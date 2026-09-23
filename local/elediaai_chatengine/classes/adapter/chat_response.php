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

/**
 * What a backend returned for one turn.
 *
 * Token counts are the backend's own measurement where it reports one. They
 * are passed to the quota manager rather than estimated locally, because a
 * local estimate cannot see the backend's system prompt, its tool loop or its
 * reranking and would book a number that is wrong in one direction only.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chat_response {
    /** @var string The answer came from retrieved course material. */
    public const ORIGIN_GROUNDED = 'grounded';

    /** @var string The answer came from the model's own knowledge. */
    public const ORIGIN_GENERAL = 'general';

    /**
     * @var string The answer was built from Moodle's own data through the
     * connector's tools. Distinct from grounded, which means retrieval over the
     * indexed course material: a learner should be able to tell "I looked this
     * up in your course" from "I read it out of Moodle for you".
     */
    public const ORIGIN_MCP = 'mcp';

    /**
     * Constructor.
     *
     * @param string $answer The answer as Markdown.
     * @param string|null $convkey Conversation id to resume with, or null for a stateless backend.
     * @param array $sources Ordered citations; sources[0] is the primary one.
     * @param string $origin One of the ORIGIN_* constants.
     * @param string|null $topic Canonical topic label, when the backend supplies one.
     * @param array|null $confirmation Pending write action the user must confirm, or null.
     * @param bool $iserror Whether the backend answered but reported a tool-level error.
     * @param int|null $prompttokens Measured prompt tokens, or null when not reported.
     * @param int|null $completiontokens Measured completion tokens, or null when not reported.
     */
    public function __construct(
        /** @var string The answer as Markdown. */
        public readonly string $answer,
        /** @var string|null Conversation id to resume with. */
        public readonly ?string $convkey = null,
        /** @var array Ordered citations. */
        public readonly array $sources = [],
        /** @var string Where the answer came from. */
        public readonly string $origin = self::ORIGIN_GENERAL,
        /** @var string|null Canonical topic label. */
        public readonly ?string $topic = null,
        /** @var array|null Pending write action. */
        public readonly ?array $confirmation = null,
        /** @var bool Whether the backend reported a tool-level error. */
        public readonly bool $iserror = false,
        /** @var int|null Measured prompt tokens. */
        public readonly ?int $prompttokens = null,
        /** @var int|null Measured completion tokens. */
        public readonly ?int $completiontokens = null,
    ) {
    }

    /**
     * The same response with its citations dropped.
     *
     * Used when the turn ran ungrounded: a backend that returns sources anyway
     * must not make the interface show a grounded badge, and the question
     * analytics must not record the turn as grounded either.
     *
     * @return self
     */
    public function without_sources(): self {
        return new self(
            $this->answer,
            $this->convkey,
            [],
            self::ORIGIN_GENERAL,
            $this->topic,
            $this->confirmation,
            $this->iserror,
            $this->prompttokens,
            $this->completiontokens,
        );
    }
}
