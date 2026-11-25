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
 * Helpers for the upgrade steps of block_elediaai_tutor.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Retire the setting "Question analytics retention (days)" (ai_suite#251).
 *
 * No code ever read block_elediaai_tutor/analyticsretentiondays; recorded
 * questions are deleted after local_elediaai_core/turn_retentiondays by the
 * core's task prune_turns. An administrator who changed the tutor value wanted
 * a shorter retention, so the tutor value is handed to the core only if it is
 * positive, differs from the old default 180 and is shorter than what the core
 * keeps now. A missing core value counts as the core default 90, a core value
 * of 0 (keep indefinitely) as longer than any positive value. A longer period
 * is never handed over. The tutor setting is removed in every case. Safe to run
 * more than once.
 *
 * @return bool True if the value was handed to the core.
 */
function block_elediaai_tutor_retire_analytics_retention(): bool {
    $value = get_config('block_elediaai_tutor', 'analyticsretentiondays');
    $core = get_config('local_elediaai_core', 'turn_retentiondays');

    $adopted = false;
    if ($value !== false && $value !== '') {
        $days = (int) $value;
        $coredays = ($core === false || $core === '') ? 90 : max(0, (int) $core);
        $shorter = $coredays === 0 || $days < $coredays;
        if ($days > 0 && $days !== 180 && $shorter) {
            set_config('turn_retentiondays', $days, 'local_elediaai_core');
            $adopted = true;
        }
    }

    unset_config('analyticsretentiondays', 'block_elediaai_tutor');
    return $adopted;
}
