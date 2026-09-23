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
 * What this plugin reports about its own state.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_elediaai_chatengine\elediaai_core;

use local_elediaai_core\health\check;
use local_elediaai_core\health\health_provider as health_provider_contract;
use moodle_url;

/**
 * The chat engine answers for the backend it is pointed at.
 *
 * It resolves the active adapter, so it is the only place that can ask
 * the right backend rather than a particular one.
 */
final class health_provider implements health_provider_contract {
    /** @var string This component. */
    private const COMPONENT = 'local_elediaai_chatengine';

    #[\Override]
    public static function get_checks(): array {
        if (!class_exists(check::class)) {
            return [];
        }

        $adapter = \local_elediaai_chatengine\backend_resolver::active();

        if ($adapter === null) {
            return [
                new check(
                    id: 'backend',
                    component: self::COMPONENT,
                    label: get_string('health_backend', self::COMPONENT),
                    status: check::STATUS_UNCONFIGURED,
                    detail: get_string('health_backend_unconfigured', self::COMPONENT),
                ),
            ];
        }

        try {
            $zustand = $adapter->health();
            $status = !$zustand->configured
                ? check::STATUS_UNCONFIGURED
                : ($zustand->healthy ? check::STATUS_OK : check::STATUS_ERROR);
            $detail = $zustand->message;
        } catch (\Throwable $e) {
            $status = check::STATUS_ERROR;
            $detail = $e->getMessage();
        }

        return [
            new check(
                id: 'backend',
                component: self::COMPONENT,
                label: get_string('health_backend', self::COMPONENT),
                status: $status,
                detail: $detail,
            ),
        ];
    }
}
