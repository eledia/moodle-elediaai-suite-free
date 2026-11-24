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

namespace local_elediaai_sources\output;

use local_elediaai_sources\activity_gate;
use local_elediaai_sources\cm_state;
use local_elediaai_sources\course_state;
use local_elediaai_sources\ingestion_manager;
use renderable;
use renderer_base;
use templatable;

/**
 * The per-course activity selection page.
 *
 * Groups the course's activities by section — the shape teachers know from
 * the course index — and carries per row everything the toggle needs: the
 * effective state, whether the decision is explicit, and whether any
 * extractor can read the activity at all. Unsupported activities keep their
 * row (hidden rows would suggest the course exports less than it does) but
 * their toggle is disabled.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activities_page implements renderable, templatable {
    /** @var \stdClass The course. */
    private \stdClass $course;

    /**
     * Constructor.
     *
     * @param \stdClass $course The course.
     */
    public function __construct(\stdClass $course) {
        $this->course = $course;
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $modinfo = get_fast_modinfo($this->course);
        $decisions = activity_gate::decisions((int) $this->course->id);
        $states = cm_state::for_course((int) $this->course->id);
        $documents = cm_state::documents_by_module((int) $this->course->id);
        $optout = activity_gate::mode() === activity_gate::MODE_OPTOUT;
        $manager = new ingestion_manager();

        $statuses = [];
        $sections = [];
        foreach ($modinfo->get_sections() as $sectionnum => $cmids) {
            $rows = [];
            $bulkcmids = [];
            foreach ($cmids as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                if ($cm->deletioninprogress) {
                    continue;
                }

                $explicit = array_key_exists((int) $cm->id, $decisions);
                $included = $explicit ? $decisions[(int) $cm->id] : $optout;
                // Two forms of the same name, for two jobs: the formatted one
                // is HTML (filters may add markup, "&" arrives as "&amp;") and
                // belongs in {{{name}}}; the plain one carries no markup and
                // is what aria labels and other attributes need, where Mustache
                // escapes once itself.
                $name = $cm->get_formatted_name();
                $plainname = $cm->get_formatted_name(['escape' => false]);
                $supported = $manager->has_extractor($cm);
                if ($supported) {
                    $bulkcmids[] = (int) $cm->id;
                }

                $status = self::status_fields(
                    $states[(int) $cm->id] ?? null,
                    ingestion_manager::visible_to_learners($cm),
                    $documents[(int) $cm->id] ?? []
                );
                $statuses[] = $status;

                $rows[] = [
                    'cmid' => (int) $cm->id,
                    'name' => $name,
                    'plainname' => $plainname,
                    'modname' => $cm->get_module_type_name(),
                    'iconurl' => $cm->get_icon_url()->out(false),
                    'supported' => $supported,
                    'included' => $included,
                    'excluded' => $explicit && !$included,
                    'explicit' => $explicit,
                    'togglearia' => get_string('activities_toggle_aria', 'local_elediaai_sources', $plainname),
                    'resetaria' => get_string('activities_reset_aria', 'local_elediaai_sources', $plainname),
                    // Offered for unsupported types too: there the preview's
                    // answer ("no extractor") is exactly what is being asked.
                    'previewurl' => (new \moodle_url(
                        '/local/elediaai_sources/preview.php',
                        ['cmid' => (int) $cm->id]
                    ))->out(false),
                    'previewaria' => get_string('preview_aria', 'local_elediaai_sources', $plainname),
                ] + $status;
            }

            if (empty($rows)) {
                continue;
            }

            $sectioninfo = $modinfo->get_section_info($sectionnum);
            $sections[] = [
                'name' => get_section_name($this->course, $sectioninfo),
                'plainname' => $this->plain_section_name($sectioninfo),
                'activities' => $rows,
                'bulkcmids' => implode(',', $bulkcmids),
                'hasbulk' => !empty($bulkcmids),
            ];
        }

        return [
            'courseid' => (int) $this->course->id,
            'intro' => get_string('activities_intro', 'local_elediaai_sources'),
            'modeinfo' => get_string(
                $optout ? 'activities_mode_optout' : 'activities_mode_optin',
                'local_elediaai_sources'
            ),
            'modedefault' => $optout ? 1 : 0,
            'sections' => $sections,
            'hassections' => !empty($sections),
        ] + self::summary_fields($statuses, $documents);
    }

    /**
     * Course status, counters and the "not available" notice for the page head (REQ-014).
     *
     * Counted from the rows the page shows, so the numbers add up to what a
     * teacher sees below them. Read from the database only: the page never
     * waits for the destination; the status script asks after rendering.
     *
     * @param array[] $statuses Status fields of every row, see {@see status_fields()}.
     * @param array<int, \stdClass[]> $documents Document rows by cmid.
     * @return array Context for the summary region.
     */
    public static function summary_fields(array $statuses, array $documents): array {
        $counts = ['indexed' => 0, 'processing' => 0, 'error' => 0];
        $inflight = false;
        $unavailable = false;
        foreach ($statuses as $status) {
            if ($status['status'] === 'indexed') {
                $counts['indexed']++;
            } else if (in_array($status['status'], ['queued', 'processing'], true)) {
                $counts['processing']++;
            } else if (in_array($status['status'], ['error', 'unconfirmed'], true)) {
                $counts['error']++;
            }
            $inflight = $inflight || !empty($status['inflight']);
            $unavailable = $unavailable || !empty($status['unavailable']);
        }

        $lastchecked = 0;
        foreach ($documents as $docs) {
            foreach ($docs as $doc) {
                $lastchecked = max($lastchecked, (int) $doc->statuscheckedat);
            }
        }

        $coursestatus = course_state::display_status($counts);
        return [
            'coursestatus' => $coursestatus,
            'coursestatuslabel' => get_string('coursestatus_' . $coursestatus, 'local_elediaai_sources'),
            'countindexed' => $counts['indexed'],
            'countprocessing' => $counts['processing'],
            'counterror' => $counts['error'],
            'hasinflight' => $inflight,
            'statusunavailable' => $unavailable,
            'lastchecked' => $lastchecked,
            'lastcheckedtext' => $lastchecked > 0
                ? get_string('activities_lastchecked', 'local_elediaai_sources', userdate($lastchecked))
                : '',
        ];
    }

    /**
     * The section name as plain text, for attributes such as aria labels.
     *
     * {@see get_section_name()} always returns HTML-escaped output and takes no
     * options, so a custom name is formatted here with escaping switched off.
     * Sections without their own name fall back to the format's generated name
     * ("Topic 3"), which carries no user input.
     *
     * @param \section_info $sectioninfo The section.
     * @return string
     */
    private function plain_section_name(\section_info $sectioninfo): string {
        $name = (string) ($sectioninfo->name ?? '');
        if (trim($name) === '') {
            return get_section_name($this->course, $sectioninfo);
        }

        return format_string($name, true, [
            'context' => \core\context\course::instance((int) $this->course->id),
            'escape' => false,
        ]);
    }

    /**
     * Status display fields for one activity.
     *
     * Honest by construction: without a state row the status is "unknown" —
     * the plugin has no record of this activity in the index, and does not
     * pretend otherwise.
     *
     * An activity hidden from learners is never sent, whatever its switch
     * says, and an index entry from before it was hidden is removed. Its
     * status says so instead of "unknown" (G-08), in the words of the dry run.
     *
     * "Indexed" means confirmed by the destination (ai_suite#232). Accepted
     * but unconfirmed is "queued", or "processing" while the pipeline works on
     * a document. The reason of a failure, the next retry and "status not
     * available" are visible text in `statusdetail` -- a title attribute
     * alone reaches neither keyboard nor screen reader users.
     *
     * Public and static because the status refresh of the page returns the
     * same fields for the same rows: one method, one wording.
     *
     * @param \stdClass|null $state The cm_state row, or null.
     * @param bool $learnervisible Whether learners can see the activity.
     * @param \stdClass[] $documents The module's document rows, in send order.
     * @return array status, statuslabel, statusclass, statustitle, statusdetail,
     *               statusreason, inflight, unavailable.
     */
    public static function status_fields(?\stdClass $state, bool $learnervisible = true, array $documents = []): array {
        $fields = [
            'statustitle' => '',
            'statusdetail' => '',
            'statusreason' => '',
            'inflight' => false,
            'unavailable' => false,
        ];

        if (!$learnervisible) {
            return [
                'status' => 'hidden',
                'statuslabel' => get_string('activities_status_hidden', 'local_elediaai_sources'),
                'statusclass' => 'badge-secondary bg-secondary',
                'statustitle' => get_string('activityhidden', 'local_elediaai_sources'),
            ] + $fields;
        }

        if ($state === null) {
            return [
                'status' => 'unknown',
                'statuslabel' => get_string('activities_status_unknown', 'local_elediaai_sources'),
                'statusclass' => 'badge-light bg-light text-dark',
            ] + $fields;
        }

        if ($state->laststatus === cm_state::STATUS_EMPTY) {
            return [
                'status' => 'empty',
                'statuslabel' => get_string('activities_status_empty', 'local_elediaai_sources'),
                'statusclass' => 'badge-secondary bg-secondary',
                'statustitle' => (string) ($state->lasterror ?? ''),
            ] + $fields;
        }

        if ($state->laststatus === cm_state::STATUS_SUCCESS) {
            return [
                'status' => 'indexed',
                'statuslabel' => get_string('activities_status_indexed', 'local_elediaai_sources'),
                'statusclass' => 'badge-success bg-success',
                'statustitle' => userdate((int) $state->timeingested),
            ] + $fields;
        }

        if ($state->laststatus === cm_state::STATUS_QUEUED) {
            return self::queued_fields($documents) + $fields;
        }

        foreach ($documents as $doc) {
            if ($doc->errortype === cm_state::ERRORTYPE_UNCONFIRMED) {
                return [
                    'status' => 'unconfirmed',
                    'statuslabel' => get_string('activities_status_unconfirmed', 'local_elediaai_sources'),
                    'statusclass' => 'badge-danger bg-danger',
                    'statustitle' => (string) ($state->lasterror ?? ''),
                    'statusdetail' => get_string('status_unconfirmed', 'local_elediaai_sources'),
                ] + $fields;
            }
        }

        $lasterror = trim((string) ($state->lasterror ?? ''));
        $detail = $lasterror;
        $reason = '';
        $errortype = self::first_failure_type($documents);
        $key = 'pipelineerror_' . strtolower((string) $errortype);
        if ($errortype !== null && get_string_manager()->string_exists($key, 'local_elediaai_sources')) {
            $detail = get_string($key, 'local_elediaai_sources');
            $reason = $lasterror;
        }

        return [
            'status' => 'error',
            'statuslabel' => get_string('activities_status_error', 'local_elediaai_sources'),
            'statusclass' => 'badge-danger bg-danger',
            'statustitle' => $lasterror,
            'statusdetail' => $detail,
            'statusreason' => $reason,
        ] + $fields;
    }

    /**
     * Status fields of a module the destination accepted but has not confirmed.
     *
     * @param \stdClass[] $documents The module's document rows.
     * @return array
     */
    private static function queued_fields(array $documents): array {
        $running = false;
        $nextretry = 0;
        $unavailable = false;
        $checked = 0;
        foreach ($documents as $doc) {
            if (!in_array($doc->remotestatus, cm_state::REMOTE_INFLIGHT, true)) {
                continue;
            }
            $running = $running || $doc->remotestatus === cm_state::REMOTE_RUNNING;
            if ((int) $doc->nextretryat > time()) {
                $nextretry = $nextretry === 0 ? (int) $doc->nextretryat : min($nextretry, (int) $doc->nextretryat);
            }
            $unavailable = $unavailable || trim((string) ($doc->checkerror ?? '')) !== '';
            $checked = max($checked, (int) $doc->statuscheckedat);
        }

        $detail = '';
        if ($unavailable) {
            $detail = $checked > 0
                ? get_string('activities_status_unavailable', 'local_elediaai_sources', userdate($checked))
                : get_string('activities_status_unavailable_never', 'local_elediaai_sources');
        } else if ($nextretry > 0) {
            $detail = get_string('activities_nextretry', 'local_elediaai_sources', userdate($nextretry));
        }

        $status = $running ? 'processing' : 'queued';
        return [
            'status' => $status,
            'statuslabel' => get_string('activities_status_' . $status, 'local_elediaai_sources'),
            'statusclass' => 'badge-info bg-info text-dark',
            'statusdetail' => $detail,
            'inflight' => true,
            'unavailable' => $unavailable,
        ];
    }

    /**
     * The pipeline error type of the first failed document, in send order (D-a).
     *
     * @param \stdClass[] $documents The module's document rows.
     * @return string|null
     */
    private static function first_failure_type(array $documents): ?string {
        foreach ($documents as $doc) {
            if ($doc->remotestatus === cm_state::REMOTE_FAILED && trim((string) ($doc->errortype ?? '')) !== '') {
                return (string) $doc->errortype;
            }
        }
        return null;
    }
}
