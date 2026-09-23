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
 * AI feature policy gate.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\feature;

defined('MOODLE_INTERNAL') || die();

use core_component;

/**
 * Decides whether a feature may be shown independently of user capability.
 */
final class policy {
    /** @var class-string<policy_provider>[]|null Cached provider classes. */
    private static ?array $providers = null;

    /**
     * Static-only.
     */
    private function __construct() {
    }

    /**
     * Whether the given feature is permitted on this site.
     *
     * @param descriptor $descriptor
     * @return bool
     */
    public static function is_allowed(descriptor $descriptor): bool {
        if (!$descriptor->is_premium()) {
            return true;
        }
        foreach (self::providers() as $provider) {
            try {
                if ($provider::is_feature_allowed($descriptor)) {
                    return true;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }
        return false;
    }

    /**
     * Whether a feature, named by its descriptor id, may be used here.
     *
     * `is_allowed()` wants a descriptor, which the caller must first fetch
     * from the registry. A page or a tool usually knows only its own feature
     * id, and it must not fall open when the registry has no descriptor for
     * that id -- a premium feature whose descriptor is missing is not thereby
     * free. Hence: unknown id, no permission.
     *
     * @param string $featureid Descriptor id, e.g. "coursegen".
     * @return bool
     */
    public static function allows_feature(string $featureid): bool {
        $descriptor = registry::all()[$featureid] ?? null;
        return $descriptor !== null && self::is_allowed($descriptor);
    }

    /**
     * Stop unless the named feature is licensed for this site.
     *
     * The counterpart to Moodle's `require_capability()`, for the other
     * question: a capability says who may, a grant says whether the site may
     * at all. Both surfaces of a premium feature -- its page and any tool it
     * contributes -- ask this, so the answer is written once.
     *
     * @param string $featureid Descriptor id.
     * @throws \moodle_exception When the feature is not granted.
     */
    public static function require_allowed(string $featureid): void {
        if (!self::allows_feature($featureid)) {
            throw new \moodle_exception('error_premiumrequired', 'local_elediaai_core');
        }
    }

    /**
     * Whether any premium policy provider is installed.
     *
     * @return bool
     */
    public static function has_provider(): bool {
        return self::providers() !== [];
    }

    /**
     * Discover the policy providers shipped by add-ons.
     *
     * @return class-string<policy_provider>[]
     */
    private static function providers(): array {
        if (self::$providers !== null) {
            return self::$providers;
        }
        $providers = [];
        foreach (core_component::get_component_classes_in_namespace(null, 'elediaai_core_policy') as $classname => $providerpath) {
            if (!registry::provider_file_is_readable($providerpath)) {
                continue;
            }

            try {
                if (!class_exists($classname)) {
                    continue;
                }
                if (!is_a($classname, policy_provider::class, true)) {
                    continue;
                }
            } catch (\Throwable $e) {
                continue;
            }

            $providers[] = $classname;
        }

        self::$providers = $providers;
        return self::$providers;
    }

    /**
     * Clear the in-process provider cache. For tests.
     *
     * @return void
     */
    public static function reset_cache(): void {
        self::$providers = null;
    }
}
