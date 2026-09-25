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
$string['guide_body'] = 'Die KI-Verordnung verlangt in Art. 50 zweierlei: Menschen müssen erkennen können, dass sie es mit einer KI zu tun haben, und KI-erzeugte Inhalte müssen maschinenlesbar gekennzeichnet sein. Die Suite legt dafür zu jeder KI-Ausgabe einen Herkunftsnachweis an.

Der Bericht unter „KI-Transparenz" zeigt diese Nachweise, die neuesten zuerst: welches Plugin die Ausgabe erzeugt hat, mit welchem Dienst und Modell, wann, und ob sie ihre Kennzeichnung trug.

Worauf zu achten ist:

- **„Ohne Kennzeichnung" ist der Befund, um den es geht.** Ein solcher Nachweis bedeutet, dass die Ausgabe ohne ihre Art.-50-Markierung hinausging.
- **Jeder Nachweis lässt sich einzeln prüfen.** Über „Öffnen" sehen Sie die Angaben zu genau dieser Ausgabe.
- **Die Person wird nach einer Frist entfernt.** Nach der eingestellten Zahl von Tagen wird die Verknüpfung zur auslösenden Person gelöst; der Nachweis selbst bleibt.

Den Bericht sehen Personen mit der Berechtigung „local/aitransparency:viewreport", standardmäßig Manager und Administration.';
$string['guide_summary'] = 'Welche KI-Ausgaben die Website nachweist und ob sie nach Art. 50 gekennzeichnet hinausgingen.';
$string['guide_title'] = 'KI-Transparenz: der Herkunftsbericht';
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
$string['suite_feature_desc'] = 'Alle KI-Ausgaben, die die Website nachweist, mit ihrer Kennzeichnung nach Art. 50 — und die, die ohne hinausgingen.';
$string['suite_feature_detail'] = 'Der Herkunftsbericht listet jede KI-erzeugte Ausgabe, für die diese Website einen Herkunftsnachweis angelegt hat: welches Plugin sie erzeugt hat, mit welchem Dienst und Modell, und ob sie ihre maschinenlesbare Kennzeichnung nach Art. 50 der KI-Verordnung trug. Ein Nachweis ohne Kennzeichnung wird als solcher gezeigt — Lücken werden sichtbar, statt still zu bleiben.';
$string['suite_feature_key_1'] = 'Alle Herkunftsnachweise der Website, die neuesten zuerst.';
$string['suite_feature_key_2'] = 'Ausgaben ohne Kennzeichnung nach Art. 50 fallen auf.';
$string['suite_feature_key_3'] = 'Jeder Nachweis lässt sich einzeln über seine Kennung prüfen.';
$string['suite_feature_name'] = 'KI-Transparenz';
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
