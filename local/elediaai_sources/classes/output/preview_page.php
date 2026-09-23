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

use local_elediaai_sources\ingestion_manager;
use local_elediaai_sources\media_accessibility;
use renderable;
use renderer_base;
use templatable;

/**
 * The dry-run page for one activity.
 *
 * Turns the report from {@see ingestion_manager::preview_module()} into
 * something readable, and does the one piece of judgement the report leaves
 * open: telling apart "the index holds older content" from "the content is
 * the same but the embedding model changed". Both make the next run re-send,
 * for entirely different reasons, and an operator needs to know which.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preview_page implements renderable, templatable {
    /** @var \cm_info The activity being previewed. */
    private \cm_info $cm;

    /**
     * Constructor.
     *
     * @param \cm_info $cm The activity to preview.
     */
    public function __construct(\cm_info $cm) {
        $this->cm = $cm;
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $report = (new ingestion_manager())->preview_module($this->cm);

        $documents = [];
        foreach ($report['documents'] as $document) {
            $documents[] = $document + [
                'sizetext' => display_size($document['bytes']),
                'hashshort' => $document['hash'] !== '' ? substr($document['hash'], 0, 12) : '',
                'hasdisplay' => $document['displaycontent'] !== null && $document['displaycontent'] !== '',
            ];
        }

        $meta = [];
        foreach ($report['payloadmeta'] as $key => $value) {
            $meta[] = ['key' => $key, 'value' => (string) $value];
        }

        return [
            'cmid' => $report['cmid'],
            'modulename' => $report['modulename'],
            'modname' => $report['modname'],
            'modulesourceid' => $report['modulesourceid'],
            'moduleurl' => (new \moodle_url(
                '/mod/' . $this->cm->modname . '/view.php',
                ['id' => $this->cm->id]
            ))->out(false),
            'courseurl' => (new \moodle_url(
                '/local/elediaai_sources/activities.php',
                ['id' => $this->cm->course]
            ))->out(false),
            'sinkname' => $report['sinkname'],
            'embeddingmodel' => $report['embeddingmodel'],
            'hasmodel' => $report['embeddingmodel'] !== null && $report['embeddingmodel'] !== '',
            'documents' => $documents,
            'hasdocuments' => !empty($documents),
            'documentcount' => count($documents),
            'meta' => $meta,
            'hasmeta' => !empty($meta),
            'aggregatehash' => $report['aggregatehash'],
            'unchanged' => $report['unchanged'],
        ] + $this->verdict_fields($report)
          + $this->comparison_fields($report)
          + $this->accessibility_fields($report);
    }

    /**
     * Name the list of files by the reason they are on it.
     *
     * The three reasons read alike but are not: no captions at all, captions
     * that could not be paired, or audio left to a transcript whose coverage
     * the package does not state. A list headed "could not be matched" above
     * files that were never matched to anything says the wrong thing.
     *
     * @param array $accessibility The accessibility report.
     * @return string The language string key.
     */
    private static function files_label_key(array $accessibility): string {
        if ($accessibility['verdict'] !== media_accessibility::VERDICT_UNDETERMINED) {
            return 'a11y_files_missing';
        }

        return !empty($accessibility['hastranscript'])
            ? 'a11y_files_transcript'
            : 'a11y_files_undetermined';
    }

    /**
     * The caption coverage of the activity's media.
     *
     * Four outcomes, not two. "Undetermined" exists because a package can hold
     * captions this cannot pair with their media — claiming accessibility there
     * would be a false clearance, and denying it a false accusation.
     *
     * @param array $report The preview report.
     * @return array Accessibility fields for the template.
     */
    private function accessibility_fields(array $report): array {
        $accessibility = $report['accessibility'] ?? null;
        if ($accessibility === null || $accessibility['verdict'] === media_accessibility::VERDICT_NOMEDIA) {
            return ['hasaccessibility' => false];
        }

        $undeterminedkey = !empty($accessibility['hastranscript'])
            ? 'a11y_undetermined_transcript'
            : 'a11y_undetermined';
        $map = [
            media_accessibility::VERDICT_COMPLETE => ['a11y_complete', 'alert-success'],
            media_accessibility::VERDICT_INCOMPLETE => ['a11y_incomplete', 'alert-warning'],
            media_accessibility::VERDICT_UNDETERMINED => [$undeterminedkey, 'alert-info'],
        ];
        [$key, $class] = $map[$accessibility['verdict']];

        $named = array_merge($accessibility['uncaptioned'], $accessibility['undetermined']);
        sort($named);

        $dangling = $accessibility['danglingcaptions'] ?? [];

        return [
            'hasaccessibility' => true,
            'a11yclass' => $class,
            'hasa11ydangling' => !empty($dangling),
            'a11ydanglinglabel' => get_string('a11y_dangling', 'local_elediaai_sources'),
            'a11ydangling' => array_map(static fn(string $path): array => ['path' => $path], $dangling),
            'a11ytext' => get_string($key, 'local_elediaai_sources', (object) [
                'captioned' => (int) $accessibility['captioned'],
                'total' => (int) $accessibility['total'],
            ]),
            'a11yfiles' => array_map(static fn(string $path): array => ['path' => $path], $named),
            'hasa11yfiles' => !empty($named),
            'a11yfileslabel' => get_string(
                self::files_label_key($accessibility),
                'local_elediaai_sources'
            ),
        ];
    }

    /**
     * Headline and badge for the verdict.
     *
     * @param array $report The preview report.
     * @return array verdictlabel, verdictclass, verdicthint.
     */
    private function verdict_fields(array $report): array {
        $map = [
            ingestion_manager::VERDICT_INGEST => ['preview_verdict_ingest', 'badge-success bg-success'],
            ingestion_manager::VERDICT_DELETE => ['preview_verdict_delete', 'badge-warning bg-warning text-dark'],
            ingestion_manager::VERDICT_SKIP => ['preview_verdict_skip', 'badge-secondary bg-secondary'],
        ];
        [$key, $class] = $map[$report['verdict']] ?? $map[ingestion_manager::VERDICT_SKIP];

        return [
            'verdict' => $report['verdict'],
            'verdictlabel' => get_string($key, 'local_elediaai_sources'),
            'verdictclass' => $class,
            'verdicthint' => $report['reasontext'],
            'hasverdicthint' => $report['reasontext'] !== '',
        ];
    }

    /**
     * How this activity currently relates to the index.
     *
     * @param array $report The preview report.
     * @return array comparisontext.
     */
    private function comparison_fields(array $report): array {
        $state = $report['state'];

        if ($report['comparison'] === 'absent') {
            return ['comparisontext' => get_string('preview_index_absent', 'local_elediaai_sources')];
        }

        if ($report['comparison'] === 'otherdestination') {
            $name = \local_elediaai_sources\sink\sink_manager::instance((string) $state->sink);
            return ['comparisontext' => get_string(
                'preview_index_otherdestination',
                'local_elediaai_sources',
                (object) [
                    'old' => $name !== null ? $name::name() : (string) $state->sink,
                    'active' => $report['sinkname'],
                ]
            )];
        }

        if ($report['comparison'] === 'lastattemptfailed') {
            return ['comparisontext' => get_string(
                'preview_index_lastattemptfailed',
                'local_elediaai_sources',
                (object) [
                    'date' => userdate((int) $state->timemodified),
                    'error' => (string) $state->lasterror,
                ]
            )];
        }

        // Indexed under the active destination. Same content but a re-send
        // pending means something other than the content changed — in
        // practice the embedding model, which is worth naming separately.
        $hashequal = $report['aggregatehash'] !== null
            && (string) $state->contenthash === (string) $report['aggregatehash'];

        if ($hashequal && !$report['unchanged']) {
            return ['comparisontext' => get_string('preview_index_modelchanged', 'local_elediaai_sources')];
        }

        $key = $hashequal ? 'preview_index_identical' : 'preview_index_older';
        return ['comparisontext' => get_string(
            $key,
            'local_elediaai_sources',
            userdate((int) $state->timeingested)
        )];
    }
}
