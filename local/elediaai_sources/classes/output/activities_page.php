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
        $optout = activity_gate::mode() === activity_gate::MODE_OPTOUT;
        $manager = new ingestion_manager();

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
                ] + $this->status_fields($states[(int) $cm->id] ?? null);
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
     * @param \stdClass|null $state The cm_state row, or null.
     * @return array status, statuslabel, statusclass, statustitle.
     */
    private function status_fields(?\stdClass $state): array {
        if ($state === null) {
            return [
                'status' => 'unknown',
                'statuslabel' => get_string('activities_status_unknown', 'local_elediaai_sources'),
                'statusclass' => 'badge-light bg-light text-dark',
                'statustitle' => '',
            ];
        }

        if ($state->laststatus === cm_state::STATUS_EMPTY) {
            return [
                'status' => 'empty',
                'statuslabel' => get_string('activities_status_empty', 'local_elediaai_sources'),
                'statusclass' => 'badge-secondary bg-secondary',
                'statustitle' => (string) ($state->lasterror ?? ''),
            ];
        }

        if ($state->laststatus === cm_state::STATUS_SUCCESS) {
            return [
                'status' => 'indexed',
                'statuslabel' => get_string('activities_status_indexed', 'local_elediaai_sources'),
                'statusclass' => 'badge-success bg-success',
                'statustitle' => userdate((int) $state->timeingested),
            ];
        }

        return [
            'status' => 'error',
            'statuslabel' => get_string('activities_status_error', 'local_elediaai_sources'),
            'statusclass' => 'badge-danger bg-danger',
            'statustitle' => (string) ($state->lasterror ?? ''),
        ];
    }
}
