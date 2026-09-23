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

namespace webservice_elediamcp\output;

use html_writer;
use moodle_url;

/**
 * Shell wrapper for plugin-owned MCP pages.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class shell {
    /** @var string Default shell width modifier. */
    private const MODIFIER_DEFAULT = 'default';

    /** @var string Narrow shell width modifier for prose and help pages. */
    public const MODIFIER_READING = 'reading';

    /** @var string Active section key for this plugin page. */
    public const ACTIVE_ELEDIAMCP = 'elediamcp';

    /** @var string Active section key for the self-service token page. */
    public const ACTIVE_TOKENS = 'tokens';

    /** @var bool Whether the eLeDia.ai Tutor Plugin Shell helper is available. */
    private static bool $usespluginshell = false;

    /**
     * Static-only helper.
     */
    private function __construct() {
    }

    /**
     * Wrap Lucide icon body markup in a standard inline SVG element.
     *
     * @param string $inner Inner Lucide SVG paths/shapes.
     * @return string Inline Lucide SVG string.
     */
    private static function lucide(string $inner): string {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" '
            . 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" '
            . 'stroke-linejoin="round" aria-hidden="true" focusable="false" class="lucide">' . $inner . '</svg>';
    }

    /**
     * Queue the stylesheets this plugin's pages need.
     */
    public static function require_css(): void {
        global $PAGE, $CFG;

        $PAGE->add_body_class('lh-plugin-shell-page');
        $PAGE->add_body_class('path-webservice-elediamcp');

        // Die Huelle kommt aus dem Kern, also auch ihr Stylesheet. Bis hierher
        // laed dieses Plugin das des Tutor-Blocks, weil das Markup von dort kam.
        if (file_exists($CFG->dirroot . '/local/elediaai_core/styles.css')) {
            $PAGE->requires->css(new moodle_url('/local/elediaai_core/styles.css'));
        }
        if (file_exists($CFG->dirroot . '/webservice/elediamcp/styles.css')) {
            $PAGE->requires->css(new moodle_url('/webservice/elediamcp/styles.css'));
        }
    }

    /**
     * Open the page shell and content area.
     *
     * @param string $active Active section key.
     * @param string|null $fallbackheading Heading for the fallback renderer.
     * @param string|null $fallbackhint Hint for the fallback renderer.
     * @param string $modifier Shell width modifier.
     */
    public static function open(
        string $active = self::ACTIVE_ELEDIAMCP,
        ?string $fallbackheading = null,
        ?string $fallbackhint = null,
        string $modifier = self::MODIFIER_DEFAULT
    ): void {
        // Die gemeinsame Huelle des Kerns. Bis hierher benutzt dieses Plugin
        // die Fassung aus `block_elediaai_tutor` -- und uebernahm dabei auch
        // dessen Namen: die MCP-Konfigurationsseite trug den Titel
        // „eLeDia.ai | Tutor".
        $pluginpage = \local_elediaai_core\output\plugin_page::class;
        $pluginshell = \local_elediaai_core\output\plugin_shell::class;
        self::$usespluginshell = class_exists($pluginpage) && class_exists($pluginshell);
        if (self::$usespluginshell) {
            $configurationurl = new moodle_url('/webservice/elediamcp/configuration.php');
            $actions = $pluginshell::action_slots(
                'webservice_elediamcp',
                true,
                $configurationurl,
                get_string('shell_help_label', 'webservice_elediamcp'),
                get_string('shell_settings_label', 'webservice_elediamcp'),
                true
            );
            $actions['helpurl'] = (new moodle_url('/webservice/elediamcp/help.php'))->out(false);

            $headerdata = [
                'name' => get_string('shell_name', 'local_elediaai_core'),
                'tagline' => get_string('pluginname', 'webservice_elediamcp'),
                'subtitle' => '',
                'sectionnav' => self::sectionnav($active),
            ] + $actions;

            $pluginpage::open($headerdata, $modifier);
            $pluginshell::content_open();
            return;
        }

        echo html_writer::start_div('webservice-elediamcp-configuration');
        echo html_writer::tag(
            'h2',
            $fallbackheading ?? get_string('configuration_heading', 'webservice_elediamcp')
        );
        $hint = $fallbackhint ?? get_string('configuration_hint', 'webservice_elediamcp');
        if ($hint !== '') {
            echo html_writer::tag('p', $hint, ['class' => 'text-muted']);
        }
    }

    /**
     * Build the shared AI Tutor/MCP Plugin Shell section navigation.
     *
     * @return string Raw HTML for the Plugin Shell `sectionnav` slot.
     */
    private static function sectionnav(string $active): string {
        // Die Leiste ueber die Infrastruktur der Suite gehoert dem Kern. Sie
        // lag im Tutor-Block, und dieses Plugin griff dorthinein -- ein Block
        // ist ein seltsames Zuhause fuer Rahmung, die nicht von ihm handelt.
        // Die Leiste ueber die Infrastruktur der Suite gehoert dem Kern -- eine
        // harte Abhaengigkeit dieses Plugins. Hier stand ein Rueckfall, der die
        // Leiste des Tutor-Blocks nachbaute, mitsamt eingebetteter SVG-Pfade
        // fuer Symbole, die im Sprite des Kerns liegen.
        return \local_elediaai_core\output\section_nav::render_infrastructure(
            \local_elediaai_core\output\section_nav::INFRA_MCP
        );
    }

    /**
     * Close the content area and shell.
     */
    public static function close(): void {
        if (self::$usespluginshell) {
            \local_elediaai_core\output\plugin_shell::content_close();
            \local_elediaai_core\output\plugin_page::close();
            self::$usespluginshell = false;
            return;
        }

        echo html_writer::end_div();
    }
}
