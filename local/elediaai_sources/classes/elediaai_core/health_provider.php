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
 * @package    local_elediaai_sources
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_elediaai_sources\elediaai_core;

use local_elediaai_core\health\check;
use local_elediaai_core\health\health_provider as health_provider_contract;
use moodle_url;

/**
 * AI Sources answers for its own ingestion destination.
 *
 * Which destination is active is a setting, and each one answers for its
 * own reachability. Asking the transport instead would only ever test the
 * backend somebody wrote the code for.
 */
final class health_provider implements health_provider_contract {
    /** @var string This component. */
    private const COMPONENT = 'local_elediaai_sources';

    #[\Override]
    public static function get_checks(): array {
        if (!class_exists(check::class)) {
            return [];
        }

        $ziel = \local_elediaai_sources\sink\sink_manager::active();
        $offen = \local_elediaai_sources\course_state::pending_ingestion_count();

        if (!$ziel->is_configured()) {
            // Gewaehlt ist immer ein Ziel -- sink_manager faellt auf einen
            // Standard zurueck. "Kein Ziel eingerichtet" war deshalb die
            // falsche Auskunft, wenn nur ein Schluessel fehlte (M-13). Die
            // Senken melden ihre fehlende Voraussetzung in healthcheck(),
            // bevor sie das Netz beruehren; genau diesen Grund nennen wir.
            [$url, $label] = self::settings_link_for_gap();
            return [
                new check(
                    id: 'destination',
                    component: self::COMPONENT,
                    label: get_string('health_destination', self::COMPONENT),
                    status: check::STATUS_UNCONFIGURED,
                    detail: get_string('health_destination_incomplete', self::COMPONENT, (object) [
                        'name' => $ziel::name(),
                        'reason' => self::missing_precondition($ziel),
                    ]),
                    actionurl: $url,
                    actionlabel: $label,
                ),
            ];
        }

        try {
            $ergebnis = $ziel->healthcheck();
            $erreichbar = !empty($ergebnis['success']);
            $grund = $erreichbar
                ? ''
                : ((string) ($ergebnis['error'] ?? '') ?: 'HTTP ' . (int) ($ergebnis['http_code'] ?? 0));
        } catch (\Throwable $e) {
            $erreichbar = false;
            $grund = $e->getMessage();
        }

        $zielname = \local_elediaai_sources\sink\sink_manager::active_id();

        $pruefungen = [
            new check(
                id: 'destination',
                component: self::COMPONENT,
                label: get_string('health_destination', self::COMPONENT),
                status: $erreichbar ? check::STATUS_OK : check::STATUS_ERROR,
                detail: $erreichbar
                    ? get_string('health_destination_ok', self::COMPONENT, $zielname)
                    : $grund,
                actionurl: new moodle_url('/admin/settings.php', ['section' => 'local_elediaai_sources_settings']),
                actionlabel: get_string('health_configure', self::COMPONENT),
            ),
        ];

        // Welche Dateiformate gerade hinausgehen, haengt vom Ziel ab (AI-75):
        // Die Support-Matrix nennt die moeglichen, das aktive Ziel sagt, welche
        // davon es lesen kann. Wer sich wundert, warum ein DOCX nicht im Index
        // steht, liest die Antwort hier statt in drei Klassen nachzusehen.
        $formate = \local_elediaai_sources\format_matrix::offered($ziel);
        $pruefungen[] = new check(
            id: 'formats',
            component: self::COMPONENT,
            label: get_string('health_formats', self::COMPONENT),
            status: check::STATUS_OK,
            detail: get_string('health_formats_detail', self::COMPONENT, implode(', ', $formate)),
        );

        // Ausstehende Kurse sind kein Fehler, aber der Grund, warum ein Tutor
        // gerade nicht aus dem Kurs antwortet -- und das sucht sonst niemand hier.
        if ($offen > 0) {
            $pruefungen[] = new check(
                id: 'pending',
                component: self::COMPONENT,
                label: get_string('health_pending', self::COMPONENT),
                status: check::STATUS_WARNING,
                detail: get_string('health_pending_detail', self::COMPONENT, $offen),
                // Der Knopf sass auf der Konfigurationsseite des Tutors und
                // zeigte schon damals hierher -- die Aktion gehoert diesem
                // Plugin, der Weg dorthin jetzt auch.
                actionurl: new moodle_url('/local/elediaai_sources/reindex.php', [
                    'queuepending' => 1,
                    'sesskey' => sesskey(),
                ]),
                actionlabel: get_string('health_pending_action', self::COMPONENT),
            );
        }

        return $pruefungen;
    }

    /**
     * What the selected but unconfigured destination is missing, in its own words.
     *
     * Both sinks answer an unconfigured healthcheck() with the missing
     * precondition and without a request. Should one ever go out anyway, the
     * generic sentence is the fallback rather than an HTTP code.
     *
     * @param \local_elediaai_sources\sink\sink $ziel The active destination.
     * @return string
     */
    private static function missing_precondition(\local_elediaai_sources\sink\sink $ziel): string {
        try {
            $grund = trim((string) ($ziel->healthcheck()['error'] ?? ''));
        } catch (\Throwable $e) {
            $grund = '';
        }
        return $grund !== '' ? $grund : get_string('health_destination_incomplete_generic', self::COMPONENT);
    }

    /**
     * Where the missing setting lives.
     *
     * The LiteRAG destination has nothing to set up here: its key is
     * LiteRAG's own ingestion key. Pointing at this plugin's settings would
     * send the administrator to a page without the field in question.
     *
     * @return array{0: moodle_url, 1: string} URL and button label.
     */
    private static function settings_link_for_gap(): array {
        if (
            \local_elediaai_sources\sink\sink_manager::active_id() === \local_elediaai_sources\sink\literag_sink::id()
            && class_exists('\\local_literag\\local\\config')
        ) {
            return [
                new moodle_url('/admin/settings.php', ['section' => 'local_literag']),
                get_string('health_configure_literag', self::COMPONENT),
            ];
        }
        return [
            new moodle_url('/admin/settings.php', ['section' => 'local_elediaai_sources_settings']),
            get_string('health_configure', self::COMPONENT),
        ];
    }
}
