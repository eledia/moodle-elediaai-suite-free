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
 * @package    block_elediaai_tutor
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace block_elediaai_tutor\elediaai_core;

use local_elediaai_core\health\check;
use local_elediaai_core\health\health_provider as health_provider_contract;
use moodle_url;

/**
 * The tutor answers for its own switches.
 *
 * Not for LiteRAG, not for the ingestion destination, not for MCP -- those
 * report themselves now. This page used to ask all of them on everybody's
 * behalf, which meant reaching into three other plugins' innards.
 */
final class health_provider implements health_provider_contract {
    /** @var string This component. */
    private const COMPONENT = 'block_elediaai_tutor';

    #[\Override]
    public static function get_checks(): array {
        if (!class_exists(check::class)) {
            return [];
        }

        $global = !empty(get_config('block_elediaai_tutor', 'enableglobalchat'));
        $kurs = !empty(get_config('block_elediaai_tutor', 'enablecoursechat'));
        $datenschutz = trim((string) get_config('block_elediaai_tutor', 'privacyguidelinestext'));

        $pruefungen = [
            new check(
                id: 'surfaces',
                component: self::COMPONENT,
                label: get_string('health_surfaces', self::COMPONENT),
                status: ($global || $kurs) ? check::STATUS_OK : check::STATUS_DISABLED,
                detail: get_string(
                    ($global || $kurs) ? 'health_surfaces_on' : 'health_surfaces_off',
                    self::COMPONENT
                ),
                actionurl: new moodle_url('/blocks/elediaai_tutor/operator_settings.php'),
                actionlabel: get_string('health_configure', self::COMPONENT),
            ),
        ];

        // Der Hinweistext steht vor der ersten Nachricht. Fehlt er, laeuft der
        // Chat trotzdem -- nur sagt die Website dann nicht, wohin der Text geht.
        if ($datenschutz === '') {
            $pruefungen[] = new check(
                id: 'privacytext',
                component: self::COMPONENT,
                label: get_string('health_privacytext', self::COMPONENT),
                status: check::STATUS_WARNING,
                detail: get_string('health_privacytext_missing', self::COMPONENT),
                actionurl: new moodle_url('/blocks/elediaai_tutor/operator_settings.php'),
                actionlabel: get_string('health_configure', self::COMPONENT),
            );
        }

        // Die letzten Stoerungen. Sie standen auf der Konfigurationsseite und
        // waeren mit ihr verschwunden -- dabei sind sie das Einzige auf dieser
        // Uebersicht, das sagt, was zuletzt schiefging statt nur was gerade
        // eingerichtet ist.
        $letzte = \block_elediaai_tutor\local\diagnostics::latest(1);
        if (!empty($letzte)) {
            $eintrag = reset($letzte);
            $pruefungen[] = new check(
                id: 'lastfailure',
                component: self::COMPONENT,
                label: get_string('health_lastfailure', self::COMPONENT),
                status: check::STATUS_WARNING,
                detail: get_string('health_lastfailure_detail', self::COMPONENT, (object) [
                    'phase' => (string) ($eintrag->phase ?? ''),
                    'when' => userdate((int) ($eintrag->timecreated ?? 0)),
                    'detail' => (string) ($eintrag->detail ?? ''),
                ]),
            );
        }

        return $pruefungen;
    }
}
