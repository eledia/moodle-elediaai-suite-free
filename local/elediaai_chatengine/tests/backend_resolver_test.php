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

/**
 * The backend follows the sink, and every sink has a reader.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_chatengine\backend_resolver
 */
final class backend_resolver_test extends \advanced_testcase {
    /**
     * Every destination that can be written to can also be read from.
     *
     * This is the canary the ticket asks for. A destination added or renamed
     * in local_elediaai_sources without its adapter would otherwise not fail
     * anywhere: the site would simply report "no backend available", which
     * looks exactly like an unconfigured site.
     *
     * @return void
     */
    public function test_every_sink_has_an_adapter(): void {
        $this->resetAfterTest();

        if (!class_exists(\local_elediaai_sources\sink\sink_manager::class)) {
            $this->markTestSkipped('local_elediaai_sources is not installed.');
        }

        foreach (backend_resolver::paired_ids() as $id => $pair) {
            $this->assertTrue(
                $pair['sink'],
                "Adapter '$id' has no sink in local_elediaai_sources: nothing writes to what it reads."
            );
            $this->assertTrue(
                $pair['adapter'],
                "Sink '$id' has no adapter in the chat engine: content is written where no question can be asked."
            );
        }
    }

    /**
     * The active id is the one local_elediaai_sources reports, not a copy.
     *
     * @return void
     */
    public function test_active_id_follows_the_sink_setting(): void {
        $this->resetAfterTest();

        if (!class_exists(\local_elediaai_sources\sink\sink_manager::class)) {
            $this->markTestSkipped('local_elediaai_sources is not installed.');
        }

        set_config('sink', 'literag', 'local_elediaai_sources');
        $this->assertSame('literag', backend_resolver::active_id());
        $this->assertInstanceOf(adapter\literag_adapter::class, backend_resolver::adapter_for('literag'));

        set_config('sink', 'ingestionapi', 'local_elediaai_sources');
        $this->assertSame('ingestionapi', backend_resolver::active_id());
        $this->assertInstanceOf(adapter\ingestionapi_adapter::class, backend_resolver::adapter_for('ingestionapi'));
    }

    /**
     * An unconfigured site has no backend, and says so rather than failing.
     *
     * @return void
     */
    public function test_unconfigured_site_has_no_backend(): void {
        $this->resetAfterTest();

        set_config('sink', 'ingestionapi', 'local_elediaai_sources');
        set_config('backend_ingestionapi_url', '', 'local_elediaai_chatengine');

        $this->assertNull(backend_resolver::active());
        $this->assertFalse(backend_resolver::is_available());
    }

    /**
     * Asking for a backend when there is none names the destination.
     *
     * @return void
     */
    public function test_require_active_fails_with_a_reason(): void {
        $this->resetAfterTest();

        set_config('sink', 'ingestionapi', 'local_elediaai_sources');
        set_config('backend_ingestionapi_url', '', 'local_elediaai_chatengine');

        $this->expectException(adapter\adapter_exception::class);
        backend_resolver::require_active();
    }

    /**
     * An unknown id resolves to nothing rather than to a default.
     *
     * Guessing here would put a question to whichever backend happened to be
     * first in the list.
     *
     * @return void
     */
    public function test_unknown_id_resolves_to_nothing(): void {
        $this->resetAfterTest();

        $this->assertNull(backend_resolver::adapter_for('oerweave'));
        $this->assertNull(backend_resolver::adapter_for(''));
    }
}
