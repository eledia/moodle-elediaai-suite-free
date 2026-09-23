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
 * German language strings for local_literag.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['confirm_declined'] = 'Alles klar, ich habe die Aktion verworfen. Es wurde nichts geändert.';
$string['confirm_no'] = 'Nein, abbrechen';
$string['confirm_yes'] = 'Ja, ausführen';
$string['chattoolname'] = 'Name des Chat-Tools';
$string['chattoolname_desc'] = 'Name des erforderlichen Chat-Tools.';
$string['chunk_overlap'] = 'Chunk-Überlappung (Zeichen)';
$string['chunk_overlap_desc'] = 'Überlappung zwischen aufeinanderfolgenden Chunks, damit Kontext über Abschnittsgrenzen erhalten bleibt.';
$string['chunk_size'] = 'Chunk-Größe (Zeichen)';
$string['chunk_size_desc'] = 'Zielgröße jedes gespeicherten Text-Chunks.';
$string['confirm_sent'] = 'Erledigt - Ihre Nachricht wurde gesendet.';
$string['context_chunks'] = 'Kontext-Chunks';
$string['context_chunks_desc'] = 'Wie viele Chunks dem LLM als fundierender Kontext übergeben werden (entspricht auch der Anzahl der Quellenhinweise).';
$string['conversation_retention_days'] = 'Aufbewahrung von Gesprächen (Tage)';
$string['conversation_retention_days_desc'] = 'Gespräche löschen, die seit so vielen Tagen nicht geändert wurden (0 = dauerhaft behalten).';
$string['deletetoolname'] = 'Name des Gespräch-löschen-Tools';
$string['deletetoolname_desc'] = 'Name des Tools zum Löschen einzelner Gespräche.';
$string['deleteusertoolname'] = 'Name des Nutzerdaten-löschen-Tools';
$string['deleteusertoolname_desc'] = 'Name des Tools zur vollständigen Löschung von Nutzerdaten.';
$string['emergency_disable'] = 'Endpunkte deaktivieren';
$string['emergency_disable_desc'] = 'Wenn aktiviert, liefern sowohl der Ingest- als auch der Tutor-Endpunkt eine Service-unavailable-Antwort.';
$string['enable_mcp_tools'] = 'Live-Moodle-Tools aktivieren';
$string['enable_mcp_tools_desc'] = 'Wenn aktiviert (und Retrieval aktiv ist), darf der Tutor die lesenden Tools von elediamcp im Namen der lernenden Person aufrufen, um Live-Daten abzurufen. Benötigt webservice_elediamcp.';
$string['enable_memory'] = 'Langzeitgedächtnis aktivieren';
$string['enable_memory_desc'] = 'Opt-in-Langzeitgedächtnis erlauben. Standardmäßig aus; jedes Lesen und Schreiben setzt eine Zustimmung pro Anfrage voraus.';
$string['enable_rerank'] = 'LLM-Reranking aktivieren';
$string['enable_rerank_desc'] = 'Optional das LLM bitten, Kandidaten vor der Antwort neu zu sortieren (zusätzliche Latenz und Kosten).';
$string['enable_write_tools'] = 'Nachrichtenversand erlauben';
$string['enable_write_tools_desc'] = 'Standardmäßig aus. Wenn aktiviert (und Live-Tools aktiv sind), darf der Tutor Moodle-Nachrichten im Namen der lernenden Person senden - aber erst nach Vorschau der Nachricht und ausdrücklicher Bestätigung im Chat. Es gelten die Berechtigungen der lernenden Person.';
$string['endpointinfo'] = 'Endpunkt-URLs';
$string['endpointinfo_desc'] = 'Konfigurieren Sie die vorhandenen Plugins so, dass sie hierhin zeigen:<ul>'
    . '<li><strong>local_elediaai_sources</strong> &rarr; als Ziel <em>LiteRAG</em> auswählen. '
    . 'Mehr ist nicht einzutragen: Die Route (<code>{$a->upsert}</code>) wird aus der Adresse dieser Website '
    . 'abgeleitet, und der Ingest-Schlüssel unten wird direkt von hier gelesen.</li>'
    . '<li><strong>block_elediaai_tutor</strong> &rarr; RAG-Server-URL: <code>{$a->mcp}</code></li></ul>';
$string['error_llm'] = 'Entschuldigung, der Tutor konnte gerade keine Antwort erzeugen. Bitte versuchen Sie es erneut.';
$string['guide_body'] = 'Wenn der Tutor aus dem Kurs zitiert statt frei zu antworten, hat er ein Aufnahmeziel gefragt. Welches, entscheidet eine Einstellung in den KI-Quellen — **es ist immer genau eines aktiv**:

- **Die externe Aufnahme-Schnittstelle** ist die Voreinstellung. Sie spricht über HTTP mit einem Dienst ausserhalb von Moodle; die Administration hinterlegt Basis-URL und Mandantenschlüssel.
- **LiteRAG** läuft auf dieser Moodle-Installation. Es gibt weder eine URL noch einen zweiten Schlüssel einzutragen: die Route ergibt sich aus dem `wwwroot`, und als Schlüssel dient LiteRAGs eigener Aufnahme-Schlüssel. Wer hier eine Einstellung sucht, sucht vergeblich — sie gehört in LiteRAG.

Was Sie hier einrichten, betrifft nur den zweiten Fall: Zugang und Betrieb von LiteRAG, und die Ansicht, ob die Aufnahme läuft und was angekommen ist.

Worauf zu achten ist: **das Umschalten des Ziels lässt jeden Kurs auseinanderlaufen.** Was im alten Ziel liegt, wandert nicht mit; die Kurse müssen neu aufgenommen werden. Zwei Ziele parallel sind nicht vorgesehen, deshalb ist die Einstellung eine Auswahl und keine Mehrfachauswahl.

Und unabhängig von der Wahl: das Ziel hält Kursinhalte vor. Bei der externen Schnittstelle liegen sie ausserhalb von Moodle, bei LiteRAG innerhalb. Welche der beiden Lagen Ihre Datenschutzbetrachtung verlangt, ist die eigentliche Entscheidung.';
$string['guide_summary'] = 'Die KI-Quellen schicken ausgewähltes Material an genau ein Ziel. LiteRAG ist eines davon: es läuft auf dieser Moodle-Installation.';
$string['guide_title'] = 'LiteRAG als Aufnahmeziel';
$string['head_connection'] = 'Verbindung';
$string['head_livetools'] = 'Live-Moodle-Tools';
$string['head_livetools_desc'] = 'Erlaubt dem Tutor, die lesenden moodle_*-Tools von webservice_elediamcp zu nutzen, um mit Echtzeitdaten der lernenden Person zu antworten (Kurse, Aufgaben, Bewertungen, Fristen, ...).';
$string['head_llm'] = 'LLM (OpenAI-kompatibel)';
$string['head_privacy'] = 'Gedächtnis, Logging & Aufbewahrung';
$string['head_retrieval'] = 'Retrieval & Chunking';
$string['head_tools'] = 'Tool-Namen';
$string['head_tools_desc'] = 'Diese Namen MÜSSEN mit den im Tutor-Block konfigurierten Tool-Namen übereinstimmen.';
$string['historytoolname'] = 'Name des Verlaufs-Tools';
$string['historytoolname_desc'] = 'Name des Tools für den Gesprächsverlauf.';
$string['ingest_api_key'] = 'Ingest-API-Schlüssel';
$string['ingest_api_key_desc'] = 'Gemeinsames Secret, das der Ingester im Header <code>X-API-Key</code> mitsenden muss. '
    . 'local_elediaai_sources liest diesen Wert von hier; es gibt keine zweite Stelle dafür.';
$string['literag:manage'] = 'eLeDia.ai LiteRAG-Backend-Einstellungen verwalten';
$string['llm_allow_private'] = 'Private/Loopback-LLM-Hosts erlauben';
$string['llm_allow_private_desc'] = 'Moodle-cURL-Sicherheit umgehen, um ein selbst gehostetes LiteLLM unter einer privaten oder Loopback-Adresse zu erreichen. Nur für vertrauenswürdige lokale/Docker-LLM-Endpunkte aktivieren; dies deaktiviert Moodles SSRF-Schutz für die LLM-URL.';
$string['llm_api_key'] = 'API-Schlüssel';
$string['llm_api_key_desc'] = 'Bearer-API-Schlüssel für den LLM-Endpunkt.';
$string['llm_base_url'] = 'API-Basis-URL';
$string['llm_base_url_desc'] = 'OpenAI-kompatible Basis-URL, z. B. <code>https://api.openai.com/v1</code> oder ein LiteLLM-Proxy.';
$string['llm_max_tokens'] = 'Maximale Ausgabe-Token';
$string['llm_max_tokens_desc'] = 'Maximale Anzahl Token, die pro Antwort erzeugt werden.';
$string['llm_model'] = 'Modell';
$string['llm_model_desc'] = 'Modell-ID für Chat-Completions.';
$string['llm_temperature'] = 'Temperatur';
$string['llm_temperature_desc'] = 'Sampling-Temperatur (z. B. 0.2).';
$string['llm_timeout'] = 'Anfrage-Timeout (Sekunden)';
$string['llm_timeout_desc'] = 'Unterhalb des Tutor-Block-Timeouts halten (Standard 30 s), damit Antworten rechtzeitig zurückkommen.';
$string['log_verbosity'] = 'Protokollierungsdetailgrad';
$string['log_verbosity_desc'] = 'Wie viele Retrieval-Details im Query-Log gespeichert werden.';
$string['loglevel_counts'] = 'Zählwerte (ohne Fragetext)';
$string['loglevel_errors'] = 'Nur Fehler';
$string['loglevel_full'] = 'Vollständig (Fragetext speichern)';
$string['max_tool_iterations'] = 'Maximale Tool-Runden';
$string['max_tool_iterations_desc'] = 'Maximale Anzahl Tool-Call-Runden, die der Tutor pro Antwort ausführen darf, bevor er antworten muss.';
$string['mcp_timeout'] = 'Tool-Call-Timeout (Sekunden)';
$string['mcp_timeout_desc'] = 'Timeout pro Anfrage beim Aufruf eines elediamcp-Tools.';
$string['memoryoptintoolname'] = 'Name des Gedächtnis-Zustimmungs-Tools';
$string['memoryoptintoolname_desc'] = 'Name des Tools für die Zustimmung zum Langzeitgedächtnis.';
$string['nav_label'] = 'eLeDia.ai LiteRAG-Bereiche';
$string['nav_settings'] = 'Einstellungen';
$string['pdftotext_path'] = 'pdftotext-Pfad (optional)';
$string['pdftotext_path_desc'] = 'Optional. PDFs sind bereits über den mitgelieferten PHP-Parser durchsuchbar; setzen Sie einen absoluten Pfad zu einem nativen <code>pdftotext</code> (Poppler) für genauere Extraktion oder lassen Sie das Feld leer, um den mitgelieferten Parser zu verwenden.';
$string['pluginname'] = 'eLeDia.ai LiteRAG';
$string['privacy:conversations'] = 'Gespräche';
$string['privacy:memory'] = 'Langzeitgedächtnis';
$string['privacy:metadata:llm_provider'] = 'Fragen und abgerufener Kontext werden an ein externes OpenAI-kompatibles LLM gesendet, um Antworten zu erstellen.';
$string['privacy:metadata:llm_provider:context'] = 'Abgerufene Kursinhaltsauszüge, die dem LLM als fundierender Kontext gesendet werden.';
$string['privacy:metadata:llm_provider:toolresults'] = 'Ergebnisse von Live-Moodle-Tools, etwa Bewertungen, Kalendereinträge, Fortschrittsinformationen und Nachrichten, können an das LLM gesendet werden, wenn Live-Tools aktiviert sind.';
$string['privacy:metadata:llm_provider:usermessage'] = 'Die Frage der lernenden Person, die an das LLM gesendet wird.';
$string['privacy:metadata:llm_provider:usersummary'] = 'Eine Zusammenfassung zur lernenden Person oder Opt-in-Gedächtnis-Fakten können an das LLM gesendet werden, wenn diese Funktionen aktiviert sind.';
$string['privacy:metadata:local_literag_conversations'] = 'Tutor-Gespräche, die diesem Backend gehören.';
$string['privacy:metadata:local_literag_conversations:courseid'] = 'Der Kurs, dem das Gespräch zugeordnet ist.';
$string['privacy:metadata:local_literag_conversations:timecreated'] = 'Wann das Gespräch erstellt wurde.';
$string['privacy:metadata:local_literag_conversations:userid'] = 'Die Person, der das Gespräch gehört.';
$string['privacy:metadata:local_literag_memory'] = 'Opt-in-Langzeitgedächtnis-Fakten über die Person.';
$string['privacy:metadata:local_literag_memory:mvalue'] = 'Der gespeicherte Gedächtnis-Fakt.';
$string['privacy:metadata:local_literag_memory:timecreated'] = 'Wann der Gedächtnis-Fakt gespeichert wurde.';
$string['privacy:metadata:local_literag_memory:userid'] = 'Die Person, zu der der Gedächtnis-Fakt gehört.';
$string['privacy:metadata:local_literag_messages'] = 'Nachrichten innerhalb von Tutor-Gesprächen.';
$string['privacy:metadata:local_literag_messages:content'] = 'Der Nachrichtentext.';
$string['privacy:metadata:local_literag_messages:role'] = 'Ob die Nachricht von der nutzenden Person oder vom Assistenten stammt.';
$string['privacy:metadata:local_literag_messages:timecreated'] = 'Wann die Nachricht erstellt wurde.';
$string['privacy:metadata:local_literag_messages:userid'] = 'Die Person, der die Nachricht gehört.';
$string['privacy:metadata:local_literag_query_log'] = 'Betriebsprotokoll der Retrieval-Anfragen.';
$string['privacy:metadata:local_literag_query_log:querytext'] = 'Der Fragetext (nur bei vollständigem Logging-Umfang).';
$string['privacy:metadata:local_literag_query_log:timecreated'] = 'Wann die Anfrage gestellt wurde.';
$string['privacy:metadata:local_literag_query_log:userid'] = 'Die Person, die die Frage gestellt hat.';
$string['privacy:querylogs'] = 'Query-Logs';
$string['query_log_retention_days'] = 'Aufbewahrung des Query-Logs (Tage)';
$string['query_log_retention_days_desc'] = 'Query-Log-Zeilen löschen, die älter als diese Anzahl Tage sind (0 = dauerhaft behalten).';
$string['reclustertoolname'] = 'Name des Recluster-Tools';
$string['reclustertoolname_desc'] = 'Name des nächtlichen Tools zur Neu-Clusterung von Fragen.';
$string['rerank_model'] = 'Rerank-Modell';
$string['rerank_model_desc'] = 'Modell für das Reranking; leer lassen, um das Antwortmodell wiederzuverwenden.';
$string['rate_limit_per_day'] = 'Tutor-Anfragen pro Person und Tag';
$string['rate_limit_per_day_desc'] = 'Harte Tagesobergrenze für kostenpflichtige Tutor-Anfragen einer Person. '
    . 'Der Wert muss mindestens 1 sein.';
$string['rate_limit_per_minute'] = 'Tutor-Anfragen pro Person und Minute';
$string['rate_limit_per_minute_desc'] = 'Burst-Limit für kostenpflichtige Tutor-Anfragen einer Person. '
    . 'Der Wert muss mindestens 1 sein.';
$string['retrieval_candidates'] = 'Kandidaten-Chunks';
$string['retrieval_candidates_desc'] = 'Wie viele Chunks die Volltextsuche vor der Berechtigungsfilterung zurückliefert.';
$string['settings_hub_desc'] = 'Wählen Sie einen eLeDia.ai LiteRAG-Einstellungsbereich aus.';
$string['settings_section_connection_desc'] = 'Endpunkt-URLs, gemeinsame Secrets und Not-Aus.';
$string['settings_section_livetools_desc'] = 'Live-Zugriff auf Moodle-Tools über eLeDia MCP.';
$string['settings_section_llm_desc'] = 'OpenAI-kompatibler Modell-Endpunkt und Generierungslimits.';
$string['settings_section_privacy_desc'] = 'Langzeitgedächtnis, Logging und Aufbewahrung.';
$string['settings_section_retrieval_desc'] = 'Chunking, Retrieval-Umfang und Reranking.';
$string['settings_section_tools_desc'] = 'MCP-Tool-Namen, die dem Tutor-Block bereitgestellt werden.';
$string['shell_help_label'] = 'Hilfe zu eLeDia.ai LiteRAG';
$string['shell_subtitle'] = 'RAG-Backend-Einstellungen für Ingest, Retrieval und Tutor-Antworten.';
$string['suite_feature_desc'] = 'Eines der Ziele, in die Kursmaterial aufgenommen werden kann — dieses läuft in Moodle selbst, statt bei einem externen Dienst.';
$string['suite_feature_detail'] = 'Wenn der Tutor den Kurs zitiert statt zu erfinden, hat er hier gefragt. LiteRAG nimmt auf, was in den KI-Quellen ausgewählt wurde, hält es durchsuchbar und liefert die Passagen, die zu einer Frage passen. Administrierende richten das Backend ein, prüfen seinen Zustand und sehen, was aufgenommen wurde.';
$string['suite_feature_key_1'] = 'Nimmt das Material auf, das in den KI-Quellen ausgewählt wurde.';
$string['suite_feature_key_2'] = 'Findet die Passagen zu einer Frage, damit Antworten den Kurs belegen.';
$string['suite_feature_key_3'] = 'Administrierende prüfen Anbindung und Bestand des Backends.';
$string['suite_feature_name'] = 'LiteRAG';
$string['task_prune_logs'] = 'eLeDia.ai LiteRAG-Logs und abgelaufene Gespräche bereinigen';
$string['transport_auth_token'] = 'Tutor-Transport-Token (optional)';
$string['transport_auth_token_desc'] = 'Optionales Bearer-Token, das der Tutor-Block mitsenden muss (Defense in Depth). '
    . 'Leer lassen, um jede Anfrage zu akzeptieren; jeder Chat enthält bereits ein verifizierbares Token pro Nutzer/in.';
$string['untitledsource'] = 'Unbenannte Quelle';
