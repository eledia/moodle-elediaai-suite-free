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

/**
 * The chat bubble's machine-readable AI marking (#52, Art. 50 Abs. 2 KI-VO).
 *
 * Rendered server-side through the same Mustache template the panel renders
 * in the browser, so the attribute and the screen-reader sentence are checked
 * where they are produced.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class message_template_test extends \advanced_testcase {
    /**
     * Render one bubble.
     *
     * @param array $context Template context on top of an assistant turn.
     * @return string HTML.
     */
    private function render(array $context): string {
        global $OUTPUT, $PAGE;
        $PAGE->set_context(\context_system::instance());
        return $OUTPUT->render_from_template('local_elediaai_chatengine/message', $context + [
            'isassistant' => true,
            'prefix' => 'elediaai-chat',
            'html' => '<p>Antwort</p>',
            'aisystem' => 'eLeDia.ai Tutor',
        ]);
    }

    /**
     * A model's answer carries the attribute, the system and the sentence for screen readers.
     */
    public function test_model_answer_is_marked(): void {
        $this->resetAfterTest();
        $html = $this->render(['aigenerated' => true]);

        $this->assertStringContainsString('data-ai-generated="true"', $html);
        $this->assertStringContainsString('data-ai-system="eLeDia.ai Tutor"', $html);
        $this->assertStringContainsString(
            '<span class="visually-hidden">' . get_string('aigenerated_sr', 'local_elediaai_chatengine') . '</span>',
            $html
        );
    }

    /**
     * Questions, errors and simulated answers are no model output and carry nothing.
     */
    public function test_other_turns_are_not_marked(): void {
        $this->resetAfterTest();
        foreach ([['aigenerated' => false], ['isassistant' => false, 'isuser' => true, 'text' => 'Frage']] as $context) {
            $html = $this->render($context);
            $this->assertStringNotContainsString('data-ai-generated', $html);
            $this->assertStringNotContainsString(get_string('aigenerated_sr', 'local_elediaai_chatengine'), $html);
        }
    }
}
