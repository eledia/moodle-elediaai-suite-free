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
 * German language strings for the KI Quellen plugin.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['feature_usagehint'] = 'Im Kurs „KI-Quellen" in der Kursnavigation öffnen.';
$string['guide_body'] = 'Der Tutor antwortet besser, wenn er den Kurs kennt — aber nur, wenn ihm jemand sagt, was er lesen darf. Das ist diese Seite.

Im Kurs steht „KI-Quellen" in der Navigation. Dort haken Sie Aktivitäten und Materialien an, und der Index wird aufgebaut. Sie sehen, wie weit der Lauf ist, und können sich vorher ansehen, welcher Text aus einer Datei gewonnen wurde.

Drei Dinge, die sich lohnen:

- **Wenig und gut schlägt viel.** Ein sauber geschriebenes Skript ist mehr wert als zwanzig Folien mit Stichpunkten.
- **Nachsehen, was ankam.** Aus einem gescannten PDF wird oft nichts Brauchbares — die Vorschau zeigt das sofort.
- **Neu indexieren, wenn sich Material ändert.** Der Index ist eine Momentaufnahme.

Worauf zu achten ist: was Sie hier auswählen, kann der Tutor Lernenden gegenüber zitieren. Material, das nicht für alle gedacht ist, gehört nicht in die Auswahl.\n\nWohin das Material geht, ist eine Einstellung: entweder an eine externe Aufnahme-Schnittstelle (Voreinstellung) oder an LiteRAG auf dieser Installation. Immer genau eines, und ein Wechsel verlangt, alle Kurse neu aufzunehmen.';
$string['guide_summary'] = 'Sie wählen je Kurs aus, welches Material die KI lesen darf. Was nicht gewählt ist, wird nicht indexiert.';
$string['guide_title'] = 'Woraus der Tutor antwortet';
$string['health_configure'] = 'Einstellungen';
$string['health_destination'] = 'Aufnahmeziel';
$string['health_destination_ok'] = 'Erreichbar ({$a}).';
$string['health_destination_unconfigured'] = 'Es ist kein Ziel eingerichtet, also wird nichts indexiert.';
$string['health_pending'] = 'Kurse warten auf Indexierung';
$string['health_pending_action'] = 'Jetzt indexieren';
$string['health_pending_detail'] = '{$a} Kurs(e) sind zur Indexierung vorgemerkt, haben aber noch keinen Index. Bis dahin antwortet der Tutor dort allein aus dem Modell.';
$string['pluginname'] = 'eLeDia.ai | KI Quellen';

// Settings.
$string['sink'] = 'Aufnahmeziel';
$string['sink_desc'] = 'Wohin Kursinhalte gesendet werden. Es ist immer genau ein Ziel aktiv; beim Umschalten werden alle freigegebenen Kurse in das neue Ziel neu aufgenommen, das bisherige Ziel bleibt unangetastet.';
$string['sink_ingestionapi'] = 'eLeDia.ai Ingestion-API (externer Dienst)';
$string['sink_literag'] = 'LiteRAG (auf dieser Website)';
$string['sink_ingestionapi_baseurl'] = 'Basis-URL des Dienstes';
$string['sink_ingestionapi_baseurl_desc'] = 'Basis-URL des Ingestion-Dienstes, ohne angehängten Pfad (z. B. http://rag-service:8001). Das Plugin ergänzt die dokumentierten Aktionen /documents/upsert, /documents/delete und /health.';
$string['sink_ingestionapi_apikey'] = 'API-Schlüssel';
$string['sink_ingestionapi_apikey_desc'] = 'Der API-Schlüssel zur Authentifizierung beim Ingestion-Dienst. Wird als X-API-Key-Header gesendet. Schlüssel werden pro Mandant ausgegeben.';
$string['sink_ingestionapi_notconfigured'] = 'Basis-URL oder API-Schlüssel fehlt.';
$string['sink_literag_missing'] = 'Das Plugin LiteRAG ist auf dieser Website nicht installiert.';
$string['sink_literag_nokey'] = 'LiteRAG ist installiert, aber sein API-Schlüssel für die Aufnahme ist nicht gesetzt.';
$string['head_sink'] = 'Ziel';
$string['head_sink_desc'] = 'Auswahl, wo Kursinhalte indiziert werden.';
$string['head_sink_ingestionapi'] = 'Ziel: eLeDia.ai Ingestion-API';
$string['head_sink_ingestionapi_desc'] = 'Gilt, wenn die Ingestion-API als Ziel ausgewählt ist.';
$string['head_sink_literag'] = 'Ziel: LiteRAG';
$string['head_sink_literag_desc'] = 'Gilt, wenn LiteRAG als Ziel ausgewählt ist. Hier sind keine Einstellungen nötig: Die Route ergibt sich aus der Adresse dieser Website, und der API-Schlüssel ist der in LiteRAG selbst konfigurierte.';
$string['allow_private_target'] = 'Privates KI Quellen-Ziel erlauben';
$string['allow_private_target_desc'] = 'Erlaubt dem konfigurierten KI Quellen-Endpunkt private Hosts, interne Dienstnamen oder nicht standardmäßige Ports. Nur aktivieren, wenn der Dienst in einem vertrauenswürdigen internen Netzwerk läuft, zum Beispiel Docker oder Kubernetes.';
$string['max_document_size_mb'] = 'Maximale Dokumentgröße (MB)';
$string['max_document_size_mb_desc'] = 'Maximal erlaubte Dokumentgröße in Megabyte. Größere Dokumente werden übersprungen.';
$string['request_timeout_seconds'] = 'Anfrage-Timeout (Sekunden)';
$string['request_timeout_seconds_desc'] = 'HTTP-Anfrage-Timeout in Sekunden für RAG-API-Aufrufe.';
$string['head_connection'] = 'Verbindung';
$string['head_connection_desc'] = 'Transportoptionen, die für jedes ausgewählte Ziel gelten.';
$string['head_courses'] = 'Kursauswahl';
$string['head_courses_desc'] = 'Opt-in-Regeln, die festlegen, welche Kurse an den RAG-Dienst gesendet werden dürfen.';
$string['activitydefault'] = 'Aktivitäten ohne Entscheidung';
$string['activitydefault_desc'] = 'Was mit Aktivitäten eines freigegebenen Kurses geschieht, solange die Lehrkraft nichts entschieden hat. Ausdrückliche Entscheidungen je Aktivität haben immer Vorrang, und ein Umschalten dieser Einstellung entfernt nichts aus dem Index — entfernt wird nur, was ausdrücklich ausgeschlossen wurde. Hinweis: Nach dem Zurückschalten auf „aufgenommen" kommen unentschiedene Aktivitäten erst mit der nächsten Bearbeitung oder einem Kurs-Reindex in den Index.';
$string['activitydefault_optout'] = 'Aufgenommen, bis die Lehrkraft sie ausschließt (Opt-out)';
$string['activitydefault_optin'] = 'Nicht aufgenommen, bis die Lehrkraft sie auswählt (Opt-in)';
$string['scorm_harvest_slidetext'] = 'Bildschirmtexte aus SCORM-Paketen lesen';
$string['scorm_harvest_slidetext_desc'] = 'Zusätzlich zum Sprechertext die Bildschirmtexte erstellter Pakete lesen (derzeit Articulate Storyline). Navigationsbeschriftungen wie „Weiter" werden herausgefiltert, der Rest bleibt aber weniger zusammenhängend als der Sprechertext. Abschalten, wenn die Suche unruhig wird; der Sprechertext ist nie betroffen.';
$string['head_limits'] = 'Limits';
$string['head_limits_desc'] = 'Grenzwerte für Payload-Größe und Laufzeit von Ingestion-Tasks.';
$string['settings_hub_desc'] = 'Wählen Sie einen KI Quellen-Einstellungsbereich.';
$string['settings_section_connection_desc'] = 'Aufnahmeziel und dessen Endpunkt-Einstellungen.';
$string['settings_section_courses_desc'] = 'Pilotkurse, Kategorie-Freigabeliste und Testphasen-Sperre.';
$string['settings_section_limits_desc'] = 'Dokumentgröße und Request-Timeout.';
$string['shell_tagline'] = 'KI Quellen';
$string['shell_subtitle'] = 'Kursinhalte für externe Retrieval-Augmented-Generation-Dienste indexieren.';
$string['shell_help_label'] = 'Hilfe zu KI Quellen';
$string['nav_label'] = 'KI Quellen-Bereiche';
$string['nav_settings'] = 'Einstellungen';
$string['nav_reindex'] = 'Reindex';

// Reindex page.
$string['reindex'] = 'Kursinhalte neu indexieren';
$string['reindexcourse'] = 'Kurs neu indexieren';
$string['reindex_btn'] = 'Kursinhalte neu indexieren';
$string['reindex_force_btn'] = 'Kursinhalte neu indexieren (erzwingen)';
$string['reindex_force_desc'] = 'Die normale Neuindexierung überspringt Aktivitäten, deren Inhalt seit der letzten Aufnahme unverändert ist. Erzwingen sendet alles erneut — für den Fall, dass das Ziel Daten verloren hat.';
$string['selectcourse'] = 'Kurs zur Neuindexierung auswählen';
$string['reindexintro'] = 'Freigegebene Kurse zur Indexierung einplanen oder einen einzelnen Kurs gezielt per Moodle-Kurs-ID neu indexieren.';
$string['manualreindex'] = 'Manuelle Kurs-Reindexierung';
$string['manualreindex_desc'] = 'Für eine gezielte Neuindexierung eines einzelnen Kurses. Freigegebene Kurse können gesammelt oben eingeplant werden.';
$string['courseid'] = 'Kurs-ID';
$string['courseid_help'] = 'Geben Sie die numerische ID des Kurses ein, der neu indexiert werden soll.';
$string['reindexresults'] = 'Reindex-Ergebnisse';
$string['purgeoldsink'] = 'Bisheriges Ziel räumen';
$string['purgeoldsink_desc'] = 'Diese Website hat von {$a} weg umgeschaltet. Die dortigen Dokumente liegen weiterhin da — das Umschalten lässt sie bewusst stehen, weil das alte Ziel in dem Moment oft nicht erreichbar ist und ein fehlschlagendes Räumen den Wechsel nicht blockieren darf.';
$string['purgeoldsink_btn'] = 'Bisheriges Ziel räumen';
$string['purgeoldsinkconfirm'] = 'Die Dokumente von {$a->courses} Kurs(en) aus {$a->sink} entfernen? Das lässt sich von hier aus nicht rückgängig machen.';
$string['purgeoldsinkqueued'] = 'Räumen von {$a} Kurs(en) im bisherigen Ziel eingeplant.';
$string['purgeoldsinknone'] = 'Es wartet kein bisheriges Ziel auf das Räumen.';
$string['purgeoldsinkunreachable'] = 'Das bisherige Ziel antwortet nicht ({$a}). Eingeplante Aufgaben versuchen es erneut, solange es nicht erreichbar ist, wird aber nichts entfernt.';
$string['purgeoldsinkfailed'] = 'Beim Räumen von Kurs {$a->course} blieben {$a->failed} Modul(e) zurück; die Aufgabe versucht es erneut.';
$string['suite_feature_desc'] = 'Festlegen, welches Kursmaterial die KI als Quelle nutzen darf — und nachsehen, was sie gefunden hat.';
$string['suite_feature_detail'] = 'Der Tutor antwortet besser, wenn er den Kurs kennt. Dieses Werkzeug entscheidet, was er lesen darf: Lehrende wählen je Kurs die Aktivitäten und Materialien aus, verfolgen die Indexierung und sehen vorab, was daraus gewonnen wurde. Indexiert wird nichts, was nicht gewählt wurde.';
$string['suite_feature_key_1'] = 'Je Kurs auswählen, welche Aktivitäten und Materialien die KI speisen.';
$string['suite_feature_key_2'] = 'Den Indexlauf verfolgen und bei geändertem Material neu anstoßen.';
$string['suite_feature_key_3'] = 'Den gewonnenen Text ansehen, bevor Lernende danach fragen.';
$string['suite_feature_name'] = 'KI-Quellen';
$string['task_purge_old_sink'] = 'Einen Kurs aus dem bisherigen KI-Quellen-Ziel räumen';
$string['recenterrors'] = 'Letzte Aufnahmefehler';
$string['recenterrors_desc'] = 'Aktivitäten, deren letzter Aufnahmeversuch fehlschlug. Der zuvor indexierte Inhalt liegt, sofern vorhanden, weiterhin im Index.';
$string['reindexsuccess'] = '{$a} Dokument(e) erfolgreich indexiert.';
$string['reindexcomplete'] = 'Kurs-Reindex abgeschlossen.';
$string['ingesting'] = 'Inhalte für Kurs werden indexiert: {$a}';
$string['unknownmodule'] = 'Modul (cmid {$a})';
$string['pendingindexingtitle'] = 'Freigegebene Kurse warten auf die Indexierung';
$string['pendingindexingcount'] = '{$a} Kurs(e) freigegeben, aber noch nicht indexiert.';
$string['partialindexingtitle'] = 'Freigegebene Kurse nur teilweise indexiert';
$string['partialindexingcount'] = '{$a} Kurs(e) liegen im Index, es fehlen aber noch Inhalte.';
$string['pendingandpartialcount'] = '{$a->pending} Kurs(e) noch nicht indexiert, {$a->partial} nur teilweise.';
$string['retriesexhausted'] = 'Nach {$a} fehlgeschlagenen Versuchen übersprungen. Nach Behebung der Ursache eine Neuindexierung dieses Kurses auslösen.';
$string['indexreleasedcourses'] = 'Freigegebene Kurse jetzt indexieren';
$string['pendingindexingqueued'] = '{$a} freigegebene Kurs(e) wurden zur Indexierung eingeplant.';
$string['indexingreadytitle'] = 'Freigegebene Kurse sind indexiert';
$string['indexingreadybody'] = 'Aktuell warten keine freigegebenen Kurse auf die Indexierung.';
$string['openreindex'] = 'Reindex öffnen';

// Results table.
$string['modulename'] = 'Modul';
$string['status'] = 'Status';
$string['details'] = 'Details';
$string['statussuccess'] = 'Erfolgreich';
$string['statusskipped'] = 'Übersprungen';
$string['statuserror'] = 'Fehler';

// Messages.
$string['noextractor'] = 'Für diesen Modultyp ist kein Extractor verfügbar.';
$string['nocontent'] = 'Keine Inhalte zur Indexierung vorhanden.';
$string['documentsizeexceeded'] = 'Die Dokumentgröße ({$a->size} MB) überschreitet das Maximum ({$a->max} MB).';
$string['unsupportedcontenttype'] = 'Nicht unterstützter Inhaltstyp: {$a}';
$string['apiclienterror'] = 'RAG-API-Fehler: {$a}';
$string['apinotconfigured'] = 'Das ausgewählte Aufnahmeziel ist nicht konfiguriert. Bitte dessen Einstellungen vervollständigen.';
$string['invalidcourseid'] = 'Ungültige Kurs-ID.';
$string['coursenotfound'] = 'Kurs wurde nicht gefunden.';
$string['deletemodule'] = 'Modul wird aus dem RAG-Index gelöscht: cmid {$a}';
$string['ingestionsuccess'] = 'Indexiert: source_id={$a->source_id}, Typ={$a->content_type}, Größe={$a->size}';
$string['ingestionfailed'] = 'Indexierung fehlgeschlagen: source_id={$a->source_id}, HTTP {$a->http_code}';
$string['ingestionmultisummary'] = '{$a->sent} Dokument(e) indexiert, {$a->failed} fehlgeschlagen';
$string['ingestionmultiskipped'] = '{$a} Datei(en) übersprungen.';

// Dokumentformate (Support-Matrix, AI-75).
$string['skipreason_unknowntype'] = 'Übersprungen: „{$a->title}“ — der Dateityp {$a->type} gehört nicht zu den unterstützten Dokumentformaten.';
$string['skipreason_notaccepted'] = 'Übersprungen: „{$a->title}“ — das aktive Ziel ({$a->sink}) verarbeitet {$a->type} derzeit nicht.';
$string['skipreason_empty'] = 'Übersprungen: „{$a}“ — die Datei ist leer oder konnte nicht gelesen werden.';
$string['skippedmore'] = 'Und {$a} weitere Datei(en).';
$string['skippedfilelog'] = 'cmid {$a->cmid}: {$a->reason}';
$string['health_formats'] = 'Dokumentformate';
$string['health_formats_detail'] = 'Wird derzeit exportiert: {$a}';
$string['deletionsuccess'] = 'Aus Index gelöscht: source_id={$a}';
$string['deletionfailed'] = 'Löschen fehlgeschlagen: source_id={$a->source_id}, HTTP {$a->http_code}';
$string['taskingestion'] = 'KI Quellen Content-Indexierung';
$string['taskdeletion'] = 'KI Quellen Content-Löschung';

// Course marking (opt-in ingestion).
$string['allcategories'] = 'Alle Kursbereiche einbeziehen';
$string['allcategories_desc'] = 'Kurse aus jedem Kursbereich aufnehmen, auch aus solchen, die spaeter angelegt werden. Die Einstellung am Kurs gilt weiterhin: Ein ausdruecklich ausgeschlossener Kurs bleibt draussen. Ausgeschaltet lassen, um stattdessen die Liste unten zu verwenden.';
$string['enabledcategories'] = 'Indexierte Kursbereiche';
$string['enabledcategories_desc'] = 'Nur Kurse in den ausgewählten Kursbereichen (oder deren Unterbereichen) werden an den RAG-Dienst gesendet. Die Indexierung ist Opt-in: Ist nichts ausgewählt, wird kein Kurs indexiert, außer er ist über die Kurseinstellung „KI Quellen“ einzeln auf „Include“ gesetzt.';
$string['searchcategories'] = 'Kursbereiche suchen';
$string['cfcategory'] = 'KI-Tutor';
$string['cffieldname'] = 'KI Quellen';
$string['cffielddesc'] = 'Legt fest, ob die Inhalte dieses Kurses an die Wissensbasis des AI Tutors gesendet werden. „Default“ folgt den Kursbereichseinstellungen der Website; „Include“ sendet immer; „Exclude“ sendet nie.';
$string['coursenotmarked'] = 'Der Kurs ist nicht für die Indexierung freigegeben.';
$string['activitynotselected'] = 'Die Aktivität ist nicht für die Aufnahme ausgewählt.';
$string['activityhidden'] = 'Die Aktivität ist für Lernende verborgen; nicht Teil des Index.';
$string['contentunchanged'] = 'Unverändert seit der letzten Aufnahme — übersprungen.';
$string['activityexcluded'] = 'Die Aktivität ist von der Aufnahme ausgeschlossen; aus dem Index entfernt.';
$string['task_reconcile_all'] = 'Kursfreigaben für KI Quellen abgleichen';
$string['task_cleanup'] = 'Verwaiste und verborgene KI Quellen-Inhalte aufräumen';
$string['task_converge_undecided'] = 'Unentschiedene Aktivitäten nach Standardwechsel einplanen';
$string['pilotcourses'] = 'Pilotkurse';
$string['pilotcourses_desc'] = 'Bestimmte Kurse, die unabhängig von der Kategorie-Freigabeliste indexiert werden. Nutzen Sie das Suchfeld, um einen oder mehrere Kurse für eine kontrollierte Test-/Pilotphase auszuwählen.';
$string['searchcourses'] = 'Kurse suchen';
$string['lockcoursemarking'] = 'Kursmarkierung sperren (Testphase)';
$string['lockcoursemarking_desc'] = 'Wenn aktiviert, hat die Kurseinstellung „KI Quellen“ keine Wirkung. Nur die Pilotkursliste und die Kategorie-Freigabeliste entscheiden, was indexiert wird; das Kursfeld ist gegen Bearbeitung durch Trainer/innen gesperrt und nur lesbar sichtbar. Nutzen Sie dies während einer Testphase, damit die indexierten Kurse ausschließlich auf dieser Admin-Seite gesteuert werden. Bestehende Kurswerte bleiben erhalten und werden wieder wirksam, wenn die Sperre deaktiviert wird.';

// Extractor content labels.
$string['alsoknownas'] = 'Auch bekannt als:';
$string['questionhint'] = 'Hinweis:';
$string['gradingcriteria'] = 'Bewertungskriterien';
$string['contenttruncated'] = '[Inhalt wurde auf die Größenbegrenzung gekürzt]';
$string['contenttruncatedlog'] = 'Inhalt wurde vor dem Senden auf das Limit von {$a->max} MB gekürzt: cmid {$a->cmid}';

// Activity selection (module form + course page).
$string['activityinclude'] = 'In die Wissensbasis des KI-Tutors aufnehmen';
$string['activityinclude_help'] = 'Legt fest, ob die Inhalte dieser Aktivität an die Wissensbasis gesendet werden, aus der der KI-Tutor antwortet. Beim Abwählen werden bereits indexierte Inhalte dieser Aktivität entfernt. Wirkt nur in Kursen, die für die Aufnahme freigegeben sind.';
$string['activities_title'] = 'KI Quellen — Aktivitätsauswahl';
$string['activities_nav'] = 'KI Quellen';
$string['activities_intro'] = 'Wählen Sie, welche Aktivitäten dieses Kurses an die Wissensbasis des KI-Tutors gesendet werden. Änderungen wirken mit dem nächsten Hintergrundlauf.';
$string['activities_mode_optout'] = 'Neue Aktivitäten werden automatisch aufgenommen, bis sie hier ausgeschlossen werden.';
$string['activities_mode_optin'] = 'Neue Aktivitäten werden erst aufgenommen, wenn sie hier ausgewählt werden.';
$string['activities_unsupported'] = 'Nicht unterstützt';
$string['activities_unsupported_hint'] = 'Kein Extractor kann diesen Aktivitätstyp lesen; er wird nie gesendet.';
$string['activities_excluded_badge'] = 'Ausgeschlossen';
$string['activities_toggle_aria'] = 'Aufnahme von {$a} umschalten';
$string['activities_saved'] = 'Auswahl für „{$a}" gespeichert.';
$string['activities_savefailed'] = 'Die Auswahl für „{$a}" konnte nicht gespeichert werden.';
$string['activities_nosections'] = 'Dieser Kurs hat noch keine Aktivitäten.';
$string['activities_filter'] = 'Aktivitäten filtern';
$string['activities_reset'] = 'Zurücksetzen';
$string['activities_reset_aria'] = '{$a} auf den Site-Standard zurücksetzen';
$string['activities_bulk_aria'] = 'Sammelaktionen für {$a}';
$string['activities_bulk_include'] = 'Alle aufnehmen';
$string['activities_bulk_exclude'] = 'Alle ausschließen';
$string['activities_bulk_saved'] = 'Auswahl für {$a} Aktivitäten gespeichert.';
$string['activities_status_indexed'] = 'Indexiert';
$string['activities_status_error'] = 'Fehler';
$string['activities_status_unknown'] = 'Unbekannt';

// Dry run.
$string['preview_title'] = 'Probelauf';
$string['preview_intro'] = 'Was für diese Aktivität gerade an die Wissensbasis gesendet würde. Das Öffnen dieser Seite sendet nichts.';
$string['preview_link'] = 'Probelauf';
$string['preview_aria'] = 'Probelauf für {$a}';
$string['preview_destination'] = 'Ziel';
$string['preview_index_state'] = 'Im Index';
$string['preview_fingerprint'] = 'Inhalts-Fingerabdruck';
$string['preview_metadata'] = 'Mitgesendete Metadaten';
$string['preview_documents'] = 'Dokumente ({$a})';
$string['preview_nodocuments'] = 'Für diese Aktivität würde nichts gesendet.';
$string['preview_binary'] = 'Binärer Inhalt — wird unverändert gesendet, hier nicht dargestellt.';
$string['preview_truncated'] = 'Gekürzt dargestellt. Die Größe oben ist die tatsächliche; das Ziel erhält den vollständigen Inhalt.';
$string['preview_backtocourse'] = 'Zurück zur Aktivitätsauswahl';
$string['preview_verdict_ingest'] = 'Würde gesendet';
$string['preview_verdict_delete'] = 'Würde aus dem Index entfernt';
$string['preview_verdict_skip'] = 'Würde nicht gesendet';
$string['preview_unchanged_note'] = 'Seit der letzten Aufnahme unverändert; eine Neuindexierung überspränge diese Aktivität. Auf der Reindex-Seite sendet „erzwingen" sie trotzdem.';
$string['preview_index_absent'] = 'Nicht im Index.';
$string['preview_index_identical'] = 'Identisch mit dem, was im Index liegt (gesendet {$a}).';
$string['preview_index_older'] = 'Der Index hält eine ältere Fassung, gesendet {$a}. Gespeichert ist nur deren Fingerabdruck, nicht der Wortlaut — sie lässt sich hier deshalb nicht zeigen.';
$string['preview_index_modelchanged'] = 'Der Inhalt ist unverändert, aber das für diesen Kurs verbuchte Embedding-Modell weicht vom Ziel ab. Der nächste Lauf sendet ihn deshalb erneut.';
$string['preview_index_otherdestination'] = 'Der Eintrag beschreibt das vorherige Ziel {$a->old}. Für {$a->active} ist noch nichts verbucht.';
$string['preview_index_lastattemptfailed'] = 'Der letzte Versuch scheiterte am {$a->date}: {$a->error} Im Index liegt weiterhin, was zuvor erfolgreich gesendet wurde, sofern überhaupt etwas.';
$string['preview_lookup'] = 'Probelauf für eine einzelne Aktivität';
$string['preview_lookup_desc'] = 'Den Probelauf direkt über eine Aktivitäts-ID öffnen — die Zahl, auf die eine Quell-ID endet und die auch der Fehlerbericht unten nennt.';
$string['preview_cmid'] = 'Aktivitäts-ID (cmid)';
$string['preview_open'] = 'Probelauf öffnen';

// Barrierefreiheit der Medien im Paket.
$string['a11y_heading'] = 'Barrierefreiheit';
$string['a11y_complete'] = 'Alle Mediendateien ({$a->total}) haben Untertitel.';
$string['a11y_incomplete'] = '{$a->captioned} von {$a->total} Mediendateien haben Untertitel — diese Aktivität ist nicht barrierefrei.';
$string['a11y_undetermined'] = '{$a->captioned} von {$a->total} Mediendateien haben Untertitel. Die übrigen ließen sich keiner Untertitelspur zuordnen, was nicht dasselbe ist wie „hat keine".';
$string['a11y_undetermined_transcript'] = '{$a->captioned} von {$a->total} Mediendateien haben Untertitel, und das Paket bringt ein Transkript mit. Für Ton ohne Bild ist ein Transkript eine anerkannte Alternative (WCAG 1.2.1) — ob es diese Aufnahmen abdeckt, lässt sich dem Paket aber nicht entnehmen.';
$string['a11y_files_missing'] = 'Ohne Untertitel:';
$string['a11y_files_undetermined'] = 'Keiner Untertitelspur zuzuordnen:';
$string['a11y_dangling'] = 'Das Paket verweist auf Untertiteldateien, die es nicht enthält — die Untertitel gibt es, der Export ist kaputt:';
$string['a11y_files_transcript'] = 'Ohne Untertitel — bitte prüfen, ob das Transkript sie abdeckt:';

// Capabilities.
$string['elediaai_sources:reindex'] = 'Kursinhalte für KI Quellen neu indexieren';
$string['elediaai_sources:selectactivities'] = 'Auswählen, welche Aktivitäten eines Kurses aufgenommen werden';

// Privacy.
$string['privacy:metadata'] = 'Das Plugin KI Quellen speichert, wer die Aufnahme-Auswahl einer Aktivität zuletzt geändert hat; Kursinhalte werden an den konfigurierten externen Dienst gesendet.';
$string['privacy:metadata:cm'] = 'Ausdrückliche Aufnahme-Entscheidungen je Aktivität.';
$string['privacy:metadata:cm:usermodified'] = 'Die Person, die die Entscheidung zuletzt geändert hat.';
$string['privacy:metadata:cm:included'] = 'Ob die Aktivität in die Aufnahme einbezogen oder davon ausgeschlossen ist.';
$string['privacy:metadata:cm:timemodified'] = 'Wann die Entscheidung zuletzt geändert wurde.';
$string['privacy:metadata:rag_service'] = 'Kursinhalte und Modulmetadaten werden an den konfigurierten KI Quellen-Dienst übertragen.';
$string['privacy:metadata:rag_service:site_url'] = 'Die Moodle-Site-URL wird übertragen, damit der RAG-Dienst den Tenant prüfen kann.';
$string['privacy:metadata:rag_service:course_id'] = 'Die Moodle-Kurs-ID wird übertragen, um Inhalte dem Kurs zuzuordnen.';
$string['privacy:metadata:rag_service:cmid'] = 'Die Moodle-Kursmodul-ID wird übertragen, um die Aktivität zu identifizieren.';
$string['privacy:metadata:rag_service:module_url'] = 'Die Moodle-Modul-URL wird für spätere Quellenangaben und Links übertragen.';
$string['privacy:metadata:rag_service:content'] = 'Extrahierte Inhalte aus Kursaktivitäten werden zur Verarbeitung, Segmentierung und Indexierung übertragen.';

// Subplugin types.
$string['subplugintype_aisourcesextractor'] = 'Content-Extractor';
$string['subplugintype_aisourcesextractor_plural'] = 'Content-Extractors';
