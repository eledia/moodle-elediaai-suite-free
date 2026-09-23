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

declare(strict_types=1);

namespace block_elediaai_tutor\form;

use block_elediaai_tutor\local\registry;
use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Create/edit a saved tutor profile: identity, images and the full set of
 * registry settings (persona + every design token + behaviour/launcher/footer).
 *
 * Field names: `name`, `shortname`, `description`, the `logo`/`avatar`
 * filemanagers, and `cfg_<key>` for every registry setting. The managing page
 * collects the `cfg_*` values into the profile's settings map.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tutor_edit_form extends moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        $isnew = empty($this->_customdata['id']);
        \block_elediaai_tutor\local\formhelper::register_colour_element();

        $mform->addElement('hidden', 'id', (int) ($this->_customdata['id'] ?? 0));
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'save');
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement(
            'text',
            'name',
            get_string('tutor_name', 'block_elediaai_tutor'),
            ['size' => 48]
        );
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement(
            'text',
            'shortname',
            get_string('tutor_shortname', 'block_elediaai_tutor'),
            ['size' => 32]
        );
        $mform->setType('shortname', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('shortname', 'tutor_shortname', 'block_elediaai_tutor');
        if (!$isnew) {
            $mform->freeze('shortname');
        }

        $mform->addElement(
            'textarea',
            'description',
            get_string('tutor_description', 'block_elediaai_tutor'),
            ['rows' => 2, 'cols' => 60]
        );
        $mform->setType('description', PARAM_TEXT);

        $imageopts = ['maxfiles' => 1, 'subdirs' => 0,
            'accepted_types' => ['.png', '.jpg', '.jpeg', '.svg', '.webp', '.gif']];
        $mform->addElement(
            'filemanager',
            'logo',
            get_string('reg_logo', 'block_elediaai_tutor'),
            null,
            $imageopts
        );
        $mform->addElement(
            'filemanager',
            'avatar',
            get_string('reg_avatar', 'block_elediaai_tutor'),
            null,
            $imageopts
        );

        // Every registry setting, grouped — a profile defines the full tutor.
        foreach (registry::groups() as $group) {
            $keys = array_filter(
                registry::group_keys($group),
                static fn(string $k): bool => registry::is_available($k) && registry::get($k)['type'] !== 'file'
            );
            if (empty($keys)) {
                continue;
            }
            $mform->addElement(
                'header',
                'grp_' . $group,
                get_string('reggroup_' . $group, 'block_elediaai_tutor')
            );
            $mform->setExpanded('grp_' . $group, $group === 'persona' || $group === 'knowledge');
            foreach ($keys as $key) {
                $this->add_registry_field($key, registry::get($key));
            }
        }

        $this->add_action_buttons();
    }

    /**
     * Add one registry setting as a `cfg_<key>` field. Empty means "not set".
     *
     * @param string $key Registry key.
     * @param array $entry Registry descriptor.
     * @return void
     */
    private function add_registry_field(string $key, array $entry): void {
        $mform = $this->_form;
        $name = 'cfg_' . $key;
        $label = get_string_manager()->string_exists('reg_' . $key, 'block_elediaai_tutor')
            ? get_string('reg_' . $key, 'block_elediaai_tutor')
            : (string) ($entry['token'] ?? $key);

        $settings = $this->_customdata['settings'] ?? [];

        switch ($entry['type']) {
            case 'colour':
                $mform->addElement('eatcolour', $name, $label);
                $mform->setType($name, PARAM_TEXT);
                break;
            case 'textarea':
                $mform->addElement('textarea', $name, $label, ['rows' => 3, 'cols' => 60]);
                $mform->setType($name, PARAM_TEXT);
                break;
            case 'select':
                $options = ['' => get_string('tutor_notset', 'block_elediaai_tutor')];
                foreach ($entry['options'] as $value => $optkey) {
                    $options[$value] = get_string($optkey, 'block_elediaai_tutor');
                }
                $mform->addElement('select', $name, $label, $options);
                break;
            case 'coursescope':
                $mform->addElement(
                    'autocomplete',
                    $name,
                    $label,
                    \local_elediaai_chatengine\local\knowledge_scope::options(),
                    ['multiple' => true]
                );
                if (get_string_manager()->string_exists('reg_' . $key . '_help', 'block_elediaai_tutor')) {
                    $mform->addHelpButton($name, 'reg_' . $key, 'block_elediaai_tutor');
                }
                break;

            case 'checkbox':
                $mform->addElement('select', $name, $label, [
                    '' => get_string('tutor_notset', 'block_elediaai_tutor'),
                    '1' => get_string('yes'),
                    '0' => get_string('no'),
                ]);
                break;
            default:
                // Cssvalue / font / text. Named-options tokens become a dropdown;
                // plain text stays a text box.
                if (!empty($entry['choices'])) {
                    $current = isset($settings[$key]) ? (string) $settings[$key] : null;
                    $options = registry::choice_select_options(
                        $key,
                        get_string('tutor_notset', 'block_elediaai_tutor'),
                        $current
                    );
                    $mform->addElement('select', $name, $label, $options);
                } else {
                    $mform->addElement('text', $name, $label, ['size' => 40]);
                    $mform->setType($name, PARAM_TEXT);
                }
                break;
        }
    }
}
