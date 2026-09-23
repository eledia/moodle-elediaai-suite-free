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
 * Rendering for the course insights page.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_elediaai_core\local;

use html_writer;
use moodle_url;
use stdClass;

/**
 * The teacher's view of what their learners asked.
 *
 * The report this replaces counted questions. Counting alone tells a teacher
 * what they already suspect; what they cannot see is which of those questions
 * their own material failed to answer. So the gap list leads, and everything
 * else is context for it.
 *
 * **Every number here is also a picture.** A share that is only written out as
 * „55 %" makes the reader do the comparing; a bar does it for them. That is why
 * the metric tiles carry their own bar, the gap rows show covered against
 * uncovered in one track, and the fortnight is a real column chart with its peak
 * marked. None of it is decoration: each shape encodes the one number the row is
 * about, and nothing is drawn that does not.
 */
final class insights_page {
    /**
     * Static-only.
     */
    private function __construct() {
    }

    /**
     * The four headline numbers, each with its own picture.
     *
     * @param stdClass $summary Result of {@see insights::summary()}.
     * @param int $days Period in days, for the caption.
     * @return string
     */
    public static function metrics(stdClass $summary, int $days = 30): string {
        $cards = audit_page::metric(
            get_string('insights_metric_questions', 'local_elediaai_core'),
            number_format($summary->total, 0, ',', ' '),
            get_string('insights_metric_questions_hint', 'local_elediaai_core', $days)
        );
        $cards .= audit_page::metric(
            get_string('insights_metric_askers', 'local_elediaai_core'),
            number_format($summary->askers, 0, ',', ' '),
            get_string('insights_metric_askers_hint', 'local_elediaai_core')
        );
        // Der Anteil ist die Kennzahl, wegen der die Seite existiert -- sie
        // bekommt als einzige einen zweifarbigen Balken, weil sie eine
        // Aufteilung ist und keine Menge.
        $cards .= audit_page::metric(
            get_string('insights_metric_grounded', 'local_elediaai_core'),
            $summary->groundedpct . ' <span class="eai-metric__unit">%</span>',
            null,
            audit_page::split_bar(
                $summary->groundedpct,
                get_string('insights_metric_grounded_label', 'local_elediaai_core', [
                    'covered' => $summary->groundedpct,
                    'open' => 100 - $summary->groundedpct,
                ])
            ),
            'ok'
        );
        $cards .= audit_page::metric(
            get_string('insights_metric_followup', 'local_elediaai_core'),
            $summary->followuppct . ' <span class="eai-metric__unit">%</span>',
            get_string('insights_metric_followup_hint', 'local_elediaai_core'),
            null,
            $summary->followuppct >= 25 ? 'warn' : 'quiet'
        );

        return html_writer::div($cards, 'eai-metrics');
    }

    /**
     * Gefragt, aber vom Material nicht beantwortet.
     *
     * @param array $gaps Result of {@see insights::gaps()}.
     * @param \context $context Course context, for the tool links.
     * @return string
     */
    public static function gaps(array $gaps, \context $context): string {
        $body = audit_page::section_heading(
            'insights_gaps_eyebrow',
            'insights_gaps_title',
            'insights_gaps_intro'
        );

        if ($gaps === []) {
            $body .= self::empty_state('insights_gaps_empty');
            return html_writer::div($body, 'eai-audit-panel');
        }

        $rows = '';
        foreach ($gaps as $gap) {
            $rows .= self::gap_row($gap);
        }
        $body .= html_writer::div($rows, 'eai-insights-gaps');
        $body .= self::tool_links($context);

        return html_writer::div($body, 'eai-audit-panel');
    }

    /**
     * One row of the gap list.
     *
     * @param stdClass $gap
     * @return string
     */
    private static function gap_row(stdClass $gap): string {
        // Material gilt als vorhanden, sobald eine Quelle genannt ist -- die
        // cmid fehlt oft, weil nicht jede Quelle ein Kursmodul ist. Vorher hing
        // die Aussage allein an der cmid und log damit.
        $hassource = ($gap->sourcetitle ?? '') !== '';
        $meta = $hassource
            ? get_string('insights_gap_hassource', 'local_elediaai_core', s((string) $gap->sourcetitle))
            : get_string('insights_gap_nosource', 'local_elediaai_core');

        $label = html_writer::div(s((string) $gap->label), 'eai-insights-gap__label');
        $label .= html_writer::div($meta, 'eai-insights-gap__meta');

        $covered = max(0, 100 - (int) $gap->ungroundedpct);
        $bar = html_writer::div('', 'eai-splitbar__covered', ['style' => 'width: ' . $covered . '%;']);
        $bar .= html_writer::div('', 'eai-splitbar__open', ['style' => 'width: ' . (int) $gap->ungroundedpct . '%;']);
        $track = html_writer::div($bar, 'eai-splitbar eai-splitbar--wide', [
            'role' => 'img',
            'aria-label' => get_string('insights_gap_share_label', 'local_elediaai_core', [
                'topic' => (string) $gap->label,
                'percent' => (int) $gap->ungroundedpct,
            ]),
        ]);
        $share = html_writer::div(
            get_string('insights_gap_share', 'local_elediaai_core', (int) $gap->ungroundedpct),
            'eai-insights-gap__share'
        );

        $numbers = html_writer::div((string) $gap->total, 'eai-insights-gap__count');
        $numbers .= html_writer::div(
            get_string('insights_gap_askers', 'local_elediaai_core', (int) $gap->askers),
            'eai-insights-gap__askers'
        );

        // Die halbe Schwelle faerbt die Zeile. Kein Rahmen links als
        // Bedeutungstraeger -- der liest sich wie Dekoration.
        $klasse = 'eai-insights-gap';
        if ((int) $gap->ungroundedpct >= 50) {
            $klasse .= ' eai-insights-gap--open';
        }

        return html_writer::div(
            html_writer::div($label, 'eai-insights-gap__head')
                . html_writer::div($track . $share, 'eai-insights-gap__bar')
                . html_writer::div($numbers, 'eai-insights-gap__numbers'),
            $klasse
        );
    }

    /**
     * Which suite tools can turn a gap into material.
     *
     * The core does not know its plugins, so it holds no list of them. It asks
     * the registry for the features that describe *themselves* as tools for
     * people who build course material. A plugin that arrives later appears here
     * without this file being touched.
     *
     * @param \context $context
     * @return string
     */
    private static function tool_links(\context $context): string {
        $links = [];
        try {
            $designer = \local_elediaai_core\feature\registry::AUDIENCE_DESIGNER;
            foreach (\local_elediaai_core\feature\registry::visible($context) as $descriptor) {
                if (
                    $descriptor->audience !== $designer
                    || $descriptor->launchurl === null
                    || $descriptor->comingsoon
                ) {
                    continue;
                }
                $links[] = html_writer::link($descriptor->launchurl, s($descriptor->name), [
                    'class' => 'eai-insights-tool',
                ]);
            }
        } catch (\Throwable $e) {
            unset($e);
        }

        if ($links === []) {
            return '';
        }

        return html_writer::div(
            html_writer::tag('span', get_string('insights_tools_hint', 'local_elediaai_core'), [
                'class' => 'eai-insights-tools__hint',
            ]) . implode('', $links),
            'eai-insights-tools'
        );
    }

    /**
     * Material vorhanden, traegt aber nicht.
     *
     * @param array $unclear Result of {@see insights::unclear()}.
     * @return string
     */
    public static function unclear(array $unclear): string {
        $body = audit_page::section_heading(
            'insights_unclear_eyebrow',
            'insights_unclear_title',
            'insights_unclear_intro'
        );

        if ($unclear === []) {
            $body .= self::empty_state('insights_unclear_empty');
            return html_writer::div($body, 'eai-audit-panel');
        }

        $rows = '';
        foreach ($unclear as $item) {
            // Ueberschrift ist das THEMA, nicht die Quelle. Vorher stand bei
            // zwei Themen aus demselben Kapitel zweimal derselbe Titel da, und
            // die Zeile sagte nichts mehr.
            $heading = html_writer::div(s((string) $item->label), 'eai-insights-unclear__label');
            $quelle = (string) ($item->sourcetitle ?? '');
            if ($quelle !== '') {
                $heading .= html_writer::div(
                    get_string('insights_unclear_source', 'local_elediaai_core', [
                        'material' => $item->cmid !== null && $item->cmid > 0
                            ? self::module_link((int) $item->cmid, $quelle)
                            : s($quelle),
                    ]),
                    'eai-insights-unclear__source'
                );
            }

            $fill = html_writer::div('', 'eai-insights-unclear__fill', [
                'style' => 'width: ' . (int) $item->followuppct . '%;',
            ]);
            $track = html_writer::div($fill, 'eai-insights-unclear__track', [
                'role' => 'img',
                'aria-label' => get_string('insights_unclear_share_label', 'local_elediaai_core', [
                    'topic' => (string) $item->label,
                    'percent' => (int) $item->followuppct,
                ]),
            ]);

            $rows .= html_writer::div(
                html_writer::div(
                    $heading . html_writer::div(
                        get_string('insights_unclear_share', 'local_elediaai_core', (int) $item->followuppct),
                        'eai-insights-unclear__share'
                    ),
                    'eai-insights-unclear__head'
                )
                    . $track
                    . html_writer::div(
                        get_string('insights_unclear_meta', 'local_elediaai_core', [
                            'questions' => (int) $item->total,
                            'askers' => (int) $item->askers,
                        ]),
                        'eai-insights-unclear__meta'
                    ),
                'eai-insights-unclear'
            );
        }
        $body .= html_writer::div($rows, 'eai-insights-unclears');

        return html_writer::div($body, 'eai-audit-panel');
    }

    /**
     * Questions per day, as a column chart that can be read.
     *
     * The previous version drew identical grey boxes: every day looked the same,
     * there was no scale and no way to tell the busiest day from the quietest.
     * A picture that does not encode its number is decoration.
     *
     * @param array<string, int> $series Result of {@see insights::per_day()}.
     * @return string
     */
    public static function trend(array $series): string {
        $body = audit_page::section_heading(
            'insights_trend_eyebrow',
            'insights_trend_title',
            'insights_trend_intro'
        );

        if ($series === [] || array_sum($series) === 0) {
            $body .= self::empty_state('insights_trend_empty');
            return html_writer::div($body, 'eai-audit-panel');
        }

        $max = max($series);
        $peakday = array_search($max, $series, true);
        $columns = '';
        foreach ($series as $day => $count) {
            $height = $max > 0 && $count > 0 ? max(3, (int) round($count * 100 / $max)) : 0;
            $ispeak = $day === $peakday && $count > 0;
            $fill = html_writer::div('', 'eai-chart__fill' . ($ispeak ? ' eai-chart__fill--peak' : ''), [
                'style' => 'height: ' . $height . '%;',
            ]);
            // Der Wert steht ueber der hoechsten Saeule; alle anderen tragen ihn
            // im Titel, damit die Flaeche ruhig bleibt und trotzdem lesbar ist.
            $value = $ispeak
                ? html_writer::div((string) $count, 'eai-chart__value')
                : '';
            $columns .= html_writer::div(
                $value . $fill,
                'eai-chart__column',
                [
                    'title' => userdate(strtotime((string) $day), get_string('strftimedateshort', 'langconfig'))
                        . ': ' . $count,
                ]
            );
        }

        $chart = html_writer::div($columns, 'eai-chart__plot');
        $chart .= html_writer::div('', 'eai-chart__baseline');
        $days = array_keys($series);
        $chart .= html_writer::div(
            html_writer::tag('span', userdate(strtotime((string) reset($days)), get_string('strftimedateshort', 'langconfig')))
                . html_writer::tag('span', userdate(strtotime((string) end($days)), get_string('strftimedateshort', 'langconfig'))),
            'eai-chart__axis'
        );

        $body .= html_writer::div($chart, 'eai-chart', [
            'role' => 'img',
            'aria-label' => get_string('insights_trend_label', 'local_elediaai_core', [
                'total' => array_sum($series),
                'days' => count($series),
            ]),
        ]);

        if ($peakday !== false && $max > 0) {
            $body .= html_writer::div(
                html_writer::tag('span', '', ['class' => 'eai-chart__key'])
                    . get_string('insights_trend_peak', 'local_elediaai_core', [
                        'day' => userdate(strtotime((string) $peakday), get_string('strftimedaydate', 'langconfig')),
                        'count' => $max,
                    ]),
                'eai-chart__note'
            );
        }

        return html_writer::div($body, 'eai-audit-panel');
    }

    /**
     * The questions in their own words, without names.
     *
     * @param array $recent Result of {@see insights::recent()}.
     * @param int $retentiondays Configured retention, for the footnote.
     * @return string
     */
    public static function verbatim(array $recent, int $retentiondays): string {
        $body = audit_page::section_heading(
            'insights_verbatim_eyebrow',
            'insights_verbatim_title',
            'insights_verbatim_intro'
        );
        $body .= html_writer::div(
            html_writer::span(
                get_string('insights_verbatim_badge', 'local_elediaai_core'),
                'eai-badge eai-badge--ok'
            ),
            'eai-insights-verbatim__badge'
        );

        if ($recent === []) {
            $body .= self::empty_state('insights_verbatim_empty');
            return html_writer::div($body, 'eai-audit-panel');
        }

        $rows = '';
        foreach ($recent as $row) {
            $grounded = $row->origin === turn_recorder::ORIGIN_GROUNDED;
            $badge = html_writer::span(
                get_string(
                    $grounded ? 'insights_covered' : 'insights_notcovered',
                    'local_elediaai_core'
                ),
                'eai-badge ' . ($grounded ? 'eai-badge--ok' : 'eai-badge--warn')
            );
            $rows .= html_writer::div(
                html_writer::div(
                    userdate((int) $row->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                    'eai-insights-question__when'
                )
                    . html_writer::div(s((string) $row->prompt), 'eai-insights-question__text')
                    . html_writer::div($badge, 'eai-insights-question__badge'),
                'eai-insights-question'
            );
        }
        $body .= html_writer::div($rows, 'eai-insights-questions');
        $body .= html_writer::tag(
            'p',
            $retentiondays > 0
                ? get_string('insights_retention_note', 'local_elediaai_core', $retentiondays)
                : get_string('insights_retention_note_forever', 'local_elediaai_core'),
            ['class' => 'eai-audit-panel__note']
        );

        return html_writer::div($body, 'eai-audit-panel');
    }

    /**
     * An empty state that says why it is empty.
     *
     * @param string $stringid
     * @return string
     */
    private static function empty_state(string $stringid): string {
        return html_writer::div(
            get_string($stringid, 'local_elediaai_core'),
            'eai-audit-panel__empty'
        );
    }

    /**
     * A course module link, or the plain title when the module is gone.
     *
     * @param int $cmid
     * @param string $title
     * @return string
     */
    private static function module_link(int $cmid, string $title): string {
        try {
            [, $cm] = get_course_and_cm_from_cmid($cmid);
            if ($cm->url) {
                return html_writer::link($cm->url, s($title));
            }
        } catch (\Throwable $e) {
            unset($e);
        }
        return s($title);
    }
}
