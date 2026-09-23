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

namespace local_aitransparency\task;

use context_system;

/**
 * Tests for the retention anonymisation task.
 *
 * @package    local_aitransparency
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aitransparency\task\anonymise_records
 */
final class anonymise_records_test extends \advanced_testcase {
    /**
     * Insert one record with the given user and age.
     *
     * @param int $userid
     * @param int $timecreated
     * @return int record id
     */
    private function make_record(int $userid, int $timecreated): int {
        global $DB;
        return (int) $DB->insert_record('local_aitransparency_rec', (object) [
            'uuid' => \core\uuid::generate(),
            'component' => 'local_elediaai_coursegen',
            'actionname' => 'generate_text',
            'provider' => 'aiprovider_eledia',
            'model' => 'test-model',
            'userid' => $userid,
            'contextid' => context_system::instance()->id,
            'assettype' => 'text',
            'contenthash' => hash('sha256', 'o' . $userid . $timecreated),
            'markstate' => 'pending',
            'timecreated' => $timecreated,
        ]);
    }

    public function test_old_records_anonymised_recent_kept(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('retentiondays', 30, 'local_aitransparency');

        $old = $this->make_record(101, time() - (40 * DAYSECS));
        $recent = $this->make_record(102, time() - (5 * DAYSECS));

        (new anonymise_records())->execute();

        $this->assertEquals(0, (int) $DB->get_field('local_aitransparency_rec', 'userid', ['id' => $old]));
        $this->assertEquals(102, (int) $DB->get_field('local_aitransparency_rec', 'userid', ['id' => $recent]));
    }

    public function test_disabled_retention_keeps_user(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('retentiondays', 0, 'local_aitransparency');

        $old = $this->make_record(103, time() - (400 * DAYSECS));

        (new anonymise_records())->execute();

        $this->assertEquals(103, (int) $DB->get_field('local_aitransparency_rec', 'userid', ['id' => $old]));
    }
}
