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

namespace local_elediaai_core;

use advanced_testcase;
use local_elediaai_core\feature\descriptor;
use local_elediaai_core\feature\registry;

/**
 * Feature switches and who may see a feature.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_core\feature\registry
 */
final class registry_switch_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        registry::reset_cache();
    }

    /**
     * Replace the catalogue with the given features.
     *
     * @param descriptor ...$features
     * @return void
     */
    private function catalogue(descriptor ...$features): void {
        $byid = [];
        foreach ($features as $feature) {
            $byid[$feature->id] = $feature;
        }
        (new \ReflectionProperty(registry::class, 'descriptors'))->setValue(null, $byid);
    }

    /**
     * A feature with the given capability and kind.
     *
     * @param string $id
     * @param string|null $capability
     * @param string $kind
     * @return descriptor
     */
    private function feature(string $id, ?string $capability, string $kind = descriptor::KIND_PAGE): descriptor {
        return new descriptor(
            id: $id,
            component: 'local_elediaai_core',
            name: ucfirst($id),
            description: 'Fixture',
            launchurl: null,
            icon: 'star',
            capability: $capability,
            kind: $kind,
        );
    }

    /**
     * A switched-off feature refuses its entry points.
     */
    public function test_require_enabled_refuses_a_switched_off_feature(): void {
        registry::require_enabled('tutor');

        registry::set_enabled('tutor', false);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('feature_disabled', 'local_elediaai_core'));
        registry::require_enabled('tutor');
    }

    /**
     * The AI marking has no switch, whatever the stored value says.
     */
    public function test_the_ai_marking_cannot_be_switched_off(): void {
        registry::set_enabled('aitransparency', false);

        $this->assertFalse(registry::is_switchable('aitransparency'));
        $this->assertTrue(registry::is_enabled('aitransparency'));
    }

    /**
     * A role without the feature's capability does not see it.
     */
    public function test_feature_is_hidden_without_its_capability(): void {
        $this->catalogue($this->feature('adminonly', 'moodle/site:config'));

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame([], registry::visible());

        $this->setAdminUser();
        $this->assertArrayHasKey('adminonly', registry::visible());
    }

    /**
     * A capability Moodle does not know hides the feature without a notice.
     */
    public function test_unknown_capability_hides_the_feature(): void {
        $this->catalogue($this->feature('ghost', 'local/notinstalled:view'));
        $this->setAdminUser();

        $this->assertSame([], registry::visible());
    }

    /**
     * A course capability counts when any course grants it.
     */
    public function test_in_course_feature_shows_for_a_teacher_of_some_course(): void {
        $this->catalogue($this->feature('coursetool', 'moodle/course:update', descriptor::KIND_IN_COURSE));
        $course = $this->getDataGenerator()->create_course();

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        $this->assertArrayHasKey('coursetool', registry::visible());

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        $this->assertSame([], registry::visible());
    }
}
