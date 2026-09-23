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
 * Shared rendering helpers for the AI audit pages.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\local;

defined('MOODLE_INTERNAL') || die();

use html_writer;
use local_elediaai_core\output\plugin_shell;
use moodle_url;
use Throwable;

/**
 * Shared rendering helpers for the AI audit pages.
 */
final class audit_page {
    /**
     * Static-only.
     */
    private function __construct() {
    }

    /**
     * Header action icons shared by all audit pages.
     *
     * @param \context $context
     * @param bool $settingsiscurrent
     * @return array
     */
    public static function actions(\context $context, bool $settingsiscurrent = false): array {
        $actions = plugin_shell::action_slots(
            'local_elediaai_core',
            has_capability('moodle/site:config', $context),
            new moodle_url('/local/elediaai_core/audit_settings.php'),
            get_string('shell_help_label', 'local_elediaai_core'),
            get_string('feature_settings', 'local_elediaai_core'),
            settingsiscurrent: $settingsiscurrent
        );
        return $actions;
    }

    /**
     * Shared CSS for audit cards, quick filters and tables.
     *
     * @return string
     */
    public static function css(): string {
        return html_writer::tag('style', '
.lh-ai-audit-stack {
    display: grid;
    gap: 1rem;
}
.lh-ai-audit-panel,
.lh-ai-audit {
    background: var(--eai-surface);
    border: 1px solid var(--eai-line);
    border-radius: var(--eai-radius);
    box-shadow: var(--eai-shadow-card);
    padding: 1rem;
}
.lh-ai-audit-panel__eyebrow {
    color: var(--eai-fg-dim);
    font-size: var(--font-size-caption);
    font-weight: 800;
    letter-spacing: 0.08em;
    margin-bottom: 0.35rem;
    text-transform: uppercase;
}
.lh-ai-audit-panel__title {
    color: var(--eai-fg);
    font-size: var(--font-size-h3);
    font-weight: 800;
    margin: 0 0 0.35rem;
}
.lh-ai-audit-panel__body {
    color: var(--eai-muted);
    margin: 0 0 0.9rem;
}
.lh-ai-audit-metrics {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(auto-fit, minmax(8rem, 1fr));
}
.lh-ai-audit-metric {
    background: var(--eai-bg);
    border: 1px solid var(--eai-line);
    border-radius: var(--eai-radius);
    padding: 0.85rem;
}
.lh-ai-audit-metric__label {
    color: var(--eai-muted);
    font-size: var(--font-size-small);
    font-weight: 800;
    text-transform: uppercase;
}
.lh-ai-audit-metric__value {
    color: var(--eai-fg);
    font-size: var(--font-size-h2);
    font-weight: 850;
    line-height: 1.15;
    margin-top: 0.2rem;
}
.lh-ai-audit-metric__hint {
    color: var(--eai-faint);
    font-size: var(--font-size-small);
    margin-top: 0.25rem;
}
.lh-ai-audit-paths {
    display: grid;
    gap: 1rem;
}
.lh-ai-audit-path {
    align-items: flex-start;
    background: var(--eai-surface);
    border: 1px solid var(--eai-line);
    border-radius: var(--eai-radius);
    box-shadow: var(--eai-shadow-card);
    display: grid;
    gap: 0.75rem;
    padding: 1rem;
}
.lh-ai-audit-path__action {
    justify-self: start;
}
.lh-ai-audit-insights {
    display: grid;
    gap: 0.6rem;
    margin: 0;
}
.lh-ai-audit-insight {
    align-items: start;
    border-top: 1px solid var(--eai-line-soft);
    display: grid;
    gap: 0.35rem 0.75rem;
    grid-template-columns: minmax(0, 1fr) auto;
    padding-top: 0.65rem;
}
.lh-ai-audit-insight:first-child {
    border-top: 0;
    padding-top: 0;
}
.lh-ai-audit-insight__title {
    font-weight: 800;
}
.lh-ai-audit-insight__meta {
    color: var(--eai-muted);
    font-size: var(--font-size-body);
}
.lh-ai-audit-insight__badge {
    background: var(--eai-accent-wash);
    border-radius: var(--eai-radius-pill);
    color: var(--eai-fg);
    font-weight: 800;
    padding: 0.2rem 0.55rem;
    white-space: nowrap;
}
.lh-ai-audit-empty,
.lh-ai-audit-note {
    color: var(--eai-muted);
    margin: 0;
}
.lh-ai-audit__quickfilters {
    align-items: center;
    border-bottom: 1px solid var(--eai-line);
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    padding-bottom: 0.75rem;
}
.lh-ai-audit__quickfilter {
    background: var(--eai-bg);
    border: 1px solid var(--eai-line);
    border-radius: var(--eai-radius-pill);
    color: var(--eai-fg-dim);
    display: inline-flex;
    font-weight: 700;
    padding: 0.25rem 0.65rem;
    text-decoration: none;
}
.lh-ai-audit__quickfilter:hover {
    background: var(--eai-accent-wash);
    color: var(--eai-fg);
    text-decoration: none;
}
.lh-ai-audit__quickfilter.is-active {
    background: var(--eai-accent);
    border-color: var(--eai-accent);
    color: var(--eai-fg);
}
.lh-ai-audit table {
    border-collapse: collapse;
    width: 100%;
}
.lh-ai-audit .reportbuilder-report,
.lh-ai-audit .reportbuilder-wrapper {
    max-width: 100%;
}
.lh-ai-audit .reportbuilder-wrapper {
    padding-bottom: 0.25rem;
}
.lh-ai-audit table thead th {
    border-bottom: 1px solid var(--eai-line);
    color: var(--eai-fg);
    font-weight: 800;
    padding: 0.6rem 0.5rem;
}
.lh-ai-audit table tbody td,
.lh-ai-audit table tbody th {
    border-bottom: 1px solid var(--eai-line);
    padding: 0.55rem 0.5rem;
    vertical-align: middle;
}
/*
 * Hier standen Spaltenbreiten nach Position: die zweite Spalte hoechstens
 * 8,5rem, die dritte genau 2,75rem, die sechste bis neunte zentriert. Sie
 * stammen aus der Zeit, als dieser Bericht eine feste Spaltenfolge hatte.
 * Er hat sie nicht mehr -- Frage, Antwort und Tokens haengen an den
 * Einstellungen der Website --, also traf `nth-child(3)` laengst eine
 * andere Spalte als gemeint. Zusammen mit `min-width: 48rem` waren sie der
 * Grund, warum die Tabelle seitlich geschoben werden musste: gemessen 1093
 * Pixel Bedarf in einer 718 Pixel breiten Spalte. Die Breiten kommen jetzt
 * aus dem Inhalt, und der Rest steht in styles.css.
 */
.lh-ai-audit .lh-audit-action-pill,
.lh-ai-audit .lh-audit-preview-btn,
.lh-ai-audit .lh-audit-success {
    align-items: center;
    background: var(--eai-bg);
    border-radius: var(--eai-radius-pill);
    color: var(--eai-muted);
    display: inline-flex;
    height: 2rem;
    justify-content: center;
    min-width: 2rem;
    text-decoration: none;
}
.lh-ai-audit .lh-audit-action-pill {
    color: var(--eai-fg-dim);
}
.lh-ai-audit .lh-audit-provider-model {
    display: grid;
    gap: 0.1rem;
    line-height: 1.25;
}
.lh-ai-audit .lh-audit-provider-model__provider {
    color: var(--eai-fg);
    font-weight: 700;
}
.lh-ai-audit .lh-audit-provider-model__model {
    color: var(--eai-muted);
    font-size: var(--font-size-small);
    max-width: 8.5rem;
    overflow-wrap: anywhere;
}
.lh-ai-audit .lh-audit-preview-btn {
    border: 0;
}
/* Der Nachdruck traegt und bleibt: `.text-success` und `.text-danger` sind
   Bootstrap-Utilities, und Boost baut sie mit `$enable-important-utilities:
   true`, also selbst mit !important. Ohne Nachdruck gewinnt die Utility und
   die Farbe steht wieder unlesbar auf ihrer eigenen Wash-Flaeche -- genau der
   Kontrastfehler, der in task27 behoben wurde. */
.lh-ai-audit .lh-audit-success.text-success {
    background: var(--eai-success-wash);
    color: var(--eai-success) !important;
}
.lh-ai-audit .lh-audit-success.text-danger {
    background: var(--eai-danger-wash);
    color: var(--eai-danger) !important;
}
.lh-audit-preview-body {
    max-width: 100%;
    overflow-x: hidden;
    overflow-wrap: anywhere;
    white-space: pre-wrap;
    word-break: break-word;
}
.lh-ai-audit .lh-audit-row--failed > th,
.lh-ai-audit .lh-audit-row--failed > td {
    background-color: var(--eai-danger-wash);
}
.lh-ai-audit .lh-audit-row--failed > th:first-child,
.lh-ai-audit .lh-audit-row--failed > td:first-child {
    border-left: 0.25rem solid var(--eai-danger);
}
.lh-ai-audit .lh-audit-row--failed:hover > th,
.lh-ai-audit .lh-audit-row--failed:hover > td {
    background-color: var(--eai-danger-wash);
}
');
    }

    /**
     * Render a small metric card.
     *
     * @param string $label
     * @param string $value
     * @param string $hint
     * @return string
     */
    public static function metric_card(string $label, string $value, string $hint = ''): string {
        $content = html_writer::div(s($label), 'lh-ai-audit-metric__label');
        $content .= html_writer::div(s($value), 'lh-ai-audit-metric__value');
        if ($hint !== '') {
            $content .= html_writer::div(s($hint), 'lh-ai-audit-metric__hint');
        }

        return html_writer::div($content, 'lh-ai-audit-metric');
    }

    /**
     * One metric tile.
     *
     * @param string $label Uppercase caption.
     * @param string $value Big number; may carry a unit span.
     * @param string|null $hint Small line under the number.
     * @param string|null $figure A bar or other small picture.
     * @param string $tone neutral, ok, warn or quiet.
     * @return string
     */
    public static function metric(
        string $label,
        string $value,
        ?string $hint = null,
        ?string $figure = null,
        string $tone = 'neutral'
    ): string {
        $body = html_writer::div(s($label), 'eai-metric__label');
        $body .= html_writer::div($value, 'eai-metric__value');
        if ($figure !== null) {
            $body .= $figure;
        }
        if ($hint !== null && $hint !== '') {
            $body .= html_writer::div(s($hint), 'eai-metric__hint');
        }

        return html_writer::div($body, 'eai-metric eai-metric--' . $tone);
    }

    /**
     * A two-colour share bar: covered against uncovered.
     *
     * @param int $percent Covered share.
     * @param string $label Accessible description of the split.
     * @return string
     */
    public static function split_bar(int $percent, string $label): string {
        $percent = max(0, min(100, $percent));
        $fill = html_writer::div('', 'eai-splitbar__covered', ['style' => 'width: ' . $percent . '%;']);
        $fill .= html_writer::div('', 'eai-splitbar__open', ['style' => 'width: ' . (100 - $percent) . '%;']);

        return html_writer::div($fill, 'eai-splitbar', [
            'role' => 'img',
            'aria-label' => $label,
        ]);
    }

    /**
     * Resolve a context name/link for audit insight cards.
     *
     * @param int $contextid
     * @return string
     */
    public static function context_label(int $contextid): string {
        if ($contextid <= 0) {
            return get_string('audit_context_unknown', 'local_elediaai_core');
        }

        try {
            $contextclass = class_exists(\core\context::class) ? \core\context::class : \context::class;
            $context = $contextclass::instance_by_id($contextid, IGNORE_MISSING);
        } catch (Throwable $e) {
            $context = false;
        }

        if (!$context) {
            return get_string('audit_context_unknown', 'local_elediaai_core');
        }

        $name = $context->get_context_name(false);
        $url = method_exists($context, 'get_url') ? $context->get_url() : null;
        if ($url instanceof moodle_url) {
            return html_writer::link($url, s($name));
        }

        return s($name);
    }

    /**
     * Build aggregate data for the audit overview.
     *
     * @return array
     */
    public static function overview_data(): array {
        global $DB, $USER;

        [$scopewhere, $scopeparams] = self::teacher_scope_where('reg.contextid', (int) $USER->id);
        $total = (int) $DB->get_field_sql(
            "SELECT COUNT(1) FROM {ai_action_register} reg WHERE 1 = 1 {$scopewhere}",
            $scopeparams
        );
        $failed = (int) $DB->get_field_sql(
            "SELECT COUNT(1) FROM {ai_action_register} reg WHERE reg.success = :failed {$scopewhere}",
            ['failed' => 0] + $scopeparams
        );
        $support = (int) $DB->get_field_sql(
            "SELECT COUNT(1)
               FROM {ai_action_register} reg
              WHERE reg.actionname IN (:summarise, :explain) {$scopewhere}",
            ['summarise' => 'summarise_text', 'explain' => 'explain_text'] + $scopeparams
        );

        $tokensql = "SELECT COALESCE(SUM(
                           COALESCE(gt.prompttokens, 0) + COALESCE(gt.completiontoken, 0) +
                           COALESCE(st.prompttokens, 0) + COALESCE(st.completiontoken, 0) +
                           COALESCE(et.prompttokens, 0) + COALESCE(et.completiontoken, 0)
                       ), 0)
                      FROM {ai_action_register} reg
                 LEFT JOIN {ai_action_generate_text} gt ON reg.actionname = 'generate_text' AND reg.actionid = gt.id
                 LEFT JOIN {ai_action_summarise_text} st ON reg.actionname = 'summarise_text' AND reg.actionid = st.id
                 LEFT JOIN {ai_action_explain_text} et ON reg.actionname = 'explain_text' AND reg.actionid = et.id
                     WHERE 1 = 1 {$scopewhere}";
        $tokens = (int) $DB->get_field_sql($tokensql, $scopeparams);
        if ($DB->get_manager()->table_exists('ai_action_generate_image')) {
            $imageusage = $DB->get_records_sql(
                "SELECT gi.quality, gi.numberimages
                   FROM {ai_action_register} reg
                   JOIN {ai_action_generate_image} gi ON reg.actionname = 'generate_image' AND reg.actionid = gi.id
                  WHERE 1 = 1 {$scopewhere}",
                $scopeparams
            );
            foreach ($imageusage as $usage) {
                $tokens += quota_manager::action_cost('generate_image', [
                    'quality' => (string) ($usage->quality ?? 'standard'),
                    'numimages' => (int) ($usage->numimages ?? 1),
                ]);
            }
        }

        return [
            'total' => $total,
            'failed' => $failed,
            'support' => $support,
            'tokens' => $tokens,
        ];
    }

    /**
     * Optional Teacher-own-courses SQL suffix for aggregate audit queries.
     *
     * @param string $contextidfield
     * @param int $userid
     * @return array{0:string,1:array}
     */
    private static function teacher_scope_where(string $contextidfield, int $userid): array {
        if (!audit_config::restrict_to_teacher_courses()) {
            return ['', []];
        }

        [$sql, $params] = audit_config::teacher_course_scope_sql($contextidfield, $userid);
        return [' AND ' . $sql, $params];
    }

    /**
     * A row of quick-filter links for a report.
     *
     * Two reports sit on the technical page now -- the suite's own turns and
     * Moodle's register for everything outside the suite -- and each needs its
     * own filter row. They must not share a URL parameter: one bar would then
     * silently filter the other table as well.
     *
     * @param moodle_url $base The page URL without the filter parameter.
     * @param string $param URL parameter this bar writes.
     * @param array<string, string> $options Value => label; '' is „all".
     * @param string $current Currently selected value.
     * @return string
     */
    public static function quickfilter_bar(
        moodle_url $base,
        string $param,
        array $options,
        string $current
    ): string {
        $html = '';
        foreach ($options as $value => $label) {
            $url = clone $base;
            if ($value !== '') {
                $url->param($param, $value);
            }
            $classes = 'lh-ai-audit__quickfilter';
            if ($current === (string) $value) {
                $classes .= ' is-active';
            }
            $html .= html_writer::link($url, s($label), ['class' => $classes]);
        }

        return html_writer::div($html, 'lh-ai-audit__quickfilters');
    }

    /**
     * Eyebrow, title and intro for a report section.
     *
     * @param string $eyebrow Lang string id for the eyebrow.
     * @param string $title Lang string id for the heading.
     * @param string $intro Lang string id for the paragraph.
     * @return string
     */
    public static function section_heading(string $eyebrow, string $title, string $intro): string {
        $html = html_writer::div(
            get_string($eyebrow, 'local_elediaai_core'),
            'eai-audit-panel__eyebrow'
        );
        $html .= html_writer::tag('h2', get_string($title, 'local_elediaai_core'), [
            'class' => 'eai-audit-panel__title',
        ]);
        $html .= html_writer::tag('p', get_string($intro, 'local_elediaai_core'), [
            'class' => 'eai-audit-panel__body',
        ]);

        return $html;
    }

    /**
     * Token usage per suite feature, with a readable label per component.
     *
     * @param int $days Period in days.
     * @return array<int, array{label: string, component: string, tokens: int, requests: int}>
     */
    public static function component_usage(int $days = 30): array {
        $out = [];
        foreach (insights::usage_by_component($days) as $row) {
            $out[] = [
                'label' => self::feature_label($row['component']),
                'component' => $row['component'],
                'tokens' => $row['tokens'],
                'requests' => $row['requests'],
            ];
        }
        return $out;
    }

    /**
     * Readable name for a frankenstyle component.
     *
     * The core does not know its plugins, so it does not hold a table of names
     * (06.09.2026). It asks: first the feature registry, whose descriptors the
     * plugins themselves supply -- that way the audit reads the same name as
     * the dashboard tile -- then the plugin's own `pluginname`. A component
     * whose plugin is gone keeps its frankenstyle name rather than vanishing;
     * usage that happened is not undone by an uninstall.
     *
     * @param string $component
     * @return string
     */
    private static function feature_label(string $component): string {
        if ($component === '') {
            return get_string('audit_component_unknown', 'local_elediaai_core');
        }

        try {
            $matches = [];
            foreach (\local_elediaai_core\feature\registry::all() as $descriptor) {
                if ($descriptor->component === $component) {
                    $matches[] = $descriptor->name;
                }
            }
            // Exactly one descriptor means the name is unambiguous. Several --
            // the core itself owns a dozen -- would be a guess, so fall through.
            if (count($matches) === 1) {
                return (string) reset($matches);
            }
        } catch (Throwable $e) {
            unset($e);
        }

        if (get_string_manager()->string_exists('pluginname', $component)) {
            return get_string('pluginname', $component);
        }

        return $component;
    }

    /**
     * Which feature consumed how much -- the question the register cannot answer.
     *
     * @param array $rows Result of {@see component_usage()}.
     * @param int $days Period in days, for the caption.
     * @return string
     */
    public static function component_panel(array $rows, int $days = 30): string {
        $body = html_writer::div(
            get_string('audit_component_eyebrow', 'local_elediaai_core'),
            'eai-audit-panel__eyebrow'
        );
        $body .= html_writer::tag('h2', get_string('audit_component_title', 'local_elediaai_core'), [
            'class' => 'eai-audit-panel__title',
        ]);
        $body .= html_writer::tag('p', get_string('audit_component_intro', 'local_elediaai_core', $days), [
            'class' => 'eai-audit-panel__body',
        ]);

        if (!$rows) {
            $body .= html_writer::tag('p', get_string('audit_component_empty', 'local_elediaai_core'), [
                'class' => 'eai-audit-panel__empty',
            ]);
            return html_writer::div($body, 'eai-audit-panel');
        }

        $max = 0;
        foreach ($rows as $row) {
            $max = max($max, $row['tokens']);
        }

        $list = '';
        foreach ($rows as $row) {
            // A zero-token feature still gets a visible sliver: the row says
            // "used, but cheap", and an invisible bar would read as a fault.
            $percent = $max > 0 && $row['tokens'] > 0
                ? max(2, (int) round($row['tokens'] * 100 / $max))
                : 0;
            $bar = html_writer::div('', 'eai-audit-component__fill', [
                'style' => 'width: ' . $percent . '%;',
            ]);
            $track = html_writer::div($bar, 'eai-audit-component__track', [
                'role' => 'progressbar',
                'aria-valuenow' => $row['tokens'],
                'aria-valuemin' => 0,
                'aria-valuemax' => $max,
                'aria-label' => $row['label'],
            ]);
            $list .= html_writer::div(
                html_writer::div(s($row['label']), 'eai-audit-component__label')
                    . $track
                    . html_writer::div(
                        number_format($row['tokens'], 0, ',', ' '),
                        'eai-audit-component__value'
                    )
                    . html_writer::div(
                        get_string('audit_component_requests', 'local_elediaai_core', $row['requests']),
                        'eai-audit-component__meta'
                    ),
                'eai-audit-component'
            );
        }
        $body .= html_writer::div($list, 'eai-audit-components');
        $body .= html_writer::tag('p', get_string('audit_component_note', 'local_elediaai_core'), [
            'class' => 'eai-audit-panel__note',
        ]);

        return html_writer::div($body, 'eai-audit-panel');
    }

    /**
     * Technical summary card.
     *
     * @param array $overview
     * @return string
     */
    public static function technical_panel(array $overview): string {
        $technical = html_writer::div(get_string('audit_state_eyebrow', 'local_elediaai_core'), 'lh-ai-audit-panel__eyebrow');
        $technical .= html_writer::tag('h2', get_string('audit_state_title', 'local_elediaai_core'), [
            'class' => 'lh-ai-audit-panel__title',
        ]);
        $technical .= html_writer::tag('p', get_string('audit_state_intro', 'local_elediaai_core'), [
            'class' => 'lh-ai-audit-panel__body',
        ]);
        $metrics = self::metric(
            get_string('audit_metric_requests', 'local_elediaai_core'),
            number_format((int) $overview['total'], 0, ',', ' ')
        );
        $metrics .= self::metric(
            get_string('audit_metric_failed', 'local_elediaai_core'),
            number_format((int) $overview['failed'], 0, ',', ' '),
            null,
            null,
            (int) $overview['failed'] > 0 ? 'warn' : 'quiet'
        );
        $metrics .= self::metric(
            get_string('audit_metric_support', 'local_elediaai_core'),
            number_format((int) $overview['support'], 0, ',', ' ')
        );
        $metrics .= self::metric(
            get_string('audit_metric_tokens', 'local_elediaai_core'),
            audit_config::show_tokens()
                ? number_format((int) $overview['tokens'], 0, ',', ' ')
                : get_string('audit_metric_hidden', 'local_elediaai_core')
        );
        $technical .= html_writer::div($metrics, 'eai-metrics');

        return html_writer::div($technical, 'lh-ai-audit-panel');
    }
}
