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

namespace webservice_elediamcp;

use advanced_testcase;

/**
 * Hand-written markup in the page scripts leaves no visible remnants (G-04).
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class page_markup_test extends advanced_testcase {
    /**
     * No inline icon is followed by a stray "/>", which the browser prints as text.
     */
    public function test_no_stray_closing_after_inline_svg(): void {
        global $CFG;
        $root = $CFG->dirroot . '/webservice/elediamcp';
        $files = array_merge(
            glob($root . '/*.php'),
            glob($root . '/token/*.php'),
            glob($root . '/oauth/*.php'),
            glob($root . '/classes/output/*.php')
        );
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $this->assertStringNotContainsString('</svg>/>', file_get_contents($file), basename($file));
        }
    }
}
