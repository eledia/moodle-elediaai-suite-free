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
 * Language strings for the scorm content extractor subplugin.
 *
 * @package    aisourcesextractor_scorm
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Nur diese eine Zeichenkette. Sie steht nicht auf einer Oberflaeche, sondern
// im indexierten Text, und genau dort sucht eine deutschsprachige Lernende
// nach "Lernpaket" (AI-53). Die uebrigen Strings des Subplugins bleiben
// bewusst unuebersetzt: Moodle faellt je Zeichenkette auf Englisch zurueck,
// und sie zu uebersetzen wuerde die Zwischenueberschriften in bereits
// indexierten Dokumenten aendern, ohne dass jemand danach gefragt haette.
$string['documentheading'] = '{$a} – Lernpaket (SCORM)';
