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
 * Audit-extended `ai_action_register` entity.
 *
 * Extends Moodle's core entity with the prompt + generated-content
 * columns that the audit-stage-2 report needs, plus a few UX-focused
 * presentation columns (eye-icon preview for long text, ✓/✗ for
 * success, combined token count). The full text stays in the database;
 * the table renders an icon that opens a modal client-side.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\reportbuilder\local\entities;

defined('MOODLE_INTERNAL') || die();

use core\context\system as context_system;
use core_ai\reportbuilder\local\entities\ai_action_register as core_ai_action_register;
use core_reportbuilder\local\entities\user as user_entity;
use core_reportbuilder\local\report\column;
use core_text;
use core_user\fields;
use html_writer;
use lang_string;
use local_elediaai_core\local\audit_config;
use local_elediaai_core\local\quota_manager;
use local_elediaai_core\output\lucide_icon;
use moodle_url;
use stdClass;

/**
 * Same joins, same filters as the core entity, plus a handful of
 * presentation-focused columns:
 *   - `prompt` / `generatedcontent`: full content rendered as eye-icon
 *     button; the AMD module `audit_preview` opens a modal on click.
 *   - `action_icon`: compact icon representation of the AI action.
 *   - `context_link`: linked context name where Moodle can derive a URL.
 *   - `provider_display`: compact provider label for the report table.
 *   - `success_icon`: ✓ green / ✗ red; failed rows open the error text.
 *   - `tokens`: combined "prompt / completion" cell.
 *
 * The per-action detail tables (`ai_action_generate_text`,
 * `ai_action_summarise_text`, `ai_action_explain_text`) carry the
 * prompt/response text; we COALESCE across all three so a single
 * column works for every action type.
 */
final class ai_action_audit extends core_ai_action_register {
    /**
     * Extend the core audit entity with suite-specific columns.
     *
     * @return column[]
     */
    protected function get_available_columns(): array {
        global $DB;

        $columns = parent::get_available_columns();

        $mainalias = $this->get_table_alias('ai_action_register');
        $generatetextalias = 'aagtaudit';
        $summarisetextalias = 'aastaudit';
        $explaintextalias = 'aaetaudit';
        $imagealias = 'aaigaudit';
        $hasimagedetail = $DB->get_manager()->table_exists('ai_action_generate_image');

        $joins = [
            "LEFT JOIN {ai_action_generate_text} {$generatetextalias}
                       ON {$mainalias}.actionid = {$generatetextalias}.id
                      AND {$mainalias}.actionname = 'generate_text'",
            "LEFT JOIN {ai_action_summarise_text} {$summarisetextalias}
                       ON {$mainalias}.actionid = {$summarisetextalias}.id
                      AND {$mainalias}.actionname = 'summarise_text'",
            "LEFT JOIN {ai_action_explain_text} {$explaintextalias}
                       ON {$mainalias}.actionid = {$explaintextalias}.id
                      AND {$mainalias}.actionname = 'explain_text'",
        ];
        if ($hasimagedetail) {
            $joins[] = "LEFT JOIN {ai_action_generate_image} {$imagealias}
                       ON {$mainalias}.actionid = {$imagealias}.id
                      AND {$mainalias}.actionname = 'generate_image'";
        }
        $imageprompt = $hasimagedetail ? "{$imagealias}.prompt" : 'NULL';
        $imagerevisedprompt = $hasimagedetail ? "{$imagealias}.revisedprompt" : 'NULL';
        $imagequality = $hasimagedetail ? "{$imagealias}.quality" : 'NULL';
        $imagenumimages = $hasimagedetail ? "{$imagealias}.numberimages" : 'NULL';

        // Actor — anonymised replacement for user fullname when enabled.
        $columns[] = (new column(
            'actor_anonymized',
            new lang_string('audit_col_actor', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$mainalias}.userid", 'audit_userid')
            ->add_field("{$mainalias}.contextid", 'audit_contextid_for_actor')
            ->set_is_sortable(false)
            ->set_callback([self::class, 'format_anonymized_actor']);

        // Actor — the person, or an explicit statement that none was recorded.
        //
        // Core's `user:fullnamewithlink` renders an *empty* cell when the LEFT
        // JOIN finds nobody (see its callback: all-NULL row -> ''). That is
        // precisely the case for rows written without a person -- chat turns
        // carry userid 0 by design. An empty cell reads as a fault, not as a
        // decision, and a privacy review cannot tell "we don't know who" from
        // "we chose not to ask". This column says which of the two it is.
        $useralias = 'lhaiaudituser';
        $nameselect = user_entity::get_name_fields_select($useralias);
        $columns[] = (new column(
            'actor_named',
            new lang_string('audit_col_actor', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->add_join("LEFT JOIN {user} {$useralias} ON {$useralias}.id = {$mainalias}.userid")
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$mainalias}.userid", 'audit_actor_userid')
            ->add_fields($nameselect)
            ->set_is_sortable(true, explode(', ', $nameselect))
            ->set_callback([self::class, 'format_named_actor']);

        // Action — compact icon with the original action name in a tooltip.
        $columns[] = (new column(
            'action_icon',
            new lang_string('audit_col_action', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$mainalias}.actionname", 'audit_actionname')
            ->set_is_sortable(true)
            ->set_callback([self::class, 'format_action_icon']);

        // Context — linked content/course name where Moodle exposes a URL.
        $columns[] = (new column(
            'context_link',
            new lang_string('audit_col_context', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$mainalias}.contextid", 'audit_contextid')
            ->set_is_sortable(false)
            ->set_callback([self::class, 'format_context_link']);

        // Prompt — icon preview that opens the full text in a modal.
        $columns[] = (new column(
            'prompt',
            new lang_string('audit_col_prompt', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->add_joins($joins)
            ->set_type(column::TYPE_LONGTEXT)
            ->add_field("COALESCE({$generatetextalias}.prompt, {$summarisetextalias}.prompt, {$explaintextalias}.prompt, {$imageprompt})", 'prompt')
            ->set_is_sortable(false)
            ->set_callback([self::class, 'format_preview_cell'], 'audit_preview_title_prompt');

        // Response — icon preview.
        $columns[] = (new column(
            'generatedcontent',
            new lang_string('audit_col_response', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->add_joins($joins)
            ->set_type(column::TYPE_LONGTEXT)
            ->add_field(
                "COALESCE({$generatetextalias}.generatedcontent, {$summarisetextalias}.generatedcontent, {$explaintextalias}.generatedcontent, {$imagerevisedprompt})",
                'generatedcontent'
            )
            ->set_is_sortable(false)
            ->set_callback([self::class, 'format_preview_cell'], 'audit_preview_title_response');

        // Provider/model — short provider label plus model in one cell so the
        // audit table stays readable in the standard plugin shell.
        $columns[] = (new column(
            'provider_model',
            new lang_string('audit_col_provider_model', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$mainalias}.provider", 'audit_provider')
            ->add_field("{$mainalias}.model", 'audit_model')
            ->set_is_sortable(true)
            ->set_callback([self::class, 'format_provider_model']);

        // Model — the per-action register row already stores it.
        $columns[] = (new column(
            'model',
            new lang_string('audit_col_model', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$mainalias}.model")
            ->set_is_sortable(true);

        // Status — ✓ / ✗ icon for visual scan; failed rows keep the
        // error message behind a compact modal trigger. "Yes/No" on download.
        $columns[] = (new column(
            'success_icon',
            new lang_string('audit_col_status', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("{$mainalias}.success", 'successflag')
            ->add_field("{$mainalias}.errormessage", 'audit_errormessage')
            ->set_is_sortable(true)
            ->set_callback([self::class, 'format_success_icon']);

        // Tokens — single cell with "prompt / completion" pair. The
        // detail tables carry the actual counts (the main register
        // table has no token columns), and core_ai stores completion
        // as `completiontoken` (singular) — reuse the same COALESCE
        // shape as the core entity's individual token columns.
        $columns[] = (new column(
            'tokens',
            new lang_string('audit_col_tokens', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->add_joins($joins)
            ->set_type(column::TYPE_TEXT)
            ->add_field(
                "COALESCE({$generatetextalias}.prompttokens, {$summarisetextalias}.prompttokens, {$explaintextalias}.prompttokens)",
                'tokens_prompt'
            )
            ->add_field(
                "COALESCE({$generatetextalias}.completiontoken, {$summarisetextalias}.completiontoken, {$explaintextalias}.completiontoken)",
                'tokens_completion'
            )
            ->add_field("{$mainalias}.actionname", 'audit_actionname_for_cost')
            ->add_field("{$imagequality}", 'audit_image_quality')
            ->add_field("{$imagenumimages}", 'audit_image_numimages')
            ->set_is_sortable(false)
            ->set_callback([self::class, 'format_tokens_cell']);

        return $columns;
    }

    /**
     * Callback for the provider column.
     *
     * @param string|null $value Raw provider component.
     * @return string
     */
    public static function format_provider_name(?string $value, ?stdClass $row = null): string {
        $provider = $value !== null ? trim($value) : '';
        if ($provider === '') {
            return '—';
        }

        $explicit = [
            'aiprovider_openai' => 'OpenAI',
        ];
        if (isset($explicit[$provider])) {
            return $explicit[$provider];
        }

        $label = $provider;
        if (str_starts_with($provider, 'aiprovider_')) {
            $label = substr($provider, strlen('aiprovider_'));
            $label = str_replace('_', ' ', $label);
            $label = core_text::strtotitle($label);
        } else {
            $manager = get_string_manager();
            if ($manager->string_exists('pluginname', $provider)) {
                $label = get_string('pluginname', $provider);
            }
        }

        $label = preg_replace('/\s+API\s+provider$/i', '', $label);
        $label = preg_replace('/\s+provider$/i', '', $label);

        return s(trim((string) $label));
    }

    /**
     * Callback for the compact provider/model column.
     *
     * @param string|null $value Raw provider component.
     * @param stdClass|null $row All column fields for this row.
     * @return string
     */
    public static function format_provider_model(?string $value, ?stdClass $row = null): string {
        $provider = self::format_provider_name($value, $row);
        $model = trim((string) ($row->audit_model ?? ''));

        if (self::is_downloading_now()) {
            return $model !== '' ? $provider . ' / ' . $model : $provider;
        }

        if ($model === '') {
            return html_writer::tag('span', $provider, ['class' => 'lh-audit-provider-model__provider']);
        }

        return html_writer::span(
            html_writer::span($provider, 'lh-audit-provider-model__provider')
                . html_writer::span(s($model), 'lh-audit-provider-model__model'),
            'lh-audit-provider-model'
        );
    }

    /**
     * Callback for the compact action column.
     *
     * @param string|null $value Raw action name.
     * @return string
     */
    public static function format_action_icon(?string $value, ?stdClass $row = null): string {
        $action = $value !== null ? trim($value) : '';
        if ($action === '') {
            return '—';
        }

        if (self::is_downloading_now()) {
            return $action;
        }

        $iconmap = [
            'generate_text' => 'magic',
            'summarise_text' => 'align-left',
            'explain_text' => 'lightbulb-o',
            'generate_image' => 'image',
        ];
        $labelmap = [
            'generate_text' => get_string('audit_action_generate_text', 'local_elediaai_core'),
            'summarise_text' => get_string('audit_action_summarise_text', 'local_elediaai_core'),
            'explain_text' => get_string('audit_action_explain_text', 'local_elediaai_core'),
            'generate_image' => get_string('audit_action_generate_image', 'local_elediaai_core'),
        ];

        $label = $labelmap[$action] ?? $action;
        $iconname = $iconmap[$action] ?? 'bolt';
        $icon = lucide_icon::render($iconname);
        $sr = html_writer::tag('span', s($label), ['class' => 'sr-only']);

        return html_writer::tag('span', $icon . $sr, [
            'class' => 'lh-audit-action-pill',
            'title' => $label,
        ]);
    }

    /**
     * Callback for the linked content name column.
     *
     * @param int|string|null $value Context id.
     * @return string
     */
    public static function format_context_link(int|string|null $value, ?stdClass $row = null): string {
        $contextid = (int) $value;
        if ($contextid <= 0) {
            return '—';
        }

        try {
            $contextclass = class_exists(\core\context::class) ? \core\context::class : \context::class;
            $context = $contextclass::instance_by_id($contextid, IGNORE_MISSING);
        } catch (\Throwable $e) {
            $context = false;
        }
        if (!$context) {
            return '—';
        }

        $name = $context->get_context_name(false);
        if (self::is_downloading_now()) {
            return $name;
        }

        $url = method_exists($context, 'get_url') ? $context->get_url() : null;
        if ($url instanceof moodle_url) {
            return html_writer::link($url, s($name));
        }

        return s($name);
    }

    /**
     * Callback for the anonymised actor column.
     *
     * @param int|string|null $value User id.
     * @param stdClass|null $row All column fields for this row.
     * @return string
     */
    public static function format_anonymized_actor(int|string|null $value, ?stdClass $row = null): string {
        global $DB;

        $userid = (int) $value;
        if ($userid <= 0) {
            return '—';
        }

        $contextid = (int) ($row->audit_contextid_for_actor ?? $row->audit_contextid ?? 0);
        $rolelabel = '';
        if ($contextid > 0) {
            try {
                $contextclass = class_exists(\core\context::class) ? \core\context::class : \context::class;
                $context = $contextclass::instance_by_id($contextid, IGNORE_MISSING);
            } catch (\Throwable $e) {
                $context = false;
            }
            if ($context) {
                [$insql, $params] = $DB->get_in_or_equal($context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'lhairolectx');
                $params['userid'] = $userid;
                $roles = $DB->get_records_sql(
                    "SELECT DISTINCT r.id, r.shortname, r.name, r.archetype, r.sortorder
                       FROM {role_assignments} ra
                       JOIN {role} r ON r.id = ra.roleid
                      WHERE ra.userid = :userid
                        AND ra.contextid {$insql}
                   ORDER BY r.sortorder",
                    $params,
                    0,
                    2
                );
                if ($roles) {
                    $role = reset($roles);
                    $rolelabel = role_get_name($role, $context, ROLENAME_ALIAS);
                }
            }
        }

        if ($rolelabel === '') {
            return s(get_string('audit_actor_anonymized_unknown', 'local_elediaai_core'));
        }

        return s(get_string('audit_actor_anonymized_role', 'local_elediaai_core', $rolelabel));
    }

    /**
     * Callback for the named actor column.
     *
     * Mirrors core's `fullnamewithlink` for real people, and names the
     * other case instead of leaving the cell blank.
     *
     * @param int|string|null $value User id.
     * @param stdClass|null $row All column fields for this row.
     * @return string
     */
    public static function format_named_actor(int|string|null $value, ?stdClass $row = null): string {
        $userid = (int) $value;

        if ($userid <= 0) {
            $label = get_string('audit_actor_not_recorded', 'local_elediaai_core');
            if (self::is_downloading_now()) {
                return $label;
            }
            return html_writer::span(s($label), 'lh-ai-audit__actor-none', [
                'title' => get_string('audit_actor_not_recorded_help', 'local_elediaai_core'),
            ]);
        }

        // The row carries only the name fields the site's fullname format uses;
        // fullname() wants them all present.
        $row = $row ?? new stdClass();
        foreach (fields::get_name_fields() as $namefield) {
            $row->{$namefield} = $row->{$namefield} ?? '';
        }
        $name = fullname($row, has_capability('moodle/site:viewfullnames', context_system::instance()));

        if (self::is_downloading_now()) {
            return $name;
        }

        return html_writer::link(new moodle_url('/user/profile.php', ['id' => $userid]), s($name));
    }

    /**
     * Callback for the prompt + response columns.
     *
     * On screen: renders a small eye-icon button. The full content is
     * embedded in a `<template>` next to the button so the AMD module
     * (`audit_preview`) can pop it open in a modal without an extra
     * round-trip. On CSV/Excel download: returns the truncated plain
     * text so the export stays readable.
     *
     * @param string|null $value Full prompt/response from the join.
     * @param stdClass $row Unused; required by reportbuilder callback signature.
     * @param string $titlestringid Lang string for the modal heading.
     * @return string
     */
    public static function format_preview_cell(?string $value, stdClass $row, string $titlestringid): string {
        $clean = $value !== null ? trim($value) : '';
        if ($clean === '') {
            return '—';
        }

        if (self::is_downloading_now()) {
            return self::truncate_plain($clean);
        }

        $title = get_string($titlestringid, 'local_elediaai_core');
        $opentext = get_string('audit_preview_open', 'local_elediaai_core');
        $icon = lucide_icon::render('eye');
        $sr = html_writer::tag('span', s($opentext), ['class' => 'sr-only']);
        $button = html_writer::tag(
            'button',
            $icon . $sr,
            [
                'type' => 'button',
                'class' => 'btn btn-link btn-sm p-0 me-1 lh-audit-preview-btn',
                'data-action' => 'lh-audit-preview',
                'data-preview-title' => $title,
                'title' => $opentext,
                'aria-label' => $opentext . ': ' . $title,
            ]
        );

        // `<template>` keeps the full text in the DOM without rendering it;
        // the AMD module reads `.innerHTML` (already HTML-escaped via s()).
        $template = html_writer::tag('template', s($clean), ['class' => 'lh-audit-preview-content']);

        return html_writer::span($button . $template, 'lh-audit-preview');
    }

    /**
     * Callback for the success column.
     *
     * Display: green check / red times icon with screen-reader text.
     * Download: localised "Yes" / "No" so spreadsheets stay readable.
     *
     * @param int|bool|null $value Raw success flag from the DB row.
     * @param stdClass|null $row All column fields for this row.
     * @return string
     */
    public static function format_success_icon($value, ?stdClass $row = null): string {
        if ($value === null || $value === '') {
            return '—';
        }

        $isok = (int) $value === 1;
        if (self::is_downloading_now()) {
            if ($isok) {
                return get_string('yes');
            }
            if (!audit_config::show_error()) {
                return get_string('no');
            }
            $error = trim((string) ($row->audit_errormessage ?? ''));
            return $error !== '' ? get_string('no') . ': ' . $error : get_string('no');
        }

        $stringid = $isok ? 'audit_success_yes' : 'audit_success_no';
        $label = get_string($stringid, 'local_elediaai_core');

        $icon = lucide_icon::render($isok ? 'check' : 'times');
        $sr = html_writer::tag('span', s($label), ['class' => 'sr-only']);

        $attrs = [
            'class' => 'lh-audit-success ' . ($isok ? 'text-success' : 'text-danger'),
            'title' => $label,
        ];

        $error = audit_config::show_error() ? trim((string) ($row->audit_errormessage ?? '')) : '';
        if ($isok || $error === '') {
            return html_writer::tag('span', $icon . $sr, $attrs);
        }

        $opentext = get_string('audit_preview_open_error', 'local_elediaai_core');
        $button = html_writer::tag(
            'button',
            $icon . $sr,
            [
                'type' => 'button',
                'class' => 'btn btn-link btn-sm p-0 lh-audit-preview-btn lh-audit-success text-danger',
                'data-action' => 'lh-audit-preview',
                'data-preview-title' => get_string('audit_preview_title_error', 'local_elediaai_core'),
                'title' => $opentext,
                'aria-label' => $opentext,
            ]
        );
        $template = html_writer::tag('template', s($error), ['class' => 'lh-audit-preview-content']);

        return html_writer::span($button . $template, 'lh-audit-preview');
    }

    /**
     * Callback for the combined tokens column.
     *
     * Renders "prompt / completion" — e.g. `420 / 180`. Returns "—"
     * when both tokens are zero (e.g. failed call) so the cell stays
     * quiet rather than showing `0 / 0`.
     *
     * @param int|string|null $value First column field; ignored, we read both from $row.
     * @param stdClass $row All column fields for this row.
     * @return string
     */
    public static function format_tokens_cell(int|string|null $value, stdClass $row): string {
        $prompt = (int) ($row->tokens_prompt ?? 0);
        $completion = (int) ($row->tokens_completion ?? 0);
        if (($row->audit_actionname_for_cost ?? '') === 'generate_image' && $prompt === 0 && $completion === 0) {
            $prompt = quota_manager::action_cost('generate_image', [
                'quality' => (string) ($row->audit_image_quality ?? 'standard'),
                'numimages' => (int) ($row->audit_image_numimages ?? 1),
            ]);
        }
        if ($prompt === 0 && $completion === 0) {
            return '—';
        }
        $label = get_string('audit_col_tokens_help', 'local_elediaai_core');
        return html_writer::tag('span', s($prompt . ' / ' . $completion), ['title' => $label]);
    }

    /**
     * Truncate to fit inline. Centralised so the teaser, the download
     * payload, and any future cell that wants a short version stay
     * visually consistent.
     */
    private static function truncate_plain(string $value, int $limit = 240): string {
        $clean = trim($value);
        if (mb_strlen($clean) <= $limit) {
            return $clean;
        }
        return mb_substr($clean, 0, $limit) . '…';
    }

    /**
     * Heuristic for "are we rendering for CSV/Excel download right now".
     *
     * Reportbuilder's `system_report_table::format_row()` uses the same
     * `format_value()` path for the on-screen table and downloads. The
     * table itself knows via `is_downloading()` but doesn't surface
     * that to column callbacks. Re-reading the `download` URL param
     * gives the same answer: it is set by `flexible_table::out()` only
     * during the download branch.
     *
     * @return bool
     */
    private static function is_downloading_now(): bool {
        return optional_param('download', '', PARAM_ALPHA) !== '';
    }
}
