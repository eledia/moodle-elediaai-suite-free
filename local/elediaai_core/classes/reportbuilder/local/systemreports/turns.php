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
 * System report over the suite's own turn log.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\reportbuilder\local\systemreports;

use core_reportbuilder\local\helpers\database as reportbuilder_database;
use core_reportbuilder\system_report;
use local_elediaai_core\local\audit_config;
use local_elediaai_core\local\turn_recorder;
use local_elediaai_core\reportbuilder\local\entities\turn;

defined('MOODLE_INTERNAL') || die();

/**
 * Every AI turn the suite made.
 *
 * Deliberately a second report beside the one over Moodle's `ai_action_register`
 * rather than a union of the two. `local_aitransparency_rec.registerid` is still
 * unset, so nothing reliably links a suite turn to a core register row -- a
 * naive union would count the same action twice, and the number would look
 * plausible while being wrong.
 */
class turns extends system_report {
    #[\Override]
    protected function initialise(): void {
        global $USER;

        $entity = new turn();
        $alias = $entity->get_table_alias(turn_recorder::TABLE);

        $this->set_main_table(turn_recorder::TABLE, $alias);
        $this->add_entity($entity);

        if (audit_config::restrict_to_teacher_courses()) {
            [$sql, $params] = audit_config::teacher_course_scope_sql("{$alias}.contextid", (int) $USER->id);
            $this->add_base_condition_sql($sql, $params);
        }

        $quickfilter = optional_param('suitequick', '', PARAM_ALPHANUMEXT);
        if ($quickfilter === 'chat') {
            $param = reportbuilder_database::generate_param_name();
            $this->add_base_condition_sql("{$alias}.actionname = :{$param}", [$param => 'chat_turn']);
        } else if ($quickfilter === 'generate_text') {
            $param = reportbuilder_database::generate_param_name();
            $this->add_base_condition_sql("{$alias}.actionname = :{$param}", [$param => 'generate_text']);
        } else if ($quickfilter === 'ungrounded') {
            $param = reportbuilder_database::generate_param_name();
            $this->add_base_condition_sql(
                "{$alias}.origin <> :{$param}",
                [$param => turn_recorder::ORIGIN_GROUNDED]
            );
        } else if ($quickfilter === 'failed') {
            $param = reportbuilder_database::generate_param_name();
            $this->add_base_condition_sql("{$alias}.success = :{$param}", [$param => 0]);
        }

        $columns = [
            'turn:timecreated',
            'turn:component',
            'turn:origin',
            'turn:context_link',
            'turn:topic',
        ];
        if (audit_config::show_prompt()) {
            $columns[] = 'turn:prompt';
        }
        if (audit_config::show_response()) {
            $columns[] = 'turn:response';
        }
        $columns[] = 'turn:success';
        $columns[] = 'turn:provider_model';
        if (audit_config::show_tokens()) {
            $columns[] = 'turn:tokens';
        }

        $this->add_columns_from_entities($columns);
        $this->add_filters_from_entities([
            'turn:timecreated',
            'turn:component',
            'turn:origin',
        ]);

        $this->set_initial_sort_column('turn:timecreated', SORT_DESC);
        $this->set_default_per_page(25);
        $this->set_downloadable(true, get_string('turn_report_filename', 'local_elediaai_core'));
    }

    #[\Override]
    protected function can_view(): bool {
        return audit_config::can_view($this->get_context());
    }

    #[\Override]
    public static function get_name(): string {
        return get_string('turn_report_name', 'local_elediaai_core');
    }
}
