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
 * The knowledge base somebody configured for a surface.
 *
 * The wish, not the result. {@see course_scope} is what a turn may actually be
 * answered from -- this is what an administrator or a teacher asked for, and it
 * is only one of the terms that go into that: it narrows, never widens, and
 * the enrolment decides.
 *
 * Stored as one comma-separated string, because categories and courses are one
 * selection with one meaning (the union of everything named). Two fields would
 * have raised the question whether they intersect or unite, and the answer
 * would need re-explaining in every second conversation:
 *
 * ```text
 * cat:12,cat:34,course:7,course:915
 * ```
 *
 * The prefixes are not decoration. A bare `12` is unreadable in a settings
 * table, and when a tutor profile is imported from another site nothing would
 * say what the number once meant.
 *
 * A category stands for its whole subtree: it is an intention ("everything
 * commercial"), not a list, and a course added to it later is meant to be
 * included. Resolving that subtree is {@see course_scope}'s job, not this
 * one's -- here the selection is only read, written and checked for shape.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class knowledge_scope {
    /** @var string Marks a course category in the stored string. */
    public const PREFIX_CATEGORY = 'cat';

    /** @var string Marks a single course in the stored string. */
    public const PREFIX_COURSE = 'course';

    /**
     * Use {@see parse()} or {@see from_selection()}.
     *
     * @param int[] $categories Category ids, ascending, without duplicates.
     * @param int[] $courses Course ids, ascending, without duplicates.
     */
    private function __construct(
        /** @var int[] The chosen categories, each standing for its subtree. */
        public readonly array $categories,
        /** @var int[] The individually chosen courses. */
        public readonly array $courses,
    ) {
    }

    /**
     * Read a stored setting.
     *
     * Forgiving on purpose: anything that is not a well-formed entry is
     * dropped rather than raised. The string can arrive from an imported
     * profile, from a hand-edited setting, or from a future version that knows
     * a prefix this one does not -- and none of those should stop a chat.
     * What is dropped was never usable.
     *
     * @param string $raw The stored value.
     * @return self
     */
    public static function parse(string $raw): self {
        $categories = [];
        $courses = [];
        foreach (explode(',', $raw) as $entry) {
            $entry = trim($entry);
            if ($entry === '' || !str_contains($entry, ':')) {
                continue;
            }
            [$prefix, $id] = explode(':', $entry, 2);
            if (!ctype_digit($id) || (int) $id <= 0) {
                continue;
            }
            if ($prefix === self::PREFIX_CATEGORY) {
                $categories[] = (int) $id;
            } else if ($prefix === self::PREFIX_COURSE) {
                $courses[] = (int) $id;
            }
        }

        return new self(self::tidy($categories), self::tidy($courses));
    }

    /**
     * Build from what a form element returns.
     *
     * The autocomplete hands back the option keys it was given, which are the
     * same prefixed entries the setting is stored as -- so this is parse()
     * with a join in front, and not a second format to keep in step.
     *
     * @param string[] $selection The chosen option keys.
     * @return self
     */
    public static function from_selection(array $selection): self {
        return self::parse(implode(',', $selection));
    }

    /**
     * Sort, drop duplicates, reindex.
     *
     * @param int[] $ids The raw ids.
     * @return int[]
     */
    private static function tidy(array $ids): array {
        $ids = array_values(array_unique($ids));
        sort($ids);
        return $ids;
    }

    /**
     * Whether nothing was chosen.
     *
     * An empty selection is not "nothing may be searched" -- it is "no wish was
     * expressed", and the surface then behaves as it did before anyone could
     * express one.
     *
     * @return bool
     */
    public function is_empty(): bool {
        return $this->categories === [] && $this->courses === [];
    }

    /**
     * The stored form.
     *
     * @return string Comma-separated, categories first, each group ascending.
     */
    public function as_string(): string {
        $entries = [];
        foreach ($this->categories as $id) {
            $entries[] = self::PREFIX_CATEGORY . ':' . $id;
        }
        foreach ($this->courses as $id) {
            $entries[] = self::PREFIX_COURSE . ':' . $id;
        }
        return implode(',', $entries);
    }

    /**
     * What the saved selection actually amounts to, as one sentence.
     *
     * The useful half of this setting is not the list but the balance: a
     * knowledge base whose courses are not indexed looks configured and
     * answers nothing, and that is the kind of thing somebody discovers
     * mid-conversation rather than in a form.
     *
     * It describes the **saved** value. Without JavaScript it cannot follow a
     * selection that has not been submitted yet, and a number that silently
     * lagged behind the field would be worse than none -- so the sentence says
     * it is about what is stored.
     *
     * @param self $wish The stored selection.
     * @return string|null The sentence, or null when nothing is chosen.
     */
    public static function index_summary(self $wish): ?string {
        if ($wish->is_empty()) {
            return null;
        }

        $resolved = course_scope::resolve_wish($wish);
        if ($resolved === []) {
            return get_string('scope_summary_none', 'local_elediaai_chatengine');
        }

        $state = '\local_elediaai_sources\course_state';
        if (!class_exists($state)) {
            return get_string('scope_summary_plain', 'local_elediaai_chatengine', count($resolved));
        }

        $indexed = call_user_func([$state, 'ingested_within'], $resolved);
        return get_string('scope_summary', 'local_elediaai_chatengine', (object) [
            'indexed' => count($indexed),
            'total' => count($resolved),
        ]);
    }

    /**
     * Which of these entries the person may not choose.
     *
     * The rule is Entscheidung 1 of the plan: a teacher may **narrow** a
     * knowledge base, never widen it. Somebody who administers the site
     * chooses freely; everybody else may name only courses they themselves
     * teach, and only categories in which they may manage courses.
     *
     * Checked when the value is saved, not merely when the list is drawn. A
     * dropdown is a convenience; a form can be posted without one.
     *
     * @param self $wish The selection somebody submitted.
     * @param int $userid The person submitting it.
     * @return string[] The rejected entries in stored form, empty when all are allowed.
     */
    public static function rejected_for(self $wish, int $userid): array {
        $system = \core\context\system::instance();
        if (
            has_capability('moodle/site:config', $system, $userid)
            || has_capability('moodle/category:manage', $system, $userid)
        ) {
            return [];
        }

        $rejected = [];
        foreach ($wish->categories as $id) {
            $context = \core\context\coursecat::instance($id, IGNORE_MISSING);
            if ($context === false || !has_capability('moodle/course:update', $context, $userid)) {
                $rejected[] = self::PREFIX_CATEGORY . ':' . $id;
            }
        }
        foreach ($wish->courses as $id) {
            $context = \core\context\course::instance($id, IGNORE_MISSING);
            if ($context === false || !has_capability('moodle/course:update', $context, $userid)) {
                $rejected[] = self::PREFIX_COURSE . ':' . $id;
            }
        }

        return $rejected;
    }

    /**
     * The names of the rejected entries, for an error a person can act on.
     *
     * @param string[] $rejected Entries as {@see rejected_for()} returns them.
     * @return string A comma-separated list of labels, falling back to the raw entry.
     */
    public static function label_rejected(array $rejected): string {
        $options = self::options();
        $labels = [];
        foreach ($rejected as $entry) {
            $labels[] = $options[$entry] ?? $entry;
        }
        return implode(', ', $labels);
    }

    /**
     * The option list for the selection field, categories first.
     *
     * One list for both kinds, because they are one selection; each entry says
     * which kind it is, so a category named like a course cannot be picked by
     * mistake. The keys are the stored entries, which is what lets
     * {@see from_selection()} be parse() with a join.
     *
     * Everything is loaded, as the ingestion settings of
     * `local_elediaai_sources` already do for the same purpose. On a site with
     * thousands of courses that is a heavy list; if it ever hurts, the place to
     * fix it is here and in that plugin at the same time, not in three forms.
     *
     * The two halves are not equally neutral: `make_categories_list()` answers
     * for the **current user** and leaves out what they may not see, while the
     * course half is read straight from the table and leaves out nothing. That
     * asymmetry is deliberate for now -- the list is a convenience, and the
     * rule that a teacher may only narrow is enforced when the value is saved
     * (T-004), not by what a dropdown happens to show.
     *
     * @return array<string, string> Option key => label.
     */
    public static function options(): array {
        global $DB;

        if (during_initial_install()) {
            return [];
        }

        $options = [];
        foreach (\core_course_category::make_categories_list() as $id => $path) {
            $options[self::PREFIX_CATEGORY . ':' . $id] =
                get_string('scope_category', 'local_elediaai_chatengine', $path);
        }

        // Including the site front page: it is a course, it can be indexed,
        // and a knowledge base that may not name it would leave a hole nobody
        // could explain.
        $courses = $DB->get_records('course', null, 'fullname ASC', 'id, fullname, shortname');
        foreach ($courses as $course) {
            $options[self::PREFIX_COURSE . ':' . $course->id] = get_string(
                'scope_course',
                'local_elediaai_chatengine',
                (object) [
                    'name' => format_string($course->fullname),
                    'shortname' => s($course->shortname),
                ]
            );
        }

        return $options;
    }
}
