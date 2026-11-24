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
use core_competency\api;
use core_competency\competency;
use moodle_url;
use stdClass;
use Throwable;
use webservice_elediamcp\local\ai\ai_tool;
use webservice_elediamcp\local\ai\tool_exception;

/**
 * Teacher tool: link competencies to a course and, optionally, to one of its activities.
 *
 * Linking only adds; it never removes a competency or changes ratings.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_link_competencies implements ai_tool {
    /** @var int Most competencies in one call. */
    private const MAX_ITEMS = 20;

    /**
     * Return the MCP tool name.
     *
     * @return string
     */
    public static function name(): string {
        return 'moodle_link_competencies';
    }

    /**
     * Return the human-readable tool title.
     *
     * @return string
     */
    public static function title(): string {
        return 'Link competencies';
    }

    /**
     * Return the tool description shown to MCP clients.
     *
     * @return string
     */
    public static function description(): string {
        return 'Links competencies (ids from moodle_find_competencies) to a course and, with cmid, also to '
            . 'that activity; a competency is added to the course first when it is not there yet. Only adds '
            . 'links, never removes. Returns a preview; call again with confirm=true to link.';
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
            'required' => ['course_id', 'competency_ids'],
            'properties' => [
                'course_id' => ['type' => 'integer', 'minimum' => 1],
                'cmid' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Also link to this activity.'],
                'competency_ids' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => self::MAX_ITEMS,
                    'items' => ['type' => 'integer', 'minimum' => 1],
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
        $item = ['type' => 'object', 'properties' => [
            'id' => ['type' => 'integer'],
            'shortname' => ['type' => 'string'],
            'course' => ['type' => 'string'],
            'activity' => ['type' => 'string'],
        ]];
        return [
            'type' => 'object',
            'required' => ['done', 'requires_confirmation', 'summary'],
            'properties' => [
                'done' => ['type' => 'boolean'],
                'requires_confirmation' => ['type' => 'boolean'],
                'competencies' => ['type' => 'array', 'items' => $item],
                'preview' => [
                    'type' => 'object',
                    'properties' => [
                        'course_fullname' => ['type' => 'string'],
                        'activity' => ['type' => 'string'],
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
        if (!api::is_enabled()) {
            throw new tool_exception('Competencies are switched off on this site.');
        }
        $courseid = (int) ($arguments['course_id'] ?? 0);
        if ($courseid <= 0 || $courseid === (int) SITEID) {
            throw new tool_exception('course_id of a course is required.');
        }
        $course = get_course($courseid);
        $context = context_course::instance($courseid);
        require_capability('moodle/competency:coursecompetencymanage', $context, $user->id);

        $cm = null;
        if (!empty($arguments['cmid'])) {
            try {
                $cm = get_fast_modinfo($course)->get_cm((int) $arguments['cmid']);
            } catch (Throwable $ex) {
                throw new tool_exception('cmid ' . (int) $arguments['cmid'] . ' is not an activity of this course.');
            }
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($arguments['competency_ids'] ?? [])))));
        if (!$ids) {
            throw new tool_exception('competency_ids must contain at least one id.');
        }
        if (count($ids) > self::MAX_ITEMS) {
            throw new tool_exception('At most ' . self::MAX_ITEMS . ' competencies per call.');
        }

        $incourse = [];
        foreach (api::list_course_competencies($courseid) as $entry) {
            $incourse[(int) $entry['competency']->get('id')] = true;
        }
        $inactivity = [];
        if ($cm) {
            foreach (api::list_course_module_competencies($cm->id) as $entry) {
                $inactivity[(int) $entry['competency']->get('id')] = true;
            }
        }

        $plan = [];
        foreach ($ids as $id) {
            $competency = competency::get_record(['id' => $id]);
            if (!$competency) {
                throw new tool_exception('Competency ' . $id . ' does not exist.');
            }
            $framework = $competency->get_framework();
            // The framework must be visible and readable for this teacher, not only visible.
            $readable = has_capability('moodle/competency:competencyview', $framework->get_context(), $user->id)
                || has_capability('moodle/competency:competencymanage', $framework->get_context(), $user->id);
            if (!$framework->get('visible') || !$readable) {
                throw new tool_exception(
                    'Competency ' . $id . ' belongs to a framework that is not available here and cannot be linked.'
                );
            }
            $plan[] = [
                'id' => $id,
                'shortname' => format_string($competency->get('shortname'), true, ['context' => $context]),
                'course' => isset($incourse[$id]) ? 'already' : 'add',
                'activity' => $cm ? (isset($inactivity[$id]) ? 'already' : 'add') : 'none',
            ];
        }
        $toadd = array_filter($plan, static fn($row) => $row['course'] === 'add' || $row['activity'] === 'add');
        if (!$toadd) {
            throw new tool_exception('All these competencies are already linked.');
        }

        $coursename = format_string($course->fullname, true, ['context' => $context]);
        $activityname = $cm ? format_string($cm->name, true, ['context' => $cm->context]) : '';
        $preview = ['course_fullname' => $coursename, 'activity' => $activityname];

        if (empty($arguments['confirm'])) {
            return [
                'done' => false,
                'requires_confirmation' => true,
                'competencies' => $plan,
                'preview' => $preview + card_summary::only(
                    $cm ? 'cardsummary_link_competencies_activity' : 'cardsummary_link_competencies',
                    (object) ['course' => $coursename, 'activity' => $activityname, 'count' => count($toadd)]
                ),
                'summary' => 'Ready to link ' . count($toadd) . ' competenc' . (count($toadd) === 1 ? 'y' : 'ies')
                    . '. Call again with confirm=true to link.',
            ];
        }

        try {
            foreach ($plan as $index => $row) {
                if ($row['course'] === 'add') {
                    api::add_competency_to_course($courseid, $row['id']);
                    $plan[$index]['course'] = 'added';
                }
                if ($cm && $row['activity'] === 'add') {
                    api::add_competency_to_course_module($cm, $row['id']);
                    $plan[$index]['activity'] = 'added';
                }
            }
        } catch (Throwable $ex) {
            throw new tool_exception('Linking failed: ' . $ex->getMessage(), $preview);
        }

        $url = (new moodle_url('/admin/tool/lp/coursecompetencies.php', ['courseid' => $courseid]))->out(false);
        return [
            'done' => true,
            'requires_confirmation' => false,
            'competencies' => $plan,
            'preview' => $preview,
            'summary' => 'Linked ' . count($toadd) . ' competenc' . (count($toadd) === 1 ? 'y' : 'ies')
                . '. Link: ' . $url . ' — share this link with the user.',
        ];
    }
}
