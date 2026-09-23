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

namespace local_elediaai_sources\sink;

/**
 * Resolves the configured destination into exactly one sink instance.
 *
 * One destination is active at a time. Switching is supported and makes every
 * course diverge; running two destinations in parallel is not supported, which
 * is why the setting is a single choice rather than a multi-select.
 *
 * A further destination is added by implementing {@see sink} and listing the
 * class in {@see CLASSES}; the settings page and this resolver pick it up from
 * there. That is the extension point the URL-shape heuristic used to occupy.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sink_manager {
    /** @var string[] Known sink implementations, in settings order. */
    private const CLASSES = [
        ingestion_api_sink::class,
        literag_sink::class,
    ];

    /** @var string The destination used when nothing is configured yet. */
    public const DEFAULT_SINK = 'ingestionapi';

    /**
     * The id of the configured destination.
     *
     * An unknown or unset value falls back to the default rather than leaving
     * the plugin without a destination: a typo in the config table must not
     * turn every ingestion into a fatal error.
     *
     * @return string The sink id.
     */
    public static function active_id(): string {
        $configured = (string) get_config('local_elediaai_sources', 'sink');
        return isset(self::classes_by_id()[$configured]) ? $configured : self::DEFAULT_SINK;
    }

    /**
     * The one active destination.
     *
     * @return sink The configured sink instance.
     */
    public static function active(): sink {
        $classname = self::classes_by_id()[self::active_id()];
        return new $classname();
    }

    /**
     * Remember which destination was active before the current one.
     *
     * Called from the setting's updated-callback, where the previous value is
     * already overwritten — hence the shadow, which is written one step behind
     * and therefore still holds the predecessor.
     *
     * @return void
     */
    public static function note_switch(): void {
        $shadow = (string) get_config('local_elediaai_sources', 'sink_shadow');
        $current = self::active_id();

        if ($shadow !== '' && $shadow !== $current) {
            set_config('previoussink', $shadow, 'local_elediaai_sources');
        }
        set_config('sink_shadow', $current, 'local_elediaai_sources');
    }

    /**
     * The destination the site switched away from, if any is pending cleanup.
     *
     * @return string The sink id, or '' when there is nothing to clear.
     */
    public static function previous_id(): string {
        $previous = (string) get_config('local_elediaai_sources', 'previoussink');
        if ($previous === '' || $previous === self::active_id()) {
            return '';
        }
        return isset(self::classes_by_id()[$previous]) ? $previous : '';
    }

    /**
     * Forget the pending previous destination.
     *
     * @return void
     */
    public static function forget_previous(): void {
        unset_config('previoussink', 'local_elediaai_sources');
    }

    /**
     * A destination by id, whether or not it is the active one.
     *
     * Needed to address a destination the site has already switched away
     * from — clearing it is the one operation that must talk to something
     * other than the current target.
     *
     * @param string $id The sink id.
     * @return sink|null The instance, or null when no sink carries that id.
     */
    public static function instance(string $id): ?sink {
        $classname = self::classes_by_id()[$id] ?? null;
        return $classname !== null ? new $classname() : null;
    }

    /**
     * Options for the destination setting: id => translated name.
     *
     * @return array<string, string>
     */
    public static function menu(): array {
        $menu = [];
        foreach (self::CLASSES as $classname) {
            $menu[$classname::id()] = $classname::name();
        }
        return $menu;
    }

    /**
     * Known sinks keyed by their id.
     *
     * @return array<string, class-string<sink>>
     */
    private static function classes_by_id(): array {
        $map = [];
        foreach (self::CLASSES as $classname) {
            $map[$classname::id()] = $classname;
        }
        return $map;
    }
}
