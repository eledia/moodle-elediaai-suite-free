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
use stdClass;
use webservice_elediamcp\local\ai\ai_tool;
use webservice_elediamcp\local\ai\tool_exception;

/**
 * Teacher tool: find competencies a course can be linked to.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_find_competencies implements ai_tool {
    /** @var int Default number of results. */
    private const DEFAULT_LIMIT = 20;

    /** @var int Most results in one call. */
    private const MAX_LIMIT = 50;

    /**
     * Return the MCP tool name.
     *
     * @return string
     */
    public static function name(): string {
        return 'moodle_find_competencies';
    }

    /**
     * Return the human-readable tool title.
     *
     * @return string
     */
    public static function title(): string {
        return 'Find competencies';
    }

    /**
     * Return the tool description shown to MCP clients.
     *
     * @return string
     */
    public static function description(): string {
        return 'Searches the competency frameworks a course may use and lists matching competencies '
            . 'with their id, and whether they are already linked to the course (and to cmid, if given). '
            . 'Use it before moodle_link_competencies. Empty query lists the first competencies of each framework.';
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
                'query' => ['type' => 'string', 'maxLength' => 200, 'description' => 'Words in name, id number or description.'],
                'framework_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Search only this framework.'],
                'cmid' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Also report links to this activity.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_LIMIT, 'default' => self::DEFAULT_LIMIT],
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
            'required' => ['frameworks', 'competencies', 'summary'],
            'properties' => [
                'frameworks' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'id' => ['type' => 'integer'],
                    'shortname' => ['type' => 'string'],
                ]]],
                'competencies' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'id' => ['type' => 'integer'],
                    'shortname' => ['type' => 'string'],
                    'idnumber' => ['type' => 'string'],
                    'framework_id' => ['type' => 'integer'],
                    'description' => ['type' => 'string'],
                    'in_course' => ['type' => 'boolean'],
                    'in_activity' => ['type' => 'boolean'],
                ]]],
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
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => false,
        ];
    }

    /**
     * Execute the search.
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
        $context = context_course::instance($courseid);
        require_capability('moodle/competency:coursecompetencyview', $context, $user->id);

        $query = trim((string) ($arguments['query'] ?? ''));
        $limit = min(self::MAX_LIMIT, max(1, (int) ($arguments['limit'] ?? self::DEFAULT_LIMIT)));
        $only = (int) ($arguments['framework_id'] ?? 0);

        $frameworks = [];
        foreach (api::list_frameworks('shortname', 'ASC', 0, 0, $context, 'parents', true) as $framework) {
            if ($only && (int) $framework->get('id') !== $only) {
                continue;
            }
            $frameworks[(int) $framework->get('id')] = $framework;
        }
        if ($only && !$frameworks) {
            throw new tool_exception('Framework ' . $only . ' is not available for this course.');
        }

        $incourse = [];
        foreach (api::list_course_competencies($courseid) as $entry) {
            $incourse[(int) $entry['competency']->get('id')] = true;
        }
        $inactivity = [];
        $cmid = (int) ($arguments['cmid'] ?? 0);
        if ($cmid > 0) {
            foreach (api::list_course_module_competencies($cmid) as $entry) {
                $inactivity[(int) $entry['competency']->get('id')] = true;
            }
        }

        $results = [];
        foreach ($frameworks as $frameworkid => $framework) {
            $found = $query !== ''
                ? api::search_competencies($query, $frameworkid)
                : api::list_competencies(['competencyframeworkid' => $frameworkid], 'sortorder', 'ASC', 0, $limit);
            foreach ($found as $competency) {
                $id = (int) $competency->get('id');
                $results[] = [
                    'id' => $id,
                    'shortname' => format_string($competency->get('shortname'), true, ['context' => $context]),
                    'idnumber' => (string) $competency->get('idnumber'),
                    'framework_id' => $frameworkid,
                    'description' => shorten_text(html_to_text((string) $competency->get('description'), 0, false), 200),
                    'in_course' => isset($incourse[$id]),
                    'in_activity' => isset($inactivity[$id]),
                ];
                if (count($results) >= $limit) {
                    break 2;
                }
            }
        }

        return [
            'frameworks' => array_values(array_map(static fn($framework) => [
                'id' => (int) $framework->get('id'),
                'shortname' => format_string($framework->get('shortname'), true, ['context' => $context]),
            ], $frameworks)),
            'competencies' => $results,
            'summary' => count($results) . ' competenc' . (count($results) === 1 ? 'y' : 'ies') . ' found in '
                . count($frameworks) . ' framework(s).',
        ];
    }
}
