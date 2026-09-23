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
 * LernHive Plugin Shell adapter for AI Sources pages.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_sources\output;

use html_writer;
use local_elediaai_core\output\plugin_shell;
use moodle_url;

/**
 * Builds the shared suite shell context for AI Sources.
 *
 * The shell chrome — header, section navigation, the `lh-plugin-*` styling —
 * is owned by the Tutor block, which carries both the template and the CSS.
 * AI Sources renders into it rather than keeping a third copy of a 233-line
 * template. When the Tutor is absent the pages fall back to a plain Moodle
 * heading, which is why every use is guarded by {@see self::is_available()}.
 */
final class shell {
    /** @var string Settings section key. */
    public const ACTIVE_SETTINGS = 'settings';

    /** @var string Reindex section key. */
    public const ACTIVE_REINDEX = 'reindex';

    /**
     * Check whether a shell provider is installed and autoloadable.
     *
     * @return bool
     */
    public static function is_available(): bool {
        // Frueher: „ist die Huelle des Tutor-Blocks da?". Die Rahmung der
        // Suite kommt jetzt aus dem Kern, also ist das die Frage.
        return class_exists(\local_elediaai_core\output\section_nav::class);
    }

    /**
     * Require styles used by the shell and AI Sources admin UI.
     */
    public static function require_css(): void {
        global $PAGE;

        $PAGE->add_body_class('path-local-elediaai_sources');
        $PAGE->add_body_class('lh-plugin-shell-page');

        $PAGE->requires->css('/local/elediaai_sources/styles.css');
        // Die Huelle kommt aus dem Kern, also auch ihr Stylesheet. Bis hierher
        // laed dieses Plugin das des Tutor-Blocks -- es gab dem
        // `lh-plugin-*`-Markup seine Form, und das Markup kam von dort.
        if (self::is_available()) {
            $PAGE->requires->css('/local/elediaai_core/styles.css');
        }
    }

    /**
     * Header values for the suite's shared page shell.
     *
     * Bis hierher rendert dieses Plugin die Vorlage
     * `block_elediaai_tutor/plugin_shell_header` -- Seitenrahmung aus einem
     * Block, der mit den KI-Quellen nichts zu tun hat. Der Kern haelt dieselbe
     * Huelle, und `action_slots()` setzt den Hilfe-Knopf selbst.
     *
     * @param string|null $tagline Overrides the plugin's own tagline.
     * @param string $active Active section key.
     * @return array<string, mixed> Ready for {@see plugin_page::open()}.
     */
    public static function header_data(?string $tagline = null, string $active = self::ACTIVE_SETTINGS): array {
        return [
            'name' => get_string('pluginname', 'local_elediaai_sources'),
            'tagline' => $tagline ?? get_string('shell_tagline', 'local_elediaai_sources'),
            'subtitle' => get_string('shell_subtitle', 'local_elediaai_sources'),
            'homeurl' => (new moodle_url('/local/elediaai_core/index.php'))->out(false),
            'sectionnav' => self::sectionnav($active),
        ] + plugin_shell::action_slots(
            'local_elediaai_sources',
            has_capability('moodle/site:config', \core\context\system::instance()),
            new moodle_url('/admin/settings.php', ['section' => 'local_elediaai_sources_settings']),
            null,
            null,
            $active === self::ACTIVE_SETTINGS
        );
    }

    /**
     * Build the Plugin Shell section navigation.
     *
     * @param string $active Active section key.
     * @return string Raw HTML for the Plugin Shell section navigation slot.
     */
    public static function sectionnav(string $active): string {
        // Die Leiste ueber die Infrastruktur der Suite gehoert dem Kern. Sie
        // lag im Tutor-Block, und dieses Plugin griff dorthinein -- ein Block
        // ist ein seltsames Zuhause fuer Rahmung, die nicht von ihm handelt.
        if (class_exists(\local_elediaai_core\output\section_nav::class)) {
            return \local_elediaai_core\output\section_nav::render_infrastructure(
                \local_elediaai_core\output\section_nav::INFRA_SOURCES
            );
        }

        $items = [
            self::ACTIVE_SETTINGS => [
                'url' => new moodle_url('/admin/settings.php', ['section' => 'local_elediaai_sources_settings']),
                'icon' => 'settings',
                'label' => get_string('nav_settings', 'local_elediaai_sources'),
            ],
            self::ACTIVE_REINDEX => [
                'url' => new moodle_url('/local/elediaai_sources/reindex.php'),
                'icon' => 'rotate-cw',
                'label' => get_string('nav_reindex', 'local_elediaai_sources'),
            ],
        ];

        $links = '';
        foreach ($items as $key => $item) {
            $attrs = [
                'class' => 'lh-plugin-section-nav__item',
                'href' => $item['url']->out(false),
            ];
            if ($active === $key) {
                $attrs['aria-current'] = 'page';
            }
            $links .= html_writer::tag(
                'a',
                self::lucide_icon($item['icon']) .
                ' ' . s($item['label']),
                $attrs
            );
        }

        return html_writer::tag('nav', $links, [
            'class' => 'lh-plugin-section-nav',
            'aria-label' => get_string('nav_label', 'local_elediaai_sources'),
        ]);
    }

    /**
     * Render an inline Lucide icon SVG by name.
     *
     * @param string $name Lucide icon name.
     * @return string Inline SVG markup, or empty string for unknown names.
     */
    public static function lucide_icon(string $name): string {
        static $icons = [
            // The settings gear is one long path; kept wrapped so the file
            // stays within the project's line-length limit.
            'settings' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0'
                . 'l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74'
                . 'l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73'
                . 'V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0'
                . ' 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0'
                . ' 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0'
                . ' 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
            'rotate-cw' => '<path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/>',
            'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/>'
                . '<line x1="12" x2="12" y1="3" y2="15"/>',
            'play' => '<polygon points="6 3 20 12 6 21 6 3"/>',
            'check' => '<path d="M20 6 9 17l-5-5"/>',
            'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        ];

        if (!isset($icons[$name])) {
            return '';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" '
            . 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" '
            . 'aria-hidden="true" focusable="false" class="lucide">' . $icons[$name] . '</svg>';
    }
}
