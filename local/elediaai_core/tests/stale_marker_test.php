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
 * Tests for the shared stale-fingerprint helper.
 *
 * @package    local_elediaai_core
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

use advanced_testcase;
use local_elediaai_core\local\stale_marker;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests the component behavior and contracts.
 *
 * @covers \local_elediaai_core\local\stale_marker
 */
final class stale_marker_test extends advanced_testcase {
    public function test_single_part_matches_legacy_sha1_trim(): void {
        // Backwards-compatibility contract with mod_aifeedback's old hash.
        $prompt = '  Give thoughtful feedback.  ';
        $this->assertSame(sha1(trim($prompt)), stale_marker::hash([$prompt]));
        $this->assertSame(sha1('Give thoughtful feedback.'), stale_marker::hash([$prompt]));
    }

    public function test_single_part_is_deterministic_and_trimmed(): void {
        $this->assertSame(stale_marker::hash(['abc']), stale_marker::hash([' abc ']));
        $this->assertNotSame(stale_marker::hash(['abc']), stale_marker::hash(['abd']));
    }

    public function test_multi_part_order_and_boundaries_matter(): void {
        $this->assertNotSame(
            stale_marker::hash(['a', 'b']),
            stale_marker::hash(['b', 'a'])
        );
        // Boundary safety: different splits must not collide.
        $this->assertNotSame(
            stale_marker::hash(['ab', 'c']),
            stale_marker::hash(['a', 'bc'])
        );
    }

    public function test_multi_part_ignores_keys_uses_values(): void {
        $this->assertSame(
            stale_marker::hash(['prompt' => 'a', 'model' => 'b']),
            stale_marker::hash(['a', 'b'])
        );
    }

    public function test_is_stale_empty_stored_hash_is_never_stale(): void {
        $this->assertFalse(stale_marker::is_stale(null, ['anything']));
        $this->assertFalse(stale_marker::is_stale('', ['anything']));
    }

    public function test_is_stale_detects_changes(): void {
        $stored = stale_marker::hash(['original']);
        $this->assertFalse(stale_marker::is_stale($stored, ['original']));
        $this->assertTrue(stale_marker::is_stale($stored, ['edited']));
    }
}
