<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

declare(strict_types=1);

namespace webservice_elediamcp\local\ai\tools;

use context_course;
use context_module;
use moodle_url;
use stdClass;
use Throwable;
use webservice_elediamcp\local\ai\ai_tool;
use webservice_elediamcp\local\ai\tool_exception;
use webservice_elediamcp\local\learning_objectives;

/**
 * Teacher tool: set, extend or remove the learning objectives of a course, a section or an activity.
 *
 * Only the objectives block of the description changes; text, images and
 * files around it stay as they are.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_set_learning_objectives implements ai_tool {
    /** @var string Target: course summary. */
    private const TARGET_COURSE = 'course';

    /** @var string Target: section summary. */
    private const TARGET_SECTION = 'section';

    /** @var string Target: activity description. */
    private const TARGET_ACTIVITY = 'activity';

    /**
     * Return the MCP tool name.
     *
     * @return string
     */
    public static function name(): string {
        return 'moodle_set_learning_objectives';
    }

    /**
     * Return the human-readable tool title.
     *
     * @return string
     */
    public static function title(): string {
        return 'Set learning objectives';
    }

    /**
     * Return the tool description shown to MCP clients.
     *
     * @return string
     */
    public static function description(): string {
        return 'Sets the learning objectives of a course (course summary), a section (section summary, '
            . 'pass section) or an activity (its description, pass cmid). Only the objectives box in the '
            . 'description changes; everything else stays. mode: replace (default; writes the box, '
            . 'creates it if missing), append (adds objectives to the existing box) or remove. '
            . 'Objectives are short sentences, one per entry, phrased as what learners can do afterwards. '
            . 'When the site has "Components for Learning" (tiny_c4l), the box uses its Learning '
            . 'outcomes component (style auto). Returns a preview with the objectives before and after; '
            . 'call again with confirm=true to write. Use moodle_course_contents to find sections and cmids.';
    }

    /**
     * Return the input schema.
     *
     * @return array<string, mixed>
     */
    public static function input_schema(): array {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['course_id'],
            'properties' => [
                'course_id' => ['type' => 'integer', 'minimum' => 1],
                'section' => [
                    'type' => 'integer',
                    'minimum' => 0,
                    'description' => 'Section number; set it to target the section summary.',
                ],
                'cmid' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'Course module id; set it to target the activity description.',
                ],
                'objectives' => [
                    'type' => 'array',
                    'maxItems' => learning_objectives::MAX_ITEMS,
                    'items' => ['type' => 'string', 'maxLength' => learning_objectives::MAX_LENGTH],
                    'description' => 'The objectives, one sentence each. Required unless mode is remove.',
                ],
                'mode' => ['type' => 'string', 'enum' => ['replace', 'append', 'remove'], 'default' => 'replace'],
                'heading' => [
                    'type' => 'string',
                    'maxLength' => 100,
                    'description' => 'Heading of the box. Defaults to the existing one or "Learning objectives".',
                ],
                'style' => ['type' => 'string', 'enum' => ['auto', 'c4l', 'plain'], 'default' => 'auto'],
                'numbered' => ['type' => 'boolean', 'default' => false],
                'position' => [
                    'type' => 'string',
                    'enum' => ['top', 'bottom'],
                    'default' => 'top',
                    'description' => 'Where a new box goes; an existing box keeps its place.',
                ],
                'confirm' => ['type' => 'boolean', 'default' => false],
            ],
        ];
    }

    /**
     * Return the output schema.
     *
     * @return array<string, mixed>
     */
    public static function output_schema(): array {
        return [
            'type' => 'object',
            'required' => ['done', 'requires_confirmation', 'summary'],
            'properties' => [
                'done' => ['type' => 'boolean'],
                'requires_confirmation' => ['type' => 'boolean'],
                'target' => [
                    'type' => 'object',
                    'properties' => [
                        'type' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'url' => ['type' => 'string'],
                    ],
                ],
                'style' => ['type' => 'string'],
                'objectives' => ['type' => 'array', 'items' => ['type' => 'string']],
                'preview' => [
                    'type' => 'object',
                    'properties' => [
                        'mode' => ['type' => 'string'],
                        'before' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'after' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'format_converted' => [
                            'type' => 'boolean',
                            'description' => 'The description was plain text or Markdown and is saved as HTML.',
                        ],
                        'card_summary' => card_summary::schema(),
                    ],
                ],
                'summary' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * Return MCP tool annotations.
     *
     * @return array<string, mixed>
     */
    public static function annotations(): array {
        return [
            'title' => self::title(),
            'readOnlyHint' => false,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => false,
        ];
    }

    /**
     * Execute the tool.
     *
     * @param array<string, mixed> $arguments Tool arguments.
     * @param stdClass $user Authenticated Moodle user.
     * @return array<string, mixed>
     */
    public static function execute(array $arguments, stdClass $user): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $courseid = (int) ($arguments['course_id'] ?? 0);
        if ($courseid <= 0 || $courseid === (int) SITEID) {
            throw new tool_exception('course_id of a course is required.');
        }
        $mode = (string) ($arguments['mode'] ?? 'replace');
        if (!in_array($mode, ['replace', 'append', 'remove'], true)) {
            throw new tool_exception('Invalid mode. Allowed: replace, append, remove.');
        }
        $position = ($arguments['position'] ?? 'top') === 'bottom' ? 'bottom' : 'top';
        $style = learning_objectives::resolve_style((string) ($arguments['style'] ?? 'auto'));

        $course = get_course($courseid);
        $target = self::target($course, $arguments, $user);

        $before = learning_objectives::extract($target->html) ?? [];
        $new = learning_objectives::normalise((array) ($arguments['objectives'] ?? []));
        if ($mode !== 'remove' && !$new) {
            throw new tool_exception('objectives must contain at least one objective.');
        }
        if ($mode === 'remove' && !$before) {
            throw new tool_exception('There are no learning objectives to remove here.');
        }
        $after = match ($mode) {
            'append' => learning_objectives::normalise(array_merge($before, $new)),
            'remove' => [],
            default => $new,
        };

        $heading = trim((string) ($arguments['heading'] ?? ''));
        if ($heading === '') {
            $heading = learning_objectives::extract_heading($target->html)
                ?? get_string('learningobjectives_heading', 'webservice_elediamcp');
        }
        $block = $after ? learning_objectives::render($after, $heading, $style, !empty($arguments['numbered'])) : '';
        $html = learning_objectives::apply($target->html, $block, $position);

        $cardkey = $mode === 'remove' ? 'cardsummary_learningobjectives_remove' : 'cardsummary_learningobjectives_set';
        $preview = [
            'mode' => $mode,
            'before' => $before,
            'after' => $after,
        ];
        if (!empty($target->converted)) {
            // Plain text and Markdown descriptions are saved as HTML once the box is in.
            $preview['format_converted'] = true;
        }
        $targetout = ['type' => $target->type, 'name' => $target->name, 'url' => $target->url];

        if (empty($arguments['confirm'])) {
            return [
                'done' => false,
                'requires_confirmation' => true,
                'target' => $targetout,
                'style' => $style,
                'objectives' => $after,
                'preview' => $preview + card_summary::only($cardkey, (object) [
                    'target' => $target->name,
                    'count' => count($after),
                ]),
                'summary' => 'Ready to ' . ($mode === 'remove' ? 'remove the learning objectives from ' : 'write '
                    . count($after) . ' learning objective(s) to ') . $target->type . ' "' . $target->name
                    . '". ' . (!empty($target->converted)
                        ? 'The description is not HTML yet and will be saved as HTML. ' : '')
                    . 'Call again with confirm=true to write.',
            ];
        }

        try {
            self::write($course, $target, $html);
        } catch (Throwable $ex) {
            throw new tool_exception('Writing the learning objectives failed: ' . $ex->getMessage(), $preview);
        }

        return [
            'done' => true,
            'requires_confirmation' => false,
            'target' => $targetout,
            'style' => $style,
            'objectives' => $after,
            'preview' => $preview,
            'summary' => 'Done: ' . count($after) . ' learning objective(s) in ' . $target->type . ' "' . $target->name
                . '". Link: ' . $target->url . ' — share this link with the user.',
        ];
    }

    /**
     * Resolve what is being changed and check the right capability.
     *
     * @param stdClass $course
     * @param array $arguments
     * @param stdClass $user
     * @return stdClass type, name, url, html and what is needed to write it back
     */
    private static function target(stdClass $course, array $arguments, stdClass $user): stdClass {
        $courseid = (int) $course->id;
        $coursecontext = context_course::instance($courseid);
        $modinfo = get_fast_modinfo($course);

        if (!empty($arguments['cmid'])) {
            try {
                $cm = $modinfo->get_cm((int) $arguments['cmid']);
            } catch (Throwable $ex) {
                throw new tool_exception('cmid ' . (int) $arguments['cmid'] . ' is not an activity of this course.');
            }
            $context = context_module::instance($cm->id);
            require_capability('moodle/course:manageactivities', $context, $user->id);
            if (!plugin_supports('mod', $cm->modname, FEATURE_MOD_INTRO, true)) {
                throw new tool_exception('Activities of type ' . $cm->modname . ' have no description.');
            }
            $record = self::instance_record($cm);
            return (object) [
                'type' => self::TARGET_ACTIVITY,
                'name' => format_string($cm->name, true, ['context' => $context]),
                'url' => $cm->url ? $cm->url->out(false) : (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
                'html' => learning_objectives::to_html((string) $record->intro, (int) $record->introformat, $context),
                'converted' => self::needs_conversion((string) $record->intro, (int) $record->introformat),
                'cm' => $cm,
            ];
        }

        require_capability('moodle/course:update', $coursecontext, $user->id);

        if (array_key_exists('section', $arguments) && $arguments['section'] !== null) {
            $sectionnum = (int) $arguments['section'];
            $section = $modinfo->get_section_info($sectionnum);
            if ($section === null) {
                throw new tool_exception('Section ' . $sectionnum . ' does not exist in this course.');
            }
            return (object) [
                'type' => self::TARGET_SECTION,
                'name' => get_section_name($course, $section),
                'url' => (new moodle_url('/course/section.php', ['id' => $section->id]))->out(false),
                'html' => learning_objectives::to_html((string) $section->summary, (int) $section->summaryformat, $coursecontext),
                'converted' => self::needs_conversion((string) $section->summary, (int) $section->summaryformat),
                'section' => $section,
            ];
        }

        return (object) [
            'type' => self::TARGET_COURSE,
            'name' => format_string($course->fullname, true, ['context' => $coursecontext]),
            'url' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
            'html' => learning_objectives::to_html((string) $course->summary, (int) $course->summaryformat, $coursecontext),
            'converted' => self::needs_conversion((string) $course->summary, (int) $course->summaryformat),
        ];
    }

    /**
     * Whether a description is not HTML yet and will be saved as HTML.
     *
     * @param string $text
     * @param int $format FORMAT_ constant.
     * @return bool
     */
    private static function needs_conversion(string $text, int $format): bool {
        return trim($text) !== '' && $format !== (int) FORMAT_HTML;
    }

    /**
     * The activity's own record with intro and introformat.
     *
     * @param \cm_info $cm
     * @return stdClass
     */
    private static function instance_record(\cm_info $cm): stdClass {
        global $DB;
        return $DB->get_record($cm->modname, ['id' => $cm->instance], 'id, intro, introformat', MUST_EXIST);
    }

    /**
     * Write the new description back.
     *
     * Files embedded in the description stay in their file area: only the
     * text changes, so their links keep working.
     *
     * @param stdClass $course
     * @param stdClass $target
     * @param string $html
     * @return void
     */
    private static function write(stdClass $course, stdClass $target, string $html): void {
        global $DB;
        $courseid = (int) $course->id;

        switch ($target->type) {
            case self::TARGET_ACTIVITY:
                $cm = $target->cm;
                // Not update_moduleinfo(): it would replace the files embedded in the description.
                $DB->update_record($cm->modname, (object) [
                    'id' => $cm->instance,
                    'intro' => $html,
                    'introformat' => FORMAT_HTML,
                    'timemodified' => time(),
                ]);
                \course_modinfo::purge_course_module_cache($courseid, (int) $cm->id);
                rebuild_course_cache($courseid, true, true);
                \core\event\course_module_updated::create_from_cm($cm)->trigger();
                break;
            case self::TARGET_SECTION:
                course_update_section($course, $target->section, ['summary' => $html, 'summaryformat' => FORMAT_HTML]);
                break;
            default:
                update_course((object) ['id' => $courseid, 'summary' => $html, 'summaryformat' => FORMAT_HTML]);
        }
    }
}
