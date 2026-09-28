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
 * Which suite components are installed, in which version, and whether they are on.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_elediaai_core\output;

use core_component;
use html_table;
use html_writer;
use local_elediaai_core\feature\registry;
use moodle_url;

/**
 * The administrator's inventory below the dashboard tiles.
 *
 * Deliberately not a tile per component. A tile stands for a feature -- a
 * place somebody opens or a capability somebody uses in a course -- and
 * every visitor sees the tiles. A component is a plugin: the chat engine,
 * for instance, has no feature of its own and therefore no tile, but it is
 * part of the suite and has a version. Versions and switches are the
 * administrator's business, so this table is shown only to those who hold
 * `moodle/site:config`, and it lists plugins rather than features.
 *
 * A suite component is any installed plugin that ships a class in the
 * `elediaai_core` namespace (a feature or health provider), plus the core.
 */
final class component_overview {
    /** @var string Every feature of the component is switched on. */
    public const STATUS_ACTIVE = 'active';

    /** @var string Some, not all, of its features are switched off. */
    public const STATUS_PARTIAL = 'partial';

    /** @var string All of its features are switched off in the suite settings. */
    public const STATUS_OFF = 'off';

    /** @var string The component has no feature switch: it runs whenever installed. */
    public const STATUS_NOSWITCH = 'noswitch';

    /** @var string Moodle itself has the plugin disabled, whatever the suite says. */
    public const STATUS_MOODLE_DISABLED = 'moodledisabled';

    /** @var string Namespace suite plugins ship their providers under. */
    private const PROVIDER_NAMESPACE = 'elediaai_core';

    /**
     * Static-only.
     */
    private function __construct() {
    }

    /**
     * Whether the current user may see the inventory.
     *
     * @return bool
     */
    public static function can_view(): bool {
        return has_capability('moodle/site:config', \core\context\system::instance());
    }

    /**
     * Frankenstyle names of every suite component, installed or not.
     *
     * @return string[]
     */
    private static function components(): array {
        $components = ['local_elediaai_core' => true];

        $classes = core_component::get_component_classes_in_namespace(null, self::PROVIDER_NAMESPACE);
        foreach (array_keys($classes) as $classname) {
            $components[explode('\\', ltrim($classname, '\\'))[0]] = true;
        }

        foreach (registry::all() as $descriptor) {
            if (!registry::is_placeholder($descriptor)) {
                $components[$descriptor->component] = true;
            }
        }

        return array_keys($components);
    }

    /**
     * One row per installed suite component.
     *
     * @return \stdClass[] Each with component, name, release, version, status and switchedoff (feature names).
     */
    public static function rows(): array {
        $pluginman = \core_plugin_manager::instance();

        $features = [];
        foreach (registry::all() as $descriptor) {
            if (registry::is_placeholder($descriptor) || !registry::is_switchable($descriptor->id)) {
                continue;
            }
            $features[$descriptor->component][] = $descriptor;
        }

        $rows = [];
        foreach (self::components() as $component) {
            $info = $pluginman->get_plugin_info($component);
            if ($info === null || empty($info->versiondb)) {
                continue;
            }

            $own = $features[$component] ?? [];
            $off = array_values(array_map(
                static fn($d): string => $d->name,
                array_filter($own, static fn($d): bool => !registry::is_enabled($d->id))
            ));

            if ($info->is_enabled() === false) {
                $status = self::STATUS_MOODLE_DISABLED;
            } else if (empty($own)) {
                $status = self::STATUS_NOSWITCH;
            } else if (empty($off)) {
                $status = self::STATUS_ACTIVE;
            } else if (count($off) === count($own)) {
                $status = self::STATUS_OFF;
            } else {
                $status = self::STATUS_PARTIAL;
            }

            $rows[] = (object) [
                'component' => $component,
                'name' => (string) $info->displayname,
                'release' => (string) ($info->release ?? ''),
                'version' => (string) $info->versiondb,
                'status' => $status,
                'switchedoff' => $off,
            ];
        }

        usort($rows, static fn($a, $b): int => strcasecmp($a->name, $b->name));
        return $rows;
    }

    /**
     * The inventory as a section, or '' for anyone who may not see it.
     *
     * @return string HTML.
     */
    public static function render(): string {
        if (!self::can_view()) {
            return '';
        }
        $rows = self::rows();
        if (empty($rows)) {
            return '';
        }

        $table = new html_table();
        $table->attributes = ['class' => 'generaltable lh-ai-suite-components__table'];
        $table->caption = get_string('components_caption', 'local_elediaai_core');
        $table->captionhide = true;
        $table->head = [
            get_string('components_col_component', 'local_elediaai_core'),
            get_string('components_col_release', 'local_elediaai_core'),
            get_string('components_col_version', 'local_elediaai_core'),
            get_string('components_col_status', 'local_elediaai_core'),
        ];
        foreach ($rows as $row) {
            $status = get_string('components_status_' . $row->status, 'local_elediaai_core');
            if (!empty($row->switchedoff) && $row->status === self::STATUS_PARTIAL) {
                $status .= html_writer::tag(
                    'div',
                    s(get_string('components_switchedoff', 'local_elediaai_core', implode(', ', $row->switchedoff))),
                    ['class' => 'small text-muted']
                );
            }
            $table->data[] = [
                html_writer::tag('span', s($row->name)) .
                    html_writer::tag('div', s($row->component), ['class' => 'small text-muted']),
                s($row->release),
                s($row->version),
                html_writer::tag('span', $status, ['data-component-status' => $row->status]),
            ];
        }

        $switches = new moodle_url('/admin/settings.php', ['section' => 'local_elediaai_core_audit']);

        return html_writer::tag(
            'section',
            html_writer::tag('h2', get_string('components_heading', 'local_elediaai_core'), [
                'id' => 'lh-ai-suite-components-heading',
                'class' => 'h4 mt-5',
            ]) .
            html_writer::tag('p', get_string('components_intro', 'local_elediaai_core')) .
            html_writer::table($table) .
            html_writer::link($switches, get_string('components_switches', 'local_elediaai_core')),
            [
                'class' => 'lh-ai-suite-components',
                'aria-labelledby' => 'lh-ai-suite-components-heading',
                'data-region' => 'suite-components',
            ]
        );
    }
}
