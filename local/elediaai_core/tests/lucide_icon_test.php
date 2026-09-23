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
 * Jedes benutzte Symbol gibt es auch.
 *
 * @package    local_elediaai_core
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

use advanced_testcase;
use local_elediaai_core\output\lucide_icon;

defined('MOODLE_INTERNAL') || die();

/**
 * Prueft die Symbolnamen dieses Plugins gegen das Sprite.
 *
 * @covers \local_elediaai_core\output\lucide_icon
 */
final class lucide_icon_test extends advanced_testcase {
    /**
     * Ein unbekannter Name gibt nichts zurueck -- und meldet nichts.
     *
     * Das ist die Eigenschaft, wegen der es diesen Test gibt: eine Karte ohne
     * Symbol sieht aus wie eine Gestaltungsentscheidung, nicht wie ein Tippfehler.
     * Am 19.09.2026 stand "shield-halved" in zwei Dateien, und die
     * Einstiegskarte des Handlungsprotokolls blieb leer.
     */
    public function test_an_unknown_name_fails_silently(): void {
        $this->assertSame('', lucide_icon::render('gibt-es-nicht'));
    }

    /**
     * Jeder Symbolname, den dieses Plugin benutzt, steht im Sprite.
     */
    public function test_every_icon_name_this_plugin_uses_exists(): void {
        global $CFG;

        $wurzel = $CFG->dirroot . '/local/elediaai_core';
        $sprite = file_get_contents($wurzel . '/pix/lucide.svg');
        $this->assertIsString($sprite);

        $namen = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($wurzel, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $datei) {
            $pfad = $datei->getPathname();
            if (!str_ends_with($pfad, '.php') || str_contains($pfad, '/tests/')) {
                continue;
            }
            $quelltext = (string) file_get_contents($pfad);
            // lucide_icon::render('name') und 'icon' => 'name' in den
            // Navigations- und Deskriptor-Feldern.
            preg_match_all("/lucide_icon::render\(\s*'([a-z0-9-]+)'/", $quelltext, $treffer);
            foreach ($treffer[1] as $name) {
                $namen[$name][] = basename($pfad);
            }
            preg_match_all("/'icon'\s*=>\s*'([a-z0-9-]+)'/", $quelltext, $treffer);
            foreach ($treffer[1] as $name) {
                $namen[$name][] = basename($pfad);
            }
        }

        $this->assertNotEmpty($namen, 'Kein einziger Symbolname gefunden -- der Test misst nichts.');

        $fehlend = [];
        foreach ($namen as $name => $dateien) {
            if (!str_contains($sprite, 'id="lucide-' . $name . '"')) {
                $fehlend[] = $name . ' (' . implode(', ', array_unique($dateien)) . ')';
            }
        }

        $this->assertSame(
            [],
            $fehlend,
            "Diese Symbolnamen stehen nicht im Sprite und rendern still als nichts:\n"
                . implode("\n", $fehlend)
        );
    }
}
