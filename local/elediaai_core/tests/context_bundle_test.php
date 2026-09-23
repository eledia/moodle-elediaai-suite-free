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
 * Tests for shared context DTOs.
 *
 * @package    local_elediaai_core
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

use advanced_testcase;
use local_elediaai_core\context\context_bundle;
use local_elediaai_core\context\context_item;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests the component behavior and contracts.
 *
 * @covers \local_elediaai_core\context\context_bundle
 * @covers \local_elediaai_core\context\context_item
 */
final class context_bundle_test extends advanced_testcase {
    public function test_combined_text_uses_item_titles_and_skips_empty_text(): void {
        $bundle = new context_bundle([
            new context_item('course', 7, 70, 'Course overview', "First text.\n"),
            new context_item('section', 7, 71, 'Empty section', '  '),
            new context_item('cm', 7, 72, 'Activity', 'Second text.'),
        ]);

        $this->assertSame("## Course overview\n\nFirst text.\n\n## Activity\n\nSecond text.", $bundle->combinedtext);
    }

    public function test_fingerprint_is_stable_for_equivalent_metadata_order(): void {
        $first = new context_bundle([
            new context_item('cm', 7, 72, 'Activity', 'Text', [
                'modname' => 'page',
                'ids' => ['cmid' => 12, 'instance' => 3],
            ]),
        ], 'Summary', 72);
        $second = new context_bundle([
            new context_item('cm', 7, 72, 'Activity', 'Text', [
                'ids' => ['instance' => 3, 'cmid' => 12],
                'modname' => 'page',
            ]),
        ], 'Summary', 72);

        $this->assertSame($first->fingerprint, $second->fingerprint);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first->fingerprint);
    }

    public function test_fingerprint_changes_when_text_changes(): void {
        $first = new context_bundle([
            new context_item('cm', 7, 72, 'Activity', 'Original text'),
        ]);
        $second = new context_bundle([
            new context_item('cm', 7, 72, 'Activity', 'Changed text'),
        ]);

        $this->assertNotSame($first->fingerprint, $second->fingerprint);
    }
}
