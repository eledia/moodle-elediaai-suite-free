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
 * German strings for local_elediaai_chatengine.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['health_backend'] = 'Chat-Backend';
$string['health_backend_unconfigured'] = 'Es ist kein Backend gewählt, also kann kein Chat antworten.';
$string['pluginname'] = 'KI-Chat-Engine';
$string['privacy:metadata:thread'] = 'Unterhaltungen mit einer KI-Assistenz, aus welcher Chat-Oberfläche auch immer geführt.';
$string['privacy:metadata:thread:userid'] = 'Die Person, die die Unterhaltung geführt hat.';
$string['privacy:metadata:thread:placement'] = 'Aus welcher Chat-Oberfläche die Unterhaltung begonnen wurde.';
$string['privacy:metadata:thread:courseid'] = 'Der Kurs, zu dem die Unterhaltung gehörte, sofern sie nicht kursübergreifend war.';
$string['privacy:metadata:thread:mode'] = 'Ob aus Kursmaterial oder allein aus dem Modell geantwortet wurde.';
$string['privacy:metadata:thread:lastpreview'] = 'Eine kurze Vorschau der letzten Nachricht für die Unterhaltungsliste.';
$string['privacy:metadata:thread:timecreated'] = 'Wann die Unterhaltung begonnen wurde.';
$string['privacy:metadata:thread:timemodified'] = 'Wann die Unterhaltung zuletzt genutzt wurde.';
$string['privacy:metadata:msg'] = 'Die einzelnen Beiträge einer Unterhaltung.';
$string['privacy:metadata:msg:role'] = 'Ob der Beitrag von der Person stammt oder von der Assistenz erzeugt wurde.';
$string['privacy:metadata:msg:content'] = 'Der Text des Beitrags.';
$string['privacy:metadata:msg:sources'] = 'Das Kursmaterial, das eine Antwort zitiert hat.';
$string['privacy:metadata:msg:timecreated'] = 'Wann der Beitrag festgehalten wurde.';
$string['privacy:metadata:backend'] = 'Nachrichten werden an das eingestellte KI-Backend gesendet, damit es eine Antwort erzeugen kann.';
$string['privacy:metadata:backend:usermessage'] = 'Die eingegebene Nachricht.';
$string['privacy:metadata:backend:history'] = 'Frühere Beiträge derselben Unterhaltung, damit die Antwort dem Gesprächsfaden folgt.';
$string['privacy:metadata:backend:userid'] = 'Ein personenbezogenes Token, das ausweist, wer fragt, damit das Backend die Rechte dieser Person anwendet.';
$string['privacy:metadata:backend:courseid'] = 'Der Kurs, aus dessen Material geantwortet werden darf.';
$string['privacy:metadata:backend:language'] = 'Die eingestellte Sprache, damit in ihr geantwortet wird.';

// Backends.
$string['backend_ingestionapi'] = 'RAG-Agent';
$string['backend_literag'] = 'LiteRAG (in dieser Instanz)';
$string['backend_simulator'] = 'Simulator (ohne Sprachmodell)';
$string['setting_simulator'] = 'Simulierter Backend (ohne Sprachmodell)';
$string['setting_simulator_desc'] = 'Beantwortet jeden Chat-Zug nach festem Drehbuch, statt ein Sprachmodell zu fragen. Gedacht für die Arbeit an den Chat-Oberflächen: Layout, Streaming, Quellenkarten, Fehlerzustände, Bestätigungskarte.

<strong>Solange das an ist, erreicht kein Chat dieser Website ein echtes Backend.</strong> Jede Antwort ist als simuliert gekennzeichnet und der Health-Bericht zeigt eine Warnung — für den Unterricht oder zur Beurteilung von Antwortqualität taugt hier nichts.

Schlüsselwörter steuern, was zurückkommt — schicke <code>/hilfe</code> in einem beliebigen Chat.';
$string['status_simulator_active'] = 'Simulator aktiv — die Antworten sind erfunden, es ist kein Sprachmodell beteiligt.';
$string['simulator_marker'] = '**Simulierte Antwort.** Es war kein Sprachmodell beteiligt.';
$string['simulator_topic'] = 'Simulation';
$string['simulator_default'] = 'Du hast geschrieben: *{$a->message}*

Das ist der simulierte Backend. Er antwortet nach Drehbuch, damit an der Chat-Oberfläche gearbeitet werden kann — ohne Schlüssel, ohne Netz, ohne Rechnung.

**Was dieser Zug mitgebracht hat**

| Feld | Wert |
|---|---|
| Antwortmodus | `{$a->mode}` |
| Grundanweisung | `{$a->prompt}` |
| Moodle-Werkzeuge erlaubt | {$a->tools} |

`/hilfe` zeigt die übrigen Varianten.';
$string['simulator_help'] = 'Diese Schlüsselwörter ändern, was zurückkommt. Eines davon irgendwo in die Nachricht schreiben.

- `/hilfe` — diese Liste
- `/quellen` — eine Antwort mit drei Quellenkarten, gemeldet als belegt
- `/fehler` — der Backend meldet ein Scheitern, damit der Fehlerzustand sichtbar wird
- `/langsam` — gestreamt mit Pause zwischen den Fragmenten, für das Tippverhalten
- `/lang` — eine lange Antwort, für Scrollen und Layout
- `/bestaetigen` — eine Antwort mit Bestätigungskarte (es wird nie etwas ausgeführt)
- `/tokens` — eine Antwort, die Token-Verbrauch meldet, für das Kontingent

Die englischen Wörter funktionieren ebenso: `/help`, `/sources`, `/error`, `/slow`, `/long`, `/confirm`.';
$string['simulator_error_body'] = 'Der simulierte Backend wurde gebeten zu scheitern, und hat es getan. So sieht das Panel aus, wenn ein Backend nicht antworten kann.';
$string['simulator_sources_body'] = 'Diese Antwort beruft sich auf Kursmaterial [S1] und auf zwei weitere Dokumente [S2] [S3]. Die Karten unten sind erfunden — sie existieren, damit die Belegmarker irgendwohin zeigen.';
$string['simulator_source_one'] = 'Simuliertes Dokument: Einführung';
$string['simulator_source_two'] = 'Simuliertes Dokument: Übungsblatt';
$string['simulator_source_three'] = 'Simuliertes Dokument: Zusammenfassung';
$string['simulator_source_snippet'] = 'Eine erfundene Textstelle, lang genug um zu sehen, wie eine Quellenkarte umbricht, wenn der Text nicht nach vier Wörtern aufhört.';
$string['simulator_confirm_body'] = 'Diese Antwort trägt eine Bestätigungskarte. Das Bestätigen bewirkt nichts: hinter dem Simulator stehen keine Moodle-Werkzeuge, und eine Bestätigung, die handelte, machte diesen Backend gefährlich statt bloß unecht.';
$string['simulator_confirm_summary'] = 'Nachricht an Erika Mustermann senden (simuliert — es wird nichts gesendet).';
$string['simulator_tokens_body'] = 'Diese Antwort meldet einen Verbrauch von 1234 Prompt- und 567 Completion-Token, damit das Kontingent etwas zu verbuchen hat.';
$string['simulator_slow_body'] = 'Diese Antwort kommt Fragment für Fragment mit Pause dazwischen, damit das Tippverhalten des Panels in einem Tempo zu sehen ist, dem das Auge folgen kann.';
$string['simulator_long'] = '## Eine lange Antwort

Die Absätze unten wiederholen sich. Es geht um die Länge, nicht um den Inhalt: Scrollen, die Lage des Eingabefelds, und ob etwas springt, während sie wächst.';
$string['simulator_long_para'] = 'Ein erfundener Absatz des simulierten Backends. Er sagt nichts, dafür etwas länger, damit die Oberfläche etwas zu rendern hat und man Zeilenabstand, Satzbreite und Textrhythmus beurteilen kann.';

// Antwortmodi.
$string['mode_grounded'] = 'Geerdet (antwortet aus der Wissensbasis)';
$string['mode_ungrounded'] = 'Nur Modell (ohne Wissensbasis)';

// Cache-Definitionen.
$string['cachedef_usertoken'] = 'Personenbezogene Rückruf-Token für das Backend';
$string['cachedef_ratelimit'] = 'Zähler für Chat-Anfragen je Person';

// Geplante Aufgaben.
$string['task_purge_threads'] = 'Abgelaufene KI-Unterhaltungen löschen';

// Einstellungen.
$string['settings_head_backend'] = 'Backend';
$string['settings_head_backend_desc'] = 'Welches Backend antwortet, wird hier nicht eingestellt. Es folgt dem Ziel, in das Kursmaterial geschrieben wird — festgelegt in KI-Quellen. So wird eine Frage nie an einen Dienst gerichtet, in den das Material nie gesendet wurde. Hier wird eingestellt, wie dieses Backend erreicht wird, und welche Grenzen gelten, gleich welches antwortet.';
$string['settings_head_ingestionapi'] = 'RAG-Agent (Ingestion-Pipeline)';
$string['settings_head_ingestionapi_desc'] = 'Der Agent ist ein eigener Dienst neben der Ingestion-Pipeline: die Pipeline nimmt Dokumente entgegen, der Agent beantwortet Fragen. Seine Adresse wird deshalb getrennt eingestellt und lässt sich nicht aus der Basis-URL der Pipeline ableiten.';
$string['settings_head_literag'] = 'LiteRAG';
$string['settings_head_literag_desc'] = 'LiteRAG läuft in dieser Instanz und wird im selben Prozess aufgerufen. Es gibt hier weder eine Adresse noch eine Zugangskennung einzustellen; Modell und Suche werden im Plugin LiteRAG selbst konfiguriert.';
$string['settings_head_limits'] = 'Grenzen';
$string['setting_backend_ingestionapi_url'] = 'MCP-Endpunkt des Agenten';
$string['setting_backend_ingestionapi_url_desc'] = 'Vollständige URL des MCP-Endpunkts, zum Beispiel https://agent.example.com/mcp.';
$string['setting_backend_ingestionapi_authmethod'] = 'Authentifizierungsverfahren';
$string['setting_backend_ingestionapi_authmethod_desc'] = 'Wie sich diese Instanz gegenüber dem Agenten ausweist.';
$string['setting_authmethod_none'] = 'Keines';
$string['setting_authmethod_bearer'] = 'Bearer-Token';
$string['setting_authmethod_header'] = 'Eigene Header-Zeilen';
$string['setting_backend_ingestionapi_authtoken'] = 'Authentifizierungs-Token';
$string['setting_backend_ingestionapi_authtoken_desc'] = 'Das Bearer-Token, oder bei eigenen Headern je Zeile ein vollständiges „Name: Wert“. Wird verschlüsselt gespeichert.';
$string['setting_backend_ingestionapi_allowinsecure'] = 'Einfaches HTTP erlauben';
$string['setting_backend_ingestionapi_allowinsecure_desc'] = 'Erlaubt einen http://-Endpunkt. Nur für die Entwicklung gedacht; ein über einfaches HTTP gesendetes Token ist unterwegs mitlesbar.';
$string['setting_backend_ingestionapi_allowprivate'] = 'Adressen im privaten Netz erlauben';
$string['setting_backend_ingestionapi_allowprivate_desc'] = 'Umgeht die Sperrliste der Instanz für den Host des Agenten. Nur nötig, wenn der Agent im internen Netz oder auf einem Entwicklungsrechner läuft.';
$string['setting_tool_chat'] = 'Name des Chat-Werkzeugs';
$string['setting_tool_chat_desc'] = 'Name des Chat-Werkzeugs des Agenten. Gilt nur für den RAG-Agenten; LiteRAG löst Werkzeugnamen über seine eigenen Einstellungen auf.';
$string['setting_tool_history'] = 'Name des Verlaufs-Werkzeugs';
$string['setting_tool_history_desc'] = 'Optional. Leer lassen, wenn der Agent keinen Gesprächsverlauf ausliefert.';
$string['setting_tool_delete'] = 'Name des Werkzeugs zum Löschen einer Unterhaltung';
$string['setting_tool_delete_desc'] = 'Optional. Leer lassen, wenn der Agent das Löschen einzelner Unterhaltungen nicht unterstützt.';
$string['setting_tool_deleteuser'] = 'Name des Werkzeugs zum Löschen aller Daten einer Person';
$string['setting_tool_deleteuser_desc'] = 'Optional, aber empfohlen: ohne es werden die Daten einer gelöschten Person nur in dieser Instanz entfernt.';
$string['setting_tool_memoryoptin'] = 'Name des Werkzeugs für die Gedächtnis-Einwilligung';
$string['setting_tool_memoryoptin_desc'] = 'Optional. Leer lassen, wenn der Agent kein Langzeitgedächtnis hat.';
$string['setting_tool_recluster'] = 'Name des Werkzeugs für die Fragen-Gruppierung';
$string['setting_tool_recluster_desc'] = 'Optional. Wird von der nächtlichen Fragenauswertung des Tutor-Placements genutzt.';
$string['setting_requesttimeout'] = 'Zeitgrenze je Anfrage';
$string['setting_requesttimeout_desc'] = 'Sekunden, die auf eine Antwort gewartet wird.';
$string['setting_maxmessagelength'] = 'Maximale Nachrichtenlänge';
$string['setting_maxmessagelength_desc'] = 'Zeichen, die eine einzelne Nachricht haben darf.';
$string['setting_historylimit'] = 'Grenze des Gesprächsverlaufs';
$string['setting_historylimit_desc'] = 'Wie viele frühere Beiträge mit einer Anfrage mitgehen. Begrenzt die Anfragegröße langer Unterhaltungen.';
$string['setting_ratelimitperminute'] = 'Anfragen je Minute';
$string['setting_ratelimitperminute_desc'] = 'Chat-Anfragen, die eine Person je Minute senden darf. 0 schaltet die Grenze ab.';
$string['setting_dailymessagelimit'] = 'Nachrichten je Tag';
$string['setting_dailymessagelimit_desc'] = 'Nachrichten, die eine Person je Tag senden darf. 0 bedeutet unbegrenzt.';
$string['setting_streamingenabled'] = 'Antworten fortlaufend anzeigen';
$string['setting_streamingenabled_desc'] = 'Zeigt eine Antwort, während sie entsteht, sofern das Backend das unterstützt.';
$string['setting_retentiondays'] = 'Aufbewahrung von Unterhaltungen';
$string['setting_retentiondays_desc'] = 'Tage, die eine Unterhaltung nach ihrem letzten Beitrag aufbewahrt wird. 0 bewahrt sie auf, bis eine Person oder die Administration sie löscht.';
$string['setting_mcpserviceid'] = 'Webservice für Rückrufe';
$string['setting_mcpserviceid_desc'] = 'Der externe Dienst, über den das Backend im Namen der fragenden Person nach Moodle zurückruft.';
$string['setting_tokenlifetime'] = 'Gültigkeit des Rückruf-Tokens';
$string['setting_tokenlifetime_desc'] = 'Sekunden, die ein Rückruf-Token gültig bleibt. 0 vergibt Token ohne Ablauf.';

// Zustände in den Oberflächen.
$string['cachedef_backendhealth'] = 'Letzte Rückmeldung des aktiven Chat-Backends zu seinem Zustand';
$string['status_health_notprobed'] = 'Eingerichtet. Dieses Backend bietet keinen Health-Endpunkt; die Erreichbarkeit meldet stattdessen die Quellen-Indizierung.';
$string['status_llm_missing_key'] = 'Für das Sprachmodell ist kein API-Schlüssel eingetragen.';
$string['status_nobackend'] = 'Es ist kein KI-Backend verfügbar';
$string['status_nobackend_help'] = 'Der Chat steht erst zur Verfügung, wenn die Administration ein Ziel in KI-Quellen und die passende Backend-Verbindung eingerichtet hat.';
$string['status_nobackend_admin'] = 'Richten Sie ein Ziel in KI-Quellen ein und konfigurieren Sie anschließend die Verbindung zum passenden Backend in den Einstellungen der KI-Chat-Engine.';

// Fehler.
$string['error_no_backend'] = 'Es ist kein KI-Backend eingerichtet. Der Chat steht nicht zur Verfügung.';
$string['error_backend_unavailable'] = 'Das KI-Backend war nicht erreichbar. Bitte später erneut versuchen.';
$string['error_backend_bad_response'] = 'Das KI-Backend hat eine Antwort geliefert, die nicht gelesen werden konnte.';
$string['error_tool_error'] = 'Die Assistenz konnte diese Anfrage nicht ausführen.';
$string['error_agent_url_missing'] = 'Es ist kein Endpunkt für den Agenten eingetragen.';
$string['error_agent_url_invalid'] = 'Der eingetragene Endpunkt des Agenten ist keine gültige URL.';
$string['error_agent_url_insecure'] = 'Der eingetragene Endpunkt des Agenten nutzt einfaches HTTP, was nicht erlaubt ist.';
$string['error_connector_missing'] = 'Der Moodle-MCP-Konnektor ist nicht installiert, das Backend kann daher nicht zurückrufen.';
$string['error_service_not_configured'] = 'Es wurde kein Webservice für Rückrufe ausgewählt.';
$string['error_service_unavailable'] = 'Der Webservice für Rückrufe steht nicht zur Verfügung.';
$string['error_token_provision_failed'] = 'Für diese Person konnte kein Rückruf-Token ausgestellt werden.';
$string['error_message_empty'] = 'Bitte eine Nachricht eingeben.';
$string['error_message_too_long'] = 'Diese Nachricht ist zu lang. Erlaubt sind {$a} Zeichen.';
$string['error_rate_limited'] = 'Gerade zu viele Nachrichten. Bitte {$a} Sekunden warten.';
$string['error_daily_limit'] = 'Das Tageslimit an Nachrichten ist erreicht.';
$string['error_mode_unsupported'] = 'Das eingerichtete Backend kann in diesem Modus nicht antworten.';
$string['error_thread_not_found'] = 'Diese Unterhaltung wurde nicht gefunden.';

// Gemeinsame Oberflächentexte.
$string['source'] = 'Quelle';
$string['sources'] = 'Quellen';
$string['tokenlabel'] = 'Rückruf-Token der KI-Chat-Engine';
$string['send'] = 'Senden';
$string['messageplaceholder'] = 'Frage stellen …';
$string['clearconversation'] = 'Unterhaltung löschen';
$string['clearconfirm'] = 'Das Löschen entfernt die gesamte Unterhaltung dauerhaft. Das lässt sich nicht rückgängig machen.';
$string['cleared'] = 'Die Unterhaltung wurde gelöscht.';
$string['error_clearfailed'] = 'Die Unterhaltung konnte nicht gelöscht werden. Das Angezeigte hat sich nicht verändert. Bitte erneut versuchen.';
$string['error_clearnotallowed'] = 'Diese Unterhaltung wurde eingereicht und kann nicht mehr geleert werden.';
$string['online'] = 'Online';
$string['assistantname'] = 'KI-Assistent';
$string['thinking'] = 'Denkt nach …';
$string['event_token_provisioned'] = 'Rückruf-Token für den KI-Chat ausgestellt';

// Nachrichtenblase.
$string['groundedbadge'] = 'Quellenbasiert';
$string['groundedbadge_title'] = 'Diese Antwort zitiert Quellen aus der Wissensbasis.';
$string['ungroundedbadge'] = 'Allgemeine Antwort';
$string['ungroundedbadge_title'] = 'Diese Antwort basiert nicht auf der Wissensbasis — prüfen Sie wichtige Fakten nach.';
$string['mcpbadge_title'] = 'Diese Antwort hat Moodle-Werkzeuge genutzt.';
$string['senderyou'] = 'Sie';
$string['copy'] = 'Antwort kopieren';
$string['copied'] = 'Kopiert';
$string['retry'] = 'Erneut versuchen';
$string['failed'] = 'Die Antwort konnte nicht zugestellt werden.';
$string['toolong'] = 'Diese Nachricht ist zu lang.';
$string['newstarted'] = 'Neue Unterhaltung begonnen.';
$string['resumed'] = 'Unterhaltung fortgesetzt.';
$string['transcriptlabel'] = 'Gesprächsverlauf';
$string['expandlabel'] = 'Chat vergrößern';
$string['collapselabel'] = 'Chat verkleinern';
$string['error_backend_tool_missing'] = 'Das eingerichtete Backend bietet dieses Werkzeug nicht an.';

// Chat designs.
$string['settings_head_design'] = 'Design';
$string['settings_head_design_desc'] = 'Wie der Chat aussieht. Ein Design ist ein benanntes Wertebündel, auf das jede Chat-Oberfläche zeigen kann — so lesen sich Tutor-Block, Chat-Aktivität und Lernszenario als ein Produkt. Eine Oberfläche ohne gewähltes Design nutzt das eingebaute Aussehen.';
$string['setting_sitedesign'] = 'Design der Website';
$string['setting_sitedesign_desc'] = 'Das Design, das überall dort gilt, wo eine Instanz keines eigenes wählt.';
$string['design_none'] = 'Eingebautes Aussehen';
$string['design_inherit'] = 'Design der Website';
$string['design'] = 'Chat-Design';
$string['design_help'] = 'Das Aussehen, in dem dieser Chat dargestellt wird. „Design der Website" folgt der Einstellung der Website, sodass eine spätere Änderung auch hier ankommt.';
$string['contract_language_note_en'] = 'Dieser Vertragstext wird auf Englisch gepflegt und steht hier, wie er geschrieben ist.';
$string['topic_contract_ragserver_summary'] = 'Das Protokoll, das ein Retrieval-Backend sprechen muss, um fuer diese Engine zu '
    . 'antworten: Werkzeuge, Transport, Authentifizierung und der Lebenszyklus eines '
    . 'Gespraechs.';
$string['topic_contract_ragserver_title'] = 'RAG-Server: die Integrationsspezifikation';

// Wissensbasis -- aus welchen Kursen eine Flaeche antworten darf.
$string['scope_category'] = 'Bereich: {$a}';
$string['scope_course'] = '{$a->name} ({$a->shortname})';
$string['scope_notallowed'] = 'Sie können nur Kurse wählen, in denen Sie selbst unterrichten. Nicht erlaubt: {$a}';
$string['scope_summary'] = 'Von den hier gewählten Kursen sind {$a->indexed} von {$a->total} indexiert und damit durchsuchbar.';
$string['scope_summary_plain'] = 'Diese Auswahl umfasst {$a} Kurse.';
$string['scope_summary_none'] = 'Diese Auswahl umfasst derzeit keine Kurse.';
$string['setting_maxscopecourses'] = 'Kurs-IDs pro Anfrage';
$string['setting_maxscopecourses_desc'] = 'Wie viele Kurse eine Anfrage an das Backend nennen darf. <b>Das ist die Zahl des Backends.</b> Dessen Suchwerkzeug weist eine zu lange Liste ab; erhöhen Sie den Wert also erst, wenn das Backend seine eigene Grenze erhöht hat — ein zu hoher Wert weitet keine Suche, er lässt Anfragen scheitern. Löst eine Wissensbasis mehr Kurse auf als hier erlaubt, fällt eine Kursfläche auf den Kurs zurück, in dem sie steht, und eine Fläche ohne Kurs überlässt dem Backend die Auflösung der Einschreibungen.';
