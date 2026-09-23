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
 * Per-instance configuration form for the eLeDia.ai Tutor block.
 *
 * Structural fields (title, course wiring, limits, answer source) are fixed. The
 * persona, every visual token and the behaviour/launcher/footer toggles are
 * generated from {@see \block_elediaai_tutor\local\registry}, and only the keys
 * the admin has exposed (expose_<key>) appear — empty always means "follow site".
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_elediaai_tutor\local\branding;
use block_elediaai_tutor\local\registry;

/**
 * Block instance settings form.
 */
class block_elediaai_tutor_edit_form extends block_edit_form {
    /**
     * Define the instance-specific form fields.
     *
     * @param MoodleQuickForm $mform The form.
     * @return void
     */
    protected function specific_definition($mform): void {
        \block_elediaai_tutor\local\formhelper::register_colour_element();
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        // Import / export this instance's tutor (settings + images), or apply a
        // site preset — available once the block exists. The shell reflects this
        // specific instance; admins reach the site-wide settings via a link in it.
        if (!empty($this->block->instance->id)) {
            $shelllink = new \moodle_url(
                '/blocks/elediaai_tutor/edit_instance.php',
                ['blockid' => (int) $this->block->instance->id]
            );
            $toolslink = new \moodle_url(
                '/blocks/elediaai_tutor/instance_tutor.php',
                ['blockid' => (int) $this->block->instance->id]
            );
            $actions = \html_writer::link(
                $shelllink,
                get_string('instance_shell_edit_link', 'block_elediaai_tutor'),
                ['class' => 'btn btn-primary', 'target' => '_top']
            ) . ' ' .
                \html_writer::link(
                    $toolslink,
                    get_string('instancetutor_link', 'block_elediaai_tutor'),
                    ['class' => 'btn btn-secondary', 'target' => '_top']
                );
            $mform->addElement('static', 'tutorio', '', $actions);
        }

        // Title.
        $mform->addElement('text', 'config_title', get_string('config_title', 'block_elediaai_tutor'));
        $mform->setType('config_title', PARAM_TEXT);
        $mform->setDefault('config_title', get_string('pluginname', 'block_elediaai_tutor'));

        // Course context wiring.
        $mform->addElement(
            'selectyesno',
            'config_passcoursecontext',
            get_string('config_passcoursecontext', 'block_elediaai_tutor')
        );
        $mform->setDefault('config_passcoursecontext', 1);
        $mform->addHelpButton('config_passcoursecontext', 'config_passcoursecontext', 'block_elediaai_tutor');

        // Daily message limit override (-1 = site default, 0 = unlimited).
        $mform->addElement('text', 'config_dailylimit', get_string('config_dailylimit', 'block_elediaai_tutor'));
        $mform->setType('config_dailylimit', PARAM_INT);
        $mform->setDefault('config_dailylimit', -1);
        $mform->addHelpButton('config_dailylimit', 'config_dailylimit', 'block_elediaai_tutor');

        // Answer source (grounded vs LLM-only). Offered only when it is a real
        // choice: LLM-only allowed site-wide AND the course has a knowledge base.
        $grounding = \block_elediaai_tutor\local\chat_mode::ingestion_available($this->effective_courseid());
        $llmallowed = \block_elediaai_tutor\local\chat_mode::is_llm_allowed();
        // Grounded answers call back into Moodle, so they require the MCP connector:
        // never offer the grounded option when webservice_elediamcp is absent.
        $connector = \local_elediaai_chatengine\local\token_provider::is_connector_available();
        if ($grounding && !$connector) {
            // Course is indexed (grounding is possible) but the connector is missing,
            // so grounded mode cannot be offered — explain why instead.
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
                    \block_elediaai_tutor\local\chat_mode::MODE_GROUNDED =>
                        get_string('ragmode_grounded', 'block_elediaai_tutor'),
                    \block_elediaai_tutor\local\chat_mode::MODE_LLMONLY =>
                        get_string('ragmode_llmonly', 'block_elediaai_tutor'),
                ]
            );
            $mform->setDefault('config_ragmode', \block_elediaai_tutor\local\chat_mode::MODE_GROUNDED);
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

        // Registry-driven tutor fields: persona, design tokens, behaviour,
        // launcher, footer and images — grouped, and only the keys the admin
        // has exposed for per-instance override. Empty = follow the site.
        foreach (registry::groups() as $group) {
            $exposed = array_filter(
                registry::group_keys($group),
                static fn(string $k): bool => registry::is_exposed($k)
            );
            if (empty($exposed)) {
                continue;
            }
            $mform->addElement(
                'header',
                'insgroup_' . $group,
                get_string('reggroup_' . $group, 'block_elediaai_tutor')
            );
            // Everything here is folded away, because most of it is design
            // tokens nobody opens twice. The knowledge base is not: it is the
            // one setting on this form somebody comes looking for, and it sits
            // directly under the course-context fields it belongs with.
            $mform->setExpanded('insgroup_' . $group, $group === 'knowledge');
            foreach ($exposed as $key) {
                $this->add_instance_field($mform, $key, registry::get($key));
            }
        }
    }

    /**
     * Add one per-instance field for a registry key. Empty/blank always means
     * "follow the site value", so selects gain a leading "use site" option and
     * checkboxes become a tri-state select.
     *
     * @param MoodleQuickForm $mform The form.
     * @param string $key Registry key.
     * @param array $entry Registry descriptor.
     * @return void
     */
    private function add_instance_field($mform, string $key, array $entry): void {
        $field = 'config_' . $key;
        $label = $this->reglabel($key, $entry);

        switch ($entry['type']) {
            case 'file':
                $imageopts = ['maxfiles' => 1, 'subdirs' => 0,
                    'accepted_types' => ['.png', '.jpg', '.jpeg', '.svg', '.webp', '.gif']];
                $mform->addElement('filemanager', $field, $label, null, $imageopts);
                break;

            case 'colour':
                // Moodle's native colour picker (text field + swatch; paste a hex too).
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

            case 'coursescope':
                // No "use site" entry: an empty selection *is* "use site" here,
                // because a multiple select has no third state. An instance can
                // therefore narrow the site's knowledge base or inherit it, but
                // not declare "nothing" -- and nothing is not a useful wish.
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
                        (string) ($this->block->config->$key ?? '')
                    )
                );
                if ($summary !== null) {
                    $mform->addElement('static', $field . 'summary', '', $summary);
                }
                break;

            case 'checkbox':
                $mform->addElement('select', $field, $label, [
                    '' => get_string('config_usesite', 'block_elediaai_tutor'),
                    '1' => get_string('yes'),
                    '0' => get_string('no'),
                ]);
                $mform->setDefault($field, '');
                break;

            default:
                // Cssvalue / font / text. Tokens with friendly named options
                // become a dropdown (no raw CSS); plain text stays a text box.
                if (!empty($entry['choices'])) {
                    $current = isset($this->block->config->$key) ? (string) $this->block->config->$key : null;
                    $options = registry::choice_select_options(
                        $key,
                        get_string('config_usesite', 'block_elediaai_tutor'),
                        $current
                    );
                    $mform->addElement('select', $field, $label, $options);
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
            // A registry key may bring its own help under the name it is known
            // by in the registry, so the same text serves this form and the
            // profile editor instead of being written twice.
            $mform->addHelpButton($field, 'reg_' . $key, 'block_elediaai_tutor');
        }
    }

    /**
     * Friendly label for a registry key, falling back to the raw token name.
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
     * Prepare the per-instance logo/avatar file-manager draft areas from the
     * stored block-context files (only for areas the admin has exposed).
     *
     * @param array|\stdClass $defaults The instance config defaults.
     * @return void
     */
    public function set_data($defaults) {
        if (!empty($this->block->instance->id)) {
            $context = $this->block->context;
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
                    $context->id,
                    'block_elediaai_tutor',
                    $filearea,
                    0,
                    ['maxfiles' => 1, 'subdirs' => 0]
                );
                $defaults->{$field} = $draftid;
            }
        }
        parent::set_data($defaults);
    }

    /**
     * The course this instance will chat in, mirroring the block's own
     * resolution: a configured fixed course id wins, otherwise the page course
     * when course context is passed, else 0 (global chat).
     *
     * @return int Course id, or 0 for global chat.
     */
    private function effective_courseid(): int {
        $config = $this->block->config ?? new \stdClass();

        $passcontext = !isset($config->passcoursecontext) || (int) $config->passcoursecontext === 1;
        $pagecourseid = (int) ($this->block->page->course->id ?? 0);
        if (
            $passcontext && \block_elediaai_tutor\local\security::course_chat_enabled()
                && $pagecourseid > 0
        ) {
            return $pagecourseid;
        }
        return 0;
    }

    /**
     * A knowledge base may only be narrowed, never widened.
     *
     * The block's own form is where a teacher touches this -- the site
     * settings and the profile editor are administration, and somebody who
     * administers the site passes the check anyway.
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
}
