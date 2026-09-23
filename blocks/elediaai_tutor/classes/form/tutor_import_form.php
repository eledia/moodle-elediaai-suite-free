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

use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Upload a tutor bundle (.zip) to import — either as a new site-wide profile or,
 * when a target block instance is supplied, snapshotted onto that instance.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tutor_import_form extends moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'action', 'importdo');
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('hidden', 'blockid', (int) ($this->_customdata['blockid'] ?? 0));
        $mform->setType('blockid', PARAM_INT);

        $mform->addElement(
            'filepicker',
            'bundle',
            get_string('tutor_bundle', 'block_elediaai_tutor'),
            null,
            ['maxbytes' => 10 * 1024 * 1024, 'accepted_types' => ['.zip']]
        );
        $mform->addRule('bundle', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('tutor_import', 'block_elediaai_tutor'));
    }
}
