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
 * Backup support for the per-activity ingestion decision.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Carries a teacher's ingestion decision along with the activity.
 *
 * Only the decision travels — not the index state. State describes what a
 * particular site sent to a particular destination; restoring it elsewhere
 * would assert something about an index that never received the content.
 * The decision, by contrast, is the teacher's intent and belongs to the
 * activity.
 *
 * Because duplicating an activity runs through backup and restore, this also
 * makes a duplicate inherit the decision instead of falling back to the site
 * default.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_local_elediaai_sources_plugin extends backup_local_plugin {
    /**
     * Attach the decision to the activity's backup structure.
     *
     * @return backup_plugin_element
     */
    protected function define_module_plugin_structure() {
        $plugin = $this->get_plugin_element();

        $wrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($wrapper);

        // usermodified is deliberately absent: a user id from another site
        // means nothing here, and mapping it buys nothing the decision needs.
        $decision = new backup_nested_element('aisourcesdecision', ['id'], [
            'included',
            'timemodified',
        ]);
        $wrapper->add_child($decision);

        $decision->set_source_table('local_elediaai_sources_cm', [
            'cmid' => backup::VAR_MODID,
        ]);

        return $plugin;
    }
}
