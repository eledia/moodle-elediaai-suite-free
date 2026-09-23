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
 * One turn of a conversation.
 *
 * Roles are the three the backends agree on. Anything a user or a tool
 * produced is untrusted regardless of the role it is stored under, which is
 * why the role lives here as data and is never taken from the message text
 * itself — see {@see prompt_safety}.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class message {
    /** @var string A turn written by the person chatting. */
    public const ROLE_USER = 'user';

    /** @var string A turn produced by the model. */
    public const ROLE_ASSISTANT = 'assistant';

    /** @var string Instructions from the placement, never from a person. */
    public const ROLE_SYSTEM = 'system';

    /**
     * Constructor.
     *
     * @param string $role One of the ROLE_* constants.
     * @param string $content Message text as Markdown.
     * @param array $sources Citations for an assistant turn, each with title/url.
     * @param int $timecreated When the turn was recorded, 0 when not persisted yet.
     * @param string $origin Where an assistant turn came from, '' when not recorded.
     */
    public function __construct(
        /** @var string One of the ROLE_* constants. */
        public readonly string $role,
        /** @var string Message text as Markdown. */
        public readonly string $content,
        /** @var array Citations for an assistant turn. */
        public readonly array $sources = [],
        /** @var int When the turn was recorded. */
        public readonly int $timecreated = 0,
        /**
         * @var string Where an assistant turn came from, one of the
         * chat_response ORIGIN_* constants. Empty for a user turn, and for a
         * turn recorded before the origin was stored — those must not be
         * guessed after the fact, so the interface shows no claim about them
         * rather than a wrong one.
         */
        public readonly string $origin = '',
    ) {
    }

    /**
     * Whether a role is one the engine knows.
     *
     * @param string $role The role to check.
     * @return bool True when the role is supported.
     */
    public static function is_valid_role(string $role): bool {
        return in_array($role, [self::ROLE_USER, self::ROLE_ASSISTANT, self::ROLE_SYSTEM], true);
    }

    /**
     * The plain array shape the adapters exchange.
     *
     * @return array{role: string, content: string}
     */
    public function to_array(): array {
        return ['role' => $this->role, 'content' => $this->content];
    }
}
