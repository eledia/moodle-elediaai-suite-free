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
 * Tutor library management: list built-in presets and saved tutor profiles,
 * apply them to the site or a block instance, edit/duplicate/delete profiles,
 * and import/export tutor bundles (settings + images). Admin only.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/output/shell.php');

use block_elediaai_tutor\form\tutor_edit_form;
use block_elediaai_tutor\form\tutor_import_form;
use block_elediaai_tutor\local\branding;
use block_elediaai_tutor\local\presets;
use block_elediaai_tutor\local\registry;
use local_elediaai_chatengine\local\knowledge_scope;
use block_elediaai_tutor\local\tutor_apply;
use block_elediaai_tutor\local\tutor_io;
use block_elediaai_tutor\local\tutor_profile;
use block_elediaai_tutor\output\shell;

$context = \core\context\system::instance();
require_login();
require_capability('moodle/site:config', $context);

$action = optional_param('action', 'list', PARAM_ALPHA);
$baseurl = new moodle_url('/blocks/elediaai_tutor/manage_tutors.php');
$PAGE->set_context($context);
$PAGE->set_url($baseurl);
$PAGE->set_pagelayout('report');
$PAGE->blocks->show_only_fake_blocks(true);
$PAGE->set_title(get_string('managetutors', 'block_elediaai_tutor'));
$PAGE->set_heading(shell::is_available() ? '' : get_string('managetutors', 'block_elediaai_tutor'));
shell::require_css();

/**
 * Build the list of appliable/exportable sources: built-in presets then saved
 * profiles, as a select-friendly map of "type:id" => label.
 *
 * @return array<string,string>
 */
function block_elediaai_tutor_source_menu(): array {
    $menu = [];
    foreach (presets::menu() as $id => $label) {
        $menu['preset:' . $id] = get_string('tutor_preset', 'block_elediaai_tutor') . ': ' . $label;
    }
    foreach (tutor_profile::get_all() as $profile) {
        $menu['profile:' . $profile->id] = format_string($profile->name);
    }
    return $menu;
}

// File-streaming actions must run before any page output.
if ($action === 'export') {
    require_sesskey();
    $source = required_param('source', PARAM_RAW);
    $data = tutor_apply::source($source);
    if ($data === null) {
        throw new moodle_exception('error_import_invalid', 'block_elediaai_tutor');
    }
    [$type, $id] = array_pad(explode(':', $source, 2), 2, '');
    $name = $type === 'preset'
        ? get_string(presets::all()[$id]['name'], 'block_elediaai_tutor')
        : (string) (tutor_profile::get((int) $id)->name ?? 'tutor');
    $shortname = $type === 'preset' ? $id : (string) (tutor_profile::get((int) $id)->shortname ?? 'tutor');
    $path = tutor_io::export($name, $shortname, $data['settings'], $data['logo'], $data['avatar']);
    send_temp_file($path, basename($path));
}

if ($action === 'exportinstance') {
    require_sesskey();
    $blockid = required_param('blockid', PARAM_INT);
    $src = tutor_apply::instance_source($blockid);
    $path = tutor_io::export(
        'instance-' . $blockid,
        'instance_' . $blockid,
        $src['settings'],
        $src['logo'],
        $src['avatar']
    );
    send_temp_file($path, basename($path));
}

// Mutating actions (POST forms or sesskey-guarded confirmations).
if ($action === 'save') {
    $saveid = optional_param('id', 0, PARAM_INT);
    $existing = ($saveid > 0 && ($p = tutor_profile::get($saveid))) ? tutor_profile::settings($p) : [];
    $form = new tutor_edit_form($baseurl->out(false), ['id' => $saveid, 'settings' => $existing]);
    if ($form->is_cancelled()) {
        redirect($baseurl);
    }
    if ($data = $form->get_data()) {
        $settings = [];
        foreach (registry::all() as $key => $entry) {
            if ($entry['type'] === 'file') {
                continue;
            }
            $value = $data->{'cfg_' . $key} ?? '';
            if ($entry['type'] === 'coursescope' && is_array($value)) {
                // The autocomplete hands back option keys; one string is stored.
                $value = knowledge_scope::from_selection($value)->as_string();
            }
            if ($value !== '' && $value !== null) {
                $settings[$key] = $value;
            }
        }
        $id = (int) $data->id;
        if ($id > 0) {
            tutor_profile::update($id, $data->name, (string) $data->description, $settings);
        } else {
            $id = tutor_profile::create(
                $data->name,
                (string) $data->description,
                $settings,
                (string) $data->shortname
            );
        }
        // Save the uploaded logo/avatar into the profile's file areas.
        $syscontext = \core\context\system::instance();
        foreach (['logo' => branding::TUTOR_LOGO_FILEAREA, 'avatar' => branding::TUTOR_AVATAR_FILEAREA] as $field => $filearea) {
            if (!empty($data->$field)) {
                file_save_draft_area_files(
                    (int) $data->$field,
                    $syscontext->id,
                    'block_elediaai_tutor',
                    $filearea,
                    $id,
                    ['maxfiles' => 1, 'subdirs' => 0]
                );
            }
        }
        redirect($baseurl, get_string('tutor_saved', 'block_elediaai_tutor'));
    }
    // Validation failed: fall through and re-render the form below.
}

if ($action === 'delete') {
    require_sesskey();
    $id = required_param('id', PARAM_INT);
    if (optional_param('confirm', 0, PARAM_BOOL)) {
        tutor_profile::delete($id);
        redirect($baseurl, get_string('tutor_deleted', 'block_elediaai_tutor'));
    }
    $profile = tutor_profile::get($id);
    echo $OUTPUT->header();
    shell::open(shell::ACTIVE_TUTORS);
    echo $OUTPUT->confirm(
        get_string('tutor_delete_confirm', 'block_elediaai_tutor', format_string($profile->name ?? '')),
        new moodle_url($baseurl, ['action' => 'delete', 'id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]),
        $baseurl
    );
    shell::close();
    echo $OUTPUT->footer();
    die;
}

if ($action === 'duplicate') {
    require_sesskey();
    $source = required_param('source', PARAM_RAW);
    $data = tutor_apply::source($source);
    if ($data !== null) {
        [$type, $id] = array_pad(explode(':', $source, 2), 2, '');
        $name = ($type === 'preset'
            ? get_string(presets::all()[$id]['name'], 'block_elediaai_tutor')
            : (string) (tutor_profile::get((int) $id)->name ?? 'tutor'))
            . ' ' . get_string('tutor_copysuffix', 'block_elediaai_tutor');
        $newid = tutor_profile::create($name, '', $data['settings']);
        foreach (['logo' => branding::TUTOR_LOGO_FILEAREA, 'avatar' => branding::TUTOR_AVATAR_FILEAREA] as $role => $area) {
            if ($data[$role] instanceof \stored_file) {
                tutor_profile::copy_image_in($newid, $area, $data[$role]);
            }
        }
        redirect(new moodle_url($baseurl, ['action' => 'edit', 'id' => $newid]));
    }
    redirect($baseurl);
}

if ($action === 'applysite') {
    require_sesskey();
    $source = required_param('source', PARAM_RAW);
    if (optional_param('confirm', 0, PARAM_BOOL)) {
        $data = tutor_apply::source($source);
        if ($data !== null) {
            tutor_apply::to_site($data['settings'], $data['logo'], $data['avatar']);
        }
        redirect($baseurl, get_string('tutor_applied_site', 'block_elediaai_tutor'));
    }
    echo $OUTPUT->header();
    shell::open(shell::ACTIVE_TUTORS);
    echo $OUTPUT->confirm(
        get_string('tutor_applysite_confirm', 'block_elediaai_tutor'),
        new moodle_url($baseurl, ['action' => 'applysite', 'source' => $source,
            'confirm' => 1, 'sesskey' => sesskey()]),
        $baseurl
    );
    shell::close();
    echo $OUTPUT->footer();
    die;
}

if ($action === 'applyinstance') {
    require_sesskey();
    $blockid = required_param('blockid', PARAM_INT);
    $source = required_param('source', PARAM_RAW);
    $data = tutor_apply::source($source);
    if ($data !== null) {
        tutor_apply::to_instance($blockid, $data['settings'], $data['logo'], $data['avatar']);
        redirect($baseurl, get_string('tutor_applied_instance', 'block_elediaai_tutor'));
    }
    redirect($baseurl);
}

if ($action === 'importdo') {
    $blockid = optional_param('blockid', 0, PARAM_INT);
    $form = new tutor_import_form($baseurl->out(false), ['blockid' => $blockid]);
    if ($form->is_cancelled()) {
        redirect($baseurl);
    }
    if ($data = $form->get_data()) {
        $tmpdir = make_request_directory();
        $zippath = $tmpdir . '/bundle.zip';
        $form->save_file('bundle', $zippath, true);
        $bundle = tutor_io::import($zippath);
        if ($blockid > 0) {
            tutor_apply::to_instance_from_bundle($blockid, $bundle);
            redirect($baseurl, get_string('tutor_applied_instance', 'block_elediaai_tutor'));
        }
        $newid = tutor_profile::create(
            $bundle['name'] ?: 'import',
            '',
            $bundle['settings'],
            $bundle['shortname']
        );
        block_elediaai_tutor_stage_images($newid, $bundle);
        redirect(
            new moodle_url($baseurl, ['action' => 'edit', 'id' => $newid]),
            get_string('tutor_imported', 'block_elediaai_tutor')
        );
    }
    // Fall through to render the import form on validation failure.
    $action = 'import';
}

/**
 * Store a bundle's parsed images onto a profile.
 *
 * @param int $profileid Target profile id.
 * @param array $bundle Parsed bundle from tutor_io::import().
 * @return void
 */
function block_elediaai_tutor_stage_images(int $profileid, array $bundle): void {
    foreach (['logo' => branding::TUTOR_LOGO_FILEAREA, 'avatar' => branding::TUTOR_AVATAR_FILEAREA] as $role => $area) {
        if (!empty($bundle[$role])) {
            tutor_profile::store_image(
                $profileid,
                $area,
                $bundle[$role]['filename'],
                $bundle[$role]['content']
            );
        }
    }
}

// Rendering.
echo $OUTPUT->header();
shell::open(shell::ACTIVE_TUTORS);

if (!shell::is_available()) {
    echo $OUTPUT->heading(get_string('managetutors', 'block_elediaai_tutor'));
}

if ($action === 'new' || $action === 'edit') {
    $id = optional_param('id', 0, PARAM_INT);
    $profile = $id > 0 ? tutor_profile::get($id) : null;
    $existing = $profile ? tutor_profile::settings($profile) : [];
    $form = new tutor_edit_form($baseurl->out(false), ['id' => $id, 'settings' => $existing]);
    $defaults = (object) ['id' => $id];
    if ($profile) {
        $defaults->name = $profile->name;
        $defaults->shortname = $profile->shortname;
        $defaults->description = $profile->description;
        foreach ($existing as $key => $value) {
            $defaults->{'cfg_' . $key} = $value;
        }
        $syscontext = \core\context\system::instance();
        foreach (['logo' => branding::TUTOR_LOGO_FILEAREA, 'avatar' => branding::TUTOR_AVATAR_FILEAREA] as $field => $filearea) {
            $draftid = file_get_submitted_draft_itemid($field);
            file_prepare_draft_area(
                $draftid,
                $syscontext->id,
                'block_elediaai_tutor',
                $filearea,
                $id,
                ['maxfiles' => 1, 'subdirs' => 0]
            );
            $defaults->$field = $draftid;
        }
    }
    $form->set_data($defaults);
    echo html_writer::start_div('eat-admin eat-admin-form-page');
    echo html_writer::div(
        html_writer::link(
            $baseurl,
            \block_elediaai_tutor\local\icon::render('arrow-left') .
            html_writer::span(get_string('tutor_back_to_library', 'block_elediaai_tutor')),
            ['class' => 'lh-btn--secondary eat-back-link']
        ),
        'eat-form-nav'
    );
    echo html_writer::tag(
        'h3',
        $profile
            ? get_string('tutor_edit_title', 'block_elediaai_tutor', format_string($profile->name))
            : get_string('tutor_create_title', 'block_elediaai_tutor'),
        ['class' => 'eat-section-title eat-section-title--form']
    );
    echo html_writer::start_div('eat-form-card');
    $form->display();
    echo html_writer::end_div();
    echo html_writer::end_div();
    shell::close();
    echo $OUTPUT->footer();
    die;
}

if ($action === 'import') {
    $blockid = optional_param('blockid', 0, PARAM_INT);
    echo html_writer::start_div('eat-admin');
    echo html_writer::div(
        \block_elediaai_tutor\local\icon::render('upload') .
        html_writer::span(get_string('tutor_import_help', 'block_elediaai_tutor')),
        'eat-admin-intro'
    );
    $form = new tutor_import_form(
        new moodle_url($baseurl, ['action' => 'importdo']),
        ['blockid' => $blockid]
    );
    $form->display();
    echo html_writer::end_div();
    shell::close();
    echo $OUTPUT->footer();
    die;
}

// Default: the library listing.
echo html_writer::start_div('eat-admin');

// Saved site tutors first: these are the editable working objects.
$libraryaction = static function (moodle_url $url, string $label, string $fa): string {
    return html_writer::link(
        $url,
        \block_elediaai_tutor\local\icon::render($fa) .
        html_writer::span($label, 'sr-only'),
        [
            'class' => 'lh-icon-action',
            'aria-label' => $label,
            'title' => $label,
        ]
    );
};
echo html_writer::div(
    html_writer::tag('h3', get_string('nav_tutors', 'block_elediaai_tutor'), ['class' => 'eat-section-title']) .
    html_writer::div(
        $libraryaction(
            new moodle_url($baseurl, ['action' => 'new']),
            get_string('tutor_new', 'block_elediaai_tutor'),
            'plus'
        ) .
        $libraryaction(
            new moodle_url($baseurl, ['action' => 'import']),
            get_string('tutor_import', 'block_elediaai_tutor'),
            'upload'
        ),
        'lh-row-actions eat-section-actions'
    ),
    'eat-section-head'
);
echo html_writer::start_div('eat-tutor-grid');
foreach (tutor_profile::get_all() as $profile) {
    echo block_elediaai_tutor_tutor_card(
        $baseurl,
        'profile:' . $profile->id,
        $profile->name,
        get_string('tutor_custom', 'block_elediaai_tutor'),
        'custom',
        tutor_profile::settings($profile),
        true,
        (int) $profile->id
    );
}
echo html_writer::end_div();

// Built-in templates second: read-only starting points.
echo html_writer::tag(
    'h3',
    get_string('tutor_templates', 'block_elediaai_tutor'),
    ['class' => 'eat-section-title']
);
echo html_writer::start_div('eat-tutor-grid');
foreach (presets::menu() as $pid => $plabel) {
    echo block_elediaai_tutor_tutor_card(
        $baseurl,
        'preset:' . $pid,
        $plabel,
        get_string('tutor_preset', 'block_elediaai_tutor'),
        'preset',
        presets::settings($pid),
        false,
        0
    );
}
echo html_writer::end_div();

// Block instances: per-instance apply / export / import, as cards.
$instances = tutor_apply::instance_records();
if ($instances) {
    $sources = block_elediaai_tutor_source_menu();
    $actionicon = static function (moodle_url $url, string $label, string $fa, string $extra = ''): string {
        return html_writer::link(
            $url,
            \block_elediaai_tutor\local\icon::render($fa) .
            html_writer::span($label, 'sr-only'),
            [
                'class' => trim('lh-icon-action ' . $extra),
                'aria-label' => $label,
                'title' => $label,
            ]
        );
    };
    echo html_writer::tag(
        'h3',
        get_string('tutor_instances', 'block_elediaai_tutor'),
        ['class' => 'eat-section-title']
    );
    echo html_writer::start_div('eat-instance-grid');
    foreach ($instances as $bi) {
        $parent = \core\context\block::instance($bi->id)->get_parent_context();
        $location = $parent ? $parent->get_context_name(false) : get_string('system', 'admin');
        $src = tutor_apply::instance_source((int) $bi->id);

        $applyurl = new moodle_url($baseurl, ['action' => 'applyinstance', 'blockid' => $bi->id]);
        $applyform = html_writer::tag(
            'form',
            html_writer::input_hidden_params($applyurl) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
            html_writer::div(
                html_writer::select(
                    $sources,
                    'source',
                    '',
                    ['' => get_string('choosedots')],
                    ['class' => 'lh-plugin-select']
                ),
                'lh-plugin-select-wrap'
            ) .
            html_writer::tag(
                'button',
                \block_elediaai_tutor\local\icon::render('check') .
                html_writer::span(get_string('tutor_apply', 'block_elediaai_tutor'), 'sr-only'),
                [
                    'type' => 'submit',
                    'class' => 'lh-icon-action eat-applyinstance-action',
                    'aria-label' => get_string('tutor_apply', 'block_elediaai_tutor'),
                    'title' => get_string('tutor_apply', 'block_elediaai_tutor'),
                ]
            ),
            ['method' => 'post', 'action' => $applyurl->out_omit_querystring(), 'class' => 'eat-instance-apply']
        );

        $links = $actionicon(
            new moodle_url($baseurl, ['action' => 'exportinstance', 'blockid' => $bi->id, 'sesskey' => sesskey()]),
            get_string('tutor_export', 'block_elediaai_tutor'),
            'download'
        ) .
            $actionicon(
                new moodle_url($baseurl, ['action' => 'import', 'blockid' => $bi->id]),
                get_string('tutor_import', 'block_elediaai_tutor'),
                'upload'
            );

        echo html_writer::div(
            html_writer::div(
                \block_elediaai_tutor\local\icon::render('cube') .
                html_writer::span(s($location)) .
                html_writer::span('#' . $bi->id, 'eat-instance-id'),
                'eat-instance-loc'
            ) .
            block_elediaai_tutor_preview($src['settings'], get_string('pluginname', 'block_elediaai_tutor')) .
            $applyform .
            html_writer::div($links, 'lh-row-actions eat-actions eat-instance-icons'),
            'eat-instance-card'
        );
    }
    echo html_writer::end_div();
}

echo html_writer::end_div(); // End of .eat-admin wrapper.
shell::close();
echo $OUTPUT->footer();

/**
 * Resolve a colour from a settings map to a safe hex value, or a default.
 *
 * @param array $settings Registry-key => value map.
 * @param string $key Registry key of a colour token.
 * @param string $default Fallback hex.
 * @return string A safe `#rrggbb` value.
 */
function block_elediaai_tutor_pcol(array $settings, string $key, string $default): string {
    $value = isset($settings[$key]) ? branding::sanitise_colour((string) $settings[$key]) : null;
    return $value ?? $default;
}

/**
 * A miniature chat mockup rendered from a tutor's own palette.
 *
 * @param array $settings Registry-key => value map.
 * @param string $name Header label (raw; escaped here).
 * @return string HTML.
 */
function block_elediaai_tutor_preview(array $settings, string $name): string {
    $accent = block_elediaai_tutor_pcol($settings, 'brandaccent', '#1e3f59');
    $accentfg = block_elediaai_tutor_pcol($settings, 'tok_accentcontrast', '#ffffff');
    $body = block_elediaai_tutor_pcol($settings, 'brandsurface', '#f4f6f8');
    $botbg = block_elediaai_tutor_pcol($settings, 'brandbotbubble', '#ffffff');
    $botfg = block_elediaai_tutor_pcol($settings, 'tok_botfg', '#1e3f59');
    $userbg = block_elediaai_tutor_pcol($settings, 'brandbubble', '#fce9db');
    $userfg = block_elediaai_tutor_pcol($settings, 'tok_userfg', '#1e3f59');

    $head = html_writer::div(
        html_writer::span('', 'eat-preview-dot') . html_writer::span(s($name)),
        'eat-preview-head',
        ['style' => "background:$accent;color:$accentfg;"]
    );
    $bubbles = html_writer::div(
        'Aa',
        'eat-preview-bubble eat-preview-bot',
        ['style' => "background:$botbg;color:$botfg;"]
    ) .
        html_writer::div(
            'Aa',
            'eat-preview-bubble eat-preview-user',
            ['style' => "background:$userbg;color:$userfg;"]
        );
    return html_writer::div(
        $head . html_writer::div($bubbles, 'eat-preview-body'),
        'eat-preview',
        ['style' => "background:$body;"]
    );
}

/**
 * A tutor preview card (palette mockup + name + type + actions).
 *
 * @param moodle_url $baseurl Page base URL.
 * @param string $source 'preset:id' or 'profile:id'.
 * @param string $name Display name (raw; escaped here).
 * @param string $typelabel Localised type label.
 * @param string $typeclass 'preset' or 'custom' (drives the badge colour).
 * @param array $settings The tutor's settings (for the preview).
 * @param bool $custom Whether it is an editable/deletable saved profile.
 * @param int $profileid Profile id (0 for presets).
 * @return string HTML.
 */
function block_elediaai_tutor_tutor_card(
    moodle_url $baseurl,
    string $source,
    string $name,
    string $typelabel,
    string $typeclass,
    array $settings,
    bool $custom,
    int $profileid
): string {
    $sk = ['sesskey' => sesskey()];
    $icon = static function (moodle_url $url, string $label, string $fa, string $extra = ''): string {
        return html_writer::link(
            $url,
            \block_elediaai_tutor\local\icon::render($fa) .
            html_writer::span($label, 'sr-only'),
            [
                'class' => trim('lh-icon-action ' . $extra),
                'aria-label' => $label,
                'title' => $label,
            ]
        );
    };

    $actions = html_writer::link(
        new moodle_url($baseurl, ['action' => 'applysite', 'source' => $source] + $sk),
        \block_elediaai_tutor\local\icon::render('check') .
            html_writer::span(get_string('tutor_applysite', 'block_elediaai_tutor'), 'sr-only'),
        [
            'class' => 'lh-icon-action eat-applysite-action',
            'aria-label' => get_string('tutor_applysite', 'block_elediaai_tutor'),
            'title' => get_string('tutor_applysite', 'block_elediaai_tutor'),
        ]
    );
    $actions .= html_writer::div(
        $icon(
            new moodle_url($baseurl, ['action' => 'export', 'source' => $source] + $sk),
            get_string('tutor_export', 'block_elediaai_tutor'),
            'download'
        ) .
        $icon(
            new moodle_url($baseurl, ['action' => 'duplicate', 'source' => $source] + $sk),
            get_string('tutor_duplicate', 'block_elediaai_tutor'),
            'clone'
        ),
        'lh-row-actions eat-card-icons'
    );
    if ($custom) {
        $actions .= html_writer::div(
            $icon(
                new moodle_url($baseurl, ['action' => 'edit', 'id' => $profileid]),
                get_string('edit'),
                'pencil'
            ) .
            $icon(
                new moodle_url($baseurl, ['action' => 'delete', 'id' => $profileid] + $sk),
                get_string('delete'),
                'trash',
                'lh-icon-action--danger'
            ),
            'lh-row-actions eat-card-icons'
        );
    }

    $persona = (string) ($settings['persona'] ?? '');
    $tone = (string) ($settings['persona_tone'] ?? '');
    $subtitle = trim($tone);

    $body = html_writer::div(
        html_writer::div(
            html_writer::span(s($name), 'eat-tutor-name') .
            html_writer::span($typelabel, 'eat-badge eat-badge-' . $typeclass),
            'eat-tutor-head'
        ) .
        ($subtitle !== '' ? html_writer::div(s($subtitle), 'eat-tutor-persona') : '') .
        html_writer::div($actions, 'eat-actions'),
        'eat-tutor-body'
    );

    return html_writer::div(
        block_elediaai_tutor_preview($settings, $persona !== '' ? $persona : $name) . $body,
        'eat-tutor-card'
    );
}
