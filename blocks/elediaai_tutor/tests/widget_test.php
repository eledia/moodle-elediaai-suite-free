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
use block_elediaai_tutor\local\widget;

/**
 * Tests for the mode-aware widget::config_error() gate.
 *
 * The MCP connector (webservice_elediamcp) + a configured external service are
 * required only for GROUNDED answers (which call back into Moodle); LLM-only
 * mode requires neither — only the RAG/Tutor server URL, which every mode uses.
 * The connector-absent branch is exercised by the isolated Behat run (the
 * connector class genuinely does not exist there), since it is present in this
 * tree.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_elediaai_tutor\local\widget::class)]
final class widget_test extends \advanced_testcase {
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
     * Put a usable backend in place.
     *
     * These tests are about what the widget renders, and it renders a chat only
     * where something can answer. Without this they would assert against the
     * configuration notice - which is correct behaviour, just not the subject.
     *
     * @return void
     */
    private function with_backend(): void {
        require_once(__DIR__ . '/../../../local/elediaai_chatengine/tests/fixtures/fake_adapter.php');
        \local_elediaai_chatengine\backend_resolver::override_for_testing(
            new \local_elediaai_chatengine\tests\fixtures\fake_adapter()
        );
    }

    /**
     * Skip if the ingestion plugin isn't installed (needed to force grounded mode).
     */
    protected function require_aisources(): void {
        if (!class_exists('\\local_elediaai_sources\\course_gate')) {
            $this->markTestSkipped('local_elediaai_sources is not installed.');
        }
    }

    /**
     * Create a course marked ingested so it resolves to grounded mode.
     *
     * @return int The course id.
     */
    private function make_ingested_course(): int {
        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        set_config('enabledcategories', (string) $cat->id, 'local_elediaai_sources');
        \local_elediaai_sources\course_state::set_ingested((int) $course->id, true);
        return (int) $course->id;
    }

    /**
     * A missing RAG/Tutor server URL is a fatal config error in every mode.
     */
    public function test_missing_backend_errors(): void {
        $this->resetAfterTest();
        // No destination reachable: whichever mode is configured, there is
        // nobody to answer, and the block says so instead of showing a box
        // that swallows questions.
        set_config('backend_ingestionapi_url', '', 'local_elediaai_chatengine');
        set_config('sink', 'ingestionapi', 'local_elediaai_sources');

        $this->assertSame(
            get_string('error_backend_unavailable', 'local_elediaai_chatengine'),
            widget::config_error(0)
        );
    }

    /**
     * LLM-only mode needs neither the connector nor a service: healthy with just
     * the RAG URL, even with no MCP service selected.
     */
    public function test_llmonly_needs_no_connector_or_service(): void {
        $this->resetAfterTest();
        $this->configure_backend();
        $this->with_backend();
        set_config('allowllmonly', 1, 'block_elediaai_tutor');
        set_config('mcpserviceid', 0, 'local_elediaai_chatengine');
        // Global chat with no ingestion destination configured resolves to LLM-only.
        set_config('sink_ingestionapi_baseurl', '', 'local_elediaai_sources');
        set_config('sink_ingestionapi_apikey', '', 'local_elediaai_sources');
        $this->assertSame(
            chat_mode::MODE_LLMONLY,
            chat_mode::resolve(0, (object) ['ragmode' => chat_mode::MODE_GROUNDED])
        );
        $this->assertNull(widget::config_error(0));
    }

    /**
     * Grounded mode requires a configured external service (the connector itself
     * is present in this tree, so this exercises the service-id branch).
     */
    public function test_grounded_requires_service(): void {
        $this->resetAfterTest();
        $this->require_aisources();
        $this->configure_backend();
        $this->with_backend();
        set_config('allowllmonly', 1, 'block_elediaai_tutor');
        $courseid = $this->make_ingested_course();
        $grounded = ['ragmode' => chat_mode::MODE_GROUNDED];

        // Sanity: this course resolves to grounded.
        $this->assertSame(
            chat_mode::MODE_GROUNDED,
            chat_mode::resolve($courseid, (object) $grounded)
        );

        // No external service selected → a fatal config error for grounded.
        set_config('mcpserviceid', 0, 'local_elediaai_chatengine');
        $this->assertSame(
            get_string('error_service_not_configured', 'block_elediaai_tutor'),
            widget::config_error($courseid, $grounded)
        );

        // Service selected → healthy.
        set_config('mcpserviceid', 1, 'local_elediaai_chatengine');
        $this->assertNull(widget::config_error($courseid, $grounded));
    }

    /**
     * Prepare a renderable global-chat setup (LLM-only, no ingestion) and a
     * logged-in user.
     *
     * @return \stdClass The user.
     */
    private function setup_render_user(): \stdClass {
        set_config('ragserverurl', 'https://rag.example.com/mcp', 'block_elediaai_tutor');
        $this->with_backend();
        set_config('allowllmonly', 1, 'block_elediaai_tutor');
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        return $user;
    }

    /**
     * On the dashboard the hero variant renders: greeting headline, briefing
     * chip and no classic welcome bubble.
     */
    public function test_render_dashboard_shows_hero_and_briefing(): void {
        $this->resetAfterTest();
        $user = $this->setup_render_user();
        set_config('promptstarters', "What is due this week?", 'block_elediaai_tutor');

        $html = widget::render(\core\context\system::instance(), 0, ['dashboard' => true]);

        $this->assertStringContainsString('elediaai-chat-hero', $html);
        // The greeting is rendered with the name set apart, so the sentence is
        // not one contiguous string in the markup any more: the placeholder is
        // replaced by a span the design colours.
        $expectedgreeting = str_replace(
            '{firstname}',
            \html_writer::span($user->firstname, 'elediaai-chat-hero-name'),
            get_string('default_dashboardgreeting', 'block_elediaai_tutor')
        );
        $this->assertStringContainsString($expectedgreeting, $html);
        $this->assertStringContainsString('data-action="briefing"', $html);
        $this->assertStringContainsString('What is due this week?', $html);
        $this->assertStringNotContainsString('elediaai-chat-welcome', $html);

        // Slim chrome: no history browser, no new-conversation button, no
        // answer-style chips, no mode banner, no identity row.
        $this->assertStringNotContainsString('data-action="history"', $html);
        $this->assertStringNotContainsString('data-action="newconversation"', $html);
        $this->assertStringNotContainsString('data-region="styles"', $html);
        $this->assertStringNotContainsString('elediaai-chat-modenote', $html);
        $this->assertStringNotContainsString('elediaai-chat-identity', $html);
        // The privacy control stays.
        $this->assertStringContainsString('data-action="privacy"', $html);
        // And the bin, in the sticky footer and rendered hidden: an empty
        // dashboard has no conversation to delete, and the row it sits in keeps
        // its height so revealing it does not move the composer.
        $this->assertStringContainsString('data-action="delete-current"', $html);
        $this->assertStringContainsString('data-region="delete-current" hidden', $html);
        // The shield has a twin down there for the length of the conversation,
        // because the named one under the greeting leaves with the hero. Also
        // hidden at first: while the greeting stands, the hero's shield is the
        // one on screen.
        $this->assertStringContainsString('data-region="privacy-tool" hidden', $html);
    }

    /**
     * The hero always chats globally: a course id wired into the instance
     * is discarded on the dashboard, so the send path never
     * hits the course-block gate.
     */
    public function test_render_dashboard_forces_global_chat(): void {
        $this->resetAfterTest();
        $this->setup_render_user();
        $course = $this->getDataGenerator()->create_course();

        $html = widget::render(\core\context\system::instance(), (int) $course->id, ['dashboard' => true]);
        $this->assertStringContainsString('"courseid":0', $html);

        // Off the dashboard the course id passes through untouched.
        $html = widget::render(\core\context\system::instance(), (int) $course->id, []);
        $this->assertStringContainsString('"courseid":' . $course->id, $html);
    }

    /**
     * The site toggle switches the hero off: the same dashboard flag renders
     * the classic widget.
     */
    public function test_render_dashboard_disabled_renders_classic(): void {
        $this->resetAfterTest();
        $this->setup_render_user();
        set_config('dashboardenabled', 0, 'block_elediaai_tutor');

        $html = widget::render(\core\context\system::instance(), 0, ['dashboard' => true]);

        $this->assertStringNotContainsString('elediaai-chat-hero', $html);
        $this->assertStringNotContainsString('data-action="briefing"', $html);
        $this->assertStringContainsString('elediaai-chat-welcome', $html);
    }

    /**
     * Off the dashboard nothing changes: no hero, no briefing chip.
     */
    public function test_render_without_dashboard_flag_is_classic(): void {
        $this->resetAfterTest();
        $this->setup_render_user();

        $html = widget::render(\core\context\system::instance(), 0, []);

        $this->assertStringNotContainsString('elediaai-chat-hero', $html);
        $this->assertStringNotContainsString('data-action="briefing"', $html);
        $this->assertStringContainsString('elediaai-chat-welcome', $html);
        // No bin off the dashboard: there the history browser deletes single
        // conversations, each from its own entry, and the header carries the
        // button for starting a new one.
        $this->assertStringNotContainsString('data-action="delete-current"', $html);
        $this->assertStringNotContainsString('data-region="privacy-tool"', $html);
    }

    /**
     * Pills parse the "fa-icon | Label | Prompt" line format: the short label
     * is the button text, the full prompt travels in data-prompt, the icon
     * renders as a FontAwesome element.
     */
    public function test_render_dashboard_pill_format(): void {
        $this->resetAfterTest();
        $this->setup_render_user();
        set_config(
            'promptstarters',
            "fa-tasks | Open tasks | Which tasks are currently open for me?\n"
                . 'action | fa-plus | Create course | Create a new course for me.',
            'block_elediaai_tutor'
        );

        $html = widget::render(\core\context\system::instance(), 0, ['dashboard' => true]);

        $this->assertStringContainsString('Open tasks', $html);
        $this->assertStringContainsString('data-prompt="Which tasks are currently open for me?"', $html);
        $this->assertStringContainsString('lucide-tasks', $html);
        $this->assertStringNotContainsString('data-prompt="Open tasks"', $html);

        // The 'action' intent segment lands as data-intent; the plain pill has none.
        $this->assertStringContainsString('data-intent="action"', $html);
        $this->assertStringContainsString('data-prompt="Create a new course for me."', $html);
        $this->assertStringNotContainsString('data-intent="auto"', $html);
    }

    /**
     * An unconfigured dashboard still shows the built-in audience pills.
     */
    public function test_render_dashboard_default_pills(): void {
        $this->resetAfterTest();
        $this->setup_render_user();

        $html = widget::render(\core\context\system::instance(), 0, ['dashboard' => true]);

        $this->assertStringContainsString('data-action="starter"', $html);
        $this->assertStringContainsString('data-prompt=', $html);
    }

    /**
     * Teachers get the teacher starter list on the dashboard; the base list
     * stays for everyone else.
     */
    public function test_render_dashboard_teacher_starters(): void {
        $this->resetAfterTest();
        $user = $this->setup_render_user();
        set_config('promptstarters', "Student question", 'block_elediaai_tutor');
        set_config('promptstarters_teacher', "Show my grading queue", 'block_elediaai_tutor');

        // As a plain user: the base list.
        $html = widget::render(\core\context\system::instance(), 0, ['dashboard' => true]);
        $this->assertStringContainsString('Student question', $html);
        $this->assertStringNotContainsString('Show my grading queue', $html);

        // As a teacher (audience cache purged after the role change): the teacher list.
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        \cache::make('block_elediaai_tutor', 'audience')->purge();

        $html = widget::render(\core\context\system::instance(), 0, ['dashboard' => true]);
        $this->assertStringContainsString('Show my grading queue', $html);
        $this->assertStringNotContainsString('Student question', $html);
    }

    /**
     * Configure a reachable backend, as an administrator would.
     *
     * @return void
     */
    private function configure_backend(): void {
        set_config('sink', 'ingestionapi', 'local_elediaai_sources');
        set_config('backend_ingestionapi_url', 'https://agent.example.com/mcp', 'local_elediaai_chatengine');
        set_config('tool_chat', 'tutor_chat', 'local_elediaai_chatengine');
    }
}
