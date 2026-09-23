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
 * Embeddings-free retrieval over stored chunks.
 *
 * Uses the database's native full-text search (PostgreSQL to_tsvector / MySQL
 * MATCH AGAINST / MSSQL CONTAINS) when available, and a portable LIKE fallback
 * otherwise. Always scopes by tenant and the supplied course ids; the caller is
 * responsible for applying {@see permission_filter} to the returned candidates
 * before any chunk is surfaced.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class retriever {
    /**
     * Fetch ranked candidate chunks for a question.
     *
     * @param string $question The raw user question.
     * @param int[] $courseids Courses to search within (empty ⇒ no results).
     * @param int $limit Maximum candidates to return.
     * @return array Chunk records ordered best-first.
     */
    public function candidates(string $question, array $courseids, int $limit): array {
        global $DB;

        $courseids = array_values(array_unique(array_filter(array_map('intval', $courseids))));
        if (empty($courseids)) {
            return [];
        }

        $tenant = tenant::id();
        [$coursesql, $courseparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_QM);

        if (schema::fulltext_available()) {
            $result = $this->fulltext_candidates($question, $tenant, $coursesql, $courseparams, $limit);
            if ($result !== null) {
                return $result;
            }
        }
        return $this->like_candidates($question, $tenant, $coursesql, $courseparams, $limit);
    }

    /**
     * Native full-text query path.
     *
     * @param string $question
     * @param string $tenant
     * @param string $coursesql IN(...) fragment.
     * @param array $courseparams
     * @param int $limit
     * @return array|null Records, or null to signal "fall back to LIKE".
     */
    private function fulltext_candidates(
        string $question,
        string $tenant,
        string $coursesql,
        array $courseparams,
        int $limit
    ): ?array {
        global $DB;

        $q = query_normaliser::clean_string($question);
        if ($q === '') {
            return [];
        }

        $base = "FROM {local_literag_chunks} WHERE tenant = ? AND courseid $coursesql";

        switch ($DB->get_dbfamily()) {
            case 'postgres':
                // OR the terms so a chunk matching ANY term is a candidate (recall),
                // ranked by how well it matches. plainto_tsquery would AND every term.
                $terms = query_normaliser::terms($question);
                if (empty($terms)) {
                    return [];
                }
                $tsquery = implode(' | ', $terms);
                $rank = "ts_rank(to_tsvector('simple', chunktext), to_tsquery('simple', ?)) + " .
                        "ts_rank(to_tsvector('simple', sourcetitle), to_tsquery('simple', ?))";
                $match = "(to_tsvector('simple', chunktext) @@ to_tsquery('simple', ?) OR " .
                         "to_tsvector('simple', sourcetitle) @@ to_tsquery('simple', ?))";
                $sql = "SELECT *, ($rank) AS relevance $base AND $match ORDER BY relevance DESC, sortorder ASC";
                $params = array_merge([$tsquery, $tsquery, $tenant], $courseparams, [$tsquery, $tsquery]);
                break;
            case 'mysql':
                $matchexpr = "MATCH (chunktext, sourcetitle) AGAINST (?)";
                $sql = "SELECT *, ($matchexpr) AS relevance $base AND $matchexpr ORDER BY relevance DESC, sortorder ASC";
                $params = array_merge([$q, $tenant], $courseparams, [$q]);
                break;
            case 'mssql':
                $contains = "CONTAINS ((chunktext, sourcetitle), ?)";
                $phrase = '"' . str_replace('"', '', $q) . '"';
                $sql = "SELECT * $base AND $contains ORDER BY sortorder ASC";
                $params = array_merge([$tenant], $courseparams, [$phrase]);
                break;
            default:
                return null;
        }

        try {
            return array_values($DB->get_records_sql($sql, $params, 0, $limit));
        } catch (\dml_exception $e) {
            // A missing full-text index or unsupported syntax: degrade gracefully.
            debugging(
                'local_literag: full-text query failed, using LIKE fallback: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return null;
        }
    }

    /**
     * Portable LIKE fallback path; scores in PHP by matched-term count.
     *
     * @param string $question
     * @param string $tenant
     * @param string $coursesql IN(...) fragment.
     * @param array $courseparams
     * @param int $limit
     * @return array Records ordered best-first.
     */
    private function like_candidates(
        string $question,
        string $tenant,
        string $coursesql,
        array $courseparams,
        int $limit
    ): array {
        global $DB;

        $terms = query_normaliser::terms($question);
        if (empty($terms)) {
            return [];
        }
        // Bound the term count to keep the OR-chain sane.
        $terms = array_slice($terms, 0, 12);

        $ors = [];
        $params = [$tenant];
        $params = array_merge($params, $courseparams);
        foreach ($terms as $term) {
            $ors[] = $DB->sql_like('chunktext', '?', false, false);
            $params[] = '%' . $DB->sql_like_escape($term) . '%';
            $ors[] = $DB->sql_like('sourcetitle', '?', false, false);
            $params[] = '%' . $DB->sql_like_escape($term) . '%';
        }

        $sql = "SELECT * FROM {local_literag_chunks}
                 WHERE tenant = ? AND courseid $coursesql AND (" . implode(' OR ', $ors) . ")";

        // Fetch a generous pool, then score and trim. Bound by 4x the limit.
        $rows = $DB->get_records_sql($sql, $params, 0, max($limit * 4, 40));

        foreach ($rows as $row) {
            $haystack = \core_text::strtolower($row->chunktext . ' ' . $row->sourcetitle);
            $score = 0;
            foreach ($terms as $term) {
                $score += substr_count($haystack, $term);
            }
            $row->relevance = $score;
        }

        $rows = array_values($rows);
        usort($rows, static function ($a, $b) {
            if ($a->relevance === $b->relevance) {
                return $a->sortorder <=> $b->sortorder;
            }
            return $b->relevance <=> $a->relevance;
        });

        return array_slice($rows, 0, $limit);
    }
}
