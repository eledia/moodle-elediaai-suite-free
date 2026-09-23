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

namespace local_elediaai_chatengine\adapter;

use local_elediaai_chatengine\local\course_scope;
use local_elediaai_chatengine\local\message;
use local_elediaai_chatengine\local\persona;

/**
 * Everything a backend needs for one turn.
 *
 * Assembled by the engine, never by a placement: a placement states what it
 * wants (persona, mode) and the engine decides what is actually sent, so a
 * client cannot widen its own permissions by posting a different mode.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chat_request {
    /**
     * Constructor.
     *
     * @param string $usermessage The validated, safety-framed user message.
     * @param message[] $history Prior turns, oldest first, already trimmed to the history limit.
     * @param persona $persona The voice to answer in.
     * @param string $systempromptid Which base prompt the backend answers inside; a
     *        {@see \local_elediaai_chatengine\local\system_prompt} constant.
     * @param string $mode A {@see \local_elediaai_chatengine\local\mode} constant.
     * @param int $userid The acting user id, 0 for an unauthenticated visitor.
     * @param int $contextid The context the turn happens in.
     * @param int $courseid The course, or 0 for a site-wide conversation.
     * @param course_scope|null $coursescope Which courses this turn may be answered
     *        from. Null falls back to the single course above, which is what a
     *        caller that predates the scope means by it.
     * @param string|null $convkey Conversation id issued by a stateful backend, or null to start one.
     * @param bool $allowtools Whether the backend may call back into Moodle.
     * @param string|null $language The user's language code, or null to omit.
     * @param array $options Backend-agnostic hints a backend may honour or ignore (e.g. answerstyle, intent).
     * @param callable|null $ondelta Called per streamed fragment as (string $text, bool $reset).
     */
    public function __construct(
        /** @var string The validated, safety-framed user message. */
        public readonly string $usermessage,
        /** @var message[] Prior turns, oldest first. */
        public readonly array $history = [],
        /** @var persona The voice to answer in. */
        public readonly persona $persona = new persona(),
        /** @var string Which base prompt the backend answers inside. */
        public readonly string $systempromptid = \local_elediaai_chatengine\local\system_prompt::TUTOR,
        /** @var string The answer mode. */
        public readonly string $mode = \local_elediaai_chatengine\local\mode::GROUNDED,
        /** @var int The acting user id. */
        public readonly int $userid = 0,
        /** @var int The context the turn happens in. */
        public readonly int $contextid = 0,
        /** @var int The course, or 0. */
        public readonly int $courseid = 0,
        /** @var course_scope|null Which courses may be searched. */
        public readonly ?course_scope $coursescope = null,
        /** @var string|null Conversation id issued by a stateful backend. */
        public readonly ?string $convkey = null,
        /** @var bool Whether the backend may call back into Moodle. */
        public readonly bool $allowtools = true,
        /** @var string|null The user's language code. */
        public readonly ?string $language = null,
        /** @var array Backend-agnostic hints. */
        public readonly array $options = [],
        /** @var callable|null Streaming callback. */
        public $ondelta = null,
    ) {
    }

    /**
     * Whether retrieval is expected for this turn.
     *
     * @return bool
     */
    public function is_grounded(): bool {
        return $this->mode === \local_elediaai_chatengine\local\mode::GROUNDED;
    }

    /**
     * The `course_id` argument for the backend, or null to omit it.
     *
     * One place, so the two adapters cannot drift apart on it. Without a scope
     * the answer is the single course, exactly as before the scope existed.
     *
     * @return string|null Comma-separated course ids, no spaces.
     */
    public function course_argument(): ?string {
        if ($this->coursescope !== null) {
            return $this->coursescope->as_argument();
        }
        return $this->courseid > 0 ? (string) $this->courseid : null;
    }

    /**
     * One optional hint, or a fallback.
     *
     * @param string $name The hint name.
     * @param mixed $default Returned when the hint is absent or empty.
     * @return mixed
     */
    public function option(string $name, mixed $default = null): mixed {
        $value = $this->options[$name] ?? null;
        return ($value === null || $value === '') ? $default : $value;
    }
}
