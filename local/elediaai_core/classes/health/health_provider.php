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
 * The contract a plugin implements to report its own state.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_elediaai_core\health;

/**
 * What a suite plugin says about how it is doing.
 *
 * Implementations are found by convention at
 * `\<component>\elediaai_core\health_provider`, the same shape the suite
 * already uses for `feature_provider`, `guide_provider` and
 * `chatengine\placement`.
 *
 * Optional twice over: a plugin that ships none is simply not reported on,
 * and one that ships one still runs when the core plugin is absent -- the
 * registry that would load this file does not exist either.
 *
 * The point of the contract is that the core does not know how anything
 * works. Whether LiteRAG is reachable, whether an ingestion destination
 * answered, whether MCP has a service id -- each plugin knows that about
 * itself, and nobody else can know it without reaching into its innards.
 * Before this existed, one page in `block_elediaai_tutor` asked all those
 * questions on everybody's behalf.
 */
interface health_provider {
    /**
     * What this component currently reports about itself.
     *
     * **May be slow.** Unlike `feature_provider` and `guide_provider`, this
     * is allowed to talk to a backend: that is the point. It is called from
     * one page that an administrator opened on purpose, never from ordinary
     * page loads. Implementations should still bound their own timeouts, and
     * a failure to reach something is a `STATUS_ERROR` check, not an
     * exception.
     *
     * @return check[]
     */
    public static function get_checks(): array;
}
