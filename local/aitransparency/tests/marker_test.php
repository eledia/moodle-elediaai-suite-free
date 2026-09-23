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
 * Kennzeichnung und Aufloesung.
 *
 * @package    local_aitransparency
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aitransparency;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

/**
 * Prueft Marker, Hinweis und die Lesewege des Nachweises.
 *
 * @covers \local_aitransparency\marker
 * @covers \local_aitransparency\provenance
 */
final class marker_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Die Markierung traegt das IPTC-Vokabular und die aufloesbare Kennung.
     *
     * Beides gehoert zusammen: ohne Kennung ist die Markierung eine Behauptung,
     * ohne Vokabular kann sie keine Maschine erkennen.
     */
    public function test_the_marking_carries_the_vocabulary_and_the_record(): void {
        $html = marker::wrap_text('<p>Antwort</p>', '9f2c4e18-5a7b-4c3d-9e16-8b0d2a4f7c53');

        $this->assertStringContainsString('data-ai-generated="true"', $html);
        $this->assertStringContainsString(
            'data-ai-digital-source-type="' . marker::SOURCE_TYPE . '"',
            $html
        );
        $this->assertStringContainsString('data-ai-record="9f2c4e18-5a7b-4c3d-9e16-8b0d2a4f7c53"', $html);
        $this->assertStringContainsString('<p>Antwort</p>', $html);
    }

    /**
     * Ohne Nachweis gibt es keine Markierung.
     *
     * Eine Markierung, die ins Leere zeigt, ist schlimmer als keine -- sie sieht
     * wie ein Beleg aus.
     */
    public function test_without_a_record_there_is_no_marking(): void {
        $this->assertSame('<p>Antwort</p>', marker::wrap_text('<p>Antwort</p>', ''));
        $this->assertSame('<p>Antwort</p>', marker::wrap_text('<p>Antwort</p>', '   '));
    }

    /**
     * Der sichtbare Hinweis sagt es in Worten und verlinkt die Pruefung.
     */
    public function test_the_visible_notice_says_it_and_links_the_check(): void {
        $html = marker::notice('9f2c4e18-5a7b-4c3d-9e16-8b0d2a4f7c53', 'literag');

        $this->assertStringContainsString(
            get_string('notice_with_provider', 'local_aitransparency', 'literag'),
            $html
        );
        $this->assertStringContainsString('/local/aitransparency/verify.php', $html);
        $this->assertStringContainsString('role="note"', $html);
    }

    /**
     * Ohne Kennung nennt der Hinweis keine Pruefung, die es nicht gibt.
     */
    public function test_the_notice_without_a_record_promises_no_check(): void {
        $html = marker::notice(null);

        $this->assertStringContainsString(get_string('notice', 'local_aitransparency'), $html);
        $this->assertStringNotContainsString('verify.php', $html);
    }

    /**
     * Der Name einer Komponente wird beim Plugin erfragt, nicht hier gehalten.
     *
     * Und eine Komponente, deren Plugin es nicht gibt, behaelt ihren Namen: ein
     * Nachweis hoert nicht auf zu existieren, weil das Plugin entfernt wurde.
     */
    public function test_a_component_is_named_by_its_own_plugin(): void {
        $this->assertSame(
            get_string('pluginname', 'local_aitransparency'),
            marker::component_label('local_aitransparency')
        );
        $this->assertSame('local_gibtesnicht', marker::component_label('local_gibtesnicht'));
        $this->assertSame(
            get_string('verify_component_unknown', 'local_aitransparency'),
            marker::component_label('')
        );
    }

    /**
     * Ein aufgezeichneter Nachweis laesst sich ueber seine Kennung wiederfinden.
     *
     * Das war bis hierher unmoeglich: das Plugin hatte vier Schreiber und
     * keinen einzigen Leser.
     */
    public function test_a_record_can_be_found_again(): void {
        $user = $this->getDataGenerator()->create_user();
        $uuid = provenance::record(new record_request(
            component: 'block_elediaai_tutor',
            actionname: 'generate_text',
            provider: 'literag',
            model: '',
            userid: (int) $user->id,
            contextid: (int) \core\context\system::instance()->id,
            assettype: 'text',
            content: 'Die mittlere quadratische Abweichung.',
        ));

        $found = provenance::get($uuid);

        $this->assertNotNull($found);
        $this->assertSame('block_elediaai_tutor', $found->component);
        $this->assertSame('literag', $found->provider);
        $this->assertSame('pending', $found->markstate);
        $this->assertSame(64, strlen($found->contenthash));
    }

    /**
     * Der Bericht zaehlt, was noch nicht gekennzeichnet ist.
     *
     * Das ist die Zahl, um die es geht: ein Nachweis in "pending" heisst, eine
     * Ausgabe ging ohne ihre Kennzeichnung hinaus.
     */
    public function test_the_report_counts_what_is_not_marked_yet(): void {
        $user = $this->getDataGenerator()->create_user();
        $contextid = (int) \core\context\system::instance()->id;

        $first = provenance::record(new record_request(
            component: 'block_elediaai_tutor',
            actionname: 'generate_text',
            provider: 'literag',
            model: '',
            userid: (int) $user->id,
            contextid: $contextid,
            assettype: 'text',
            content: 'eins',
        ));
        provenance::record(new record_request(
            component: 'block_elediaai_tutor',
            actionname: 'generate_text',
            provider: 'literag',
            model: '',
            userid: (int) $user->id,
            contextid: $contextid,
            assettype: 'text',
            content: 'zwei',
        ));

        $this->assertSame(2, provenance::count_records());
        $this->assertSame(2, provenance::count_unmarked());

        provenance::set_mark_state($first, 'marked');

        $this->assertSame(2, provenance::count_records());
        $this->assertSame(1, provenance::count_unmarked());
    }

    /**
     * Die Liste zeigt den neuesten Nachweis zuerst.
     */
    public function test_the_list_shows_the_newest_first(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $contextid = (int) \core\context\system::instance()->id;
        foreach (['alt', 'neu'] as $inhalt) {
            provenance::record(new record_request(
                component: 'block_elediaai_tutor',
                actionname: 'generate_text',
                provider: 'literag',
                model: '',
                userid: (int) $user->id,
                contextid: $contextid,
                assettype: 'text',
                content: $inhalt,
            ));
        }
        // Den ersten sicher nach hinten datieren.
        $ids = array_keys($DB->get_records('local_aitransparency_rec', [], 'id ASC'));
        $DB->set_field('local_aitransparency_rec', 'timecreated', time() - 3600, ['id' => reset($ids)]);

        $liste = provenance::recent(10);

        $this->assertCount(2, $liste);
        $this->assertSame(end($ids), $liste[0]->id);
    }
    /**
     * Der Anbieter erscheint mit seinem Namen, nicht mit seiner Kennung.
     *
     * Ein Chat-Turn speichert die Kennung seines Backends -- „ingestionapi" --
     * in einem Feld, das „Anbieter" heisst. Der Bericht und die Aufloesung
     * zeigten sie roh.
     */
    public function test_a_backend_id_is_shown_by_its_name(): void {
        if (!class_exists('\\local_elediaai_chatengine\\backend_resolver')) {
            $this->markTestSkipped('Die Chat-Engine ist in diesem Lauf nicht installiert.');
        }

        $this->assertSame(
            \local_elediaai_chatengine\adapter\ingestionapi_adapter::name(),
            marker::provider_label('ingestionapi')
        );
    }

    /**
     * Was die Engine nicht kennt, bleibt stehen.
     */
    public function test_an_unknown_provider_is_left_alone(): void {
        $this->assertSame('litellm', marker::provider_label('litellm'));
        $this->assertSame('', marker::provider_label(''));
    }
}
