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
 * Unit tests for the workshop content extractor.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aisourcesextractor_workshop\extractor
 */
final class extractor_workshop_test extends \advanced_testcase {
    /**
     * Instructions and the configured grading-strategy dimensions are extracted.
     */
    public function test_extract_includes_instructions_and_criteria(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $workshop = $this->getDataGenerator()->create_module('workshop', [
            'course' => $course->id,
            'name' => 'Peer Review',
            'strategy' => 'accumulative',
        ]);

        // Add two accumulative assessment dimensions.
        foreach (['Clarity of the argument', 'Quality of evidence'] as $i => $desc) {
            $DB->insert_record('workshopform_accumulative', (object) [
                'workshopid' => $workshop->id,
                'sort' => $i + 1,
                'description' => '<p>' . $desc . '</p>',
                'descriptionformat' => FORMAT_HTML,
                'grade' => 10,
                'weight' => 1,
            ]);
        }

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($workshop->cmid);

        $result = (new \aisourcesextractor_workshop\extractor())->extract($cm);

        $this->assertNotNull($result);
        // The grading-strategy dimensions are surfaced under a criteria heading.
        $this->assertStringContainsString('Grading criteria', $result['content']);
        $this->assertStringContainsString('Clarity of the argument', $result['content']);
        $this->assertStringContainsString('Quality of evidence', $result['content']);
    }

    /**
     * A workshop with no grading dimensions has no criteria section (but still
     * extracts its instructions).
     */
    public function test_no_criteria_section_without_dimensions(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $workshop = $this->getDataGenerator()->create_module('workshop', [
            'course' => $course->id,
            'strategy' => 'accumulative',
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($workshop->cmid);

        $result = (new \aisourcesextractor_workshop\extractor())->extract($cm);

        $this->assertNotNull($result);
        $this->assertStringNotContainsString('Grading criteria', $result['content']);
    }
}
