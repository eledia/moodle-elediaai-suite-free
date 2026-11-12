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

namespace block_elediaai_tutor\local;

/**
 * Small UI helpers shared by the plugin's edit forms.
 *
 * Autoloaded so the block-instance edit form and the tutor-profile editor can
 * both use it without including the plugin's lib.php (which Moodle does not load
 * for those pages).
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class formhelper {
    /**
     * Register the 'eatcolour' form element (Moodle's native colour picker).
     *
     * Idempotent; call before adding colour fields. The element renders the
     * admin colour-picker markup and initialises M.util.init_colour_picker during
     * render(), so it works on a full page and inside the block config modal.
     *
     * @return void
     */
    public static function register_colour_element(): void {
        global $CFG;
        \MoodleQuickForm::registerElementType(
            'eatcolour',
            $CFG->dirroot . '/blocks/elediaai_tutor/form/element_eatcolour.php',
            'MoodleQuickForm_eatcolour'
        );
    }

    /**
     * Add the help button for a registry field.
     *
     * A field-specific help wins, then the registry key's own help. A text
     * field without either gets the help on multilingual text: those fields
     * accept the "Multi-language content" markup, and nobody finds that out
     * from an empty text box.
     *
     * @param \MoodleQuickForm $mform The form.
     * @param string $field Form element name.
     * @param string $key Registry key.
     * @param array $entry Registry descriptor.
     * @return void
     */
    public static function add_registry_help(\MoodleQuickForm $mform, string $field, string $key, array $entry): void {
        $strings = get_string_manager();
        if ($strings->string_exists($field . '_help', 'block_elediaai_tutor')) {
            $mform->addHelpButton($field, $field, 'block_elediaai_tutor');
        } else if ($strings->string_exists('reg_' . $key . '_help', 'block_elediaai_tutor')) {
            // A registry key may bring its own help under its registry name,
            // so one text serves every form the registry is rendered in.
            $mform->addHelpButton($field, 'reg_' . $key, 'block_elediaai_tutor');
        } else if (!empty($entry['multilang'])) {
            $mform->addHelpButton($field, 'multilanghint', 'block_elediaai_tutor');
        }
    }
}
