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
 * MCP tool: what the suite stores about the person asking.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_elediaai_core\mcp;

use local_elediaai_core\local\actions;
use local_elediaai_core\local\insights;
use local_elediaai_core\local\quota_manager;
use stdClass;
use webservice_elediamcp\local\ai\ai_tool;

/**
 * „Was weiß das System über mich?" — answered to the person asking, and only them.
 *
 * The data-protection duty exists either way; this turns it into a function.
 * Moodle can already export it, but an export is a file somebody has to request
 * and then read. A learner who wonders mid-conversation gets an answer in the
 * conversation.
 *
 * **There is no user parameter, and that is the design.** Not an unchecked one
 * and not a checked one: the tool answers about the authenticated caller, full
 * stop, so there is nothing an agent could be talked into pointing at somebody
 * else.
 */
final class moodle_my_ai_data implements ai_tool {
    /**
     * Tool name.
     *
     * @return string
     */
    public static function name(): string {
        return 'moodle_my_ai_data';
    }

    /**
     * Display name.
     *
     * @return string
     */
    public static function title(): string {
        return 'What the AI suite stores about you';
    }

    /**
     * Description shown to MCP clients.
     *
     * @return string
     */
    public static function description(): string {
        return 'Answers "what does the system know about me", "what is stored about my AI use", '
            . '"can I get my data deleted" for the person asking. Returns their own AI conversations '
            . 'count, their own credit use today, the AI actions carried out on their behalf, and how '
            . 'long each log is kept, plus the link where they can download or delete it. Read-only, '
            . 'and it takes no user argument at all: it can only ever answer about the caller, so do '
            . 'not offer to look somebody else up with it.';
    }

    /**
     * Input schema: nothing to pass.
     *
     * @return array<string, mixed>
     */
    public static function input_schema(): array {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [],
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
            'required' => ['summary'],
            'properties' => [
                'summary' => ['type' => 'string'],
                'my_questions_recorded' => ['type' => 'integer'],
                'my_questions_are_anonymous' => ['type' => 'boolean'],
                'actions_on_my_behalf' => ['type' => 'integer'],
                'tokens_used_today' => ['type' => 'integer'],
                'retention' => [
                    'type' => 'object',
                    'properties' => [
                        'questions_days' => ['type' => 'integer'],
                        'actions_days' => ['type' => 'integer'],
                    ],
                ],
                'privacy_url' => ['type' => 'string'],
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
     * Execute for the authenticated user, about the authenticated user.
     *
     * @param array<string, mixed> $arguments Ignored; the schema accepts none.
     * @param stdClass $user Authenticated Moodle user.
     * @return array<string, mixed>
     */
    public static function execute(array $arguments, stdClass $user): array {
        unset($arguments);

        $userid = (int) $user->id;
        $turns = insights::turns_for_user($userid);
        $myactions = actions::for_user($userid, 500);
        $tokens = quota_manager::used_tokens(
            $userid,
            quota_manager::role_bucket($userid),
            quota_manager::WINDOW_DAY
        );

        $questiondays = insights::retention_days();
        $actiondays = actions::retention_days();

        return [
            'summary' => self::summary_line(count($turns), count($myactions), $tokens, $questiondays),
            'my_questions_recorded' => count($turns),
            // Ausdruecklich als Feld und nicht nur im Text: ein Agent soll die
            // Aussage weitergeben koennen, ohne sie zu formulieren.
            'my_questions_are_anonymous' => true,
            'actions_on_my_behalf' => count($myactions),
            'tokens_used_today' => $tokens,
            'retention' => [
                'questions_days' => $questiondays,
                'actions_days' => $actiondays,
            ],
            'privacy_url' => (new \moodle_url('/admin/tool/dataprivacy/mydatarequests.php'))->out(false),
        ];
    }

    /**
     * One sentence the agent can read out.
     *
     * @param int $questions
     * @param int $actioncount
     * @param int $tokens
     * @param int $questiondays
     * @return string
     */
    private static function summary_line(
        int $questions,
        int $actioncount,
        int $tokens,
        int $questiondays
    ): string {
        $parts = [];
        $parts[] = $questions === 0
            ? 'No questions of yours are currently recorded.'
            : $questions . ' of your questions are recorded, together with the answers.';
        $parts[] = 'They are stored WITHOUT your name: your teacher can see which topics are asked '
            . 'in the course, never who asked. That is fixed when the row is written, not hidden '
            . 'when it is shown.';
        if ($actioncount > 0) {
            $parts[] = $actioncount . ' actions were carried out by the AI on your behalf; those '
                . 'do keep your name, because an action has to be accountable.';
        }
        $parts[] = 'You have used ' . $tokens . ' AI tokens today.';
        if ($questiondays > 0) {
            $parts[] = 'Questions are deleted after ' . $questiondays . ' days.';
        }
        $parts[] = 'You can download or delete your data at any time via your data requests page.';

        return implode(' ', $parts);
    }
}
