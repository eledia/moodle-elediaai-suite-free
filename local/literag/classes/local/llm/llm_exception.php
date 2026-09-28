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

namespace local_literag\local\llm;

/**
 * Raised when the OpenAI-compatible LLM call fails.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class llm_exception extends \Exception {
    /**
     * Constructor.
     *
     * @param string $message What went wrong.
     * @param bool $requestrejected The service answered and refused the request itself
     *        (HTTP 400/404/422, or an error object in the body) - the one case where
     *        sending it again without the tool list can help. A timeout, a refused
     *        connection, a wrong key or a server fault is not.
     */
    public function __construct(
        string $message,
        /** @var bool Whether the service refused the request as sent. */
        public readonly bool $requestrejected = false
    ) {
        parent::__construct($message);
    }
}
