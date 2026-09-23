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
 * System report over the AI action log.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\reportbuilder\local\systemreports;

use core_reportbuilder\local\helpers\database as reportbuilder_database;
use core_reportbuilder\system_report;
use local_elediaai_core\local\action_recorder;
use local_elediaai_core\reportbuilder\local\entities\action;

defined('MOODLE_INTERNAL') || die();

/**
 * What the AI did, for whom.
 *
 * Scoped by its own capability at course level, so a teacher can be given the
 * oversight of their own course without being shown the whole site.
 */
class aiactions extends system_report {
    #[\Override]
    protected function initialise(): void {
        $entity = new action();
        $alias = $entity->get_table_alias(action_recorder::TABLE);

        $this->set_main_table(action_recorder::TABLE, $alias);
        $this->add_entity($entity);

        $courseid = (int) ($this->get_parameter('courseid', 0, PARAM_INT));
        if ($courseid > 0) {
            $param = reportbuilder_database::generate_param_name();
            $this->add_base_condition_sql("{$alias}.courseid = :{$param}", [$param => $courseid]);
        }

        $quick = optional_param('actionquick', '', PARAM_ALPHANUMEXT);
        if ($quick === 'write') {
            $param = reportbuilder_database::generate_param_name();
            $this->add_base_condition_sql("{$alias}.iswrite = :{$param}", [$param => 1]);
        } else if ($quick === 'failed') {
            $param = reportbuilder_database::generate_param_name();
            $this->add_base_condition_sql("{$alias}.success = :{$param}", [$param => 0]);
        }

        $this->add_columns_from_entities([
            'action:timecreated',
            'action:actor',
            'action:iswrite',
            'action:toolname',
            'action:place',
            'action:success',
            'action:durationms',
        ]);
        $this->add_filters_from_entities([
            'action:timecreated',
            'action:toolname',
            'action:iswrite',
        ]);

        $this->set_initial_sort_column('action:timecreated', SORT_DESC);
        $this->set_default_per_page(25);
        $this->set_downloadable(true, get_string('action_report_filename', 'local_elediaai_core'));
    }

    #[\Override]
    protected function can_view(): bool {
        return has_capability('local/elediaai_core:viewaiactions', $this->get_context());
    }

    #[\Override]
    public static function get_name(): string {
        return get_string('action_report_name', 'local_elediaai_core');
    }
}
