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

namespace webservice_elediamcp;

use advanced_testcase;
use webservice_elediamcp\local\ai\tools\card_summary;
use webservice_elediamcp\local\ai\tools\moodle_create_activity;
use webservice_elediamcp\local\ai\tools\moodle_create_course;
use webservice_elediamcp\local\ai\tools\moodle_create_user;
use webservice_elediamcp\local\ai\tools\moodle_enrol_user;
use webservice_elediamcp\local\ai\tools\moodle_manage_sections;
use webservice_elediamcp\local\ai\tools\moodle_send_message;
use webservice_elediamcp\local\ai\tools\moodle_update_course;

/**
 * Tests for the text of the confirmation cards (`preview.card_summary`, #198).
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_elediamcp\local\ai\tools\card_summary
 */
final class card_summary_test extends advanced_testcase {
    /**
     * The tool classes with a confirm step.
     *
     * @return string[]
     */
    private static function confirming_tools(): array {
        $classes = [];
        foreach (glob(__DIR__ . '/../classes/local/ai/tools/moodle_*.php') as $file) {
            $class = 'webservice_elediamcp\\local\\ai\\tools\\' . basename($file, '.php');
            $input = $class::input_schema();
            if (isset(((array) ($input['properties'] ?? []))['confirm'])) {
                $classes[] = $class;
            }
        }
        return $classes;
    }

    /**
     * Every tool with a `confirm` argument declares `preview.card_summary` in its output.
     */
    public function test_every_confirming_tool_declares_the_card_summary(): void {
        $classes = self::confirming_tools();
        $this->assertGreaterThanOrEqual(15, count($classes), 'The tools with a confirm step were found.');
        foreach ($classes as $class) {
            $output = $class::output_schema();
            $this->assertArrayHasKey('preview', $output['properties'], $class);
            $this->assertEquals(
                card_summary::schema(),
                $output['properties']['preview']['properties']['card_summary'] ?? null,
                $class
            );
        }
    }

    /**
     * Every card string exists in English and German with the same placeholders.
     */
    public function test_card_strings_exist_in_both_languages(): void {
        $string = [];
        require(__DIR__ . '/../lang/en/webservice_elediamcp.php');
        $en = array_filter($string, static fn($key) => str_starts_with($key, 'cardsummary_'), ARRAY_FILTER_USE_KEY);
        $string = [];
        require(__DIR__ . '/../lang/de/webservice_elediamcp.php');
        $de = array_filter($string, static fn($key) => str_starts_with($key, 'cardsummary_'), ARRAY_FILTER_USE_KEY);

        $this->assertNotEmpty($en);
        $this->assertSame(array_keys($en), array_keys($de));
        $placeholders = static function (string $text): array {
            preg_match_all('/\{\$a->(\w+)\}/', $text, $matches);
            $found = array_unique($matches[1]);
            sort($found);
            return $found;
        };
        foreach ($en as $key => $text) {
            $this->assertSame($placeholders($text), $placeholders($de[$key]), $key);
        }
    }

    /**
     * Course tools: the card names the course, the category and the number of changes.
     */
    public function test_course_cards(): void {
        global $USER;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category(['name' => 'Fortbildung']);

        $preview = moodle_create_course::execute([
            'fullname' => 'Kurs Neu',
            'shortname' => 'KN198',
            'category_id' => (int) $category->id,
        ], $USER);
        $this->assertSame(
            'The course "Kurs Neu" will be created in the category "Fortbildung".',
            $preview['preview']['card_summary']
        );

        $course = $this->getDataGenerator()->create_course(['fullname' => 'Kurs Alt']);
        $preview = moodle_update_course::execute([
            'course_id' => (int) $course->id,
            'fullname' => 'Kurs Geaendert',
            'visible' => false,
        ], $USER);
        $this->assertTrue($preview['requires_confirmation']);
        $this->assertSame(
            'In the course "Kurs Alt" 2 detail(s) will be changed.',
            $preview['preview']['card_summary']
        );
    }

    /**
     * The activity, user and enrolment cards.
     */
    public function test_activity_user_and_enrolment_cards(): void {
        global $USER;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Kurs A', 'numsections' => 2]);

        $preview = moodle_create_activity::execute([
            'course_id' => (int) $course->id,
            'type' => 'page',
            'name' => 'Seite eins',
            'content' => '<p>Text</p>',
            'section' => 1,
        ], $USER);
        $this->assertSame(
            get_string('modulename', 'mod_page') . ' "Seite eins" will be created in the course "Kurs A", section "'
                . $preview['preview']['section_name'] . '".',
            $preview['preview']['card_summary']
        );

        $preview = moodle_create_user::execute([
            'username' => 'card.user',
            'firstname' => 'Karla',
            'lastname' => 'Karte',
            'email' => 'card.user@example.test',
        ], $USER);
        $this->assertSame(
            'The account "card.user" for Karla Karte will be created.',
            $preview['preview']['card_summary']
        );

        $student = $this->getDataGenerator()->create_user(['firstname' => 'Stefan', 'lastname' => 'Student']);
        $preview = moodle_enrol_user::execute([
            'user_id' => (int) $student->id,
            'course_id' => (int) $course->id,
            'role_shortname' => 'student',
        ], $USER);
        $this->assertSame(
            'Stefan Student will be enrolled as Student in the course "Kurs A".',
            $preview['preview']['card_summary']
        );
    }

    /**
     * Section management: one sentence for each action, never the English detail.
     */
    public function test_section_cards(): void {
        global $USER;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Kurs B', 'numsections' => 2]);
        $base = ['course_id' => (int) $course->id];

        $create = moodle_manage_sections::execute($base + ['action' => 'create'], $USER);
        $this->assertSame('A new section will be added to the course "Kurs B".', $create['preview']['card_summary']);

        $rename = moodle_manage_sections::execute(
            $base + ['action' => 'rename', 'section' => 1, 'name' => 'Woche 1'],
            $USER
        );
        $this->assertSame(
            'In the course "Kurs B" the section "' . get_section_name($course, 1) . '" will be renamed to "Woche 1".',
            $rename['preview']['card_summary']
        );

        $visibility = $base + ['action' => 'set_visibility', 'section' => 2];
        $hide = moodle_manage_sections::execute($visibility + ['visible' => false], $USER);
        $this->assertSame(
            'In the course "Kurs B" the section "' . get_section_name($course, 2) . '" will be hidden.',
            $hide['preview']['card_summary']
        );

        $show = moodle_manage_sections::execute($visibility + ['visible' => true], $USER);
        $this->assertSame(
            'In the course "Kurs B" the section "' . get_section_name($course, 2) . '" will be shown.',
            $show['preview']['card_summary']
        );
        $this->assertNotSame($hide['preview']['detail'], $hide['preview']['card_summary']);
    }

    /**
     * A message to one person: the card names the recipient, not the message.
     */
    public function test_send_message_card(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $sender = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $recipient = $this->getDataGenerator()->create_and_enrol(
            $course,
            'student',
            ['firstname' => 'Rita', 'lastname' => 'Empfang']
        );
        $this->setUser($sender);

        $preview = moodle_send_message::execute(['to_user_id' => (int) $recipient->id, 'message' => 'Hallo'], $sender);
        $this->assertTrue($preview['requires_confirmation']);
        $this->assertSame('A message will be sent to Rita Empfang.', $preview['preview']['card_summary']);

        $sent = moodle_send_message::execute(
            ['to_user_id' => (int) $recipient->id, 'message' => 'Hallo', 'confirm' => true],
            $sender
        );
        $this->assertArrayNotHasKey('preview', $sent, 'Only the preview carries the card text.');
    }
}
