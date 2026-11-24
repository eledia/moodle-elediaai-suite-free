<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

declare(strict_types=1);

namespace local_elediaai_chatengine\local;

use local_elediaai_chatengine\placement\placement;
use local_elediaai_chatengine\placement\quiz_lock_policy;

/**
 * Closes the chat while the person has a quiz attempt open (ai_suite#237).
 *
 * The chat must not become a way to look up answers during a quiz. Any open
 * attempt counts, in any course: a tutor in course A can answer from every
 * course the learner is enrolled in, and the dashboard and the site-wide
 * launcher are not in a course at all (decision E-1, 24.11.2026).
 *
 * Asked on every turn and never cached, so the chat is free again with the
 * very next message after the attempt was submitted or ran out. The query
 * goes through the user index of `quiz_attempts` and finds 0 or 1 rows.
 *
 * Three kinds of attempt do not lock:
 *
 * - previews, which are a teacher trying out the quiz;
 * - attempts the person can no longer reach -- quiz hidden from them, course
 *   hidden, enrolment gone. Such an attempt can never be submitted, and
 *   counting it would lock the chat for good;
 * - attempts in a course where the person holds
 *   `local/elediaai_chatengine:bypassquizlock`, which teachers do.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_lock {
    /** @var string The site setting that switches the lock on. Off by default (decision E-2). */
    public const SETTING = 'quizlock';

    /** @var string The capability that exempts a person in the quiz's course. */
    public const BYPASS_CAPABILITY = 'local/elediaai_chatengine:bypassquizlock';

    /**
     * Whether the lock applies to this placement instance.
     *
     * The placement's own answer wins when it gives one; otherwise the site
     * setting decides.
     *
     * @param placement $placement The placement the turn runs on.
     * @param int $instanceid The placement's instance id.
     * @return bool
     */
    public static function applies(placement $placement, int $instanceid): bool {
        $override = $placement instanceof quiz_lock_policy ? $placement->quiz_lock($instanceid) : null;

        return $override ?? (bool) get_config('local_elediaai_chatengine', self::SETTING);
    }

    /**
     * Refuse the turn when the lock applies and a quiz attempt is open.
     *
     * @param placement $placement The placement the turn runs on.
     * @param int $instanceid The placement's instance id.
     * @param int $userid The person asking; 0 for a visitor without an account.
     * @return void
     * @throws \moodle_exception error_quiz_in_progress, naming the quiz and its course.
     */
    public static function enforce(placement $placement, int $instanceid, int $userid): void {
        if ($userid <= 0 || !self::applies($placement, $instanceid)) {
            return;
        }

        $attempt = self::open_attempt($userid);
        if ($attempt === null) {
            return;
        }

        // The name only, as plain text: the chat window shows the message as
        // text and has no place for a link. The link travels as the
        // exception's own link, for a surface that can use it.
        throw new \moodle_exception(
            'error_quiz_in_progress',
            'local_elediaai_chatengine',
            $attempt->url->out(false),
            (object) ['quiz' => $attempt->quizname, 'course' => $attempt->coursename]
        );
    }

    /**
     * The open attempt that locks the chat for this person, if there is one.
     *
     * @param int $userid The person.
     * @return \stdClass|null With attemptid, quizname, coursename and url, or null.
     */
    public static function open_attempt(int $userid): ?\stdClass {
        global $DB;

        if ($userid <= 0) {
            return null;
        }

        [$insql, $params] = $DB->get_in_or_equal(['inprogress', 'overdue'], SQL_PARAMS_NAMED, 'state');
        $params['userid'] = $userid;
        $attempts = $DB->get_records_sql(
            "SELECT qa.id, qa.quiz, q.course
               FROM {quiz_attempts} qa
               JOIN {quiz} q ON q.id = qa.quiz
              WHERE qa.userid = :userid
                AND qa.preview = 0
                AND qa.state $insql
           ORDER BY qa.timestart DESC, qa.id DESC",
            $params
        );

        foreach ($attempts as $attempt) {
            $found = self::reachable($attempt, $userid);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * The attempt as a lock, or null when it does not lock this person.
     *
     * @param \stdClass $attempt With id, quiz and course.
     * @param int $userid The person.
     * @return \stdClass|null
     */
    private static function reachable(\stdClass $attempt, int $userid): ?\stdClass {
        $courseid = (int) $attempt->course;
        $coursecontext = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$coursecontext) {
            return null;
        }

        if (has_capability(self::BYPASS_CAPABILITY, $coursecontext, $userid)) {
            return null;
        }
        if (!is_enrolled($coursecontext, $userid, '', true)) {
            return null;
        }

        $modinfo = get_fast_modinfo($courseid, $userid);
        $course = $modinfo->get_course();
        if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $coursecontext, $userid)) {
            return null;
        }
        $cm = $modinfo->get_instances_of('quiz')[(int) $attempt->quiz] ?? null;
        if ($cm === null || !$cm->uservisible) {
            return null;
        }

        return (object) [
            'attemptid' => (int) $attempt->id,
            'quizname' => format_string($cm->name, true, ['context' => $cm->context, 'escape' => false]),
            'coursename' => format_string($course->fullname, true, ['context' => $coursecontext, 'escape' => false]),
            'url' => new \moodle_url('/mod/quiz/attempt.php', ['attempt' => (int) $attempt->id, 'cmid' => (int) $cm->id]),
        ];
    }
}
