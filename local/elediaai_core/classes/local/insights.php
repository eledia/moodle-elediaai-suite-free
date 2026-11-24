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
 * Public read API over the anonymous turn log.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_elediaai_core\local;

use stdClass;

/**
 * What the turn log can say about a course, without naming anybody.
 *
 * The one way in for everything outside this plugin: the course report, the two
 * MCP tools, the copilot. Nothing reaches the table directly across a plugin
 * boundary, and nothing here takes a plugin name as an argument — the core does
 * not know its plugins (06.09.2026).
 *
 * **The leading question changed.** The report this replaces counted what was
 * asked. Counting alone tells a teacher what they already suspect. What they
 * cannot see is which of those questions their material failed to answer, and
 * that is what {@see gaps()} ranks: by the share of ungrounded answers, not by
 * volume. A topic asked eleven times that the course never covers outranks one
 * asked forty times that the script answers well.
 *
 * **Two failures that look the same and are not.** A topic with no source at
 * all means content is missing. A topic that *has* a source and is still asked
 * again minutes later means the content is there but unclear. {@see gaps()}
 * finds the first, {@see unclear()} the second, and conflating them sends a
 * teacher to write material they already wrote.
 */
final class insights {
    /** @var int Minutes within which a second question counts as a follow-up. */
    public const FOLLOWUP_WINDOW_MINUTES = 30;

    /** @var int Default period in days. */
    public const DEFAULT_DAYS = 30;

    /**
     * The action name of a chat turn, as the chat engine records it.
     *
     * The turn table holds every AI request of the suite: chat turns, and
     * since the credit chokepoint also every core_ai call a feature makes --
     * the question generator's prompt among them. The insights are about what
     * learners asked, so they read chat turns only (#170). Before, a teacher
     * found the generator prompt "Generate 5 quiz questions ... Return ONLY
     * Moodle question XML" listed as a learner's question.
     */
    public const ACTION_CHAT = 'chat_turn';

    /**
     * The action name of a decision turn: the answer to a confirmation card.
     *
     * The chat engine records a click on "Ja, ausfuehren" / "Nein" as its own
     * action (#210). It still counts against the credit, but it is no
     * question, so nothing here reads it: not the counters, not the topics,
     * not the recluster batch.
     */
    public const ACTION_DECISION = 'chat_decision';

    /**
     * The reserved topic "fits no topic" (#210).
     *
     * The recluster agent gives it to greetings, thanks, confirmations, meta
     * questions and test input. It is a real label rather than NULL on
     * purpose: NULL means "not classified yet" and would be sent again every
     * night. Every reader in this class treats it as no topic at all -- it is
     * left out of gaps(), unclear(), the follow-up count, distinct_topics()
     * and labels_with_examples() -- and summary() reports it as a side figure
     * (`notopic`). Where a label is shown to a person, {@see topic_label()}
     * turns it into "Ohne Thema".
     */
    public const TOPIC_NONE = '__none__';

    /** @var int Example questions per label in {@see labels_with_examples()}. */
    public const LABEL_EXAMPLES = 3;

    /**
     * Static-only.
     */
    private function __construct() {
    }

    /**
     * Headline numbers for a course.
     *
     * @param int $courseid Course id, 0 for site-wide surfaces.
     * @param int $days Period in days.
     * @return stdClass total, askers, grounded, groundedpct, followups, followuppct, notopic
     *         (turns the agent marked as fitting no topic, {@see TOPIC_NONE})
     */
    public static function summary(int $courseid, int $days = self::DEFAULT_DAYS): stdClass {
        global $DB;

        [$where, $params] = self::scope($courseid, $days);

        $row = $DB->get_record_sql(
            "SELECT COUNT(1) AS total,
                    COUNT(DISTINCT CASE WHEN askerkey <> :emptykey THEN askerkey END) AS askers,
                    SUM(CASE WHEN origin = :grounded THEN 1 ELSE 0 END) AS grounded,
                    SUM(CASE WHEN topic = :topicnone THEN 1 ELSE 0 END) AS notopic
               FROM {" . turn_recorder::TABLE . "}
              WHERE {$where}",
            $params + [
                'emptykey' => '',
                'grounded' => turn_recorder::ORIGIN_GROUNDED,
                'topicnone' => self::TOPIC_NONE,
            ]
        );

        $out = new stdClass();
        $out->total = (int) ($row->total ?? 0);
        $out->askers = (int) ($row->askers ?? 0);
        $out->grounded = (int) ($row->grounded ?? 0);
        $out->notopic = (int) ($row->notopic ?? 0);
        $out->groundedpct = $out->total > 0
            ? (int) round($out->grounded * 100 / $out->total)
            : 0;
        $out->followups = self::followup_count($courseid, $days);
        $out->followuppct = $out->total > 0
            ? (int) round($out->followups * 100 / $out->total)
            : 0;

        return $out;
    }

    /**
     * Topics the course material did not answer, worst share first.
     *
     * @param int $courseid
     * @param int $days
     * @param int $limit
     * @return array<int, stdClass> label, total, askers, ungrounded, ungroundedpct, cmid, sourcetitle
     */
    public static function gaps(int $courseid, int $days = self::DEFAULT_DAYS, int $limit = 10): array {
        global $DB;

        [$where, $params] = self::scope($courseid, $days);
        // Zwei Namen fuer denselben Wert, weil Moodles Treiber benannte
        // Platzhalter in positionale uebersetzt und dabei Vorkommen zaehlt,
        // nicht Namen: ein zweimal verwendeter Name bricht mit "Incorrect
        // number of query parameters".
        $params += [
            'emptykey' => '',
            'grounded' => turn_recorder::ORIGIN_GROUNDED,
            'groundedsort' => turn_recorder::ORIGIN_GROUNDED,
            'groundedhaving' => turn_recorder::ORIGIN_GROUNDED,
            'minimum' => 3,
            'topicnone' => self::TOPIC_NONE,
        ];

        // Two filters, both load-bearing. Asked at least a handful of times: a
        // single ungrounded question is 100 % ungrounded and would top the list
        // without meaning anything. And at least one uncovered answer: a topic
        // the material answers every time is not a gap, and listing it at 0 %
        // makes the reader doubt the list.
        $rows = $DB->get_records_sql(
            "SELECT topic AS label,
                    COUNT(1) AS total,
                    COUNT(DISTINCT CASE WHEN askerkey <> :emptykey THEN askerkey END) AS askers,
                    SUM(CASE WHEN origin <> :grounded THEN 1 ELSE 0 END) AS ungrounded,
                    MAX(cmid) AS cmid,
                    MAX(sourcetitle) AS sourcetitle
               FROM {" . turn_recorder::TABLE . "}
              WHERE {$where}
                AND topic IS NOT NULL
                AND topic <> :topicnone
           GROUP BY topic
             HAVING COUNT(1) >= :minimum
                AND SUM(CASE WHEN origin <> :groundedhaving THEN 1 ELSE 0 END) > 0
           ORDER BY SUM(CASE WHEN origin <> :groundedsort THEN 1 ELSE 0 END) DESC,
                    COUNT(1) DESC",
            $params,
            0,
            max(1, $limit)
        );

        $out = [];
        foreach ($rows as $row) {
            $total = (int) $row->total;
            $row->total = $total;
            $row->askers = (int) $row->askers;
            $row->ungrounded = (int) $row->ungrounded;
            $row->ungroundedpct = $total > 0 ? (int) round($row->ungrounded * 100 / $total) : 0;
            $row->cmid = $row->cmid !== null ? (int) $row->cmid : null;
            $out[] = $row;
        }

        // The share decides, not the volume -- that is the whole point of this
        // list. Sorted in PHP because the share is derived and not every
        // supported database allows a HAVING alias in ORDER BY.
        usort($out, static function (stdClass $a, stdClass $b): int {
            return [$b->ungroundedpct, $b->ungrounded] <=> [$a->ungroundedpct, $a->ungrounded];
        });

        return $out;
    }

    /**
     * Topics whose material exists but is asked again anyway.
     *
     * @param int $courseid
     * @param int $days
     * @param int $limit
     * @return array<int, stdClass> label, total, askers, followups, followuppct, cmid, sourcetitle
     */
    public static function unclear(int $courseid, int $days = self::DEFAULT_DAYS, int $limit = 10): array {
        global $DB;

        [$where, $params] = self::scope($courseid, $days, 't');
        $window = self::FOLLOWUP_WINDOW_MINUTES * MINSECS;
        $params += [
            'emptykey' => '',
            'grounded' => turn_recorder::ORIGIN_GROUNDED,
            'window' => $window,
            'minimum' => 3,
            // Zweiter Satz Namen fuer dieselbe Bedingung im HAVING. Moodles
            // Treiber uebersetzt benannte Platzhalter in positionale und
            // zaehlt dabei Vorkommen, nicht Namen: derselbe Name zweimal
            // bricht mit "Incorrect number of query parameters".
            'emptykeyhaving' => '',
            'windowhaving' => $window,
            'topicnone' => self::TOPIC_NONE,
        ];

        $table = '{' . turn_recorder::TABLE . '}';
        $exists = static function (string $leer, string $fenster) use ($table): string {
            return "EXISTS (
                        SELECT 1
                          FROM {$table} f
                         WHERE f.topic = t.topic
                           AND f.courseid = t.courseid
                           AND f.askerkey = t.askerkey
                           AND f.askerkey <> :{$leer}
                           AND f.id <> t.id
                           AND f.timecreated > t.timecreated
                           AND f.timecreated <= t.timecreated + :{$fenster}
                    )";
        };
        $followed = $exists('emptykey', 'window');
        $followedhaving = $exists('emptykeyhaving', 'windowhaving');

        $rows = $DB->get_records_sql(
            "SELECT MIN(t.id) AS rowid,
                    t.topic AS label,
                    COUNT(1) AS total,
                    COUNT(DISTINCT t.askerkey) AS askers,
                    SUM(CASE WHEN {$followed} THEN 1 ELSE 0 END) AS followups,
                    MAX(t.cmid) AS cmid,
                    MAX(t.sourcetitle) AS sourcetitle
               FROM {$table} t
              WHERE {$where}
                AND t.topic IS NOT NULL
                AND t.topic <> :topicnone
                AND t.origin = :grounded
           GROUP BY t.topic, t.courseid
             HAVING COUNT(1) >= :minimum
                AND SUM(CASE WHEN {$followedhaving} THEN 1 ELSE 0 END) > 0
           ORDER BY COUNT(1) DESC",
            $params,
            0,
            max(1, $limit)
        );

        $out = [];
        foreach ($rows as $row) {
            $total = (int) $row->total;
            $row->total = $total;
            $row->askers = (int) $row->askers;
            $row->followups = (int) $row->followups;
            $row->followuppct = $total > 0 ? (int) round($row->followups * 100 / $total) : 0;
            $row->cmid = $row->cmid !== null ? (int) $row->cmid : null;
            $out[] = $row;
        }

        usort($out, static function (stdClass $a, stdClass $b): int {
            return [$b->followuppct, $b->total] <=> [$a->followuppct, $a->total];
        });

        return $out;
    }

    /**
     * Questions per calendar day, oldest first, gaps filled with zero.
     *
     * @param int $courseid
     * @param int $days
     * @return array<string, int> Keyed by Y-m-d.
     */
    public static function per_day(int $courseid, int $days = 14): array {
        global $DB;

        $days = max(1, $days);
        [$where, $params] = self::scope($courseid, $days);

        // Hier get_fieldset_sql, nicht get_records_sql: letzteres schluesselt das
        // Ergebnis nach der ersten Spalte und wirft, sobald sie doppelt
        // vorkommt. Zwei Turns in derselben Sekunde reichen -- und genau das
        // ist der Normalfall, wenn eine Klasse gleichzeitig fragt.
        $stamps = $DB->get_fieldset_sql(
            "SELECT timecreated FROM {" . turn_recorder::TABLE . "} WHERE {$where}",
            $params
        );

        $out = [];
        $timezone = \core_date::get_user_timezone_object();
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = new \DateTime('now', $timezone);
            $day->modify('-' . $i . ' days');
            // Not userdate('%Y-%m-%d'): it drops the leading zero, and
            // '2026-08-6' sorts behind '2026-08-29' as a string.
            $out[$day->format('Y-m-d')] = 0;
        }
        foreach ($stamps as $stamp) {
            $day = new \DateTime('@' . (int) $stamp);
            $day->setTimezone($timezone);
            $key = $day->format('Y-m-d');
            if (array_key_exists($key, $out)) {
                $out[$key]++;
            }
        }

        return $out;
    }

    /**
     * The most recent questions, newest first.
     *
     * Returns the question, never the answer, and never the pseudonym: a
     * teacher reading a list of questions has no business receiving a key that
     * groups them by person, even an unresolvable one.
     *
     * @param int $courseid
     * @param int $days
     * @param int $limit
     * @param int $offset
     * @return array<int, stdClass> timecreated, prompt, origin, topic, sourcetitle, cmid
     */
    public static function recent(
        int $courseid,
        int $days = self::DEFAULT_DAYS,
        int $limit = 20,
        int $offset = 0
    ): array {
        global $DB;

        [$where, $params] = self::scope($courseid, $days);

        return array_values($DB->get_records_sql(
            "SELECT id, timecreated, prompt, origin, topic, sourcetitle, cmid
               FROM {" . turn_recorder::TABLE . "}
              WHERE {$where}
           ORDER BY timecreated DESC, id DESC",
            $params,
            max(0, $offset),
            max(1, $limit)
        ));
    }

    /**
     * Courses with at least one recorded turn in the period.
     *
     * @param int $days
     * @return int[]
     */
    public static function active_courses(int $days = self::DEFAULT_DAYS): array {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT DISTINCT courseid
               FROM {" . turn_recorder::TABLE . "}
              WHERE courseid > 0
                AND timecreated >= :cutoff
                AND actionname = :chataction
                AND success = :success",
            ['cutoff' => self::cutoff($days), 'chataction' => self::ACTION_CHAT, 'success' => 1]
        );

        return array_map('intval', array_keys($rows));
    }

    /**
     * Token usage per suite feature over the period.
     *
     * The question „welches Feature wird eigentlich benutzt?" stood on the audit
     * tile as a promise and was unanswerable: Moodle's `ai_action_register` has
     * no `component` column, so every plugin using the same provider looks the
     * same in it.
     *
     * It is answerable **here** and nowhere else in the suite. The credit ledger
     * also carries a `component`, which is why it looked like the obvious
     * source — but its unique index is (userid, rolebucket, windowtype,
     * windowstart) **without** the component. There is one row per person and
     * window, and the column is overwritten by whichever feature booked last.
     * Its own privacy string says so: „the component that last updated the
     * quota row". Aggregating it produces a plausible-looking answer that is
     * simply wrong.
     *
     * This table has one row per turn, so the sum is the real one.
     *
     * @param int $days Period in days.
     * @return array<int, array{component: string, tokens: int, requests: int}>
     */
    public static function usage_by_component(int $days = self::DEFAULT_DAYS): array {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT component,
                    SUM(prompttokens) + SUM(completiontokens) AS totaltokens,
                    COUNT(1) AS requests
               FROM {" . turn_recorder::TABLE . "}
              WHERE timecreated >= :cutoff
           GROUP BY component
           ORDER BY SUM(prompttokens) + SUM(completiontokens) DESC, component ASC",
            ['cutoff' => self::cutoff($days)]
        );

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'component' => (string) $row->component,
                'tokens' => (int) $row->totaltokens,
                'requests' => (int) $row->requests,
            ];
        }
        return $out;
    }

    /**
     * Courses with AI activity, richest first, for the course chooser.
     *
     * The entry point a teacher needs and the audit never had: which of my
     * courses has anything to look at. Without it the insights page is only
     * reachable from inside a course, and whoever oversees several has to walk
     * them one by one.
     *
     * @param int $days Period in days.
     * @param int $limit
     * @return array<int, stdClass> courseid, questions, askers, uncovered, uncoveredpct
     */
    public static function courses_with_activity(int $days = self::DEFAULT_DAYS, int $limit = 50): array {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT courseid,
                    COUNT(1) AS questions,
                    COUNT(DISTINCT CASE WHEN askerkey <> :emptykey THEN askerkey END) AS askers,
                    SUM(CASE WHEN origin <> :grounded THEN 1 ELSE 0 END) AS uncovered
               FROM {" . turn_recorder::TABLE . "}
              WHERE timecreated >= :cutoff
                AND courseid > 0
                AND actionname = :chataction
                AND success = :success
           GROUP BY courseid
           ORDER BY COUNT(1) DESC",
            [
                'cutoff' => self::cutoff($days),
                'chataction' => self::ACTION_CHAT,
                'success' => 1,
                'emptykey' => '',
                'grounded' => turn_recorder::ORIGIN_GROUNDED,
            ],
            0,
            max(1, $limit)
        );

        $out = [];
        foreach ($rows as $row) {
            $row->courseid = (int) $row->courseid;
            $row->questions = (int) $row->questions;
            $row->askers = (int) $row->askers;
            $row->uncovered = (int) $row->uncovered;
            $row->uncoveredpct = $row->questions > 0
                ? (int) round($row->uncovered * 100 / $row->questions)
                : 0;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * The topic labels already in use in a course.
     *
     * Part of the re-clustering loop: the backend gets the existing vocabulary
     * so it merges „Essay-Abgabe" into „Abgabe Essay 2" instead of inventing a
     * third name for the same thing.
     *
     * Without {@see TOPIC_NONE}: "fits no topic" is a rule the agent applies,
     * not a word of the course's vocabulary. Only answered questions count.
     *
     * @param int $courseid
     * @param int $limit
     * @return string[]
     */
    public static function distinct_topics(int $courseid, int $limit = 100): array {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT DISTINCT topic
               FROM {" . turn_recorder::TABLE . "}
              WHERE courseid = :courseid
                AND topic IS NOT NULL
                AND topic <> :topicnone
                AND actionname = :chataction
                AND success = :success",
            [
                'courseid' => $courseid,
                'topicnone' => self::TOPIC_NONE,
                'chataction' => self::ACTION_CHAT,
                'success' => 1,
            ],
            0,
            max(1, $limit)
        );

        return array_values(array_map('strval', array_keys($rows)));
    }

    /**
     * The topic labels of a course with how often each is used and what it means.
     *
     * The recluster agent classifies into this set (#210). A bare list of
     * names let a label stretch to anything that sounded vaguely like it; a
     * few questions per label are the anchor for what the label actually
     * covers. Most used first, so a cap keeps the labels that matter.
     *
     * Same rules as {@see distinct_topics()}: answered chat questions only,
     * without {@see TOPIC_NONE}, over everything the log still holds for the
     * course (the retention decides how far back that is). The examples are
     * the most recent questions of the label, trimmed and clipped to 300
     * characters -- question text only, never the answer or the pseudonym.
     *
     * @param int $courseid
     * @param int $limit Maximum number of labels.
     * @param int $examples Example questions per label.
     * @return array<int, array{label: string, count: int, examples: string[]}>
     */
    public static function labels_with_examples(
        int $courseid,
        int $limit = 100,
        int $examples = self::LABEL_EXAMPLES
    ): array {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT topic, COUNT(1) AS uses
               FROM {" . turn_recorder::TABLE . "}
              WHERE courseid = :courseid
                AND topic IS NOT NULL
                AND topic <> :topicnone
                AND actionname = :chataction
                AND success = :success
           GROUP BY topic
           ORDER BY COUNT(1) DESC, topic ASC",
            [
                'courseid' => $courseid,
                'topicnone' => self::TOPIC_NONE,
                'chataction' => self::ACTION_CHAT,
                'success' => 1,
            ],
            0,
            max(1, $limit)
        );

        $notempty = $DB->sql_isnotempty(turn_recorder::TABLE, 'prompt', true, true);
        $out = [];
        foreach ($rows as $row) {
            $texts = [];
            if ($examples > 0) {
                // A few more rows than needed, because duplicates are skipped:
                // "Wann ist die Abgabe?" three times anchors nothing.
                $prompts = $DB->get_records_select(
                    turn_recorder::TABLE,
                    "courseid = :courseid AND topic = :topic AND actionname = :chataction
                        AND success = :success AND prompt IS NOT NULL AND {$notempty}",
                    [
                        'courseid' => $courseid,
                        'topic' => (string) $row->topic,
                        'chataction' => self::ACTION_CHAT,
                        'success' => 1,
                    ],
                    'id DESC',
                    'id, prompt',
                    0,
                    $examples * 4
                );
                foreach ($prompts as $prompt) {
                    $text = \core_text::substr(trim((string) $prompt->prompt), 0, 300);
                    if ($text === '' || in_array($text, $texts, true)) {
                        continue;
                    }
                    $texts[] = $text;
                    if (count($texts) >= $examples) {
                        break;
                    }
                }
            }
            $out[] = [
                'label' => (string) $row->topic,
                'count' => (int) $row->uses,
                'examples' => $texts,
            ];
        }

        return $out;
    }

    /**
     * A topic label as a person should read it.
     *
     * {@see TOPIC_NONE} is a technical marker and reads as "Ohne Thema"; any
     * other label is returned trimmed, an empty one stays empty so the caller
     * decides how to show "nothing".
     *
     * @param string|null $topic Stored label.
     * @return string Plain text, not escaped.
     */
    public static function topic_label(?string $topic): string {
        $topic = trim((string) $topic);
        if ($topic === self::TOPIC_NONE) {
            return get_string('insights_topic_none', 'local_elediaai_core');
        }
        return $topic;
    }

    /**
     * A batch of questions for re-clustering: id and text, nothing else.
     *
     * Deliberately not the answer and not the pseudonym. What leaves the site
     * here is the smallest thing the backend needs to group questions.
     *
     * Only answered turns with a question in them (#206). A refused turn is
     * logged on purpose with an empty prompt and success = 0 -- the code,
     * place and time are what a diagnosis needs -- but it is no question, and
     * the backend rejected a whole batch for one empty text. The prompt is a
     * TEXT column, hence sql_isnotempty() and not a plain comparison.
     *
     * With $untopiconly only turns that have no topic yet (#210, variant B):
     * once labelled stays labelled, so the numbers a teacher sees do not jump
     * from night to night without new questions. {@see TOPIC_NONE} is a
     * label, so a turn marked as fitting no topic is not sent again either.
     *
     * $afterid pages by id rather than by offset. A caller that labels each
     * batch before asking for the next would otherwise skip rows: the
     * labelled ones drop out of the "no topic yet" set and every offset
     * shifts by a batch.
     *
     * @param int $courseid
     * @param int $days
     * @param int $limit
     * @param int $offset
     * @param bool $untopiconly Only turns whose topic is still NULL.
     * @param int $afterid Only turns with a higher id (cursor paging).
     * @return stdClass[] id, prompt -- oldest first.
     */
    public static function fetch_for_recluster(
        int $courseid,
        int $days,
        int $limit,
        int $offset = 0,
        bool $untopiconly = false,
        int $afterid = 0
    ): array {
        global $DB;

        $notempty = $DB->sql_isnotempty(turn_recorder::TABLE, 'prompt', true, true);
        $where = "courseid = :courseid AND timecreated >= :since AND actionname = :chataction
                AND success = :success AND prompt IS NOT NULL AND {$notempty}";
        $params = [
            'courseid' => $courseid,
            'since' => time() - max(1, $days) * DAYSECS,
            'chataction' => self::ACTION_CHAT,
            'success' => 1,
        ];
        if ($untopiconly) {
            $where .= ' AND topic IS NULL';
        }
        if ($afterid > 0) {
            $where .= ' AND id > :afterid';
            $params['afterid'] = $afterid;
        }

        return array_values($DB->get_records_select(
            turn_recorder::TABLE,
            $where,
            $params,
            'id ASC',
            'id, prompt',
            max(0, $offset),
            max(1, $limit)
        ));
    }

    /**
     * Apply re-clustered topic labels.
     *
     * Defensive by construction: only ids that were actually sent in the batch,
     * and that belong to the course, can be relabelled. A misbehaving backend
     * cannot reach rows it was never shown.
     *
     * @param array $map Turn id => topic label.
     * @param int $courseid The course the batch belongs to.
     * @param int[] $allowedids The ids that were sent.
     * @return int Rows updated.
     */
    public static function apply_topics(array $map, int $courseid, array $allowedids): int {
        global $DB;

        $allowed = array_flip(array_map('intval', $allowedids));
        $updated = 0;
        foreach ($map as $id => $topic) {
            $id = (int) $id;
            $topic = \core_text::substr(trim((string) $topic), 0, 255);
            if (!isset($allowed[$id]) || $topic === '') {
                continue;
            }
            $DB->set_field(
                turn_recorder::TABLE,
                'topic',
                $topic,
                ['id' => $id, 'courseid' => $courseid]
            );
            $updated++;
        }

        return $updated;
    }

    /**
     * How long turns are kept, in days. 0 means indefinitely.
     *
     * @return int
     */
    public static function retention_days(): int {
        $value = get_config('local_elediaai_core', 'turn_retentiondays');
        if ($value === false || $value === null || $value === '') {
            return 90;
        }
        return max(0, (int) $value);
    }

    /**
     * Delete turns older than the configured retention.
     *
     * @return int Rows removed.
     */
    public static function prune(): int {
        global $DB;

        $days = self::retention_days();
        if ($days <= 0) {
            return 0;
        }

        $cutoff = time() - ($days * DAYSECS);
        $count = $DB->count_records_select(
            turn_recorder::TABLE,
            'timecreated < :cutoff',
            ['cutoff' => $cutoff]
        );
        if ($count > 0) {
            $DB->delete_records_select(
                turn_recorder::TABLE,
                'timecreated < :cutoff',
                ['cutoff' => $cutoff]
            );
        }
        return $count;
    }

    /**
     * Delete every turn that a given person asked.
     *
     * The key is recomputed from the user id; it is never looked up. That is
     * what makes the pseudonym answerable without making it resolvable.
     *
     * @param int $userid
     * @return int Rows removed.
     */
    public static function delete_for_user(int $userid): int {
        global $DB;

        $key = pseudonym::for_user($userid);
        if ($key === '') {
            return 0;
        }

        $count = $DB->count_records(turn_recorder::TABLE, ['askerkey' => $key]);
        if ($count > 0) {
            $DB->delete_records(turn_recorder::TABLE, ['askerkey' => $key]);
        }
        return $count;
    }

    /**
     * Every turn a given person asked, for a data-subject request.
     *
     * @param int $userid
     * @return array<int, stdClass>
     */
    public static function turns_for_user(int $userid): array {
        global $DB;

        $key = pseudonym::for_user($userid);
        if ($key === '') {
            return [];
        }

        return array_values($DB->get_records(
            turn_recorder::TABLE,
            ['askerkey' => $key],
            'timecreated ASC'
        ));
    }

    /**
     * How many questions were followed by another on the same topic.
     *
     * @param int $courseid
     * @param int $days
     * @return int
     */
    private static function followup_count(int $courseid, int $days): int {
        global $DB;

        [$where, $params] = self::scope($courseid, $days, 't');
        $table = '{' . turn_recorder::TABLE . '}';
        $params += [
            'emptykey' => '',
            'window' => self::FOLLOWUP_WINDOW_MINUTES * MINSECS,
            'topicnone' => self::TOPIC_NONE,
        ];

        // Zwei Begruessungen in einer halben Stunde sind kein Nachfassen.
        return (int) $DB->get_field_sql(
            "SELECT COUNT(1)
               FROM {$table} t
              WHERE {$where}
                AND t.topic IS NOT NULL
                AND t.topic <> :topicnone
                AND t.askerkey <> :emptykey
                AND EXISTS (
                        SELECT 1
                          FROM {$table} f
                         WHERE f.topic = t.topic
                           AND f.courseid = t.courseid
                           AND f.askerkey = t.askerkey
                           AND f.id <> t.id
                           AND f.timecreated > t.timecreated
                           AND f.timecreated <= t.timecreated + :window
                    )",
            $params
        );
    }

    /**
     * Shared WHERE clause for course and period.
     *
     * @param int $courseid 0 means every course.
     * @param int $days
     * @param string $alias Table alias, or '' for none.
     * @return array{0:string,1:array}
     */
    private static function scope(int $courseid, int $days, string $alias = ''): array {
        $prefix = $alias === '' ? '' : $alias . '.';
        // Nur beantwortete Durchgaenge zaehlen als Frage (#206). Eine Ablehnung
        // -- keine Einwilligung, kein Zugriff, Kontingent erschoepft -- steht
        // mit leerem Prompt und success = 0 im Protokoll; als Frage gezaehlt
        // trieb sie die Fragenzahl hoch und senkte den Anteil gedeckter
        // Antworten, ohne dass jemand etwas gefragt haette.
        $where = "{$prefix}timecreated >= :cutoff AND {$prefix}actionname = :chataction"
            . " AND {$prefix}success = :success";
        $params = ['cutoff' => self::cutoff($days), 'chataction' => self::ACTION_CHAT, 'success' => 1];

        if ($courseid > 0) {
            $where .= " AND {$prefix}courseid = :courseid";
            $params['courseid'] = $courseid;
        }

        return [$where, $params];
    }

    /**
     * Start of the period.
     *
     * @param int $days
     * @return int
     */
    private static function cutoff(int $days): int {
        return time() - (max(1, $days) * DAYSECS);
    }
}
