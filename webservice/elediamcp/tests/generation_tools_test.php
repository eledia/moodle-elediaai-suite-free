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
use required_capability_exception;
use webservice_elediamcp\local\ai\registry;
use webservice_elediamcp\local\ai\tool_exception;
use webservice_elediamcp\local\ai\tools\moodle_generate_h5p;
use webservice_elediamcp\local\ai\tools\moodle_generate_questions;
use webservice_elediamcp\local\tool_provider;

/**
 * Tests for the optional eledia.ai generation tools.
 *
 * The wrapped plugins (local_elediaai_h5pauthor, local_elediaai_questiongen) are not
 * part of this repository, so the tests split into three groups: presence
 * checks that always run, execution tests that run only where the plugins
 * are installed, and guard tests that run only where they are absent.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_elediamcp\local\ai\registry
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_generate_h5p
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_generate_questions
 */
final class generation_tools_test extends advanced_testcase {
    /**
     * Whether local_elediaai_h5pauthor is installed on the test site.
     *
     * @return bool
     */
    private static function h5pauthor_installed(): bool {
        return class_exists('\local_elediaai_h5pauthor\local\authoring_service');
    }

    /**
     * Whether local_elediaai_questiongen is installed on the test site.
     *
     * @return bool
     */
    private static function questiongen_installed(): bool {
        return class_exists('\local_elediaai_questiongen\local\generator');
    }

    /**
     * Whether the core mod_qbank module is available on the test site.
     *
     * The shared question bank activity was introduced in Moodle 5.0; Moodle
     * 4.5 ships no mod_qbank. Scenarios that rely on an existing qbank module
     * cannot be exercised there and must skip.
     *
     * @return bool
     */
    private static function qbank_available(): bool {
        return (bool) get_plugin_directory('mod', 'qbank');
    }

    /**
     * The registry follows the presence of the wrapped plugins and never fatals.
     */
    public function test_registry_generation_tools_follow_plugin_presence(): void {
        $names = registry::names();

        if (self::h5pauthor_installed()) {
            $this->assertContains('moodle_generate_h5p', $names);
            $this->assertSame(moodle_generate_h5p::class, registry::find('moodle_generate_h5p'));
        } else {
            $this->assertNotContains('moodle_generate_h5p', $names);
            $this->assertNull(registry::find('moodle_generate_h5p'));
        }

        if (self::questiongen_installed()) {
            $this->assertContains('moodle_generate_questions', $names);
            $this->assertSame(moodle_generate_questions::class, registry::find('moodle_generate_questions'));
        } else {
            $this->assertNotContains('moodle_generate_questions', $names);
            $this->assertNull(registry::find('moodle_generate_questions'));
        }
    }

    /**
     * The tool classes expose complete static metadata regardless of plugin presence.
     */
    public function test_generation_tool_metadata_complete(): void {
        foreach ([moodle_generate_h5p::class, moodle_generate_questions::class] as $class) {
            $this->assertNotSame('', $class::name());
            $this->assertNotSame('', $class::title());
            $this->assertStringContainsString('SERVER-SIDE', $class::description());

            $input = $class::input_schema();
            $this->assertSame('object', $input['type']);
            $this->assertFalse($input['properties']['confirm']['default']);
            $this->assertContains('course_id', $input['required']);

            $output = $class::output_schema();
            $this->assertContains('requires_confirmation', $output['required']);

            $annotations = $class::annotations();
            $this->assertFalse($annotations['readOnlyHint']);
            $this->assertTrue($annotations['openWorldHint']);
        }
    }

    /**
     * Generation tools are premium: hidden without the premium add-on.
     *
     * @runInSeparateProcess
     */
    public function test_generation_tools_premium_gated_without_addon(): void {
        $this->resetAfterTest(true);

        if (class_exists('\\local_elediaai_tutor_premium\\feature')) {
            $this->markTestSkipped('Premium add-on installed; the without-addon case cannot be asserted.');
        }

        $this->assertNull(tool_provider::find_ai_tool('moodle_create_activity'));
        $this->assertNull(tool_provider::find_ai_tool('moodle_generate_h5p'));
        $this->assertNull(tool_provider::find_ai_tool('moodle_generate_questions'));
        $this->assertContains('moodle_create_activity', tool_provider::premium_ai_tool_names());
    }

    /**
     * With the premium add-on the registered tools are dispatchable.
     *
     * @runInSeparateProcess
     */
    public function test_generation_tools_available_with_premium(): void {
        $this->resetAfterTest(true);

        if (!class_exists('\\local_elediaai_tutor_premium\\feature', false)) {
            require_once(__DIR__ . '/fixtures/local_elediaai_tutor_premium/classes/feature.php');
        }

        $this->assertSame(
            \webservice_elediamcp\local\ai\tools\moodle_create_activity::class,
            tool_provider::find_ai_tool('moodle_create_activity')
        );

        if (self::h5pauthor_installed()) {
            $this->assertSame(moodle_generate_h5p::class, tool_provider::find_ai_tool('moodle_generate_h5p'));
            $this->assertContains('moodle_generate_h5p', tool_provider::premium_ai_tool_names());
        } else {
            $this->assertNull(tool_provider::find_ai_tool('moodle_generate_h5p'));
        }

        if (self::questiongen_installed()) {
            $this->assertSame(
                moodle_generate_questions::class,
                tool_provider::find_ai_tool('moodle_generate_questions')
            );
        } else {
            $this->assertNull(tool_provider::find_ai_tool('moodle_generate_questions'));
        }
    }

    /**
     * Without local_elediaai_h5pauthor the H5P tool fails with a clear business error.
     */
    public function test_generate_h5p_execute_requires_plugin(): void {
        global $USER;
        if (self::h5pauthor_installed()) {
            $this->markTestSkipped('local_elediaai_h5pauthor is installed on this site.');
        }
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $this->expectException(tool_exception::class);
        $this->expectExceptionMessageMatches('/local_elediaai_h5pauthor is not installed/');
        moodle_generate_h5p::execute(['course_id' => 1, 'type' => 'blanks', 'topic' => 'Test'], $USER);
    }

    /**
     * Without local_elediaai_questiongen the question tool fails with a clear business error.
     */
    public function test_generate_questions_execute_requires_plugin(): void {
        global $USER;
        if (self::questiongen_installed()) {
            $this->markTestSkipped('local_elediaai_questiongen is installed on this site.');
        }
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $this->expectException(tool_exception::class);
        $this->expectExceptionMessageMatches('/local_elediaai_questiongen is not installed/');
        moodle_generate_questions::execute(['course_id' => 1, 'mode' => 'topic', 'topic' => 'Test'], $USER);
    }

    /**
     * The H5P tool validates its type argument before doing anything expensive.
     */
    public function test_generate_h5p_invalid_type(): void {
        global $USER;
        if (!self::h5pauthor_installed()) {
            $this->markTestSkipped('local_elediaai_h5pauthor is not installed on this site.');
        }
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $this->expectException(tool_exception::class);
        $this->expectExceptionMessageMatches('/Invalid type/');
        moodle_generate_h5p::execute(['course_id' => 1, 'type' => 'crossword', 'topic' => 'Test'], $USER);
    }

    /**
     * The H5P tool enforces local/elediaai_h5pauthor:use in the course.
     */
    public function test_generate_h5p_requires_capability(): void {
        if (!self::h5pauthor_installed()) {
            $this->markTestSkipped('local_elediaai_h5pauthor is not installed on this site.');
        }
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(required_capability_exception::class);
        moodle_generate_h5p::execute([
            'course_id' => (int) $course->id,
            'type' => 'blanks',
            'topic' => 'Photosynthesis',
        ], $student);
    }

    /**
     * The question tool rejects an empty topic before any AI call.
     */
    public function test_generate_questions_topic_required(): void {
        global $USER;
        if (!self::questiongen_installed()) {
            $this->markTestSkipped('local_elediaai_questiongen is not installed on this site.');
        }
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $this->expectException(tool_exception::class);
        $this->expectExceptionMessageMatches('/topic is required/');
        moodle_generate_questions::execute([
            'course_id' => (int) $course->id,
            'mode' => 'topic',
        ], $USER);
    }

    /**
     * The question tool rejects a source_cmid from a foreign course.
     */
    public function test_generate_questions_foreign_source_cmid(): void {
        global $USER;
        if (!self::questiongen_installed()) {
            $this->markTestSkipped('local_elediaai_questiongen is not installed on this site.');
        }
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $othercourse->id]);

        $this->expectException(tool_exception::class);
        $this->expectExceptionMessageMatches('/not a visible activity in this course/');
        moodle_generate_questions::execute([
            'course_id' => (int) $course->id,
            'mode' => 'coursecontents',
            'source_cmid' => (int) $page->cmid,
        ], $USER);
    }

    /**
     * The create path enforces the questiongen capability even with manageactivities.
     */
    public function test_generate_questions_create_path_requires_questiongen_use(): void {
        global $DB;
        if (!self::questiongen_installed()) {
            $this->markTestSkipped('local_elediaai_questiongen is not installed on this site.');
        }
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        $coursecontext = \core\context\course::instance((int) $course->id);
        assign_capability(
            'local/elediaai_questiongen:use',
            CAP_PROHIBIT,
            (int) $teacherrole->id,
            (int) $coursecontext->id,
            true
        );
        $this->setUser($teacher);

        $this->expectException(required_capability_exception::class);
        moodle_generate_questions::execute([
            'course_id' => (int) $course->id,
            'mode' => 'topic',
            'topic' => 'Photosynthesis',
        ], $teacher);
    }

    /**
     * Existing but unusable banks are rejected instead of silently creating a new one.
     */
    public function test_generate_questions_rejects_unusable_existing_banks(): void {
        global $DB;
        if (!self::questiongen_installed()) {
            $this->markTestSkipped('local_elediaai_questiongen is not installed on this site.');
        }
        if (!self::qbank_available()) {
            $this->markTestSkipped('mod_qbank is not available on this Moodle version (Moodle 5.0+).');
        }
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $bank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        $modcontext = \core\context\module::instance((int) $bank->cmid);
        assign_capability(
            'local/elediaai_questiongen:use',
            CAP_PROHIBIT,
            (int) $teacherrole->id,
            (int) $modcontext->id,
            true
        );
        $this->setUser($teacher);

        try {
            moodle_generate_questions::execute([
                'course_id' => (int) $course->id,
                'mode' => 'topic',
                'topic' => 'Photosynthesis',
            ], $teacher);
            $this->fail('Expected tool_exception when no existing bank is usable.');
        } catch (tool_exception $e) {
            $this->assertStringContainsString('do not have permission to add questions', $e->getMessage());
        }
        // No additional bank may have been created.
        $this->assertCount(1, get_fast_modinfo($course)->get_instances_of('qbank'));
    }
}
