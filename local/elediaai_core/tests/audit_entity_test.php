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
 * Audit reportbuilder entity tests.
 *
 * @package    local_elediaai_core
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core;

use advanced_testcase;
use local_elediaai_core\local\audit_config;
use local_elediaai_core\reportbuilder\local\entities\ai_action_audit;
use local_elediaai_core\reportbuilder\local\systemreports\audit as audit_report;
use ReflectionClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests the component behavior and contracts.
 *
 * @covers \local_elediaai_core\reportbuilder\local\entities\ai_action_audit
 * @covers \local_elediaai_core\reportbuilder\local\systemreports\audit
 */
final class audit_entity_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        // The audit report extends core's ai_action_register entity and reads
        // the {ai_action_register} table, both introduced in Moodle 5.0. On
        // Moodle 4.5 the feature is intentionally unavailable, so there is
        // nothing to assert here.
        if (!audit_config::feature_available()) {
            $this->markTestSkipped('Audit report requires the core_ai usage register (Moodle 5.0+).');
        }
        $this->resetAfterTest();
    }

    /**
     * The subclass must register the LernHive-specific columns on top
     * of the core entity columns so the audit report has its full
     * payload (text content + presentation columns).
     */
    public function test_extended_columns_are_registered(): void {
        $entity = new ai_action_audit();

        // Use reflection to call the protected `get_available_columns()` —
        // the entity helper does not expose a public accessor for the
        // column collection (only `get_columns()` after the report is
        // bound). We still want to assert on the subclass API directly.
        $ref = new ReflectionClass($entity);
        $method = $ref->getMethod('get_available_columns');
        $method->setAccessible(true);
        $columns = $method->invoke($entity);

        $names = array_map(static fn($c) => $c->get_name(), $columns);

        $this->assertContains('action_icon', $names);
        $this->assertContains('actor_anonymized', $names);
        $this->assertContains('context_link', $names);
        $this->assertContains('provider_model', $names);
        $this->assertContains('prompt', $names);
        $this->assertContains('generatedcontent', $names);
        $this->assertContains('model', $names);
        $this->assertContains('success_icon', $names);
        $this->assertContains('tokens', $names);
    }

    /**
     * The success callback renders an icon (with sr-only label) when
     * not downloading. Behaviour during download is exercised via the
     * end-to-end test below.
     */
    public function test_format_success_icon_renders_check_or_times(): void {
        $okhtml = ai_action_audit::format_success_icon(1);
        $this->assertStringContainsString('lucide-check', $okhtml);
        $this->assertStringContainsString('text-success', $okhtml);

        $failhtml = ai_action_audit::format_success_icon(0);
        $this->assertStringContainsString('lucide-times', $failhtml);
        $this->assertStringContainsString('text-danger', $failhtml);

        $failwitherror = ai_action_audit::format_success_icon(0, (object) ['audit_errormessage' => 'Provider timeout']);
        $this->assertStringContainsString('data-action="lh-audit-preview"', $failwitherror);
        $this->assertStringContainsString('Provider timeout', $failwitherror);

        $this->assertSame('—', ai_action_audit::format_success_icon(null));
    }

    /**
     * Error details are sensitive and must not leak into report exports
     * when the audit setting hides them on screen.
     */
    public function test_format_success_icon_hides_error_in_download_when_disabled(): void {
        set_config('audit_show_error', 0, 'local_elediaai_core');
        $_GET['download'] = 'csv';

        try {
            $cell = ai_action_audit::format_success_icon(0, (object) [
                'audit_errormessage' => 'Provider timeout with sensitive details',
            ]);
        } finally {
            unset($_GET['download']);
        }

        $this->assertSame(get_string('no'), $cell);
    }

    /**
     * Combined token cell joins prompt + completion with a slash, and
     * collapses to "—" when both are zero so failed calls stay quiet.
     */
    public function test_format_tokens_cell_combines_or_dashes(): void {
        $row = (object) ['tokens_prompt' => 420, 'tokens_completion' => 180];
        $html = ai_action_audit::format_tokens_cell(420, $row);
        $this->assertStringContainsString('420 / 180', $html);
        $this->assertStringContainsString('title=', $html);

        $empty = (object) ['tokens_prompt' => 0, 'tokens_completion' => 0];
        $this->assertSame('—', ai_action_audit::format_tokens_cell(0, $empty));
    }

    /**
     * Provider labels should be short enough for the audit table.
     */
    public function test_format_provider_name_shortens_common_provider_labels(): void {
        $this->assertSame('OpenAI', ai_action_audit::format_provider_name('aiprovider_openai'));
        $this->assertSame('Custom', ai_action_audit::format_provider_name('aiprovider_custom'));
        $this->assertSame('—', ai_action_audit::format_provider_name(''));
    }

    /**
     * Provider and model share one compact cell in the technical audit table.
     */
    public function test_format_provider_model_combines_provider_and_model(): void {
        $html = ai_action_audit::format_provider_model('aiprovider_openai', (object) [
            'audit_model' => 'gpt-4o-mini-2024-07-18',
        ]);

        $this->assertStringContainsString('OpenAI', $html);
        $this->assertStringContainsString('gpt-4o-mini-2024-07-18', $html);
        $this->assertStringContainsString('lh-audit-provider-model', $html);
    }

    /**
     * A row recorded without a person must say so.
     *
     * Core's own `fullnamewithlink` returns the empty string when the
     * LEFT JOIN finds nobody, and an empty cell in an audit reads as a
     * fault rather than as a decision. Chat turns are written this way
     * on purpose, so the report has to name the case.
     */
    public function test_a_row_without_a_person_says_so_instead_of_showing_nothing(): void {
        $this->resetAfterTest();

        $html = ai_action_audit::format_named_actor(0, (object) []);

        $this->assertNotSame('', trim(strip_tags($html)));
        $this->assertStringContainsString(
            get_string('audit_actor_not_recorded', 'local_elediaai_core'),
            $html
        );
        // The reason belongs on the cell, not only in the documentation.
        $this->assertStringContainsString(
            get_string('audit_actor_not_recorded_help', 'local_elediaai_core'),
            $html
        );
    }

    /**
     * A real person still gets their name and a link to their profile.
     */
    public function test_a_row_with_a_person_still_names_them(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
        ]);

        $html = ai_action_audit::format_named_actor($user->id, (object) [
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
        ]);

        $this->assertStringContainsString('Ada', $html);
        $this->assertStringContainsString('Lovelace', $html);
        $this->assertStringContainsString('/user/profile.php?id=' . $user->id, $html);
    }

    /**
     * Fresh installs should keep the full audit visible, while admins
     * can turn individual sensitive columns off explicitly.
     */
    public function test_audit_config_defaults_and_visibility_toggles(): void {
        $this->assertTrue(audit_config::show_prompt());
        $this->assertTrue(audit_config::show_response());
        $this->assertTrue(audit_config::show_error());
        $this->assertTrue(audit_config::show_tokens());
        $this->assertFalse(audit_config::anonymize_users());

        set_config('audit_show_prompt', 0, 'local_elediaai_core');
        set_config('audit_show_response', 0, 'local_elediaai_core');
        set_config('audit_show_error', 0, 'local_elediaai_core');
        set_config('audit_show_tokens', 0, 'local_elediaai_core');
        set_config('audit_anonymize_users', 1, 'local_elediaai_core');

        $this->assertFalse(audit_config::show_prompt());
        $this->assertFalse(audit_config::show_response());
        $this->assertFalse(audit_config::show_error());
        $this->assertFalse(audit_config::show_tokens());
        $this->assertTrue(audit_config::anonymize_users());
    }

    /**
     * Parent (core) columns must still be present in the subclass —
     * otherwise the audit report loses provider/action/timecreated etc.
     */
    public function test_core_columns_inherited(): void {
        $entity = new ai_action_audit();
        $ref = new ReflectionClass($entity);
        $method = $ref->getMethod('get_available_columns');
        $method->setAccessible(true);
        $columns = $method->invoke($entity);

        $names = array_map(static fn($c) => $c->get_name(), $columns);

        $this->assertContains('actionname', $names);
        $this->assertContains('provider', $names);
        $this->assertContains('timecreated', $names);
        $this->assertContains('success', $names);
        $this->assertContains('prompttokens', $names);
        $this->assertContains('completiontokens', $names);
    }

    /**
     * Entity name is the class basename — the system report references
     * columns by `ai_action_audit:<column>`, so the name must stay
     * stable.
     */
    public function test_entity_name_is_ai_action_audit(): void {
        $entity = new ai_action_audit();
        $this->assertSame('ai_action_audit', $entity->get_entity_name());
    }

    /**
     * The system report locks itself behind moodle/ai:viewaiusagereport.
     * Admins can construct + view it; non-privileged users get a
     * `report_access_exception` from the factory before render.
     */
    public function test_audit_report_requires_capability(): void {
        $context = \core\context\system::instance();

        // Site admin builds the report fine.
        $this->setAdminUser();
        $report = \core_reportbuilder\system_report_factory::create(audit_report::class, $context);
        $this->assertInstanceOf(audit_report::class, $report);

        // Regular user without the capability can't even construct it.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->expectException(\core_reportbuilder\exception\report_access_exception::class);
        \core_reportbuilder\system_report_factory::create(audit_report::class, $context);
    }

    /**
     * Quick filters are translated into Reportbuilder base conditions.
     * Moodle validates those SQL parameter names strictly, so every
     * allowlisted quick value must be constructible.
     */
    public function test_audit_report_quick_filters_use_valid_reportbuilder_params(): void {
        $this->setAdminUser();
        $context = \core\context\system::instance();

        foreach (['failed', 'participants', 'generate_text', 'summarise_text', 'explain_text', ''] as $quickfilter) {
            $_GET['quick'] = $quickfilter;
            $_REQUEST['quick'] = $quickfilter;
            try {
                $report = \core_reportbuilder\system_report_factory::create(audit_report::class, $context);
            } finally {
                unset($_GET['quick'], $_REQUEST['quick']);
            }
            $this->assertInstanceOf(audit_report::class, $report);
        }
    }

    /**
     * End-to-end: insert a synthetic action row + detail row, build the
     * report, and confirm prompt + response surface in the rendered
     * output. This is the regression net for the COALESCE join pattern.
     */
    public function test_audit_report_renders_prompt_and_response(): void {
        global $DB;
        $this->setAdminUser();

        $detailid = $DB->insert_record('ai_action_generate_text', (object) [
            'prompt' => 'Explain photosynthesis briefly.',
            'responseid' => 'unit-resp-1',
            'fingerprint' => 'unit',
            'generatedcontent' => 'Photosynthesis converts light, CO2 and water into glucose and oxygen.',
            'finishreason' => 'stop',
            'prompttokens' => 20,
            'completiontoken' => 30,
        ]);
        $DB->insert_record('ai_action_register', (object) [
            'actionname' => 'generate_text',
            'actionid' => $detailid,
            'success' => 1,
            'userid' => 2, // admin
            'contextid' => \core\context\system::instance()->id,
            'provider' => 'aiprovider_openai',
            'errorcode' => null,
            'errormessage' => null,
            'timecreated' => time() - 60,
            'timecompleted' => time() - 59,
            'model' => 'gpt-4o-mini',
        ]);

        $report = \core_reportbuilder\system_report_factory::create(
            audit_report::class,
            \core\context\system::instance()
        );
        $html = $report->output();

        $this->assertStringContainsString('Explain photosynthesis briefly.', $html);
        $this->assertStringContainsString('glucose and oxygen', $html);
        $this->assertStringContainsString('gpt-4o-mini', $html);
        $this->assertStringContainsString('lucide-magic', $html);

        // The new presentation layer: eye-icon trigger + ✓ for success +
        // combined "20 / 30" token cell.
        $this->assertStringContainsString('data-action="lh-audit-preview"', $html);
        $this->assertStringContainsString('lh-audit-preview-content', $html);
        $this->assertStringContainsString('lucide-check', $html);
        $this->assertStringContainsString('20 / 30', $html);
    }

    /**
     * The anonymous row survives the whole report, not just the callback.
     *
     * This is the end-to-end check the unit tests above cannot give: the
     * actor column brings its own join and its own name fields, so the
     * question is whether the report still produces valid SQL and whether
     * a row with no person reaches the page at all.
     */
    public function test_an_anonymous_row_reaches_the_report_and_is_labelled(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;

        // Bestandszeilen aus der Zeit, als die Suite selbst in Moodles Register
        // schrieb (05.09. bis 19.09.2026). Sie bleiben dort liegen und muessen
        // weiter lesbar sein -- deshalb wird eine hier von Hand nachgebaut,
        // statt sie ueber audit_recorder zu erzeugen, den es nicht mehr gibt.
        $detailid = $DB->insert_record('ai_action_generate_text', (object) [
            'prompt' => 'Wie funktioniert die Photosynthese?',
            'responseid' => '',
            'fingerprint' => '',
            'generatedcontent' => 'Licht, CO2 und Wasser werden zu Glukose und Sauerstoff.',
            'finishreason' => 'stop',
            'prompttokens' => 12,
            'completiontoken' => 34,
        ]);
        $DB->insert_record('ai_action_register', (object) [
            'actionname' => 'generate_text',
            'actionid' => $detailid,
            'success' => 1,
            'userid' => 0,
            'contextid' => \core\context\system::instance()->id,
            'provider' => 'aiprovider_openai',
            'errorcode' => null,
            'errormessage' => null,
            'timecreated' => time() - 60,
            'timecompleted' => time(),
            'model' => 'gpt-4o-mini',
        ]);

        $report = \core_reportbuilder\system_report_factory::create(
            audit_report::class,
            \core\context\system::instance()
        );
        $html = $report->output();

        // The turn itself is in the audit ...
        $this->assertStringContainsString('Wie funktioniert die Photosynthese?', $html);
        $this->assertStringContainsString('Glukose und Sauerstoff', $html);
        // ... and its actor cell states that nobody was recorded, rather
        // than being empty, which is what core's own column would render.
        $this->assertStringContainsString(
            get_string('audit_actor_not_recorded', 'local_elediaai_core'),
            $html
        );
    }

    /**
     * When privacy-oriented switches are active, the report replaces
     * the real user column and suppresses prompt/response/token output.
     */
    public function test_audit_report_honours_visibility_settings(): void {
        global $DB;
        $this->setAdminUser();
        set_config('audit_anonymize_users', 1, 'local_elediaai_core');
        set_config('audit_show_prompt', 0, 'local_elediaai_core');
        set_config('audit_show_response', 0, 'local_elediaai_core');
        set_config('audit_show_tokens', 0, 'local_elediaai_core');

        $detailid = $DB->insert_record('ai_action_generate_text', (object) [
            'prompt' => 'Hidden prompt.',
            'responseid' => 'unit-resp-2',
            'fingerprint' => 'unit',
            'generatedcontent' => 'Hidden response.',
            'finishreason' => 'stop',
            'prompttokens' => 10,
            'completiontoken' => 5,
        ]);
        $DB->insert_record('ai_action_register', (object) [
            'actionname' => 'generate_text',
            'actionid' => $detailid,
            'success' => 1,
            'userid' => 2, // admin
            'contextid' => \core\context\system::instance()->id,
            'provider' => 'aiprovider_openai',
            'errorcode' => null,
            'errormessage' => null,
            'timecreated' => time() - 60,
            'timecompleted' => time() - 59,
            'model' => 'gpt-4o-mini',
        ]);

        $report = \core_reportbuilder\system_report_factory::create(
            audit_report::class,
            \core\context\system::instance()
        );
        $html = $report->output();

        $this->assertStringContainsString(get_string('audit_col_actor', 'local_elediaai_core'), $html);
        $this->assertStringNotContainsString('Hidden prompt.', $html);
        $this->assertStringNotContainsString('Hidden response.', $html);
        $this->assertStringNotContainsString('10 / 5', $html);
    }

    /**
     * Teacher-own-courses mode limits rows to courses where the viewer teaches.
     */
    public function test_audit_report_teacher_mode_filters_to_own_courses(): void {
        global $DB;

        set_config('audit_access', audit_config::ACCESS_TEACHER_OWN_COURSES, 'local_elediaai_core');

        $teacher = $this->getDataGenerator()->create_user();
        $owncourse = $this->getDataGenerator()->create_course(['fullname' => 'Own audit course']);
        $othercourse = $this->getDataGenerator()->create_course(['fullname' => 'Other audit course']);
        $this->getDataGenerator()->enrol_user((int) $teacher->id, (int) $owncourse->id, 'editingteacher');

        $owndetailid = $DB->insert_record('ai_action_generate_text', (object) [
            'prompt' => 'Visible teacher prompt.',
            'responseid' => 'unit-teacher-own',
            'fingerprint' => 'unit',
            'generatedcontent' => 'Visible teacher response.',
            'finishreason' => 'stop',
            'prompttokens' => 3,
            'completiontoken' => 4,
        ]);
        $otherdetailid = $DB->insert_record('ai_action_generate_text', (object) [
            'prompt' => 'Hidden teacher prompt.',
            'responseid' => 'unit-teacher-other',
            'fingerprint' => 'unit',
            'generatedcontent' => 'Hidden teacher response.',
            'finishreason' => 'stop',
            'prompttokens' => 5,
            'completiontoken' => 6,
        ]);

        $DB->insert_record('ai_action_register', (object) [
            'actionname' => 'generate_text',
            'actionid' => $owndetailid,
            'success' => 1,
            'userid' => (int) $teacher->id,
            'contextid' => \core\context\course::instance((int) $owncourse->id)->id,
            'provider' => 'aiprovider_openai',
            'errorcode' => null,
            'errormessage' => null,
            'timecreated' => time() - 60,
            'timecompleted' => time() - 59,
            'model' => 'gpt-4o-mini',
        ]);
        $DB->insert_record('ai_action_register', (object) [
            'actionname' => 'generate_text',
            'actionid' => $otherdetailid,
            'success' => 1,
            'userid' => (int) $teacher->id,
            'contextid' => \core\context\course::instance((int) $othercourse->id)->id,
            'provider' => 'aiprovider_openai',
            'errorcode' => null,
            'errormessage' => null,
            'timecreated' => time() - 50,
            'timecompleted' => time() - 49,
            'model' => 'gpt-4o-mini',
        ]);

        $this->setUser($teacher);
        $report = \core_reportbuilder\system_report_factory::create(
            audit_report::class,
            \core\context\system::instance()
        );
        $html = $report->output();

        $this->assertStringContainsString('Visible teacher prompt.', $html);
        $this->assertStringNotContainsString('Hidden teacher prompt.', $html);

        $overview = \local_elediaai_core\local\audit_page::overview_data();
        $this->assertSame(1, $overview['total']);
        $this->assertSame(7, $overview['tokens']);
    }
}
