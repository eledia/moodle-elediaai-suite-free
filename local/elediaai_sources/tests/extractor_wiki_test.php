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
 * Unit tests for the wiki content extractor.
 *
 * The index is searched course-wide, so only sub-wikis every learner of the
 * course may read are sent (#100).
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aisourcesextractor_wiki\extractor
 */
final class extractor_wiki_test extends \advanced_testcase {
    /**
     * A wiki with one page per given sub-wiki owner.
     *
     * @param \stdClass $course The course.
     * @param string $wikimode collaborative or individual.
     * @param int $groupmode The module's group mode.
     * @param array $pages Page text => [groupid, userid].
     * @return \cm_info
     */
    private function wiki(\stdClass $course, string $wikimode, int $groupmode, array $pages): \cm_info {
        global $DB;

        $wiki = $this->getDataGenerator()->create_module('wiki', [
            'course' => $course->id,
            'name' => 'Wiki',
            'wikimode' => $wikimode,
            'groupmode' => $groupmode,
        ]);
        /** @var \mod_wiki_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_wiki');
        foreach ($pages as $text => [$groupid, $userid]) {
            $page = $generator->create_page($wiki, [
                'title' => 'Seite ' . $text,
                'content' => $text,
                'group' => $groupid,
                'userid' => $userid,
            ]);
            // The extractor reads what Moodle renders on view; set it here.
            $DB->set_field('wiki_pages', 'cachedcontent', '<p>' . $text . '</p>', ['id' => $page->id]);
        }

        return get_fast_modinfo($course->id)->get_cm($wiki->cmid);
    }

    /**
     * The extracted text, or '' when nothing is sent.
     *
     * @param \cm_info $cm The wiki.
     * @return string
     */
    private function extracted(\cm_info $cm): string {
        $result = (new \aisourcesextractor_wiki\extractor())->extract($cm);
        return $result === null ? '' : (string) $result['content'];
    }

    /**
     * A shared wiki without groups is sent as before.
     */
    public function test_collaborative_wiki_is_sent(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $cm = $this->wiki($course, 'collaborative', NOGROUPS, ['Gemeinsam' => [0, 0]]);
        $this->assertStringContainsString('Gemeinsam', $this->extracted($cm));
    }

    /**
     * Personal wikis are for their owner and the teachers only; nothing of them is sent.
     */
    public function test_individual_wiki_is_not_sent(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $alice = $this->getDataGenerator()->create_user();
        $bob = $this->getDataGenerator()->create_user();

        $cm = $this->wiki($course, 'individual', NOGROUPS, [
            'Notiz von Alice' => [0, (int) $alice->id],
            'Notiz von Bob' => [0, (int) $bob->id],
        ]);
        $content = $this->extracted($cm);
        $this->assertStringNotContainsString('Notiz von Alice', $content);
        $this->assertStringNotContainsString('Notiz von Bob', $content);
    }

    /**
     * Under separate groups only the course-wide sub-wiki is sent; under visible groups all are.
     */
    public function test_group_wikis_follow_the_group_mode(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $separate = $this->extracted($this->wiki($course, 'collaborative', SEPARATEGROUPS, [
            'Fuer alle' => [0, 0],
            'Nur Gruppe A' => [(int) $group->id, 0],
        ]));
        $this->assertStringContainsString('Fuer alle', $separate);
        $this->assertStringNotContainsString('Nur Gruppe A', $separate);

        $visible = $this->extracted($this->wiki($course, 'collaborative', VISIBLEGROUPS, [
            'Gruppe A sichtbar' => [(int) $group->id, 0],
        ]));
        $this->assertStringContainsString('Gruppe A sichtbar', $visible);
    }
}
