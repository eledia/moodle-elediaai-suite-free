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

declare(strict_types=1);

namespace webservice_elediamcp\local\ai\tools;

use context_system;
use moodle_url;
use stdClass;
use Throwable;
use webservice_elediamcp\local\ai\ai_tool;
use webservice_elediamcp\local\ai\response_budget;
use webservice_elediamcp\local\ai\source;
use webservice_elediamcp\local\ai\tool_exception;

/**
 * AI-native announcements feed.
 *
 * Returns the most recent posts in each course's news forum
 * (forum.type='news', the per-course Announcements forum) for the
 * authenticated user's enrolled courses. Designed to answer "what's new
 * in my courses?" in a single round-trip.
 *
 * @package     webservice_elediamcp
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_get_announcements implements ai_tool {
    /** @var int Default page size. */
    private const DEFAULT_LIMIT = 10;

    /** @var int Maximum page size. */
    private const MAX_LIMIT = 30;

    /** @var int Highest accepted offset; beyond it the request should be narrowed. */
    private const MAX_OFFSET = 1000;

    /** @var int Default lookback window (days). */
    private const DEFAULT_DAYS = 30;

    /** @var int Maximum lookback window (days). */
    private const MAX_DAYS = 180;

    /**
     * Tool name.
     *
     * @return string
     */
    public static function name(): string {
        return 'moodle_get_announcements';
    }

    /**
     * Tool title.
     *
     * @return string
     */
    public static function title(): string {
        return 'Latest course announcements';
    }

    /**
     * Tool description.
     *
     * @return string
     */
    public static function description(): string {
        return 'Returns the most recent posts in each course\'s Announcements forum (forum.type='
            . '\'news\') for the authenticated user. Restrict to one course with course_id and '
            . 'set lookback_days to bound how far back to look (default 30). Use this to answer '
            . '"what\'s new in my courses?" or "did my teacher post anything?". total is the number of '
            . 'announcements in this answer, not the number of all announcements. '
            . response_budget::DESCRIPTION_SENTENCE;
    }

    /**
     * Input schema.
     *
     * @return array<string, mixed>
     */
    public static function input_schema(): array {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'course_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'Optional course id to restrict the feed to a single course.',
                ],
                'lookback_days' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => self::MAX_DAYS,
                    'default' => self::DEFAULT_DAYS,
                    'description' => 'Look-back window in days (default 30, max 180).',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => self::MAX_LIMIT,
                    'default' => self::DEFAULT_LIMIT,
                ],
                'offset' => [
                    'type' => 'integer',
                    'minimum' => 0,
                    'maximum' => self::MAX_OFFSET,
                    'default' => 0,
                    'description' => 'Number of announcements to skip (newest first), for the next page.',
                ],
                'include_body' => [
                    'type' => 'boolean',
                    'default' => true,
                    'description' => 'Include a plain-text excerpt of each post body.',
                ],
                'excerpt_chars' => [
                    'type' => 'integer',
                    'minimum' => 80,
                    'maximum' => 1200,
                    'default' => 400,
                ],
            ],
        ];
    }

    /**
     * Output schema.
     *
     * @return array<string, mixed>
     */
    public static function output_schema(): array {
        return [
            'type' => 'object',
            'required' => ['announcements', 'total', 'has_more', 'next_offset', 'summary'],
            'properties' => [
                'announcements' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['discussion_id', 'subject', 'course_id', 'posted_iso', 'url'],
                        'properties' => [
                            'discussion_id' => ['type' => 'integer'],
                            'post_id' => ['type' => 'integer'],
                            'subject' => ['type' => 'string'],
                            'course_id' => ['type' => 'integer'],
                            'course_name' => ['type' => 'string'],
                            'forum_id' => ['type' => 'integer'],
                            'author_id' => ['type' => 'integer'],
                            'author_name' => ['type' => 'string'],
                            'posted_iso' => ['type' => 'string'],
                            'url' => ['type' => 'string'],
                            'body_excerpt' => ['type' => 'string'],
                            'source' => source::schema(),
                        ],
                    ],
                ],
                'total' => [
                    'type' => 'integer',
                    'description' => 'announcements in this page',
                ],
                'lookback_days' => ['type' => 'integer'],
                'limit' => ['type' => 'integer'],
                'offset' => ['type' => 'integer'],
                'has_more' => ['type' => 'boolean'],
                'next_offset' => [
                    'type' => ['integer', 'null'],
                    'description' => 'offset for the next call while has_more is true, else null',
                ],
                'summary' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * Tool annotations.
     *
     * @return array<string, mixed>
     */
    public static function annotations(): array {
        return [
            'title' => self::title(),
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => false,
        ];
    }

    /**
     * Execute the tool.
     *
     * $user must be the session user ($USER), as the server passes it
     * (classes/local/server.php, dispatch_ai_tool()). The per-forum filter below
     * uses $user explicitly; forum_user_can_see_discussion() partly checks
     * $USER. If both ever differed, the final check could only remove rows.
     *
     * @param array<string, mixed> $arguments Validated arguments.
     * @param stdClass $user Authenticated user record.
     * @return array<string, mixed>
     */
    public static function execute(array $arguments, stdClass $user): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $userid = (int) $user->id;
        $courseidfilter = isset($arguments['course_id']) ? (int) $arguments['course_id'] : 0;
        $lookback = isset($arguments['lookback_days'])
            ? max(1, min(self::MAX_DAYS, (int) $arguments['lookback_days']))
            : self::DEFAULT_DAYS;
        $limit = isset($arguments['limit'])
            ? max(1, min(self::MAX_LIMIT, (int) $arguments['limit']))
            : self::DEFAULT_LIMIT;
        $offset = isset($arguments['offset']) ? max(0, (int) $arguments['offset']) : 0;
        if ($offset > self::MAX_OFFSET) {
            // Every page re-checks the visibility of all skipped announcements,
            // so the offset is bounded; a silent clamp could make a client loop.
            throw new tool_exception(
                'offset must not exceed ' . self::MAX_OFFSET . '; narrow the request with course_id or lookback_days.',
                ['offset' => $offset, 'maximum' => self::MAX_OFFSET]
            );
        }
        $includebody = !array_key_exists('include_body', $arguments) || (bool) $arguments['include_body'];
        $excerpt = isset($arguments['excerpt_chars'])
            ? max(80, min(1200, (int) $arguments['excerpt_chars']))
            : 400;

        // No response cache: display periods of timed announcements start and end
        // to the second, and group or capability changes must apply at once.

        // Resolve eligible course ids (enrol_get_users_courses() drops hidden
        // courses unless the user may see them).
        $usercourses = enrol_get_users_courses($userid, true, ['id', 'fullname']);
        $coursemap = [];
        foreach ($usercourses as $c) {
            $coursemap[(int) $c->id] = (string) $c->fullname;
        }
        if ($courseidfilter > 0) {
            if (!isset($coursemap[$courseidfilter]) && !is_siteadmin($user)) {
                // Same neutral message for a missing, foreign or hidden course.
                throw new tool_exception(
                    "Course {$courseidfilter} is not available to you.",
                    ['course_id' => $courseidfilter]
                );
            }
            $coursemap = [$courseidfilter => $coursemap[$courseidfilter] ?? ('Course ' . $courseidfilter)];
        }
        if (empty($coursemap)) {
            return self::empty_payload($lookback, $limit, $offset);
        }

        // Only read announcement forums whose course-module is visible to the user
        // (honours hidden modules, availability restrictions and course visibility).
        $cms = [];
        foreach (array_keys($coursemap) as $cid) {
            try {
                $modinfo = get_fast_modinfo((int) $cid, $userid);
            } catch (Throwable $ex) {
                continue;
            }
            foreach ($modinfo->get_instances_of('forum') as $cm) {
                if ($cm->uservisible) {
                    $cms[(int) $cm->instance] = $cm;
                }
            }
        }
        $forums = empty($cms) ? [] : $DB->get_records_list('forum', 'id', array_keys($cms));

        // Per forum, the rules of Moodle's discussion list
        // (mod_forum_get_discussion_summaries()): mod/forum:viewdiscussion; the
        // display period unless mod/forum:viewhiddentimedposts (always, like the
        // list, also when forum_enabletimedposts is off; own posts stay visible);
        // in separate groups without moodle/site:accessallgroups only "all
        // participants" and the user's groups within the forum's grouping
        // (mod_forum_get_groups_from_groupid()). They go into the SQL so that
        // hidden rows are never read.
        $now = time();
        $params = ['since' => $now - ($lookback * DAYSECS)];
        $openforums = [];
        $clauses = [];
        $contexts = [];
        foreach ($forums as $forum) {
            if ((string) $forum->type !== 'news') {
                continue;
            }
            $forumid = (int) $forum->id;
            $cm = $cms[$forumid];
            $context = \context_module::instance($cm->id);
            if (!has_capability('mod/forum:viewdiscussion', $context, $user)) {
                continue;
            }
            $contexts[$forumid] = $context;
            $conditions = [];
            if (!has_capability('mod/forum:viewhiddentimedposts', $context, $user)) {
                $conditions[] = "((d.timestart <= :ts{$forumid} AND (d.timeend = 0 OR d.timeend > :te{$forumid}))"
                    . " OR d.userid = :by{$forumid})";
                $params["ts{$forumid}"] = $now;
                $params["te{$forumid}"] = $now;
                $params["by{$forumid}"] = $userid;
            }
            if (
                (int) $cm->effectivegroupmode === SEPARATEGROUPS
                && !has_capability('moodle/site:accessallgroups', $context, $user)
            ) {
                $groupids = array_keys(groups_get_all_groups((int) $forum->course, $userid, (int) $cm->groupingid));
                $groupids[] = -1;
                [$gsql, $gparams] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, "g{$forumid}_");
                $conditions[] = "d.groupid {$gsql}";
                $params += $gparams;
            }
            if (empty($conditions)) {
                $openforums[] = $forumid;
            } else {
                $clauses[] = "(d.forum = :f{$forumid} AND " . implode(' AND ', $conditions) . ')';
                $params["f{$forumid}"] = $forumid;
            }
        }
        if (!empty($openforums)) {
            [$opensql, $openparams] = $DB->get_in_or_equal($openforums, SQL_PARAMS_NAMED, 'of');
            $clauses[] = "d.forum {$opensql}";
            $params += $openparams;
        }
        if (empty($clauses)) {
            return self::empty_payload($lookback, $limit, $offset);
        }

        // First post per discussion; ordered by discussion timemodified (bumped on
        // replies, useful for "what changed recently?"). The body is loaded later,
        // only for the rows on the page.
        $sql = "SELECT d.id AS discussionid, d.name AS subject, d.timemodified, d.course AS courseid,
                       d.forum AS forumid, d.groupid, d.timestart, d.timeend, d.userid,
                       p.id AS postid, p.userid AS authorid, p.created AS posttime
                  FROM {forum_discussions} d
                  JOIN {forum_posts} p ON p.id = d.firstpost
                 WHERE (" . implode(' OR ', $clauses) . ")
                   AND d.timemodified >= :since
              ORDER BY d.timemodified DESC, d.id DESC";

        // The final check is forum_user_can_see_discussion() (the rule of the
        // direct link). Rows it drops leave no placeholder, so the offset counts
        // visible announcements only; read in blocks until one row more than the
        // page is visible (has_more probe).
        $rows = [];
        $skipped = 0;
        $from = 0;
        $block = $offset + $limit + 1;
        do {
            $chunk = $DB->get_records_sql($sql, $params, $from, $block);
            $from += $block;
            foreach ($chunk as $r) {
                $forumid = (int) $r->forumid;
                $discussion = (object) [
                    'id' => (int) $r->discussionid,
                    'forum' => $forumid,
                    'course' => (int) $r->courseid,
                    'groupid' => (int) $r->groupid,
                    'timestart' => (int) $r->timestart,
                    'timeend' => (int) $r->timeend,
                    'userid' => (int) $r->userid,
                ];
                if (!forum_user_can_see_discussion($forums[$forumid], $discussion, $contexts[$forumid], $user)) {
                    continue;
                }
                if ($skipped < $offset) {
                    $skipped++;
                    continue;
                }
                $rows[] = $r;
                if (count($rows) > $limit) {
                    break 2;
                }
            }
        } while (count($chunk) === $block);
        $probe = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);

        // Bodies only for the selected posts.
        $bodies = [];
        if ($includebody && !empty($rows)) {
            $postids = array_map(static fn($r) => (int) $r->postid, $rows);
            $bodies = $DB->get_records_list('forum_posts', 'id', $postids, '', 'id, message, messageformat');
        }

        // Batch-load author names.
        $authorids = array_unique(array_map(static fn($r) => (int) $r->authorid, $rows));
        $authors = [];
        if (!empty($authorids)) {
            $namefields = 'id, firstname, lastname, firstnamephonetic, lastnamephonetic, '
                . 'middlename, alternatename';
            $authrecords = $DB->get_records_list('user', 'id', $authorids, '', $namefields);
            foreach ($authrecords as $a) {
                $authors[(int) $a->id] = fullname($a);
            }
        }

        $stringopts = ['context' => context_system::instance(), 'filter' => false, 'escape' => true];
        $entries = [];
        foreach ($rows as $r) {
            $courseid = (int) $r->courseid;
            $entry = [
                'discussion_id' => (int) $r->discussionid,
                'post_id' => (int) $r->postid,
                'subject' => format_string((string) $r->subject, true, $stringopts),
                'course_id' => $courseid,
                'course_name' => isset($coursemap[$courseid])
                    ? format_string($coursemap[$courseid], true, $stringopts) : '',
                'forum_id' => (int) $r->forumid,
                'author_id' => (int) $r->authorid,
                'author_name' => $authors[(int) $r->authorid] ?? '',
                'posted_iso' => gmdate('c', (int) $r->posttime),
                'url' => (new moodle_url('/mod/forum/discuss.php', ['d' => (int) $r->discussionid]))->out(false),
            ];
            if ($includebody) {
                $post = $bodies[(int) $r->postid] ?? null;
                $entry['body_excerpt'] = $post
                    ? self::excerpt((string) $post->message, (int) $post->messageformat, $excerpt)
                    : '';
            }
            // Ready-made citation (see class source): an announcement quoted in an
            // answer should be linkable like any other course material.
            $entry['source'] = source::build(
                $entry['subject'],
                $entry['url'],
                $entry['body_excerpt'] ?? '',
                null,
                $courseid
            );
            $entries[] = $entry;
        }

        // The page is as large as the limit allows and the answer still fits
        // into the character budget (response_budget).
        $built = count($entries);
        return response_budget::fit_page(
            $entries,
            static function (array $page) use ($built, $probe, $lookback, $limit, $offset, $courseidfilter, $coursemap): array {
                $shown = count($page);
                $hasmore = $probe || $shown < $built;
                return [
                    'announcements' => $page,
                    'total' => $shown,
                    'lookback_days' => $lookback,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => $hasmore,
                    'next_offset' => $hasmore ? $offset + $shown : null,
                    'summary' => response_budget::summary(
                        self::build_summary($shown, $lookback, $courseidfilter, $coursemap, $hasmore || $offset > 0),
                        $hasmore
                    ),
                ];
            }
        );
    }

    /**
     * Build a plain-text excerpt from forum-post HTML.
     *
     * @param string $body Raw body.
     * @param int $format Text format.
     * @param int $max Maximum length.
     * @return string
     */
    private static function excerpt(string $body, int $format, int $max): string {
        $opts = ['context' => context_system::instance(), 'filter' => false, 'noclean' => true];
        $formatted = format_text($body, $format, $opts);
        $plain = trim(html_to_text($formatted, 0, false));
        if ($plain === '') {
            return '';
        }
        if (\core_text::strlen($plain) > $max) {
            $plain = \core_text::substr($plain, 0, $max - 3) . '...';
        }
        return $plain;
    }

    /**
     * Empty payload helper for users with no eligible courses.
     *
     * @param int $lookback Lookback window in days.
     * @param int $limit Requested page size.
     * @param int $offset Requested offset.
     * @return array<string, mixed>
     */
    private static function empty_payload(int $lookback, int $limit, int $offset): array {
        return [
            'announcements' => [],
            'total' => 0,
            'lookback_days' => $lookback,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => false,
            'next_offset' => null,
            'summary' => sprintf('No announcements in the last %d days.', $lookback),
        ];
    }

    /**
     * Build a one-sentence summary for the LLM.
     *
     * @param int $shown Posts in the page.
     * @param int $lookback Days searched.
     * @param int $courseidfilter Course filter (0 = none).
     * @param array<int, string> $coursemap Map of course id => fullname.
     * @param bool $partial Whether the answer holds only part of the announcements (more follow or an offset was used).
     * @return string
     */
    private static function build_summary(
        int $shown,
        int $lookback,
        int $courseidfilter,
        array $coursemap,
        bool $partial = false
    ): string {
        if ($shown === 0) {
            return sprintf('No new announcements in the last %d days.', $lookback);
        }
        $courselabel = '';
        if ($courseidfilter > 0) {
            $courselabel = isset($coursemap[$courseidfilter])
                ? sprintf(' in "%s"', $coursemap[$courseidfilter])
                : sprintf(' in course %d', $courseidfilter);
        }
        $word = $shown === 1 ? 'announcement' : 'announcements';
        // The total of all visible announcements is unknown (see execute()), so
        // a partial answer must not read like a complete count.
        $format = $partial
            ? 'Showing %d %s%s from the last %d days; this is not the complete list.'
            : 'Found %d %s%s in the last %d days.';
        return sprintf(
            $format,
            $shown,
            $word,
            $courselabel,
            $lookback
        );
    }
}
