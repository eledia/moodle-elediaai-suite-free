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
 * Which base prompt a turn is answered inside.
 *
 * A placement says what *kind* of conversation it is; the backend owns the
 * prompt text and picks it from this name. Nothing here is prompt text, and
 * nothing here is a plugin name: two activities of the same kind share an id
 * rather than each adding one.
 *
 * The distinction this draws is the one the persona could not. Persona shapes
 * how an answer sounds, and every surface was therefore answered inside the
 * tutor's base prompt - a learning assistant with tutoring stance and tool
 * rules. A role-play scenario and a general-purpose assistant are not that,
 * and no amount of voice guidance turns one into the other.
 *
 * The vocabulary is a wire contract (`system_prompt_id` in the RAG server
 * specification, block_elediaai_tutor 0.25.0). It is closed: a new surface
 * either reuses a kind or the contract gains a value first.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class system_prompt {
    /** @var string A learning assistant with access to the learner's Moodle data. */
    public const TUTOR = 'tutor';

    /** @var string A general-purpose assistant, with no tutoring stance. */
    public const AICHAT = 'aichat';

    /** @var string Plays a role a teacher wrote, inside the material the activity supplies. */
    public const ELLI = 'elli';

    /**
     * Every id in the contract.
     *
     * @return string[] The vocabulary, in contract order.
     */
    public static function all(): array {
        return [self::TUTOR, self::AICHAT, self::ELLI];
    }

    /**
     * Whether a value is one of the contract's ids.
     *
     * @param string $id The candidate.
     * @return bool
     */
    public static function is_valid(string $id): bool {
        return in_array($id, self::all(), true);
    }

    /**
     * Normalise a value, defaulting to the tutor prompt.
     *
     * Tutor is the safe default because it is what every backend answered
     * before this argument existed: an unknown id degrades to today's
     * behaviour rather than to no answer. The specification requires a server
     * to do the same, so the rule holds on both sides of the wire.
     *
     * @param string|null $id The candidate.
     * @return string A valid id.
     */
    public static function normalise(?string $id): string {
        return $id !== null && self::is_valid($id) ? $id : self::TUTOR;
    }
}
