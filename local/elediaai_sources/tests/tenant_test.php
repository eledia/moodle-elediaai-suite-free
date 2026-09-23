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

/**
 * Unit tests for the derived tenant identity.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\tenant
 */
final class tenant_test extends \advanced_testcase {
    /**
     * Canonicalisation: host (lowercased), optional subdirectory, safe alphabet.
     *
     * @dataProvider canonicalisation_provider
     * @param string $url The site URL.
     * @param string $expected The canonical tenant id.
     */
    public function test_from_url(string $url, string $expected): void {
        $this->assertSame($expected, tenant::from_url($url));
    }

    /**
     * Data provider for canonicalisation cases.
     *
     * @return array[]
     */
    public static function canonicalisation_provider(): array {
        return [
            'plain host' => ['https://moodle.uni-x.de', 'moodle.uni-x.de'],
            'uppercase host' => ['https://Moodle.Example.COM', 'moodle.example.com'],
            'scheme is irrelevant' => ['http://moodle.uni-x.de', 'moodle.uni-x.de'],
            'subdirectory install' => ['https://example.com/lms', 'example.com-lms'],
            'trailing slash' => ['https://example.com/lms/', 'example.com-lms'],
            'deep path' => ['https://example.com/sites/moodle', 'example.com-sites-moodle'],
            'port is dropped' => ['https://example.com:8443', 'example.com'],
            'empty/broken input' => ['not a url', 'default'],
        ];
    }

    /**
     * Two different subdirectory installs on one host get distinct tenants.
     */
    public function test_subdirectory_installs_are_distinct(): void {
        $this->assertNotSame(
            tenant::from_url('https://example.com/moodle1'),
            tenant::from_url('https://example.com/moodle2'),
        );
    }

    /**
     * id() derives from this site's wwwroot and matches from_url().
     */
    public function test_id_matches_wwwroot(): void {
        global $CFG;
        $this->assertSame(tenant::from_url((string) $CFG->wwwroot), tenant::id());
        // Never contains the source_id separator.
        $this->assertStringNotContainsString(':', tenant::id());
    }
}
