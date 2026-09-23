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
 * OAuth 2.1 authorization endpoint for the MCP authorization server.
 *
 * Requires a logged-in Moodle user, validates the Authorization Code + PKCE
 * request, shows a consent screen, and on approval issues a single-use
 * authorization code bound to the user, client, redirect URI and PKCE challenge.
 *
 * @package     webservice_elediamcp
 * @author      Sven (eLeDia) <dev@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use webservice_elediamcp\local\oauth\oauth_exception;
use webservice_elediamcp\local\oauth\service;

$params = [
    'response_type' => optional_param('response_type', '', PARAM_RAW),
    'client_id' => optional_param('client_id', '', PARAM_RAW),
    'redirect_uri' => optional_param('redirect_uri', '', PARAM_RAW),
    'scope' => optional_param('scope', '', PARAM_RAW),
    'state' => optional_param('state', '', PARAM_RAW),
    'code_challenge' => optional_param('code_challenge', '', PARAM_RAW),
    'code_challenge_method' => optional_param('code_challenge_method', '', PARAM_RAW),
];

$pageurl = new moodle_url('/webservice/elediamcp/oauth/authorize.php', $params);
$PAGE->set_url($pageurl);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('oauth_consent_title', 'webservice_elediamcp'));
$PAGE->set_heading(get_string('oauth_consent_title', 'webservice_elediamcp'));

// Require a real, logged-in user (no auto guest). require_login preserves the full
// request URL across the login round-trip, so the OAuth parameters survive.
require_login(null, false);

/**
 * Render a terminal error page for a non-redirectable authorization failure.
 *
 * @param string $message Safe, human-readable message.
 * @return never
 */
function webservice_elediamcp_oauth_authorize_error(string $message) {
    global $OUTPUT, $PAGE;
    echo $OUTPUT->header();
    echo $OUTPUT->notification($message, \core\output\notification::NOTIFY_ERROR);
    echo $OUTPUT->footer();
    die;
}

if (!service::is_enabled()) {
    webservice_elediamcp_oauth_authorize_error(
        get_string('oauth_err_disabled', 'webservice_elediamcp')
    );
}

if (isguestuser()) {
    webservice_elediamcp_oauth_authorize_error(
        get_string('oauth_err_guest', 'webservice_elediamcp')
    );
}

// Validate the request. Non-redirectable failures render an error page; once the
// client and redirect URI are trusted, protocol failures redirect back with error.
try {
    $ctx = service::validate_authorization_request($params);
} catch (oauth_exception $ex) {
    if (!$ex->is_redirectable()) {
        webservice_elediamcp_oauth_authorize_error($ex->get_description());
    }
    redirect(new moodle_url(service::build_error_redirect(
        (string) $params['redirect_uri'],
        $ex->get_error_code(),
        $ex->get_description(),
        $params['state'] !== '' ? (string) $params['state'] : null
    )));
}

$state = $ctx->state;

// Process a consent decision.
if (optional_param('decision', '', PARAM_ALPHA) !== '') {
    require_sesskey();
    $decision = optional_param('decision', '', PARAM_ALPHA);
    if ($decision === 'allow') {
        $code = service::issue_code($ctx, (int) $USER->id);
        redirect(new moodle_url(service::build_success_redirect(
            (string) $ctx->redirecturi,
            $code,
            $state
        )));
    }
    // Denied.
    redirect(new moodle_url(service::build_error_redirect(
        (string) $ctx->redirecturi,
        'access_denied',
        get_string('oauth_err_access_denied', 'webservice_elediamcp'),
        $state
    )));
}

// Render the consent screen.
$clientname = format_string($ctx->client->clientname);

echo $OUTPUT->header();

$out = html_writer::start_div('webservice-elediamcp-oauth-consent');
$out .= html_writer::tag(
    'p',
    get_string('oauth_consent_intro', 'webservice_elediamcp', (object) [
        'client' => $clientname,
        'user' => fullname($USER),
    ])
);

$scopelist = $ctx->scope !== null && $ctx->scope !== ''
    ? explode(' ', $ctx->scope)
    : service::supported_scopes();
$items = '';
foreach ($scopelist as $scope) {
    $items .= html_writer::tag('li', s($scope));
}
$out .= html_writer::tag('p', get_string('oauth_consent_scopes', 'webservice_elediamcp'));
$out .= html_writer::tag('ul', $items);
$out .= html_writer::tag(
    'p',
    get_string('oauth_consent_note', 'webservice_elediamcp'),
    ['class' => 'text-muted']
);

$form = html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
foreach ($params as $name => $value) {
    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
}
$form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
$form .= html_writer::start_div('mt-3');
$form .= html_writer::tag('button', get_string('oauth_consent_allow', 'webservice_elediamcp'), [
    'type' => 'submit',
    'name' => 'decision',
    'value' => 'allow',
    'class' => 'btn btn-primary mr-2',
]);
$form .= html_writer::tag('button', get_string('oauth_consent_deny', 'webservice_elediamcp'), [
    'type' => 'submit',
    'name' => 'decision',
    'value' => 'deny',
    'class' => 'btn btn-secondary',
]);
$form .= html_writer::end_div();
$form .= html_writer::end_tag('form');

$out .= $form;
$out .= html_writer::end_div();

echo $out;
echo $OUTPUT->footer();
