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
 * Audit-Stufe-2 system report.
 *
 * Reuses Moodle's `ai_action_register` table but mounts the LernHive
 * audit entity so the report shows what was actually asked of the LLM
 * and what came back, joined with the per-action detail tables for
 * generate_text / summarise_text / explain_text / generate_image.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\reportbuilder\local\systemreports;

defined('MOODLE_INTERNAL') || die();

use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database as reportbuilder_database;
use core_reportbuilder\system_report;
use local_elediaai_core\local\audit_config;
use local_elediaai_core\local\turn_recorder;
use local_elediaai_core\reportbuilder\local\entities\ai_action_audit;

/**
 * System report for audited Moodle AI actions.
 */
class audit extends system_report {
    #[\Override]
    protected function initialise(): void {
        global $DB, $USER;

        $entitymain = new ai_action_audit();
        $entitymainalias = $entitymain->get_table_alias('ai_action_register');

        $this->set_main_table('ai_action_register', $entitymainalias);
        $this->add_entity($entitymain);

        // Was die Suite selbst auflistet, steht hier nicht noch einmal.
        //
        // Eine Anfrage der Suite laeuft durch core_ai und erzeugt damit auch
        // eine Zeile in Moodles Register -- die beiden Listen ueberschnitten
        // sich, und trennen liess sich das lange nicht, weil das Register
        // keine Komponente fuehrt. Seit der Turn die Nummer seiner
        // Registerzeile mitschreibt, geht es doch: ausgelassen wird, wozu es
        // einen Turn gibt.
        //
        // Grenze, bewusst so: laeuft die Aufbewahrungsfrist von Schicht B ab,
        // faellt der Turn weg und seine alte Registerzeile taucht hier wieder
        // auf. Das ist die ehrlichere Richtung -- die Zeile existiert dann
        // wirklich nur noch hier.
        $turntable = turn_recorder::TABLE;
        $this->add_base_condition_sql(
            "NOT EXISTS (
                SELECT 1
                  FROM {{$turntable}} lhaisuiteturn
                 WHERE lhaisuiteturn.registerid = {$entitymainalias}.id
            )"
        );

        if (audit_config::restrict_to_teacher_courses()) {
            [$sql, $params] = audit_config::teacher_course_scope_sql("{$entitymainalias}.contextid", (int) $USER->id);
            $this->add_base_condition_sql($sql, $params);
        }

        $quickfilter = optional_param('quick', '', PARAM_ALPHANUMEXT);
        if (in_array($quickfilter, ['generate_text', 'summarise_text', 'explain_text', 'generate_image'], true)) {
            $actionparam = reportbuilder_database::generate_param_name();
            $this->add_base_condition_sql("{$entitymainalias}.actionname = :{$actionparam}", [
                $actionparam => $quickfilter,
            ]);
        } else if ($quickfilter === 'failed') {
            $failedparam = reportbuilder_database::generate_param_name();
            $this->add_base_condition_sql("{$entitymainalias}.success = :{$failedparam}", [
                $failedparam => 0,
            ]);
        } else if ($quickfilter === 'participants') {
            $roleparam = reportbuilder_database::generate_param_name();
            $rolecontextpath = $DB->sql_concat('lhaiaudit_rolectx.path', "'/%'");
            $this->add_base_condition_sql(
                "EXISTS (
                    SELECT 1
                      FROM {context} lhaiaudit_ctx
                      JOIN {role_assignments} lhaiaudit_ra ON lhaiaudit_ra.userid = {$entitymainalias}.userid
                      JOIN {context} lhaiaudit_rolectx ON lhaiaudit_rolectx.id = lhaiaudit_ra.contextid
                      JOIN {role} lhaiaudit_r ON lhaiaudit_r.id = lhaiaudit_ra.roleid
                     WHERE lhaiaudit_ctx.id = {$entitymainalias}.contextid
                       AND lhaiaudit_r.archetype = :{$roleparam}
                       AND (
                            lhaiaudit_ctx.id = lhaiaudit_rolectx.id
                            OR lhaiaudit_ctx.path LIKE {$rolecontextpath}
                       )
                )",
                [$roleparam => 'student']
            );
        }

        // Join user for fullnamewithlink + readable provider context.
        $entityuser = new user();
        $entituseralias = $entityuser->get_table_alias('user');
        $this->add_entity($entityuser->add_join(
            "LEFT JOIN {user} {$entituseralias} ON {$entituseralias}.id = {$entitymainalias}.userid"
        ));

        $columns = [
            'ai_action_audit:timecreated',
            'ai_action_audit:action_icon',
            'ai_action_audit:provider_model',
            'ai_action_audit:context_link',
        ];
        array_splice($columns, 1, 0, audit_config::anonymize_users()
            ? ['ai_action_audit:actor_anonymized']
            // Not core's `user:fullnamewithlink`: it renders an empty cell for
            // rows recorded without a person, which reads as a fault. Ours
            // names that case.
            : ['ai_action_audit:actor_named']);
        if (audit_config::show_prompt()) {
            $columns[] = 'ai_action_audit:prompt';
        }
        if (audit_config::show_response()) {
            $columns[] = 'ai_action_audit:generatedcontent';
        }
        $columns[] = 'ai_action_audit:success_icon';
        if (audit_config::show_tokens()) {
            $columns[] = 'ai_action_audit:tokens';
        }

        $this->add_columns_from_entities($columns);

        $filters = [
            'ai_action_audit:timecreated',
            'ai_action_audit:actionname',
            'ai_action_audit:provider',
            'ai_action_audit:success',
        ];
        if (!audit_config::anonymize_users()) {
            $filters[] = 'user:fullname';
        }

        $this->add_filters_from_entities($filters);

        $this->set_initial_sort_column('ai_action_audit:timecreated', SORT_DESC);
        $this->set_default_per_page(25);
        $this->set_downloadable(true, get_string('audit_report_filename', 'local_elediaai_core'));
    }

    #[\Override]
    protected function can_view(): bool {
        return audit_config::can_view($this->get_context());
    }

    #[\Override]
    public static function get_name(): string {
        return get_string('audit_report_name', 'local_elediaai_core');
    }
}
