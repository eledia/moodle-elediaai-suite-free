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
 * The voice a placement asks the engine to answer in.
 *
 * The tutor block configures these five fields as separate settings, mod_elli
 * and mod_aichat configure a single free-text system prompt. Both arrive here:
 * the structured fields travel to backends that accept a structured persona,
 * and {@see as_system_prompt()} folds them into one instruction for backends
 * that only take messages.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class persona {
    /**
     * Constructor.
     *
     * @param string $name Display name of the assistant.
     * @param string $role What it is (e.g. "Lerncoach").
     * @param string $tone How it speaks.
     * @param string $audience Who it speaks to.
     * @param string $instructions Free-text instructions; a placement's whole system prompt lands here.
     */
    public function __construct(
        /** @var string Display name of the assistant. */
        public readonly string $name = '',
        /** @var string What the assistant is. */
        public readonly string $role = '',
        /** @var string How the assistant speaks. */
        public readonly string $tone = '',
        /** @var string Who the assistant speaks to. */
        public readonly string $audience = '',
        /** @var string Free-text instructions. */
        public readonly string $instructions = '',
    ) {
    }

    /**
     * Build a persona from a single free-text system prompt.
     *
     * @param string $prompt The placement's system prompt.
     * @return self
     */
    public static function from_prompt(string $prompt): self {
        return new self(instructions: trim($prompt));
    }

    /**
     * Whether anything at all was configured.
     *
     * @return bool True when every field is empty.
     */
    public function is_empty(): bool {
        return $this->as_array() === [];
    }

    /**
     * The populated fields only, as the structured persona argument.
     *
     * Empty fields are omitted rather than sent blank: a backend with a strict
     * schema must not receive five empty strings and treat them as an
     * instruction to have no name, no role and no tone.
     *
     * @return array<string, string>
     */
    public function as_array(): array {
        $fields = [
            'name' => $this->name,
            'role' => $this->role,
            'tone' => $this->tone,
            'audience' => $this->audience,
            'instructions' => $this->instructions,
        ];
        return array_filter($fields, static fn(string $value): bool => trim($value) !== '');
    }

    /**
     * The persona folded into one system instruction.
     *
     * For backends that take a message list and nothing else. The labels are
     * deliberately English and fixed: they are read by the model, not by a
     * person, so translating them would make the prompt vary by user language.
     *
     * @return string The system prompt, or '' when nothing was configured.
     */
    public function as_system_prompt(): string {
        $labels = [
            'name' => 'Name',
            'role' => 'Role',
            'tone' => 'Tone',
            'audience' => 'Audience',
        ];
        $lines = [];
        foreach ($labels as $field => $label) {
            $value = trim($this->{$field});
            if ($value !== '') {
                $lines[] = $label . ': ' . $value;
            }
        }
        if (trim($this->instructions) !== '') {
            $lines[] = trim($this->instructions);
        }
        return implode("\n", $lines);
    }

    /**
     * A stable fingerprint of the persona.
     *
     * A thread records this so that a changed persona starts a fresh
     * conversation instead of continuing one whose earlier turns followed
     * different instructions.
     *
     * @return string sha1 hash, or '' for an empty persona.
     */
    public function hash(): string {
        $array = $this->as_array();
        return $array === [] ? '' : sha1(json_encode($array));
    }
}
