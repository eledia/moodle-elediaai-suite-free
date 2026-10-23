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

namespace webservice_elediamcp\local\ai\tools;

use context_course;
use context_system;
use grade_category;
use grade_grade;
use grade_item;
use moodle_url;
use stdClass;
use webservice_elediamcp\local\ai\ai_tool;
use webservice_elediamcp\local\ai\tool_exception;

/**
 * AI-native gradebook overview.
 *
 * Returns the authenticated user's course-final grade across each enrolled
 * course (defaults) or, when include_items is set, the per-item grade
 * breakdown for a single course. Visibility follows the core user report: hidden
 * grades, their feedback and totals that contain them are withheld unless the user
 * has moodle/grade:viewhidden.
 *
 * @package     webservice_elediamcp
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_my_grades implements ai_tool {
    /** @var string Grade string for a value that exists but is not shown to the user yet. */
    private const WITHHELD = 'not shown yet';

    /** @var string Summary sentence when grades are withheld (the values themselves are never given). */
    private const WITHHELD_NOTE = 'Some grades exist that are not shown to you yet: where grade_withheld is true, '
        . 'null values mean "not released", not "no grade".';

    /** @var string Summary sentence explaining a missing pass verdict. */
    private const PASS_NOTE = 'has_passed is null where the pass status cannot be determined or is not shown.';

    /** @var int Core GRADE_REPORT_USER_HIDE_HIDDEN (grade/report/user/lib.php): list no hidden grades. */
    private const SHOW_HIDDEN_NONE = 0;

    /** @var int Core GRADE_REPORT_USER_HIDE_UNTIL: list only grades hidden until a date. */
    private const SHOW_HIDDEN_UNTIL_ONLY = 1;

    /**
     * Tool name.
     *
     * @return string
     */
    public static function name(): string {
        return 'moodle_my_grades';
    }

    /**
     * Tool title.
     *
     * @return string
     */
    public static function title(): string {
        return 'My grades across courses';
    }

    /**
     * Tool description.
     *
     * @return string
     */
    public static function description(): string {
        return 'Returns the authenticated user\'s course-final grade across each enrolled course. '
            . 'Set include_items=true with a course_id to receive the per-grade-item breakdown for '
            . 'that course (limited to items the user is allowed to see). Grades that Moodle does not '
            . 'show to the user yet are withheld: the value is null and the summary says so. '
            . 'grade_withheld is true when a grade exists but must not be shown yet and false when there is '
            . 'no grade yet; grades_available false means the course does not show grades to the user at all. '
            . 'has_passed null means unknown or not shown; never report a withheld grade as "no grade" or as 0.';
    }

    /**
     * Input schema.
     *
     * @return array<string, mixed>
     */
    public static function input_schema(): array {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'course_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'Restrict to a single course. Required when include_items=true.',
                ],
                'include_items' => [
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Include per-grade-item breakdown for the chosen course.',
                ],
            ],
        ];
    }

    /**
     * Output schema.
     *
     * @return array<string, mixed>
     */
    public static function output_schema(): array {
        return [
            'type' => 'object',
            'required' => ['courses', 'summary'],
            'properties' => [
                'courses' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['course_id', 'course_name', 'final_grade_str'],
                        'properties' => [
                            'course_id' => ['type' => 'integer'],
                            'course_name' => ['type' => 'string'],
                            'final_grade' => ['type' => ['number', 'null']],
                            'final_grade_max' => ['type' => ['number', 'null']],
                            'final_grade_str' => ['type' => 'string'],
                            'final_grade_long' => ['type' => 'string'],
                            'pass_grade' => ['type' => ['number', 'null']],
                            'has_passed' => ['type' => ['boolean', 'null']],
                            'feedback_excerpt' => ['type' => 'string'],
                            'grade_withheld' => [
                                'type' => 'boolean',
                                'description' => 'True when a course total (or its feedback) exists that Moodle does not '
                                    . 'show to the user yet; the grade fields are then null. False with a null grade '
                                    . 'means not graded yet.',
                            ],
                            'grades_available' => [
                                'type' => 'boolean',
                                'description' => 'False when the user may not view grades in this course at all '
                                    . '(gradebook not shown to learners or missing permission).',
                            ],
                            'url' => ['type' => 'string'],
                            'items' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'required' => ['item_id', 'name', 'grade_str'],
                                    'properties' => [
                                        'item_id' => ['type' => 'integer'],
                                        'name' => ['type' => 'string'],
                                        'item_type' => ['type' => 'string'],
                                        'item_module' => ['type' => 'string'],
                                        'grade' => ['type' => ['number', 'null']],
                                        'grade_max' => ['type' => ['number', 'null']],
                                        'grade_str' => ['type' => 'string'],
                                        'graded_iso' => ['type' => ['string', 'null']],
                                        'grade_withheld' => [
                                            'type' => 'boolean',
                                            'description' => 'True when a grade (or its feedback) exists that Moodle '
                                                . 'does not show to the user yet; grade is then null. False with a '
                                                . 'null grade means not graded yet.',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'summary' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * Tool annotations.
     *
     * @return array<string, mixed>
     */
    public static function annotations(): array {
        return [
            'title' => self::title(),
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => false,
        ];
    }

    /**
     * Execute the tool.
     *
     * Visibility follows the core user report: the gradebook is only shown when the course
     * shows grades to learners (or the user can view all grades), hidden grades and their
     * feedback are withheld without moodle/grade:viewhidden, and course or category totals
     * that contain hidden grades follow the "Hide totals if they contain hidden items"
     * setting. The response is not cached because hidden-until dates and grade releases
     * take effect immediately in Moodle.
     *
     * @param array<string, mixed> $arguments Validated arguments.
     * @param stdClass $user Authenticated user record.
     * @return array<string, mixed>
     */
    public static function execute(array $arguments, stdClass $user): array {
        global $CFG;

        $userid = (int) $user->id;
        $courseidfilter = isset($arguments['course_id']) ? (int) $arguments['course_id'] : 0;
        $includeitems = !empty($arguments['include_items']);
        if ($includeitems && $courseidfilter <= 0) {
            throw new tool_exception('include_items=true requires course_id.');
        }

        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/querylib.php');

        $usercourses = enrol_get_users_courses($userid, true, ['id', 'fullname', 'showgrades']);
        if ($courseidfilter > 0) {
            $usercourses = array_filter($usercourses, static fn($c) => (int) $c->id === $courseidfilter);
            if (empty($usercourses) && !is_siteadmin($user)) {
                throw new tool_exception(
                    "Course {$courseidfilter} is not available to you.",
                    ['course_id' => $courseidfilter]
                );
            }
        }
        if (empty($usercourses)) {
            return [
                'courses' => [],
                'summary' => 'No enrolled courses with a gradebook.',
            ];
        }

        $isadmin = is_siteadmin($user);
        $stringopts = ['context' => context_system::instance(), 'filter' => false, 'escape' => true];
        $coursesout = [];
        $passed = 0;
        $failed = 0;
        $ungraded = 0;
        $denied = 0;
        $withheld = false;
        $passunknown = false;

        foreach ($usercourses as $course) {
            $courseid = (int) $course->id;
            $coursename = format_string((string) $course->fullname, true, $stringopts);
            $coursecontext = context_course::instance($courseid);
            // Same rule as grade/report/user/index.php: the user report capability first, then viewall
            // always, own grades only if the course shows them.
            $canviewall = $isadmin || has_capability('moodle/grade:viewall', $coursecontext, $userid);
            $canview = ($isadmin || has_capability('gradereport/user:view', $coursecontext, $userid))
                && ($canviewall || (!empty($course->showgrades)
                    && has_capability('moodle/grade:view', $coursecontext, $userid)));
            $canviewhidden = $isadmin || has_capability('moodle/grade:viewhidden', $coursecontext, $userid);

            $finalgrade = null;
            $finalmax = null;
            $finalstr = '-';
            $finallong = '-';
            $passgrade = null;
            $haspassed = null;
            $feedback = '';
            $coursewithheld = false;
            $totalwithheld = false;
            // Computed at most once per course, see visible_total().
            $affected = null;

            if ($canview) {
                $info = grade_get_course_grade($userid, $courseid);
                if ($info && !empty($info->item)) {
                    $finalmax = isset($info->item->grademax) ? (float) $info->item->grademax : null;
                    $passgrade = isset($info->item->gradepass) && $info->item->gradepass > 0
                        ? (float) $info->item->gradepass : null;
                    $gradehidden = !$canviewhidden && !empty($info->hidden);
                    if ($gradehidden) {
                        // The course total itself is hidden from this user: no value, no feedback.
                        if (($info->grade !== false && $info->grade !== null) || !empty($info->feedback)) {
                            $coursewithheld = true;
                            $totalwithheld = true;
                            $finalstr = self::WITHHELD;
                            $finallong = 'The course total is not shown yet.';
                        }
                    } else if ($info->grade !== false && $info->grade !== null) {
                        $total = $canviewhidden
                            ? ['grade' => (float) $info->grade, 'grademin' => null, 'grademax' => null, 'changed' => false]
                            : self::visible_total(
                                $courseid,
                                $userid,
                                grade_item::fetch_course_item($courseid),
                                (float) $info->grade,
                                $affected
                            );
                        if ($total['grade'] === null) {
                            $coursewithheld = true;
                            $totalwithheld = true;
                            $finalstr = self::WITHHELD;
                            $finallong = 'The course total is not shown yet because it contains grades that are not shown yet.';
                        } else if (!$total['changed']) {
                            $finalgrade = (float) $info->grade;
                            $finalstr = (string) ($info->str_grade ?? '-');
                            $finallong = (string) ($info->str_long_grade ?? $finalstr);
                            if ($passgrade !== null) {
                                $haspassed = $finalgrade >= $passgrade;
                            }
                        } else {
                            // Total recalculated without hidden grades, as the user report shows it.
                            $courseitem = clone grade_item::fetch_course_item($courseid);
                            if ($total['grademax'] !== null) {
                                $courseitem->grademax = $total['grademax'];
                            }
                            if ($total['grademin'] !== null) {
                                $courseitem->grademin = $total['grademin'];
                            }
                            $finalgrade = $total['grade'];
                            $finalmax = (float) $courseitem->grademax;
                            $finalstr = (string) grade_format_gradevalue($finalgrade, $courseitem);
                            $finallong = self::long_grade($finalstr, $courseitem);
                            // The pass mark refers to the real total, which is not shown: no verdict.
                            $haspassed = null;
                            $coursewithheld = true;
                        }
                    } else {
                        // Grade === false means needsupdate / not yet calculated.
                        $rawstr = (string) ($info->str_grade ?? '-');
                        $errorstr = get_string('error');
                        $finalstr = ($rawstr === $errorstr || $rawstr === '-')
                            ? 'Not yet calculated' : $rawstr;
                        $finallong = (string) ($info->str_long_grade ?? $finalstr);
                        if ($finallong === $errorstr) {
                            $finallong = 'Course grade has not been calculated yet.';
                        }
                    }
                    if (!$gradehidden && !empty($info->feedback)) {
                        $feedback = self::excerpt(
                            (string) $info->feedback,
                            (int) ($info->feedbackformat ?? FORMAT_HTML)
                        );
                    }
                }
            } else {
                $finalstr = 'access denied';
                $finallong = 'You do not have permission to view grades in this course.';
                $denied++;
            }

            if (!$canview) {
                // Counted as denied above, never as "not yet graded".
                $haspassed = null;
            } else if ($haspassed === true) {
                $passed++;
            } else if ($haspassed === false) {
                $failed++;
            } else if ($finalgrade === null && !$coursewithheld) {
                $ungraded++;
            }

            $entry = [
                'course_id' => $courseid,
                'course_name' => $coursename,
                'final_grade' => $finalgrade,
                'final_grade_max' => $finalmax,
                'final_grade_str' => $finalstr,
                'final_grade_long' => $finallong,
                'pass_grade' => $passgrade,
                'has_passed' => $haspassed,
                'feedback_excerpt' => $feedback,
                'grade_withheld' => $totalwithheld,
                'grades_available' => $canview,
                'url' => (new moodle_url('/grade/report/user/index.php', ['id' => $courseid]))->out(false),
            ];

            if ($includeitems && $courseidfilter === $courseid && $canview) {
                $items = self::collect_items($courseid, $userid, $canviewhidden, $stringopts, $affected);
                $entry['items'] = $items['items'];
                $coursewithheld = $coursewithheld || $items['withheld'];
            }

            $withheld = $withheld || $coursewithheld;
            $passunknown = $passunknown || ($canview && $passgrade !== null && $haspassed === null);
            $coursesout[] = $entry;
        }

        return [
            'courses' => $coursesout,
            'summary' => self::build_summary(
                count($coursesout),
                $passed,
                $failed,
                $ungraded,
                $courseidfilter,
                $withheld,
                $denied,
                $passunknown
            ),
        ];
    }

    /**
     * Collect the per-item grade breakdown for a single course.
     *
     * Mirrors the user report (grade/report/user/classes/report/user.php): rows of hidden
     * grades and everything below a hidden category are left out unless the "Show hidden
     * items" setting lists them, in which case the value stays null; activities the user
     * cannot see are left out; category totals follow the same hidden-total rule as the
     * course total.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param bool $canviewhidden Whether the user may see hidden grades.
     * @param array<string, mixed> $stringopts format_string options.
     * @param array<string, array<int, mixed>>|null $affected Hiding information, computed once per course.
     * @return array{items: array<int, array<string, mixed>>, withheld: bool}
     */
    private static function collect_items(
        int $courseid,
        int $userid,
        bool $canviewhidden,
        array $stringopts,
        ?array &$affected
    ): array {
        global $CFG;

        $items = grade_item::fetch_all(['courseid' => $courseid]);
        if (empty($items)) {
            return ['items' => [], 'withheld' => false];
        }
        $showhiddenitems = (int) grade_get_setting(
            $courseid,
            'report_user_showhiddenitems',
            $CFG->grade_report_user_showhiddenitems ?? self::SHOW_HIDDEN_UNTIL_ONLY
        );
        $modinfo = get_fast_modinfo($courseid, $userid);
        $categories = $canviewhidden ? [] : (grade_category::fetch_all(['courseid' => $courseid]) ?: []);
        $withheld = false;
        $out = [];
        foreach ($items as $item) {
            // Skip course total (already exposed as final_grade).
            if ($item->itemtype === 'course') {
                continue;
            }
            $grade = new grade_grade(['itemid' => (int) $item->id, 'userid' => $userid]);
            $grade->grade_item = $item;
            $hasvalue = $grade->finalgrade !== null;
            // A grade "exists" when it has a value or feedback.
            $hasdata = $hasvalue || trim((string) ($grade->feedback ?? '')) !== '';
            $hidden = !$canviewhidden && $grade->is_hidden();

            if ($hidden) {
                $withheld = $withheld || $hasdata;
            }
            if (
                $hidden && (
                    $showhiddenitems === self::SHOW_HIDDEN_NONE
                    || ($showhiddenitems === self::SHOW_HIDDEN_UNTIL_ONLY && !$grade->is_hiddenuntil())
                )
            ) {
                continue;
            }
            // The user report does not descend into a hidden category (user.php, fill_table_recursive()).
            if (!$canviewhidden && self::in_hidden_category($item, $categories, $showhiddenitems)) {
                $withheld = $withheld || $hasdata;
                continue;
            }
            if (!empty($item->itemmodule) && !empty($item->iteminstance)) {
                // Activities the user cannot see are left out, as in the user report (for every listed row).
                $instances = $modinfo->get_instances_of((string) $item->itemmodule);
                $cm = $instances[(int) $item->iteminstance] ?? null;
                if ($cm && !$cm->uservisible && (!$cm->visible || !$cm->availableinfo)) {
                    continue;
                }
            }

            $display = $item;
            $gradeval = null;
            if (!$hidden && $hasvalue) {
                $gradeval = (float) $grade->finalgrade;
                if (!$canviewhidden && $item->is_category_item()) {
                    $total = self::visible_total($courseid, $userid, $item, $gradeval, $affected);
                    $gradeval = $total['grade'];
                    if ($total['changed']) {
                        $withheld = true;
                        $display = clone $item;
                        if ($total['grademax'] !== null) {
                            $display->grademax = $total['grademax'];
                        }
                        if ($total['grademin'] !== null) {
                            $display->grademin = $total['grademin'];
                        }
                    }
                }
            }
            $valuewithheld = ($hidden && $hasdata) || ($hasvalue && $gradeval === null);

            if ($valuewithheld) {
                $gradestr = self::WITHHELD;
            } else {
                $gradestr = $gradeval !== null ? grade_format_gradevalue($gradeval, $display) : '-';
            }

            $out[] = [
                'item_id' => (int) $item->id,
                'name' => format_string((string) ($item->get_name() ?? ''), true, $stringopts),
                'item_type' => (string) ($item->itemtype ?? ''),
                'item_module' => (string) ($item->itemmodule ?? ''),
                'grade' => $gradeval,
                'grade_max' => isset($display->grademax) ? (float) $display->grademax : null,
                'grade_str' => (string) $gradestr,
                'graded_iso' => (!$hidden && !$valuewithheld && $grade->timemodified)
                    ? gmdate('c', (int) $grade->timemodified) : null,
                'grade_withheld' => $valuewithheld,
            ];
        }
        return ['items' => $out, 'withheld' => $withheld];
    }

    /**
     * Apply the "Hide totals if they contain hidden items" rule to a course or category total.
     *
     * Same logic as grade_report::blank_hidden_total_and_adjust_bounds() in grade/report/lib.php,
     * which is protected and bound to a report instance.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param grade_item $totalitem Course or category grade item.
     * @param float $finalgrade Real total of the user.
     * @param array<string, array<int, mixed>>|null $affected Hiding information; computed on first use and
     *     kept by the caller for the rest of the course (the user report caches it the same way).
     * @return array{grade: ?float, grademin: ?float, grademax: ?float, changed: bool}
     */
    private static function visible_total(
        int $courseid,
        int $userid,
        grade_item $totalitem,
        float $finalgrade,
        ?array &$affected
    ): array {
        global $CFG;

        $result = ['grade' => $finalgrade, 'grademin' => null, 'grademax' => null, 'changed' => false];
        $setting = (int) grade_get_setting(
            $courseid,
            'report_user_showtotalsifcontainhidden',
            $CFG->grade_report_user_showtotalsifcontainhidden ?? GRADE_REPORT_HIDE_TOTAL_IF_CONTAINS_HIDDEN
        );
        if ($setting === GRADE_REPORT_SHOW_REAL_TOTAL_IF_CONTAINS_HIDDEN) {
            return $result;
        }

        if ($affected === null) {
            $affected = self::hiding_affected($courseid, $userid);
        }
        $id = (int) $totalitem->id;
        $hide = $setting === GRADE_REPORT_HIDE_TOTAL_IF_CONTAINS_HIDDEN;

        if (array_key_exists($id, $affected['altered']) && $hide) {
            return ['grade' => null, 'grademin' => null, 'grademax' => null, 'changed' => true];
        }
        if (array_key_exists($id, $affected['altered'])) {
            $result['grade'] = $affected['altered'][$id] === null ? null : (float) $affected['altered'][$id];
            $result['changed'] = true;
        } else if (array_key_exists($id, $affected['unknowngrades'])) {
            if ($hide) {
                return ['grade' => null, 'grademin' => null, 'grademax' => null, 'changed' => true];
            }
            $result['grade'] = $affected['unknowngrades'][$id] === null ? null : (float) $affected['unknowngrades'][$id];
            $result['changed'] = true;
        }
        if (array_key_exists($id, $affected['alteredgrademin'])) {
            $result['grademin'] = (float) $affected['alteredgrademin'][$id];
            $result['changed'] = true;
        }
        if (array_key_exists($id, $affected['alteredgrademax'])) {
            $result['grademax'] = (float) $affected['alteredgrademax'][$id];
            $result['changed'] = true;
        }
        return $result;
    }

    /**
     * Whether an item sits in (or is the total of) a hidden category that the user report leaves out.
     *
     * Hiding a category in the gradebook UI cascades to its items, but the API allows a hidden
     * category with visible items; the report then still skips the whole subtree.
     *
     * @param grade_item $item Grade item.
     * @param array<int, grade_category> $categories Categories of the course, keyed by id.
     * @param int $showhiddenitems Value of report_user_showhiddenitems.
     * @return bool
     */
    private static function in_hidden_category(grade_item $item, array $categories, int $showhiddenitems): bool {
        $categoryid = $item->is_category_item() ? (int) $item->iteminstance : (int) $item->categoryid;
        $guard = 0;
        while ($categoryid > 0 && isset($categories[$categoryid]) && $guard++ < 100) {
            $category = $categories[$categoryid];
            if (
                $category->is_hidden() && (
                    $showhiddenitems === self::SHOW_HIDDEN_NONE
                    || ($showhiddenitems === self::SHOW_HIDDEN_UNTIL_ONLY && !$category->is_hiddenuntil())
                )
            ) {
                return true;
            }
            $categoryid = (int) ($category->parent ?? 0);
        }
        return false;
    }

    /**
     * Compute which aggregated grades of a user depend on hidden grades.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @return array<string, array<int, mixed>> Result of grade_grade::get_hiding_affected().
     */
    private static function hiding_affected(int $courseid, int $userid): array {
        global $DB;

        $items = grade_item::fetch_all(['courseid' => $courseid]) ?: [];
        $grades = [];
        $records = $DB->get_records_sql(
            'SELECT g.*
               FROM {grade_grades} g
               JOIN {grade_items} gi ON gi.id = g.itemid
              WHERE g.userid = :userid AND gi.courseid = :courseid',
            ['userid' => $userid, 'courseid' => $courseid]
        );
        foreach ($records as $record) {
            $grades[(int) $record->itemid] = new grade_grade($record, false);
        }
        foreach ($items as $itemid => $unused) {
            if (!isset($grades[$itemid])) {
                $grades[$itemid] = new grade_grade(['userid' => $userid, 'itemid' => $items[$itemid]->id], false);
            }
            $grades[$itemid]->grade_item =& $items[$itemid];
        }
        return grade_grade::get_hiding_affected($grades, $items);
    }

    /**
     * Long form of a grade, as grade_get_course_grade() builds it.
     *
     * @param string $gradestr Formatted grade.
     * @param grade_item $item Grade item with the bounds to show.
     * @return string
     */
    private static function long_grade(string $gradestr, grade_item $item): string {
        if ((int) $item->gradetype === GRADE_TYPE_SCALE || (int) $item->get_displaytype() !== GRADE_DISPLAY_TYPE_REAL) {
            return $gradestr;
        }
        return get_string('gradelong', 'grades', (object) [
            'grade' => $gradestr,
            'max' => grade_format_gradevalue((float) $item->grademax, $item),
        ]);
    }

    /**
     * Build a plain-text excerpt of grade feedback.
     *
     * @param string $feedback Raw feedback.
     * @param int $format Text format.
     * @return string
     */
    private static function excerpt(string $feedback, int $format): string {
        $opts = ['context' => context_system::instance(), 'filter' => false, 'noclean' => true];
        $formatted = format_text($feedback, $format, $opts);
        $plain = trim(html_to_text($formatted, 0, false));
        if ($plain === '') {
            return '';
        }
        if (\core_text::strlen($plain) > 300) {
            $plain = \core_text::substr($plain, 0, 297) . '...';
        }
        return $plain;
    }

    /**
     * Build a one-sentence summary for the LLM.
     *
     * @param int $total Total courses inspected.
     * @param int $passed Courses with a pass grade reached.
     * @param int $failed Courses below the pass grade.
     * @param int $ungraded Courses with no grade yet.
     * @param int $courseidfilter Course filter (0 = none).
     * @param bool $withheld Whether grades exist that are not shown to the user yet.
     * @param int $denied Courses whose grades the user may not view.
     * @param bool $passunknown Whether a course has a pass grade but no pass verdict.
     * @return string
     */
    private static function build_summary(
        int $total,
        int $passed,
        int $failed,
        int $ungraded,
        int $courseidfilter,
        bool $withheld = false,
        int $denied = 0,
        bool $passunknown = false
    ): string {
        if ($denied > 0 && $denied === $total) {
            $summary = $courseidfilter > 0
                ? 'Grades of the requested course are not available to you.'
                : sprintf('Grades are not available to you in any of the %d courses.', $total);
        } else {
            $summary = self::build_counts_summary($total - $denied, $passed, $failed, $ungraded, $courseidfilter);
            if ($denied > 0) {
                $summary .= sprintf(' Grades are not available to you in %d further course(s).', $denied);
            }
        }
        if ($withheld) {
            $summary .= ' ' . self::WITHHELD_NOTE;
        }
        if ($passunknown) {
            $summary .= ' ' . self::PASS_NOTE;
        }
        return $summary;
    }

    /**
     * Build the count part of the summary.
     *
     * @param int $total Total courses inspected.
     * @param int $passed Courses with a pass grade reached.
     * @param int $failed Courses below the pass grade.
     * @param int $ungraded Courses with no grade yet.
     * @param int $courseidfilter Course filter (0 = none).
     * @return string
     */
    private static function build_counts_summary(int $total, int $passed, int $failed, int $ungraded, int $courseidfilter): string {
        if ($total === 0) {
            return 'No grade information available.';
        }
        if ($total === 1 && $courseidfilter > 0) {
            if ($passed > 0) {
                return 'Pass grade reached in the requested course.';
            }
            if ($failed > 0) {
                return 'Pass grade not yet reached in the requested course.';
            }
            return $ungraded > 0
                ? 'No final grade recorded yet for the requested course.'
                : 'Grade information available for the requested course.';
        }
        return sprintf(
            'Grades across %d courses: %d passed, %d below pass, %d not yet graded.',
            $total,
            $passed,
            $failed,
            $ungraded
        );
    }
}
