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

namespace local_literag\local;

/**
 * Per-course canonical topic label registry for analytics clustering.
 *
 * Labels are derived from retrieval (the primary source title) and classified
 * INTO the existing per-course set, only minting a new label when none matches —
 * which keeps the teacher hotspot report from fragmenting across rephrasings.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class topic_registry {
    /**
     * Classify a candidate label into the course registry, reusing or minting.
     *
     * @param int $courseid Course (0 for global; not registered).
     * @param string|null $candidate Candidate label (e.g. the primary source title).
     * @return string|null The canonical label, or null when there is nothing to classify.
     */
    public function classify(int $courseid, ?string $candidate): ?string {
        $candidate = $candidate === null ? '' : \core_text::substr(trim($candidate), 0, 100);
        if ($candidate === '' || $courseid <= 0) {
            return $candidate !== '' ? $candidate : null;
        }

        $existing = $this->match_existing($courseid, $candidate);
        if ($existing !== null) {
            $this->bump($courseid, $existing);
            return $existing;
        }
        return $this->register($courseid, $candidate);
    }

    /**
     * All labels currently registered for a course.
     *
     * @param int $courseid
     * @return string[]
     */
    public function existing_labels(int $courseid): array {
        global $DB;
        return array_values($DB->get_fieldset_select('local_literag_topics', 'label', 'courseid = ?', [$courseid]));
    }

    /**
     * Register (or reuse) a label and return its canonical form.
     *
     * @param int $courseid
     * @param string $label
     * @return string
     */
    public function register(int $courseid, string $label): string {
        global $DB;
        $label = \core_text::substr(trim($label), 0, 100);
        if ($label === '') {
            return $label;
        }
        $existing = $this->match_existing($courseid, $label);
        if ($existing !== null) {
            $this->bump($courseid, $existing);
            return $existing;
        }
        $now = time();
        try {
            $DB->insert_record('local_literag_topics', (object) [
                'courseid' => $courseid,
                'tenant' => tenant::id(),
                'label' => $label,
                'usecount' => 1,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        } catch (\dml_exception $e) {
            // A concurrent insert created the same (courseid,label) — reuse it.
            $existing = $this->match_existing($courseid, $label);
            return $existing ?? $label;
        }
        return $label;
    }

    /**
     * Find a case-insensitively equal existing label.
     *
     * @param int $courseid
     * @param string $label
     * @return string|null The stored label, or null.
     */
    private function match_existing(int $courseid, string $label): ?string {
        global $DB;
        $like = $DB->sql_like('label', '?', false, false);
        $row = $DB->get_record_select(
            'local_literag_topics',
            "courseid = ? AND $like",
            [$courseid, $DB->sql_like_escape($label)],
            'id, label',
            IGNORE_MULTIPLE
        );
        return $row ? (string) $row->label : null;
    }

    /**
     * Increment the usage counter for a label.
     *
     * @param int $courseid
     * @param string $label
     * @return void
     */
    private function bump(int $courseid, string $label): void {
        global $DB;
        $DB->execute('UPDATE {local_literag_topics} SET usecount = usecount + 1, timemodified = ? '
            . 'WHERE courseid = ? AND label = ?', [time(), $courseid, $label]);
    }
}
