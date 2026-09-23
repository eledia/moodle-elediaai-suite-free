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
 * Tests for the developer page.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_elediaai_core;

use local_elediaai_core\output\developer_page;

/**
 * What the page reads out of the plugin's own files.
 *
 * Two of these tests are really about the design layer and not about the page:
 * if the `:root` block loses its section comments or the scale loses a step,
 * they fail here first. That is deliberate - the page is a view of that block,
 * so a change to it should have to be noticed.
 *
 * @covers \local_elediaai_core\output\developer_page
 */
final class developer_page_test extends \advanced_testcase {
    /**
     * The setting decides whether the site offers the page.
     *
     * @return void
     */
    public function test_the_setting_switches_the_page_on(): void {
        $this->resetAfterTest();

        $this->assertFalse(developer_page::is_enabled());

        set_config(developer_page::SETTING, 1, 'local_elediaai_core');
        $this->assertTrue(developer_page::is_enabled());

        set_config(developer_page::SETTING, 0, 'local_elediaai_core');
        $this->assertFalse(developer_page::is_enabled());
    }

    /**
     * The token groups come out of the stylesheet, grouped as it is written.
     *
     * @return void
     */
    public function test_the_groups_are_read_from_the_stylesheet(): void {
        $gruppen = developer_page::token_groups();

        $this->assertNotEmpty($gruppen);

        $titel = array_column($gruppen, 'title');
        $this->assertContains('Schriftskala', $titel);

        $alle = array_merge(...array_column($gruppen, 'tokens'));
        $this->assertContains('--eai-accent', $alle);
        $this->assertContains('--font-size-body', $alle);

        // Kein Titel ist leer: eine Gruppe ohne Ueberschrift waere eine Liste
        // ohne Aussage, und ein Hinweis darauf, dass die Abschnittskommentare
        // im :root-Block nicht mehr die erwartete Form haben.
        foreach ($gruppen as $gruppe) {
            $this->assertNotSame('', $gruppe['title']);
        }
    }

    /**
     * Eight steps, in the order the stylesheet declares them.
     *
     * @return void
     */
    public function test_the_type_scale_has_eight_steps(): void {
        $this->assertSame([
            '--font-size-display',
            '--font-size-h1',
            '--font-size-h2',
            '--font-size-h3',
            '--font-size-h4',
            '--font-size-body',
            '--font-size-small',
            '--font-size-caption',
        ], developer_page::type_scale());
    }

    /**
     * The contract is read from the document, not retyped.
     *
     * @return void
     */
    public function test_the_contract_comes_from_the_document(): void {
        $vertrag = developer_page::contract();

        $this->assertNotSame('', $vertrag);
        $this->assertStringContainsString('Was ein Plugin darf und was nicht', $vertrag);
        // Bis zur naechsten Ueberschrift derselben Ebene und keine Zeile
        // weiter: sonst haengt der halbe Rest des Dokuments mit an der Seite.
        $this->assertStringNotContainsString("\n## ", $vertrag);
    }

    /**
     * The environment names this installation, including the suite.
     *
     * @return void
     */
    public function test_the_environment_names_the_suite(): void {
        $umgebung = developer_page::environment();

        $this->assertArrayHasKey('Moodle', $umgebung);
        $this->assertArrayHasKey('PHP', $umgebung);
        $this->assertArrayHasKey('Theme', $umgebung);
        $this->assertStringContainsString('local_elediaai_core', $umgebung['Plugins']);
    }
}
