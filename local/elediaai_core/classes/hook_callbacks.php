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
 * Top-of-body hook injecting the AI launcher pill.
 *
 * Mirrors local_lernhive_launcher's fallback pattern: emits hidden
 * HTML and a tiny JS bootstrap that anchors the pill next to the
 * LernHive launcher (theme_lernhive brand row) or next to the user
 * menu (everything else). The pill opens the AI Suite overview; feature
 * selection lives on that overview page.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

defined('MOODLE_INTERNAL') || die();

use core\hook\output\before_standard_head_html_generation;
use core\hook\output\before_standard_top_of_body_html_generation;
use core\hook\navigation\primary_extend;
use local_elediaai_core\feature\registry;
use moodle_url;

/**
 * Output hook callbacks for shared AI Suite presentation.
 */
class hook_callbacks {
    /**
     * Activity modules that count as "AI" for the shared brand colour.
     *
     * Their activity-chooser (and course-page) icon tile is tinted with the
     * eLeDia AI brand teal instead of Moodle's purpose colour, so learners
     * recognise AI activities at a glance. Frankenstyle components; the icon
     * image URL carries the component, which the CSS below matches on.
     */
    private const AI_ACTIVITY_COMPONENTS = ['mod_aichat', 'mod_aifeedback', 'mod_elli'];

    /** @var string eLeDia AI brand teal. */
    private const AI_BRAND_COLOUR = '#3aadaa';

    /**
     * Tint the AI activities' chooser/course icon tiles with the brand teal.
     *
     * Boost renders each activity icon on a purpose-coloured `.activityiconcontainer`
     * square (same class in the activity chooser and on the course page). We
     * override the background for the AI modules only, matched via the icon
     * image URL (`:has(img[src*="mod_aichat"])`).
     *
     * @param before_standard_head_html_generation $hook
     */
    public static function tint_ai_chooser_icons(before_standard_head_html_generation $hook): void {
        $selectors = array_map(
            static fn(string $component): string =>
                '.activityiconcontainer:has(img[src*="' . $component . '"])',
            self::AI_ACTIVITY_COMPONENTS
        );
        // Ohne Nachdruck: gemessen am 06.09.2026 auf /523 faerbt Moodle 5.2 die
        // Kachel fremder Aktivitaeten gar nicht (transparent), es gibt also
        // nichts zu ueberstimmen. Der Selektor mit :has() ist ausserdem
        // spezifischer als alles, was Boost auf den Container legt.
        $css = implode(",\n", $selectors)
            . " {\n    background-color: " . self::AI_BRAND_COLOUR . ";\n}";
        $hook->add_html('<style id="lh-ai-chooser-tint">' . $css . '</style>');
    }

    /**
     * Inject the global AI Suite launcher.
     *
     * @param before_standard_top_of_body_html_generation $hook
     */
    public static function inject_launcher(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE;

        if (!isloggedin() || isguestuser()) {
            return;
        }
        if (in_array($PAGE->pagelayout, ['login', 'popup', 'embedded', 'maintenance'], true)) {
            return;
        }

        $descriptors = registry::visible();
        if (empty($descriptors)) {
            return;
        }

        $renderer = $PAGE->get_renderer('core');
        $launcherhtml = $renderer->render_from_template('local_elediaai_core/launcher', [
            'title' => get_string('launcher_title', 'local_elediaai_core'),
            'subtitle' => get_string('launcher_subtitle', 'local_elediaai_core'),
            'url' => (new moodle_url('/local/elediaai_core/index.php'))->out(false),
        ]);

        $css = self::launcher_css();
        $js = self::launcher_js();
        $hook->add_html(
            $css
            . '<div id="lh-ai-launcher-host" class="lh-ai-launcher-host" hidden>'
            . $launcherhtml
            . '</div>'
            . $js
        );
    }

    /**
     * Put the AI Suite into Moodle's own primary navigation.
     *
     * The launcher pill above is markup this plugin docks into the navigation
     * bar itself. A theme is entitled to refuse that: theme_elediaai carries
     * exactly three controls on its bar and states that "the pages stay in the
     * launcher", so it hides the pill -- and with it, on that theme, the only
     * way to reach the suite.
     *
     * A primary-navigation node is the answer that does not depend on a
     * theme's chrome. Boost shows it on the bar, theme_elediaai's launcher
     * grid is built from the same navigation, and any other theme gets it
     * wherever it puts primary navigation. The pill stays for the themes that
     * want a one-click affordance next to the user menu.
     *
     * @param primary_extend $hook The navigation hook.
     * @return void
     */
    public static function extend_primary_navigation(primary_extend $hook): void {
        if (!isloggedin() || isguestuser()) {
            return;
        }
        if (empty(registry::visible())) {
            return;
        }

        $hook->get_primaryview()->add(
            // launcher_title ("AI Suite"), nicht shell_name: der Seitenkopf
            // heisst "eLeDia.ai | AI Suite", und in einem Kachelraster neben
            // "Dashboard" bricht das ueber zwei Zeilen.
            get_string('launcher_title', 'local_elediaai_core'),
            new moodle_url('/local/elediaai_core/index.php'),
            \navigation_node::TYPE_ROOTNODE,
            null,
            'aisuite'
        );
    }

    /**
     * Scoped CSS. Every rule is scoped under `.lh-ai-launcher-host` so
     * it cannot leak.
     */
    private static function launcher_css(): string {
        return '<style>
.lh-ai-launcher-host {
    display: inline-flex;
    align-items: center;
    margin: 0 0.15rem;
    position: relative;
    z-index: 1040;
}
/* Bootstraps Reboot erzwingt [hidden] global mit !important. */
.lh-ai-launcher-host[hidden] { display: none; }
.lh-ai-launcher-host .lh-ai-launcher { display: inline-flex; align-items: center; }
.lh-ai-launcher-host .lh-ai-launcher__link {
    align-items: center;
    background: transparent;
    border: 0;
    border-radius: var(--eai-radius-pill);
    color: var(--eai-fg);
    cursor: pointer;
    display: inline-flex;
    height: 2.25rem;
    justify-content: center;
    list-style: none;
    outline: none;
    padding: 0;
    text-decoration: none;
    transition: background 120ms ease, color 120ms ease;
    width: 2.25rem;
}
.lh-ai-launcher-host .lh-ai-launcher__link:hover,
.lh-ai-launcher-host .lh-ai-launcher__link:focus-visible {
    background: var(--eai-ai-wash);
    color: var(--eai-ai);
    outline: none;
}
.lh-ai-launcher-host .lh-ai-launcher__summary-icon,
.lh-ai-launcher-host .lh-ai-launcher__summary-icon svg {
    display: block;
    height: 1.15rem;
    width: 1.15rem;
}
.lh-ai-launcher-host .lh-ai-launcher__summary-icon svg {
    fill: none;
    stroke: currentColor;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 2;
}
.lh-ai-launcher-host .lh-ai-launcher__title,
.lh-ai-launcher-host .lh-ai-launcher__subtitle {
    clip: rect(0 0 0 0);
    border: 0;
    height: 1px;
    margin: -1px;
    overflow: hidden;
    padding: 0;
    position: absolute;
    white-space: nowrap;
    width: 1px;
}
</style>';
    }

    /**
     * Bootstrap that anchors the pill into the navbar / theme_lernhive
     * brand row so it sits flush with the LernHive launcher.
     */
    private static function launcher_js(): string {
        return '<script>
document.addEventListener("DOMContentLoaded", function() {
    var host = document.getElementById("lh-ai-launcher-host");
    if (!host) {
        return;
    }
    // Resolution order:
    //   1. theme_lernhive brand row, right after the LH launcher pill.
    //   2. Right next to the LH launcher fallback (other themes).
    //   3. Right before the whole .usermenu-container in the navbar.
    //   4. End of the navbar container.
    // Never dock inside .usermenu-container (the core user-menu region): a
    // foreign node in front of .usermenu leaves the user-menu toggle
    // unresponsive on click (SUI-5).
    var anchor = document.querySelector(".lernhive-brand__launcher");
    if (anchor && anchor.parentNode) {
        anchor.parentNode.insertBefore(host, anchor.nextSibling);
    } else {
        var lhFallback = document.getElementById("local-lernhive-launcher-navbar-fallback");
        if (lhFallback && lhFallback.parentNode) {
            lhFallback.parentNode.insertBefore(host, lhFallback);
        } else {
            var usermenu = document.querySelector(".usermenu");
            var usermenucontainer = usermenu ? usermenu.closest(".usermenu-container") : null;
            var usermenuanchor = usermenucontainer || usermenu;
            if (usermenuanchor && usermenuanchor.parentNode) {
                usermenuanchor.parentNode.insertBefore(host, usermenuanchor);
            } else {
                var navbar = document.querySelector(".navbar .container-fluid, .navbar .container, .navbar");
                if (navbar) {
                    navbar.appendChild(host);
                }
            }
        }
    }
    host.hidden = false;
});
</script>';
    }
}
