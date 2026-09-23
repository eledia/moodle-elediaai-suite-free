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

/**
 * MCP tool: what occupied the learners in a course.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_elediaai_core\mcp;

use local_elediaai_core\local\insights;
use stdClass;
use webservice_elediamcp\local\ai\ai_tool;
use webservice_elediamcp\local\ai\tool_exception;

/**
 * „Was hat meine Lernenden beschäftigt?" — as a question the tutor can answer.
 *
 * Reads the anonymous turn log, which is exactly why this is safe to hand an
 * agent: it carries no person. The action log, which does, is deliberately not
 * reachable from any tool — a natural-language interface over a personal
 * oversight record would be a surveillance instrument that nobody would even
 * have to misuse.
 *
 * The one exception is {@see moodle_my_ai_data}, and it can only ever answer
 * about the caller.
 */
final class moodle_ai_course_insights implements ai_tool {
    /**
     * Tool name.
     *
     * @return string
     */
    public static function name(): string {
        return 'moodle_ai_course_insights';
    }

    /**
     * Display name.
     *
     * @return string
     */
    public static function title(): string {
        return 'What learners asked the AI in a course';
    }

    /**
     * Description shown to MCP clients.
     *
     * @return string
     */
    public static function description(): string {
        return 'For teachers: what learners asked the AI tutor in one course, grouped by topic and '
            . 'without any personal data. Answers "what were my learners working on", "what do they '
            . 'not understand", "where does my material fall short". Returns the topics that the '
            . 'course material could NOT answer first (these are the gaps worth acting on), then the '
            . 'topics where material exists but was asked about again anyway (there the content is '
            . 'present but unclear), plus counts of questions and of distinct askers. Read-only. '
            . 'Never returns who asked - the log holds no identities by design, so do not promise a '
            . 'teacher that you can find out.';
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
            'required' => ['courseid'],
            'properties' => [
                'courseid' => [
                    'type' => 'integer',
                    'description' => 'The course to report on. Use moodle_my_courses to find it.',
                ],
                'days' => [
                    'type' => 'integer',
                    'description' => 'Period in days, 1 to 365. Defaults to 30. '
                        . 'Say "this week" as 7, "this semester" as 180.',
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
        $topic = [
            'type' => 'object',
            'properties' => [
                'topic' => ['type' => 'string'],
                'questions' => ['type' => 'integer'],
                'askers' => ['type' => 'integer'],
                'uncovered_percent' => ['type' => 'integer'],
                'followup_percent' => ['type' => 'integer'],
                'material' => ['type' => 'string'],
            ],
        ];

        return [
            'type' => 'object',
            'required' => ['summary'],
            'properties' => [
                'summary' => ['type' => 'string'],
                'questions' => ['type' => 'integer'],
                'askers' => ['type' => 'integer'],
                'covered_percent' => ['type' => 'integer'],
                'followup_percent' => ['type' => 'integer'],
                'gaps' => ['type' => 'array', 'items' => $topic],
                'unclear' => ['type' => 'array', 'items' => $topic],
                'examples' => ['type' => 'array', 'items' => ['type' => 'string']],
                'insights_url' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * MCP annotations.
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
     * Execute for the authenticated user.
     *
     * The capability is checked here as well as when the catalogue is built: a
     * tool missing from the catalogue cannot be guessed by an agent, and one
     * that runs anyway would be the hole.
     *
     * @param array<string, mixed> $arguments
     * @param stdClass $user
     * @return array<string, mixed>
     * @throws tool_exception
     */
    public static function execute(array $arguments, stdClass $user): array {
        $courseid = (int) ($arguments['courseid'] ?? 0);
        $days = (int) ($arguments['days'] ?? insights::DEFAULT_DAYS);
        $days = max(1, min(365, $days));

        if ($courseid <= 0) {
            throw new tool_exception('Give a courseid. Use moodle_my_courses to find one.');
        }

        try {
            $context = \core\context\course::instance($courseid);
        } catch (\Throwable $e) {
            throw new tool_exception('There is no course with that id.', ['courseid' => $courseid]);
        }

        $allowed = has_capability('local/elediaai_core:viewcourseinsights', $context, $user)
            || has_capability('block/elediaai_tutor:viewreports', $context, $user);
        if (!$allowed) {
            throw new tool_exception(
                'You may not read the AI insights of that course. Do not retry.',
                ['courseid' => $courseid]
            );
        }

        $summary = insights::summary($courseid, $days);
        if ($summary->total === 0) {
            return [
                'summary' => 'Nobody asked the AI tutor anything in this course in the last '
                    . $days . ' days.',
                'questions' => 0,
                'askers' => 0,
                'gaps' => [],
                'unclear' => [],
                'examples' => [],
            ];
        }

        $gaps = array_map([self::class, 'gap_row'], insights::gaps($courseid, $days, 8));
        $unclear = array_map([self::class, 'unclear_row'], insights::unclear($courseid, $days, 6));
        $examples = [];
        foreach (insights::recent($courseid, $days, 8) as $row) {
            $examples[] = \core_text::substr(trim((string) $row->prompt), 0, 200);
        }

        return [
            'summary' => self::summary_line($summary, $gaps, $days),
            'questions' => $summary->total,
            'askers' => $summary->askers,
            'covered_percent' => $summary->groundedpct,
            'followup_percent' => $summary->followuppct,
            'gaps' => $gaps,
            'unclear' => $unclear,
            'examples' => $examples,
            'insights_url' => (new \moodle_url(
                '/local/elediaai_core/course_insights.php',
                ['courseid' => $courseid, 'days' => $days]
            ))->out(false),
        ];
    }

    /**
     * One sentence an agent can read out without doing arithmetic.
     *
     * @param stdClass $summary
     * @param array $gaps
     * @param int $days
     * @return string
     */
    private static function summary_line(stdClass $summary, array $gaps, int $days): string {
        $line = $summary->total . ' questions from ' . $summary->askers . ' people in the last '
            . $days . ' days. ' . $summary->groundedpct . ' % could be answered from the course '
            . 'material.';
        if ($gaps !== []) {
            $line .= ' The biggest gap is "' . $gaps[0]['topic'] . '" ('
                . $gaps[0]['uncovered_percent'] . ' % of its answers not covered by the material).';
        }
        return $line;
    }

    /**
     * A gap as a flat row.
     *
     * @param stdClass $gap
     * @return array<string, mixed>
     */
    private static function gap_row(stdClass $gap): array {
        return [
            'topic' => (string) $gap->label,
            'questions' => (int) $gap->total,
            'askers' => (int) $gap->askers,
            'uncovered_percent' => (int) $gap->ungroundedpct,
            'material' => $gap->sourcetitle !== null && $gap->sourcetitle !== ''
                ? (string) $gap->sourcetitle
                : 'none',
        ];
    }

    /**
     * An unclear topic as a flat row.
     *
     * @param stdClass $item
     * @return array<string, mixed>
     */
    private static function unclear_row(stdClass $item): array {
        return [
            'topic' => (string) $item->label,
            'questions' => (int) $item->total,
            'askers' => (int) $item->askers,
            'followup_percent' => (int) $item->followuppct,
            'material' => $item->sourcetitle !== null && $item->sourcetitle !== ''
                ? (string) $item->sourcetitle
                : 'none',
        ];
    }
}
