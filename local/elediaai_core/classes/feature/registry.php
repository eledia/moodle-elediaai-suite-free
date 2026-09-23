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
 * AI feature registry.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\feature;

defined('MOODLE_INTERNAL') || die();

use core_component;

/**
 * Collects feature descriptors from every AI plugin that ships a
 * `<frankenstyle>\elediaai_core\feature_provider`.
 */
final class registry {
    /** Provider namespace segment plugins ship under. */
    private const PROVIDER_NAMESPACE = 'elediaai_core';

    /** Config bucket for per-feature admin toggles. */
    private const CONFIG_COMPONENT = 'local_elediaai_core';

    /** Admin/governance-oriented feature audience. */
    public const AUDIENCE_ADMIN = 'admin';

    /** Teacher-facing authoring and grading feature audience. */
    public const AUDIENCE_TEACHER = 'teacher';

    /** Course-design and didactic planning feature audience. */
    public const AUDIENCE_DESIGNER = 'designer';

    /** Learner-facing support feature audience. */
    public const AUDIENCE_STUDENT = 'student';

    /** @var descriptor[]|null Cached descriptors for the current request. */
    private static ?array $descriptors = null;

    /**
     * Static-only.
     */
    private function __construct() {
    }

    /**
     * Return every descriptor announced by AI suite plugins.
     *
     * @return descriptor[] Keyed by descriptor id.
     */
    public static function all(): array {
        if (self::$descriptors !== null) {
            return self::$descriptors;
        }

        $found = [];
        foreach (self::collect() as $descriptor) {
            self::keep_best($found, $descriptor);
        }

        uasort($found, static fn(descriptor $a, descriptor $b): int => strcasecmp($a->name, $b->name));
        self::$descriptors = $found;
        return self::$descriptors;
    }

    /**
     * Gather descriptors announced by suite providers.
     *
     * @return descriptor[]
     */
    private static function collect(): array {
        $providers = core_component::get_component_classes_in_namespace(null, self::PROVIDER_NAMESPACE);

        // Der Namensraum `elediaai_core` traegt inzwischen mehr als eine Sorte
        // Provider -- neben `feature_provider` auch `health_provider`. Ohne
        // diese Zeile meldet die Registry jede fremde Klasse darin als
        // kaputten Feature-Provider: eine Fehlermeldung ueber Code, der
        // voellig in Ordnung ist. Der Wegweiser filtert seit jeher so.
        //
        // Gefiltert wird hier und nicht in `collect_providers()`, weil dort
        // die Tests ihre Attrappen einspeisen -- anonyme Klassen, deren Name
        // auf nichts endet. Die Ausfallsicherheit soll pruefbar bleiben.
        $providers = array_filter(
            $providers,
            static fn(string $classname): bool => str_ends_with($classname, '\\feature_provider'),
            ARRAY_FILTER_USE_KEY
        );

        return self::collect_providers($providers);
    }

    /**
     * Gather descriptors from a discovered provider map.
     *
     * Kept separate from Moodle's component discovery so failure isolation can
     * be tested deterministically.
     *
     * @param array<string, mixed> $providers Provider class names and discovered paths.
     * @return descriptor[]
     */
    private static function collect_providers(array $providers): array {
        $out = [];
        foreach ($providers as $classname => $providerpath) {
            if (!self::provider_file_is_readable($providerpath)) {
                self::report_provider_problem(
                    $classname,
                    'the discovered provider file is not readable: ' . $providerpath
                );
                continue;
            }
            try {
                if (!class_exists($classname)) {
                    self::report_provider_problem($classname, 'the provider class could not be loaded');
                    continue;
                }
                if (!is_a($classname, feature_provider::class, true)) {
                    self::report_provider_problem(
                        $classname,
                        'the class does not implement ' . feature_provider::class
                    );
                    continue;
                }
                $descriptors = $classname::get_descriptors();
            } catch (\Throwable $e) {
                self::report_provider_problem($classname, 'provider loading failed: ' . $e->getMessage());
                continue;
            }
            foreach ($descriptors as $descriptor) {
                if ($descriptor instanceof descriptor) {
                    $out[] = $descriptor;
                    continue;
                }
                self::report_provider_problem(
                    $classname,
                    'get_descriptors() returned an invalid value of type ' . get_debug_type($descriptor)
                );
            }
        }
        return $out;
    }

    /**
     * Report a provider failure without exposing it in the normal UI.
     *
     * @param string $classname Provider class name.
     * @param string $reason Failure reason.
     * @return void
     */
    private static function report_provider_problem(string $classname, string $reason): void {
        debugging('AI feature provider ' . $classname . ': ' . $reason, DEBUG_DEVELOPER);
    }

    /**
     * Resolve duplicate ids in favour of a live tile over a roadmap stub.
     *
     * @param array<string, descriptor> $found
     * @param descriptor $descriptor
     * @return void
     */
    private static function keep_best(array &$found, descriptor $descriptor): void {
        $existing = $found[$descriptor->id] ?? null;
        if ($existing === null || ($existing->comingsoon && !$descriptor->comingsoon)) {
            $found[$descriptor->id] = $descriptor;
        }
    }




    /**
     * Return the primary audience group for a feature.
     *
     * @param string $featureid
     * @return string
     */
    public static function audience(string $featureid): string {
        $declared = self::all()[$featureid]->audience ?? null;
        if ($declared !== null && in_array($declared, self::audiences(), true)) {
            return $declared;
        }

        // Die letzte Ausnahme ist weg: local_elediaai_coursegen nennt seine
        // Zielgruppe jetzt selbst, wie alle anderen auch. Was bleibt, ist der
        // Rueckfall fuer ein Plugin, das noch nichts sagt -- und der meldet
        // sich, statt still eine Zuordnung zu erfinden.
        if (isset(self::all()[$featureid])) {
            debugging(
                "Feature '{$featureid}' does not declare its audience; falling back to teachers. "
                    . 'Add audience: registry::AUDIENCE_* to its descriptor.',
                DEBUG_DEVELOPER
            );
        }

        return self::AUDIENCE_TEACHER;
    }

    /**
     * What sort of thing a feature is: a place you open, or a capability you
     * use inside a course.
     *
     * The dashboard used to decide this by accident. Its card rule read
     * `launchurl ?? feature.php`, so a feature that named a start page was
     * opened and one that did not was explained instead -- not because anyone
     * decided it, but because a field was empty. That made the page look
     * arbitrary to its readers, and it hid real omissions: a plugin that simply
     * forgot its `launchurl` silently became an explained one.
     *
     * The plugin says it now. The derivation below stays only so that an
     * un-migrated provider keeps working, and it reports itself.
     *
     * @param string $featureid
     * @return string One of the `descriptor::KIND_*` constants.
     */
    public static function kind(string $featureid): string {
        $descriptor = self::all()[$featureid] ?? null;
        $declared = $descriptor->kind ?? null;
        if ($declared !== null && in_array($declared, self::kinds(), true)) {
            return $declared;
        }

        if ($descriptor !== null) {
            debugging(
                "Feature '{$featureid}' does not declare its kind; deriving it from launchurl. "
                    . 'Add kind: descriptor::KIND_PAGE or descriptor::KIND_IN_COURSE to its descriptor.',
                DEBUG_DEVELOPER
            );
        }

        return ($descriptor?->launchurl ?? null) !== null
            ? descriptor::KIND_PAGE
            : descriptor::KIND_IN_COURSE;
    }

    /**
     * Every kind a feature may declare.
     *
     * @return string[]
     */
    public static function kinds(): array {
        return [
            descriptor::KIND_PAGE,
            descriptor::KIND_IN_COURSE,
        ];
    }

    /**
     * Every audience key a feature may declare.
     *
     * @return string[]
     */
    public static function audiences(): array {
        return [
            self::AUDIENCE_ADMIN,
            self::AUDIENCE_TEACHER,
            self::AUDIENCE_DESIGNER,
            self::AUDIENCE_STUDENT,
        ];
    }

    /**
     * Return the localised audience label for a feature.
     *
     * @param string $featureid
     * @return string
     */
    public static function audience_label(string $featureid): string {
        return get_string('feature_audience_' . self::audience($featureid), self::CONFIG_COMPONENT);
    }

    /**
     * Return the stable icon colour modifier for a feature audience.
     *
     * @param string $featureid
     * @return string
     */
    public static function icon_modifier(string $featureid): string {
        return 'lh-plugin-card__icon--lhai-' . self::audience($featureid);
    }

    /**
     * Return source/credit rows for a feature detail page.
     *
     * @param string $featureid
     * @return array<int, array<string, string>>
     */
    public static function origins(string $featureid): array {
        // Wessen Arbeit hier weitergefuehrt wird, weiss nur das Plugin selbst.
        // Hier stand eine Tabelle mit zwei Feature-IDs -- die dritte Fassung
        // desselben Fehlers, nach Name, Icon und Zielgruppe. Ein Fork, der
        // seine Herkunft nennen wollte, musste dafuer eine fremde Datei
        // aendern; und ein Plugin, das die Suite nicht kennt, konnte seine
        // Urheber ueberhaupt nicht nennen. Das ist bei Lizenzangaben mehr als
        // eine Unschoenheit.
        $declared = self::all()[$featureid]->origin ?? null;
        if (is_array($declared) && isset($declared['title'], $declared['authors'])) {
            return [$declared + ['url' => '', 'note' => '']];
        }

        return [[
            'title' => get_string('feature_origin_eledia_title', self::CONFIG_COMPONENT),
            'authors' => 'eLeDia GmbH',
            'url' => 'https://github.com/jmoskaliuk/eledia.ai',
            'note' => get_string('feature_origin_eledia_note', self::CONFIG_COMPONENT),
        ]];
    }

    /**
     * Whether Moodle's discovered provider path is safe to autoload.
     *
     * @param mixed $providerpath Path returned by core_component.
     * @return bool
     */
    public static function provider_file_is_readable($providerpath): bool {
        return !is_string($providerpath) || $providerpath === '' || is_readable($providerpath);
    }

    /**
     * Subset of descriptors the current user may actually see.
     *
     * @param \context|null $context Context for the capability check.
     * @return descriptor[] Keyed by descriptor id.
     */
    public static function visible(?\context $context = null): array {
        $context ??= \core\context\system::instance();
        $out = [];
        foreach (self::all() as $descriptor) {
            if (!self::is_enabled($descriptor->id)) {
                continue;
            }
            if (!policy::is_allowed($descriptor)) {
                continue;
            }
            // Das Audit hat eine eigene Zugangsregel, und sie hat das letzte
            // Wort -- in beide Richtungen. Vorher fiel ein "nein" von
            // can_view() in die normale Capability-Pruefung durch, und
            // moodle/ai:viewaiusagereport sagte dann ja: im Modus "nur
            // Administration" sah eine Managerin die Kachel, und audit.php
            // wies sie ab. Auf Moodle 4.5 kam dazu eine Debugging-Meldung je
            // Seitenaufruf, weil es die Capability dort nicht gibt.
            if ($descriptor->id === 'audit') {
                if (\local_elediaai_core\local\audit_config::can_view($context)) {
                    $out[$descriptor->id] = $descriptor;
                }
                continue;
            }
            if (
                $descriptor->capability !== null
                    && !has_capability($descriptor->capability, $context)
            ) {
                continue;
            }
            $out[$descriptor->id] = $descriptor;
        }
        return $out;
    }

    /**
     * Narrow a set of features by a search phrase and an audience.
     *
     * Searched is everything the plugin says about itself and a reader could
     * plausibly type: the name, the one-liner, the longer explanation, the
     * key features and -- for a course capability -- the sentence saying
     * where to find it. Somebody looking for "Aufgabe" should land on the
     * feedback activity even though its name does not contain the word.
     *
     * All words must match, none of them case-sensitively. That is the same
     * rule the guide's handbook search uses, and two different rules on one
     * site would be worse than either.
     *
     * @param descriptor[] $features Features to narrow, keyed by id.
     * @param string $query Search phrase; empty means no narrowing.
     * @param string $audience One of the AUDIENCE_* constants, or '' for all.
     * @return descriptor[] Keyed by id, order preserved.
     */
    public static function narrow(array $features, string $query = '', string $audience = ''): array {
        if ($audience !== '' && in_array($audience, self::audiences(), true)) {
            $features = array_filter(
                $features,
                static fn(descriptor $d): bool => self::audience($d->id) === $audience
            );
        }

        $words = preg_split('/\s+/u', \core_text::strtolower(trim($query)), -1, PREG_SPLIT_NO_EMPTY);
        if (!$words) {
            return $features;
        }

        return array_filter($features, static function (descriptor $d) use ($words): bool {
            $haystack = self::search_haystack($d);
            foreach ($words as $word) {
                if (!str_contains($haystack, $word)) {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * Everything a feature can be found by, lowercased.
     *
     * Its own method because two places need to agree on it: this class, which
     * narrows server-side, and the dashboard's cards, which carry the same text
     * so the browser can narrow again without asking. Were the two lists to
     * drift apart, typing a word would quietly hide a tile that a reload then
     * brought back.
     *
     * @param descriptor $feature The feature.
     * @return string The searchable text.
     */
    public static function search_haystack(descriptor $feature): string {
        return \core_text::strtolower(implode(' ', array_filter([
            $feature->name,
            $feature->description,
            (string) $feature->detaildescription,
            (string) $feature->usagehint,
            implode(' ', array_map('strval', $feature->keyfeatures)),
        ])));
    }

    /**
     * Whether the admin toggle for a feature is on. Defaults to true.
     *
     * @param string $featureid
     * @return bool
     */
    public static function is_enabled(string $featureid): bool {
        $value = get_config(self::CONFIG_COMPONENT, 'feature_' . $featureid . '_enabled');
        return $value === false || (int) $value === 1;
    }

    /**
     * Persist the enabled flag for a feature.
     *
     * @param string $featureid
     * @param bool $enabled
     * @return void
     */
    public static function set_enabled(string $featureid, bool $enabled): void {
        set_config('feature_' . $featureid . '_enabled', $enabled ? 1 : 0, self::CONFIG_COMPONENT);
    }

    /**
     * Clear the in-process caches. For tests after mutating providers.
     *
     * @return void
     */
    public static function reset_cache(): void {
        self::$descriptors = null;
        self::$maturities = [];
        policy::reset_cache();
    }

    /** @var array<string, int|null> Declared maturity per component, once per request. */
    private static array $maturities = [];

    /**
     * The maturity a plugin declares for itself, or null if it declares none.
     *
     * Read out of the plugin's own version.php, because Moodle keeps it
     * nowhere else: `core_plugin_manager` drops the value while loading a
     * plugin from disk, and asking its plugininfo for `maturity` raises
     * „Invalid plugin property accessed". The constant is only used by the
     * update checker, for plugins in the directory -- not for installed ones.
     *
     * Reading the file is what the core does too ({@see \core\plugininfo\base
     * ::load_disk_version()}); the result is kept for the request so a grid of
     * twenty tiles includes twenty files once, not twenty times.
     *
     * @param string $component Frankenstyle component name.
     * @return int|null One of the MATURITY_* constants, or null.
     */
    public static function maturity(string $component): ?int {
        if (array_key_exists($component, self::$maturities)) {
            return self::$maturities[$component];
        }

        self::$maturities[$component] = null;
        $dir = \core_component::get_component_directory($component);
        if ($dir === null || !is_readable($dir . '/version.php')) {
            return null;
        }

        // Dieselben zwei Namen, die auch der Kern beim Einlesen bedient: alte
        // Aktivitaetsmodule schreiben in $module statt in $plugin.
        $plugin = new \stdClass();
        $module = $plugin;
        include($dir . '/version.php');
        $wert = $plugin->maturity ?? ($module->maturity ?? null);
        if (is_int($wert)) {
            self::$maturities[$component] = $wert;
        }

        return self::$maturities[$component];
    }

    /**
     * Whether this component calls itself finished.
     *
     * A plugin that declares nothing counts as released: the suite must not
     * put a caution on somebody else's plugin because it stayed silent.
     *
     * @param string $component Frankenstyle component name.
     * @return bool
     */
    public static function is_released(string $component): bool {
        $maturity = self::maturity($component);

        return $maturity === null || $maturity >= MATURITY_STABLE;
    }
}
