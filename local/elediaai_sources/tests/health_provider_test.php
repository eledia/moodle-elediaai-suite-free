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

use local_elediaai_sources\elediaai_core\health_provider;

/**
 * Tests for what AI Sources reports on the suite's health page (M-13).
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\elediaai_core\health_provider
 */
final class health_provider_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        if (!class_exists(\local_elediaai_core\health\check::class)) {
            $this->markTestSkipped('local_elediaai_core is not installed.');
        }
        $this->resetAfterTest();
    }

    /**
     * The only destination check among the returned checks.
     *
     * @return \local_elediaai_core\health\check
     */
    private function destination_check(): \local_elediaai_core\health\check {
        $checks = array_values(array_filter(
            health_provider::get_checks(),
            fn($check) => $check->id === 'destination'
        ));
        $this->assertCount(1, $checks);
        return $checks[0];
    }

    /**
     * LiteRAG chosen without its ingestion key: the key is named, and the link goes to LiteRAG.
     */
    public function test_literag_without_key_names_the_key_and_links_literag(): void {
        if (!class_exists('\\local_literag\\local\\config')) {
            $this->markTestSkipped('local_literag is not installed.');
        }
        set_config('sink', 'literag', 'local_elediaai_sources');
        set_config('ingest_api_key', '', 'local_literag');

        $check = $this->destination_check();

        $this->assertSame(\local_elediaai_core\health\check::STATUS_UNCONFIGURED, $check->status);
        $this->assertStringContainsString(get_string('sink_literag', 'local_elediaai_sources'), $check->detail);
        $this->assertStringContainsString(get_string('sink_literag_nokey', 'local_elediaai_sources'), $check->detail);
        $this->assertStringNotContainsString('No destination', $check->detail);
        $this->assertSame('local_literag', $check->actionurl->get_param('section'));
    }

    /**
     * The external service without URL or key: its own reason, and this plugin's settings.
     */
    public function test_ingestion_api_without_url_links_own_settings(): void {
        set_config('sink', 'ingestionapi', 'local_elediaai_sources');
        set_config('sink_ingestionapi_baseurl', '', 'local_elediaai_sources');
        set_config('sink_ingestionapi_apikey', '', 'local_elediaai_sources');

        $check = $this->destination_check();

        $this->assertSame(\local_elediaai_core\health\check::STATUS_UNCONFIGURED, $check->status);
        $this->assertStringContainsString(
            get_string('sink_ingestionapi_notconfigured', 'local_elediaai_sources'),
            $check->detail
        );
        $this->assertSame('local_elediaai_sources_settings', $check->actionurl->get_param('section'));
    }
}
