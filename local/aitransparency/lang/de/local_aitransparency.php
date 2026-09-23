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
 * German strings for local_aitransparency.
 *
 * @package    local_aitransparency
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['aitransparency:viewreport'] = 'KI-Transparenz-Provenance-Bericht ansehen';
$string['pluginname'] = 'KI-Transparenz';
$string['privacy:metadata:local_aitransparency_file'] = 'Verknüpft einen Provenance-Datensatz mit einer gespeicherten Datei, damit die Kennzeichnung Kopieren, Verschieben und Backup übersteht. Enthält keine personenbezogene Kennung.';
$string['privacy:metadata:local_aitransparency_file:filecontenthash'] = 'Der Content-Hash der gekennzeichneten Datei.';
$string['privacy:metadata:local_aitransparency_file:timecreated'] = 'Zeitpunkt, zu dem die Dateiverknüpfung angelegt wurde.';
$string['privacy:metadata:local_aitransparency_rec'] = 'Ein Provenance-Datensatz je KI-Ausgabe, aufbewahrt als rechtlicher Compliance-Nachweis (Art. 50 EU AI Act). Die Nutzerverknüpfung wird zur Rechtskonformität aufbewahrt und nach Ablauf der Aufbewahrungsfrist anonymisiert.';
$string['privacy:metadata:local_aitransparency_rec:actionname'] = 'Art der KI-Aktion, z. B. generate_text oder generate_image.';
$string['privacy:metadata:local_aitransparency_rec:contenthash'] = 'Hash der erzeugten Ausgabe, bindet den Datensatz an den konkreten Inhalt.';
$string['privacy:metadata:local_aitransparency_rec:contextid'] = 'Der Moodle-Kontext, in dem die Ausgabe erzeugt wurde.';
$string['privacy:metadata:local_aitransparency_rec:model'] = 'Die in der Antwort gemeldete KI-Modellbezeichnung.';
$string['privacy:metadata:local_aitransparency_rec:provider'] = 'Der KI-Anbieter, der die Ausgabe erzeugt hat.';
$string['privacy:metadata:local_aitransparency_rec:timecreated'] = 'Zeitpunkt der Erzeugung der Ausgabe.';
$string['privacy:metadata:local_aitransparency_rec:userid'] = 'Die Nutzerin oder der Nutzer, die/der die Erzeugung ausgelöst hat.';
$string['setting_retentiondays'] = 'Aufbewahrung der Nutzerverknüpfung (Tage)';
$string['setting_retentiondays_desc'] = 'Anzahl der Tage, nach denen die auslösende Person auf einem Provenance-Datensatz anonymisiert wird. Der Datensatz selbst bleibt als Compliance-Nachweis erhalten. 0 behält die Nutzerverknüpfung unbegrenzt.';
$string['task_anonymise_records'] = 'Nutzerverknüpfung abgelaufener KI-Provenance-Datensätze anonymisieren';
$string['assettype_file'] = 'Datei';
$string['assettype_image'] = 'Bild';
$string['assettype_text'] = 'Text';
$string['markstate_embedded'] = 'Eingebettet';
$string['markstate_failed'] = 'Kennzeichnung fehlgeschlagen';
$string['markstate_marked'] = 'Gekennzeichnet';
$string['markstate_pending'] = 'Noch nicht gekennzeichnet';
$string['markstate_sidecar'] = 'Gekennzeichnet (Beidatei)';
$string['markstate_unsupported'] = 'Nicht kennzeichenbar';
$string['notice'] = 'Dieser Inhalt wurde von einer KI erzeugt.';
$string['notice_verify'] = 'Herkunft prüfen';
$string['notice_with_provider'] = 'Dieser Inhalt wurde von einer KI erzeugt ({$a}).';
$string['report_col_asset'] = 'Art';
$string['report_col_component'] = 'Erzeugt von';
$string['report_col_markstate'] = 'Kennzeichnung';
$string['report_col_provider'] = 'Dienst / Modell';
$string['report_col_time'] = 'Wann';
$string['report_col_verify'] = 'Nachweis';
$string['report_empty'] = 'Für diese Website wurde noch keine KI-Ausgabe verzeichnet.';
$string['report_intro'] = 'Jede KI-Ausgabe, für die diese Website einen Herkunftsnachweis angelegt hat, neueste zuerst. Ein Nachweis ohne Kennzeichnung bedeutet: die Ausgabe ging ohne ihre Art.-50-Markierung hinaus.';
$string['report_open'] = 'Öffnen';
$string['report_title'] = 'KI-Herkunftsnachweise';
$string['report_total'] = 'Nachweise';
$string['report_unmarked'] = 'ohne Kennzeichnung';
$string['verify_assettype'] = 'Art der Ausgabe';
$string['verify_component'] = 'Erzeugt von';
$string['verify_component_unknown'] = 'Unbekannte Komponente';
$string['verify_component_value'] = '{$a->component}';
$string['verify_contenthash'] = 'Prüfsumme des Inhalts';
$string['verify_found'] = 'Dieser Inhalt wurde von einer KI erzeugt';
$string['verify_found_intro'] = 'Er trägt eine Kennzeichnung nach Art. 50 der EU-KI-Verordnung. Die Angaben unten stammen aus dem Herkunftsnachweis, der beim Erzeugen angelegt wurde.';
$string['verify_markstate'] = 'Kennzeichnung';
$string['verify_noperson'] = 'Wer den Inhalt erzeugt hat, steht hier nicht. Der Nachweis beantwortet, ob eine KI beteiligt war — nicht, wer sie bedient hat.';
$string['verify_notfound'] = 'Auf dieser Website gibt es keinen Herkunftsnachweis mit dieser Kennung.';
$string['verify_nouuid'] = 'Es wurde keine Kennung übergeben, es gibt also nichts nachzusehen.';
$string['verify_provider'] = 'Anbieter und Modell';
$string['verify_time'] = 'Erzeugt am';
$string['verify_title'] = 'Herkunft eines Inhalts';
