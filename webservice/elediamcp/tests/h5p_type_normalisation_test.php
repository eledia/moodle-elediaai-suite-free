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

namespace webservice_elediamcp;

use advanced_testcase;
use webservice_elediamcp\local\ai\tools\moodle_generate_h5p;

/**
 * The type of moodle_generate_h5p tolerates the usual spellings (#182).
 *
 * @package     webservice_elediamcp
 * @author      Immanuel Pasanec <immanuel.pasanec@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_elediamcp\local\ai\tools\moodle_generate_h5p
 */
final class h5p_type_normalisation_test extends advanced_testcase {
    /**
     * Spellings that map to a supported type.
     *
     * @return array
     */
    public static function accepted_provider(): array {
        return [
            'exact' => ['dialogcards', 'dialogcards'],
            'words and capitals' => ['Dialog Cards', 'dialogcards'],
            'underscore' => ['dialog_cards', 'dialogcards'],
            'single card' => ['dialogcard', 'dialogcards'],
            'flashcards' => ['Flashcards', 'dialogcards'],
            'fill in the blanks' => ['Fill in the Blanks', 'blanks'],
            'hyphens' => ['fill-in-the-blanks', 'blanks'],
            'blank' => ['blank', 'blanks'],
            'german' => ['Lückentext', 'blanks'],
            'german ascii' => ['lueckentext', 'blanks'],
            'single choice set' => ['Single Choice Set', 'singlechoice'],
            'multiple choice' => ['Multiple Choice', 'multichoice'],
            'padding and capitals' => ['  MULTICHOICE ', 'multichoice'],
        ];
    }

    /**
     * Known spellings give the type.
     *
     * @dataProvider accepted_provider
     * @param string $input What the caller sent.
     * @param string $expected The type.
     */
    public function test_known_spellings_map_to_a_type(string $input, string $expected): void {
        $this->assertSame($expected, moodle_generate_h5p::normalise_type($input));
    }

    /**
     * Anything else stays invalid, so the error still lists the supported types.
     */
    public function test_unknown_types_stay_invalid(): void {
        foreach (['', 'truefalse', 'dialogs', 'multi', 'quiz', 'drag and drop'] as $input) {
            $this->assertNull(moodle_generate_h5p::normalise_type($input), $input);
        }
    }
}
