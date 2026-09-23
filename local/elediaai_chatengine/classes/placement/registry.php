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

namespace local_elediaai_chatengine\placement;

/**
 * Finds the placement implementation belonging to a component.
 *
 * By convention rather than by configuration: a component that wants a chat
 * ships `\<component>\chatengine\placement` and is thereby a placement. There
 * is no list to keep in step, and a placement that is uninstalled stops being
 * one without anything else having to be told.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class registry {
    /** @var array<string, placement> Test-only overrides, keyed by component. */
    private static array $overrides = [];

    /**
     * The placement for a component, or null when it is not one.
     *
     * @param string $component Frankenstyle component name.
     * @return placement|null The implementation, or null.
     */
    public static function get(string $component): ?placement {
        if (isset(self::$overrides[$component])) {
            return self::$overrides[$component];
        }

        $classname = '\\' . $component . '\\chatengine\\placement';
        if (!class_exists($classname) || !is_subclass_of($classname, placement::class)) {
            return null;
        }

        return new $classname();
    }

    /**
     * The placement for a component, or a failure naming it.
     *
     * @param string $component Frankenstyle component name.
     * @return placement The implementation.
     * @throws \coding_exception When the component is not a placement.
     */
    public static function require_placement(string $component): placement {
        $placement = self::get($component);
        if ($placement === null) {
            throw new \coding_exception('Not a chat engine placement: ' . $component);
        }

        return $placement;
    }

    /**
     * Inject a fake placement (PHPUnit only).
     *
     * @param string $component Frankenstyle component name.
     * @param placement|null $placement The fake, or null to remove the override.
     * @return void
     * @throws \coding_exception Outside PHPUnit.
     */
    public static function override_for_testing(string $component, ?placement $placement): void {
        if (!defined('PHPUNIT_TEST') || !PHPUNIT_TEST) {
            throw new \coding_exception('registry::override_for_testing() is only available in PHPUnit tests.');
        }
        if ($placement === null) {
            unset(self::$overrides[$component]);
            return;
        }
        self::$overrides[$component] = $placement;
    }
}
