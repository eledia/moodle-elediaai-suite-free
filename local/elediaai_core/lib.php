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
 * LernHive AI library functions.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Die MCP-Werkzeuge, die dieses Plugin beisteuert.
 *
 * Zwei, und ihre Trennung ist die Regel und nicht der Zufall:
 *
 * - `moodle_ai_course_insights` liest den ANONYMEN Turn-Speicher. Deshalb darf
 *   ein Agent ihn haben: die Tabelle traegt keine Person.
 * - `moodle_my_ai_data` beantwortet die Selbstauskunft, und zwar nur fuer die
 *   fragende Person. Es nimmt gar kein Nutzer-Argument -- auch kein
 *   geprueftes -- damit es nichts gibt, worauf ein Agent gelenkt werden koennte.
 *
 * Das Handlungsprotokoll (Schicht A) ist von KEINEM Werkzeug erreichbar. Es ist
 * personenbezogen, und ein natuerlichsprachiges Interface darueber waere ein
 * Ueberwachungswerkzeug, das man nicht einmal missbrauchen muesste -- es wuerde
 * einfach antworten. Es bleibt ein Bericht, den ein Mensch mit einer
 * Berechtigung oeffnet.
 *
 * @return string[] Klassennamen, die den MCP-Werkzeugvertrag erfuellen.
 */
function local_elediaai_core_elediamcp_tools(): array {
    return [
        \local_elediaai_core\mcp\moodle_ai_course_insights::class,
        \local_elediaai_core\mcp\moodle_my_ai_data::class,
    ];
}
