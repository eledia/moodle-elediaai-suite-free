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
 * What a backend answered when asked whether it is working.
 *
 * Deliberately not a state name. "ready", "todo", "error" are words a
 * particular dashboard uses to colour a row; whether a backend responded is a
 * fact about the backend. The surface that displays it maps the fact to its
 * own vocabulary.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class health {
    /**
     * Constructor.
     *
     * @param bool $healthy Whether the backend answered.
     * @param string $message What it said, or why it did not.
     * @param bool $configured Whether it is set up at all; false means nothing was tried.
     */
    public function __construct(
        /** @var bool Whether the backend answered. */
        public readonly bool $healthy,
        /** @var string What it said, or why it did not. */
        public readonly string $message = '',
        /** @var bool Whether it is set up at all. */
        public readonly bool $configured = true,
    ) {
    }

    /**
     * The answer for a backend that is not set up.
     *
     * @param string $message Optional explanation.
     * @return self
     */
    public static function unconfigured(string $message = ''): self {
        return new self(false, $message, false);
    }
}
