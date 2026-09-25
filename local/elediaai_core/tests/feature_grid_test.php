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
 * Feature grid renderer tests.
 *
 * @package    local_elediaai_core
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

use advanced_testcase;
use local_elediaai_core\feature\descriptor;
use local_elediaai_core\feature\registry;
use local_elediaai_core\output\feature_grid;
use moodle_url;

/**
 * Feature grid renderer tests.
 *
 * @covers \local_elediaai_core\output\feature_grid
 */
final class feature_grid_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        registry::reset_cache();
    }

    /**
     * A minimal descriptor for rendering, independent of any real provider.
     */
    private function make_descriptor(string $id = 'demofeature', ?moodle_url $configurl = null): descriptor {
        return new descriptor(
            id: $id,
            component: 'local_elediaai_core',
            name: 'Demo feature',
            description: 'A demo feature for tests.',
            launchurl: null,
            // Ein Name, den das Sprite wirklich fuehrt. Vorher stand hier
            // 'wand-magic-sparkles', das es nicht gibt -- die Vorlage hat also
            // genau den Fehler getestet, den sie nicht sehen konnte.
            icon: 'magic',
            configurl: $configurl,
        );
    }

    /**
     * The teacher dashboard suppresses the audience badge; the admin
     * dashboard (default options) still shows it.
     */
    public function test_audience_badge_suppressed_when_disabled(): void {
        $feature = $this->make_descriptor();

        $withbadge = feature_grid::render([$feature->id => $feature]);
        $this->assertStringContainsString('lh-ai-suite-card__audience', $withbadge);

        $withoutbadge = feature_grid::render([$feature->id => $feature], ['showaudiencebadge' => false]);
        $this->assertStringNotContainsString('lh-ai-suite-card__audience', $withoutbadge);
    }

    /**
     * The settings gear only appears for users with moodle/site:config, and
     * only when the grid is not told to suppress it.
     */
    public function test_settings_gear_respects_option_and_capability(): void {
        $this->setAdminUser();
        $feature = $this->make_descriptor('demofeature', new moodle_url('/admin/settings.php'));

        $shown = feature_grid::render([$feature->id => $feature]);
        $this->assertStringContainsString('lh-ai-suite-card__settings', $shown);

        $hidden = feature_grid::render([$feature->id => $feature], ['showsettingsgear' => false]);
        $this->assertStringNotContainsString('lh-ai-suite-card__settings', $hidden);

        $this->setUser($this->getDataGenerator()->create_user());
        $withoutcapability = feature_grid::render([$feature->id => $feature]);
        $this->assertStringNotContainsString('lh-ai-suite-card__settings', $withoutcapability);
    }

    /**
     * Rendering an empty feature list is quiet, not an error.
     *
     * It used to emit an empty grid container. Since the grid grew two
     * sections, nothing at all is the honest answer: a heading over no cards
     * would announce a group that is not there. The caller
     * (`index.php`) already puts up its own notice for the empty case.
     */
    public function test_render_empty_list(): void {
        $html = feature_grid::render([]);
        $this->assertSame('', $html);
    }

    /**
     * Dashboard cards use a feature's launch URL as their primary action when
     * a concrete tool entry point exists.
     */
    public function test_render_uses_launch_url_for_primary_action(): void {
        $feature = new descriptor(
            id: 'teacher_tools',
            component: 'local_elediaai_core',
            name: 'Teacher tools',
            description: 'Teacher entry point.',
            launchurl: new moodle_url('/local/elediaai_teachertools/index.php'),
            icon: 'magic',
        );

        $html = feature_grid::render([$feature->id => $feature]);

        $this->assertStringContainsString('/local/elediaai_teachertools/index.php', $html);
        $this->assertStringNotContainsString('/local/elediaai_guide/index.php', $html);
    }

    /**
     * Eine nicht lizenzierte Premium-Kachel ist gesperrt und fuehrt ins Handbuch (#29).
     */
    public function test_an_unlicensed_card_is_locked_and_says_so(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $gesperrt = new descriptor(
            id: 'lockedfortest',
            component: 'local_elediaai_core',
            name: 'Premium thing',
            description: 'Costs extra.',
            launchurl: new moodle_url('/local/elediaai_core/somewhere.php'),
            icon: 'award',
            kind: descriptor::KIND_PAGE,
            tier: \local_elediaai_core\feature\tier::PREMIUM,
        );

        $html = feature_grid::render([$gesperrt->id => $gesperrt]);

        preg_match_all('~<div class="lh-plugin-card__kicker[^"]*">([^<]*)</div>~', $html, $treffer);
        $this->assertSame([get_string('feature_status_locked', 'local_elediaai_core')], $treffer[1]);
        $this->assertStringContainsString('lh-plugin-card--locked', $html);
        $this->assertStringContainsString(s(get_string('feature_locked_hint', 'local_elediaai_core')), $html);
        $this->assertStringNotContainsString('somewhere.php', $html, 'A locked tile must not lead into the feature.');
    }

    /**
     * Jede Kachel sagt, woran man ist.
     *
     * Frueher pruefte das ein Behat-Szenario auf der Erklaerseite. Das ging
     * nur, solange eine bestimmte Funktion in Vorbereitung war -- in der CI
     * war es der Tutor, auf der Entwicklungsinstanz keine. Ein Test, der von
     * den eingehaengten Plugins abhaengt, sagt dort nichts, wo es darauf
     * ankaeme.
     */
    public function test_a_card_says_what_state_its_feature_is_in(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $vorbereitung = new descriptor(
            id: 'tutorpremium',
            component: 'local_elediaai_tutor_premium',
            name: 'AI Tutor Premium',
            description: 'Noch nicht da.',
            launchurl: null,
            icon: 'award',
            kind: descriptor::KIND_PAGE,
            comingsoon: true,
        );
        $fertig = new descriptor(
            id: 'audit',
            component: 'local_elediaai_core',
            name: 'AI Audit',
            description: 'Da.',
            launchurl: new moodle_url('/local/elediaai_core/audit.php'),
            icon: 'clipboard-list',
            kind: descriptor::KIND_PAGE,
        );

        $html = feature_grid::render([$vorbereitung->id => $vorbereitung, $fertig->id => $fertig]);

        // Genau eine Zustandszeile, und sie gehoert der angekuendigten Kachel.
        // Die fertige traegt keine mehr: sie sagte "Verfuegbar", und das stand
        // auf jeder Nachbarkachel auch.
        //
        // Geprueft wird das Element, nicht das Wort. Die erste Fassung suchte
        // nach der Zeichenkette "Available" und fiel in der CI um, weil die
        // Abschnittsueberschrift "Available in your courses" heisst -- lokal
        // blieb sie gruen, weil dort alle Plugins eingehaengt sind und dieser
        // Abschnitt gar nicht entsteht.
        preg_match_all('~<div class="lh-plugin-card__kicker[^"]*">([^<]*)</div>~', $html, $treffer);

        $this->assertSame(
            [get_string('feature_status_coming', 'local_elediaai_core')],
            $treffer[1]
        );
        $this->assertSame(1, substr_count($html, 'lh-plugin-card__kicker'));
        // Was noch nicht da ist, wird auch als gesperrt gezeichnet.
        $this->assertStringContainsString('lh-plugin-card--locked', $html);
    }

    /**
     * Eine Kachel ohne eigene Seite fuehrt ins Handbuch, nicht ins Leere.
     *
     * Die Erklaerseite im Kern ist weg; was ein Werkzeug ist, schreibt das
     * Plugin selbst. Neun der zweiundzwanzig Kacheln haben keine eigene
     * Startseite -- fuer sie ist das Kapitel das Ziel.
     */
    public function test_a_card_without_a_page_of_its_own_leads_to_the_handbook(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $feature = new descriptor(
            id: 'aifeedback',
            component: 'mod_aifeedback',
            name: 'AI Feedback',
            description: 'Rueckmeldung zu Abgaben.',
            launchurl: null,
            icon: 'comments',
            kind: descriptor::KIND_IN_COURSE,
            usagehint: 'Im Kurs eine Aktivitaet anlegen.',
        );

        $html = feature_grid::render([$feature->id => $feature]);

        // Der Wegweiser ist eine weiche Abhaengigkeit, und die CI haengt nur
        // das geprueste Plugin ein. Geprueft wird deshalb der Vertrag, den der
        // Code selbst kennt (`handbook_url()`), in beiden Lagen -- statt einer
        // Zusicherung, die nur auf der Entwicklungsinstanz zutrifft.
        if (\core_component::get_component_directory('local_elediaai_guide') !== null) {
            $this->assertStringContainsString('/local/elediaai_guide/index.php', $html);
            $this->assertStringContainsString('component=mod_aifeedback', $html);
        } else {
            $this->assertStringNotContainsString('/local/elediaai_guide/', $html);
            $this->assertStringContainsString('/local/elediaai_core/index.php', $html);
        }
        $this->assertStringNotContainsString('/local/elediaai_core/feature.php', $html);
    }

    /**
     * A feature id with no explicit audience mapping defaults to "teacher".
     */
    public function test_a_drawing_fills_the_image_area(): void {
        $html = feature_grid::render([
            new descriptor(
                id: 'audit',
                component: 'local_elediaai_core',
                name: 'Demo feature',
                description: 'A demo feature for tests.',
                launchurl: null,
                icon: 'magic',
                imagename: 'feature_audit',
            ),
        ]);

        $this->assertStringContainsString('lh-ai-suite-card__image', $html);
        $this->assertStringContainsString('eai-ill', $html, 'the drawing was not inlined');
        $this->assertStringNotContainsString('lh-ai-suite-card__placeholder', $html);
    }

    /**
     * A feature with no drawing gets the same area, filled with its icon.
     *
     * Not an empty space and not a differently shaped card: one tile with a
     * different silhouette makes the whole grid look unfinished, and the
     * reader blames the feature rather than the missing file.
     */
    public function test_a_feature_without_a_drawing_keeps_the_same_shape(): void {
        $html = feature_grid::render([$this->make_descriptor()]);

        $this->assertStringContainsString('lh-ai-suite-card__image', $html);
        $this->assertStringContainsString('lh-ai-suite-card__placeholder', $html);
    }

    /**
     * A drawing is inlined, never linked -- otherwise it cannot follow the theme.
     *
     * An SVG loaded through an `<img>` tag is an isolated document: it sees
     * neither `currentColor` nor the page's custom properties. Measured, not
     * assumed. The moment somebody "tidies" this back into an `<img>`, the
     * illustrations stop following the palette, and nothing else would say so.
     */
    public function test_a_drawing_is_inlined_rather_than_linked(): void {
        $html = feature_grid::render([
            new descriptor(
                id: 'audit',
                component: 'local_elediaai_core',
                name: 'Demo feature',
                description: 'A demo feature for tests.',
                launchurl: null,
                icon: 'magic',
                imagename: 'feature_audit',
            ),
        ]);

        $this->assertStringContainsString('<svg', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    /**
     * An illustration name that is not a plain file name is refused.
     */
    public function test_an_illustration_name_must_be_a_plain_file_name(): void {
        $html = feature_grid::render([
            new descriptor(
                id: 'audit',
                component: 'local_elediaai_core',
                name: 'Demo feature',
                description: 'A demo feature for tests.',
                launchurl: null,
                icon: 'magic',
                imagename: '../../../config',
            ),
        ]);

        $this->assertStringContainsString('lh-ai-suite-card__placeholder', $html);
        $this->assertDebuggingCalled();
    }

    /**
     * An icon the sprite does not carry used to paint a bare grey circle.
     *
     * It looked deliberate, so nobody reported it -- and two shipped features
     * were in that state. The card now draws a placeholder and tells
     * developers which feature asked for what.
     */
    public function test_an_unknown_icon_falls_back_instead_of_rendering_nothing(): void {
        $html = feature_grid::render([
            new descriptor(
                id: 'demofeature',
                component: 'local_elediaai_core',
                name: 'Demo feature',
                description: 'A demo feature for tests.',
                launchurl: null,
                icon: 'no-such-icon-anywhere',
            ),
        ]);

        $this->assertStringContainsString('lh-plugin-card__icon', $html);
        $this->assertStringContainsString('<svg', $html, 'the icon tile stayed empty');
        $this->assertDebuggingCalled();
    }

    public function test_unmapped_feature_defaults_to_teacher_audience(): void {
        $this->assertSame(registry::AUDIENCE_TEACHER, registry::audience('some_never_before_seen_id'));
    }

    /**
     * Every tile is shipped; the filter only decides which one is hidden.
     *
     * That is what lets the browser widen a filter again without fetching the
     * page: a tile the server excluded is still in the document, only hidden.
     */
    public function test_excluded_tiles_are_rendered_but_hidden(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $features = [
            'keep' => $this->make_descriptor('keep'),
            'drop' => $this->make_descriptor('drop'),
        ];

        $html = feature_grid::render($features, ['matching' => ['keep'], 'groupbykind' => false]);

        $this->assertStringContainsString('data-feature-id="keep"', $html);
        $this->assertStringContainsString('data-feature-id="drop"', $html);
        $this->assertMatchesRegularExpression(
            '~<article[^>]*data-feature-id="drop"[^>]*\bhidden\b~',
            $html,
            'A tile outside the filter must be rendered hidden, not left out.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '~<article[^>]*data-feature-id="keep"[^>]*\bhidden\b~',
            $html
        );
    }

    /**
     * Each tile carries what the browser needs to apply the same rule.
     *
     * Without these two the live filter would have to ask the server what it
     * already knows, which is the round trip this is meant to remove.
     */
    public function test_tiles_carry_the_filter_data(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $feature = $this->make_descriptor('demofeature');
        $html = feature_grid::render(['demofeature' => $feature], ['groupbykind' => false]);

        $this->assertStringContainsString('data-search=', $html);
        $this->assertStringContainsString('data-audience=', $html);
        $this->assertStringContainsString(
            registry::search_haystack($feature),
            html_entity_decode($html, ENT_QUOTES, 'UTF-8'),
            'The tile must carry the same searchable text the server narrows by.'
        );
    }

    /**
     * The searchable text is lowercased and covers every field a search hits.
     */
    public function test_the_haystack_covers_the_searchable_fields(): void {
        $this->resetAfterTest();

        $feature = new descriptor(
            id: 'haystack',
            component: 'local_elediaai_core',
            name: 'Grosser Name',
            description: 'Eine Beschreibung.',
            launchurl: null,
            detaildescription: 'Ein Detail.',
            usagehint: 'Ein Hinweis.',
            keyfeatures: ['Erstes Merkmal'],
            icon: 'magic',
        );

        $haystack = registry::search_haystack($feature);

        $this->assertSame(\core_text::strtolower($haystack), $haystack);
        foreach (['grosser name', 'beschreibung', 'detail', 'hinweis', 'merkmal'] as $word) {
            $this->assertStringContainsString($word, $haystack);
        }
    }
}
