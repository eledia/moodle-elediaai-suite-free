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

namespace aisourcesextractor_feedback;

use local_elediaai_sources\content_extractor;

/**
 * Content extractor for mod_feedback activities.
 *
 * Extracts the feedback intro and all question/item definitions.
 * Only the survey structure (question text and answer options) is
 * indexed — user responses are never included as they are personal data.
 *
 * @package    aisourcesextractor_feedback
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor implements content_extractor {
    /** @var string[] Item types that carry no educational content. */
    private const SKIP_TYPES = ['pagebreak', 'captcha'];

    /**
     * Check whether this extractor supports the given module.
     *
     * @param \cm_info $cm The course module info.
     * @return bool True if this is a feedback module.
     */
    public function supports(\cm_info $cm): bool {
        return $cm->modname === 'feedback';
    }

    /**
     * Extract content from a feedback activity.
     *
     * Gathers the activity intro plus all item definitions (questions,
     * labels, info items) in position order. Multichoice items include
     * their answer options parsed from the presentation field.
     *
     * @param \cm_info $cm The course module info.
     * @return array|null Extracted document data, or null if no content.
     */
    public function extract(\cm_info $cm): ?array {
        global $DB;

        $feedback = $DB->get_record(
            'feedback',
            ['id' => $cm->instance],
            'id, name, intro, page_after_submit',
            MUST_EXIST,
        );
        $context = \core\context\module::instance($cm->id);

        $html = '';

        // Feedback intro.
        if (!empty($feedback->intro)) {
            $html .= file_rewrite_pluginfile_urls(
                $feedback->intro,
                'pluginfile.php',
                $context->id,
                'mod_feedback',
                'intro',
                0,
            );
        }

        // Get all items for this feedback instance (not template items).
        $items = $DB->get_records(
            'feedback_item',
            ['feedback' => $feedback->id],
            'position ASC',
            'id, name, label, presentation, typ',
        );

        if (!empty($items)) {
            foreach ($items as $item) {
                // Skip structural items.
                if (in_array($item->typ, self::SKIP_TYPES, true)) {
                    continue;
                }

                // Label items are static HTML blocks — output their name directly.
                if ($item->typ === 'label') {
                    if (!empty($item->presentation)) {
                        $html .= format_text(
                            (string) $item->presentation,
                            FORMAT_HTML,
                            ['context' => $context, 'filter' => false, 'noclean' => false]
                        ) . "\n";
                    }
                    continue;
                }

                // Question items.
                if (!empty($item->name)) {
                    $html .= '<h3>' . htmlspecialchars($item->name, ENT_QUOTES, 'UTF-8') . '</h3>' . "\n";
                }

                // For multichoice items, parse answer options from presentation field.
                // The presentation format is: "r>option1|option2|option3" or "c>option1|option2".
                // The prefix indicates radio (r), checkbox (c), or dropdown (d).
                if (
                    in_array($item->typ, ['multichoice', 'multichoicerated'], true)
                    && !empty($item->presentation)
                ) {
                    $options = self::parse_multichoice_options($item->presentation);
                    if (!empty($options)) {
                        $html .= '<ul>' . "\n";
                        foreach ($options as $option) {
                            $html .= '<li>' . htmlspecialchars($option, ENT_QUOTES, 'UTF-8') . '</li>' . "\n";
                        }
                        $html .= '</ul>' . "\n";
                    }
                }
            }
        }

        // Page after submit (thank-you page).
        if (!empty($feedback->page_after_submit)) {
            $html .= file_rewrite_pluginfile_urls(
                $feedback->page_after_submit,
                'pluginfile.php',
                $context->id,
                'mod_feedback',
                'page_after_submit',
                0,
            );
        }

        if (empty($html)) {
            return null;
        }

        return [
            'content' => $html,
            'content_type' => 'text/html',
            'title' => $feedback->name,
        ];
    }

    /**
     * Parse multichoice options from the feedback presentation field.
     *
     * The format is: "type_prefix>option1|option2|option3" where the
     * prefix is r (radio), c (checkbox), or d (dropdown). For rated
     * items, each option may have "####value" appended.
     *
     * @param string $presentation The raw presentation string.
     * @return string[] The parsed option labels.
     */
    private static function parse_multichoice_options(string $presentation): array {
        // Strip the type prefix (e.g., "r>", "c>", "d>").
        $parts = explode('>', $presentation, 2);
        $optionstring = $parts[1] ?? $parts[0];

        $rawoptions = explode('|', $optionstring);
        $options = [];

        foreach ($rawoptions as $raw) {
            // Strip rated value suffix (####value).
            $label = preg_replace('/####.*$/', '', $raw);
            $label = trim($label);
            if ($label !== '') {
                $options[] = $label;
            }
        }

        return $options;
    }
}
