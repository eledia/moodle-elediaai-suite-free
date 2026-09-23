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
 * Reportbuilder entity over the AI action log.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\reportbuilder\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use html_writer;
use lang_string;
use local_elediaai_core\local\action_recorder;
use local_elediaai_core\output\lucide_icon;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Columns and filters for `local_elediaai_core_action`.
 *
 * Keeps the actor, unlike the turn entity: oversight of what an AI *did* has to
 * say on whose behalf it did it.
 */
final class action extends base {
    #[\Override]
    protected function get_default_tables(): array {
        return [action_recorder::TABLE];
    }

    #[\Override]
    protected function get_default_entity_title(): lang_string {
        return new lang_string('action_entity_title', 'local_elediaai_core');
    }

    #[\Override]
    protected function get_available_columns(): array {
        $alias = $this->get_table_alias(action_recorder::TABLE);
        $columns = [];

        $columns[] = (new column(
            'timecreated',
            new lang_string('audit_col_time', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$alias}.timecreated")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        $columns[] = (new column(
            'actor',
            new lang_string('action_col_actor', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.userid")
            ->set_is_sortable(true)
            ->set_callback([self::class, 'format_actor']);

        $columns[] = (new column(
            'iswrite',
            new lang_string('action_col_kind', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("{$alias}.iswrite")
            ->set_is_sortable(true)
            ->set_callback([self::class, 'format_kind']);

        $columns[] = (new column(
            'toolname',
            new lang_string('action_col_action', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.toolname")
            ->set_is_sortable(true)
            ->set_callback([self::class, 'format_tool']);

        $columns[] = (new column(
            'place',
            new lang_string('audit_col_context', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.contextid")
            ->set_is_sortable(false)
            ->set_callback([self::class, 'format_place']);

        $columns[] = (new column(
            'success',
            new lang_string('audit_col_status', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("{$alias}.success")
            ->add_field("{$alias}.errorcode")
            ->set_is_sortable(true)
            ->set_callback([self::class, 'format_success']);

        $columns[] = (new column(
            'durationms',
            new lang_string('action_col_duration', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$alias}.durationms")
            ->set_is_sortable(true)
            ->set_callback([self::class, 'format_duration']);

        return $columns;
    }

    #[\Override]
    protected function get_available_filters(): array {
        $alias = $this->get_table_alias(action_recorder::TABLE);
        $filters = [];

        $filters[] = (new filter(
            date::class,
            'timecreated',
            new lang_string('audit_col_time', 'local_elediaai_core'),
            $this->get_entity_name(),
            "{$alias}.timecreated"
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            text::class,
            'toolname',
            new lang_string('action_col_action', 'local_elediaai_core'),
            $this->get_entity_name(),
            "{$alias}.toolname"
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            boolean_select::class,
            'iswrite',
            new lang_string('action_col_kind', 'local_elediaai_core'),
            $this->get_entity_name(),
            "{$alias}.iswrite"
        ))->add_joins($this->get_joins());

        return $filters;
    }

    /**
     * The person the AI acted for, or an explicit statement that the link is gone.
     *
     * A retention run sets the id to 0 and keeps the row. An empty cell would
     * read as a fault; this says which of the two it is.
     *
     * @param int|string|null $value
     * @return string
     */
    public static function format_actor(int|string|null $value): string {
        global $DB;

        $userid = (int) $value;
        if ($userid <= 0) {
            $label = get_string('action_actor_anonymised', 'local_elediaai_core');
            if (self::is_downloading_now()) {
                return $label;
            }
            return html_writer::span(s($label), 'eai-audit__actor-none', [
                'title' => get_string('action_actor_anonymised_help', 'local_elediaai_core'),
            ]);
        }

        $user = $DB->get_record('user', ['id' => $userid], 'id, ' . implode(', ', \core_user\fields::get_name_fields()));
        if (!$user) {
            return get_string('action_actor_deleted', 'local_elediaai_core');
        }
        $name = fullname($user, has_capability('moodle/site:viewfullnames', \core\context\system::instance()));
        if (self::is_downloading_now()) {
            return $name;
        }
        return html_writer::link(new moodle_url('/user/profile.php', ['id' => $userid]), s($name));
    }

    /**
     * Reads or writes, as a badge.
     *
     * @param mixed $value
     * @return string
     */
    public static function format_kind($value): string {
        $iswrite = (int) $value === 1;
        $label = get_string($iswrite ? 'action_kind_write' : 'action_kind_read', 'local_elediaai_core');
        if (self::is_downloading_now()) {
            return $label;
        }
        return html_writer::span(
            s($label),
            'eai-badge ' . ($iswrite ? 'eai-badge--warn' : 'eai-badge--quiet')
        );
    }

    /**
     * The tool, named in plain language where the plugin offers a string.
     *
     * Falls back to the raw tool name: an agent called it by that name, and a
     * protocol that renames it makes the two records hard to line up.
     *
     * @param string|null $value
     * @return string
     */
    public static function format_tool(?string $value): string {
        $tool = trim((string) $value);
        if ($tool === '') {
            return '—';
        }
        $key = 'tool_' . $tool;
        if (get_string_manager()->string_exists($key, 'webservice_elediamcp')) {
            return get_string($key, 'webservice_elediamcp');
        }
        return $tool;
    }

    /**
     * Where it happened.
     *
     * @param int|string|null $value
     * @return string
     */
    public static function format_place(int|string|null $value): string {
        $contextid = (int) $value;
        if ($contextid <= 0) {
            return get_string('action_place_unknown', 'local_elediaai_core');
        }
        try {
            $context = \core\context::instance_by_id($contextid, IGNORE_MISSING);
        } catch (\Throwable $e) {
            $context = false;
        }
        if (!$context) {
            return get_string('action_place_unknown', 'local_elediaai_core');
        }
        $name = $context->get_context_name(false);
        if (self::is_downloading_now()) {
            return $name;
        }
        $url = method_exists($context, 'get_url') ? $context->get_url() : null;
        return $url instanceof moodle_url ? html_writer::link($url, s($name)) : s($name);
    }

    /**
     * Success as an icon, with the error code beside a failed one.
     *
     * @param mixed $value
     * @param stdClass|null $row
     * @return string
     */
    public static function format_success($value, ?stdClass $row = null): string {
        $isok = (int) $value === 1;
        $label = get_string($isok ? 'audit_success_yes' : 'audit_success_no', 'local_elediaai_core');
        $error = trim((string) ($row->errorcode ?? ''));

        if (self::is_downloading_now()) {
            $suffix = !$isok && $error !== '' ? ': ' . $error : '';
            return ($isok ? get_string('yes') : get_string('no')) . $suffix;
        }

        $span = html_writer::tag(
            'span',
            lucide_icon::render($isok ? 'check' : 'times')
                . html_writer::tag('span', s($label), ['class' => 'sr-only']),
            [
                'class' => 'lh-audit-success ' . ($isok ? 'text-success' : 'text-danger'),
                'title' => $label,
            ]
        );
        if ($isok || $error === '') {
            return $span;
        }
        return $span . html_writer::span(s($error), 'eai-audit__errorcode');
    }

    /**
     * Duration in seconds with one decimal.
     *
     * @param int|string|null $value
     * @return string
     */
    public static function format_duration(int|string|null $value): string {
        $ms = (int) $value;
        if ($ms <= 0) {
            return '—';
        }
        return format_float($ms / 1000, 1) . ' s';
    }

    /**
     * Whether a download is being rendered right now.
     *
     * @return bool
     */
    private static function is_downloading_now(): bool {
        return optional_param('download', '', PARAM_ALPHA) !== '';
    }
}
