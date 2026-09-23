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
 * Feature registry tests.
 *
 * @package    local_elediaai_core
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

use advanced_testcase;
use local_elediaai_core\feature\descriptor;
use local_elediaai_core\feature\feature_provider;
use local_elediaai_core\feature\policy;
use local_elediaai_core\feature\registry;
use local_elediaai_core\feature\tier;
use local_elediaai_core\local\audit_config;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests the component behavior and contracts.
 *
 * @covers \local_elediaai_core\feature\registry
 * @covers \local_elediaai_core\feature\descriptor
 */
final class registry_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        registry::reset_cache();
    }

    /**
     * Invoke provider collection without depending on Moodle's component cache.
     *
     * @param array<string, mixed> $providers
     * @return descriptor[]
     */
    private function collect_providers(array $providers): array {
        $method = new \ReflectionMethod(registry::class, 'collect_providers');
        return $method->invoke(null, $providers);
    }

    public function test_throwing_provider_is_reported(): void {
        $provider = new class implements feature_provider {
            public static function get_descriptors(): array {
                throw new \RuntimeException('Fixture provider failed');
            }
        };
        $classname = get_class($provider);

        $this->assertSame([], $this->collect_providers([$classname => null]));
        $this->assertDebuggingCalled(
            'AI feature provider ' . $classname . ': provider loading failed: Fixture provider failed',
            DEBUG_DEVELOPER
        );
    }

    public function test_class_with_wrong_interface_is_reported(): void {
        $provider = new class {
        };
        $classname = get_class($provider);

        $this->assertSame([], $this->collect_providers([$classname => null]));
        $this->assertDebuggingCalled(
            'AI feature provider ' . $classname . ': the class does not implement '
                . feature_provider::class,
            DEBUG_DEVELOPER
        );
    }

    public function test_unreadable_provider_file_is_reported(): void {
        $provider = new class implements feature_provider {
            public static function get_descriptors(): array {
                return [];
            }
        };
        $classname = get_class($provider);
        $missingpath = __DIR__ . '/fixtures/missing_feature_provider.php';

        $this->assertSame([], $this->collect_providers([$classname => $missingpath]));
        $this->assertDebuggingCalled(
            'AI feature provider ' . $classname
                . ': the discovered provider file is not readable: ' . $missingpath,
            DEBUG_DEVELOPER
        );
    }

    public function test_mixed_descriptor_values_keep_valid_entries_and_report_invalid_ones(): void {
        $provider = new class implements feature_provider {
            public static function get_descriptors(): array {
                return [
                    new descriptor(
                        id: 'healthy',
                        component: 'local_elediaai_core',
                        name: 'Healthy',
                        description: 'Healthy fixture feature',
                        launchurl: null,
                        icon: 'star',
                    ),
                    'invalid descriptor',
                ];
            }
        };
        $classname = get_class($provider);

        $descriptors = $this->collect_providers([$classname => null]);

        $this->assertCount(1, $descriptors);
        $this->assertSame('healthy', $descriptors[0]->id);
        $this->assertDebuggingCalled(
            'AI feature provider ' . $classname
                . ': get_descriptors() returned an invalid value of type string',
            DEBUG_DEVELOPER
        );
    }

    public function test_faulty_provider_does_not_suppress_healthy_provider(): void {
        $faultyprovider = new class implements feature_provider {
            public static function get_descriptors(): array {
                throw new \RuntimeException('Fixture provider failed');
            }
        };
        $healthyprovider = new class implements feature_provider {
            public static function get_descriptors(): array {
                return [
                    new descriptor(
                        id: 'healthy',
                        component: 'local_elediaai_core',
                        name: 'Healthy',
                        description: 'Healthy fixture feature',
                        launchurl: null,
                        icon: 'star',
                    ),
                ];
            }
        };
        $faultyclassname = get_class($faultyprovider);
        $healthyclassname = get_class($healthyprovider);

        $descriptors = $this->collect_providers([
            $faultyclassname => null,
            $healthyclassname => null,
        ]);

        $this->assertCount(1, $descriptors);
        $this->assertSame('healthy', $descriptors[0]->id);
        $this->assertDebuggingCalled(
            'AI feature provider ' . $faultyclassname
                . ': provider loading failed: Fixture provider failed',
            DEBUG_DEVELOPER
        );
    }

    /**
     * Discovery finds the umbrella plugin's own provider — that gives
     * us a stable set we can pin against (audit + roadmap/live tiles).
     */
    public function test_all_includes_roadmap_descriptors(): void {
        $descriptors = registry::all();
        $ids = array_keys($descriptors);

        $this->assertContains('coursegen', $ids);
        $this->assertContains('translate', $ids);
        $this->assertContains('tutor', $ids);
        // The audit tile only ships where core provides its AI usage register
        // (Moodle 5.0+); on 4.5 it is intentionally hidden.
        if (audit_config::feature_available()) {
            $this->assertContains('audit', $ids);
        }
    }

    /**
     * The umbrella provider ships coursegen / tutor as comingsoon.
     * Translate is replaced by the live filter_eledia_translate provider.
     */
    public function test_roadmap_descriptors_are_comingsoon(): void {
        $descriptors = registry::all();
        // Roadmap stubs stay comingsoon (without a launch url) only until a
        // live plugin provider replaces them - which plugins are present
        // differs per environment, so assert the invariant, not a snapshot.
        foreach (['coursegen', 'tutor'] as $id) {
            $descriptor = $descriptors[$id];
            if ($descriptor->comingsoon) {
                $this->assertNull($descriptor->launchurl, $id . ' stub must not have a launch url');
            } else {
                $this->assertNotSame(
                    'local_elediaai_core',
                    $descriptor->component,
                    $id . ' is live, so a real plugin provider must own it'
                );
            }
        }
    }

    /**
     * A real plugin descriptor with the same id replaces the umbrella
     * roadmap stub so live features do not stay visually "coming soon".
     */
    public function test_live_translate_descriptor_replaces_roadmap_stub(): void {
        if (\core_component::get_component_directory('filter_eledia_translate') === null) {
            $this->markTestSkipped('filter_eledia_translate is fetched separately and not installed here.');
        }
        $descriptors = registry::all();

        $this->assertArrayHasKey('translate', $descriptors);
        $this->assertFalse($descriptors['translate']->comingsoon);
        $this->assertSame('filter_eledia_translate', $descriptors['translate']->component);
        $this->assertNotNull($descriptors['translate']->launchurl);
    }

    /**
     * The audit descriptor is capability-gated. visible() must hide it
     * for users who cannot view the usage report so the launcher does
     * not surface a tile they cannot enter.
     */
    public function test_audit_descriptor_is_hidden_without_capability(): void {
        if (!audit_config::feature_available()) {
            $this->markTestSkipped('Audit report requires the core_ai usage register (Moodle 5.0+).');
        }
        $unprivileged = $this->getDataGenerator()->create_user();
        $this->setUser($unprivileged);

        $visible = registry::visible();

        $this->assertArrayNotHasKey('audit', $visible);
        $this->assertArrayHasKey($this->ein_offenes_feature(), $visible);
    }

    /**
     * Admin sees every descriptor — including the capability-gated
     * audit tile.
     */
    public function test_audit_descriptor_visible_for_admin(): void {
        if (!audit_config::feature_available()) {
            $this->markTestSkipped('Audit report requires the core_ai usage register (Moodle 5.0+).');
        }
        $this->setAdminUser();

        $visible = registry::visible();

        $this->assertArrayHasKey('audit', $visible);
    }

    /**
     * `feature_<id>_enabled = 0` hides the tile from `visible()`.
     */
    public function test_per_feature_toggle_hides_descriptor(): void {
        $this->setAdminUser();
        $id = $this->ein_offenes_feature();
        $this->assertArrayHasKey($id, registry::visible());

        registry::set_enabled($id, false);
        registry::reset_cache();

        $this->assertArrayNotHasKey($id, registry::visible());
    }

    /**
     * The id of some feature every visitor may see.
     *
     * These two tests are about visibility rules, not about a particular
     * plugin. Naming one made them break the day `coursegen` became a
     * premium feature -- a change in a sibling plugin turning a rule test
     * red says nothing about the rule. So the test asks the registry for a
     * descriptor that is free and demands no capability, and fails with a
     * clear sentence if the suite has none left.
     *
     * @return string Descriptor id.
     */
    private function ein_offenes_feature(): string {
        foreach (registry::all() as $id => $descriptor) {
            if (!$descriptor->is_premium() && $descriptor->capability === null && !$descriptor->comingsoon) {
                return $id;
            }
        }
        $this->fail('Kein freies, capability-loses Feature im Katalog — der Test braucht eines.');
    }

    /**
     * Default state of `is_enabled()` is true so a fresh install ships
     * every feature on.
     */
    public function test_is_enabled_defaults_true(): void {
        $this->assertTrue(registry::is_enabled('coursegen'));
        $this->assertTrue(registry::is_enabled('nonexistent_feature_id'));
    }

    public function test_set_enabled_persists(): void {
        registry::set_enabled('translate', false);
        $this->assertFalse(registry::is_enabled('translate'));

        registry::set_enabled('translate', true);
        $this->assertTrue(registry::is_enabled('translate'));
    }

    /**
     * Descriptors come out alphabetically sorted by display name so
     * the launcher render is deterministic.
     */
    /**
     * A plugin that names its own audience is not overruled by the table.
     */
    public function test_a_declared_audience_wins_over_the_central_table(): void {
        $descriptor = new descriptor(
            id: 'audit',
            component: 'local_elediaai_core',
            name: 'AI Audit',
            description: 'Audit',
            launchurl: null,
            icon: 'clipboard-list',
            audience: registry::AUDIENCE_STUDENT,
        );

        $this->assertSame(registry::AUDIENCE_STUDENT, $this->audience_of($descriptor));
    }

    /**
     * An audience the registry does not know is ignored, not shown.
     *
     * A typo must not invent a fifth group that has no legend entry and no
     * colour; falling back to the table is the safe answer.
     */
    public function test_an_unknown_audience_falls_back(): void {
        $descriptor = new descriptor(
            id: 'audit',
            component: 'local_elediaai_core',
            name: 'AI Audit',
            description: 'Audit',
            launchurl: null,
            icon: 'clipboard-list',
            audience: 'governors',
        );

        // Der Rueckfall ist seit dem Wegfall der Tabellen schlicht "teacher".
        // Wichtig bleibt, was der Test eigentlich prueft: ein Wert, den die
        // Registry nicht kennt, wird ignoriert statt als fuenfte Gruppe ohne
        // Legende und ohne Farbe durchgereicht -- und das Plugin erfaehrt es,
        // statt dass sein Tippfehler still in der falschen Gruppe landet.
        $this->assertSame(registry::AUDIENCE_TEACHER, $this->audience_of($descriptor));
        $this->assertDebuggingCalled();
    }

    /**
     * Wer schweigt, wird nicht mehr geraten -- er wird gemeldet.
     *
     * Frueher stand hier eine Tabelle, die fuer `coursegen` „Kursdesign"
     * erfand. Das war einmal richtig und haette jederzeit falsch werden
     * koennen, ohne dass es jemandem aufgefallen waere: beide Antworten sehen
     * plausibel aus. Jetzt gibt es eine ruhige Vorgabe und einen Hinweis an
     * die Entwicklung.
     */
    public function test_a_silent_plugin_is_reported_rather_than_guessed(): void {
        $descriptor = new descriptor(
            id: 'coursegen',
            component: 'local_elediaai_core',
            name: 'AI Course Author',
            description: 'Coursegen',
            launchurl: null,
            icon: 'book-plus',
        );

        $this->assertSame(registry::AUDIENCE_TEACHER, $this->audience_of($descriptor));
        $this->assertDebuggingCalled();
    }

    /**
     * A descriptor reaches the dashboard exactly as its plugin built it.
     *
     * The registry used to rebuild every descriptor to force a curated name
     * and icon onto it. That rebuild silently dropped any field it forgot to
     * copy -- invisible until a tile rendered without its picture. It is gone;
     * this test holds the door shut.
     */
    public function test_a_descriptor_is_passed_through_untouched(): void {
        $descriptor = new descriptor(
            id: 'translate',
            component: 'filter_eledia_translate',
            name: 'A name the registry used to override',
            description: 'Translate',
            launchurl: null,
            icon: 'languages',
            audience: registry::AUDIENCE_ADMIN,
            imagename: 'feature',
        );

        $collected = $this->collect_providers([]);
        unset($collected);

        $property = new \ReflectionProperty(registry::class, 'descriptors');
        $property->setAccessible(true);
        $property->setValue(null, [$descriptor->id => $descriptor]);

        $fromregistry = registry::all()['translate'];
        $this->assertSame('A name the registry used to override', $fromregistry->name);
        $this->assertSame('languages', $fromregistry->icon);
        $this->assertSame(registry::AUDIENCE_ADMIN, $fromregistry->audience);
        $this->assertSame('feature', $fromregistry->imagename);
    }

    /**
     * The audience the registry reports for one descriptor in isolation.
     *
     * @param descriptor $descriptor The descriptor to ask about.
     * @return string
     */
    private function audience_of(descriptor $descriptor): string {
        $property = new \ReflectionProperty(registry::class, 'descriptors');
        $property->setAccessible(true);
        $property->setValue(null, [$descriptor->id => $descriptor]);

        return registry::audience($descriptor->id);
    }

    /**
     * Every installed feature names an icon the shared sprite actually has.
     *
     * `lucide_icon::render()` answers an unknown name with the empty string,
     * so a typo used to paint a bare grey circle that looked deliberate. Two
     * shipped features were in that state and nobody had reported either. The
     * card now falls back visibly, but a wrong name is still a wrong name, and
     * this is the check that says so before a release does.
     */
    public function test_every_feature_icon_exists_in_the_sprite(): void {
        $sprite = file_get_contents(__DIR__ . '/../pix/lucide.svg');
        $this->assertNotFalse($sprite, 'the shared icon sprite is missing');
        preg_match_all('/id="lucide-([a-z0-9-]+)"/', $sprite, $matches);
        $available = $matches[1];

        $missing = [];
        foreach (registry::all() as $descriptor) {
            if (!in_array($descriptor->icon, $available, true)) {
                $missing[] = $descriptor->id . ' (' . $descriptor->component . ') wants "' . $descriptor->icon . '"';
            }
        }

        $this->assertSame([], $missing, "Features name icons the sprite does not carry:\n" . implode("\n", $missing));
    }

    /**
     * Every installed feature says which audience it is for.
     *
     * The registry used to decide this centrally, which meant a new plugin was
     * silently filed under "teachers" until somebody edited a table in another
     * component. The tables are gone; this test is what stops them from
     * growing back one forgotten declaration at a time.
     *
     * The course author is the one documented exception -- it is out of the
     * deliveries with defects of its own. When it returns, it declares its
     * audience and this list goes empty.
     */
    public function test_every_feature_declares_its_audience(): void {
        // Die Ausnahmeliste ist leer geworden: coursegen war die letzte, und es
        // nennt seine Zielgruppe jetzt selbst. Geprueft wird weiter gegen alle
        // installierten Features, nicht gegen eine feste Liste -- welche
        // Plugins da sind, entscheidet die Umgebung.
        $unerwartet = [];
        foreach (registry::all() as $descriptor) {
            if ($descriptor->audience === null) {
                $unerwartet[] = $descriptor->id . ' (' . $descriptor->component . ')';
            }
        }

        $this->assertSame(
            [],
            $unerwartet,
            "Diese Features nennen ihre Zielgruppe nicht und landen im Rueckfall:\n"
                . implode("\n", $unerwartet)
        );
    }

    /**
     * Jedes Feature nennt seine Sorte selbst.
     *
     * Vorher entschied das ein leeres Feld: `launchurl ?? feature.php`. Wer
     * seine Startseite vergass, wurde stillschweigend zur Kurs-Faehigkeit
     * erklaert und stand im falschen Abschnitt -- ohne dass jemand es merkte,
     * weil beides plausibel aussieht.
     */
    public function test_every_feature_declares_its_kind(): void {
        $unerwartet = [];
        foreach (registry::all() as $descriptor) {
            if ($descriptor->kind === null) {
                $unerwartet[] = $descriptor->id . ' (' . $descriptor->component . ')';
            }
        }

        $this->assertSame(
            [],
            $unerwartet,
            "Diese Features nennen ihre Sorte nicht und landen im Rueckfall:\n"
                . implode("\n", $unerwartet)
        );
    }

    /**
     * Und wer keine Tuer hat, sagt, wo man ihn stattdessen findet.
     *
     * Eine Kurs-Faehigkeit ohne diesen Satz ist auf dem Dashboard eine Kachel,
     * die eine Frage aufwirft und sie nicht beantwortet.
     */
    public function test_every_course_capability_says_where_it_is_found(): void {
        $ohne = [];
        foreach (registry::all() as $descriptor) {
            if (registry::kind($descriptor->id) !== descriptor::KIND_IN_COURSE) {
                continue;
            }
            if ($descriptor->usagehint === null || trim($descriptor->usagehint) === '') {
                $ohne[] = $descriptor->id . ' (' . $descriptor->component . ')';
            }
        }

        $this->assertSame(
            [],
            $ohne,
            "Diese Kurs-Faehigkeiten sagen nicht, wo man sie findet:\n" . implode("\n", $ohne)
        );
    }

    /**
     * Die Sorte, die ein Plugin nennt, ist eine der bekannten.
     */
    public function test_a_declared_kind_is_one_of_the_known_ones(): void {
        foreach (registry::all() as $descriptor) {
            if ($descriptor->kind === null) {
                continue;
            }
            $this->assertContains(
                $descriptor->kind,
                registry::kinds(),
                $descriptor->id . ' nennt eine unbekannte Sorte: ' . $descriptor->kind
            );
        }
    }

    /**
     * Kein Plugin holt seinen eigenen Text aus dem Kern.
     *
     * Das war lange andersherum: neun Plugins lasen Name, Beschreibung und
     * Kernfunktionen aus `local_elediaai_core`, und dreiundachtzig
     * Sprachstrings fremder Plugins lagen dort. Wer sein Plugin umbenennen
     * wollte, musste eine Datei aendern, die seinem Team nicht gehoert -- und
     * drei Plugins hatten deshalb zwei Namen, einen eigenen und den, den der
     * Kern zeigte.
     *
     * Die Ausnahme ist der Kern selbst: seine eigene Kachel (Audit) und die
     * Vorschau-Kacheln fuer noch nicht installierte Plugins gehoeren ihm.
     */
    public function test_no_plugin_reads_its_tile_text_out_of_core(): void {
        $providers = \core_component::get_component_classes_in_namespace(null, 'elediaai_core');
        $verstoesse = [];

        foreach (array_keys($providers) as $classname) {
            if (!is_a($classname, \local_elediaai_core\feature\feature_provider::class, true)) {
                continue;
            }
            $datei = (new \ReflectionClass($classname))->getFileName();
            if ($datei === false || str_contains($datei, '/local/elediaai_core/')) {
                // Der Kern darf seine eigenen Texte lesen.
                continue;
            }
            $quelltext = (string) file_get_contents($datei);
            $muster = "/get_string\\(\\s*'([a-z_0-9]+)',\\s*'local_elediaai_core'\\s*\\)/";
            $gefunden = preg_match_all($muster, $quelltext, $treffer);
            if ($gefunden) {
                $verstoesse[] = $classname . ': ' . implode(', ', $treffer[1]);
            }
        }

        $this->assertSame(
            [],
            $verstoesse,
            "Diese Plugins holen ihren Kacheltext aus dem Kern:\n" . implode("\n", $verstoesse)
        );
    }

    /**
     * Jedes installierte Suite-Plugin stellt sich im Index vor.
     *
     * Zwoelf Plugins der Suite hatten lange keine Kachel -- nicht aus einer
     * Entscheidung heraus, sondern weil niemand nachgezaehlt hat. Ein Plugin
     * ohne Provider ist auf dem Dashboard unsichtbar, und das faellt nie
     * jemandem auf: es fehlt ja nur etwas.
     *
     * Geprueft wird gegen die tatsaechlich installierten Komponenten, nicht
     * gegen eine feste Liste -- welche davon eine Umgebung einhaengt,
     * unterscheidet sich zwischen CI und Entwicklungsinstanz. Die Ausnahmen
     * sind benannt und begruendet.
     */
    public function test_every_installed_suite_plugin_is_in_the_index(): void {
        // Kein Werkzeug, sondern Unterbau: das Theme zeichnet die Suite, die
        // Chat-Engine traegt sie, aitransparency protokolliert im
        // Hintergrund, aiprovider_eledia ist die Anbindung an den Anbieter,
        // und die drei Zweitoberflaechen (Block Tactics, qbank Questiongen,
        // die Tiny-Schaltflaeche der Uebersetzung) gehoeren zu Kacheln, die
        // es schon gibt. Keines davon hat eine eigene Seite -- geprueft, nicht
        // vermutet.
        $ohnekachel = [
            'theme_elediaai',
            'local_elediaai_chatengine',
            'local_aitransparency',
            'block_elediaai_tactics',
            'qbank_elediaai_questiongen',
            'local_elediaai_core_premium',
            'mod_aichat',
            'aiprovider_eledia',
            'tiny_eledia_translate',
        ];

        $angemeldet = [];
        foreach (registry::all() as $descriptor) {
            $angemeldet[$descriptor->component] = true;
        }

        $fehlend = [];
        foreach (\core_component::get_plugin_types() as $type => $pfad) {
            foreach (\core_component::get_plugin_list($type) as $name => $plugindir) {
                $component = $type . '_' . $name;
                if (!str_contains($component, 'eledia') && !str_contains($component, 'elli')) {
                    continue;
                }
                if (isset($angemeldet[$component]) || in_array($component, $ohnekachel, true)) {
                    continue;
                }
                $fehlend[] = $component;
            }
        }

        $this->assertSame(
            [],
            $fehlend,
            "Diese Suite-Plugins stellen sich dem Dashboard nicht vor:\n" . implode("\n", $fehlend)
        );
    }

    /**
     * Die Suche findet ein Feature auch ueber das, was es kann.
     *
     * Wer „Aufgabe" tippt, sucht die Rueckmeldung zu Abgaben -- und die heisst
     * „AI Feedback". Eine Suche nur ueber den Namen haette sie nicht gefunden.
     * Deshalb liest sie alles, was das Plugin ueber sich sagt.
     */
    public function test_search_reads_more_than_the_name(): void {
        $features = [
            'a' => new descriptor(
                id: 'a',
                component: 'local_a',
                name: 'AI Feedback',
                description: 'Rueckmeldung zu Abgaben.',
                launchurl: null,
                icon: 'comments',
                kind: descriptor::KIND_IN_COURSE,
                usagehint: 'Im Kurs eine Aufgabe anlegen.',
            ),
            'b' => new descriptor(
                id: 'b',
                component: 'local_b',
                name: 'AI Translation',
                description: 'Inhalte uebersetzen.',
                launchurl: null,
                icon: 'languages',
                kind: descriptor::KIND_PAGE,
            ),
        ];

        $this->assertSame(['a'], array_keys(registry::narrow($features, 'Aufgabe')));
        $this->assertSame(['b'], array_keys(registry::narrow($features, 'uebersetzen')));
        // Gross- und Kleinschreibung darf nicht entscheiden.
        $this->assertSame(['a'], array_keys(registry::narrow($features, 'AUFGABE')));
        // Alle Woerter muessen treffen, nicht irgendeines.
        $this->assertSame([], array_keys(registry::narrow($features, 'Aufgabe uebersetzen')));
        // Ohne Suchbegriff bleibt alles stehen.
        $this->assertCount(2, registry::narrow($features, ''));
    }

    /**
     * Der Zielgruppenfilter nimmt die Angabe des Plugins, nicht eine Vermutung.
     */
    public function test_the_audience_filter_uses_what_the_plugin_declared(): void {
        $features = [];
        foreach (registry::all() as $id => $descriptor) {
            $features[$id] = $descriptor;
        }
        if (empty($features)) {
            $this->markTestSkipped('Keine Features eingehaengt.');
        }

        foreach (registry::audiences() as $audience) {
            foreach (registry::narrow($features, '', $audience) as $id => $descriptor) {
                $this->assertSame(
                    $audience,
                    registry::audience($id),
                    $id . ' ist im Filter ' . $audience . ' gelandet, gehoert aber woandershin'
                );
            }
        }

        // Ein unbekannter Wert filtert nicht, statt alles wegzuwerfen: eine
        // manipulierte Adresse soll eine volle Seite ergeben, keine leere.
        $this->assertCount(count($features), registry::narrow($features, '', 'governors'));
    }

    /**
     * Was eine eigene Seite hat, verlinkt auch seine Einstellungen.
     *
     * Das Zahnrad auf der Kachel ist fuer die Administration oft der einzige
     * Weg, den sie kennt -- die Alternative ist, den Abschnitt in Moodles
     * Einstellungsbaum zu suchen. Fehlt es, ist die Kachel eine Sackgasse.
     *
     * Zwei Gruppen sind ausgenommen: Kurs-Faehigkeiten, weil sie im Kurs
     * eingerichtet werden und `mod_aifeedback` gar keine `settings.php` hat --
     * und angekuendigte Features, weil ein noch nicht installiertes Plugin
     * keine Einstellungsseite haben kann.
     */
    public function test_every_place_you_open_links_its_settings(): void {
        $ohne = [];
        foreach (registry::all() as $id => $descriptor) {
            if (registry::kind($id) !== descriptor::KIND_PAGE) {
                continue;
            }
            // Was noch nicht da ist, hat nichts einzustellen. Cores
            // Platzhalter fuer noch nicht installierte Plugins sind genau das:
            // in der CI, wo Tutor und Kursautor nicht eingehaengt sind,
            // gewinnen sie -- und ein Zahnrad wuerde dort auf eine
            // Einstellungsseite zeigen, die es nicht gibt.
            if ($descriptor->comingsoon || $descriptor->installrequired) {
                continue;
            }
            if ($descriptor->configurl === null) {
                $ohne[] = $id . ' (' . $descriptor->component . ')';
            }
        }

        $this->assertSame(
            [],
            $ohne,
            "Diese Werkzeuge haben eine eigene Seite, aber keinen Weg zu ihren Einstellungen:\n"
                . implode("\n", $ohne)
        );
    }

    /**
     * Ein Provider, der wirft, kostet sich selbst -- nicht die Seite.
     *
     * Gerade das Plugin, dessen Backend brennt, ist das, dessen Bericht die
     * anderen nicht mitreissen darf. Sein Scheitern wird selbst zu einer
     * Meldung, denn „dieses Plugin konnte nicht sagen, wie es ihm geht" ist
     * etwas, das ein Administrator lesen will.
     */
    public function test_a_failing_health_provider_does_not_take_the_page_down(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Der echte Lauf gegen die installierten Provider muss durchgehen.
        $checks = \local_elediaai_core\health\registry::all();
        foreach ($checks as $check) {
            $this->assertContains(
                $check->status,
                \local_elediaai_core\health\check::statuses(),
                $check->component . ':' . $check->id . ' meldet einen unbekannten Status'
            );
            $this->assertNotSame('', trim($check->label), $check->component . ' meldet ohne Bezeichnung');
        }

        // Und die Sortierung ist stabil, damit die Seite nicht bei jedem
        // Aufruf anders aussieht.
        $keys = array_map(
            static fn(\local_elediaai_core\health\check $c): string => $c->component . ':' . $c->id,
            $checks
        );
        $sortiert = $keys;
        sort($sortiert);
        $this->assertSame($sortiert, $keys);
    }

    /**
     * Nur Fehler und Warnungen rufen nach jemandem.
     *
     * Abgeschaltet ist eine Entscheidung, nicht eingerichtet eine Aufgabe --
     * beides gehoert nicht in dieselbe Zeile wie „kaputt". Wer diese
     * Unterscheidung aufgibt, bekommt eine Seite, die immer rot ist und die
     * deshalb niemand mehr liest.
     */
    public function test_only_faults_ask_for_attention(): void {
        $this->resetAfterTest();

        $laut = ['error', 'warning'];
        foreach (\local_elediaai_core\health\check::statuses() as $status) {
            $check = new \local_elediaai_core\health\check(
                id: 'x',
                component: 'local_test',
                label: 'X',
                status: $status,
            );
            $this->assertSame(
                in_array($status, $laut, true),
                $check->needs_attention(),
                $status . ' wird falsch eingeordnet'
            );
        }
    }

    /**
     * Kein Suite-Plugin ruft Moodles KI-Manager an der Quota vorbei auf.
     *
     * Vier taten es: Aktivitaetensuche, Lernpfad, Kursautor und Selbstlernen.
     * Der Aufruf funktioniert dabei tadellos -- die Antwort kommt ja --, er
     * zaehlt nur gegen kein Guthaben und steht in keinem Suite-Audit. Genau
     * deshalb faellt so etwas nicht auf, sondern muss geprueft werden.
     *
     * Gelesen wird der Quelltext, nicht das Verhalten: ein Aufruf, den kein
     * Test ausloest, waere sonst unsichtbar.
     */
    public function test_no_plugin_calls_the_ai_manager_past_the_quota(): void {
        $verstoesse = [];

        foreach (\core_component::get_plugin_types() as $typ => $pfad) {
            foreach (\core_component::get_plugin_list($typ) as $name => $verzeichnis) {
                $component = $typ . '_' . $name;
                if (!str_contains($component, 'eledia') && $component !== 'mod_elli') {
                    continue;
                }
                foreach (self::php_dateien($verzeichnis) as $datei) {
                    // Der Kern haelt den Einstieg selbst; Tests duerfen ihn umgehen.
                    if (str_contains($datei, '/tests/') || str_contains($datei, 'elediaai_core/classes/')) {
                        continue;
                    }
                    $quelltext = (string) file_get_contents($datei);
                    if (!str_contains($quelltext, 'process_action(')) {
                        continue;
                    }
                    preg_match_all('/([\w\\\\:>$()-]{0,40})process_action\(/', $quelltext, $treffer);
                    foreach ($treffer[1] as $davor) {
                        if (str_contains($davor, 'quota_aware_ai_manager')) {
                            continue;
                        }
                        if (str_contains($davor, '->') || str_contains($davor, '::')) {
                            $verstoesse[] = $component . ': ' . basename($datei);
                            break 2;
                        }
                    }
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($verstoesse)),
            "Diese Plugins rufen den KI-Manager direkt auf, an Quota und Audit vorbei:\n"
                . implode("\n", array_unique($verstoesse))
        );
    }

    /**
     * Die Zugangsregel des Audits hat das letzte Wort, auch wenn sie nein sagt.
     *
     * Vorher fiel ein "nein" von audit_config::can_view() in die normale
     * Capability-Pruefung durch. Im Modus "nur Administration" sah damit jede
     * Person mit moodle/ai:viewaiusagereport die Kachel -- und bekam auf
     * audit.php eine required_capability_exception. Eine Kachel, die zu einer
     * Fehlerseite fuehrt, ist schlimmer als keine.
     */
    public function test_the_audit_tile_follows_its_own_access_rule(): void {
        global $DB;

        $this->resetAfterTest();

        if (!audit_config::feature_available()) {
            $this->markTestSkipped('Das Audit braucht Moodles KI-Nutzungsregister (Moodle 5.0+).');
        }

        $context = \core\context\system::instance();
        $manager = $this->getDataGenerator()->create_user();
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($roleid, $manager->id, $context->id);
        $this->setUser($manager);

        // Die Capability allein genuegt: die Kachel ist da.
        set_config('audit_access', audit_config::ACCESS_CORE_CAPABILITY, 'local_elediaai_core');
        $this->assertTrue(has_capability('moodle/ai:viewaiusagereport', $context));
        $this->assertArrayHasKey('audit', registry::visible($context));

        // "Nur Administration": dieselbe Person, dieselbe Capability, keine
        // Kachel -- weil audit.php sie ebenfalls abweisen wuerde.
        set_config('audit_access', audit_config::ACCESS_ADMINS_ONLY, 'local_elediaai_core');
        $this->assertFalse(audit_config::can_view($context));
        $this->assertArrayNotHasKey('audit', registry::visible($context));
    }

    /**
     * Every PHP file below a directory.
     *
     * @param string $verzeichnis
     * @return string[]
     */
    private static function php_dateien(string $verzeichnis): array {
        if (!is_dir($verzeichnis)) {
            return [];
        }
        $out = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($verzeichnis, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $datei) {
            if (str_ends_with($datei->getPathname(), '.php')) {
                $out[] = $datei->getPathname();
            }
        }
        return $out;
    }

    public function test_descriptors_ordered_alphabetically_by_name(): void {
        $names = array_map(static fn(descriptor $d): string => $d->name, array_values(registry::all()));
        $sorted = $names;
        usort($sorted, 'strcasecmp');
        $this->assertSame($sorted, $names);
    }

    public function test_descriptor_defaults_to_free_tier(): void {
        $descriptor = new descriptor(
            id: 'example',
            component: 'local_elediaai_core',
            name: 'Example',
            description: 'Example feature',
            launchurl: null,
            icon: 'star',
        );

        $this->assertSame(tier::FREE, $descriptor->tier);
        $this->assertFalse($descriptor->is_premium());
        $this->assertFalse($descriptor->installrequired);
        $this->assertNull($descriptor->installurl);
        $this->assertTrue(policy::is_allowed($descriptor));
    }

    public function test_installable_descriptor_can_be_modelled(): void {
        $installurl = new \moodle_url('/admin/tool/installaddon/index.php');
        $descriptor = new descriptor(
            id: 'translate',
            component: 'local_elediaai_core',
            name: 'Translate',
            description: 'Install Translate',
            launchurl: null,
            icon: 'languages',
            capability: 'moodle/site:config',
            comingsoon: true,
            installrequired: true,
            installurl: $installurl,
        );

        $this->assertTrue($descriptor->installrequired);
        $this->assertSame($installurl, $descriptor->installurl);
        $this->assertTrue($descriptor->comingsoon);
    }
}
