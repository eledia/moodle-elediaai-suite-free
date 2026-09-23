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
 * Plugin-shell editor for a single eLeDia.ai Tutor block instance.
 *
 * @package     block_elediaai_tutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once(__DIR__ . '/classes/output/shell.php');

use block_elediaai_tutor\local\branding;
use block_elediaai_tutor\local\chat_mode;
use block_elediaai_tutor\local\formhelper;
use block_elediaai_tutor\local\registry;
use block_elediaai_tutor\local\security;
use local_elediaai_chatengine\local\token_provider;
use block_elediaai_tutor\local\widget;
use block_elediaai_tutor\output\shell;

/**
 * Moodle form for the in-shell instance editor.
 */
class block_elediaai_tutor_instance_shell_form extends moodleform {
    /** @var int Block instance id. */
    private int $blockid;

    /** @var \core\context\block Block context. */
    private \core\context\block $blockcontext;

    /** @var stdClass Existing block config. */
    private stdClass $config;

    /** @var int Parent course id, or 0. */
    private int $courseid;

    /**
     * Constructor.
     *
     * @param moodle_url|string $action Form action.
     * @param array<string,mixed> $customdata Custom form data.
     */
    public function __construct($action = null, $customdata = null) {
        $this->blockid = (int) ($customdata['blockid'] ?? 0);
        $this->blockcontext = $customdata['blockcontext'];
        $this->config = $customdata['config'];
        $this->courseid = (int) ($customdata['courseid'] ?? 0);
        parent::__construct($action, $customdata);
    }

    /**
     * Define form fields.
     *
     * @return void
     */
    protected function definition(): void {
        formhelper::register_colour_element();
        $mform = $this->_form;

        $mform->addElement(
            'header',
            'quicksettings',
            get_string('instance_shell_quicksettings', 'block_elediaai_tutor')
        );
        $mform->setExpanded('quicksettings', true);

        $mform->addElement('text', 'config_title', get_string('config_title', 'block_elediaai_tutor'));
        $mform->setType('config_title', PARAM_TEXT);
        $mform->setDefault('config_title', get_string('pluginname', 'block_elediaai_tutor'));

        $mform->addElement('text', 'config_dailylimit', get_string('config_dailylimit', 'block_elediaai_tutor'));
        $mform->setType('config_dailylimit', PARAM_INT);
        $mform->setDefault('config_dailylimit', -1);
        $mform->addHelpButton('config_dailylimit', 'config_dailylimit', 'block_elediaai_tutor');

        $mform->addElement(
            'static',
            'instance_tools',
            '',
            html_writer::link(
                new moodle_url(
                    '/blocks/elediaai_tutor/instance_tutor.php',
                    ['blockid' => $this->blockid]
                ),
                get_string('instancetutor_link', 'block_elediaai_tutor'),
                ['class' => 'btn btn-secondary']
            )
        );

        foreach (registry::groups() as $group) {
            $exposed = array_filter(
                registry::group_keys($group),
                static fn(string $key): bool => registry::is_exposed($key)
            );
            // The knowledge group always has a section, even when its registry
            // field is not exposed: two of the three settings on that card are
            // fixed fields and must not disappear with it.
            if (empty($exposed) && $group !== 'knowledge') {
                continue;
            }
            $mform->addElement(
                'header',
                'insgroup_' . $group,
                get_string('reggroup_' . $group, 'block_elediaai_tutor')
            );
            if ($group === 'knowledge') {
                $this->add_knowledge_fields($mform);
            }
            // Expanded by default: the settings-hub JS shows one section at a time,
            // so collapsed groups would hide content behind the active card.
            $mform->setExpanded('insgroup_' . $group, true);
            foreach ($exposed as $key) {
                $this->add_instance_field($mform, $key, registry::get($key));
            }
        }

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * The three settings that decide what this tutor may draw on.
     *
     * They belong together and now share a card of their own: whether the
     * surrounding course counts, whether answers come from material at all,
     * and which courses the material may come from. Two of them are fixed
     * fields, the third comes from the registry -- the header is drawn by the
     * loop in {@see definition()} and these are added under it.
     *
     * @param MoodleQuickForm $mform The form.
     * @return void
     */
    private function add_knowledge_fields($mform): void {
        $mform->addElement(
            'selectyesno',
            'config_passcoursecontext',
            get_string('config_passcoursecontext', 'block_elediaai_tutor')
        );
        $mform->setDefault('config_passcoursecontext', 1);
        $mform->addHelpButton('config_passcoursecontext', 'config_passcoursecontext', 'block_elediaai_tutor');

        $grounding = chat_mode::ingestion_available($this->effective_courseid());
        $llmallowed = chat_mode::is_llm_allowed();
        // Grounded answers call back into Moodle, so they require the MCP connector:
        // never offer the grounded option when webservice_elediamcp is absent.
        $connector = token_provider::is_connector_available();
        if ($grounding && !$connector) {
            // The course is indexed (grounding is possible) but the connector is
            // missing, so grounded mode cannot be offered — explain why instead.
            $mform->addElement(
                'static',
                'ragmode_note',
                get_string('config_ragmode', 'block_elediaai_tutor'),
                get_string('error_connector_missing', 'block_elediaai_tutor')
            );
        } else if ($llmallowed && $grounding) {
            $mform->addElement(
                'select',
                'config_ragmode',
                get_string('config_ragmode', 'block_elediaai_tutor'),
                [
                chat_mode::MODE_GROUNDED => get_string('ragmode_grounded', 'block_elediaai_tutor'),
                chat_mode::MODE_LLMONLY => get_string('ragmode_llmonly', 'block_elediaai_tutor'),
                ]
            );
            $mform->setDefault('config_ragmode', chat_mode::MODE_GROUNDED);
            $mform->addHelpButton('config_ragmode', 'config_ragmode', 'block_elediaai_tutor');
        } else if (!$grounding) {
            $mform->addElement(
                'static',
                'ragmode_note',
                get_string('config_ragmode', 'block_elediaai_tutor'),
                $llmallowed
                ? get_string('config_ragmode_nokb', 'block_elediaai_tutor')
                : get_string('llmonly_unavailable', 'block_elediaai_tutor')
            );
        }
    }

    /**
     * Add one registry-backed instance field.
     *
     * @param MoodleQuickForm $mform Form object.
     * @param string $key Registry key.
     * @param array $entry Registry descriptor.
     * @return void
     */
    private function add_instance_field($mform, string $key, array $entry): void {
        $field = 'config_' . $key;
        $label = $this->reglabel($key, $entry);

        switch ($entry['type']) {
            case 'file':
                $mform->addElement('filemanager', $field, $label, null, [
                    'maxfiles' => 1,
                    'subdirs' => 0,
                    'accepted_types' => ['.png', '.jpg', '.jpeg', '.svg', '.webp', '.gif'],
                ]);
                break;
            case 'colour':
                $mform->addElement('eatcolour', $field, $label);
                $mform->setType($field, PARAM_TEXT);
                break;
            case 'textarea':
                $mform->addElement('textarea', $field, $label, ['rows' => 3, 'cols' => 50]);
                $mform->setType($field, PARAM_TEXT);
                break;
            case 'select':
                $options = ['' => get_string('config_usesite', 'block_elediaai_tutor')];
                foreach ($entry['options'] as $value => $optkey) {
                    $options[$value] = get_string($optkey, 'block_elediaai_tutor');
                }
                $mform->addElement('select', $field, $label, $options);
                $mform->setDefault($field, '');
                break;
            case 'checkbox':
                $mform->addElement('select', $field, $label, [
                    '' => get_string('config_usesite', 'block_elediaai_tutor'),
                    '1' => get_string('yes'),
                    '0' => get_string('no'),
                ]);
                $mform->setDefault($field, '');
                break;
            case 'coursescope':
                $mform->addElement(
                    'autocomplete',
                    $field,
                    $label,
                    \local_elediaai_chatengine\local\knowledge_scope::options(),
                    ['multiple' => true]
                );
                // What the saved selection amounts to, where there is one.
                $summary = \local_elediaai_chatengine\local\knowledge_scope::index_summary(
                    \local_elediaai_chatengine\local\knowledge_scope::parse(
                        (string) ($this->config->$key ?? '')
                    )
                );
                if ($summary !== null) {
                    $mform->addElement('static', $field . 'summary', '', $summary);
                }
                break;

            default:
                if (!empty($entry['choices'])) {
                    $current = isset($this->config->$key) ? (string) $this->config->$key : null;
                    $mform->addElement(
                        'select',
                        $field,
                        $label,
                        registry::choice_select_options(
                            $key,
                            get_string('config_usesite', 'block_elediaai_tutor'),
                            $current
                        )
                    );
                    $mform->setDefault($field, '');
                } else {
                    $mform->addElement('text', $field, $label);
                    $mform->setType($field, PARAM_TEXT);
                }
                break;
        }

        if (get_string_manager()->string_exists($field . '_help', 'block_elediaai_tutor')) {
            $mform->addHelpButton($field, $field, 'block_elediaai_tutor');
        } else if (get_string_manager()->string_exists('reg_' . $key . '_help', 'block_elediaai_tutor')) {
            // A registry key may bring its own help under its registry name,
            // so one text serves every form the registry is rendered in.
            $mform->addHelpButton($field, 'reg_' . $key, 'block_elediaai_tutor');
        }
    }

    /**
     * A knowledge base may only be narrowed, never widened.
     *
     * This is the form a teacher actually reaches: the standard block
     * configuration redirects here (see hook_callbacks), so the check has to
     * be here and not only on the form it redirects away from.
     *
     * @param array $data Submitted values.
     * @param array $files Submitted files.
     * @return array Errors keyed by field name.
     */
    public function validation($data, $files) {
        global $USER;
        $errors = parent::validation($data, $files);

        $wish = \local_elediaai_chatengine\local\knowledge_scope::from_selection(
            (array) ($data['config_coursescope'] ?? [])
        );
        $rejected = \local_elediaai_chatengine\local\knowledge_scope::rejected_for($wish, (int) $USER->id);
        if ($rejected !== []) {
            $errors['config_coursescope'] = get_string(
                'scope_notallowed',
                'local_elediaai_chatengine',
                \local_elediaai_chatengine\local\knowledge_scope::label_rejected($rejected)
            );
        }

        return $errors;
    }

    /**
     * Friendly field label.
     *
     * @param string $key Registry key.
     * @param array $entry Registry descriptor.
     * @return string
     */
    private function reglabel(string $key, array $entry): string {
        if (get_string_manager()->string_exists('reg_' . $key, 'block_elediaai_tutor')) {
            return get_string('reg_' . $key, 'block_elediaai_tutor');
        }
        return (string) ($entry['token'] ?? $key);
    }

    /**
     * Prepare defaults and file-manager drafts.
     *
     * @param array|stdClass $defaults Defaults.
     * @return void
     */
    public function set_data($defaults): void {
        $filemap = [
            'config_logo' => [branding::INSTANCE_LOGO_FILEAREA, 'logo'],
            'config_avatar' => [branding::INSTANCE_AVATAR_FILEAREA, 'avatar'],
        ];
        foreach ($filemap as $field => [$filearea, $key]) {
            if (!registry::is_exposed($key)) {
                continue;
            }
            $draftid = file_get_submitted_draft_itemid($field);
            file_prepare_draft_area(
                $draftid,
                $this->blockcontext->id,
                'block_elediaai_tutor',
                $filearea,
                0,
                ['maxfiles' => 1, 'subdirs' => 0]
            );
            $defaults->$field = $draftid;
        }

        // The knowledge base is stored as one string and shown as a list of
        // option keys. Without this the field renders empty although a
        // selection is saved -- which reads as "the setting did not stick".
        foreach (registry::all() as $key => $entry) {
            if (($entry['type'] ?? '') !== 'coursescope') {
                continue;
            }
            $field = 'config_' . $key;
            $stored = (string) ($defaults->$field ?? '');
            $defaults->$field = $stored === ''
                ? []
                : explode(',', \local_elediaai_chatengine\local\knowledge_scope::parse($stored)->as_string());
        }

        parent::set_data($defaults);
    }

    /**
     * Resolve the course id used for the grounding-mode availability check.
     *
     * @return int Course id, or 0 for global chat.
     */
    private function effective_courseid(): int {
        $passcontext = !isset($this->config->passcoursecontext) || (int) $this->config->passcoursecontext === 1;
        if ($passcontext && security::course_chat_enabled() && $this->courseid > 0) {
            return $this->courseid;
        }
        return 0;
    }
}

$blockid = required_param('blockid', PARAM_INT);

require_login();

global $DB, $OUTPUT, $PAGE;

$record = $DB->get_record('block_instances', ['id' => $blockid, 'blockname' => 'elediaai_tutor'], '*', MUST_EXIST);
$blockcontext = \core\context\block::instance($blockid);
require_capability('block/elediaai_tutor:manage', $blockcontext);

$returnurl = new moodle_url('/blocks/elediaai_tutor/manage_tutors.php');
// Resolves course and activity for every placement, including blocks inside an
// activity. Must run before any output, since the settings navigation needs a
// page whose course matches the module.
$courseid = \block_elediaai_tutor\local\placement::prepare_page($PAGE, $blockcontext);
if ($courseid > 0) {
    $returnurl = new moodle_url('/course/view.php', ['id' => $courseid]);
}

$pageurl = new moodle_url('/blocks/elediaai_tutor/edit_instance.php', ['blockid' => $blockid]);
$PAGE->set_url($pageurl);
$PAGE->set_context($blockcontext);
$PAGE->set_title(get_string('instance_shell_title', 'block_elediaai_tutor'));
// Empty: the instance shell renders its own header ("eLeDia.ai Tutor | Settings (course)"),
// so a separate course-name page heading above it would be redundant.
$PAGE->set_heading('');
shell::require_css();

$config = !empty($record->configdata) ? unserialize_object(base64_decode($record->configdata)) : new stdClass();
if (!is_object($config)) {
    $config = new stdClass();
}

$defaults = new stdClass();
foreach ((array) $config as $key => $value) {
    $defaults->{'config_' . $key} = $value;
}
$defaults->config_title = $config->title ?? get_string('pluginname', 'block_elediaai_tutor');
$defaults->config_passcoursecontext = (int) ($config->passcoursecontext ?? 1);
$defaults->config_dailylimit = (int) ($config->dailylimit ?? -1);
$defaults->config_ragmode = $config->ragmode ?? chat_mode::MODE_GROUNDED;

$form = new block_elediaai_tutor_instance_shell_form($pageurl, [
    'blockid' => $blockid,
    'blockcontext' => $blockcontext,
    'config' => $config,
    'courseid' => $courseid,
]);
$form->set_data($defaults);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    $config->title = trim((string) ($data->config_title ?? ''));
    $config->passcoursecontext = (int) ($data->config_passcoursecontext ?? 1);
    $config->dailylimit = (int) ($data->config_dailylimit ?? -1);
    if (isset($data->config_ragmode)) {
        $config->ragmode = (string) $data->config_ragmode;
    }

    foreach (registry::instanceable_keys() as $key) {
        if (!registry::is_exposed($key)) {
            continue;
        }
        $entry = registry::get($key);
        if (($entry['type'] ?? '') === 'file') {
            continue;
        }
        $field = 'config_' . $key;
        if (!property_exists($data, $field)) {
            continue;
        }
        $value = $data->$field;
        if ($value === '' || $value === null) {
            unset($config->$key);
            continue;
        }
        $clean = registry::sanitise($key, $value);
        if ($clean === null || $clean === '') {
            unset($config->$key);
        } else {
            $config->$key = $clean;
        }
    }

    foreach (
        [
        'config_logo' => branding::INSTANCE_LOGO_FILEAREA,
        'config_avatar' => branding::INSTANCE_AVATAR_FILEAREA,
        ] as $field => $filearea
    ) {
        if (property_exists($data, $field)) {
            file_save_draft_area_files(
                (int) $data->$field,
                $blockcontext->id,
                'block_elediaai_tutor',
                $filearea,
                0,
                ['maxfiles' => 1, 'subdirs' => 0]
            );
        }
    }

    $DB->set_field('block_instances', 'configdata', base64_encode(serialize($config)), ['id' => $blockid]);
    if ($courseid > 0) {
        rebuild_course_cache($courseid, true);
    }
    redirect($pageurl, get_string('changessaved'));
}

echo $OUTPUT->header();
shell::open_instance($blockid, shell::ACTIVE_INSTANCE_SETTINGS);

if (!shell::is_available()) {
    echo $OUTPUT->heading(get_string('instance_shell_title', 'block_elediaai_tutor'), 2);
}

echo html_writer::start_div('path-block-elediaai_tutor eat-instance-shell');
echo html_writer::tag(
    'h2',
    get_string('instance_shell_title', 'block_elediaai_tutor'),
    ['class' => 'eat-section-heading']
);
echo html_writer::tag(
    'p',
    get_string('instance_shell_intro', 'block_elediaai_tutor'),
    ['class' => 'text-muted']
);
// Site admins keep one-click access to the site-wide settings hub (the per-instance
// shell otherwise only exposes this block's own settings).
if (has_capability('moodle/site:config', \core\context\system::instance())) {
    echo html_writer::div(
        html_writer::link(
            new moodle_url('/admin/settings.php', ['section' => 'blocksettingelediaai_tutor']),
            get_string('instance_shell_siteadmin_link', 'block_elediaai_tutor'),
            ['class' => 'btn btn-outline-secondary btn-sm']
        ),
        'eat-instance-siteadmin mb-3'
    );
}

$form->display();

// Live preview: a floating, draggable panel that overlays the page (so it never
// affects the form layout) and re-themes instantly as design fields change. Drag it by
// its title bar; the chevron minimises it. See instance_preview.js.
$previewcfg = (array) $config;
$previewcfg['instanceid'] = $blockid;
$previewcfg['displaymode'] = 'embedded';
$previewcfg['preview'] = true;
echo html_writer::start_div('eat-preview-float', ['data-region' => 'eat-preview-float']);
echo html_writer::start_div('eat-preview-float__bar', ['data-region' => 'eat-preview-drag']);
echo html_writer::tag(
    'span',
    get_string('instance_preview_heading', 'block_elediaai_tutor'),
    ['class' => 'eat-preview-float__title']
);
echo html_writer::tag(
    'button',
    \block_elediaai_tutor\local\icon::render('window-minimize'),
    [
        'type' => 'button',
        'class' => 'eat-preview-float__toggle',
        'data-action' => 'eat-preview-toggle',
        'aria-label' => get_string('instance_preview_toggle', 'block_elediaai_tutor'),
    ]
);
echo html_writer::end_div();
echo html_writer::start_div('eat-preview-float__body');
echo html_writer::div(
    widget::render($blockcontext, $courseid, $previewcfg),
    'elediaai-chat-page eat-instance-preview'
);
echo html_writer::end_div();
echo html_writer::end_div();

// Wrap the moodleform in the settings hub (Design / Conversation / Technical cards),
// mirroring the admin settings experience. The group -> major mapping matches the
// admin settings page (see settings.php). Degrades to the plain form if the JS
// cannot build the hub.
if (shell::is_available()) {
    $designgroups = ['accent', 'surfaces', 'text', 'bubbles', 'states', 'shape', 'effects', 'launcher', 'footer', 'files'];
    $conversationgroups = ['persona', 'conversation'];
    // A group that is named in neither list lands on the last card, because
    // that is the fallback in the JS -- silently, and only right by accident.
    // The knowledge base belongs on the technical card next to "pass the course
    // context" and "answer source": the three together say what this tutor may
    // draw on. Named here rather than left to the fallback, so the next group
    // added does not inherit a home nobody chose for it.
    $groupmajors = [
        'quicksettings' => 'technical',
        'insgroup_knowledge' => 'knowledge',
    ];
    foreach ($designgroups as $g) {
        $groupmajors['insgroup_' . $g] = 'design';
    }
    foreach ($conversationgroups as $g) {
        $groupmajors['insgroup_' . $g] = 'conversation';
    }
    $hubconfig = [
        'sectionCards' => [
            [
                'key' => 'design',
                'icon' => 'palette',
                'title' => get_string('setting_section_design', 'block_elediaai_tutor'),
                'body' => get_string('setting_section_design_desc', 'block_elediaai_tutor'),
            ],
            [
                'key' => 'conversation',
                'icon' => 'comments',
                'title' => get_string('setting_section_conversation', 'block_elediaai_tutor'),
                'body' => get_string('setting_section_conversation_desc', 'block_elediaai_tutor'),
            ],
            [
                'key' => 'knowledge',
                'icon' => 'database',
                'title' => get_string('setting_section_knowledge', 'block_elediaai_tutor'),
                'body' => get_string('setting_section_knowledge_desc', 'block_elediaai_tutor'),
            ],
            [
                'key' => 'technical',
                'icon' => 'plug',
                'title' => get_string('setting_section_technical', 'block_elediaai_tutor'),
                'body' => get_string('setting_section_technical_desc', 'block_elediaai_tutor'),
            ],
        ],
        'groupMajors' => $groupmajors,
        'hubTitle' => get_string('settings_hub_title', 'block_elediaai_tutor'),
        'hubDesc' => get_string('settings_hub_desc', 'block_elediaai_tutor'),
        'topicsLabel' => get_string('settings_hub_topics_label', 'block_elediaai_tutor'),
    ];
    $encodedhub = json_encode(
        $hubconfig,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
    $PAGE->requires->js_amd_inline(
        "require(['block_elediaai_tutor/instance_settings_shell'], function(shell) {"
        . "shell.init({$encodedhub});"
        . "});"
    );
}

// Live preview: re-theme the inert preview widget client-side as the design fields
// change. Pass the registry key -> --eac-token map and each token field's type so the
// module knows how to read/apply each value, plus the defaults used when a field is
// cleared (so the preview reverts exactly like the saved render would).
$previewtokens = [];
$previewtypes = [];
foreach (registry::token_keys() as $regkey => $token) {
    $previewtokens[$regkey] = $token;
    $entry = registry::get($regkey);
    $previewtypes[$regkey] = (string) ($entry['type'] ?? '');
}
$previewmeta = [
    'tokenMap' => $previewtokens,
    'fieldTypes' => $previewtypes,
    'defaults' => [
        'welcome' => get_string('default_welcome', 'block_elediaai_tutor'),
        'persona' => get_string('default_persona', 'block_elediaai_tutor'),
        'poweredby' => get_string('poweredby', 'block_elediaai_tutor'),
    ],
];
$encodedpreview = json_encode(
    $previewmeta,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);
$PAGE->requires->js_amd_inline(
    "require(['block_elediaai_tutor/instance_preview'], function(preview) {"
    . "preview.init({$encodedpreview});"
    . "});"
);

echo html_writer::end_div();

shell::close();
echo $OUTPUT->footer();
