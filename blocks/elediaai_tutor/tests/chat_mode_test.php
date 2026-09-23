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

namespace block_elediaai_tutor;

use PHPUnit\Framework\Attributes\CoversClass;
use block_elediaai_tutor\local\chat_mode;

/**
 * Tests for the grounded / LLM-only / unavailable mode resolution.
 *
 * Ingestion availability is driven through local_elediaai_sources's per-course
 * marking (the plugin is present in this tree); the admin gate via the
 * allowllmonly setting.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_elediaai_tutor\local\chat_mode::class)]
final class chat_mode_test extends \advanced_testcase {
    /**
     * Skip if the ingestion plugin isn't installed (it should be in this tree).
     */
    protected function require_aisources(): void {
        if (!class_exists('\\local_elediaai_sources\\course_gate')) {
            $this->markTestSkipped('local_elediaai_sources is not installed.');
        }
    }

    /**
     * Create a course and (optionally) mark it for ingestion via its category.
     *
     * @param bool $ingested Whether to mark the course's category for ingestion.
     * @return int The course id.
     */
    private function make_course(bool $ingested): int {
        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        set_config('enabledcategories', $ingested ? (string) $cat->id : '', 'local_elediaai_sources');
        // The ingestion_available() call requires BOTH the gate (the category above) AND a recorded
        // ingestion state, so mark the course ingested through aisources's own API.
        if ($ingested && class_exists('\\local_elediaai_sources\\course_state')) {
            \local_elediaai_sources\course_state::set_ingested((int) $course->id, true);
        }
        return (int) $course->id;
    }

    /**
     * Put the backend override back, so it cannot leak into the next test.
     *
     * @return void
     */
    protected function tearDown(): void {
        \local_elediaai_chatengine\backend_resolver::override_for_testing(null);
        parent::tearDown();
    }

    /**
     * The full decision matrix.
     */
    public function test_resolution_matrix(): void {
        $this->resetAfterTest();
        $this->require_aisources();

        $ingested = (object) ['ragmode' => chat_mode::MODE_GROUNDED];
        $llmpref = (object) ['ragmode' => chat_mode::MODE_LLMONLY];

        // LLM-only allowed - and answerable: an ungrounded answer still needs a
        // model, so the backend has to be there and has to report the mode.
        $this->with_backend(true);
        set_config('allowllmonly', 1, 'block_elediaai_tutor');
        $cid = $this->make_course(true);
        $this->assertSame(chat_mode::MODE_GROUNDED, chat_mode::resolve($cid, $ingested));
        $this->assertSame(chat_mode::MODE_LLMONLY, chat_mode::resolve($cid, $llmpref));

        // Allowed + not ingested → forced LLM-only regardless of instance choice.
        $cidoff = $this->make_course(false);
        $this->assertSame(chat_mode::MODE_LLMONLY, chat_mode::resolve($cidoff, $ingested));

        // LLM-only disallowed.
        set_config('allowllmonly', 0, 'block_elediaai_tutor');
        $cid2 = $this->make_course(true);
        // Instance asked for LLM-only, but it's disallowed → grounded.
        $this->assertSame(chat_mode::MODE_GROUNDED, chat_mode::resolve($cid2, $llmpref));
        // Disallowed + not ingested → unavailable.
        $cidoff2 = $this->make_course(false);
        $this->assertSame(chat_mode::MODE_UNAVAILABLE, chat_mode::resolve($cidoff2, $ingested));
    }

    /**
     * Global chat (no course) follows the configured ingestion destination.
     *
     * There is no per-course signal here, so the answer is whether ingestion is
     * configured at all. This asks local_elediaai_sources's destination rather than a
     * setting name — the previous check read an endpoint setting that no longer
     * exists, which silently made grounding unavailable everywhere.
     */
    public function test_global_chat_follows_the_ingestion_destination(): void {
        $this->resetAfterTest();
        $this->require_aisources();

        set_config('allowllmonly', 1, 'block_elediaai_tutor');
        $grounded = (object) ['ragmode' => chat_mode::MODE_GROUNDED];

        set_config('sink', 'ingestionapi', 'local_elediaai_sources');
        set_config('sink_ingestionapi_baseurl', 'http://rag-service:8001', 'local_elediaai_sources');
        set_config('sink_ingestionapi_apikey', 'secret', 'local_elediaai_sources');
        $this->assertTrue(chat_mode::ingestion_available(0));
        $this->assertSame(chat_mode::MODE_GROUNDED, chat_mode::resolve(0, $grounded));

        // Taking the key away leaves nothing that can answer at all. That is
        // not "ungrounded", it is unavailable - promising a model-only chat
        // here would promise an answer nobody can give.
        set_config('sink_ingestionapi_apikey', '', 'local_elediaai_sources');
        $this->assertFalse(chat_mode::ingestion_available(0));
        $this->assertSame(chat_mode::MODE_UNAVAILABLE, chat_mode::resolve(0, $grounded));

        // With a backend in place it is ungrounded, as before.
        $this->with_backend(true);
        $this->assertSame(chat_mode::MODE_LLMONLY, chat_mode::resolve(0, $grounded));
    }

    /**
     * rag_enabled_for maps modes to the wire flag.
     */
    public function test_rag_enabled_for(): void {
        $this->assertTrue(chat_mode::rag_enabled_for(chat_mode::MODE_GROUNDED));
        $this->assertFalse(chat_mode::rag_enabled_for(chat_mode::MODE_LLMONLY));
        $this->assertNull(chat_mode::rag_enabled_for(chat_mode::MODE_UNAVAILABLE));
    }

    /**
     * is_llm_allowed reflects the admin setting (default allowed).
     */
    public function test_is_llm_allowed(): void {
        $this->resetAfterTest();
        $this->with_backend(true);

        set_config('allowllmonly', 1, 'block_elediaai_tutor');
        $this->assertTrue(chat_mode::is_llm_allowed());
        set_config('allowllmonly', 0, 'block_elediaai_tutor');
        $this->assertFalse(chat_mode::is_llm_allowed());
    }

    /**
     * The operator may want ungrounded answers; the backend decides if it can.
     *
     * The setting alone used to be enough, which would put the choice in front
     * of a teacher on a backend that cannot honour it.
     *
     * @return void
     */
    public function test_llm_only_needs_a_backend_that_reports_it(): void {
        $this->resetAfterTest();
        set_config('allowllmonly', 1, 'block_elediaai_tutor');

        $this->with_backend(false);
        $this->assertFalse(chat_mode::is_llm_allowed(), 'Offered although the backend does not report it.');

        $this->with_backend(true);
        $this->assertTrue(chat_mode::is_llm_allowed());

        \local_elediaai_chatengine\backend_resolver::override_for_testing(null);
        $this->assertFalse(chat_mode::is_llm_allowed(), 'Offered although no backend is configured.');
    }

    /**
     * Put a backend in place that does or does not answer without grounding.
     *
     * @param bool $supportsungrounded What the backend reports.
     * @return void
     */
    private function with_backend(bool $supportsungrounded): void {
        require_once(__DIR__ . '/../../../local/elediaai_chatengine/tests/fixtures/fake_adapter.php');
        \local_elediaai_chatengine\backend_resolver::override_for_testing(
            new \local_elediaai_chatengine\tests\fixtures\fake_adapter(
                capabilities: new \local_elediaai_chatengine\adapter\capabilities(
                    supportsungrounded: $supportsungrounded,
                ),
            )
        );
    }
}
