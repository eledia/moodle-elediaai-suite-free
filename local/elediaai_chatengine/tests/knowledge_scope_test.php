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

use local_elediaai_chatengine\local\knowledge_scope;

/**
 * The knowledge base somebody configured, as it is read and written.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_chatengine\local\knowledge_scope
 */
final class knowledge_scope_test extends \advanced_testcase {
    /**
     * Both kinds come out of one string, sorted and deduplicated.
     */
    public function test_parse_reads_both_kinds(): void {
        $scope = knowledge_scope::parse('cat:34,course:915,cat:12,course:7,cat:12');

        $this->assertSame([12, 34], $scope->categories);
        $this->assertSame([7, 915], $scope->courses);
        $this->assertFalse($scope->is_empty());
    }

    /**
     * Nothing chosen is "no wish", and it round-trips as the empty string.
     */
    public function test_empty_stays_empty(): void {
        $scope = knowledge_scope::parse('');

        $this->assertSame([], $scope->categories);
        $this->assertSame([], $scope->courses);
        $this->assertTrue($scope->is_empty());
        $this->assertSame('', $scope->as_string());
    }

    /**
     * Unusable entries are dropped, not raised.
     *
     * The string can come from an imported profile, a hand-edited setting or a
     * future version that knows a prefix this one does not. None of those may
     * stop a chat, and what is dropped was never usable.
     */
    public function test_rubbish_is_dropped_quietly(): void {
        $scope = knowledge_scope::parse('cat:12,,  ,cat:,cat:abc,course:-3,course:0,group:4,7,course:8');

        $this->assertSame([12], $scope->categories);
        $this->assertSame([8], $scope->courses);
    }

    /**
     * What was read is what is written: categories first, each group ascending.
     */
    public function test_round_trip_is_canonical(): void {
        $raw = 'course:9,cat:5,course:2';

        $once = knowledge_scope::parse($raw)->as_string();
        $twice = knowledge_scope::parse($once)->as_string();

        $this->assertSame('cat:5,course:2,course:9', $once);
        $this->assertSame($once, $twice);
    }

    /**
     * The form hands back option keys, which are the stored entries.
     */
    public function test_from_selection_takes_the_option_keys(): void {
        $scope = knowledge_scope::from_selection(['cat:3', 'course:11']);

        $this->assertSame([3], $scope->categories);
        $this->assertSame([11], $scope->courses);
        $this->assertSame('cat:3,course:11', $scope->as_string());
    }

    /**
     * A teacher may name their own course and nothing else.
     *
     * The rule is "narrow, never widen": the selection is a wish, and a wish
     * that could name a stranger's course would be a way of handing out
     * material. The enrolment check would still catch it at answer time, but
     * a setting that silently means less than it says is its own problem.
     */
    public function test_a_teacher_may_only_name_their_own_courses(): void {
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $mine = $this->getDataGenerator()->create_course();
        $theirs = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user(
            (int) $teacher->id,
            (int) $mine->id,
            'editingteacher'
        );

        $wish = knowledge_scope::parse('course:' . $mine->id . ',course:' . $theirs->id);
        $rejected = knowledge_scope::rejected_for($wish, (int) $teacher->id);

        $this->assertSame(['course:' . $theirs->id], $rejected);
    }

    /**
     * Somebody who administers the site chooses freely.
     */
    public function test_an_administrator_may_name_anything(): void {
        $this->resetAfterTest();
        $admin = get_admin();
        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course();

        $wish = knowledge_scope::parse('cat:' . $category->id . ',course:' . $course->id);

        $this->assertSame([], knowledge_scope::rejected_for($wish, (int) $admin->id));
    }

    /**
     * A course that no longer exists cannot be chosen either.
     */
    public function test_a_vanished_course_is_rejected(): void {
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();

        $wish = knowledge_scope::parse('course:999999');

        $this->assertSame(['course:999999'], knowledge_scope::rejected_for($wish, (int) $teacher->id));
    }

    /**
     * The option list carries both kinds, keyed by what gets stored.
     *
     * The site course is left out: it is never ingested
     * (`course_gate::should_ingest()` excludes it), so it would be an entry
     * that can be chosen and never searched.
     *
     * With a user, because the category half of the list is user-dependent:
     * `make_categories_list()` answers what this person may see. That is the
     * behaviour the forms want, and T-004 builds the rest of the rights check
     * on top of it.
     */
    public function test_options_offer_categories_and_courses(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category(['name' => 'Kaufmännisches']);
        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Arbeitsrecht 2026',
            'shortname' => 'AR26',
            'category' => $category->id,
        ]);

        $options = knowledge_scope::options();

        $this->assertArrayHasKey('cat:' . $category->id, $options);
        $this->assertArrayHasKey('course:' . $course->id, $options);
        $this->assertArrayHasKey(
            'course:' . SITEID,
            $options,
            'the site front page is a course and may be chosen since 20.09.2026'
        );
        $this->assertStringContainsString('Kaufmännisches', $options['cat:' . $category->id]);
        $this->assertStringContainsString('Arbeitsrecht 2026', $options['course:' . $course->id]);
        $this->assertStringContainsString('AR26', $options['course:' . $course->id]);

        // Every key the list offers must survive being stored and read back.
        $scope = knowledge_scope::from_selection(array_keys($options));
        $this->assertContains((int) $category->id, $scope->categories);
        $this->assertContains((int) $course->id, $scope->courses);
    }
}
