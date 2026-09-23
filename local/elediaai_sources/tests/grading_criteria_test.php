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
 * Unit tests for the advanced-grading criteria helper.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\grading_criteria
 */
final class grading_criteria_test extends \advanced_testcase {
    /**
     * No advanced grading configured → empty string.
     */
    public function test_no_grading_returns_empty(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $context = \core\context\module::instance($assign->cmid);

        $this->assertSame('', grading_criteria::html($context->id, 'mod_assign', 'submissions'));
    }

    /**
     * A configured rubric renders its criteria and levels.
     */
    public function test_rubric_criteria_rendered(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $context = \core\context\module::instance($assign->cmid);

        /** @var \gradingform_rubric_generator $rubricgen */
        $rubricgen = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric');
        $rubricgen->create_instance(
            $context,
            'mod_assign',
            'submissions',
            'Essay rubric',
            'Overall assessment of the essay',
            [
                'Structure and argument' => [
                    'Poorly structured' => 0,
                    'Clear and well argued' => 5,
                ],
                'Use of sources' => [
                    'No sources cited' => 0,
                    'Sources well integrated' => 3,
                ],
            ]
        );

        $html = grading_criteria::html($context->id, 'mod_assign', 'submissions');

        $this->assertStringContainsString('Overall assessment of the essay', $html);
        $this->assertStringContainsString('Structure and argument', $html);
        $this->assertStringContainsString('Clear and well argued', $html);
        $this->assertStringContainsString('Use of sources', $html);
        $this->assertStringContainsString('Sources well integrated', $html);
    }

    /**
     * The assign extractor includes the rubric criteria in its content.
     */
    public function test_assign_extractor_includes_rubric(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'intro' => '<p>Write an essay.</p>',
        ]);
        $context = \core\context\module::instance($assign->cmid);

        /** @var \gradingform_rubric_generator $rubricgen */
        $rubricgen = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric');
        $rubricgen->create_instance(
            $context,
            'mod_assign',
            'submissions',
            'Essay rubric',
            'How your essay is graded',
            [
                'Originality' => ['Derivative' => 0, 'Highly original' => 4],
            ]
        );

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($assign->cmid);

        $result = (new \aisourcesextractor_assign\extractor())->extract($cm);

        $this->assertNotNull($result);
        $this->assertStringContainsString('Write an essay.', $result['content']);
        $this->assertStringContainsString('How your essay is graded', $result['content']);
        $this->assertStringContainsString('Originality', $result['content']);
        $this->assertStringContainsString('Highly original', $result['content']);
    }
}
