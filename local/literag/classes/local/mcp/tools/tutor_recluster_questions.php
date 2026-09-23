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

namespace local_literag\local\mcp\tools;

use local_literag\local\mcp\result;
use local_literag\local\mcp\tool;
use local_literag\local\mcp\tool_exception;
use local_literag\local\query_normaliser;
use local_literag\local\token_validator;

/**
 * Optional tutor_recluster_questions tool: batch re-label logged questions.
 *
 * Authenticated by the powerless maintenance account's token, PINNED on the
 * username "elediaai_tutor_service" — a learner token MUST be rejected for this
 * tool. Classifies each question into the supplied label registry (deterministic
 * keyword overlap), minting a new label only when nothing fits. Idempotent.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tutor_recluster_questions implements tool {
    /** @var string The maintenance account username this tool is pinned to. */
    private const SERVICE_USERNAME = 'elediaai_tutor_service';

    /**
     * Re-label a batch of logged questions (maintenance account only).
     *
     * @param array $arguments
     * @return array
     * @throws tool_exception
     */
    public function handle(array $arguments): array {
        $user = token_validator::resolve_user((string) ($arguments['moodle_token'] ?? ''));
        if ($user === null) {
            throw new tool_exception('invalid moodle_token', -32001);
        }
        // Pin on the maintenance account; reject any learner token.
        if ($user->username !== self::SERVICE_USERNAME) {
            throw new tool_exception('recluster requires the maintenance account', -32002);
        }

        $existinglabels = array_values(array_filter(array_map(
            static fn($l) => trim((string) $l),
            (array) ($arguments['existing_labels'] ?? [])
        )));
        $questions = (array) ($arguments['questions'] ?? []);

        $topics = [];
        foreach ($questions as $question) {
            if (!is_array($question) || !isset($question['id'])) {
                continue;
            }
            $id = (int) $question['id'];
            $text = (string) ($question['text'] ?? '');
            $label = $this->classify($text, $existinglabels);
            if ($label !== '') {
                $topics[] = ['id' => $id, 'topic' => \core_text::substr($label, 0, 100)];
            }
        }

        return result::tool('', ['topics' => $topics], false);
    }

    /**
     * Classify a question into the existing label set, or mint one.
     *
     * Deterministic: chooses the existing label sharing the most normalised terms
     * with the question; when none overlaps, mints a label from the question's
     * leading significant terms.
     *
     * @param string $text Question text.
     * @param string[] $labels Existing label registry.
     * @return string The chosen label (possibly empty for an empty question).
     */
    private function classify(string $text, array $labels): string {
        $qterms = query_normaliser::terms($text);
        if (empty($qterms)) {
            return $labels[0] ?? '';
        }
        $qset = array_flip($qterms);

        $best = '';
        $bestscore = 0;
        foreach ($labels as $label) {
            $score = 0;
            foreach (query_normaliser::terms($label) as $lt) {
                if (isset($qset[$lt])) {
                    $score++;
                }
            }
            if ($score > $bestscore) {
                $bestscore = $score;
                $best = $label;
            }
        }
        if ($bestscore > 0) {
            return $best;
        }

        // Mint a new label from the leading significant terms.
        $minted = ucwords(implode(' ', array_slice($qterms, 0, 4)));
        return $minted;
    }
}
