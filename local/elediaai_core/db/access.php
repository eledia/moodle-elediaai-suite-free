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
 * Capabilities for local_elediaai_core.
 *
 * Course and system permissions owned by the AI Suite.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // Wer das Handlungsprotokoll der KI lesen darf -- was die KI getan hat und
    // in wessen Auftrag. Eigene Capability und nicht Moodles
    // moodle/ai:viewaiusagereport: das Register des Kerns beantwortet eine
    // andere Frage und existiert auf 4.5 gar nicht, das Protokoll aber schon.
    // Auf Kursebene vergebbar, damit eine Lehrkraft sehen kann, was die KI in
    // ihrem Kurs getan hat, ohne die ganze Website zu sehen.
    'local/elediaai_core:viewaiactions' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/site:config',
    ],

    // Die Kurs-Einblicke: Themen, Luecken und Fragen im Wortlaut, ohne
    // Personen. Getrennt von der Aufsicht oben, weil es eine didaktische
    // Auskunft ist und keine ueber Menschen -- eine Lehrkraft soll sie haben,
    // ohne damit Zugriff auf ein personenbezogenes Protokoll zu bekommen.
    'local/elediaai_core:viewcourseinsights' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
        ],
    ],
];
