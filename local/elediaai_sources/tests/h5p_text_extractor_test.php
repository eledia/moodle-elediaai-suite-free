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
 * Unit tests for the H5P text extractor utility.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\h5p_text_extractor
 */
final class h5p_text_extractor_test extends \advanced_testcase {
    /**
     * Test extraction from a flat Fill-in-the-Blanks style JSON.
     */
    public function test_flat_fill_in_blanks(): void {
        $json = json_encode([
            'params' => [
                'text' => '<p>The capital of France is *Paris*.</p>',
                'questions' => '<p>Fill in the blank above.</p>',
            ],
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        $this->assertStringContainsString('The capital of France is *Paris*.', $result);
        $this->assertStringContainsString('Fill in the blank above.', $result);
    }

    /**
     * Test extraction from a Multiple Choice style JSON with nested answer arrays.
     */
    public function test_multiple_choice_answers(): void {
        $json = json_encode([
            'params' => [
                'question' => '<p>What is the largest planet in our solar system?</p>',
                'answers' => [
                    ['text' => '<p>Jupiter is the correct answer</p>', 'correct' => true],
                    ['text' => '<p>Saturn is a very large planet too</p>', 'correct' => false],
                    ['text' => '<p>Mars is actually quite small</p>', 'correct' => false],
                ],
            ],
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        $this->assertStringContainsString('What is the largest planet', $result);
        $this->assertStringContainsString('Jupiter is the correct answer', $result);
        $this->assertStringContainsString('Saturn is a very large planet too', $result);
        $this->assertStringContainsString('Mars is actually quite small', $result);
    }

    /**
     * Test deeply nested Course Presentation style JSON.
     */
    public function test_deeply_nested_course_presentation(): void {
        $json = json_encode([
            'params' => [
                'presentation' => [
                    'slides' => [
                        [
                            'elements' => [
                                [
                                    'params' => [
                                        'text' => '<p>Welcome to the introduction slide.</p>',
                                    ],
                                ],
                                [
                                    'params' => [
                                        'text' => '<p>This module covers quantum physics.</p>',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'elements' => [
                                [
                                    'params' => [
                                        'text' => '<p>Slide two discusses wave-particle duality.</p>',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        $this->assertStringContainsString('Welcome to the introduction slide.', $result);
        $this->assertStringContainsString('This module covers quantum physics.', $result);
        $this->assertStringContainsString('Slide two discusses wave-particle duality.', $result);
    }

    /**
     * Test that blocklisted keys are skipped.
     */
    public function test_blocklist_filtering(): void {
        $json = json_encode([
            'params' => [
                'text' => '<p>This educational text should appear.</p>',
                'subContentId' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
                'library' => 'H5P.MultiChoice 1.14',
                'metadata' => [
                    'title' => 'Should not appear from metadata block',
                    'license' => 'CC BY',
                ],
                'behaviour' => [
                    'enableRetry' => true,
                    'enableSolutionsButton' => true,
                ],
            ],
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        $this->assertStringContainsString('This educational text should appear.', $result);
        $this->assertStringNotContainsString('aaaaaaaa-bbbb', $result);
        $this->assertStringNotContainsString('H5P.MultiChoice', $result);
        $this->assertStringNotContainsString('Should not appear from metadata block', $result);
        $this->assertStringNotContainsString('CC BY', $result);
    }

    /**
     * Test that short strings (< 10 chars) are filtered out.
     */
    public function test_short_strings_filtered(): void {
        $json = json_encode([
            'params' => [
                'text' => '<p>This is a meaningful educational sentence.</p>',
                'shortval' => 'tiny',
                'number' => '42',
                'id' => 'abc',
            ],
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        $this->assertStringContainsString('This is a meaningful educational sentence.', $result);
        $this->assertStringNotContainsString('tiny', $result);
        $this->assertStringNotContainsString('42', $result);
    }

    /**
     * Test that URLs and file paths are excluded.
     */
    public function test_urls_and_paths_excluded(): void {
        $json = json_encode([
            'params' => [
                'text' => '<p>This content discusses web technologies in detail.</p>',
                'imagepath' => 'https://example.com/images/photo.jpg',
                'filepath' => '/content/images/diagram.png',
            ],
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        $this->assertStringContainsString('This content discusses web technologies in detail.', $result);
        $this->assertStringNotContainsString('example.com', $result);
        $this->assertStringNotContainsString('/content/images', $result);
    }

    /**
     * Test that HTML tags are stripped from extracted text.
     */
    public function test_html_tags_stripped(): void {
        $json = json_encode([
            'params' => [
                'text' => '<p>Text with <strong>bold</strong> and <em>italic</em> formatting.</p>',
            ],
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        $this->assertStringContainsString('Text with bold and italic formatting.', $result);
        $this->assertStringNotContainsString('<strong>', $result);
        $this->assertStringNotContainsString('<em>', $result);
    }

    /**
     * Test empty and invalid JSON inputs.
     */
    public function test_empty_and_invalid_input(): void {
        $this->assertEquals('', h5p_text_extractor::extract_text_from_json(''));
        $this->assertEquals('', h5p_text_extractor::extract_text_from_json('not-json'));
        $this->assertEquals('', h5p_text_extractor::extract_text_from_json('null'));
        $this->assertEquals('', h5p_text_extractor::extract_text_from_json('{}'));
    }

    /**
     * Test that duplicate text values are deduplicated.
     */
    public function test_deduplication(): void {
        $json = json_encode([
            'params' => [
                'questionA' => '<p>This same question appears twice in the content.</p>',
                'questionB' => '<p>This same question appears twice in the content.</p>',
                'different' => '<p>This is a completely different sentence for variety.</p>',
            ],
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        // Should appear only once despite being in two fields.
        $count = substr_count($result, 'This same question appears twice in the content.');
        $this->assertEquals(1, $count);
        $this->assertStringContainsString('This is a completely different sentence for variety.', $result);
    }

    /**
     * Test Accordion-style H5P content with panels.
     */
    public function test_accordion_panels(): void {
        $json = json_encode([
            'params' => [
                'panels' => [
                    [
                        'title' => 'Introduction to Biology',
                        'content' => [
                            'params' => [
                                'text' => '<p>Biology is the study of living organisms.</p>',
                            ],
                        ],
                    ],
                    [
                        'title' => 'Cell Structure and Function',
                        'content' => [
                            'params' => [
                                'text' => '<p>Cells are the basic unit of life on Earth.</p>',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        $this->assertStringContainsString('Introduction to Biology', $result);
        $this->assertStringContainsString('Biology is the study of living organisms.', $result);
        $this->assertStringContainsString('Cell Structure and Function', $result);
        $this->assertStringContainsString('Cells are the basic unit of life on Earth.', $result);
    }

    /**
     * Multiple choice answers are labelled, marking the correct option.
     */
    public function test_multichoice_labels_correct_answer(): void {
        $json = json_encode([
            'params' => [
                'question' => '<p>What is the chemical symbol for water?</p>',
                'answers' => [
                    ['text' => '<p>H2O</p>', 'correct' => true],
                    ['text' => '<p>CO2</p>', 'correct' => false],
                ],
            ],
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        $this->assertStringContainsString('Question: What is the chemical symbol for water?', $result);
        // Short answers survive (no minimum-length filter on known-good fields)
        // and the correct one is marked.
        $this->assertStringContainsString('Correct answer: H2O', $result);
        $this->assertStringContainsString('Answer: CO2', $result);
    }

    /**
     * True/False questions surface the correct boolean answer.
     */
    public function test_true_false_question(): void {
        $json = json_encode([
            'params' => [
                'question' => '<p>The Earth orbits the Sun.</p>',
                'correct' => 'true',
            ],
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        $this->assertStringContainsString('Question: The Earth orbits the Sun.', $result);
        $this->assertStringContainsString('Correct answer: True', $result);
    }

    /**
     * Drag-text / mark-the-words textField is captured with its *markers*.
     */
    public function test_drag_text_field(): void {
        $json = json_encode([
            'params' => [
                'textField' => 'The mitochondria is the *powerhouse* of the cell.',
            ],
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        $this->assertStringContainsString('Text: The mitochondria is the *powerhouse* of the cell.', $result);
    }

    /**
     * Summary statements mark the first (correct) option in each set.
     */
    public function test_summary_statements(): void {
        $json = json_encode([
            'params' => [
                'summaries' => [
                    ['summary' => [
                        'Photosynthesis converts light into chemical energy.',
                        'Photosynthesis produces only carbon dioxide.',
                    ]],
                ],
            ],
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        $this->assertStringContainsString(
            'Correct statement: Photosynthesis converts light into chemical energy.',
            $result
        );
        $this->assertStringContainsString(
            'Statement: Photosynthesis produces only carbon dioxide.',
            $result
        );
    }

    /**
     * Dialog/flash cards capture both the prompt and the answer.
     */
    public function test_dialog_cards(): void {
        $json = json_encode([
            'params' => [
                'dialogs' => [
                    ['text' => 'What is the capital of Japan?', 'answer' => 'Tokyo'],
                ],
            ],
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        $this->assertStringContainsString('Prompt: What is the capital of Japan?', $result);
        $this->assertStringContainsString('Answer: Tokyo', $result);
    }

    /**
     * Content nested under `action` (Course Presentation, Interactive Video,
     * Branching Scenario, Interactive Book) is now recursed into — previously
     * `action` was blocklisted and this content was lost entirely.
     */
    public function test_action_nested_content_is_extracted(): void {
        $json = json_encode([
            'params' => [
                'presentation' => [
                    'slides' => [
                        [
                            'elements' => [
                                [
                                    'action' => [
                                        'library' => 'H5P.AdvancedText 1.1',
                                        'params' => [
                                            'text' => '<p>Cellular respiration releases energy from glucose.</p>',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        $this->assertStringContainsString('Cellular respiration releases energy from glucose.', $result);
        // The library identifier must not leak as content.
        $this->assertStringNotContainsString('H5P.AdvancedText', $result);
    }

    /**
     * The blocks API returns ordered, de-duplicated, labelled lines.
     */
    public function test_blocks_api(): void {
        $json = json_encode([
            'params' => [
                'question' => '<p>Pick the prime number.</p>',
                'answers' => [
                    ['text' => '<p>7</p>', 'correct' => true],
                    ['text' => '<p>8</p>', 'correct' => false],
                ],
            ],
        ]);

        $blocks = h5p_text_extractor::extract_blocks_from_json($json);

        $this->assertSame([
            'Question: Pick the prime number.',
            'Correct answer: 7',
            'Answer: 8',
        ], $blocks);
    }

    /**
     * Test extraction from JSON without the 'params' wrapper.
     */
    public function test_json_without_params_wrapper(): void {
        $json = json_encode([
            'text' => '<p>Direct content without a params wrapper element.</p>',
            'questions' => '<p>A test question placed at the top level.</p>',
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        $this->assertStringContainsString('Direct content without a params wrapper element.', $result);
        $this->assertStringContainsString('A test question placed at the top level.', $result);
    }
}
