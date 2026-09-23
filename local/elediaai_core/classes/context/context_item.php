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
 * Context item DTO.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\context;

defined('MOODLE_INTERNAL') || die();

/**
 * Plain DTO for one piece of Moodle context that may be sent to AI actions.
 */
final class context_item {
    /** Public content, safe to reuse in normal prompts. */
    public const SENSITIVITY_PUBLIC = 'public';

    /** Course-internal content that should stay inside the Moodle course. */
    public const SENSITIVITY_INTERNAL = 'internal';

    /** Sensitive content that callers should handle with additional care. */
    public const SENSITIVITY_SENSITIVE = 'sensitive';

    /**
     * Create one context item.
     *
     * @param string $type Stable source type, e.g. `course`, `cm`, `section`, `file`.
     * @param int|null $courseid Owning course id when known.
     * @param int|null $contextid Moodle context id when known.
     * @param string $title Human-readable source title.
     * @param string $text Plain text payload for prompt grounding.
     * @param array $metadata Small structured source metadata.
     * @param string $sensitivity Sensitivity marker for downstream policy decisions.
     * @param bool $truncated Whether the original source text was shortened.
     */
    public function __construct(
        /** @var string Constructor-promoted value. */
        public readonly string $type,
        /** @var ?int Constructor-promoted value. */
        public readonly ?int $courseid,
        /** @var ?int Constructor-promoted value. */
        public readonly ?int $contextid,
        /** @var string Constructor-promoted value. */
        public readonly string $title,
        /** @var string Constructor-promoted value. */
        public readonly string $text,
        /** @var array Constructor-promoted value. */
        public readonly array $metadata = [],
        /** @var string Constructor-promoted value. */
        public readonly string $sensitivity = self::SENSITIVITY_INTERNAL,
        /** @var bool Constructor-promoted value. */
        public readonly bool $truncated = false,
    ) {
    }

    /**
     * Return a stable array representation for transport and hashing.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'type' => $this->type,
            'courseid' => $this->courseid,
            'contextid' => $this->contextid,
            'title' => $this->title,
            'text' => $this->text,
            'metadata' => $this->metadata,
            'sensitivity' => $this->sensitivity,
            'truncated' => $this->truncated,
        ];
    }
}
