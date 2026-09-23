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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * LernHive Plugin Shell adapter for LiteRAG pages.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_literag\output;

use html_writer;
use local_elediaai_core\output\plugin_page;
use local_elediaai_core\output\plugin_shell;
use moodle_url;

/**
 * Builds the shared LernHive Plugin Shell context for LiteRAG.
 */
final class shell {
    /** @var string Settings section key. */
    public const ACTIVE_SETTINGS = 'settings';

    /** @var string Help page section key. */
    public const ACTIVE_HELP = 'help';

    /**
     * Require styles used by the shell and LiteRAG admin UI.
     */
    public static function require_css(): void {
        global $PAGE;

        $PAGE->add_body_class('path-local-literag');
        $PAGE->add_body_class('lh-plugin-shell-page');

        // Der Rahmen kommt aus dem Kern. Vorher lag er im Tutor-Block, und
        // dieses Plugin lud dessen Stylesheet mit -- eine Kopplung an ein
        // Plugin, von dem es fachlich nichts will. Das eigene Stylesheet
        // bleibt: darin stehen die Regeln fuer die Indexverwaltung, nicht
        // fuer den Rahmen.
        $PAGE->requires->css('/local/elediaai_core/styles.css');
        $PAGE->requires->css('/local/literag/styles.css');
    }

    /**
     * The shell header, as markup.
     *
     * Returned rather than echoed because this plugin's settings live under
     * Moodle's admin tree: a small AMD module moves the header into that
     * page, so it cannot be printed where the page is built. The help page
     * uses {@see plugin_page::open()} instead.
     *
     * @param string $active Active section key.
     * @param string|null $tagline Optional tagline override.
     * @return string
     */
    public static function header_html(string $active, ?string $tagline = null): string {
        return plugin_page::header_html(self::header_data($active, $tagline));
    }

    /**
     * What the core's shell needs to draw this plugin's header.
     *
     * @param string $active Active section key.
     * @param string|null $tagline Optional tagline override.
     * @return array<string, mixed>
     */
    public static function header_data(string $active, ?string $tagline = null): array {
        return [
            'name' => get_string('shell_name', 'local_elediaai_core'),
            'tagline' => $tagline ?? get_string('pluginname', 'local_literag'),
            'homeurl' => (new moodle_url('/local/elediaai_core/index.php'))->out(false),
            'sectionnav' => self::sectionnav($active),
        ] + plugin_shell::action_slots(
            'local_literag',
            has_capability('moodle/site:config', \core\context\system::instance()),
            new moodle_url('/admin/settings.php', ['section' => 'local_literag']),
            null,
            null,
            $active === self::ACTIVE_SETTINGS
        );
    }

    /**
     * Build the Plugin Shell section navigation.
     *
     * @param string $active Active section key.
     * @return string Raw HTML for the Plugin Shell `sectionnav` slot.
     */
    public static function sectionnav(string $active): string {
        // Die Leiste ueber die Infrastruktur der Suite gehoert dem Kern. Sie
        // lag im Tutor-Block, und dieses Plugin griff dorthinein -- ein Block
        // ist ein seltsames Zuhause fuer Rahmung, die nicht von ihm handelt.
        //
        // Ohne Rueckfall, seit dieses Plugin den Kern in version.php als
        // Abhaengigkeit fuehrt: der Zweig war nicht erreichbar und trug eine
        // von Hand abgeschriebene Kopie eines Symbols, das im Sprite des
        // Kerns liegt.
        unset($active);

        return \local_elediaai_core\output\section_nav::render_infrastructure(
            \local_elediaai_core\output\section_nav::INFRA_LITERAG
        );
    }
}
