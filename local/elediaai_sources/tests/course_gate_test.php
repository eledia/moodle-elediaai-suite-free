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
 * Unit tests for the opt-in course ingestion gate.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\course_gate
 * @covers     \local_elediaai_sources\setup
 */
final class course_gate_test extends \advanced_testcase {
    /**
     * Opt-in: with no categories enabled and no override, nothing is ingested.
     */
    public function test_optin_default_excludes(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->assertFalse(course_gate::should_ingest((int) $course->id));
        // The site course is never ingestable.
        $this->assertFalse(course_gate::should_ingest(SITEID));
    }

    /**
     * A course is eligible when its category — or an ancestor — is allow-listed.
     */
    public function test_category_allow_list(): void {
        $this->resetAfterTest();

        $parent = $this->getDataGenerator()->create_category();
        $child = $this->getDataGenerator()->create_category(['parent' => $parent->id]);
        $other = $this->getDataGenerator()->create_category();

        $incourse = $this->getDataGenerator()->create_course(['category' => $child->id]);
        $outcourse = $this->getDataGenerator()->create_course(['category' => $other->id]);

        // Enable the PARENT only — the child course qualifies via its ancestor.
        set_config('enabledcategories', (string) $parent->id, 'local_elediaai_sources');

        $this->assertTrue(course_gate::should_ingest((int) $incourse->id));
        $this->assertFalse(course_gate::should_ingest((int) $outcourse->id));
    }

    /**
     * With allcategories set, a course qualifies whatever its category —
     * including a category created after the allow-list was last edited.
     *
     * That is the whole point of the setting: the allow-list is a list of ids,
     * so a category added later silently falls outside it and its courses are
     * never ingested, with nothing on screen saying why.
     */
    public function test_all_categories_covers_new_categories(): void {
        $this->resetAfterTest();

        // A category id that exists nowhere. Two reasons: the allow-list must
        // not match, and course_gate caches by "<list>:<courseid>" in a static
        // that survives resetAfterTest() — with ids restarting per test, a
        // realistic list value would collide with a previous test's entry.
        set_config('enabledcategories', '999999', 'local_elediaai_sources');

        // A category that nobody put on the list — the case this setting exists for.
        $later = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $later->id]);

        $this->assertFalse(course_gate::should_ingest((int) $course->id));

        set_config('allcategories', 1, 'local_elediaai_sources');
        $this->assertTrue(course_gate::should_ingest((int) $course->id));
    }

    /**
     * allcategories does not override an explicit per-course Exclude.
     *
     * "Every course" is an admin default, not a veto over the course itself —
     * a teacher who opted their course out must stay out, or the setting would
     * quietly re-include content somebody deliberately withheld.
     */
    public function test_all_categories_respects_exclude_override(): void {
        $this->resetAfterTest();
        setup::ensure_course_field();

        set_config('allcategories', 1, 'local_elediaai_sources');
        set_config('enabledcategories', '', 'local_elediaai_sources');

        $category = $this->getDataGenerator()->create_category();
        $normal = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $excluded = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $this->set_override((int) $excluded->id, course_gate::OVERRIDE_EXCLUDE);

        $this->assertTrue(course_gate::should_ingest((int) $normal->id));
        $this->assertFalse(course_gate::should_ingest((int) $excluded->id));
    }

    /**
     * The per-course override beats the category rule in both directions.
     */
    public function test_override_beats_category(): void {
        $this->resetAfterTest();
        setup::ensure_course_field();

        $enabledcat = $this->getDataGenerator()->create_category();
        $disabledcat = $this->getDataGenerator()->create_category();
        set_config('enabledcategories', (string) $enabledcat->id, 'local_elediaai_sources');

        // In an enabled category but overridden to Exclude → not ingested.
        $excluded = $this->getDataGenerator()->create_course(['category' => $enabledcat->id]);
        $this->set_override((int) $excluded->id, course_gate::OVERRIDE_EXCLUDE);
        $this->assertFalse(course_gate::should_ingest((int) $excluded->id));

        // In a disabled category but overridden to Include → ingested.
        $included = $this->getDataGenerator()->create_course(['category' => $disabledcat->id]);
        $this->set_override((int) $included->id, course_gate::OVERRIDE_INCLUDE);
        $this->assertTrue(course_gate::should_ingest((int) $included->id));
    }

    /**
     * A course named in the central pilot list is ingested regardless of
     * category, but an explicit Exclude override still wins.
     */
    public function test_pilot_course_list(): void {
        $this->resetAfterTest();
        setup::ensure_course_field();

        // No categories enabled (opt-in default).
        set_config('enabledcategories', '', 'local_elediaai_sources');

        $pilot = $this->getDataGenerator()->create_course(['shortname' => 'PILOT-101']);
        $byid = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course(['shortname' => 'NOTPILOT']);

        // New UI stores comma-separated ids.
        set_config('pilotcourses', "{$pilot->id},{$byid->id}", 'local_elediaai_sources');

        $this->assertTrue(course_gate::should_ingest((int) $pilot->id));
        $this->assertTrue(course_gate::should_ingest((int) $byid->id));
        $this->assertFalse(course_gate::should_ingest((int) $other->id));

        // Existing newline-separated shortname/id settings remain supported.
        set_config('pilotcourses', "PILOT-101\n{$byid->id}", 'local_elediaai_sources');
        $this->assertTrue(course_gate::should_ingest((int) $pilot->id));
        $this->assertTrue(course_gate::should_ingest((int) $byid->id));

        // A manager-set Exclude override beats the pilot list.
        $this->set_override((int) $pilot->id, course_gate::OVERRIDE_EXCLUDE);
        $this->assertFalse(course_gate::should_ingest((int) $pilot->id));
    }

    /**
     * While marking is locked, per-course overrides are inert in BOTH
     * directions: Include cannot add a course, Exclude cannot remove one —
     * only the central lists decide. Unlocking restores the overrides.
     */
    public function test_lock_makes_overrides_inert(): void {
        $this->resetAfterTest();
        setup::ensure_course_field();

        $cat = $this->getDataGenerator()->create_category();
        set_config('enabledcategories', '', 'local_elediaai_sources');

        // Course A: Include override, not in any central list.
        $included = $this->getDataGenerator()->create_course();
        $this->set_override((int) $included->id, course_gate::OVERRIDE_INCLUDE);

        // Course B: Exclude override, but named in the pilot list.
        $excluded = $this->getDataGenerator()->create_course(['shortname' => 'PILOT-X']);
        $this->set_override((int) $excluded->id, course_gate::OVERRIDE_EXCLUDE);
        set_config('pilotcourses', 'PILOT-X', 'local_elediaai_sources');

        // Unlocked: overrides win (sanity).
        set_config('lockcoursemarking', 0, 'local_elediaai_sources');
        $this->assertTrue(course_gate::should_ingest((int) $included->id));
        $this->assertFalse(course_gate::should_ingest((int) $excluded->id));

        // Locked: overrides are ignored — only the central lists decide.
        set_config('lockcoursemarking', 1, 'local_elediaai_sources');
        $this->assertFalse(
            course_gate::should_ingest((int) $included->id),
            'Include override must be inert while marking is locked.'
        );
        $this->assertTrue(
            course_gate::should_ingest((int) $excluded->id),
            'Exclude override must be inert while marking is locked.'
        );
    }

    /**
     * Locking the field removes teacher edit access while a capability holder
     * (admin) keeps it, and unlocking restores teacher access.
     */
    public function test_field_lock_blocks_teachers(): void {
        global $DB;
        $this->resetAfterTest();
        setup::ensure_course_field();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $fieldid = (int) $DB->get_field('customfield_field', 'id', ['shortname' => course_gate::FIELD]);
        $handler = \core_course\customfield\course_handler::create();

        // Locked: the editing teacher (no moodle/course:changelockedcustomfields)
        // cannot edit; an admin (has the capability) can.
        setup::apply_field_state(true);
        $field = \core_customfield\field_controller::create($fieldid);
        $this->setUser($teacher);
        $this->assertFalse($handler->can_edit($field, (int) $course->id));
        $this->setAdminUser();
        $this->assertTrue($handler->can_edit($field, (int) $course->id));

        // Unlocked: the editing teacher can edit again.
        setup::apply_field_state(false);
        $field = \core_customfield\field_controller::create($fieldid);
        $this->setUser($teacher);
        $this->assertTrue($handler->can_edit($field, (int) $course->id));
    }

    /**
     * The form hook strips the marking field (and its section header) from the
     * course edit form so it is not settable by ANYONE while locked.
     */
    public function test_strip_course_marking_removes_form_elements(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        setup::ensure_course_field();
        require_once($CFG->libdir . '/formslib.php');

        $categoryid = (int) $DB->get_field(
            'customfield_field',
            'categoryid',
            ['shortname' => course_gate::FIELD]
        );

        $form = new \MoodleQuickForm('testform', 'post', '');
        $form->addElement('header', 'category_' . $categoryid, 'AI tutor');
        $form->addElement(
            'select',
            'customfield_' . course_gate::FIELD,
            'AI Sources',
            [1 => 'Default', 2 => 'Include', 3 => 'Exclude']
        );
        $form->addElement('text', 'fullname', 'Course name');
        $form->setType('fullname', PARAM_TEXT);

        \local_elediaai_sources\hook_callbacks::strip_course_marking($form);

        $this->assertFalse($form->elementExists('customfield_' . course_gate::FIELD));
        $this->assertFalse($form->elementExists('category_' . $categoryid));
        // Unrelated elements are untouched.
        $this->assertTrue($form->elementExists('fullname'));
    }

    /**
     * The install/upgrade setup creates the override field, idempotently.
     */
    public function test_ensure_course_field_idempotent(): void {
        global $DB;
        $this->resetAfterTest();

        setup::ensure_course_field();
        setup::ensure_course_field();

        $this->assertSame(
            1,
            $DB->count_records('customfield_field', ['shortname' => course_gate::FIELD])
        );
    }

    /**
     * Set the per-course override custom field value.
     *
     * @param int $courseid The course id.
     * @param string $option One of the course_gate::OVERRIDE_* option labels.
     */
    private function set_override(int $courseid, string $option): void {
        $handler = \core_course\customfield\course_handler::create();
        foreach ($handler->get_instance_data($courseid, true) as $datum) {
            if ($datum->get_field()->get('shortname') !== course_gate::FIELD) {
                continue;
            }
            // Select fields store the 1-based option index.
            $options = [
                course_gate::OVERRIDE_DEFAULT => 1,
                course_gate::OVERRIDE_INCLUDE => 2,
                course_gate::OVERRIDE_EXCLUDE => 3,
            ];
            $datum->set('intvalue', $options[$option]);
            $datum->set('value', (string) $options[$option]);
            $datum->set('contextid', \core\context\course::instance($courseid)->id);
            $datum->save();
            return;
        }
    }
}
