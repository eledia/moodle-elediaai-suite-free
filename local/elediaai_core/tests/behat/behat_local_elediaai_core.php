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

/**
 * Behat step definitions for local_elediaai_core.
 *
 * @package    local_elediaai_core
 * @category   test
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Site-level helpers for opening AI Suite pages.
 */
class behat_local_elediaai_core extends behat_base {
    /**
     * Open the AI Suite landing page.
     *
     * @Given /^I open the AI Suite$/
     */
    public function i_open_the_ai_suite(): void {
        $this->execute('behat_general::i_visit', ['/local/elediaai_core/index.php']);
    }

    /**
     * Open the AI Suite audit page.
     *
     * @Given /^I open the AI Suite audit$/
     */
    public function i_open_the_ai_suite_audit(): void {
        $this->execute('behat_general::i_visit', ['/local/elediaai_core/audit.php']);
    }
}
