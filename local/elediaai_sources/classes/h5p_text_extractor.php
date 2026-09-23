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

namespace local_elediaai_sources;

/**
 * Utility to extract educational text from H5P content JSON.
 *
 * H5P stores interactive content as deeply-nested JSON (the `content.json`
 * inside the .h5p package, or the deployed `h5p.jsoncontent` DB field). The
 * structure varies across 40+ content types. This class walks the tree,
 * recognising the common assessable/interactive shapes (multiple choice,
 * true/false, fill-in-the-blanks, drag text, summary, dialog/flash cards,
 * accordion, …) and emitting *labelled* text blocks ("Question:", "Correct
 * answer:", "Answer:", "Cloze:", "Section:") so the downstream RAG embeddings
 * preserve the question/answer relationship. Unknown shapes fall back to a
 * generic recursive walk that harvests content strings while filtering out
 * identifiers, config and styling values.
 *
 * It also resolves the H5P payload for a stored .h5p file: the deployed record
 * when present, otherwise reading `content/content.json` straight from the
 * package zip — so content is indexable even if nobody has opened the activity
 * in a browser yet.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class h5p_text_extractor {
    /**
     * Keys whose values are skipped during the generic recursive walk.
     *
     * Identifiers, configuration, behaviour, localisation and styling keys
     * that never carry educational text. NOTE: `action` is deliberately NOT
     * skipped — Course Presentation, Interactive Video, Branching Scenario and
     * Interactive Book all nest their sub-content libraries under `action`.
     */
    private const SKIP_KEYS = [
        'subcontentid',
        'library',
        'css',
        'path',
        'mime',
        'mimetype',
        'copyright',
        'metadata',
        'overridesettings',
        'behaviour',
        'confirmationdialog',
        'overallfeedback',
        'l10n',
        'a11y',
        'ui',
        'localize',
        'scorebarlabel',
        'submitanswer',
        'confirmcheck',
        'confirmretry',
        'checkanswer',
        'showsolutionbutton',
        'retrybutton',
        'tipbuttonlabel',
        'lock',
        'reset',
        'scorebar',
        'scoreexplanation',
        'showsolution',
        'tryagain',
        'check',
        'font',
        'height',
        'width',
        'x',
        'y',
    ];

    /** @var int Minimum string length to consider a generic value as content. */
    private const MIN_TEXT_LENGTH = 10;

    /** @var int Largest .h5p package (bytes) we will open to read content.json. */
    private const MAX_PACKAGE_BYTES = 104857600;

    /**
     * Extract plain text from an H5P content JSON string.
     *
     * Backwards-compatible entry point: returns the labelled blocks joined by
     * newlines. Safe for `text/plain` ingestion.
     *
     * @param string $jsoncontent The raw content JSON (deployed jsoncontent or content.json).
     * @return string Extracted plain text (may be empty).
     */
    public static function extract_text_from_json(string $jsoncontent): string {
        return implode("\n", self::extract_blocks_from_json($jsoncontent));
    }

    /**
     * Extract labelled text blocks from an H5P content JSON string.
     *
     * Each block is a single line such as "Question: …", "Correct answer: …"
     * or a bare content string. Order is preserved and exact duplicates are
     * removed.
     *
     * @param string $jsoncontent The raw content JSON.
     * @return string[] Ordered, de-duplicated text blocks.
     */
    public static function extract_blocks_from_json(string $jsoncontent): array {
        $data = json_decode($jsoncontent, true);
        if (!is_array($data)) {
            return [];
        }

        // The deployed jsoncontent wraps the params under a 'params' key;
        // a package content.json holds the params at the top level.
        if (isset($data['params']) && is_array($data['params'])) {
            $data = $data['params'];
        }

        $blocks = [];
        self::walk($data, $blocks);

        $blocks = array_filter($blocks, static fn($b) => trim($b) !== '');
        return array_values(array_unique($blocks));
    }

    /**
     * Resolve the content JSON for a stored .h5p file.
     *
     * Prefers the deployed `h5p` record (already-validated, filtered JSON).
     * Falls back to reading `content/content.json` directly from the package
     * zip, so the activity is indexable without having been viewed/deployed.
     *
     * @param \stored_file $file The .h5p package file.
     * @return string|null The content JSON, or null when unavailable.
     */
    public static function jsoncontent_from_file(\stored_file $file): ?string {
        // 1. Deployed record (fast path).
        $h5p = \core_h5p\api::get_content_from_pathnamehash($file->get_pathnamehash());
        if ($h5p !== null && !empty($h5p->jsoncontent)) {
            return $h5p->jsoncontent;
        }

        // 2. Read content.json from the package zip — no deployment required.
        if (!preg_match('/\.h5p$/i', $file->get_filename())) {
            return null;
        }
        if ($file->get_filesize() <= 0 || $file->get_filesize() > self::MAX_PACKAGE_BYTES) {
            return null;
        }

        try {
            $packer = get_file_packer('application/zip');
            $tmpdir = make_request_directory();
            // Extract only the content descriptor, not the (possibly large) assets.
            $packer->extract_to_pathname($file, $tmpdir, ['content/content.json']);
            $path = $tmpdir . '/content/content.json';
            if (is_readable($path)) {
                $json = file_get_contents($path);
                if (is_string($json) && trim($json) !== '') {
                    return $json;
                }
            }
        } catch (\Throwable $e) {
            debugging('[h5p_text_extractor] Could not read content.json from package: '
                . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return null;
    }

    /**
     * Recursively walk a decoded H5P structure, collecting labelled blocks.
     *
     * @param mixed $data The current node.
     * @param string[] $blocks Collected blocks (by reference).
     */
    private static function walk(mixed $data, array &$blocks): void {
        if (is_string($data)) {
            self::collect_generic($data, $blocks);
            return;
        }
        if (!is_array($data)) {
            return;
        }

        // Sequential list: walk every element.
        if (array_is_list($data)) {
            foreach ($data as $item) {
                self::walk($item, $blocks);
            }
            return;
        }

        // Associative node: try the semantic handlers first, then recurse into
        // any keys they did not consume (and that are not blocklisted).
        $consumed = self::apply_semantic($data, $blocks);

        foreach ($data as $key => $value) {
            $keystr = is_string($key) ? $key : '';
            if ($keystr !== '' && isset($consumed[$keystr])) {
                continue;
            }
            if ($keystr !== '' && in_array(strtolower($keystr), self::SKIP_KEYS, true)) {
                continue;
            }
            self::walk($value, $blocks);
        }
    }

    /**
     * Apply shape-based semantic handlers to an associative node.
     *
     * Recognises the common H5P content shapes and pushes labelled blocks.
     * Returns the set of keys it consumed so the caller skips re-collecting
     * them generically.
     *
     * @param array $node The associative node.
     * @param string[] $blocks Collected blocks (by reference).
     * @return array<string, true> Keys consumed by the handlers.
     */
    private static function apply_semantic(array $node, array &$blocks): array {
        $consumed = [];

        // Multiple/single choice: question + answers[] (each {text, correct?}).
        if (
            isset($node['question']) && is_string($node['question'])
                && isset($node['answers']) && is_array($node['answers']) && array_is_list($node['answers'])
        ) {
            $q = self::clean($node['question']);
            if ($q !== '') {
                $blocks[] = 'Question: ' . $q;
            }
            foreach ($node['answers'] as $ans) {
                if (is_string($ans)) {
                    $t = self::clean($ans);
                    if ($t !== '') {
                        $blocks[] = 'Answer: ' . $t;
                    }
                    continue;
                }
                if (is_array($ans) && isset($ans['text'])) {
                    $t = self::clean((string) $ans['text']);
                    if ($t === '') {
                        continue;
                    }
                    $blocks[] = (!empty($ans['correct']) ? 'Correct answer: ' : 'Answer: ') . $t;
                    if (!empty($ans['tipsAndFeedback']['tip'])) {
                        $tip = self::clean((string) $ans['tipsAndFeedback']['tip']);
                        if ($tip !== '') {
                            $blocks[] = 'Hint: ' . $tip;
                        }
                    }
                }
            }
            $consumed['question'] = true;
            $consumed['answers'] = true;
        }

        // True/False: question + correct (bool or "true"/"false"), no answers[].
        if (
            !isset($consumed['question']) && isset($node['question']) && is_string($node['question'])
                && isset($node['correct'])
                && (is_bool($node['correct']) || in_array($node['correct'], ['true', 'false'], true))
        ) {
            $q = self::clean($node['question']);
            if ($q !== '') {
                $blocks[] = 'Question: ' . $q;
            }
            $val = is_bool($node['correct']) ? $node['correct'] : ($node['correct'] === 'true');
            $blocks[] = 'Correct answer: ' . ($val ? 'True' : 'False');
            $consumed['question'] = true;
            $consumed['correct'] = true;
        }

        // Fill-in-the-blanks: questions is a list of strings with *answer* markers.
        if (isset($node['questions']) && is_array($node['questions']) && array_is_list($node['questions'])) {
            $any = false;
            foreach ($node['questions'] as $q) {
                if (is_string($q)) {
                    $t = self::clean($q);
                    if ($t !== '') {
                        $blocks[] = 'Cloze: ' . $t;
                        $any = true;
                    }
                }
            }
            if ($any) {
                $consumed['questions'] = true;
            }
        }

        // Drag the text / Mark the words: a single textField with *word* markers.
        if (isset($node['textField']) && is_string($node['textField'])) {
            $t = self::clean($node['textField']);
            if ($t !== '') {
                $blocks[] = 'Text: ' . $t;
                $consumed['textField'] = true;
            }
        }

        // Summary: summaries[].summary[]; the first option in each set is correct.
        if (isset($node['summaries']) && is_array($node['summaries']) && array_is_list($node['summaries'])) {
            foreach ($node['summaries'] as $set) {
                if (is_array($set) && isset($set['summary']) && is_array($set['summary'])) {
                    foreach (array_values($set['summary']) as $i => $opt) {
                        $t = self::clean((string) $opt);
                        if ($t !== '') {
                            $blocks[] = ($i === 0 ? 'Correct statement: ' : 'Statement: ') . $t;
                        }
                    }
                }
            }
            $consumed['summaries'] = true;
        }

        // Dialog cards / Flashcards: dialogs[] or cards[] with text/answer.
        foreach (['dialogs', 'cards'] as $cardkey) {
            if (isset($node[$cardkey]) && is_array($node[$cardkey]) && array_is_list($node[$cardkey])) {
                foreach ($node[$cardkey] as $card) {
                    if (!is_array($card)) {
                        continue;
                    }
                    foreach (['text', 'question'] as $promptkey) {
                        if (isset($card[$promptkey])) {
                            $t = self::clean((string) $card[$promptkey]);
                            if ($t !== '') {
                                $blocks[] = 'Prompt: ' . $t;
                            }
                        }
                    }
                    if (isset($card['answer'])) {
                        $t = self::clean((string) $card['answer']);
                        if ($t !== '') {
                            $blocks[] = 'Answer: ' . $t;
                        }
                    }
                    if (isset($card['tip'])) {
                        $t = self::clean((string) $card['tip']);
                        if ($t !== '') {
                            $blocks[] = 'Hint: ' . $t;
                        }
                    }
                }
                $consumed[$cardkey] = true;
            }
        }

        // Accordion: panels[] with {title, content}. Label titles, recurse content.
        if (isset($node['panels']) && is_array($node['panels']) && array_is_list($node['panels'])) {
            foreach ($node['panels'] as $panel) {
                if (!is_array($panel)) {
                    continue;
                }
                if (isset($panel['title'])) {
                    $t = self::clean((string) $panel['title']);
                    if ($t !== '') {
                        $blocks[] = 'Section: ' . $t;
                    }
                }
                if (isset($panel['content'])) {
                    self::walk($panel['content'], $blocks);
                }
            }
            $consumed['panels'] = true;
        }

        // Essay / open-ended task description.
        if (isset($node['taskDescription']) && is_string($node['taskDescription'])) {
            $t = self::clean($node['taskDescription']);
            if ($t !== '') {
                $blocks[] = 'Task: ' . $t;
                $consumed['taskDescription'] = true;
            }
        }

        return $consumed;
    }

    /**
     * Clean a known-good text field: strip tags, decode entities, trim.
     *
     * No length or URL filtering — these are fields the semantic handlers have
     * already identified as content, so short answers ("H2O", "True") survive.
     *
     * @param string $value The raw value.
     * @return string The cleaned text.
     */
    private static function clean(string $value): string {
        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim($text);
    }

    /**
     * Collect a generic (unlabelled) string value, filtering out non-content.
     *
     * Applies the minimum-length filter and excludes URLs, file paths, colour
     * codes and symbol-only values.
     *
     * @param string $value The raw string value.
     * @param string[] $blocks Collected blocks (by reference).
     */
    private static function collect_generic(string $value, array &$blocks): void {
        $text = self::clean($value);

        if (strlen($text) < self::MIN_TEXT_LENGTH) {
            return;
        }
        if (preg_match('#^(https?://|/|\./)#i', $text)) {
            return;
        }
        if (preg_match('/^[a-zA-Z0-9_-]+\.[a-zA-Z]{2,4}$/', $text)) {
            return;
        }
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $text)) {
            return;
        }
        if (!preg_match('/[a-zA-Z\p{L}]{2,}/u', $text)) {
            return;
        }

        $blocks[] = $text;
    }
}
