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
 * AI audit shell settings form.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use local_elediaai_core\local\audit_config;
use moodleform;

/**
 * Plugin-shell settings form for the AI audit display policy.
 */
final class audit_settings extends moodleform {
    #[\Override]
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement(
            'select',
            'audit_access',
            get_string('audit_access', 'local_elediaai_core'),
            [
                audit_config::ACCESS_CORE_CAPABILITY => get_string('audit_access_corecap', 'local_elediaai_core'),
                audit_config::ACCESS_TEACHER_OWN_COURSES => get_string('audit_access_teacherowncourses', 'local_elediaai_core'),
                audit_config::ACCESS_ADMINS_ONLY => get_string('audit_access_adminonly', 'local_elediaai_core'),
            ]
        );
        $mform->setType('audit_access', PARAM_ALPHANUMEXT);
        $mform->addElement(
            'static',
            'audit_access_helptext',
            '',
            get_string('audit_access_desc', 'local_elediaai_core')
        );

        $mform->addElement(
            'advcheckbox',
            'audit_anonymize_users',
            get_string('audit_anonymize_users', 'local_elediaai_core'),
            get_string('audit_anonymize_users_desc', 'local_elediaai_core'),
            null,
            [0, 1]
        );
        $mform->setType('audit_anonymize_users', PARAM_BOOL);

        $mform->addElement(
            'advcheckbox',
            'audit_show_prompt',
            get_string('audit_show_prompt', 'local_elediaai_core'),
            get_string('audit_show_prompt_desc', 'local_elediaai_core'),
            null,
            [0, 1]
        );
        $mform->setType('audit_show_prompt', PARAM_BOOL);

        $mform->addElement(
            'advcheckbox',
            'audit_show_response',
            get_string('audit_show_response', 'local_elediaai_core'),
            get_string('audit_show_response_desc', 'local_elediaai_core'),
            null,
            [0, 1]
        );
        $mform->setType('audit_show_response', PARAM_BOOL);

        $mform->addElement(
            'advcheckbox',
            'audit_show_error',
            get_string('audit_show_error', 'local_elediaai_core'),
            get_string('audit_show_error_desc', 'local_elediaai_core'),
            null,
            [0, 1]
        );
        $mform->setType('audit_show_error', PARAM_BOOL);

        $mform->addElement(
            'advcheckbox',
            'audit_show_tokens',
            get_string('audit_show_tokens', 'local_elediaai_core'),
            get_string('audit_show_tokens_desc', 'local_elediaai_core'),
            null,
            [0, 1]
        );
        $mform->setType('audit_show_tokens', PARAM_BOOL);

        // Jede Frist nennt ihre Folge in einem Satz. Eine Zahl ohne die Folge
        // ist eine Einstellung, die niemand mit Grund setzt.
        $mform->addElement(
            'header',
            'retention_header',
            get_string('retention_settings_heading', 'local_elediaai_core')
        );
        $mform->addElement(
            'static',
            'retention_intro',
            '',
            get_string('retention_settings_desc', 'local_elediaai_core')
        );

        foreach (['action_retentiondays', 'turn_retentiondays', 'usage_retentiondays'] as $name) {
            $mform->addElement('text', $name, get_string($name, 'local_elediaai_core'));
            $mform->setType($name, PARAM_INT);
            $mform->addRule($name, null, 'numeric', null, 'client');
            $mform->addElement(
                'static',
                $name . '_helptext',
                '',
                get_string($name . '_desc', 'local_elediaai_core')
            );
        }

        $mform->addElement(
            'header',
            'quota_header',
            get_string('quota_settings_heading', 'local_elediaai_core')
        );

        foreach (['student', 'teacher'] as $bucket) {
            foreach (['hour', 'day', 'month'] as $window) {
                $name = 'quota_' . $bucket . '_' . $window;
                $mform->addElement('text', $name, get_string($name, 'local_elediaai_core'));
                $mform->setType($name, PARAM_INT);
                $mform->addRule($name, null, 'numeric', null, 'client');
            }
        }

        $mform->addElement(
            'static',
            'quota_helptext',
            '',
            get_string('quota_settings_desc', 'local_elediaai_core')
        );

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
