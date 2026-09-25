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
 * German language strings for the MCP web service plugin.
 *
 * @package     webservice_elediamcp
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2025 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Audit events.
$string['event_context_verified'] = 'MCP-Nutzerkontext geprüft';
$string['event_context_verified_desc'] = 'Nutzer/in mit der ID \'{$a->userid}\' hat den eigenen MCP-Kontext geprüft (Kursfilter: {$a->coursefilter}).';
$string['event_token_created'] = 'MCP-Token erstellt';
$string['event_token_created_desc'] = 'Nutzer/in mit der ID \'{$a->userid}\' hat das MCP-Token \'{$a->label}\' für Nutzer/in mit der ID \'{$a->relateduserid}\' im Service \'{$a->service}\' erstellt (über {$a->component}).';
$string['event_token_revoked'] = 'MCP-Token widerrufen';
$string['event_token_revoked_desc'] = 'Nutzer/in mit der ID \'{$a->userid}\' hat das MCP-Token \'{$a->label}\' von Nutzer/in mit der ID \'{$a->relateduserid}\' im Service \'{$a->service}\' widerrufen.';
$string['event_tool_invoked'] = 'MCP-Tool aufgerufen';
$string['event_tool_invoked_desc'] = 'Nutzer/in mit der ID \'{$a->userid}\' hat das MCP-Tool \'{$a->toolname}\' aufgerufen (isError: {$a->iserror}, Dauer: {$a->durationms} ms).';
$string['event_write_performed'] = 'MCP-Schreibaktion ausgeführt';
$string['event_write_performed_desc'] = 'Nutzer/in mit der ID \'{$a->userid}\' hat über das MCP-Tool \'{$a->toolname}\' eine Schreibaktion ausgeführt.';

// Errors.
$string['error_expiry_in_past'] = 'Das Ablaufdatum muss in der Zukunft liegen.';
$string['error_invalid_component'] = 'Unbekannte Komponente \'{$a}\'. MCP-Tokens können nur im Auftrag einer installierten Moodle-Komponente ausgestellt werden.';
$string['error_label_required'] = 'Eine Bezeichnung für das Token ist erforderlich.';
$string['error_service_disabled'] = 'Der ausgewählte Webservice ist deaktiviert.';
$string['error_service_not_mcp'] = 'Der ausgewählte Webservice ist nicht als MCP-Service konfiguriert. Tokens können nur für konfigurierte MCP-Services erstellt werden.';
$string['error_token_not_found'] = 'Das angeforderte MCP-Token existiert nicht.';
$string['error_token_not_owned_by_component'] = 'Dieses MCP-Token wurde nicht von der aufrufenden Komponente ausgestellt und kann nicht über die interne API widerrufen werden.';
$string['err_emergency_disabled'] = 'Der MCP-Webservice ist von der Website-Administration vorübergehend deaktiviert.';
$string['err_empty_request'] = 'Der Anfragetext ist leer';
$string['err_forbidden_origin'] = 'Origin durch die Richtlinie der Website nicht erlaubt';
$string['err_invalid_json'] = 'Ungültiges JSON';
$string['err_invalid_jsonrpc'] = 'Ungültige JSON-RPC-Version';
$string['err_invalid_protocol_version'] = 'Nicht unterstützte MCP-Protokollversion';
$string['err_internal_tool_error'] = 'Interner Tool-Fehler. Wenden Sie sich an die Website-Administration, wenn das Problem weiterhin besteht.';
$string['err_missing_method'] = 'Fehlende Methode';
$string['err_missing_tool_name'] = 'Fehlender Tool-Name';
$string['err_not_mcp_service'] = 'Dieses Token ist nicht für den MCP-Service berechtigt.';
$string['err_rate_limit_exceeded'] = 'Rate Limit überschritten. Versuchen Sie es in {$a} Sekunden erneut.';
$string['err_request_too_large'] = 'Der Anfragetext überschreitet die maximal zulässige Größe';
$string['err_token_in_query_disabled'] = 'Token im Query-String ist durch die Richtlinie der Website deaktiviert. Verwenden Sie stattdessen den Authorization-Header.';

// Capabilities.
$string['elediamcp:managetokens'] = 'Eigene MCP-Tokens erstellen und widerrufen';
$string['elediamcp:use'] = 'MCP-Webservice nutzen';
$string['elediamcp:viewcaps'] = 'Eigenen Fähigkeitsbaum über MCP ansehen';

// Generic.
$string['disabled'] = 'deaktiviert';

// Plugin metadata.
$string['guide_body'] = 'Model Context Protocol ist das offene Protokoll, über das KI-Assistenten fremde Dienste erreichen. Dieses Plugin stellt ausgewählte Moodle-Fähigkeiten als solche Werkzeuge bereit: Kurse nachschlagen, Inhalte lesen, Material vorbereiten.

Was Sie als Administration festlegen:

- **Welche Werkzeuge offenstehen.** Die Auswahl ist die Sicherheitsgrenze.
- **Wer ein Token bekommt.** Jeder Aufruf läuft unter der Identität des Tokens.

Worauf zu achten ist: die Rechte der Person gelten, nicht die des Assistenten. Ein Token für ein Konto mit weitreichenden Rechten gibt dem Assistenten dieselben. Für Assistenten lohnt ein eigenes Konto mit genau den Rechten, die die Aufgabe braucht.';
$string['guide_summary'] = 'MCP macht Moodle zu einem Werkzeug, das ein Assistent benutzen kann — immer als angemeldete Person und nie über deren Rechte hinaus.';
$string['guide_title'] = 'Externe KI-Assistenten anbinden';
$string['health_configure'] = 'Konfiguration';
$string['health_services'] = 'Freigegebene Dienste';
$string['health_services_none'] = 'Es ist kein Dienst freigegeben, ein Assistent erreicht also nichts.';
$string['health_services_ok'] = '{$a} Dienst(e) über MCP freigegeben.';
$string['health_webservices'] = 'Moodles Webservices';
$string['health_webservices_off'] = 'Webservices sind website-weit abgeschaltet. Nichts, was MCP anbietet, ist erreichbar, solange das so ist.';
$string['pluginname'] = 'Model Context Protocol';
$string['shell_help_label'] = 'Hilfe zu Model Context Protocol';
$string['shell_settings_label'] = 'Einstellungen für Model Context Protocol';
$string['privacy:metadata:webservice_elediamcp_token'] = 'Metadaten zu MCP-Webservice-Tokens, die für Nutzer/innen oder in deren Auftrag ausgestellt wurden. Der geheime Token-Wert selbst wird hier nie gespeichert.';
$string['privacy:metadata:webservice_elediamcp_token:component'] = 'Die suite-eigene Komponente, die das Token ausgestellt hat, sofern vorhanden.';
$string['privacy:metadata:webservice_elediamcp_token:creatorid'] = 'Nutzer/in, die das Token erstellt hat.';
$string['privacy:metadata:webservice_elediamcp_token:externalserviceid'] = 'Der externe Service, auf den das Token beschränkt ist.';
$string['privacy:metadata:webservice_elediamcp_token:lastaccess'] = 'Der Zeitpunkt, zu dem das Token zuletzt verwendet wurde.';
$string['privacy:metadata:webservice_elediamcp_token:name'] = 'Die Bezeichnung des Tokens.';
$string['privacy:metadata:webservice_elediamcp_token:revoked'] = 'Ob das Token widerrufen wurde.';
$string['privacy:metadata:webservice_elediamcp_token:revokedby'] = 'Nutzer/in, die das Token widerrufen hat.';
$string['privacy:metadata:webservice_elediamcp_token:timecreated'] = 'Der Zeitpunkt, zu dem das Token erstellt wurde.';
$string['privacy:metadata:webservice_elediamcp_token:userid'] = 'Nutzer/in, als die sich das Token authentifiziert.';
$string['privacy:metadata:webservice_elediamcp_token:validuntil'] = 'Der Ablaufzeitpunkt des Tokens.';
$string['privacy:metadata:webservice_elediamcp_oauth_code'] = 'Kurzlebige OAuth-Autorisierungscodes, die während des Authorization-Code-+-PKCE-Flows ausgestellt werden. Es wird nur ein Hash des Codes gespeichert; die Zeilen werden beim Token-Tausch oder Ablauf gelöscht.';
$string['privacy:metadata:webservice_elediamcp_oauth_code:clientid'] = 'Der OAuth-Client, für den der Autorisierungscode ausgestellt wurde.';
$string['privacy:metadata:webservice_elediamcp_oauth_code:expires'] = 'Der Zeitpunkt, zu dem der Autorisierungscode abläuft.';
$string['privacy:metadata:webservice_elediamcp_oauth_code:scope'] = 'Die für den Autorisierungscode freigegebenen Scopes.';
$string['privacy:metadata:webservice_elediamcp_oauth_code:timecreated'] = 'Der Zeitpunkt, zu dem der Autorisierungscode ausgestellt wurde.';
$string['privacy:metadata:webservice_elediamcp_oauth_code:userid'] = 'Die Nutzer/in, die die Anfrage autorisiert hat.';

// Token management UI.
$string['claude_config_file'] = 'Speicherort der Konfigurationsdatei';
$string['claude_config_file_help'] = 'Unter macOS: <code>~/Library/Application Support/Claude/claude_desktop_config.json</code>. Unter Windows: <code>%APPDATA%\\Claude\\claude_desktop_config.json</code>. Legen Sie die Datei an, falls sie noch nicht existiert.';
$string['claude_connect_heading'] = 'MCP-Client verbinden (Claude Desktop)';
$string['claude_connect_intro'] = 'Claude Desktop und andere MCP-Clients, die nur stdio unterstützen, verbinden sich mit diesem Remote-Server über die Bridge <a href="https://www.npmjs.com/package/mcp-remote" target="_blank" rel="noopener">mcp-remote</a>, die auf dem Client-Rechner <a href="https://nodejs.org/" target="_blank" rel="noopener">Node.js</a> (für <code>npx</code>) voraussetzt. Fügen Sie den folgenden Ausschnitt in Ihre <code>claude_desktop_config.json</code> ein, ersetzen Sie das Token und starten Sie Claude Desktop anschließend vollständig neu.';
$string['claude_connect_serverurl'] = 'MCP-Server-URL';
$string['claude_connect_snippet'] = 'Claude-Desktop-Konfiguration';
$string['claude_connect_snippet_withtoken'] = 'Sofort nutzbare Claude-Desktop-Konfiguration (Ihr neues Token ist bereits eingetragen — kopieren Sie es jetzt)';
$string['claude_connect_tokenhint'] = 'Ersetzen Sie <code>{$a}</code> durch ein Token, das Sie oben erstellt haben.';
$string['suite_feature_desc'] = 'Lässt einen externen KI-Assistenten mit diesem Moodle arbeiten — unter Moodles Rollen und Rechten.';
$string['suite_feature_detail'] = 'MCP ist das offene Protokoll, über das KI-Assistenten fremde Werkzeuge erreichen. Dieses Plugin macht Moodle zu einem davon: Ein Assistent kann Kurse nachschlagen, Inhalte lesen und Material vorbereiten — immer als angemeldete Person und nie über deren Rechte hinaus. Administrierende legen fest, welche Werkzeuge offenstehen, und vergeben die Token.';
$string['suite_feature_key_1'] = 'Stellt ausgewählte Moodle-Fähigkeiten als MCP-Werkzeuge bereit.';
$string['suite_feature_key_2'] = 'Jeder Aufruf läuft als angemeldete Person, mit deren Rechten.';
$string['suite_feature_key_3'] = 'Administrierende wählen die Werkzeuge aus und vergeben Token.';
$string['suite_feature_name'] = 'Model Context Protocol';
$string['token_actions'] = 'Aktionen';
$string['token_create'] = 'Token erstellen';
$string['token_created'] = 'Erstellt';
$string['token_created_once'] = 'Ihr neues Token wurde erstellt. Kopieren Sie es jetzt — aus Sicherheitsgründen wird es nicht erneut angezeigt.';
$string['token_label'] = 'Bezeichnung';
$string['token_label_help'] = 'Ein Name, an dem Sie dieses Token später wiedererkennen, zum Beispiel das Gerät oder die Anwendung, die es verwendet.';
$string['token_lastused'] = 'Zuletzt verwendet';
$string['token_never'] = 'Nie';
$string['token_revoke'] = 'Widerrufen';
$string['token_revoke_confirm'] = 'Möchten Sie das Token „{$a}“ wirklich widerrufen? Jede Anwendung, die es verwendet, verliert sofort den Zugriff. Dieser Vorgang kann nicht rückgängig gemacht werden.';
$string['token_revoked_notice'] = 'Das Token wurde widerrufen.';
$string['token_service'] = 'Service';
$string['token_service_help'] = 'Der MCP-Service, auf den dieses Token Zugriff gewährt. Aufgelistet werden nur Services, die Ihre Administration für MCP aktiviert hat und die Sie verwenden dürfen.';
$string['token_status'] = 'Status';
$string['token_status_active'] = 'Aktiv';
$string['token_status_expired'] = 'Abgelaufen';
$string['token_status_revoked'] = 'Widerrufen';
$string['token_validuntil'] = 'Gültig bis';
$string['token_validuntil_help'] = 'Ein optionales Datum, ab dem das Token nicht mehr funktioniert. Lassen Sie das Feld deaktiviert, wenn das Token nie ablaufen soll.';
$string['tokens_heading'] = 'MCP-Tokens';
$string['tokens_existing_heading'] = 'Vorhandene Tokens';
$string['tokens_intro'] = 'Mit Tokens können MCP-Clients und KI-Agenten in Ihrem Namen auf Moodle zugreifen. Behandeln Sie jedes Token wie ein Passwort.';
$string['tokens_navlabel'] = 'MCP-Tokens';
$string['tokens_activate_service_button'] = 'MCP-Service aktivieren';
$string['tokens_activate_service_success'] = 'Der MCP-Service wurde aktiviert. Sie können jetzt Tokens erstellen.';
$string['tokens_no_services_configured'] = 'Auf dieser Website ist noch kein MCP-Service konfiguriert. Aktivieren Sie den Standard-MCP-Service, um Tokens erstellen zu können.';
$string['tokens_no_services_permitted'] = 'Auf dieser Website sind MCP-Services konfiguriert, Sie dürfen derzeit aber keinen davon verwenden. Meist ist der Service auf berechtigte Nutzer/innen beschränkt; wenden Sie sich an Ihre Administration, um Zugriff zu erhalten.';
$string['tokens_none'] = 'Sie haben noch keine MCP-Tokens erstellt.';

// Settings.
$string['configuration_error_invalid_origin'] = 'Jede CORS-Origin muss eine absolute http(s)-Origin sein, zum Beispiel https://app.example.com.';
$string['configuration_error_nonnegative'] = 'Geben Sie einen Wert von null oder höher ein.';
$string['configuration_error_wildcard_origin'] = 'Eine Wildcard-CORS-Origin (*) ist hier nicht erlaubt. Tragen Sie stattdessen einzelne vertrauenswürdige Origins ein.';
$string['configuration_heading'] = 'MCP-Konfiguration';
$string['configuration_hint'] = 'Konfigurieren Sie externe Services, Token-Richtlinien, Sicherheitsgrenzen und den MCP-Tool-Katalog.';
$string['configuration_saved'] = 'MCP-Konfiguration gespeichert.';
$string['configuration_security_heading'] = 'Sicherheit und Grenzwerte';
$string['configuration_services_heading'] = 'Services und Tokens';
$string['configuration_shell_link'] = 'MCP-Plugin-Shell öffnen';
$string['configuration_shell_link_desc'] = 'Die plugin-eigene MCP-Konfigurationsseite öffnen.';
$string['configuration_tag_mcp'] = 'MCP';
$string['configuration_tag_security'] = 'Sicherheit';
$string['configuration_oauth_heading'] = 'OAuth-2.1-Autorisierungsserver';
$string['configuration_tagline'] = 'Konfiguration';
$string['configuration_tools_heading'] = 'Tool-Katalog';
$string['setting_oauth_allow_dynamic_registration'] = 'Dynamische Client-Registrierung erlauben';
$string['setting_oauth_allow_dynamic_registration_desc'] = 'Wenn aktiviert, können sich MCP-Clients selbst als öffentliche PKCE-Clients registrieren (RFC 7591) und automatisch eine client_id erhalten. Für Zero-Configuration-Clients wie Claude Desktop erforderlich. Deaktivieren, um nur selbst bereitgestellte Clients zuzulassen.';
$string['setting_oauth_allow_dynamic_registration_help'] = $string['setting_oauth_allow_dynamic_registration_desc'];
$string['setting_oauth_code_ttl'] = 'Lebensdauer des Autorisierungscodes (Sekunden)';
$string['setting_oauth_code_ttl_desc'] = 'Wie lange ein ausgestellter Autorisierungscode gültig bleibt, bevor er gegen ein Token getauscht werden muss. Begrenzt auf 30–600 Sekunden. Standard 300.';
$string['setting_oauth_code_ttl_help'] = $string['setting_oauth_code_ttl_desc'];
$string['setting_oauth_enabled'] = 'OAuth 2.1 aktivieren (Authorization Code + PKCE)';
$string['setting_oauth_enabled_desc'] = 'Wenn aktiviert, veröffentlicht das Plugin einen OAuth-2.1-Autorisierungsserver, sodass konforme MCP-Clients einen Authorization-Code-+-PKCE-Flow abschließen und ein MCP-Token erhalten können, ohne ein manuell erstelltes Token. Bestehende Bearer-Tokens funktionieren unabhängig von dieser Einstellung weiter. Standardmäßig deaktiviert.';
$string['setting_oauth_enabled_help'] = $string['setting_oauth_enabled_desc'];
$string['setting_oauth_token_ttl'] = 'Lebensdauer des OAuth-Access-Tokens (Sekunden)';
$string['setting_oauth_token_ttl_desc'] = 'Ablauf, der auf über den OAuth-Flow ausgestellte Tokens angewendet wird. 0 für Tokens ohne Ablauf (Standard, wie manuell erstellte Tokens).';
$string['setting_oauth_token_ttl_help'] = $string['setting_oauth_token_ttl_desc'];
$string['setting_oauth_warning'] = 'Der OAuth-Flow erlaubt es jeder Person, die sich anmelden kann und die MCP-Capability besitzt, ein Token für einen Client zu erzeugen, der den Flow abschließt. Halten Sie die erlaubten CORS-Origins und die MCP-Service-Liste eng, und aktivieren Sie die dynamische Client-Registrierung nur, wenn Sie verstehen, dass sich Clients selbst registrieren können.';
$string['premium_status_active'] = 'Premium aktiv';
$string['premium_status_active_notice'] = 'Das Premium-Add-on schaltet den vollständigen kuratierten MCP-Tool-Katalog frei. Rohe Moodle-Webservice-Funktionen erfordern weiterhin die separate Einstellung unten.';
$string['premium_status_free'] = 'Free-Version';
$string['premium_status_free_notice'] = 'Die Free-Version stellt nur die MCP-Basis-Tools bereit. Installieren und aktivieren Sie das Add-on eLeDia.ai Tutor Premium mit dem Feature mcp_tools, um den vollständigen Katalog freizuschalten.';
$string['premium_status_free_tools'] = '{$a} Free-Tools';
$string['premium_status_heading'] = 'Edition und Tool-Zugriff';
$string['premium_status_intro'] = '{$a->edition}: {$a->freecount} Free-Tools sind verfügbar. Premium ergänzt {$a->premiumcount} weitere kuratierte Tools.';
$string['premium_status_premium_tool_list'] = 'Premium-Tools';
$string['premium_status_premium_tools'] = '{$a} Premium-Tools';
$string['setting_allow_token_in_query'] = 'Token im Query-String erlauben';
$string['setting_allow_token_in_query_desc'] = 'Wenn aktiviert, akzeptiert der MCP-Endpunkt das Token über den Query-Parameter <code>?wstoken=</code>. Standardmäßig deaktiviert, weil Tokens in URLs in Webserver-Logs, im Browserverlauf und in HTTP-Referer-Headern landen. Clients sollten den Header <code>Authorization: Bearer</code> verwenden.';
$string['setting_allow_token_in_query_help'] = $string['setting_allow_token_in_query_desc'];
$string['setting_allowed_origins'] = 'Erlaubte CORS-Origins';
$string['setting_allowed_origins_desc'] = 'Eine Origin pro Zeile (z. B. <code>https://app.example.com</code>). Mit <code>*</code> wird jede Origin erlaubt (nicht empfohlen). Bleibt das Feld leer, werden nur Anfragen von derselben Origin akzeptiert. Der MCP-Server prüft bei jeder Anfrage den <code>Origin</code>-Header und antwortet mit HTTP 403 für jede Origin, die nicht in dieser Liste steht.';
$string['setting_allowed_origins_help'] = $string['setting_allowed_origins_desc'];
$string['setting_enforce_mcp_service'] = 'Endpunkt auf MCP-Services beschränken';
$string['setting_enforce_mcp_service_desc'] = 'Wenn aktiviert (Standard), akzeptiert der MCP-Endpunkt nur Tokens, die zu einem konfigurierten externen MCP-Service gehören. Damit ist die Liste der MCP-Services die Zugriffsgrenze für den Endpunkt und nicht nur für die Ausstellung von Tokens. Deaktivieren Sie die Einstellung nur, wenn Sie Tokens verwenden müssen, die für andere Webservices ausgestellt wurden.';
$string['setting_enforce_mcp_service_help'] = $string['setting_enforce_mcp_service_desc'];
$string['setting_emergency_disable'] = 'Notabschaltung';
$string['setting_emergency_disable_desc'] = 'Wenn aktiviert, beantwortet der MCP-Endpunkt jede Anfrage mit HTTP 503 Service Unavailable. Nutzen Sie dies als vorübergehenden Notschalter bei einem Sicherheitsvorfall.';
$string['setting_emergency_disable_help'] = $string['setting_emergency_disable_desc'];
$string['setting_expose_raw_functions'] = 'Rohe Moodle-Webservice-Funktionen bereitstellen';
$string['setting_expose_raw_functions_desc'] = 'Wenn aktiviert, wird zusätzlich zu den kuratierten KI-nativen Tools jede externe Funktion des authentifizierten Service als MCP-Tool bereitgestellt. Ist die Einstellung deaktiviert, beschränkt sich der Umfang auf die KI-nativen Tools, was für produktive KI-Agenten empfohlen wird.';
$string['setting_expose_raw_functions_help'] = $string['setting_expose_raw_functions_desc'];
$string['setting_expose_raw_functions_warning'] = 'Sicherheitshinweis: Das Aktivieren der rohen Webservice-Funktionen erweitert den KI-Clients bereitgestellten Tool-Umfang erheblich. Verwenden Sie dies nur für kontrollierte Tests durch die Administration, nicht als Standard für produktive Agenten. Die für rohe Funktionen abgeleiteten Kennzeichnungen „nur lesend“ bzw. „verändernd“ sind lediglich Näherungswerte und dürfen nicht als Sicherheitsgrenze behandelt werden.';
$string['setting_max_request_size'] = 'Maximale Größe des Anfragetexts (Bytes)';
$string['setting_max_request_size_desc'] = 'Anfragen mit einem größeren Anfragetext als diesem Wert werden mit HTTP 413 abgelehnt. Standard: 1 MiB.';
$string['setting_max_request_size_help'] = $string['setting_max_request_size_desc'];
$string['setting_rate_limit_per_hour'] = 'Rate Limit pro Stunde (je Token)';
$string['setting_rate_limit_per_hour_desc'] = 'Maximale Anzahl von Anfragen pro Stunde für ein einzelnes Token. Standard: 600.';
$string['setting_rate_limit_per_hour_help'] = $string['setting_rate_limit_per_hour_desc'];
$string['setting_rate_limit_per_minute'] = 'Rate Limit pro Minute (je Token)';
$string['setting_rate_limit_per_minute_desc'] = 'Maximale Anzahl von Anfragen pro Minute für ein einzelnes Token. Standard: 60.';
$string['setting_rate_limit_per_minute_help'] = $string['setting_rate_limit_per_minute_desc'];
$string['setting_token_retention_days'] = 'Aufbewahrung widerrufener Tokens (Tage)';
$string['setting_token_retention_days_desc'] = 'Wie viele Tage der Auditeintrag eines widerrufenen MCP-Tokens aufbewahrt wird, bevor der geplante Aufräum-Task ihn löscht. Von Connectoren ausgestellte Tokens werden regelmäßig neu ausgestellt, sodass sich deren widerrufene Einträge ansammeln können. Setzen Sie den Wert auf 0, um jeden Eintrag eines widerrufenen Tokens dauerhaft zu behalten.';
$string['setting_token_retention_days_help'] = $string['setting_token_retention_days_desc'];
$string['setting_services'] = 'Externe MCP-Services';
$string['setting_services_desc'] = 'Die externen Services, die MCP-Tokens ausstellen dürfen. Nur hier ausgewählte Services können in der Token-Oberfläche für Nutzer/innen gewählt oder über die interne Token-API angesprochen werden. Legen Sie die Services zuerst unter <em>Website-Administration → Server → Webservices → Externe Services</em> an und aktivieren Sie sie anschließend hier.';
$string['setting_services_help'] = $string['setting_services_desc'];
$string['setting_tools_page_size'] = 'Standard-Seitengröße für tools/list';
$string['setting_tools_page_size_desc'] = 'Maximale Anzahl von Tools pro <code>tools/list</code>-Antwort. Größere Mengen werden über das Feld <code>nextCursor</code> paginiert.';
$string['setting_tools_page_size_help'] = $string['setting_tools_page_size_desc'];

// OAuth 2.1 authorization server.
$string['oauth_consent_allow'] = 'Zugriff erlauben';
$string['oauth_consent_deny'] = 'Ablehnen';
$string['oauth_consent_intro'] = '<strong>{$a->client}</strong> möchte in Ihrem Namen auf Moodle zugreifen ({$a->user}). Wenn Sie zustimmen, kann die Anwendung die MCP-Tools mit Ihren Berechtigungen nutzen.';
$string['oauth_consent_note'] = 'Mit der Zustimmung wird ein an Ihr Konto gebundenes MCP-Token erstellt. Sie können es jederzeit unter Einstellungen → MCP-Tokens widerrufen.';
$string['oauth_consent_scopes'] = 'Die Anwendung fordert folgenden Zugriff an:';
$string['oauth_consent_title'] = 'MCP-Client autorisieren';
$string['oauth_default_client_name'] = 'MCP-Client';
$string['oauth_err_access_denied'] = 'Sie haben die Autorisierungsanfrage abgelehnt.';
$string['oauth_err_client_id_required'] = 'Eine client_id ist erforderlich.';
$string['oauth_err_code_challenge'] = 'Eine gültige PKCE-code_challenge ist erforderlich (43–128 base64url-Zeichen).';
$string['oauth_err_code_client_mismatch'] = 'Der Autorisierungscode wurde nicht für diesen Client ausgestellt.';
$string['oauth_err_disabled'] = 'Der OAuth-Autorisierungsserver ist auf dieser Website nicht aktiviert.';
$string['oauth_err_expired_code'] = 'Der Autorisierungscode ist abgelaufen.';
$string['oauth_err_grant_type'] = 'Nicht unterstützter grant_type. Nur authorization_code wird unterstützt.';
$string['oauth_err_guest'] = 'Sie müssen sich mit einem vollständigen Moodle-Konto anmelden, um einen MCP-Client zu autorisieren. Gastzugang ist nicht zulässig.';
$string['oauth_err_invalid_code'] = 'Der Autorisierungscode ist ungültig.';
$string['oauth_err_invalid_registration_body'] = 'Der Registrierungs-Anfragetext muss ein JSON-Objekt sein.';
$string['oauth_err_missing_token_params'] = 'In der Token-Anfrage fehlen ein oder mehrere erforderliche Parameter.';
$string['oauth_err_no_service'] = 'Es ist kein MCP-Service konfiguriert, gegen den Tokens ausgestellt werden könnten.';
$string['oauth_err_pkce_failed'] = 'PKCE-Prüfung fehlgeschlagen.';
$string['oauth_err_pkce_method'] = 'code_challenge_method muss S256 sein.';
$string['oauth_err_public_client_only'] = 'Nur öffentliche Clients (token_endpoint_auth_method "none") werden unterstützt.';
$string['oauth_err_redirect_uri_invalid'] = 'Eine oder mehrere Redirect-URIs sind ungültig. Verwenden Sie eine absolute https-URI, eine http-URI auf einem Loopback-Host oder ein privates URI-Schema.';
$string['oauth_err_redirect_uri_mismatch'] = 'Die redirect_uri stimmt nicht mit einer registrierten Redirect-URI dieses Clients überein.';
$string['oauth_err_redirect_uri_required'] = 'Eine redirect_uri ist erforderlich, da der Client mehr als eine registrierte Redirect-URI hat.';
$string['oauth_err_redirect_uris_required'] = 'Mindestens eine redirect_uri ist erforderlich.';
$string['oauth_err_registration_disabled'] = 'Die dynamische Client-Registrierung ist auf dieser Website deaktiviert.';
$string['oauth_err_response_type'] = 'Nicht unterstützter response_type. Nur "code" wird unterstützt.';
$string['oauth_err_token_issue'] = 'Die Autorisierung konnte für Ihr Konto nicht abgeschlossen werden.';
$string['oauth_err_unknown_client'] = 'Unbekannter OAuth-Client.';
$string['oauth_err_unsupported_grant_registration'] = 'Nur der Grant-Typ authorization_code wird unterstützt.';
$string['oauth_privacy_codes_heading'] = 'MCP-OAuth-Autorisierungscodes';
$string['oauth_token_label'] = 'OAuth · {$a}';

// Server metadata.
$string['server_instructions'] = 'Moodle-MCP-Server mit kuratierten KI-nativen Tools.

Identität und Kontext: Rufen Sie zuerst moodle_me auf, um die Identität zu bestätigen, danach moodle_verify_user_context für die vollständige Prüfung von Rollen, Gruppen und Fähigkeiten.

Personensuche: moodle_find_user löst ein freies Namensfragment (z. B. "erika") in eine Liste von Moodle-Nutzer/innen mit konkreten IDs auf, die per Mitteilung erreichbar sind. Verwenden Sie moodle_find_user VOR moodle_send_message, wenn Sie die genaue Nutzer-ID des Empfängers nicht bereits kennen.

Kurssuche: moodle_my_courses listet eingeschriebene Kurse; moodle_search_courses durchsucht den öffentlichen Katalog; moodle_list_course_categories listet die Kursbereiche, in denen die Person Kurse anlegen darf (damit wird ein genannter Bereich zur category_id für moodle_create_course); moodle_course_contents listet Abschnitte und Aktivitäten eines Kurses auf; moodle_get_resource liefert den Inhalt einer Textseite, eines Buchkapitels, eines Textfelds, eines Links oder einer Datei anhand der cmid.

Feeds und Fortschritt: moodle_get_announcements für die neuesten Beiträge im Ankündigungsforum; moodle_calendar_upcoming für Termine; moodle_my_assignments für den Abgabestatus; moodle_my_grades für die Kursgesamtbewertung (oder je Element mit include_items + course_id).

Schreibtools: moodle_send_message akzeptiert to_user_id (bevorzugt), to_username (exakte Übereinstimmung) oder to_query (unscharf, nur bei genau einem Treffer). Zweistufiger Ablauf — rufen Sie das Tool zuerst ohne confirm auf, um eine Vorschau zu erhalten, und danach erneut mit confirm=true und der ausdrücklichen Freigabe der Nutzerin bzw. des Nutzers, um tatsächlich zu senden. Hartes Limit: 4000 Zeichen.

Nur lesende Tools können bedenkenlos automatisch aufgerufen werden; Schreibtools erfordern ein ausdrückliches Argument "confirm".';
$string['servername'] = 'Moodle MCP Server';

// Scheduled tasks.
$string['err_prompt_not_found'] = 'Der Prompt „{$a}“ wurde nicht gefunden oder ist für dieses Token nicht verfügbar.';
$string['err_prompt_missing_argument'] = 'Das Prompt-Argument „{$a}“ ist erforderlich.';
$string['err_prompt_unknown_argument'] = 'Das Prompt-Argument „{$a}“ wird nicht unterstützt.';
$string['err_prompt_invalid_argument'] = 'Das Prompt-Argument „{$a}“ hat einen ungültigen Typ.';
$string['err_prompt_arguments_object'] = 'Prompt-Argumente müssen ein Objekt sein.';
$string['err_internal_prompt_error'] = 'Der Prompt konnte wegen eines internen Fehlers nicht gerendert werden.';
$string['prompt_arg_titel'] = 'Optionaler Kurstitel.';
$string['prompt_arg_kurs_id'] = 'Moodle-Kurs-ID.';
$string['prompt_arg_anzahl_sektionen'] = 'Optionale Anzahl der Kursabschnitte.';
$string['prompt_arg_aktivitaet'] = 'Optionaler Aktivitätsname oder -ID, auf den fokussiert werden soll.';
$string['prompt_kurs_aus_dokument_title'] = 'Kurs aus Dokument erstellen';
$string['prompt_kurs_aus_dokument_description'] = 'Aus dem vom Nutzer angehängten Dokument eine Kursgliederung ableiten und sie nach ausdrücklicher Bestätigung anlegen.';
$string['prompt_kurs_aus_dokument_text'] = 'Verwende das vom Nutzer angehängte Dokument als Grundlage für eine Moodle-Kursgliederung. ' .
    'Schlage zuerst Titel, Abschnitte und Aktivitäten vor und bitte um Bestätigung des vollständigen Plans. ' .
    'Verwende danach moodle_create_course, moodle_manage_sections und moodle_create_activity mit ihrem zweistufigen Vorschau-/Bestätigungsablauf; umgehe die Bestätigung niemals.' .
    '{$a}';
$string['prompt_wochenueberblick_title'] = 'Wochenüberblick';
$string['prompt_wochenueberblick_description'] = 'Fasst anstehende Aufgaben, Bewertungen und Kalendertermine der authentifizierten lernenden Person zusammen.';
$string['prompt_wochenueberblick_text'] = 'Erstelle einen knappen Wochenüberblick für die authentifizierte Person. Rufe moodle_due_work, moodle_my_grades und moodle_calendar_upcoming auf, gruppiere nach Dringlichkeit und Kurs und unterscheide fehlende Daten klar von keinen Ergebnissen.';
$string['prompt_kurs_health_check_title'] = 'Kurs-Health-Check';
$string['prompt_kurs_health_check_description'] = 'Prüft einen Kurs auf Beteiligung, unbeantwortete Forenbeiträge und Bewertungsrückstände.';
$string['prompt_kurs_health_check_text'] = 'Prüfe den Moodle-Kurs {$a} mit moodle_course_health, moodle_unanswered_forum_posts und moodle_grading_queue. Fasse konkrete Risiken zusammen und schlage nächste Schritte vor, ohne Daten zu ändern.';
$string['prompt_bewertungs_session_title'] = 'Bewertungssitzung';
$string['prompt_bewertungs_session_description'] = 'Führt Lehrende durch eine fokussierte Bewertungssitzung mit ausdrücklicher Bestätigung vor jeder Bewertungsänderung.';
$string['prompt_bewertungs_session_text'] = 'Starte eine Bewertungssitzung für Kurs {$a->courseid}. Verwende moodle_grading_queue und moodle_read_submission, um Arbeiten auszuwählen und zu prüfen{$a->activity}. Präsentiere Feedback und vorgeschlagene Bewertungen, bitte um ausdrückliche Bestätigung und rufe erst danach moodle_grade_submission mit confirm=true auf.';
$string['prompt_kursmaterial_zusammenfassen_title'] = 'Kursmaterial zusammenfassen';
$string['prompt_kursmaterial_zusammenfassen_description'] = 'Fasst sichtbare Materialien eines Moodle-Kurses zusammen.';
$string['prompt_kursmaterial_zusammenfassen_text'] = 'Verwende moodle_course_contents und moodle_get_resource, um sichtbare Materialien für Kurs {$a} zu sammeln. Erstelle eine strukturierte Zusammenfassung mit Links oder Aktivitätsnamen und erfinde keine Inhalte, die die Tools nicht geliefert haben.';
$string['task_prune_revoked_tokens'] = 'Alte widerrufene MCP-Tokens aufräumen';
