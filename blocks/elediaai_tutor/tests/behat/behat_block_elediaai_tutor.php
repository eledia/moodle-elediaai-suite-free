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

// NOTE: no MOODLE_INTERNAL guard here; Behat may require this file before config.php.

/**
 * Behat page resolvers for the eLeDia.ai Tutor block.
 *
 * @package     block_elediaai_tutor
 * @category    test
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_block_elediaai_tutor extends behat_base {
    /**
     * Resolve plugin page URLs whose ids are only known at runtime.
     *
     * Use as: `I am on the "<identifier>" "block_elediaai_tutor > <type>" page`.
     * Recognised types:
     * - `coursechat` — the course-scoped standalone chat page; the identifier is the
     *   course shortname (resolved to a course id, so scenarios never hard-code ids,
     *   which are not deterministic between Behat scenarios).
     *
     * @param string $type Page type within the plugin.
     * @param string $identifier Disambiguating identifier (course shortname for `coursechat`).
     * @return moodle_url
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        global $DB;

        switch (strtolower($type)) {
            case 'coursechat':
                $courseid = $DB->get_field('course', 'id', ['shortname' => $identifier], MUST_EXIST);
                return new moodle_url('/blocks/elediaai_tutor/view.php', ['courseid' => $courseid]);
            default:
                throw new Exception("Unrecognised block_elediaai_tutor page type '{$type}'.");
        }
    }

    /**
     * Check the rendered width of the open tutor window.
     *
     * Measures the box the browser actually laid out, not the inline style: a
     * token that is set but lost to a stronger rule would pass a style check
     * and still leave the window as narrow as before.
     *
     * @Then /^the tutor window should be "(?P<width_number>\d+)" pixels wide$/
     * @param int $width Expected width in CSS pixels.
     * @return void
     */
    public function the_tutor_window_should_be_pixels_wide(int $width): void {
        $actual = (int) round((float) $this->evaluate_script(
            "return document.querySelector('.elediaai-chat-panel:not([hidden])').getBoundingClientRect().width;"
        ));
        if ($actual !== $width) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "The tutor window is {$actual}px wide, expected {$width}px.",
                $this->getSession()
            );
        }
    }
}
