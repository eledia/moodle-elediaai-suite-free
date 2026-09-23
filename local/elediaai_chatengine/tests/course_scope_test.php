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

use local_elediaai_chatengine\adapter\chat_request;
use local_elediaai_chatengine\local\course_scope;

/**
 * Which courses a turn may be answered from.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_chatengine\local\course_scope
 */
final class course_scope_test extends \advanced_testcase {
    /**
     * Mark courses as indexed, where the ingestion plugin is installed.
     *
     * A configured knowledge base is intersected with what has an index --
     * searching a course the corpus does not hold finds nothing, so naming it
     * only lengthens the argument. Without `local_elediaai_sources` there is
     * no index and no intersection, and these calls are simply skipped.
     *
     * @param int[] $courseids The courses to mark.
     * @return void
     */
    private function mark_ingested(array $courseids): void {
        $state = '\local_elediaai_sources\course_state';
        if (!class_exists($state)) {
            return;
        }
        foreach ($courseids as $id) {
            call_user_func([$state, 'set_ingested'], (int) $id, true);
        }
    }

    /**
     * A surface that names its course keeps it, enrolled or not.
     *
     * The placement has already decided this person may be here, and that
     * decision can rest on more than an enrolment: a manager reading a course,
     * a teacher not enrolled in it. Re-deriving it would lock them out of the
     * material they are looking at.
     */
    public function test_named_course_is_kept_without_enrolment(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $scope = course_scope::for_turn((int) $course->id, (int) $user->id);

        $this->assertSame([(int) $course->id], $scope->ids);
        $this->assertSame(course_scope::STATE_NAMED, $scope->state);
        $this->assertSame((string) $course->id, $scope->as_argument());
    }

    /**
     * Without a course the scope is what the person is enrolled in.
     */
    public function test_site_wide_scope_is_the_enrolments(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $one = $this->getDataGenerator()->create_course();
        $two = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_course(); // Not enrolled: must not appear.
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $one->id);
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $two->id);

        $scope = course_scope::for_turn(0, (int) $user->id);

        $expected = [(int) $one->id, (int) $two->id];
        sort($expected);
        $this->assertSame($expected, $scope->ids);
        $this->assertSame(course_scope::STATE_NAMED, $scope->state);
        $this->assertSame(implode(',', $expected), $scope->as_argument());
    }

    /**
     * A suspended enrolment is not a course somebody may be answered from.
     */
    public function test_suspended_enrolment_drops_out(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $active = $this->getDataGenerator()->create_course();
        $suspended = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $active->id);
        $this->getDataGenerator()->enrol_user(
            (int) $user->id,
            (int) $suspended->id,
            null,
            'manual',
            0,
            0,
            ENROL_USER_SUSPENDED
        );

        $scope = course_scope::for_turn(0, (int) $user->id);

        $this->assertSame([(int) $active->id], $scope->ids);
    }

    /**
     * Nobody enrolled anywhere: no course material, and that is an answer.
     *
     * Told apart from the overflow case internally, though both leave the
     * argument out: here there is nothing to name, there too much. The server
     * resolves the enrolments in both cases and lands right in both.
     */
    public function test_no_enrolments_leaves_the_argument_out(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $scope = course_scope::for_turn(0, (int) $user->id);

        $this->assertSame([], $scope->ids);
        $this->assertSame(course_scope::STATE_NONE, $scope->state);
        $this->assertNull($scope->as_argument());
    }

    /**
     * A guest has no enrolments and therefore no course material.
     */
    public function test_guest_has_no_scope(): void {
        $this->resetAfterTest();

        $scope = course_scope::for_turn(0, 0);

        $this->assertSame(course_scope::STATE_NONE, $scope->state);
        $this->assertNull($scope->as_argument());
    }

    /**
     * More courses than the protocol carries: left out, never truncated.
     *
     * Twenty-one enrolments, and the server's own validator stops at twenty.
     * Sending twenty of them would make the other course unfindable with no
     * signal anywhere; omitting the argument hands the question to the server,
     * which resolves the enrolments itself.
     */
    public function test_more_than_the_limit_omits_the_argument(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        for ($i = 0; $i <= course_scope::LIMIT; $i++) {
            $course = $this->getDataGenerator()->create_course();
            $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id);
        }

        $scope = course_scope::for_turn(0, (int) $user->id);

        $this->assertSame(course_scope::STATE_OVERFLOW, $scope->state);
        $this->assertCount(course_scope::LIMIT + 1, $scope->ids);
        // Absent, like the empty case -- but it means "resolve them yourself",
        // not "there is nothing", and the server lands right either way.
        $this->assertNull($scope->as_argument());
    }

    /**
     * A configured knowledge base adds courses the person is enrolled in.
     */
    public function test_configured_scope_adds_enrolled_courses(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $surface = $this->getDataGenerator()->create_course();
        $inbase = $this->getDataGenerator()->create_course();
        $notenrolled = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $inbase->id);
        $this->mark_ingested([(int) $inbase->id, (int) $notenrolled->id]);

        $configured = 'course:' . $inbase->id . ',course:' . $notenrolled->id;
        $scope = course_scope::for_turn((int) $surface->id, (int) $user->id, $configured);

        $expected = [(int) $surface->id, (int) $inbase->id];
        sort($expected);
        $this->assertSame($expected, $scope->ids);
    }

    /**
     * A category stands for its subtree, and the subtree is resolved per turn.
     */
    public function test_a_category_brings_its_courses(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $surface = $this->getDataGenerator()->create_course();
        $parent = $this->getDataGenerator()->create_category();
        $child = $this->getDataGenerator()->create_category(['parent' => $parent->id]);
        $incategory = $this->getDataGenerator()->create_course(['category' => $parent->id]);
        $insubcategory = $this->getDataGenerator()->create_course(['category' => $child->id]);
        foreach ([$incategory, $insubcategory] as $course) {
            $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id);
        }
        $this->mark_ingested([(int) $incategory->id, (int) $insubcategory->id]);

        $scope = course_scope::for_turn((int) $surface->id, (int) $user->id, 'cat:' . $parent->id);

        $this->assertContains((int) $incategory->id, $scope->ids);
        $this->assertContains((int) $insubcategory->id, $scope->ids);
        $this->assertContains((int) $surface->id, $scope->ids);
    }

    /**
     * The surface's own course survives, enrolment or not.
     */
    public function test_the_surfaces_course_is_never_cut_away(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $surface = $this->getDataGenerator()->create_course();
        $elsewhere = $this->getDataGenerator()->create_course();

        $scope = course_scope::for_turn((int) $surface->id, (int) $user->id, 'course:' . $elsewhere->id);

        $this->assertSame([(int) $surface->id], $scope->ids);
    }

    /**
     * Without a course, a selection replaces the enrolments -- it does not add to them.
     *
     * The earlier rule was that a surface without a course ignores the
     * selection altogether. The operator overturned it on 20.09.2026, and
     * rightly: "pass the course context" off plus a knowledge base is how a
     * tutor for a named set of courses is built, and ignoring the selection
     * made that setting dead. What must not happen is the other extreme --
     * the enrolled course the selection did not name creeping back in.
     */
    public function test_a_selection_replaces_the_enrolments_not_adds_to_them(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $enrolled = $this->getDataGenerator()->create_course();
        $chosen = $this->getDataGenerator()->create_course();
        foreach ([$enrolled, $chosen] as $course) {
            $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id);
        }
        $this->mark_ingested([(int) $enrolled->id, (int) $chosen->id]);

        $scope = course_scope::for_turn(0, (int) $user->id, 'course:' . $chosen->id);

        $this->assertSame([(int) $chosen->id], $scope->ids);
        $this->assertNotContains((int) $enrolled->id, $scope->ids);
    }

    /**
     * A big category and few enrolments stay well inside the limit.
     *
     * This is the assumption the cap of twenty rests on: the intersection
     * happens before anything is counted. If this ever fails, the cap is not
     * merely inconvenient -- it is in the wrong place.
     */
    public function test_intersecting_happens_before_counting(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $surface = $this->getDataGenerator()->create_course();
        $category = $this->getDataGenerator()->create_category();
        $enrolled = [];
        for ($i = 0; $i < 30; $i++) {
            $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
            if ($i < 5) {
                $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id);
                $enrolled[] = (int) $course->id;
            }
        }
        $this->mark_ingested($enrolled);

        $scope = course_scope::for_turn((int) $surface->id, (int) $user->id, 'cat:' . $category->id);

        $this->assertSame(course_scope::STATE_NAMED, $scope->state);
        $this->assertCount(6, $scope->ids, 'five enrolled courses plus the surface');
        foreach ($enrolled as $id) {
            $this->assertContains($id, $scope->ids);
        }
        $this->assertNotNull($scope->as_argument());
    }

    /**
     * Over the limit on a course surface, the surface's course is what remains.
     *
     * Leaving the argument out would be worse than useless here: the server
     * reads an absent scope as "all this person's courses", so a knowledge
     * base that grew too large would end up *widening* the search. Falling
     * back to the one course the surface sits in is what it searched before
     * anybody could configure anything.
     */
    public function test_over_the_limit_a_course_surface_keeps_its_own(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $surface = $this->getDataGenerator()->create_course();
        $category = $this->getDataGenerator()->create_category();
        for ($i = 0; $i <= course_scope::LIMIT; $i++) {
            $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
            $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id);
            $this->mark_ingested([(int) $course->id]);
        }

        $scope = course_scope::for_turn((int) $surface->id, (int) $user->id, 'cat:' . $category->id);

        $this->assertSame([(int) $surface->id], $scope->ids);
        $this->assertSame((string) $surface->id, $scope->as_argument());
    }

    /**
     * A chosen course without an index drops out of the list.
     *
     * Not a rule but an economy: the corpus holds nothing for it, so naming it
     * would only make the argument longer -- and the argument has a limit.
     */
    public function test_a_course_without_an_index_drops_out(): void {
        if (!class_exists('\local_elediaai_sources\course_state')) {
            $this->markTestSkipped('Ohne local_elediaai_sources gibt es keinen Index.');
        }
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $surface = $this->getDataGenerator()->create_course();
        $indexed = $this->getDataGenerator()->create_course();
        $notindexed = $this->getDataGenerator()->create_course();
        foreach ([$indexed, $notindexed] as $course) {
            $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id);
        }
        $this->mark_ingested([(int) $indexed->id]);

        $configured = 'course:' . $indexed->id . ',course:' . $notindexed->id;
        $scope = course_scope::for_turn((int) $surface->id, (int) $user->id, $configured);

        $this->assertContains((int) $indexed->id, $scope->ids);
        $this->assertNotContains((int) $notindexed->id, $scope->ids);
    }

    /**
     * The limit is the backend's, so the site can follow it.
     *
     * Hard-coding twenty would have meant a code change on the day a backend
     * raises its own limit -- and the number was never ours to begin with.
     */
    public function test_the_limit_follows_the_setting(): void {
        $this->resetAfterTest();
        set_config('maxscopecourses', 3, 'local_elediaai_chatengine');
        $user = $this->getDataGenerator()->create_user();
        for ($i = 0; $i < 4; $i++) {
            $course = $this->getDataGenerator()->create_course();
            $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id);
        }

        $this->assertSame(3, course_scope::limit());
        $this->assertSame(
            course_scope::STATE_OVERFLOW,
            course_scope::for_turn(0, (int) $user->id)->state,
            'four enrolments are over a limit of three'
        );

        set_config('maxscopecourses', 20, 'local_elediaai_chatengine');
        $this->assertSame(
            course_scope::STATE_NAMED,
            course_scope::for_turn(0, (int) $user->id)->state,
            'and under a limit of twenty they fit again'
        );
    }

    /**
     * Without a course but with a selection, only the selection counts.
     *
     * This is how a tutor for a named set of courses is built: "pass the
     * course context" off, a knowledge base on. Ignoring the selection here
     * would make the setting dead exactly where somebody reached for it.
     */
    public function test_without_a_course_the_selection_still_counts(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $chosen = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        foreach ([$chosen, $other] as $course) {
            $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id);
        }
        $this->mark_ingested([(int) $chosen->id]);

        $scope = course_scope::for_turn(0, (int) $user->id, 'course:' . $chosen->id);

        $this->assertSame([(int) $chosen->id], $scope->ids, 'the other enrolment stays out');
        $this->assertSame(course_scope::STATE_NAMED, $scope->state);
        $this->assertFalse($scope->is_exhausted());
    }

    /**
     * Without a course and without a selection it is still the enrolments.
     */
    public function test_without_a_course_and_without_a_selection(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id);

        $scope = course_scope::for_turn(0, (int) $user->id, '');

        $this->assertSame([(int) $course->id], $scope->ids);
        $this->assertFalse($scope->is_exhausted());
    }

    /**
     * A selection this person may see none of stops the retrieval.
     *
     * The one case the protocol cannot express: an absent argument means "all
     * their courses" to the server, which would widen a setting meant to
     * narrow. So the turn stops being grounded.
     */
    public function test_a_selection_nobody_may_see_is_exhausted(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $elsewhere = $this->getDataGenerator()->create_course();
        $own = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $own->id);
        $this->mark_ingested([(int) $elsewhere->id, (int) $own->id]);

        $scope = course_scope::for_turn(0, (int) $user->id, 'course:' . $elsewhere->id);

        $this->assertSame(course_scope::STATE_EXHAUSTED, $scope->state);
        $this->assertTrue($scope->is_exhausted());
        $this->assertNull($scope->as_argument(), 'and nothing is sent that could be read as "everything"');
        $this->assertNotContains((int) $own->id, $scope->ids, 'certainly not the course they are in');
    }

    /**
     * The site front page is a course and is scoped like one.
     */
    public function test_the_front_page_is_a_course(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $scope = course_scope::for_turn((int) SITEID, (int) $user->id, '');

        $this->assertSame([(int) SITEID], $scope->ids);
        $this->assertSame((string) SITEID, $scope->as_argument());
    }

    /**
     * The front page can be named in a knowledge base and is not filtered away.
     *
     * Nobody is enrolled in the site course, so without the rule in
     * allowed_from() it could be chosen and would never survive the
     * entitlement check -- an entry that silently does nothing.
     */
    public function test_the_front_page_survives_the_entitlement_check(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->mark_ingested([(int) SITEID, (int) $course->id]);

        $scope = course_scope::for_turn((int) $course->id, (int) $user->id, 'course:' . SITEID);

        $this->assertContains((int) SITEID, $scope->ids);
    }

    /**
     * The request asks the scope; without one it answers as it always did.
     */
    public function test_request_falls_back_to_the_single_course(): void {
        $this->resetAfterTest();

        $withscope = new chat_request(
            usermessage: 'x',
            courseid: 7,
            coursescope: course_scope::for_turn(42, 0),
        );
        $this->assertSame('42', $withscope->course_argument());

        $withoutscope = new chat_request(usermessage: 'x', courseid: 7);
        $this->assertSame('7', $withoutscope->course_argument());

        $sitewide = new chat_request(usermessage: 'x');
        $this->assertNull($sitewide->course_argument());
    }
}
