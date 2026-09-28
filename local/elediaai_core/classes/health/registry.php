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
 * Collects what the suite's plugins report about themselves.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_elediaai_core\health;

use core\output\notification;
use core_admin\local\settings\linkable_settings_page;
use core_component;
use moodle_url;

/**
 * Discovery for `health_provider`, along the same path as the feature registry.
 *
 * Nothing is cached. A health report that answers from a cache is not a
 * health report -- the reader opened the page to learn the state now, and a
 * stale "everything fine" is worse than a slow page.
 */
final class registry {
    /** @var string Namespace the providers live in, below their component. */
    private const PROVIDER_NAMESPACE = 'elediaai_core';

    /**
     * Every check every installed plugin reports, in component order.
     *
     * A provider that throws costs itself, not the page: a plugin whose
     * backend is on fire is exactly the one whose report must not take the
     * others down with it. Its failure becomes a check of its own, because
     * "this plugin could not say how it is" is itself worth reading.
     *
     * @return check[]
     */
    public static function all(): array {
        $out = [];
        $classes = core_component::get_component_classes_in_namespace(null, self::PROVIDER_NAMESPACE);

        foreach (array_keys($classes) as $classname) {
            if (!str_ends_with($classname, '\\health_provider')) {
                continue;
            }
            if (!is_a($classname, health_provider::class, true)) {
                continue;
            }

            $component = self::component_of($classname);
            try {
                foreach ($classname::get_checks() as $check) {
                    if ($check instanceof check) {
                        $out[] = $check;
                    }
                }
            } catch (\Throwable $e) {
                $out[] = new check(
                    id: 'provider_failed',
                    component: $component,
                    label: get_string('health_provider_failed', 'local_elediaai_core', $component),
                    status: check::STATUS_ERROR,
                    detail: $e->getMessage(),
                );
            }
        }

        usort($out, static fn(check $a, check $b): int => [$a->component, $a->id] <=> [$b->component, $b->id]);

        return $out;
    }

    /**
     * The checks that need somebody to act, newest concern first.
     *
     * @return check[]
     */
    public static function attention(): array {
        return array_values(array_filter(self::all(), static fn(check $c): bool => $c->needs_attention()));
    }

    /**
     * Where the reader of a check goes to act on it.
     *
     * A check that names its own destination keeps it. One that does not --
     * the chat backend reported "not set up" and offered no way to set it
     * up -- gets its component's settings page, if the admin tree has one.
     * The page itself stays with the plugin: the core only finds it.
     *
     * @param check $check The check.
     * @return moodle_url|null Null when the component has no settings page.
     */
    public static function action_url(check $check): ?moodle_url {
        return $check->actionurl ?? self::settings_url($check->component);
    }

    /**
     * The admin settings page of a component, if it has one.
     *
     * Moodle knows the section name for most plugin types (`modsetting...`,
     * `blocksetting...`), but not for local plugins: those name their page
     * themselves, by convention after the component, some with a
     * `_settings` suffix. Both are tried, in that order.
     *
     * @param string $component Frankenstyle component name.
     * @return moodle_url|null
     */
    public static function settings_url(string $component): ?moodle_url {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');

        $info = \core_plugin_manager::instance()->get_plugin_info($component);
        if ($info === null) {
            return null;
        }
        $url = $info->get_settings_url();
        if ($url !== null) {
            return $url;
        }

        $root = admin_get_root();
        foreach ([$component, $component . '_settings'] as $section) {
            $node = $root->locate($section);
            if ($node instanceof linkable_settings_page) {
                return $node->get_settings_page_url();
            }
        }
        return null;
    }

    /**
     * The one-line summary above the checks, and how loud it is.
     *
     * Two numbers, because they ask for two different things. A fault or a
     * warning is an alarm; "not set up" is a task. The summary used to count
     * only the first and call it "things that need attention", so a page with
     * one warning and three "not set up" rows said one thing needed
     * attention -- true by the definition in the code, false to the reader.
     * Now each number is named for what it counts.
     *
     * @param check[] $checks The checks on the page.
     * @return array{0: string, 1: string} Message and notification type.
     */
    public static function summary(array $checks): array {
        $attention = count(array_filter($checks, static fn(check $c): bool => $c->needs_attention()));
        $unconfigured = count(array_filter(
            $checks,
            static fn(check $c): bool => $c->status === check::STATUS_UNCONFIGURED
        ));

        if ($attention > 0 && $unconfigured > 0) {
            return [
                get_string('health_summary_attention_unconfigured', 'local_elediaai_core', (object) [
                    'attention' => $attention,
                    'unconfigured' => $unconfigured,
                ]),
                notification::NOTIFY_WARNING,
            ];
        }
        if ($attention > 0) {
            return [
                get_string('health_summary_attention', 'local_elediaai_core', $attention),
                notification::NOTIFY_WARNING,
            ];
        }
        if ($unconfigured > 0) {
            return [
                get_string('health_summary_unconfigured', 'local_elediaai_core', $unconfigured),
                notification::NOTIFY_INFO,
            ];
        }
        return [
            get_string('health_summary_quiet', 'local_elediaai_core', count($checks)),
            notification::NOTIFY_SUCCESS,
        ];
    }

    /**
     * The component a provider class belongs to.
     *
     * @param string $classname Fully qualified provider class name.
     * @return string Frankenstyle component, or the class name when unclear.
     */
    private static function component_of(string $classname): string {
        $parts = explode('\\', ltrim($classname, '\\'));
        return $parts[0] ?? $classname;
    }
}
