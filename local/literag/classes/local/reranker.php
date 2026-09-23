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

use local_literag\local\llm\client;
use local_literag\local\llm\llm_exception;

/**
 * Optional LLM reranking of candidate chunks (off by default).
 *
 * Asks the model to order candidates by relevance and returns the top N. Any
 * failure degrades gracefully to the input order, so reranking can never break
 * an answer.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reranker {
    /** @var client LLM client. */
    private client $llm;

    /**
     * Measured usage of the last rerank call, or nulls when none was made.
     *
     * @var array{prompttokens: ?int, completiontokens: ?int}
     */
    private array $usage = ['prompttokens' => null, 'completiontokens' => null];

    /**
     * Token usage of the last rerank call.
     *
     * @return array{prompttokens: ?int, completiontokens: ?int}
     */
    public function get_usage(): array {
        return $this->usage;
    }

    /**
     * Constructor.
     *
     * @param client $llm
     */
    public function __construct(client $llm) {
        $this->llm = $llm;
    }

    /**
     * Reorder candidates by LLM-judged relevance and return the top N.
     *
     * @param string $question The user question.
     * @param array $candidates Candidate chunk records.
     * @param int $topn How many to keep.
     * @return array The kept chunk records (best-first), or the input order on failure.
     */
    public function rerank(string $question, array $candidates, int $topn): array {
        $candidates = array_values($candidates);
        if (count($candidates) <= $topn) {
            return $candidates;
        }

        $lines = [];
        foreach ($candidates as $i => $chunk) {
            $snippet = \core_text::substr(trim((string) $chunk->chunktext), 0, 300);
            $lines[] = "[$i] " . trim((string) $chunk->sourcetitle) . ': ' . $snippet;
        }
        $prompt = "Rank the following passages by how well they help answer the question. "
            . "Return ONLY a comma-separated list of the passage numbers, best first.\n\n"
            . "Question: " . $question . "\n\nPassages:\n" . implode("\n", $lines);

        try {
            // Reranking is a separate billable completion, so its usage is kept
            // and added to the turn by the caller.
            $result = $this->llm->chat_with_usage(
                [['role' => 'user', 'content' => $prompt]],
                config::rerank_model(),
                64
            );
            $answer = $result['content'];
            $this->usage = $result['usage'];
        } catch (llm_exception $e) {
            return array_slice($candidates, 0, $topn);
        }

        if (!preg_match_all('/\d+/', $answer, $m)) {
            return array_slice($candidates, 0, $topn);
        }

        $ordered = [];
        $seen = [];
        foreach ($m[0] as $num) {
            $idx = (int) $num;
            if (isset($candidates[$idx]) && !isset($seen[$idx])) {
                $ordered[] = $candidates[$idx];
                $seen[$idx] = true;
            }
            if (count($ordered) >= $topn) {
                break;
            }
        }
        // Backfill from the original order if the model returned too few.
        foreach ($candidates as $i => $chunk) {
            if (count($ordered) >= $topn) {
                break;
            }
            if (!isset($seen[$i])) {
                $ordered[] = $chunk;
                $seen[$i] = true;
            }
        }
        return $ordered;
    }
}
