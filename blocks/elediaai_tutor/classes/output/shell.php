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
 * Plugin Shell adapter for eLeDia.ai Tutor pages.
 *
 * @package     block_elediaai_tutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_elediaai_tutor\output;

use html_writer;
use local_elediaai_core\output\section_nav;
use moodle_url;

/**
 * Builds and renders the shared Plugin Shell context.
 */
final class shell {
    /** @var string Configuration section key. */
    public const ACTIVE_CONFIGURATION = 'configuration';

    /** @var string Operator settings section key. */
    public const ACTIVE_SETTINGS = 'settings';

    /** @var string Tutor library section key. */
    public const ACTIVE_TUTORS = 'tutors';

    /** @var string Standalone tutor section key. */
    public const ACTIVE_PREVIEW = 'preview';

    /** @var string Per-instance "Settings" tab (this block's config; teacher-facing). */
    public const ACTIVE_INSTANCE_SETTINGS = 'instance-settings';

    /** @var string Per-instance "Tutor" tab (apply/import a tutor to this block). */
    public const ACTIVE_INSTANCE_TUTOR = 'instance-tutor';

    /** @var string Per-instance "Preview" tab (the tutor chat for this block's course). */
    public const ACTIVE_INSTANCE_PREVIEW = 'instance-preview';

    /** @var string Per-instance "MCP tokens" tab (the user's own MCP token preferences). */
    public const ACTIVE_INSTANCE_MCP = 'instance-mcp';

    /**
     * Check whether the shell helper is installed and autoloadable.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return class_exists(plugin_page::class) && class_exists(plugin_shell::class);
    }

    /**
     * Require the shared shell CSS.
     */
    public static function require_css(): void {
        global $PAGE, $CFG;

        // Cache-bust on the theme revision. The bare static include carried no version, so
        // browsers served a heuristically-cached copy (and, loading after the theme-aggregated
        // CSS, that stale copy won the cascade) — edits to styles.css appeared not to take.
        $rev = isset($CFG->themerev) ? (int) $CFG->themerev : -1;
        $params = ['rev' => $rev > 0 ? $rev : time()];
        $PAGE->requires->css(new moodle_url('/blocks/elediaai_tutor/styles.css', $params));
    }

    /**
     * Build the Zone-A shell context.
     *
     * The Settings slot points to this plugin-owned in-shell page. Moodle's
     * generic admin settings remain an operator-facing target linked from the
     * page content, in line with ux-system.md §3.5.
     *
     * @param string $active The active nav slot key (one of the ACTIVE_* constants).
     * @param bool $settingsiscurrent Whether the settings slot represents the current page.
     * @return array<string,mixed>
     */
    public static function context(
        string $active = self::ACTIVE_CONFIGURATION,
        bool $settingsiscurrent = false
    ): array {
        if (!self::is_available()) {
            return [];
        }

        $settingsurl = new moodle_url('/blocks/elediaai_tutor/operator_settings.php');
        $canconfigure = has_capability('moodle/site:config', \core\context\system::instance());
        $tagline = match ($active) {
            self::ACTIVE_SETTINGS => get_string('nav_settings', 'block_elediaai_tutor'),
            self::ACTIVE_TUTORS => get_string('managetutors', 'block_elediaai_tutor'),
            self::ACTIVE_PREVIEW => get_string('nav_preview', 'block_elediaai_tutor'),
            default => get_string('nav_configuration', 'block_elediaai_tutor'),
        };
        $subtitle = '';

        return [
            'name' => get_string('pluginname', 'block_elediaai_tutor'),
            'tagline' => $tagline,
            'subtitle' => $subtitle,
            'sectionnav' => self::sectionnav($active),
        ] + plugin_shell::action_slots(
            'block_elediaai_tutor',
            $canconfigure,
            $settingsurl,
            get_string('shell_help_label', 'block_elediaai_tutor'),
            get_string('shell_settings_label', 'block_elediaai_tutor'),
            $settingsiscurrent
        );
    }

    /**
     * Build the Plugin Shell section navigation.
     *
     * The cross-plugin tabs link almost exclusively to site-admin pages (each
     * enforces moodle/site:config). It is therefore shown only to users who can
     * actually use it, so teacher-reachable shell pages (e.g. instance_tutor.php)
     * don't surface admin links a non-admin would only get "access denied" from.
     *
     * @param string $active Active section key.
     * @return string Raw HTML for the Plugin Shell `sectionnav` slot (empty for non-admins).
     */
    public static function sectionnav(string $active): string {
        if (!has_capability('moodle/site:config', \core\context\system::instance())) {
            return '';
        }

        $items = [
            // Hiess „Dashboard" und zeigte auf eine Uebersichtsseite dieses
            // Blocks, die vier fremde Plugins nach ihrem Zustand fragte. Das
            // tun die jetzt selbst, der Bericht steht im Kern -- und wie sein
            // Reiter aussieht, sagt seither auch der Kern.
            ['key' => section_nav::SECTION_HEALTH] + section_nav::health_item(),
            [
                'key' => self::ACTIVE_SETTINGS,
                'icon' => 'sliders',
                'label' => get_string('nav_settings', 'block_elediaai_tutor'),
                'url' => new moodle_url('/blocks/elediaai_tutor/operator_settings.php'),
            ],
            [
                'key' => self::ACTIVE_TUTORS,
                'icon' => 'comments',
                'label' => get_string('nav_tutors', 'block_elediaai_tutor'),
                'url' => new moodle_url('/blocks/elediaai_tutor/manage_tutors.php'),
            ],
            [
                'key' => self::ACTIVE_PREVIEW,
                'icon' => 'eye',
                'label' => get_string('nav_preview', 'block_elediaai_tutor'),
                'url' => new moodle_url('/blocks/elediaai_tutor/view.php'),
            ],
        ];
        // Die drei Nachbarn -- LiteRAG, KI-Quellen, MCP -- standen hier als
        // eigene Liste, und dieselben drei Plugins griffen fuer *dieselbe*
        // Leiste in diesen Block hinein. Wer dazugehoert, weiss jetzt der
        // Kern; die vier Reiter oben bleiben, weil sie Seiten dieses Blocks
        // sind und den Kern nichts angehen.
        foreach (section_nav::infrastructure_items() as $key => $item) {
            $items[] = ['key' => $key] + $item;
        }

        return self::render_nav($items, $active);
    }

    /**
     * Open the shell for a per-instance block page (Settings / Tutor / Preview tabs
     * scoped to one block instance). Available to anyone with manage rights on the
     * instance; it carries no site-admin tabs and no settings cog.
     *
     * @param int $blockid The block_instances.id.
     * @param string $active One of the ACTIVE_INSTANCE_* keys.
     * @param string $modifier Shell width modifier.
     */
    public static function open_instance(
        int $blockid,
        string $active,
        string $modifier = plugin_page::MODIFIER_DEFAULT
    ): void {
        if (!self::is_available()) {
            return;
        }

        $coursename = \block_elediaai_tutor\local\placement::name(
            \core\context\block::instance($blockid)
        );

        // Mirror the site shell ("eLeDia.ai Tutor | <section>"), keeping the course in
        // parentheses so the instance is still clear, e.g. "Settings (Demo course)".
        $section = match ($active) {
            self::ACTIVE_INSTANCE_SETTINGS => get_string('nav_settings', 'block_elediaai_tutor'),
            self::ACTIVE_INSTANCE_TUTOR => get_string('nav_instance_tutor', 'block_elediaai_tutor'),
            self::ACTIVE_INSTANCE_PREVIEW => get_string('nav_preview', 'block_elediaai_tutor'),
            self::ACTIVE_INSTANCE_MCP => get_string('nav_instance_mcp', 'block_elediaai_tutor'),
            default => get_string('pluginname', 'block_elediaai_tutor'),
        };
        $tagline = $coursename !== ''
            ? get_string('instance_shell_tagline', 'block_elediaai_tutor', (object) [
                'section' => $section,
                'course' => $coursename,
            ])
            : $section;

        $context = [
            'name' => get_string('pluginname', 'block_elediaai_tutor'),
            'tagline' => $tagline,
            'subtitle' => '',
            'sectionnav' => self::instance_sectionnav($blockid, $active),
        ] + plugin_shell::action_slots('block_elediaai_tutor', false);

        // Easy return to the host course via the header's back-arrow slot.
        $courseid = self::instance_courseid($blockid);
        if ($courseid > 0) {
            $context['backurl'] = (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false);
            $context['backlabel'] = get_string('nav_back_to_course', 'block_elediaai_tutor');
        }

        plugin_page::open($context, $modifier);
        plugin_shell::content_open();
    }

    /**
     * Build the per-instance section navigation (Settings / Tutor / Preview),
     * scoped to a single block instance.
     *
     * @param int $blockid The block_instances.id.
     * @param string $active One of the ACTIVE_INSTANCE_* keys.
     * @return string Raw nav HTML.
     */
    public static function instance_sectionnav(int $blockid, string $active): string {
        $courseid = self::instance_courseid($blockid);
        // Preview keeps the blockid so view.php re-renders this instance shell (menu persists).
        $previewparams = ['blockid' => $blockid] + ($courseid > 0 ? ['courseid' => $courseid] : []);
        $items = [
            [
                'key' => self::ACTIVE_INSTANCE_SETTINGS,
                'icon' => 'sliders',
                'label' => get_string('nav_settings', 'block_elediaai_tutor'),
                'url' => new moodle_url('/blocks/elediaai_tutor/edit_instance.php', ['blockid' => $blockid]),
            ],
            [
                'key' => self::ACTIVE_INSTANCE_TUTOR,
                'icon' => 'comments',
                'label' => get_string('nav_instance_tutor', 'block_elediaai_tutor'),
                'url' => new moodle_url('/blocks/elediaai_tutor/instance_tutor.php', ['blockid' => $blockid]),
            ],
            [
                'key' => self::ACTIVE_INSTANCE_PREVIEW,
                'icon' => 'eye',
                'label' => get_string('nav_preview', 'block_elediaai_tutor'),
                'url' => new moodle_url('/blocks/elediaai_tutor/view.php', $previewparams),
            ],
        ];
        // The learner's own MCP token preferences, when the MCP webservice is installed.
        if (\core_component::get_plugin_directory('webservice', 'elediamcp') !== null) {
            $items[] = [
                'key' => self::ACTIVE_INSTANCE_MCP,
                'icon' => 'plug',
                'label' => get_string('nav_instance_mcp', 'block_elediaai_tutor'),
                // Carry the blockid so the token page re-renders this instance shell.
                'url' => new moodle_url('/webservice/elediamcp/token/index.php', ['blockid' => $blockid]),
            ];
        }
        return self::render_nav($items, $active);
    }

    /**
     * The course id a block instance belongs to, or 0 when it is not in a course
     * (e.g. a Dashboard block).
     *
     * @param int $blockid The block_instances.id.
     * @return int
     */
    private static function instance_courseid(int $blockid): int {
        return \block_elediaai_tutor\local\placement::courseid(
            \core\context\block::instance($blockid)
        );
    }

    /**
     * Render a list of nav items as the shared section-nav markup.
     *
     * @param array $items Nav items, each with key/icon/label/url.
     * @param string $active Active item key.
     * @return string Raw nav HTML.
     */
    private static function render_nav(array $items, string $active): string {
        $html = html_writer::start_tag('nav', [
            'class' => 'lh-plugin-section-nav',
            'aria-label' => get_string('nav_label', 'block_elediaai_tutor'),
        ]);
        foreach ($items as $item) {
            $attrs = [
                'class' => 'lh-plugin-section-nav__item',
                'href' => $item['url']->out(false),
            ];
            if ($item['key'] === $active) {
                $attrs['aria-current'] = 'page';
            }
            $label = \block_elediaai_tutor\local\icon::render($item['icon']) . ' ' . s($item['label']);
            $html .= html_writer::tag('a', $label, $attrs);
        }
        $html .= html_writer::end_tag('nav');
        return $html;
    }

    /**
     * Open the shell and its content wrapper.
     *
     * @param string $active Active section key.
     * @param bool $settingsiscurrent Whether the settings slot represents the current page.
     * @param string $modifier Shell width modifier.
     */
    public static function open(
        string $active = self::ACTIVE_CONFIGURATION,
        bool $settingsiscurrent = false,
        string $modifier = plugin_page::MODIFIER_DEFAULT
    ): void {
        if (!self::is_available()) {
            return;
        }

        plugin_page::open(
            self::context($active, $settingsiscurrent),
            $modifier
        );
        plugin_shell::content_open();
    }

    /**
     * Close the shell content wrapper.
     */
    public static function close(): void {
        if (!self::is_available()) {
            return;
        }

        plugin_shell::content_close();
        plugin_page::close();
    }
}
