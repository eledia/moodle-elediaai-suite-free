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

namespace local_elediaai_sources;

use local_elediaai_sources\output\shell;

/**
 * Unit tests for the shell chrome.
 *
 * The header template belongs to another plugin, so the name is the one thing
 * that can rot silently: a rename there turns every admin page of this plugin
 * into an exception, and nothing else in this suite would notice.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\output\shell
 */
final class shell_test extends \advanced_testcase {
    /**
     * The plugin no longer names the retired LernHive core anywhere.
     */
    public function test_no_reference_to_the_retired_shell_plugin(): void {
        $root = __DIR__ . '/..';
        $files = ['/classes/output/shell.php', '/settings.php', '/help.php', '/reindex.php'];

        foreach ($files as $file) {
            $this->assertStringNotContainsString(
                'local_lernhive',
                (string) file_get_contents($root . $file),
                $file . ' still names local_lernhive, which no longer exists.'
            );
            $this->assertStringNotContainsString('/local/lernhive/', (string) file_get_contents($root . $file));
        }
    }

    /**
     * Markup does not travel through the JavaScript argument list.
     *
     * Moodle raises a debugging notice past 1024 characters there, and a shell
     * header exceeds that on its own. The markup goes through the page instead.
     */
    public function test_the_settings_page_passes_no_markup_to_javascript(): void {
        $source = (string) file_get_contents(__DIR__ . '/../settings.php');

        $this->assertStringNotContainsString('headerHtml', $source);
        $this->assertStringNotContainsString('reindexHtml', $source);
    }

    /**
     * The shipped AMD build knows the same containers as its source.
     *
     * The build is a hand-copied file — no JavaScript toolchain is available
     * here — so it drifts silently when only the source is edited, and Moodle
     * serves the build. Comparing the container ids catches that without
     * assuming the build is byte-identical, should it ever be minified.
     */
    public function test_the_amd_build_matches_its_source(): void {
        $src = (string) file_get_contents(__DIR__ . '/../amd/src/settings_shell.js');
        $build = (string) file_get_contents(__DIR__ . '/../amd/build/settings_shell.min.js');

        foreach (['les-shell-header', 'les-shell-reindex', 'les-shell-source'] as $id) {
            $this->assertStringContainsString($id, $src, 'The source lost the container ' . $id . '.');
            $this->assertStringContainsString($id, $build, 'The AMD build was not rebuilt after a source change.');
        }
    }

    /**
     * When a shell provider is present, its header renders with our context.
     */
    public function test_header_renders_with_the_plugin_context(): void {
        global $PAGE;
        $this->resetAfterTest();

        if (!shell::is_available()) {
            $this->markTestSkipped('No shell provider installed; the pages fall back to a plain heading.');
        }

        // Die gemeinsame Huelle des Kerns, nicht mehr die Vorlage aus dem
        // Tutor-Block: dieses Plugin rendert keine fremde Seitenrahmung mehr.
        $output = \local_elediaai_core\output\plugin_page::header_html(
            shell::header_data(null, shell::ACTIVE_REINDEX)
        );

        $this->assertStringContainsString(get_string('pluginname', 'local_elediaai_sources'), $output);
        $this->assertStringContainsString('elediaai-core-shell__header', $output);
        // Unresolved language strings would render as [[key]].
        $this->assertStringNotContainsString('[[', $output);
    }

    /**
     * Ohne den Kern gibt es keine Huelle, und die Seiten fallen auf eine
     * schlichte Ueberschrift zurueck statt auf eine halb gebaute Kopfzeile.
     */
    public function test_the_shell_is_only_offered_when_core_is_there(): void {
        $this->assertSame(
            class_exists(\local_elediaai_core\output\section_nav::class),
            shell::is_available()
        );
    }
}
