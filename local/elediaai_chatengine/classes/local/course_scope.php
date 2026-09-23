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

/**
 * Which courses a turn may be answered from.
 *
 * The backend searches a corpus that holds every ingested course of the site.
 * Something has to say which slice of it this person, in this conversation, may
 * be answered from -- and that something is Moodle, because the enrolment is a
 * Moodle fact. The server filters on what it is handed and does not re-derive
 * it: the caller is an authenticated tenant and the token in the same request
 * is scoped to this one learner, so the list is the statement of an identified
 * sender about an identified person (operator decision 20.09.2026). One rule in
 * one place beats the same rule in two that can disagree.
 *
 * Three outcomes, and they are not the same thing:
 *
 * - **Named** -- these courses, and only these. A course surface names its own
 *   course; a site-wide surface names what the person is enrolled in.
 * - **None** -- no course material may be searched, because the person is
 *   enrolled nowhere. The argument is left out and the server resolves the
 *   same empty set; what must not happen is that "nothing" turns into
 *   "everything" along the way.
 * - **Overflow** -- more courses than the protocol carries. The list is left
 *   out rather than truncated, and the server resolves the enrolments itself;
 *   silently dropping courses would make material unfindable with no signal.
 *
 * The two empty cases send the same thing -- no argument -- and mean it
 * differently, which is why they are told apart here: one is "there is nothing
 * for this person", the other is "there is too much to name". The server
 * arrives at the right answer for both by resolving the enrolments itself.
 *
 * What this deliberately does **not** do is change the answer mode. Nothing to
 * search in course material is not the same as nothing to retrieve: the
 * backend also holds a corpus that has no enrolments at all behind it.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_scope {
    /** @var string These courses, and only these. */
    public const STATE_NAMED = 'named';

    /** @var string Nothing may be searched; the turn goes ungrounded. */
    public const STATE_NONE = 'none';

    /** @var string Too many to carry; the server resolves them itself. */
    public const STATE_OVERFLOW = 'overflow';

    /**
     * A knowledge base was chosen and none of it is available to this person.
     *
     * Apart from {@see STATE_NONE} because the two need different answers.
     * "Nobody enrolled anywhere" may simply retrieve nothing; "the selection
     * names courses this person is not in" must not fall back to an absent
     * argument, which the server reads as *all* their courses. The turn is
     * sent ungrounded instead.
     */
    public const STATE_EXHAUSTED = 'exhausted';

    /**
     * How many course ids the protocol carries, when nobody has said otherwise.
     *
     * Not our number: the retrieval tool behind the agent validates
     * `course_ids` and rejects a list that is too long, so a list over the
     * limit would fail the whole turn rather than widen it. Twenty is what
     * that tool accepted when this was written (MCP_Tools/server/validation.py,
     * 20.09.2026) -- but it belongs to the backend and a backend may raise it,
     * so the effective value is the site setting `maxscopecourses` and this is
     * only its default. Read it through {@see limit()}.
     */
    public const LIMIT = 20;

    /**
     * How many course ids may travel with this site's requests.
     *
     * @return int
     */
    public static function limit(): int {
        return connection::max_scope_courses();
    }

    /**
     * Use {@see for_turn()}.
     *
     * @param int[] $ids Course ids, ascending, without duplicates.
     * @param string $state One of the STATE_* constants.
     */
    private function __construct(
        /** @var int[] The courses that may be searched. */
        public readonly array $ids,
        /** @var string One of the STATE_* constants. */
        public readonly string $state,
    ) {
    }

    /**
     * The scope for one turn.
     *
     * Two surfaces, two rules, and they meet in the middle.
     *
     * **A surface that names a course** keeps that course, always. The
     * placement has already decided this person may be there, and that
     * decision can rest on more than an enrolment -- a manager reading a
     * course, a teacher not enrolled in it. Re-deriving it from enrolments
     * here would lock those people out of the material they are looking at. A
     * configured knowledge base adds to it: those of its courses the person is
     * enrolled in and that have an index.
     *
     * **A surface without a course** -- the tutor's start page -- searches
     * what the person is enrolled in, and a configured knowledge base does
     * **not** apply there (operator decision 20.09.2026). That page belongs to
     * the person, not to a course. It is also what keeps the protocol out of
     * this: without it there would be a case in which Moodle must say "no
     * courses at all", and an absent argument means the opposite.
     *
     * A guest has no enrolments and therefore no course material; that is the
     * answer, not a case to work around.
     *
     * @param int $courseid The course the surface names, or 0 for site-wide.
     * @param int $userid The acting person, 0 for a guest.
     * @param string $configured The knowledge base the surface was given, in
     *        {@see knowledge_scope} form; ignored when there is no course.
     * @return self
     */
    public static function for_turn(int $courseid, int $userid, string $configured = ''): self {
        $wish = knowledge_scope::parse($configured);

        if ($courseid <= 0) {
            // No course. A knowledge base that somebody set still counts --
            // it was chosen on purpose, and ignoring it would make the
            // setting dead exactly where an operator reached for it: a tutor
            // with "pass the course context" switched off is how you build a
            // tutor for a named set of courses. Only without one does the
            // person's own enrolment list stand in.
            return $wish->is_empty()
                ? self::for_enrolments($userid)
                : self::for_wish_alone($wish, $userid);
        }

        $ids = [$courseid];
        if (!$wish->is_empty() && $userid > 0) {
            $ids = array_merge($ids, self::allowed_from($wish, $userid));
        }
        $ids = self::tidy($ids);

        if (count($ids) > self::limit()) {
            // Over the limit the argument cannot carry everything -- but here,
            // unlike on the start page, leaving it out would *widen*: the
            // server would fall back to every course this person is in. So the
            // surface keeps its own course and nothing else, which is what it
            // searched before anyone could configure a knowledge base.
            return new self([$courseid], self::STATE_NAMED);
        }

        return new self($ids, self::STATE_NAMED);
    }

    /**
     * A surface with no course but a configured knowledge base.
     *
     * Only what was chosen, and of that only what this person may see. The
     * empty result needs care: leaving the argument out would mean "the
     * learner's own courses" to the server -- the opposite of a selection
     * that deliberately named a few. There is no way to say "no courses" in
     * the protocol, so the turn stops being a grounded one instead. That
     * costs the model-independent corpora for this one turn, and it is the
     * cheaper mistake: the alternative widens a setting meant to narrow.
     *
     * @param knowledge_scope $wish The configured selection.
     * @param int $userid The acting person, 0 for a guest.
     * @return self
     */
    private static function for_wish_alone(knowledge_scope $wish, int $userid): self {
        $ids = $userid > 0 ? self::tidy(self::allowed_from($wish, $userid)) : [];
        if ($ids === []) {
            return new self([], self::STATE_EXHAUSTED);
        }
        if (count($ids) > self::limit()) {
            // Too many to name and nothing safe to fall back to: an absent
            // argument would widen to every course this person is in. The
            // list is cut to the limit rather than dropped -- fewer courses
            // than asked for, but never more than allowed.
            return new self(array_slice($ids, 0, self::limit()), self::STATE_NAMED);
        }
        return new self($ids, self::STATE_NAMED);
    }

    /**
     * The scope of a surface that has no course: the person's own courses.
     *
     * @param int $userid The acting person, 0 for a guest.
     * @return self
     */
    private static function for_enrolments(int $userid): self {
        if ($userid <= 0) {
            return new self([], self::STATE_NONE);
        }

        $ids = self::enrolled($userid);
        if ($ids === []) {
            return new self([], self::STATE_NONE);
        }
        if (count($ids) > self::limit()) {
            return new self($ids, self::STATE_OVERFLOW);
        }
        return new self($ids, self::STATE_NAMED);
    }

    /**
     * The configured knowledge base, reduced to what this person may be shown.
     *
     * Three steps, and the order is the point: expand, then intersect with the
     * enrolments, then with what is indexed. Expanding is the expensive part
     * and does not depend on the person, so it is cached; intersecting does
     * depend on the person and is never cached. Because the intersection comes
     * before anything is counted, a category with three hundred courses is
     * harmless as long as the learner is in seven of them.
     *
     * @param knowledge_scope $wish The configured selection.
     * @param int $userid The acting person.
     * @return int[] Course ids, in no particular order.
     */
    private static function allowed_from(knowledge_scope $wish, int $userid): array {
        $wanted = self::expand($wish);
        if ($wanted === []) {
            return [];
        }

        // Nobody is enrolled in the site front page, and everybody may see it.
        // Without this it could be chosen and would then always be filtered
        // away -- an entry in the list that never does anything. This is not
        // the special case that was removed elsewhere: that one excluded the
        // front page from being a course, this one states who may read it.
        $visible = self::enrolled($userid);
        if (in_array((int) SITEID, $wanted, true)) {
            $visible[] = (int) SITEID;
        }

        $allowed = array_values(array_intersect($wanted, $visible));
        if ($allowed === []) {
            return [];
        }

        // Without the ingestion plugin nothing is indexed and nothing can be
        // said about it; the selection then stands as it is, and whether the
        // turn is grounded at all is decided elsewhere.
        $state = '\local_elediaai_sources\course_state';
        if (!class_exists($state)) {
            return $allowed;
        }
        return call_user_func([$state, 'ingested_within'], $allowed);
    }

    /**
     * A selection expanded to course ids, without any person in the picture.
     *
     * For the settings form, which reports what a saved selection amounts to
     * before anybody asks a question in it. A turn never uses this on its own
     * -- there the enrolments come next, and they are the point.
     *
     * @param knowledge_scope $wish The configured selection.
     * @return int[] Course ids, ascending.
     */
    public static function resolve_wish(knowledge_scope $wish): array {
        return $wish->is_empty() ? [] : self::expand($wish);
    }

    /**
     * A selection expanded to plain course ids: subtrees resolved, courses added.
     *
     * A category stands for everything below it -- including a course created
     * in it tomorrow, which is why this is resolved per turn rather than
     * frozen into the setting. The cache is what makes that affordable.
     *
     * @param knowledge_scope $wish The configured selection.
     * @return int[] Course ids, ascending, without duplicates.
     */
    private static function expand(knowledge_scope $wish): array {
        global $DB;

        $cache = \cache::make('local_elediaai_chatengine', 'coursescope');
        $key = sha1($wish->as_string());
        $cached = $cache->get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $ids = $wish->courses;
        if ($wish->categories !== []) {
            $ids = array_merge($ids, self::courses_below($wish->categories));
        }

        $ids = self::tidy($ids);
        $cache->set($key, $ids);
        return $ids;
    }

    /**
     * Every course in these categories and below them, read from the tables.
     *
     * Deliberately **not** `core_course_category::get_courses()`. That method
     * answers for the current user and leaves out what they may not see -- so
     * the same selection would expand differently per person, and the cache
     * above would hand one learner's answer to the next. Which courses a
     * person may be answered from is decided one step later, by their
     * enrolments, and that is the only place it should be decided.
     *
     * The subtree comes from the category path rather than from recursion:
     * `/1/5/12` and everything starting with `/1/5/12/`, which is one query
     * however deep the tree goes.
     *
     * @param int[] $categoryids The chosen categories.
     * @return int[] Course ids, unsorted, possibly with duplicates.
     */
    private static function courses_below(array $categoryids): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'cat');
        $categories = $DB->get_records_select('course_categories', "id {$insql}", $params, '', 'id, path');
        if ($categories === []) {
            // Chosen and since deleted. The selection is not repaired here: an
            // administrator may be moving things about, and a chat turn is the
            // wrong place to decide that a setting has gone stale.
            return [];
        }

        $where = [];
        $params = ['siteid' => SITEID];
        $i = 0;
        foreach ($categories as $category) {
            $where[] = '(cc.id = :cid' . $i . ' OR ' . $DB->sql_like('cc.path', ':path' . $i) . ')';
            $params['cid' . $i] = (int) $category->id;
            $params['path' . $i] = $DB->sql_like_escape($category->path) . '/%';
            $i++;
        }

        $sql = 'SELECT c.id
                  FROM {course} c
                  JOIN {course_categories} cc ON cc.id = c.category
                 WHERE c.id <> :siteid AND (' . implode(' OR ', $where) . ')';

        return array_map('intval', $DB->get_fieldset_sql($sql, $params));
    }

    /**
     * The courses a person is actively enrolled in, ids only.
     *
     * Asked afresh every turn, on purpose. A static cache here was the first
     * version and it was wrong twice over: the enrolments can change under a
     * long-lived process, and in a test run the user ids start over after each
     * reset, so the second test read the first one's courses. The tests caught
     * it. The query costs nothing next to the model call that follows it.
     *
     * @param int $userid The person.
     * @return int[] Ascending, without the site course.
     */
    private static function enrolled(int $userid): array {
        // onlyactive: a suspended enrolment, or one whose dates have passed, is
        // not a course somebody may be answered from.
        $courses = enrol_get_users_courses($userid, true, 'id');
        $ids = [];
        foreach ($courses as $course) {
            $id = (int) $course->id;
            if ($id > 0 && $id != SITEID) {
                $ids[] = $id;
            }
        }
        sort($ids);
        return $ids;
    }

    /**
     * Sort, drop duplicates, reindex.
     *
     * @param int[] $ids The raw ids.
     * @return int[] Ascending, without duplicates.
     */
    private static function tidy(array $ids): array {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);
        return $ids;
    }

    /**
     * Whether this turn must not retrieve from course material at all.
     *
     * True only for the one case the protocol cannot express: a selection was
     * made, and this person may see none of it. Leaving the argument out
     * would mean "all of their courses" to the server, so the turn drops out
     * of grounded mode instead.
     *
     * @return bool
     */
    public function is_exhausted(): bool {
        return $this->state === self::STATE_EXHAUSTED;
    }

    /**
     * The `course_id` argument for the backend, or null to omit it.
     *
     * Null for both of the cases that are not a list: nothing entitled, and too
     * many to carry. The contract reads the absent argument as "the learner's
     * own courses", and the server resolving them lands on the right answer
     * either way -- on nothing for the first, on all of them for the second.
     *
     * @return string|null Comma-separated ids, no spaces.
     */
    public function as_argument(): ?string {
        if ($this->state !== self::STATE_NAMED || $this->ids === []) {
            return null;
        }
        return implode(',', $this->ids);
    }
}
