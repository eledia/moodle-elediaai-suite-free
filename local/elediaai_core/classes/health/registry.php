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

use core_component;

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
