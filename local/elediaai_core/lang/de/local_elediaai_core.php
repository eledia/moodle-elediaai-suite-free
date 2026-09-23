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
 * Deutsche Sprachdatei für local_elediaai_core.
 *
 * Keys sind alphabetisch sortiert.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['dashboard_audience_all'] = 'Alle';
$string['dashboard_kind_incourse'] = 'In deinen Kursen verfügbar';
$string['dashboard_kind_incourse_intro'] = 'Das sind keine Orte, die man aufsucht — sie erscheinen im Kurs, dort wo unterrichtet wird. Jede Kachel sagt, wo man sie findet.';
$string['dashboard_kind_page'] = 'Werkzeuge, die du öffnest';
$string['dashboard_kind_page_intro'] = 'Jedes davon hat eine eigene Seite. Die Kachel bringt dich direkt dorthin.';
$string['dashboard_matchcount'] = '{$a} Funktionen passen.';
$string['dashboard_nomatch'] = 'Dazu passt hier nichts. Versuchen Sie es mit weniger Wörtern oder einer anderen Gruppe.';
$string['dashboard_search_button'] = 'Suchen';
$string['dashboard_search_label'] = 'KI-Funktionen durchsuchen';
$string['dashboard_search_placeholder'] = 'Was möchten Sie tun?';
$string['dashboard_showall'] = 'Wieder alles zeigen';
$string['error_extract_unsupported'] = 'Aus dem Aktivitätstyp „{$a}" kann kein Text extrahiert werden. Unterstützte Typen: Textseite, Buch, Datei, Verzeichnis, Lektion.';
$string['error_premiumrequired'] = 'Diese Funktion ist nicht freigeschaltet. Sie gehört zum Premium-Umfang der KI-Suite; eine Administratorin oder ein Administrator gibt sie in den Einstellungen des Premium-Zusatzes frei.';
$string['error_quota_token_exceeded'] = 'Sie haben Ihr KI-Tokenlimit von {$a->limit} Tokens für dieses Zeitfenster ({$a->window}) erreicht. Bitte versuchen Sie es später erneut.';
$string['error_quota_unavailable'] = 'Die KI-Token-Quota konnte gerade nicht geprüft werden. Bitte versuchen Sie es später erneut.';
$string['health_heading'] = 'Wie es der Suite geht';
$string['health_intro'] = 'Jede Zeile hier kommt von dem Plugin, um das es geht. Nichts davon ist stellvertretend geraten.';
$string['health_none'] = 'Kein Plugin meldet seinen Zustand. Das ist kein Fehler — der Bericht ist freiwillig —, aber diese Seite kann Ihnen dann nichts sagen.';
$string['health_provider_failed'] = 'Bericht von {$a} fehlgeschlagen';
$string['health_status_disabled'] = 'Abgeschaltet';
$string['health_status_error'] = 'Gestört';
$string['health_status_ok'] = 'Läuft';
$string['health_status_unconfigured'] = 'Nicht eingerichtet';
$string['health_status_warning'] = 'Beachten';
$string['health_summary_attention'] = '{$a} Sache(n) brauchen Aufmerksamkeit.';
$string['health_summary_quiet'] = 'Nichts braucht Aufmerksamkeit. {$a} Prüfung(en) gemeldet.';
$string['nav_aria_label'] = 'KI-Suite Bereiche';
$string['nav_audit'] = 'Audit';
$string['nav_help'] = 'Hilfe';
$string['nav_infra_health'] = 'Zustand';
$string['nav_infra_literag'] = 'LiteRAG';
$string['nav_infra_mcp'] = 'MCP';
$string['nav_infra_sources'] = 'KI-Quellen';
$string['nav_infra_tutor'] = 'Tutor einrichten';
$string['nav_overview'] = 'Übersicht';
$string['premium_required_heading'] = 'Nicht freigeschaltet';
$string['shell_aitutor_label'] = 'eLeDia.ai Tutor';
$string['shell_help_label'] = 'Hilfe — KI-Suite';
$string['shell_name'] = 'eLeDia.ai | KI-Suite';
$string['help_heading'] = 'Hilfe';
$string['help_intro'] = 'Lesen Sie das KI-Suite-Handbuch, ohne die KI-Suite-Navigation zu verlassen.';
$string['audit_unavailable_heading'] = 'KI-Audit nicht verfügbar';
$string['audit_unavailable_message'] = 'Der KI-Audit-Bericht baut auf Moodles Kern-KI-Nutzungsregister auf, das mit Moodle 5.0 eingeführt wurde. Diese Website nutzt eine ältere Moodle-Version, daher ist der Audit-Bericht nicht verfügbar. Alle anderen Funktionen der KI-Suite bleiben uneingeschränkt nutzbar.';
$string['audit_unavailable_title'] = 'KI-Audit';
$string['audit_access'] = 'Wer darf das Audit sehen';
$string['audit_access_adminonly'] = 'Nur Website-Administrator:innen';
$string['audit_access_corecap'] = 'Nutzer:innen mit Moodle-KI-Nutzungsbericht-Recht';
$string['audit_access_teacherowncourses'] = 'Lehrkräfte für eigene Kurse plus Nutzer:innen mit KI-Nutzungsbericht-Recht';
$string['audit_access_desc'] = 'Steuert, wer das Audit öffnen darf. Im Lehrkräfte-Modus sehen Lehrkräfte nur Einträge aus Kursen, in denen sie als Lehrkraft eingeschrieben sind; wer moodle/ai:viewaiusagereport hat, sieht weiterhin alles.';
$string['audit_action_explain_text'] = 'Text erklären';
$string['audit_action_generate_image'] = 'Bild generieren';
$string['audit_action_generate_text'] = 'Text generieren';
$string['audit_action_summarise_text'] = 'Text zusammenfassen';
$string['audit_actor_anonymized_role'] = '{$a}';
$string['audit_actor_anonymized_unknown'] = 'Rolle nicht verfügbar';
$string['audit_actor_not_recorded'] = 'Nicht erfasst';
$string['audit_actor_not_recorded_help'] = 'Diese Aktion wurde ohne Nutzerkennung aufgezeichnet. Prompt, Antwort, Ort und Zeit stehen fest, wer gefragt hat nicht. Chat-Turns werden so erfasst.';
$string['audit_anonymize_users'] = 'Nutzer:innen im Audit anonymisieren';
$string['audit_anonymize_users_desc'] = 'Ersetzt echte Namen in der Audit-Tabelle durch eine Rollen-/Kontextanzeige. Moodle Core speichert die ursprüngliche Nutzer-ID weiterhin in seinen Core-KI-Audit-Tabellen.';
$string['audit_col_actor'] = 'Akteur';
$string['audit_col_action'] = 'Aktion';
$string['audit_col_context'] = 'Inhaltsname';
$string['audit_col_error'] = 'Fehlermeldung';
$string['audit_col_model'] = 'Modell';
$string['audit_col_prompt'] = 'Prompt';
$string['audit_col_provider'] = 'Dienst';
$string['audit_col_provider_model'] = 'Dienst / Modell';
$string['audit_col_response'] = 'Antwort';
$string['audit_col_success'] = 'OK';
$string['audit_col_status'] = 'Status';
$string['audit_col_tokens'] = 'Tokens';
$string['audit_col_tokens_help'] = 'Prompt-Tokens / Antwort-Tokens';
$string['audit_component_empty'] = 'In diesem Zeitraum wurde keine KI-Anfrage verbucht.';
$string['audit_component_eyebrow'] = 'Verbrauch';
$string['audit_component_intro'] = 'Welches Feature das Guthaben tatsächlich verbraucht, über die letzten {$a} Tage. Gezählt aus dem eigenen Turn-Protokoll der Suite, eine Zeile je Anfrage — nicht aus dem Guthaben-Hauptbuch, dessen Komponenten-Spalte nur festhält, welches Feature zuletzt gebucht hat.';
$string['audit_component_note'] = 'Begrenzt durch die Aufbewahrung der Fragen und Antworten; eine kürzere Frist verkürzt auch diese Liste.';
$string['audit_component_requests'] = '{$a} Anfragen';
$string['audit_component_title'] = 'Welches Feature benutzt wird';
$string['audit_component_unknown'] = 'Nicht zugeordnet';
$string['audit_context_unknown'] = 'Unbekannter Kontext';
$string['audit_heading'] = 'KI-Audit';
$string['audit_metric_failed'] = 'Fehlgeschlagen';
$string['audit_metric_hidden'] = 'Ausgeblendet';
$string['audit_metric_requests'] = 'Anfragen';
$string['audit_metric_support'] = 'Zusammenfassen und Erklären';
$string['audit_metric_tokens'] = 'Tokens';
$string['audit_overview_intro'] = 'Vier Ansichten, je eine Frage: was die Lernenden gefragt haben, was die KI getan hat, jede einzelne Anfrage, und wer das alles lesen darf.';
$string['audit_preview_close'] = 'Schließen';
$string['audit_preview_open'] = 'Volltext anzeigen';
$string['audit_preview_open_error'] = 'Fehlermeldung anzeigen';
$string['audit_preview_title_error'] = 'Fehlermeldung';
$string['audit_preview_title_prompt'] = 'Prompt';
$string['audit_preview_title_response'] = 'KI-Antwort';
$string['audit_quick_all'] = 'Alle';
$string['audit_quick_failed'] = 'Nur Fehler';
$string['audit_quick_participants'] = 'Nur Teilnehmende';
$string['audit_report_filename'] = 'elediaai-ki-audit';
$string['audit_report_name'] = 'eLeDia.ai Audit';
$string['audit_settings_heading'] = 'KI-Audit';
$string['audit_settings_heading_desc'] = 'Diese Einstellungen steuern, was das Audit der KI-Suite anzeigt. Moodle Core kann seine eigenen KI-Auditdaten unabhängig davon weiterhin speichern.';
$string['audit_show_error'] = 'Fehlerdetails anzeigen';
$string['audit_show_error_desc'] = 'Erlaubt, bei fehlgeschlagenen Einträgen die vollständige Fehlermeldung zu öffnen.';
$string['audit_show_prompt'] = 'Prompts anzeigen';
$string['audit_show_prompt_desc'] = 'Zeigt ein Prompt-Vorschau-Icon in der Audit-Tabelle. Prompts können personenbezogene Daten enthalten.';
$string['audit_show_response'] = 'KI-Antworten anzeigen';
$string['audit_show_response_desc'] = 'Zeigt ein Antwort-Vorschau-Icon in der Audit-Tabelle.';
$string['audit_show_tokens'] = 'Token-Werte anzeigen';
$string['audit_show_tokens_desc'] = 'Zeigt Prompt-/Antwort-Tokens in Übersicht und Tabelle.';
$string['audit_success_no'] = 'Fehlgeschlagen';
$string['audit_success_yes'] = 'Erfolgreich';
$string['dashboard_empty'] = 'Es sind noch keine KI-Funktionen registriert.';
$string['dashboard_heading'] = 'eLeDia.ai | KI-Suite';
$string['dashboard_subtitle'] = 'Gebündelte KI-Funktionen, die Sie zentral ein- und ausschalten können.';
$string['feature_backtosuite'] = 'Zurück zur KI-Suite';
$string['feature_benefit_title'] = 'Nutzen und Einordnung';
$string['feature_launch_desc'] = 'Öffnet das verfügbare Werkzeug und führt direkt in den Feature-Workflow.';
$string['feature_launch_title'] = 'Start';
$string['feature_install'] = 'Plugin installieren';
$string['feature_install_desc'] = 'Öffnet die Plugin-Quelle. Nach Installation in dieser Moodle-Instanz und Cache-Purge ersetzt die Live-Kachel diese Roadmap-Karte automatisch.';
$string['feature_install_title'] = 'Noch nicht installiert';
$string['feature_audience_admin'] = 'Admin-Tools';
$string['feature_audience_designer'] = 'Kursgestaltung';
$string['feature_audience_legend'] = 'Werkzeuggruppen';
$string['feature_audience_student'] = 'Studierende';
$string['feature_audience_teacher'] = 'Lehrende';
$string['feature_keyfeatures_title'] = 'Wesentliche Funktionen';
$string['feature_notfound'] = 'Diese KI-Funktion ist nicht verfügbar.';
$string['feature_open'] = 'Funktion öffnen';
$string['feature_origin_eledia_note'] = 'Eigenentwicklung innerhalb der eLeDia.ai Suite. Wenn externe Teilbibliotheken genutzt werden, bleiben deren Lizenzen und Hinweise im jeweiligen Plugin erhalten.';
$string['feature_origin_eledia_title'] = 'eLeDia.ai Suite';
$string['feature_origin_intro'] = 'Diese Angaben machen sichtbar, ob ein Tool eine eLeDia-Eigenentwicklung ist oder auf einem übernommenen bzw. geforkten Open-Source-Plugin aufsetzt.';
$string['feature_origin_title'] = 'Herkunft & Credits';
$string['feature_status_coming'] = 'In Vorbereitung';
$string['feature_status_install'] = 'Installierbar';
$string['feature_status_ready'] = 'Verfügbar';
$string['feature_audit_desc'] = 'Vier Ansichten auf die KI dieser Website: was die Lernenden gefragt haben, was die KI getan hat, jede einzelne Anfrage, und wer das alles lesen darf.';
$string['feature_audit_detail'] = 'Das Audit führt drei getrennte Protokolle, geschnitten danach, wie lange sie leben dürfen und ob sie eine Person tragen. Das Handlungsprotokoll nennt die Person und bleibt am längsten, weil es festhält, in wessen Auftrag die KI gehandelt hat. Fragen und Antworten enthalten freien Text, keine Person und bekommen die kürzeste Frist — an die Stelle des Fragenden tritt ein gesalzenes Pseudonym, und genau das macht die Kursansicht möglich, ohne jemanden zu benennen. Der Herkunftsnachweis enthält nur einen Hash und überlebt den Inhalt, den er belegt. Jeder Aufruf der Suite läuft durch einen einzigen Engpass für das Guthaben, deshalb kann der Bericht sagen, welches Feature was verbraucht hat — eine Frage, die Moodles eigenes Aktionsregister nicht beantworten kann, weil es keine Komponente führt. Was es nicht abdeckt: der Übersetzungsfilter spricht DeepL auf einem eigenen Guthabentopf an, und Indexierung und Retrieval haben keinen eigenen LLM-Aufruf, der zu zählen wäre. Daneben steht Moodles eigenes Register, ungefiltert: es hält jede KI-Aktion der Website fest, die der Suite eingeschlossen, und führt keine Komponente, an der sich das unterscheiden ließe. Alle drei Fristen sind einstellbar.';
$string['feature_audit_key_1'] = 'Was die Lernenden gefragt haben — je Kurs, welche Themen das Material nicht beantworten konnte und wo es vorhanden ist und trotzdem nicht trägt. Ohne Namen.';
$string['feature_audit_key_2'] = 'Was die KI getan hat — jeder Werkzeugaufruf, der etwas verändert hat, und die Person, in deren Auftrag er lief.';
$string['feature_audit_key_3'] = 'Jede KI-Anfrage — Frage, Antwort, Provider, Modell, Status und Kosten, soweit die Einstellungen es erlauben.';
$string['feature_audit_name'] = 'KI-Audit';
$string['feature_coursegen_desc'] = 'Kursentwürfe aus einem Thema erzeugen: Abschnitte, Einstiege und Aktivitätsideen.';
$string['feature_coursegen_detail'] = 'Kurse soll Lehrende beim schnellen Strukturieren neuer Lernangebote unterstützen. Aus einem Thema entsteht ein erster didaktischer Entwurf mit Abschnitten, Einführungen und passenden Aktivitätsideen. Der Nutzen liegt vor allem in der Konzeptphase: weniger leere Seite, schnellerer Start, bessere Vergleichbarkeit von Kursentwürfen.';
$string['feature_coursegen_key_1'] = 'Erzeugt aus einem Thema einen ersten Kursentwurf mit Abschnitten und Einstiegstexten.';
$string['feature_coursegen_key_2'] = 'Schlägt Aktivitätsideen pro Abschnitt vor, damit Lehrende einen Entwurf verfeinern statt bei null starten.';
$string['feature_coursegen_key_3'] = 'Als Konzeptwerkzeug gedacht; Veröffentlichung und Kursfreigabe bleiben bei Lehrenden bzw. Admins.';
$string['feature_coursegen_name'] = 'KI Kursautor';
$string['feature_questiongenerator_name'] = 'KI Fragen';
$string['feature_settings'] = 'Einstellungen';
$string['feature_translate_desc'] = 'Kursinhalte in andere Sprachen übertragen und Review, Glossar und Import zentral steuern.';
$string['feature_translate_detail'] = 'Übersetzen beschleunigt mehrsprachige Kursangebote. Bestehende Moodle-Inhalte können markiert, übersetzt, geprüft, importiert und über zentrale Workflows gepflegt werden, mit DeepL-Unterstützung, wenn konfiguriert. Strategisch hilft das bei Internationalisierung, Rollouts in mehreren Zielgruppen und schneller Aktualisierung mehrsprachiger Lernmaterialien.';
$string['feature_translate_install_desc'] = 'Installieren Sie eledia Translate, um Kursinhalte zu uebersetzen, Aenderungen zu pruefen, Glossare zu pflegen und Import-Workflows zu nutzen.';
$string['feature_translate_key_1'] = 'Markiert übersetzbare Moodle-Inhalte mit stabilen Spans und Zielsprachen-Metadaten.';
$string['feature_translate_key_2'] = 'Unterstützt Übersetzungsprüfung, Import-/Export-Workflows, Glossare und Provider-Übersetzung wie DeepL.';
$string['feature_translate_key_3'] = 'Kann Zielsprachen pro Kurs begrenzen und trotzdem zentrale Übersetzungsworkflows nutzen.';
$string['feature_translate_name'] = 'KI Übersetzung';
$string['feature_tutor_desc'] = 'Persönlicher KI-Tutor für kontextbezogene Lernbegleitung. In Vorbereitung.';
$string['feature_tutor_detail'] = 'Tutor soll Lernende stärker individuell begleiten als ein allgemeiner Chat. Die Funktion beantwortet Fragen im Kurskontext, unterstützt bei Verständnisproblemen und kann Lernpfade personalisierter machen. Didaktisch ist das interessant für selbstgesteuerte Lernphasen, in denen Lehrende nicht permanent verfügbar sind.';
$string['feature_tutor_install_desc'] = 'Installieren Sie eLeDia.ai | Tutor, um kontextbezogene Lernbegleitung mit kursbewusstem Chat und gefuehrter Unterstuetzung zu nutzen.';
$string['feature_tutor_key_1'] = 'Stellt einen lernendenseitigen KI-Tutor-Block mit kontextbezogener Gesprächsunterstützung bereit.';
$string['feature_tutor_key_2'] = 'Unterstützt konfigurierbare Persona, Branding, Einwilligung und Long-Term-Memory-Optionen.';
$string['feature_tutor_key_3'] = 'Kann an externe RAG-/MCP-Dienste anbinden, wenn diese separat installierten Plugins verfügbar sind.';
$string['feature_tutor_name'] = 'KI-Tutor';
$string['feature_tutorpremium_name'] = 'KI Tutor Premium';
$string['launcher_comingsoon'] = 'In Vorbereitung';
$string['launcher_subtitle'] = 'Gebündelte KI-Werkzeuge, einen Klick entfernt.';
$string['launcher_title'] = 'KI-Suite';
$string['pluginname'] = 'eLeDia.ai | KI-Suite';
$string['privacy:metadata'] = 'Die KI-Suite speichert kompakte Tokenzähler pro Nutzer:in zur Durchsetzung von Nutzungslimits. Einzelne KI-Funktionen und das Moodle-Core-KI-Logging deklarieren weitere Datenschutzdaten separat.';
$string['privacy:metadata:core_ai'] = 'Das KI-Audit liest seine Basisdaten direkt aus dem KI-Subsystem des Moodle-Core (core_ai), das jede KI-Aktion mit Prompt, Antwort und Token-Verbrauch protokolliert. Die Verantwortung für Export und Löschung dieser Daten liegt beim Moodle-Core; dieses Plugin zeigt sie nur an und speichert lediglich seine eigenen, site-weiten Audit-Anzeigeeinstellungen, die keine personenbezogenen Daten enthalten.';
$string['privacy:metadata:local_elediaai_core_turn'] = 'Eine Zeile je KI-Turn der Suite: was gefragt, was geantwortet wurde, welches Feature gefragt hat und worauf die Antwort gestützt war. Die Zeile führt keine Nutzerkennung. Sie führt ein gesalzenes Pseudonym der fragenden Person, das keine Oberfläche anzeigt oder auflöst — damit der Kursbericht mehrere Fragende von einer unterscheiden kann und eine Löschanfrage beantwortbar bleibt. Die Daten sind damit pseudonym, nicht anonym.';
$string['privacy:metadata:local_elediaai_core_turn:askerkey'] = 'Ein gesalzener Hash der fragenden Person, nie angezeigt und nie aufgelöst. Er existiert, damit diese Daten auf Anfrage exportiert und gelöscht werden können.';
$string['privacy:metadata:local_elediaai_core_turn:component'] = 'Das Suite-Feature, das die Anfrage gestellt hat.';
$string['privacy:metadata:local_elediaai_core_turn:courseid'] = 'Der Kurs, in dem die Anfrage gestellt wurde, sofern in einem.';
$string['privacy:metadata:local_elediaai_core_turn:origin'] = 'Ob die Antwort aus indexiertem Kursmaterial, aus dem Modell selbst oder aus Moodle-Daten über ein Werkzeug kam.';
$string['privacy:metadata:local_elediaai_core_turn:prompt'] = 'Die Frage im Wortlaut. Freier Text kann personenbezogene Daten enthalten, was auch immer das Schema sagt — deshalb hat diese Tabelle die kürzeste der drei Aufbewahrungsfristen.';
$string['privacy:metadata:local_elediaai_core_turn:response'] = 'Die Antwort im Wortlaut.';
$string['privacy:metadata:local_elediaai_core_turn:timecreated'] = 'Wann der Turn stattfand.';
$string['privacy:metadata:local_elediaai_core_turn:topic'] = 'Das kanonische Thema, das das Backend der Frage zugeordnet hat.';
$string['privacy:path:turns'] = 'KI-Fragen und -Antworten';
$string['privacy:metadata:local_elediaai_core_usage'] = 'Tokenzähler pro Nutzer:in zur Durchsetzung stündlicher, täglicher und monatlicher KI-Tokenlimits.';
$string['privacy:metadata:local_elediaai_core_usage:completiontokens'] = 'Anzahl der Antwort-Tokens im Quota-Zeitfenster.';
$string['privacy:metadata:local_elediaai_core_usage:component'] = 'Die eLeDia.ai-Komponente, die den Quota-Eintrag zuletzt aktualisiert hat.';
$string['privacy:metadata:local_elediaai_core_usage:prompttokens'] = 'Anzahl der Prompt-Tokens im Quota-Zeitfenster.';
$string['privacy:metadata:local_elediaai_core_usage:requestcount'] = 'Anzahl erfolgreicher KI-Anfragen im Quota-Zeitfenster.';
$string['privacy:metadata:local_elediaai_core_usage:reservedtokens'] = 'Tokens, die für laufende KI-Anfragen im Quota-Zeitfenster reserviert sind.';
$string['privacy:metadata:local_elediaai_core_usage:rolebucket'] = 'Ob die Nutzung gegen das Student- oder Teacher-Limit gezählt wurde.';
$string['privacy:metadata:local_elediaai_core_usage:totaltokens'] = 'Summe aus Prompt- und Antwort-Tokens im Quota-Zeitfenster.';
$string['privacy:metadata:local_elediaai_core_usage:userid'] = 'Die Nutzer-ID, zu der der Quota-Zähler gehört.';
$string['privacy:metadata:local_elediaai_core_usage:windowstart'] = 'Startzeitpunkt des Quota-Zeitfensters.';
$string['privacy:metadata:local_elediaai_core_usage:windowtype'] = 'Typ des Quota-Zeitfensters: Stunde, Tag oder Monat.';
$string['quota_completion_buffer'] = 'Reservierte Completion-Tokens pro Anfrage';
$string['quota_completion_buffer_desc'] = 'Completion-Tokens, die zusätzlich zur geschätzten Prompt-Tokenzahl vor einer externen KI-Anfrage reserviert werden. Jede Anfrage mit aktivem Limit benötigt daher Prompt-Schätzung plus diesen Puffer als freien Spielraum. 0 reserviert nur die Prompt-Schätzung.';
$string['quota_image_cost_256'] = 'Bildguthaben bis 256px';
$string['quota_image_cost_512'] = 'Bildguthaben bis 512px';
$string['quota_image_cost_1024'] = 'Bildguthaben bis 1024px';
$string['quota_image_cost_desc'] = 'Token-äquivalentes Guthaben pro generiertem Bild. Es nutzt dieselbe Quota-Währung wie Text-Tokens. 0 verwendet den eingebauten Standardwert.';
$string['quota_limit_desc'] = 'Maximale Text-Tokens und Bildguthaben in Token-Äquivalenz. 0 bedeutet unbegrenzt.';
$string['quota_settings_desc'] = 'Limits werden serverseitig geprüft, bevor eLeDia.ai eine externe KI-Anfrage sendet. Textschätzungen und Bildguthaben werden pro Nutzer:in atomar in Stunden-, Tages- und Monatsfenstern gebucht, sodass parallele Anfragen das Limit nicht überziehen können; nach der Antwort wird auf die tatsächliche Nutzung korrigiert. 0 bedeutet unbegrenzt.';
$string['quota_settings_heading'] = 'KI-Guthabenlimits';
$string['quota_student_day'] = 'Student-Tokenlimit pro Tag';
$string['quota_student_hour'] = 'Student-Tokenlimit pro Stunde';
$string['quota_student_month'] = 'Student-Tokenlimit pro Monat';
$string['quota_teacher_day'] = 'Teacher-Tokenlimit pro Tag';
$string['quota_teacher_hour'] = 'Teacher-Tokenlimit pro Stunde';
$string['quota_teacher_month'] = 'Teacher-Tokenlimit pro Monat';
$string['quota_window_day'] = 'Tag';
$string['quota_window_hour'] = 'Stunde';
$string['quota_window_month'] = 'Monat';
$string['support_handbook_pending_detail'] = 'Das Handbuch ist noch nicht verfügbar.';

// Entwicklerseite (task24, adr05 §6). Hinter dem Setting "developerdocs"
// und moodle/site:config; zeigt den Design-System-Vertrag an der laufenden
// Installation.
$string['developer_blocks'] = 'Bausteine, live';
$string['developer_blocks_intro'] = 'Von dieser Installation gezeichnet, mit den Stilen, die ein Plugin tatsächlich bekommt.';
$string['developer_col_origin'] = 'Herkunft';
$string['developer_col_role'] = 'Rolle';
$string['developer_col_token'] = 'Token';
$string['developer_col_value'] = 'Aufgelöster Wert';
$string['developer_contract'] = 'Der Vertrag';
$string['developer_contract_missing'] = 'Der Abschnitt „Design-System" wurde in 03-dev-doc.md nicht gefunden.';
$string['developer_disabled'] = 'Die Seite „Entwicklung" ist auf dieser Website nicht eingeschaltet. Sie steht unter Website-Administration > Plugins > Lokale Plugins > eLeDia.ai | KI-Suite als „Entwickler-Dokumentation anzeigen".';
$string['developer_environment'] = 'Umgebung';
$string['developer_heading'] = 'Entwicklung';
$string['developer_origin_core'] = 'Core';
$string['developer_origin_other'] = 'überschrieben';
$string['developer_origin_theme'] = 'Theme';
$string['developer_origin_unknown'] = 'nicht lesbar';
$string['developer_scales'] = 'Die Skalen, gezeichnet';
$string['developer_scales_intro'] = 'Jede Stufe in der Größe, auf die sie in diesem Browser tatsächlich hinausläuft.';
$string['developer_subtitle'] = 'Das Design-System der Suite, an dieser Installation gezeigt.';
$string['developer_tokens'] = 'Token, aufgelöst';
$string['developer_tokens_intro'] = 'Aus der laufenden Seite gelesen, nicht aus dem Quelltext. „Herkunft" nennt das Stylesheet, das sich durchgesetzt hat.';
$string['developerdocs'] = 'Entwickler-Dokumentation anzeigen';
$string['developerdocs_desc'] = 'Ergänzt für Administratorinnen und Administratoren die Seite „Entwicklung": die Gestaltungstoken der Suite, aufgelöst an dieser Installation, die Skalen gezeichnet und der Vertrag, an den sich jedes Suite-Plugin hält. Vorgabe aus — die Seite ist für Menschen geschrieben, die Plugins bauen, nicht für Menschen, die die Website betreiben.';
$string['developerdocs_heading'] = 'Für Entwickelnde';
$string['developerdocs_heading_desc'] = 'Nichts hier ändert etwas daran, wie sich die Suite für ihre Nutzenden verhält.';
$string['topic_suite_admin_body'] = 'Vollständigkeit zählt mehr als die Reihenfolge — diese hier spart aber die meisten Umwege:

1. Mindestens einen Provider unter **Website-Administration > KI > KI-Provider** einrichten. Kein Plugin der Suite pflegt eigene API-Schlüssel.
2. Die Übersicht der KI-Suite öffnen und die Funktionen einschalten, die diese Website anbieten soll.
3. Für den Tutor die Retrieval- und Werkzeug-Backends einrichten und mindestens einen Kurs indexieren. Das Tutor-Dashboard listet auf, was noch nicht bereit ist.
4. Ein websiteweites Tagesbudget setzen, damit ein begeisterter Kurs nicht das Jahresbudget verbraucht.

Und dann Bescheid geben. Eine Ankündigung unter **Ankündigungen** erscheint im Hilfe-Panel aller Personen, für die sie gedacht ist — das erreicht sie früher als ein Support-Ticket.';
$string['topic_suite_admin_summary'] = 'Erst ein Provider im Moodle-Kern, dann die Funktionen der Suite, dann die Backends des Tutors. Übersicht und Tutor-Dashboard sagen, was noch fehlt.';
$string['topic_suite_admin_title'] = 'Die Suite in Betrieb nehmen';
$string['topic_suite_developer_body'] = 'Die Suite hat eine Gestaltungsschicht: Schriftgrößen, Farben, Abstände und Radien entstehen an einer Stelle und werden überall sonst nur gelesen. Ein Plugin, das sich daran hält, sieht auch unter einem fremden Theme richtig aus.

Die Seite **Entwicklung** zeigt das an dieser Website, nicht im Allgemeinen:

- welchen Wert jedes Token gerade hat und aus welchem Stylesheet er stammt,
- die acht Stufen der Schriftskala in der Größe, auf die sie in diesem Browser hinauslaufen,
- die Bausteine der Suite, live gezeichnet,
- und den Vertrag: was ein Plugin setzen darf und was nicht.

Zu finden unter **Website-Administration > Plugins > Lokale Plugins > eLeDia.ai | KI-Suite**, sobald dort „Entwickler-Dokumentation anzeigen" eingeschaltet ist — dieselbe Einstellung, die auch dieses Kapitel sichtbar macht.

Ein Kapitel für Menschen, die Plugins bauen. Wer die Website nur betreibt, verliert nichts, wenn die Einstellung aus bleibt.';
$string['topic_suite_developer_summary'] = 'Token, Skalen und der Vertrag, an den sich jedes Plugin der Suite hält — an dieser Installation gezeigt.';
$string['topic_suite_developer_title'] = 'Für Entwickelnde: das Design-System';
$string['topic_suite_limits_body'] = 'Wenn keine Antwort kommt, sagt die Meldung meist, welcher dieser Fälle vorliegt.

- **Ein Limit ist erreicht.** Websites setzen ein Tagesbudget, ein Kurs oder eine Aktivität kann ein strengeres setzen. Bis morgen warten hilft; Lehrende oder Administration können es anheben.
- **Der Dienst ist nicht erreichbar.** Von Ihrer Seite nichts zu machen — melden Sie es der Administration.
- **Die Funktion ist noch nicht eingerichtet.** Kurz nach der Installation der Suite normal.

Antworten können außerdem falsch sein und trotzdem sicher klingen. Behandeln Sie das Ergebnis als Entwurf, nicht als Quelle, und prüfen Sie alles, was Sie sonst nachgeschlagen hätten — besonders Zahlen, Daten und Zitate.';
$string['topic_suite_limits_summary'] = 'KI-Antworten kosten die Website Geld, deshalb gibt es Tagesbudgets. Eine Absage ist meist ein Limit, eine fehlende Einstellung oder ein Dienst, der nicht erreichbar ist.';
$string['topic_suite_limits_title'] = 'Warum manchmal keine Antwort kommt';
$string['topic_suite_privacy_body'] = 'Jede KI-Anfrage läuft über den Moodle-Server. Ihr Browser spricht nie mit dem KI-Dienst — deshalb erreichen dessen Zugangsdaten Ihren Rechner nie.

Was wo bleibt:

- **In Moodle:** Zeiger auf Ihre Gespräche, Frage und Antwort ohne Ihren Namen, die Bestätigung der Datenschutzhinweise und Nutzungszähler. Das Kapitel „Was die KI-Suite protokolliert" sagt genau, was wohin geht und wie lange.
- **Beim KI-Dienst:** die Transkripte selbst — sofern der Dienst Ihrer Website sie überhaupt speichert.

Sie können Ihre eigenen Daten entfernen lassen. Der Tutor hat einen Datenschutzbereich mit **Alle meine Tutor-Daten löschen**, und beim Löschen Ihres Moodle-Kontos verschwinden dieselben lokalen Einträge automatisch.

Zwei Gewohnheiten lohnen sich: Schreiben Sie nichts in eine KI-Funktion, was Sie nicht auch in ein Kursforum schreiben würden. Und behalten Sie im Kopf, dass andere Lernende Ihre Gespräche zwar nicht lesen können, Administrator/innen aber sehen, dass und wie viel KI genutzt wurde.';
$string['topic_suite_privacy_summary'] = 'Ihr Text geht vom Moodle-Server zum KI-Dienst Ihrer Website — nie direkt aus Ihrem Browser. Moodle behält nur, was es braucht, um Ihnen Ihren eigenen Verlauf zu zeigen.';
$string['topic_suite_privacy_title'] = 'Was mit dem passiert, was Sie eingeben';
$string['topic_suite_teacher_body'] = 'Was Sie am ehesten brauchen werden:

- **Der Tutor-Block.** In den Kurs einfügen, Kurskontext einschalten — dann fragen Lernende zu Ihrem Material statt zum Internet.
- **Fragengenerierung.** Fragenentwürfe aus einem Text oder aus Kursmaterial erzeugen und in der Fragensammlung prüfen. Es sind Entwürfe: Ohne Sie landet nichts in einem Test.
- **KI-Feedback und KI-Freitextfragen.** Ein Modell kommentiert Freitext anhand von Kriterien, die Sie schreiben.
- **Das Lehrenden-Dashboard.** Im Kurs sammelt es, was die KI-Funktionen dort getan haben.

Alles bleibt innerhalb der üblichen Moodle-Rechte. Der Tutor greift mit dem Zugang der fragenden Person auf Material zu und kann deshalb niemandem etwas zeigen, was diese Person nicht ohnehin öffnen dürfte.';
$string['topic_suite_teacher_summary'] = 'Fragenentwürfe aus Ihrem eigenen Material, KI-Kommentare zu Freitext und einen Tutor, den Sie in Ihren Kurs setzen können.';
$string['topic_suite_teacher_title'] = 'Was die Suite Ihnen als Lehrkraft gibt';
$string['topic_suite_what_body'] = 'Die KI-Suite ist keine eigene Website. Sie ist eine Reihe von Funktionen in diesem Moodle, die ein Sprachmodell nutzen.

Was Ihnen begegnen kann — je nachdem, was Ihre Website eingeschaltet hat:

- **Der Tutor** — ein Chat, den Sie zu Ihrem Kurs fragen können, im Kursblock oder auf einer eigenen Seite.
- **KI-Aktivitäten** — eine Chat-Aktivität, eine Feedback-Aktivität und Freitextfragen, die ein Modell kommentiert.
- **Hilfe beim Erstellen** — Fragenentwürfe und Kursgerüste für alle, die Kurse bauen.
- **Übersetzung** — ein Filter, der Inhalte beim Anzeigen übersetzt.

Nicht jede Website hat alles, und nicht jede Rolle sieht alles. Die Suite-Übersicht zeigt nur, was Sie tatsächlich nutzen dürfen. Eine Lücke dort bedeutet also: ausgeschaltet oder nicht für Ihre Rolle gedacht — nicht: kaputt.';
$string['topic_suite_what_summary'] = 'Eine Gruppe von KI-Funktionen in diesem Moodle: ein Tutor zum Fragen, Hilfe beim Erstellen von Fragen und Feedback, und Übersetzung. Sie teilen sich einen Einstieg und dieselben Regeln.';
$string['topic_suite_what_title'] = 'Was die KI-Suite ist';
$string['topic_suite_where_body'] = 'Oben auf jeder Seite, neben Ihrem Nutzermenü, sitzt eine kleine Pille mit dem KI-Zeichen. Das ist der Launcher der KI-Suite, und er öffnet die Übersicht.

Die Übersicht listet jede KI-Funktion auf, die Sie nutzen dürfen, mit einer Zeile dazu, was sie tut. Ein Klick auf eine Kachel öffnet sie.

Zwei Dinge stehen bewusst nicht auf der Übersicht, weil sie zu einem Ort gehören und nicht zur Suite:

- Der **Tutor-Block** erscheint in dem Kurs oder Dashboard, in den ihn jemand gesetzt hat.
- **KI-Aktivitäten** erscheinen im Kurs, in dem Abschnitt, in den sie eingefügt wurden.

Ausgeloggt ist der Launcher nicht sichtbar, ebenso wenig auf Anmelde-, Popup- und Wartungsseiten.';
$string['topic_suite_where_summary'] = 'Eine Pille in der obersten Leiste öffnet die Übersicht. Alles andere geht von dort aus — oder sitzt im Kurs, in dem es benutzt wird.';
$string['topic_suite_where_title'] = 'Wo die KI-Funktionen zu finden sind';
$string['contract_language_note_de'] = 'Dieser Vertragstext wird auf Deutsch gepflegt und steht hier, wie er geschrieben ist.';
$string['topic_contract_chat_summary'] = 'Wie ein Plugin zu einer Platzierung auf der gemeinsamen Chat-Engine wird.';
$string['topic_contract_chat_title'] = 'Vertrag 4: einen Chat anbieten';
$string['topic_contract_explain_summary'] = 'Wie ein Plugin eigene Kapitel in dieses Handbuch stellt, statt eine Hilfeseite '
    . 'mitzufuehren.';
$string['topic_contract_explain_title'] = 'Vertrag 2: sich selbst erklaeren';
$string['topic_contract_health_summary'] = 'Wie ein Plugin dem Kern sagt, ob es eingerichtet, untaetig oder kaputt ist.';
$string['topic_contract_health_title'] = 'Vertrag 3: den eigenen Zustand melden';
$string['topic_contract_overview_summary'] = 'Die sieben Schnittstellen zwischen einem Suite-Plugin, dem Kern und seinen '
    . 'Nachbarn, und welche davon Konventionen statt Registrierungen sind.';
$string['topic_contract_overview_title'] = 'Vertraege: wie ein Plugin Teil der Suite wird';
$string['topic_contract_premium_summary'] = 'Wie ein Plugin fragt, ob eine kostenpflichtige Faehigkeit auf dieser Website '
    . 'verfuegbar ist.';
$string['topic_contract_premium_title'] = 'Vertrag 5: Premium freischalten';
$string['topic_contract_quota_summary'] = 'Der eine Aufruf, der Guthaben bucht und den Audit-Eintrag schreibt, und was passiert, '
    . 'wenn er umgangen wird.';
$string['topic_contract_quota_title'] = 'Vertrag 6: die KI aufrufen';
$string['topic_contract_register_summary'] = 'Wie ein Plugin auf dem Dashboard erscheint: eine Klasse, ein Descriptor, keine '
    . 'Registrierungstabelle.';
$string['topic_contract_register_title'] = 'Vertrag 1: ein Feature anmelden';
$string['topic_contract_shell_summary'] = 'Die Seitenhuelle, die dem Kern gehoert, und was ein Plugin ihr hinzufuegen darf.';
$string['topic_contract_shell_title'] = 'Vertrag 7: aussehen wie die Suite';
$string['task_prune_turns'] = 'Abgelaufene KI-Fragen und -Antworten löschen';
$string['task_prune_usage'] = 'Abgelaufene KI-Guthabenzähler löschen';
$string['turn_retentiondays'] = 'Fragen und Antworten aufbewahren';
$string['turn_retentiondays_desc'] = 'Tage, bis eine aufgezeichnete KI-Frage samt Antwort gelöscht wird. 0 bewahrt sie unbegrenzt auf. Dieses Protokoll enthält freien Text und verdient deshalb die kürzeste der drei Fristen; unter 30 Tagen wird die Themenauswertung in den Kurs-Einblicken unbrauchbar.';
$string['usage_retentiondays'] = 'Guthabenzähler aufbewahren';
$string['usage_retentiondays_desc'] = 'Tage, bis eine Zeile des Guthaben-Hauptbuchs gelöscht wird. 0 bewahrt sie unbegrenzt auf. Das Hauptbuch ist zugleich die Quelle der Ansicht „welches Feature wird benutzt" — eine kürzere Frist verkürzt auch diesen Bericht.';
$string['retention_settings_desc'] = 'Drei Protokolle, drei Aufräumaufgaben, drei Fristen. Das Handlungsprotokoll behält die Person und die längste Frist, weil es festhält, in wessen Auftrag die KI gehandelt hat; Fragen und Antworten enthalten freien Text und bekommen die kürzeste; der Herkunftsnachweis überlebt den Inhalt, den er belegt, und wird im Plugin KI-Transparenz eingestellt. 0 bewahrt ein Protokoll unbegrenzt auf.';
$string['retention_settings_heading'] = 'Aufbewahrung';
$string['audit_col_feature'] = 'Feature';
$string['audit_col_origin'] = 'Herkunft';
$string['audit_col_time'] = 'Zeit';
$string['audit_col_topic'] = 'Thema';
$string['audit_core_report_intro'] = 'Was andere Plugins gefragt haben, so wie Moodle es aufgezeichnet hat. Anfragen der Suite bleiben hier außen vor: sie laufen ebenfalls durch Moodles KI-Subsystem und stünden sonst zweimal da, deshalb merkt sich jeder Turn seine Registerzeile und genau die wird ausgelassen. Zwei Ausnahmen, beide ehrlich — ein Chat-Turn erreicht dieses Register nie, und läuft die Aufbewahrungsfrist eines Turns ab, taucht seine alte Registerzeile hier wieder auf, weil sie dann tatsächlich nur noch hier steht.';
$string['audit_core_report_title'] = 'KI außerhalb der Suite';
$string['audit_origin_general'] = 'Modell';
$string['audit_origin_grounded'] = 'Kursmaterial';
$string['audit_origin_mcp'] = 'Moodle-Daten';
$string['audit_quick_chat'] = 'Chat-Turns';
$string['audit_quick_ungrounded'] = 'Nicht vom Material gedeckt';
$string['audit_suite_report_intro'] = 'Jede KI-Anfrage der Suite: welches Feature gefragt hat, was gefragt und geantwortet wurde, worauf die Antwort gegründet war und was sie gekostet hat. Keine Akteur-Spalte — ein Chat-Turn erreicht dieses Protokoll ohne Nutzerkennung, und nichts hier löst das Pseudonym auf, das an ihre Stelle tritt.';
$string['audit_suite_report_title'] = 'Anfragen aus der Suite';
$string['turn_entity_title'] = 'KI-Turn';
$string['turn_report_filename'] = 'ki-turns';
$string['turn_report_name'] = 'KI-Turns der Suite';
$string['action_actor_anonymised'] = 'Bezug entfernt';
$string['action_actor_anonymised_help'] = 'Die Aufbewahrungsfrist dieser Handlung ist abgelaufen, der Personenbezug wurde entfernt. Die Handlung selbst bleibt: ein Aufsichtsnachweis, der verschwindet, weist nichts nach.';
$string['action_actor_deleted'] = 'Gelöschtes Konto';
$string['action_col_action'] = 'Handlung';
$string['action_col_actor'] = 'Im Auftrag von';
$string['action_col_duration'] = 'Dauer';
$string['action_col_kind'] = 'Art';
$string['action_entity_title'] = 'KI-Handlung';
$string['action_eyebrow'] = 'Was hier festgehalten wird';
$string['action_kind_read'] = 'Liest';
$string['action_kind_write'] = 'Schreibt';
$string['action_metric_failed'] = 'Fehlversuche';
$string['action_metric_people'] = 'Personen';
$string['action_metric_total'] = 'Handlungen';
$string['action_metric_writes'] = 'Davon schreibend';
$string['action_metric_writes_hint'] = '{$a} % aller Handlungen';
$string['action_page_intro'] = 'Jede Handlung, die der Assistent über ein Werkzeug ausgeführt hat, und die Person, in deren Auftrag sie lief. Fragen und Antworten stehen hier nicht — die stehen unter „Jede KI-Anfrage", und dort ohne Person.';
$string['action_panel_intro'] = 'Eine Handlung, die etwas verändert — eine geschriebene Bewertung, eine angelegte Aktivität, eine gesendete Nachricht —, ist eine Handlung im Auftrag eines Menschen. Hier ist sie aktenkundig.';
$string['action_panel_title'] = 'In Zahlen';
$string['action_place_unknown'] = 'Nicht erfasst';
$string['action_quick_write'] = 'Nur schreibend';
$string['action_report_filename'] = 'ki-handlungen';
$string['action_report_name'] = 'KI-Handlungen';
$string['action_retention_note'] = 'Der Personenbezug wird nach {$a} Tagen entfernt; die Handlung selbst bleibt.';
$string['action_retention_note_forever'] = 'Der Personenbezug bleibt unbegrenzt erhalten.';
$string['action_retentiondays'] = 'Personenbezug der Handlungen aufbewahren';
$string['action_retentiondays_desc'] = 'Tage, bis die Person aus einer aufgezeichneten KI-Handlung entfernt wird. Die Handlung selbst bleibt immer — ein Aufsichtsnachweis, der auf Zuruf verschwindet, weist nichts nach. 0 bewahrt den Bezug unbegrenzt auf. Das ist die längste der drei Fristen.';
$string['privacy:metadata:local_elediaai_core_action'] = 'Eine Zeile je Handlung, die die KI über ein Werkzeug im Auftrag einer Person ausgeführt hat: welches Werkzeug, ob sie etwas verändert hat, wo und wann. Prompt und Antwort werden nicht gespeichert. Weil dies der Aufsichtsnachweis ist, entfernt eine Löschanfrage die Person aus der Zeile und behält die Handlung.';
$string['privacy:metadata:local_elediaai_core_action:courseid'] = 'Der Kurs, den die Handlung betraf.';
$string['privacy:metadata:local_elediaai_core_action:iswrite'] = 'Ob das Werkzeug etwas verändert hat.';
$string['privacy:metadata:local_elediaai_core_action:success'] = 'Ob die Handlung durchlief.';
$string['privacy:metadata:local_elediaai_core_action:timecreated'] = 'Wann sie stattfand.';
$string['privacy:metadata:local_elediaai_core_action:toolname'] = 'Das Werkzeug, das der Assistent aufgerufen hat.';
$string['privacy:metadata:local_elediaai_core_action:userid'] = 'Die Person, in deren Auftrag die KI gehandelt hat.';
$string['privacy:path:actions'] = 'KI-Handlungen';
$string['task_anonymise_actions'] = 'Personenbezug alter KI-Handlungen entfernen';
$string['insights_backtocourse'] = 'Zurück zum Kurs';
$string['insights_covered'] = 'Gedeckt';
$string['insights_gap_askers'] = 'von {$a} Personen';
$string['insights_gap_hassource'] = 'Material vorhanden: {$a}';
$string['insights_gap_nosource'] = 'Kein Kursmaterial deckt dieses Thema';
$string['insights_gap_share'] = '{$a} % ungedeckt';
$string['insights_gap_share_label'] = '{$a->topic}: {$a->percent} % der Antworten waren nicht vom Kursmaterial gedeckt';
$string['insights_gaps_empty'] = 'Jedes Thema, das in diesem Zeitraum gefragt wurde, war aus Ihrem Kursmaterial beantwortbar.';
$string['insights_gaps_eyebrow'] = 'Lücken';
$string['insights_gaps_intro'] = 'Sortiert nach dem Anteil der Antworten, die Ihr Material nicht decken konnte — nicht nach der Menge. Die erste Zeile ist die, die sich am ehesten lohnt.';
$string['insights_gaps_title'] = 'Gefragt, aber vom Material nicht beantwortet';
$string['insights_lead'] = 'Fragen an den KI-Tutor, zusammengefasst nach Thema. Ohne Namen — die Auswertung zeigt, wo Ihr Material trägt und wo nicht, nie wer gefragt hat.';
$string['insights_metric_askers'] = 'Fragende';
$string['insights_metric_askers_hint'] = 'gezählt, ohne jemanden zu benennen';
$string['insights_metric_followup'] = 'Nachgefasst';
$string['insights_metric_followup_hint'] = 'die Antwort hat nicht getragen';
$string['insights_metric_grounded'] = 'Vom Material gedeckt';
$string['insights_metric_grounded_hint'] = 'aus Ihrem Kurs beantwortet';
$string['insights_metric_questions'] = 'Fragen';
$string['insights_notcovered'] = 'Ungedeckt';
$string['insights_nothing_yet'] = 'In diesem Zeitraum wurde nichts gefragt.';
$string['insights_period_180'] = 'Semester';
$string['insights_period_30'] = '30 Tage';
$string['insights_period_7'] = '7 Tage';
$string['insights_retention_note'] = 'Fragen werden {$a} Tage aufbewahrt und danach gelöscht.';
$string['insights_retention_note_forever'] = 'Fragen werden unbegrenzt aufbewahrt.';
$string['insights_tools_hint'] = 'Aus einer Lücke Material machen:';
$string['insights_trend_eyebrow'] = 'Verlauf';
$string['insights_trend_intro'] = 'Fragen pro Tag über die letzten zwei Wochen.';
$string['insights_trend_title'] = 'Wann gefragt wurde';
$string['insights_unclear_empty'] = 'Kein Thema wurde nach einer Antwort aus Ihrem Material erneut gefragt.';
$string['insights_unclear_eyebrow'] = 'Unklar';
$string['insights_unclear_intro'] = 'Aus Ihrem Material beantwortet und trotzdem innerhalb einer halben Stunde erneut gefragt. Hier fehlt nicht der Inhalt, sondern die Klarheit.';
$string['insights_unclear_meta'] = '{$a->questions} Fragen · {$a->askers} Personen';
$string['insights_unclear_share'] = '{$a} % nachgefasst';
$string['insights_unclear_title'] = 'Material vorhanden, trägt aber nicht';
$string['insights_verbatim_badge'] = 'Ohne Namen erfasst';
$string['insights_verbatim_empty'] = 'Keine Fragen anzuzeigen.';
$string['insights_verbatim_eyebrow'] = 'Im Wortlaut';
$string['insights_verbatim_intro'] = 'Die letzten Fragen, wie sie gestellt wurden.';
$string['insights_verbatim_title'] = 'Die Fragen selbst';
$string['elediaai_core:viewaiactions'] = 'Protokoll der KI-Handlungen einsehen';
$string['elediaai_core:viewcourseinsights'] = 'Kurs-Einblicke in die KI-Fragen einsehen';
$string['insights_metric_grounded_label'] = '{$a->covered} % der Antworten kamen aus Ihrem Kursmaterial, {$a->open} % nicht';
$string['insights_metric_questions_hint'] = 'in den letzten {$a} Tagen';
$string['insights_trend_empty'] = 'In diesem Zeitraum wurde nichts gefragt, es gibt also nichts zu zeichnen.';
$string['insights_trend_label'] = 'Fragen pro Tag: {$a->total} über {$a->days} Tage';
$string['insights_trend_peak'] = 'Stärkster Tag: {$a->day} mit {$a->count} Fragen';
$string['insights_unclear_share_label'] = '{$a->topic}: {$a->percent} % der Antworten wurden erneut nachgefragt';
$string['insights_unclear_source'] = 'Material: {$a->material}';
$string['chooser_empty'] = 'In diesem Zeitraum hat kein Kurs KI-Fragen.';
$string['chooser_eyebrow'] = 'Kurs wählen';
$string['chooser_intro'] = 'Nach Menge geordnet. Der Anteil rechts ist der Teil, den Ihr Material nicht beantworten konnte — je höher er ist, desto eher lohnt der Blick in den Kurs.';
$string['chooser_meta'] = '{$a->questions} Fragen · {$a->askers} Personen';
$string['chooser_page_intro'] = 'Wählen Sie einen Kurs, um dessen Themen, Lücken und Fragen zu sehen.';
$string['chooser_title'] = 'Kurse mit KI-Fragen';
$string['chooser_uncovered'] = '{$a} % ungedeckt';
$string['entry_actions_text'] = 'Welches Werkzeug was getan hat, in wessen Auftrag, und ob es etwas verändert hat.';
$string['entry_insights_text'] = 'Je Kurs: welche Themen Ihr Material nicht beantworten konnte, und wo es vorhanden ist und trotzdem nicht trägt.';
$string['entry_requests_text'] = 'Jede einzelne KI-Anfrage der Suite mit Frage, Antwort, Modell und Kosten.';
$string['entry_settings_text'] = 'Wer das Audit lesen darf, was es zeigt, und wie lange jedes Protokoll aufbewahrt wird.';
$string['insights_othercourse'] = 'Anderen Kurs wählen';
$string['audit_core_report_eyebrow'] = 'Moodle';
$string['audit_requests_page_intro'] = 'Zwei Listen, weil es zwei Protokolle gibt: das eigene der Suite und Moodles für alles andere. Sie überschneiden sich nicht mehr — eine Anfrage der Suite steht einmal da, oben. Beide zeigen Frage, Antwort, Modell, Status und Kosten, soweit die Einstellungen es erlauben.';
$string['audit_state_eyebrow'] = 'Ganze Website, ganze Laufzeit';
$string['audit_state_intro'] = 'Gezählt aus Moodles eigenem KI-Register: alle Plugins, seit die KI hier eingeschaltet wurde. Die Aufteilung darunter ist enger gefasst — nur die eLeDia.ai-Suite und nur die letzten 30 Tage.';
$string['audit_state_title'] = 'Wie viel KI genutzt wurde';
$string['audit_suite_report_eyebrow'] = 'eLeDia.ai-Suite';
$string['entry_scope_admin'] = 'Administration';
$string['entry_scope_course'] = 'Je Kurs';
$string['entry_scope_site'] = 'Ganze Website';
$string['nav_audit_home'] = 'Überblick';
$string['surface_actions_title'] = 'Was die KI getan hat';
$string['surface_insights_title'] = 'Was die Lernenden gefragt haben';
$string['surface_requests_title'] = 'Jede KI-Anfrage';
$string['surface_settings_title'] = 'Zugang und Aufbewahrung';
$string['action_table_eyebrow'] = 'Jeder Eintrag';
$string['action_table_intro'] = 'Neueste zuerst. Die Liste lässt sich auf Handlungen einschränken, die etwas verändert haben, oder auf die fehlgeschlagenen.';
$string['action_table_title'] = 'Die Handlungen selbst';
$string['audit_settings_page_intro'] = 'Wer das alles lesen darf, wie viel von einer Anfrage gezeigt wird, und wie lange jedes der drei Protokolle aufbewahrt wird.';
$string['topic_suite_logs_title'] = 'Was die KI-Suite protokolliert';
$string['topic_suite_logs_summary'] = 'Drei Protokolle mit drei Aufgaben: was gefragt wurde (ohne Ihren Namen), was die KI getan hat (mit Ihrem Namen) und woher ein Text stammt (nur als Prüfwert).';
$string['topic_suite_logs_body'] = 'Die KI-Suite führt drei getrennte Protokolle. Sie sind verschieden, weil sie verschiedene Aufgaben haben — und nur deshalb dürfen sie verschieden lange bleiben.

**Was Sie gefragt haben.** Frage und Antwort werden festgehalten, ohne Ihren Namen. An seiner Stelle steht ein kurzes Kürzel, das für Sie steht, aber nicht zu Ihnen zurückführt. Daraus entsteht die Kursansicht: welche Themen oft gefragt wurden und wo das Kursmaterial keine Antwort hergab. Ihre Lehrkraft sieht die Fragen, nicht wer sie gestellt hat. Dieses Protokoll hat die kürzeste Frist.

**Was die KI getan hat.** Wenn die KI etwas verändert — eine Bewertung schreibt, eine Aktivität anlegt, eine Nachricht sendet —, wird das mit Ihrem Namen festgehalten. Nicht der Inhalt, nur die Handlung. Das ist der Teil, den es geben muss: eine Handlung im Auftrag eines Menschen muss nachvollziehbar bleiben. Deshalb hat dieses Protokoll die längste Frist, und deshalb wird es am Ende nicht gelöscht, sondern der Name daraus entfernt.

**Woher ein Text stammt.** Von der KI erzeugte Texte bekommen einen Prüfwert, mit dem sich später belegen lässt, dass sie aus der KI kamen. Er enthält den Text nicht, nur seinen Fingerabdruck — deshalb überlebt er den Text.

Wie lange jedes davon bleibt, entscheidet Ihre Website. Fragen Sie die Administration, wenn Sie es genau wissen wollen; im KI-Audit steht es unter „Zugang und Aufbewahrung".';
$string['audit_col_tokens_help_estimated'] = 'Prompt-Tokens / Antwort-Tokens — geschätzt. Das Backend hat keinen Verbrauch gemeldet; die Suite hat ihre eigene Schätzung auf das Guthaben gebucht und dieselbe Zahl hier festgehalten.';
$string['feature_maturity_alpha'] = 'Alpha';
$string['feature_maturity_alpha_help'] = 'In der Alpha: früh, unvollständig und jederzeit änderbar. Zum Ausprobieren, nicht zum Verlassen.';
$string['feature_maturity_beta'] = 'Beta';
$string['feature_maturity_beta_help'] = 'In der Beta: benutzbar und im Einsatz, aber noch in Bewegung. Rechnen Sie mit Kanten und gelegentlichen Änderungen an der Bedienung.';
