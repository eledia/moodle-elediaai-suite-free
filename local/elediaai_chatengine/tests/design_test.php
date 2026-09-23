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

use local_elediaai_chatengine\local\design;

/**
 * Chat designs: what applies, in which order, and what never reaches a page.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_chatengine\local\design
 */
final class design_test extends \advanced_testcase {
    /**
     * Store a design and return its id.
     *
     * @param string $shortname Machine name.
     * @param array $tokens Token map.
     * @return int The design id.
     */
    private function create_design(string $shortname, array $tokens): int {
        global $DB;

        return (int) $DB->insert_record(design::TABLE, (object) [
            'name' => ucfirst($shortname),
            'shortname' => $shortname,
            'tokens' => json_encode($tokens),
            'sortorder' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * A site that defines no design looks exactly as it did before designs existed.
     *
     * This is the guarantee the whole feature rests on: introducing it changes
     * nothing until somebody chooses something.
     *
     * @return void
     */
    public function test_without_a_design_nothing_is_emitted(): void {
        $this->resetAfterTest();

        $this->assertSame([], design::resolve());
        $this->assertSame('', design::css_variables(design::resolve()));
    }

    /**
     * An instance with no choice of its own follows the site.
     *
     * @return void
     */
    public function test_instance_inherits_the_site_design(): void {
        $this->resetAfterTest();
        $id = $this->create_design('site', ['--eac-accent' => '#194866']);
        set_config('sitedesign', $id, 'local_elediaai_chatengine');

        $this->assertSame(['--eac-accent' => '#194866'], design::resolve(-1));
    }

    /**
     * An instance that picks one overrides the site.
     *
     * @return void
     */
    public function test_instance_choice_beats_the_site(): void {
        $this->resetAfterTest();
        $site = $this->create_design('site', ['--eac-accent' => '#194866']);
        $own = $this->create_design('own', ['--eac-accent' => '#aa3311']);
        set_config('sitedesign', $site, 'local_elediaai_chatengine');

        $this->assertSame(['--eac-accent' => '#aa3311'], design::resolve($own));
    }

    /**
     * An instance can also refuse both and keep the built-in look.
     *
     * @return void
     */
    public function test_instance_can_choose_the_built_in_look(): void {
        $this->resetAfterTest();
        set_config(
            'sitedesign',
            $this->create_design('site', ['--eac-accent' => '#194866']),
            'local_elediaai_chatengine'
        );

        $this->assertSame([], design::resolve(design::NONE));
    }

    /**
     * What a placement resolves for itself has the last word.
     *
     * The tutor block brings a full design from its own settings; a chat design
     * must not silently repaint a block somebody branded by hand.
     *
     * @return void
     */
    public function test_placement_values_win(): void {
        $this->resetAfterTest();
        $id = $this->create_design('site', ['--eac-accent' => '#194866', '--eac-ink' => '#111111']);
        set_config('sitedesign', $id, 'local_elediaai_chatengine');

        $resolved = design::resolve(-1, ['--eac-accent' => '#aa3311']);

        $this->assertSame('#aa3311', $resolved['--eac-accent']);
        // What the placement does not set still comes from the design.
        $this->assertSame('#111111', $resolved['--eac-ink']);
    }

    /**
     * A design that was deleted leaves the built-in look, not a guess.
     *
     * @return void
     */
    public function test_deleted_design_falls_back_to_defaults(): void {
        global $DB;
        $this->resetAfterTest();
        $id = $this->create_design('gone', ['--eac-accent' => '#194866']);
        $DB->delete_records(design::TABLE, ['id' => $id]);

        $this->assertSame([], design::resolve($id));
    }

    /**
     * Nothing reaches the style attribute that could close it.
     *
     * The values land in an inline style, so a stray brace or semicolon would
     * let a token end the declaration and start a rule of its own.
     *
     * @return void
     */
    public function test_dangerous_values_are_dropped(): void {
        $this->resetAfterTest();
        $id = $this->create_design('nasty', [
            '--eac-ok' => '#194866',
            '--eac-brace' => 'red} body {display:none',
            '--eac-semicolon' => 'red;position:fixed',
            '--eac-url' => 'url(https://evil.example/x.png)',
            '--eac-script' => 'javascript:alert(1)',
            'notatoken' => 'red',
        ]);

        $resolved = design::resolve($id);

        $this->assertSame(['--eac-ok' => '#194866'], $resolved);
        $this->assertSame('--eac-ok:#194866;', design::css_variables($resolved));
    }

    /**
     * A design reaches the rendered panel; no design leaves the markup bare.
     *
     * @return void
     */
    public function test_design_reaches_the_panel(): void {
        global $PAGE;

        $this->resetAfterTest();
        $PAGE->set_url('/');
        // $OUTPUT is the bootstrap renderer until the page is set up; ask for
        // the real one so the renderable gets the type it declares.
        $output = $PAGE->get_renderer('core');

        $panel = new \local_elediaai_chatengine\output\chat_panel(
            component: 'mod_aichat',
            instanceid: 1,
            context: \context_system::instance(),
        );
        $bare = $output->render_from_template(
            'local_elediaai_chatengine/chat_panel',
            $panel->export_for_template($output)
        );
        $this->assertStringContainsString('style=""', $bare);

        $id = $this->create_design('house', ['--eac-accent' => '#aa3311']);
        $themed = new \local_elediaai_chatengine\output\chat_panel(
            component: 'mod_aichat',
            instanceid: 1,
            context: \context_system::instance(),
            designid: $id,
        );
        $html = $output->render_from_template(
            'local_elediaai_chatengine/chat_panel',
            $themed->export_for_template($output)
        );
        $this->assertStringContainsString('style="--eac-accent:#aa3311;"', $html);
    }

    /**
     * The instance menu offers inheriting, the built-in look, and each design.
     *
     * @return void
     */
    public function test_instance_menu_offers_inheriting(): void {
        $this->resetAfterTest();
        $id = $this->create_design('house', ['--eac-accent' => '#194866']);

        $menu = design::instance_menu();

        $this->assertArrayHasKey(-1, $menu);
        $this->assertArrayHasKey(design::NONE, $menu);
        $this->assertArrayHasKey($id, $menu);
    }
}
