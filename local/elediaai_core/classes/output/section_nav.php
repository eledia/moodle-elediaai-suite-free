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
 * Plugin section navigation for local_elediaai_core (UX-system §3.4.1).
 *
 * Two scopes:
 *   - Suite scope (overview + audit + health) — peer surfaces of the
 *     umbrella. All three are reports about the suite as a whole and
 *     belong to no single feature.
 *   - Feature scope (overview + settings) — the peer surfaces of
 *     a single feature once the user drilled into it. "Overview" here
 *     means *the feature's* start page, not the suite-wide dashboard,
 *     so context stays inside the feature.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\output;

use local_elediaai_core\feature\registry;
use local_elediaai_core\local\audit_config;
use local_elediaai_core\output\lucide_icon;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared navigation renderer for Core pages.
 */
class section_nav {
    // Suite-level keys.
    /** @var string Navigation key. */
    public const SECTION_OVERVIEW = 'overview';
    /** @var string Navigation key. */
    public const SECTION_AUDIT = 'audit';
    /** @var string Navigation key. */
    public const SECTION_HEALTH = 'health';

    // Feature-level keys.
    /** @var string Navigation key. */
    public const FEATURE_SUITE = 'suite';
    /** @var string Navigation key. */
    public const FEATURE_OVERVIEW = 'overview';
    /** @var string Navigation key. */
    public const FEATURE_AUDIT = 'audit';
    /** @var string Navigation key. */
    public const FEATURE_AUDIT_TECHNICAL = 'audittechnical';
    /** @var string Navigation key. */
    public const FEATURE_AUDIT_DIDACTIC = 'auditdidactic';
    /** @var string Navigation key. */
    public const FEATURE_AUDIT_ACTIONS = 'auditactions';
    /** @var string Navigation key. */
    public const FEATURE_PLUGINS = 'plugins';
    /** @var string Navigation key. */
    public const FEATURE_SETTINGS = 'settings';

    // Infrastructure keys.
    /** @var string Suite infrastructure: the tutor's own configuration. */
    public const INFRA_TUTOR = 'tutorconfig';

    /** @var string Suite infrastructure: LiteRAG. */
    public const INFRA_LITERAG = 'literag';

    /** @var string Suite infrastructure: AI Sources. */
    public const INFRA_SOURCES = 'aisources';

    /** @var string Suite infrastructure: the MCP web service. */
    public const INFRA_MCP = 'elediamcp';

    /**
     * Suite-wide nav: Overview (dashboard) + Audit + Health, each when allowed.
     *
     * @param string $current One of SECTION_OVERVIEW | SECTION_AUDIT | SECTION_HEALTH.
     */
    public static function render(string $current): string {
        $items = [
            self::SECTION_OVERVIEW => [
                'url' => new moodle_url('/local/elediaai_core/index.php'),
                'icon' => 'th-large',
                'label' => get_string('nav_overview', 'local_elediaai_core'),
            ],
        ];

        if (audit_config::can_view(\core\context\system::instance())) {
            $items[self::SECTION_AUDIT] = [
                'url' => new moodle_url('/local/elediaai_core/audit.php'),
                'icon' => 'clock-rotate-left',
                'label' => get_string('nav_audit', 'local_elediaai_core'),
            ];
        }

        // Der Zustandsbericht ist wie das Audit eine Auskunft ueber die ganze
        // Suite und gehoert deshalb daneben. Er stand nur in der Leiste der
        // Grundierungskette -- wer keines der vier Nachbarplugins installiert
        // hat, kam an einen Bericht des Kerns gar nicht heran.
        if (has_capability('moodle/site:config', \core\context\system::instance())) {
            $items[self::SECTION_HEALTH] = self::health_item();
        }

        return self::render_items($items, $current);
    }

    /**
     * The one description of the health report as a navigation entry.
     *
     * The report appears in three bars -- beside the audit, in the
     * infrastructure bar, and among the tutor's own tabs -- because each is a
     * different way of arriving at the same question. Only one of them says
     * what it looks like: the tutor block kept a hand-written copy of URL,
     * icon and label, and a copy is a thing that drifts.
     *
     * @return array{url: moodle_url, icon: string, label: string}
     */
    public static function health_item(): array {
        return [
            'url' => new moodle_url('/local/elediaai_core/health.php'),
            'icon' => 'clipboard-check',
            'label' => get_string('nav_infra_health', 'local_elediaai_core'),
        ];
    }

    /**
     * The navigation across the suite's infrastructure pages.
     *
     * Tutor configuration, LiteRAG, AI Sources and the MCP service are one
     * administrative surface: whoever sets up grounded answers walks all four.
     * The bar lived in `block_elediaai_tutor` and three plugins reached into
     * that block to get it -- a block is an odd home for chrome that is not
     * about the block.
     *
     * Entries for the three companion plugins appear only where the plugin is
     * installed. The tutor's own pages pass their finer keys through
     * unchanged; an unknown key simply marks nothing as current, which is what
     * a page outside this bar should look like.
     *
     * @param string $current Active key; one of the INFRA_* constants or a
     *                        tutor-internal key. SECTION_HEALTH is never
     *                        current here: health.php shows the suite bar.
     * @return string HTML, or the empty string without moodle/site:config.
     */
    public static function render_infrastructure(string $current): string {
        if (!has_capability('moodle/site:config', \core\context\system::instance())) {
            return '';
        }

        $items = [
            // Der Zustandsbericht steht vorn: wer diese Leiste benutzt, sucht
            // meist, warum etwas nicht geht. Hier ist er ein Querverweis --
            // markiert wird er auf der Suite-Leiste, die health.php zeigt.
            self::SECTION_HEALTH => self::health_item(),
            self::INFRA_TUTOR => [
                'url' => new moodle_url('/blocks/elediaai_tutor/operator_settings.php'),
                'icon' => 'tachometer',
                'label' => get_string('nav_infra_tutor', 'local_elediaai_core'),
            ],
        ] + self::infrastructure_items();

        return self::render_items($items, $current);
    }

    /**
     * The companion plugins of the grounding chain, where they are installed.
     *
     * Separate from the renderer because the tutor's own bar carries four tabs
     * of its own and only wants this part appended. One list, two readers --
     * the alternative was the same three entries maintained twice.
     *
     * @return array<string, array<string, mixed>> Keyed by INFRA_* constant.
     */
    public static function infrastructure_items(): array {
        $begleiter = [
            'local_literag' => [self::INFRA_LITERAG, 'database', 'nav_infra_literag',
                new moodle_url('/admin/settings.php', ['section' => 'local_literag'])],
            'local_elediaai_sources' => [self::INFRA_SOURCES, 'upload', 'nav_infra_sources',
                new moodle_url('/admin/settings.php', ['section' => 'local_elediaai_sources_settings'])],
            'webservice_elediamcp' => [self::INFRA_MCP, 'plug', 'nav_infra_mcp',
                new moodle_url('/webservice/elediamcp/configuration.php')],
        ];

        $items = [];
        foreach ($begleiter as $component => [$key, $icon, $stringid, $url]) {
            if (\core_component::get_component_directory($component) === null) {
                continue;
            }
            $items[$key] = [
                'url' => $url,
                'icon' => $icon,
                'label' => get_string($stringid, 'local_elediaai_core'),
            ];
        }

        return $items;
    }

    /**
     * Navigation for one feature's own pages: the feature's start page, its
     * tool surfaces and Settings, the last only for a feature that ships an
     * in-shell config page to a user holding `moodle/site:config`. The
     * settings tab is surfaced on the feature's own configuration page too,
     * so the navigation persists on sub-pages -- UX-system §3.4.
     *
     * @param string $featureid Descriptor id (e.g. "qtype_aitext").
     * @param string $current FEATURE_OVERVIEW | FEATURE_AUDIT | FEATURE_SETTINGS.
     * @return string HTML.
     */
    public static function render_for_feature(string $featureid, string $current): string {
        $descriptor = registry::all()[$featureid] ?? null;
        if ($descriptor === null) {
            return '';
        }

        $cansiteconfig = has_capability('moodle/site:config', \core\context\system::instance());

        $items = [
            self::FEATURE_SUITE => [
                'url' => new moodle_url('/local/elediaai_core/index.php'),
                'icon' => 'th-large',
                'label' => get_string('shell_name', 'local_elediaai_core'),
            ],
        ];
        // „Uebersicht" ist der Punkt, auf dem der Leser steht, wenn er die
        // Startseite des Werkzeugs offen hat -- also zeigt er auch dorthin.
        // Bis hierher zeigte er auf die Erklaerseite im Kern: man stand auf
        // dem Werkzeug, der als aktuell markierte Punkt fuehrte woandershin,
        // und die Erklaerung gab es daneben im Handbuch ein zweites Mal.
        //
        // Das Audit bringt seine Startseite selbst mit (FEATURE_AUDIT zeigt
        // auf dieselbe URL wie launchurl). Beide Punkte zu setzen hiesse: zwei
        // Reiter, zwei Namen, eine Seite -- genau das, was hier aufgeraeumt
        // wird.
        if ($featureid !== 'audit') {
            $items[self::FEATURE_OVERVIEW] = [
                'url' => $descriptor->launchurl
                    ?? new moodle_url('/local/elediaai_core/index.php'),
                'icon' => 'info-circle',
                'label' => get_string('nav_overview', 'local_elediaai_core'),
            ];
        }

        if ($featureid === 'audit' && audit_config::can_view(\core\context\system::instance())) {
            $items[self::FEATURE_AUDIT] = [
                'url' => new moodle_url('/local/elediaai_core/audit.php'),
                'icon' => 'clock-rotate-left',
                'label' => get_string('nav_audit_home', 'local_elediaai_core'),
            ];
            // Reihenfolge nach der Frage, die jemand stellt: erst "was haben
            // die Lernenden gefragt", dann "was hat die KI getan", dann die
            // einzelnen Anfragen, zuletzt die Verwaltung.
            //
            // Die Beschriftung ist wortgleich mit dem Kartentitel und dem
            // Seitentitel des Ziels. Vorher stand im Reiter ein kuerzerer
            // Begriff ("Kurs-Einblicke", "Handlungen"), auf der Karte ein
            // anderer -- und wer den Reiter geklickt hatte, landete auf einer
            // Seite, die noch einmal anders hiess.
            $items[self::FEATURE_AUDIT_DIDACTIC] = [
                'url' => new moodle_url('/local/elediaai_core/audit_didactic.php'),
                'icon' => 'chart-simple',
                'label' => get_string('surface_insights_title', 'local_elediaai_core'),
            ];
            // Eigene Capability, nicht die des Audits: das Handlungsprotokoll
            // ist personenbezogen und auf Kursebene vergebbar.
            if (has_capability('local/elediaai_core:viewaiactions', \core\context\system::instance())) {
                $items[self::FEATURE_AUDIT_ACTIONS] = [
                    'url' => new moodle_url('/local/elediaai_core/audit_actions.php'),
                    'icon' => 'shield-alt',
                    'label' => get_string('surface_actions_title', 'local_elediaai_core'),
                ];
            }
            $items[self::FEATURE_AUDIT_TECHNICAL] = [
                'url' => new moodle_url('/local/elediaai_core/audit_technical.php'),
                'icon' => 'list-check',
                'label' => get_string('surface_requests_title', 'local_elediaai_core'),
            ];
            // Die Einstellungen sind eine der vier Flaechen und stehen auf der
            // Uebersicht als Karte. Ohne diesen Reiter war die Seite, auf der
            // man dann stand, im Menue nicht vorhanden -- kein Punkt als
            // aktuell markiert, kein Weg zurueck ausser dem Formular.
            if ($cansiteconfig) {
                $items[self::FEATURE_SETTINGS] = [
                    'url' => new moodle_url('/local/elediaai_core/audit_settings.php'),
                    'icon' => 'sliders',
                    'label' => get_string('surface_settings_title', 'local_elediaai_core'),
                ];
            }
        }

        if ($featureid === 'strategyhelper' && $cansiteconfig) {
            $items[self::FEATURE_PLUGINS] = [
                'url' => new moodle_url('/local/elediaai_strategy/plugins.php'),
                'icon' => 'sliders',
                'label' => get_string('nav_ai_plugins', 'local_elediaai_strategy'),
            ];
        }

        // Settings tab only when the descriptor ships an in-shell page
        // and the user has the capability to see it. We refuse admin
        // tree URLs because the tab must keep the user inside the
        // Plugin Shell (ux-system §3.5).
        if ($featureid !== 'audit' && $cansiteconfig && $descriptor->configurl !== null) {
            $configurlstring = $descriptor->configurl->out(false);
            if (!str_contains($configurlstring, '/admin/')) {
                $items[self::FEATURE_SETTINGS] = [
                    'url' => $descriptor->configurl,
                    'icon' => 'cog',
                    'label' => get_string('settings', 'core'),
                ];
            }
        }

        return self::render_items($items, $current);
    }

    /**
     * Shared renderer for both scopes.
     *
     * @param array $items Keyed by section/feature id; each entry has url/icon/label.
     * @param string $current
     */
    private static function render_items(array $items, string $current): string {
        $navlabel = get_string('nav_aria_label', 'local_elediaai_core');
        $html = '<nav class="lh-plugin-section-nav" aria-label="' . s($navlabel) . '">';
        foreach ($items as $key => $item) {
            $isactive = ($key === $current);
            $attrs = 'class="lh-plugin-section-nav__item" href="' . $item['url']->out(false) . '"';
            if ($isactive) {
                $attrs .= ' aria-current="page"';
            }
            $html .= '<a ' . $attrs . '>'
                . lucide_icon::render($item['icon']) . ' '
                . s($item['label'])
                . '</a>';
        }
        $html .= '</nav>';
        return $html;
    }
}
