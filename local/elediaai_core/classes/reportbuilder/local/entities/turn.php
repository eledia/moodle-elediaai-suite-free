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
 * Reportbuilder entity over the suite's own turn log.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\reportbuilder\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use html_writer;
use lang_string;
use local_elediaai_core\local\audit_config;
use local_elediaai_core\local\turn_recorder;
use local_elediaai_core\output\lucide_icon;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Columns and filters for `local_elediaai_core_turn`.
 *
 * Unlike the older audit entity this one does not extend anything from core: the
 * table is the suite's own, and with it comes the column Moodle's register
 * cannot have -- the feature that asked. The actor column is gone entirely
 * rather than rendered empty, because there is no actor to render: the log
 * holds a pseudonym that nothing resolves.
 */
final class turn extends base {
    #[\Override]
    protected function get_default_tables(): array {
        return [turn_recorder::TABLE];
    }

    #[\Override]
    protected function get_default_entity_title(): lang_string {
        return new lang_string('turn_entity_title', 'local_elediaai_core');
    }

    #[\Override]
    protected function get_available_columns(): array {
        $alias = $this->get_table_alias(turn_recorder::TABLE);
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
            'component',
            new lang_string('audit_col_feature', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.component")
            ->set_is_sortable(true)
            ->set_callback([self::class, 'format_feature']);

        $columns[] = (new column(
            'actionname',
            new lang_string('audit_col_action', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.actionname")
            ->set_is_sortable(true);

        $columns[] = (new column(
            'origin',
            new lang_string('audit_col_origin', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.origin")
            ->set_is_sortable(true)
            ->set_callback([self::class, 'format_origin']);

        $columns[] = (new column(
            'context_link',
            new lang_string('audit_col_context', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.contextid")
            ->set_is_sortable(false)
            ->set_callback([self::class, 'format_context']);

        $columns[] = (new column(
            'topic',
            new lang_string('audit_col_topic', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.topic")
            ->set_is_sortable(true)
            ->set_callback([self::class, 'format_plain']);

        $columns[] = (new column(
            'prompt',
            new lang_string('audit_col_prompt', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_LONGTEXT)
            ->add_field("{$alias}.prompt")
            ->set_is_sortable(false)
            ->set_callback([self::class, 'format_preview'], 'audit_preview_title_prompt');

        $columns[] = (new column(
            'response',
            new lang_string('audit_col_response', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_LONGTEXT)
            ->add_field("{$alias}.response")
            ->set_is_sortable(false)
            ->set_callback([self::class, 'format_preview'], 'audit_preview_title_response');

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
            'provider_model',
            new lang_string('audit_col_provider_model', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.provider")
            ->add_field("{$alias}.model", 'turnmodel')
            ->set_is_sortable(true)
            ->set_callback([self::class, 'format_provider_model']);

        $columns[] = (new column(
            'tokens',
            new lang_string('audit_col_tokens', 'local_elediaai_core'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.prompttokens")
            ->add_field("{$alias}.completiontokens", 'turncompletion')
            ->add_field("{$alias}.tokensestimated", 'turnestimated')
            ->set_is_sortable(false)
            ->set_callback([self::class, 'format_tokens']);

        return $columns;
    }

    #[\Override]
    protected function get_available_filters(): array {
        $alias = $this->get_table_alias(turn_recorder::TABLE);
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
            'component',
            new lang_string('audit_col_feature', 'local_elediaai_core'),
            $this->get_entity_name(),
            "{$alias}.component"
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            select::class,
            'origin',
            new lang_string('audit_col_origin', 'local_elediaai_core'),
            $this->get_entity_name(),
            "{$alias}.origin"
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback(static function (): array {
                return [
                    turn_recorder::ORIGIN_GROUNDED => get_string('audit_origin_grounded', 'local_elediaai_core'),
                    turn_recorder::ORIGIN_GENERAL => get_string('audit_origin_general', 'local_elediaai_core'),
                    turn_recorder::ORIGIN_MCP => get_string('audit_origin_mcp', 'local_elediaai_core'),
                ];
            });

        return $filters;
    }

    /**
     * Readable feature name, asked from the plugin rather than held here.
     *
     * @param string|null $value
     * @return string
     */
    public static function format_feature(?string $value): string {
        $component = trim((string) $value);
        if ($component === '') {
            return get_string('audit_component_unknown', 'local_elediaai_core');
        }
        if (get_string_manager()->string_exists('pluginname', $component)) {
            return get_string('pluginname', $component);
        }
        return $component;
    }

    /**
     * Where the answer came from, as a badge.
     *
     * @param string|null $value
     * @return string
     */
    public static function format_origin(?string $value): string {
        $origin = trim((string) $value);
        $known = [
            turn_recorder::ORIGIN_GROUNDED => ['audit_origin_grounded', 'eai-badge--ok'],
            turn_recorder::ORIGIN_GENERAL => ['audit_origin_general', 'eai-badge--warn'],
            turn_recorder::ORIGIN_MCP => ['audit_origin_mcp', 'eai-badge--ai'],
        ];
        if (!isset($known[$origin])) {
            return '—';
        }
        [$stringid, $class] = $known[$origin];
        $label = get_string($stringid, 'local_elediaai_core');
        if (self::is_downloading_now()) {
            return $label;
        }
        return html_writer::span(s($label), 'eai-badge ' . $class);
    }

    /**
     * Context name, linked where Moodle exposes a URL.
     *
     * @param int|string|null $value
     * @return string
     */
    public static function format_context(int|string|null $value): string {
        $contextid = (int) $value;
        if ($contextid <= 0) {
            return '—';
        }
        try {
            $context = \core\context::instance_by_id($contextid, IGNORE_MISSING);
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
        return $url instanceof moodle_url ? html_writer::link($url, s($name)) : s($name);
    }

    /**
     * Plain text or an em dash.
     *
     * @param string|null $value
     * @return string
     */
    public static function format_plain(?string $value): string {
        $value = trim((string) $value);
        return $value === '' ? '—' : s($value);
    }

    /**
     * Prompt/response preview behind an eye icon.
     *
     * @param string|null $value
     * @param stdClass $row
     * @param string $titlestringid
     * @return string
     */
    public static function format_preview(?string $value, stdClass $row, string $titlestringid): string {
        $clean = trim((string) $value);
        if ($clean === '') {
            return '—';
        }
        if (self::is_downloading_now()) {
            return \core_text::strlen($clean) > 240
                ? \core_text::substr($clean, 0, 240) . '…'
                : $clean;
        }

        $title = get_string($titlestringid, 'local_elediaai_core');
        $opentext = get_string('audit_preview_open', 'local_elediaai_core');
        $button = html_writer::tag(
            'button',
            lucide_icon::render('eye')
                . html_writer::tag('span', s($opentext), ['class' => 'sr-only']),
            [
                'type' => 'button',
                'class' => 'btn btn-link btn-sm p-0 me-1 lh-audit-preview-btn',
                'data-action' => 'lh-audit-preview',
                'data-preview-title' => $title,
                'title' => $opentext,
                'aria-label' => $opentext . ': ' . $title,
            ]
        );
        $template = html_writer::tag('template', s($clean), ['class' => 'lh-audit-preview-content']);

        return html_writer::span($button . $template, 'lh-audit-preview');
    }

    /**
     * Success as an icon, with the error code behind a failed one.
     *
     * @param mixed $value
     * @param stdClass|null $row
     * @return string
     */
    public static function format_success($value, ?stdClass $row = null): string {
        if ($value === null || $value === '') {
            return '—';
        }
        $isok = (int) $value === 1;
        $stringid = $isok ? 'audit_success_yes' : 'audit_success_no';
        $label = get_string($stringid, 'local_elediaai_core');

        if (self::is_downloading_now()) {
            $error = audit_config::show_error() ? trim((string) ($row->errorcode ?? '')) : '';
            $suffix = !$isok && $error !== '' ? ': ' . $error : '';
            return ($isok ? get_string('yes') : get_string('no')) . $suffix;
        }

        $icon = lucide_icon::render($isok ? 'check' : 'times');
        $sr = html_writer::tag('span', s($label), ['class' => 'sr-only']);
        $span = html_writer::tag('span', $icon . $sr, [
            'class' => 'lh-audit-success ' . ($isok ? 'text-success' : 'text-danger'),
            'title' => $label,
        ]);

        $error = audit_config::show_error() ? trim((string) ($row->errorcode ?? '')) : '';
        if ($isok || $error === '') {
            return $span;
        }
        return $span . html_writer::span(s($error), 'eai-audit__errorcode');
    }

    /**
     * Provider and model in one cell.
     *
     * @param string|null $value
     * @param stdClass|null $row
     * @return string
     */
    public static function format_provider_model(?string $value, ?stdClass $row = null): string {
        $provider = trim((string) $value);
        $model = trim((string) ($row->turnmodel ?? ''));
        if ($provider === '' && $model === '') {
            return '—';
        }
        if (str_starts_with($provider, 'aiprovider_')) {
            $provider = \core_text::strtotitle(str_replace('_', ' ', substr($provider, 11)));
        } else {
            $provider = self::backend_name($provider);
        }
        if (self::is_downloading_now()) {
            return $model !== '' ? $provider . ' / ' . $model : $provider;
        }
        if ($model === '') {
            return html_writer::span(s($provider), 'lh-audit-provider-model__provider');
        }
        return html_writer::span(
            html_writer::span(s($provider), 'lh-audit-provider-model__provider')
                . html_writer::span(s($model), 'lh-audit-provider-model__model'),
            'lh-audit-provider-model'
        );
    }

    /**
     * "prompt / completion" tokens.
     *
     * @param int|string|null $value
     * @param stdClass $row
     * @return string
     */
    /**
     * The human name of a chat backend, if the provider is one.
     *
     * A chat turn stores the id of the backend it spoke to -- there is no AI
     * provider to name, because the tutor never calls a model itself. The id
     * is the right thing to store: stable and the same in every language. It
     * is the wrong thing to show, and „ingestionapi" in a column headed
     * „Provider / model" is what made somebody ask.
     *
     * Asked of the chat engine rather than mapped here: the core knows no
     * plugins, and the list of backends is the engine's to keep. Without the
     * engine installed the id stays as it is, which is still better than
     * nothing.
     *
     * @param string $provider The stored provider value.
     * @return string
     */
    private static function backend_name(string $provider): string {
        $resolver = '\\local_elediaai_chatengine\\backend_resolver';
        if ($provider === '' || !class_exists($resolver)) {
            return $provider;
        }

        return $resolver::display_name($provider);
    }

    /**
     * Prompt and response tokens, marked when they are an estimate.
     *
     * @param int|string|null $value The prompt tokens.
     * @param stdClass $row The row, carrying the completion count and the flag.
     * @return string
     */
    public static function format_tokens(int|string|null $value, stdClass $row): string {
        if (!audit_config::show_tokens()) {
            return get_string('audit_metric_hidden', 'local_elediaai_core');
        }
        $prompt = (int) $value;
        $completion = (int) ($row->turncompletion ?? 0);
        if ($prompt === 0 && $completion === 0) {
            return '—';
        }

        // Eine Schaetzung als gemessene Zahl zu zeigen waere die
        // unangenehmere Sorte Fehler: sie sieht genau aus.
        $estimated = !empty($row->turnestimated);
        $text = ($estimated ? '≈ ' : '') . $prompt . ' / ' . $completion;

        return html_writer::tag('span', s($text), [
            'title' => get_string(
                $estimated ? 'audit_col_tokens_help_estimated' : 'audit_col_tokens_help',
                'local_elediaai_core'
            ),
        ]);
    }

    /**
     * Whether a CSV/Excel download is being rendered right now.
     *
     * Same heuristic and the same reason as in the older audit entity:
     * reportbuilder uses one formatting path for screen and download and does
     * not surface which one is running to a column callback.
     *
     * @return bool
     */
    private static function is_downloading_now(): bool {
        return optional_param('download', '', PARAM_ALPHA) !== '';
    }
}
