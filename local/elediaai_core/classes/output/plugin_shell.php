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
 * Plugin-owned page-shell helpers.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\output;

defined('MOODLE_INTERNAL') || die();

use html_writer;
use moodle_url;

/**
 * Builds header actions and content wrappers without an external UI plugin.
 */
final class plugin_shell {
    /**
     * Build the help and settings action slots used by plugin pages.
     *
     * @param string $component Component requesting the slots.
     * @param bool $cansiteconfig Whether settings may be shown.
     * @param moodle_url|string|null $settingsurl Plugin-owned settings URL.
     * @param string|null $helplabel Help label.
     * @param string|null $settingslabel Settings label.
     * @param bool $settingsiscurrent Whether settings is the current page.
     * @return array<string, mixed>
     */
    public static function action_slots(
        string $component,
        bool $cansiteconfig = false,
        moodle_url|string|null $settingsurl = null,
        ?string $helplabel = null,
        ?string $settingslabel = null,
        bool $settingsiscurrent = false
    ): array {
        unset($component);
        $resolvedsettingsurl = '';
        if ($cansiteconfig && $settingsurl !== null && $settingsurl !== '') {
            $resolvedsettingsurl = $settingsurl instanceof moodle_url ? $settingsurl->out(false) : $settingsurl;
        }
        // `?:` und nicht `??`: ein Aufrufer, der '' uebergibt, meint „nimm die
        // Vorgabe", nicht „nimm nichts". Mit `??` fiel nur null zurueck, und
        // drei Seiten trugen deshalb oben rechts eine leere Pille -- ein
        // Knopf ohne Aufschrift, der in die Dokumentation fuehrte. Ein Link
        // ohne zugaenglichen Namen ist dazu ein Barrierefreiheitsfehler.
        return [
            'helpurl' => self::help_url(),
            'helplabel' => ($helplabel ?? '') !== '' ? $helplabel : get_string('help'),
            'settingsurl' => $resolvedsettingsurl,
            'settingslabel' => ($settingslabel ?? '') !== '' ? $settingslabel : get_string('settings'),
            'settingsiscurrent' => $settingsiscurrent && $resolvedsettingsurl !== '',
        ];
    }

    /**
     * Where the shell's Help button leads, or nothing when there is nowhere.
     *
     * The suite's user-facing help is the guide's handbook. It used to be a
     * page in this plugin that rendered the team's own developer document to
     * end users, which is a different audience and a second place for the same
     * answers.
     *
     * Returns the empty string when the guide is not installed, and both
     * shells drop the button for an empty URL. A Help button that leads to a
     * 404 is worse than no Help button: the first costs trust, the second only
     * costs a click somebody would not have made.
     *
     * @return string
     */
    private static function help_url(): string {
        if (\core_component::get_component_directory('local_elediaai_guide') === null) {
            return '';
        }
        return (new moodle_url('/local/elediaai_guide/index.php'))->out(false);
    }

    /**
     * Open the main content region.
     *
     * @param string $classes Additional CSS classes.
     */
    public static function content_open(string $classes = ''): void {
        echo html_writer::start_tag('main', ['class' => trim('elediaai-core-shell__content ' . $classes)]);
    }

    /**
     * Close the main content region.
     */
    public static function content_close(): void {
        echo html_writer::end_tag('main');
    }
}
