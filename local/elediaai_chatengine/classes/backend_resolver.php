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

namespace local_elediaai_chatengine;

use local_elediaai_chatengine\adapter\adapter;
use local_elediaai_chatengine\adapter\adapter_exception;
use local_elediaai_chatengine\adapter\ingestionapi_adapter;
use local_elediaai_chatengine\adapter\literag_adapter;
use local_elediaai_chatengine\adapter\simulator_adapter;

/**
 * Decides which backend answers, and is the only place that decides it.
 *
 * There is no setting for this. The destination that course material is
 * written to is configured once, in local_elediaai_sources, and the engine
 * reads it: a question is then always put to the service that holds the
 * answer. A second setting here could disagree with the first, and the way it
 * would disagree is silently — an empty index looks exactly like a question
 * the material does not cover.
 *
 * A backend therefore arrives as a pair carrying one id: a sink over there to
 * write with, an adapter over here to read with. {@see paired_ids()} is what a
 * test asserts against, so that a destination added or renamed in
 * local_elediaai_sources fails loudly here instead of degrading to "no backend
 * available" on a live site.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backend_resolver {
    /** @var string[] Known adapters, in settings order. */
    private const CLASSES = [
        ingestionapi_adapter::class,
        literag_adapter::class,
        simulator_adapter::class,
    ];

    /** @var string The destination resolver in local_elediaai_sources. */
    private const SINK_MANAGER = '\local_elediaai_sources\sink\sink_manager';

    /** @var adapter|null Test-only override. */
    private static ?adapter $override = null;

    /**
     * The id of the destination the site currently writes to.
     *
     * @return string The sink id, or '' when local_elediaai_sources is absent.
     */
    public static function active_id(): string {
        // The simulator is the one backend that does not follow the content
        // destination. It answers without a language model, so it has nothing
        // to do with where material is written to, and tying it to a sink
        // would mean inventing a destination that ingests into nowhere.
        if (simulator_adapter::is_enabled()) {
            return simulator_adapter::id();
        }
        if (!class_exists(self::SINK_MANAGER)) {
            return '';
        }
        return (string) call_user_func([self::SINK_MANAGER, 'active_id']);
    }

    /**
     * The adapter that reads from the active destination.
     *
     * Null rather than an exception: a placement asks this on every page load
     * to decide between a chat box and a configuration notice, and "not
     * configured yet" is an ordinary state of a fresh site, not a fault.
     *
     * @return adapter|null The adapter, or null when none is configured or usable.
     */
    public static function active(): ?adapter {
        if (self::$override !== null) {
            return self::$override;
        }

        $adapter = self::adapter_for(self::active_id());
        if ($adapter === null || !$adapter->is_available()) {
            return null;
        }
        return $adapter;
    }

    /**
     * The active adapter, or a failure that says why there is none.
     *
     * @return adapter The usable adapter.
     * @throws adapter_exception When no backend is configured or usable.
     */
    public static function require_active(): adapter {
        $adapter = self::active();
        if ($adapter === null) {
            throw new adapter_exception('error_no_backend', 'active sink: ' . self::active_id());
        }
        return $adapter;
    }

    /**
     * Whether a usable backend exists right now.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return self::active() !== null;
    }

    /**
     * An adapter by id, whether or not it is the active one.
     *
     * Availability is not checked: a caller that needs to address the backend
     * a site has just switched away from must be able to reach it even while
     * the active one is a different service.
     *
     * @param string $id The backend id.
     * @return adapter|null The instance, or null when no adapter carries that id.
     */
    /**
     * The readable name of a backend id, or the id when it names none.
     *
     * A chat turn and a provenance record both store `$adapter::id()` in a
     * field called „provider" -- there is no AI provider to name, because the
     * chat never calls a model itself. The id is the right thing to store and
     * the wrong thing to show: „ingestionapi" in a column headed „Provider"
     * is what made somebody ask.
     *
     * Lives here rather than in each reader: three surfaces show that field
     * (the request list, the provenance report, the single-record view), and
     * the list of backends is this plugin's to keep. Callers ask softly --
     * this plugin is not a hard dependency of either reader.
     *
     * @param string $id The stored backend id.
     * @return string The adapter's name, or the id unchanged.
     */
    public static function display_name(string $id): string {
        if ($id === '') {
            return $id;
        }

        try {
            $adapter = self::adapter_for($id);
        } catch (\Throwable $e) {
            unset($e);
            return $id;
        }

        return $adapter !== null ? $adapter::name() : $id;
    }

    /**
     * An adapter by id, whether or not it is the active one.
     *
     * Availability is not checked: a caller that needs to address the backend
     * a site has just switched away from must be able to reach it even while
     * the active one is a different service.
     *
     * @param string $id The backend id.
     * @return adapter|null The instance, or null when no adapter carries that id.
     */
    public static function adapter_for(string $id): ?adapter {
        $classname = self::classes_by_id()[$id] ?? null;
        return $classname !== null ? new $classname() : null;
    }

    /**
     * Every backend id, and whether each side of the pair exists.
     *
     * The simulator is left out. The pairing is a canary for a real mistake --
     * a destination that content is written to but no question can be asked
     * of, or the reverse -- and the simulator is neither half of that: it
     * reads from nothing because it invents its answers. Counting it would
     * make the canary cry wolf on every site that has it switched on, and a
     * warning that is always on is one nobody reads.
     *
     * @return array<string, array{sink: bool, adapter: bool}> Keyed by id.
     */
    public static function paired_ids(): array {
        $pairs = [];
        foreach (array_keys(self::classes_by_id()) as $id) {
            if ($id === simulator_adapter::id()) {
                continue;
            }
            $pairs[$id] = ['sink' => false, 'adapter' => true];
        }
        if (class_exists(self::SINK_MANAGER)) {
            foreach (array_keys((array) call_user_func([self::SINK_MANAGER, 'menu'])) as $id) {
                $pairs[$id] = ['sink' => true, 'adapter' => $pairs[$id]['adapter'] ?? false];
            }
        }
        return $pairs;
    }

    /**
     * Known adapters keyed by their id.
     *
     * @return array<string, class-string<adapter>>
     */
    private static function classes_by_id(): array {
        $map = [];
        foreach (self::CLASSES as $classname) {
            $map[$classname::id()] = $classname;
        }
        return $map;
    }

    /**
     * Inject a fake adapter (PHPUnit only).
     *
     * @param adapter|null $adapter The fake, or null to restore normal resolution.
     * @return void
     * @throws \coding_exception Outside PHPUnit.
     */
    public static function override_for_testing(?adapter $adapter): void {
        if (!defined('PHPUNIT_TEST') || !PHPUNIT_TEST) {
            throw new \coding_exception('backend_resolver::override_for_testing() is only available in PHPUnit tests.');
        }
        self::$override = $adapter;
    }
}
