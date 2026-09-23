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

namespace aisourcesextractor_scorm;

use local_elediaai_sources\accessibility_reporter;
use local_elediaai_sources\content_extractor;
use local_elediaai_sources\media_accessibility;
use local_elediaai_sources\multi_document_extractor;

/**
 * Content extractor for mod_scorm activities.
 *
 * Extracts the activity intro, the table of contents (SCO titles), the text of
 * HTML pages inside the package, and the narration transcript.
 *
 * The transcript is the part worth explaining. A published course is often a
 * player shell: its launch page carries no prose at all, only the script that
 * boots the course, so reading the launch page alone yields nothing usable. The
 * words the learner hears live in the caption track instead — which an
 * accessible package has to ship anyway. Captions are therefore the most
 * reliable text in such a package, and frequently the only one.
 *
 * Caption tracks are read wherever they are kept:
 *
 * - as standalone `.vtt` / `.srt` files, the tool-independent case;
 * - unwrapped from Articulate Storyline's `*_captions.js`, which carries the
 *   very same WebVTT payload URL-encoded inside a JavaScript wrapper because
 *   that tool bundles its assets as scripts rather than as separate files.
 *
 * Both end up in one parser: only the retrieval differs, the payload does not.
 *
 * @package    aisourcesextractor_scorm
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor implements accessibility_reporter, content_extractor, multi_document_extractor {
    /** @var int Maximum HTML size for regex body extraction. */
    private const MAX_BODY_REGEX_BYTES = 2097152;

    /** @var int Stop collecting once this much transcript/slide text has accumulated. */
    private const MAX_COLLECTED_BYTES = 2097152;

    /** @var int Skip individual asset files larger than this. */
    private const MAX_ASSET_BYTES = 4194304;

    /** @var string Suffix of the module-level document in the multi-document set. */
    private const MAIN_SUFFIX = 'main';

    /**
     * @var string[] On-screen strings that are player chrome, not content.
     *
     * Matched whole, never as a substring: "Weiter" alone is a navigation
     * button, but a slide reading "Weiter denken" is content. Captions are
     * never filtered — narration does not contain chrome.
     */
    private const CHROME_PATTERNS = [
        '/^\d+\s?%$/u',
        '/^\d+\s*\/\s*\d+$/u',
        '/^(next|previous|prev|back|submit|play|pause|replay|menu|resources|continue|start|exit|close)$/iu',
        '/^(weiter|zurueck|zurück|absenden|abschicken|start|starten|menue|menü|ressourcen|beenden|schliessen|schließen)$/iu',
        '/^(slide|folie)\s*\d+\s*(of|von)\s*\d+$/iu',
        '/^(frage|question)\s*\d+\s*(of|von)\s*\d+$/iu',
    ];

    /**
     * Check whether this extractor supports the given module.
     *
     * @param \cm_info $cm The course module info.
     * @return bool True if this is a scorm module.
     */
    public function supports(\cm_info $cm): bool {
        return $cm->modname === 'scorm';
    }

    /**
     * Extract content from a SCORM activity.
     *
     * @param \cm_info $cm The course module info.
     * @return array|null Extracted document data, or null if no content.
     */
    public function extract(\cm_info $cm): ?array {
        global $DB;

        $scorm = $DB->get_record(
            'scorm',
            ['id' => $cm->instance],
            'id, name, intro, scormtype',
            MUST_EXIST,
        );
        $context = \core\context\module::instance($cm->id);

        // The document leads with its own heading, which suppresses the
        // generic one ingestion_manager would otherwise prepend. The reason is
        // the word: a package is filed in Moodle as "SCORM", but learners ask
        // for a "Lernpaket" -- and LiteRAG matches text, not concepts, so a
        // word that is nowhere in the document is a question that finds
        // nothing (AI-53). Naming the kind in the heading puts both terms in
        // the first chunk, where they are cheapest to find.
        $html = '<h1>' . htmlspecialchars(
            get_string('documentheading', 'aisourcesextractor_scorm', $scorm->name),
            ENT_QUOTES,
            'UTF-8'
        ) . '</h1>' . "\n";

        // SCORM intro.
        if (!empty($scorm->intro)) {
            $html .= file_rewrite_pluginfile_urls(
                $scorm->intro,
                'pluginfile.php',
                $context->id,
                'mod_scorm',
                'intro',
                0,
            );
        }

        // Build a file index for locally-stored packages.
        $filesbypath = [];
        if ($scorm->scormtype === 'local') {
            $fs = get_file_storage();
            $files = $fs->get_area_files(
                $context->id,
                'mod_scorm',
                'content',
                0,
                'sortorder, filepath, filename',
                false,
            );

            foreach ($files as $file) {
                $relpath = ltrim($file->get_filepath() . $file->get_filename(), '/');
                $filesbypath[$relpath] = $file;
            }
        }

        // Get all SCOs (learning objects) for this package.
        $scoes = $DB->get_records(
            'scorm_scoes',
            ['scorm' => $scorm->id],
            'sortorder ASC, id ASC',
            'id, title, launch, scormtype',
        );

        // Output SCO titles and extract HTML content where possible.
        foreach ($scoes as $sco) {
            if (empty($sco->title)) {
                continue;
            }

            $html .= '<h2>' . htmlspecialchars($sco->title, ENT_QUOTES, 'UTF-8') . '</h2>' . "\n";

            // Try to extract text from the launch page if it's an HTML file.
            if (!empty($sco->launch) && !empty($filesbypath)) {
                $launchpath = preg_replace('/[?#].*$/', '', $sco->launch);
                if (isset($filesbypath[$launchpath])) {
                    $file = $filesbypath[$launchpath];
                    $mimetype = $file->get_mimetype();

                    if ($mimetype === 'text/html' || $mimetype === 'application/xhtml+xml') {
                        $content = $file->get_content();
                        if (!empty($content)) {
                            $bodytext = self::extract_body_content($content);
                            // Only include if the body has meaningful text.
                            $plaintext = trim(strip_tags($bodytext));
                            if (!empty($plaintext)) {
                                $html .= $bodytext . "\n";
                            }
                        }
                    }
                }
            }
        }

        // The player shell carries no prose; these two do.
        $html .= self::collect_slide_text($filesbypath);
        $html .= self::collect_transcripts($filesbypath, self::course_language($cm));

        if (trim(strip_tags($html)) === '') {
            return null;
        }

        return [
            'content' => $html,
            'content_type' => 'text/html',
            'title' => $scorm->name,
        ];
    }

    /**
     * Extract the package as a set of documents.
     *
     * The narrative — intro, table of contents, page text, on-screen text and
     * transcript — stays one document, because it only makes sense read
     * together. PDFs shipped inside the package become sub-documents instead:
     * they keep their native content type so the RAG service parses them
     * itself, rather than being flattened into the HTML.
     *
     * Sub-document suffixes are derived from the file path, which is stable
     * across re-uploads. A renamed file therefore looks like a new one — the
     * manager's prefix-scoped delete clears the old set first, so nothing is
     * orphaned.
     *
     * @param \cm_info $cm The course module info.
     * @return array<int, array{content: string, content_type: string, title: string, suffix: string}>
     */
    public function extract_documents(\cm_info $cm): array {
        global $DB;

        $documents = [];

        $main = $this->extract($cm);
        if ($main !== null) {
            $main['suffix'] = self::MAIN_SUFFIX;
            $documents[] = $main;
        }

        $scorm = $DB->get_record('scorm', ['id' => $cm->instance], 'id, name, scormtype', MUST_EXIST);
        if ($scorm->scormtype !== 'local') {
            return $documents;
        }

        $context = \core\context\module::instance($cm->id);
        $files = get_file_storage()->get_area_files(
            $context->id,
            'mod_scorm',
            'content',
            0,
            'sortorder, filepath, filename',
            false
        );

        foreach ($files as $file) {
            if ($file->get_mimetype() !== 'application/pdf' || $file->get_filesize() > self::MAX_ASSET_BYTES) {
                continue;
            }
            $relpath = ltrim($file->get_filepath() . $file->get_filename(), '/');
            $documents[] = [
                'content' => (string) $file->get_content(),
                'content_type' => 'application/pdf',
                'title' => $file->get_filename(),
                'suffix' => 'pdf-' . $relpath,
            ];
        }

        return $documents;
    }

    /**
     * Extract the body content from a full HTML document.
     *
     * Script and style blocks go first. A published package inlines its player
     * stylesheet into the launch page, and without this the extracted "text" is
     * a screenful of CSS rules — which would be indexed as if it were course
     * content.
     *
     * @param string $html The full HTML document.
     * @return string The body content.
     */
    private static function extract_body_content(string $html): string {
        if (strlen($html) > self::MAX_BODY_REGEX_BYTES) {
            return $html;
        }
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
            $html = trim($matches[1]);
        }
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        $html = trim((string) $html);

        return $html === '' ? '' : self::strip_player_shell($html);
    }

    #[\Override]
    public function accessibility_report(\cm_info $cm): ?array {
        global $DB;

        $scorm = $DB->get_record('scorm', ['id' => $cm->instance], 'id, scormtype', IGNORE_MISSING);
        if (!$scorm || $scorm->scormtype !== 'local') {
            // A package hosted elsewhere is not ours to inspect; saying nothing
            // beats reporting "no media" about files we never saw.
            return null;
        }

        $files = get_file_storage()->get_area_files(
            \core\context\module::instance($cm->id)->id,
            'mod_scorm',
            'content',
            0,
            'sortorder, filepath, filename',
            false,
        );

        $contents = [];
        foreach ($files as $file) {
            $path = ltrim($file->get_filepath() . $file->get_filename(), '/');
            // Media is weighed by name alone; only files that can carry a
            // reference are read, and only while they stay a sensible size.
            $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            $readable = in_array($extension, ['html', 'htm', 'xhtml', 'js', 'json', 'xml'], true);
            $contents[$path] = $readable && $file->get_filesize() <= self::MAX_ASSET_BYTES
                ? $file->get_content()
                : '';
        }

        return $contents === [] ? null : media_accessibility::scan($contents);
    }

    /**
     * Drop the authoring tool's player skeleton from a launch page.
     *
     * An authored package launches into a shell whose prose lives elsewhere —
     * in the slide data and the caption tracks, both collected separately. What
     * the shell itself contributes is empty containers, an offline dialog and
     * inline SVG path data, which reach the knowledge base as text that means
     * nothing and dilutes retrieval.
     *
     * The rules are deliberately structural rather than a list of known
     * container names: anything invisible to learners, and anything carrying no
     * text at all, is not content. That keeps hand-written SCORM packages —
     * where the launch page *is* the content — untouched.
     *
     * @param string $fragment Body markup with scripts and styles already gone.
     * @return string The same markup without the shell.
     */
    private static function strip_player_shell(string $fragment): string {
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML(
            '<div>' . $fragment . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->documentElement;
        if (!$loaded || !$root instanceof \DOMElement) {
            return $fragment;
        }

        $xpath = new \DOMXPath($doc);
        // Invisible, transient or non-prose by construction. The ARIA roles
        // cover interface that only ever appears in reaction to something — an
        // offline warning is not part of the course. Landmarks such as
        // navigation stay: in a hand-written package they may hold a table of
        // contents, and losing that would cost real content.
        $transient = '|alert|alertdialog|dialog|status|';
        $hidden = '//comment() | //svg | //link | //meta | //noscript'
            . ' | //*[@hidden]'
            . ' | //*[contains(translate(@style, " ", ""), "display:none")]'
            . ' | //*[@role][contains("' . $transient . '", concat("|", @role, "|"))]';
        foreach ($xpath->query($hidden) as $node) {
            if ($node->parentNode !== null) {
                $node->parentNode->removeChild($node);
            }
        }

        self::remove_textless_elements($root);

        $result = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $result .= (string) $doc->saveHTML($child);
        }

        return trim($result);
    }

    /**
     * Remove elements that carry no text, innermost first.
     *
     * @param \DOMElement $element The subtree to prune.
     */
    private static function remove_textless_elements(\DOMElement $element): void {
        foreach (iterator_to_array($element->childNodes) as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            self::remove_textless_elements($child);
            if (!self::carries_text($child)) {
                $element->removeChild($child);
            }
        }
    }

    /**
     * Whether an element still contributes something readable.
     *
     * Called after its children were pruned, so a surviving child element is
     * itself proof that something worth keeping sits below.
     *
     * @param \DOMElement $element The element to judge.
     * @return bool
     */
    private static function carries_text(\DOMElement $element): bool {
        if (trim((string) $element->textContent) !== '') {
            return true;
        }
        foreach (['alt', 'title', 'aria-label'] as $attribute) {
            if (trim($element->getAttribute($attribute)) !== '') {
                return true;
            }
        }

        return $element->getElementsByTagName('*')->length > 0;
    }

    /**
     * Collect every caption track in the package as one transcript.
     *
     * @param array<string, \stored_file> $filesbypath Package files keyed by relative path.
     * @param string $courselang The course language, or '' when unknown.
     * @return string HTML section, or '' when the package has no captions.
     */
    private static function collect_transcripts(array $filesbypath, string $courselang = ''): string {
        $blocks = [];
        $seen = [];
        $bytes = 0;
        $preferred = [];

        foreach ($filesbypath as $path => $file) {
            if ($bytes >= self::MAX_COLLECTED_BYTES || $file->get_filesize() > self::MAX_ASSET_BYTES) {
                continue;
            }

            $cues = [];
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if ($extension === 'vtt' || $extension === 'srt') {
                // A language code in the filename (lesson.de.vtt) is the
                // tool-independent convention for multi-language tracks.
                $langcode = self::filename_language($path);
                $cues[] = ['lang' => $langcode, 'vtt' => (string) $file->get_content()];
            } else if (substr($path, -12) === '_captions.js') {
                $cues = self::unwrap_storyline_captions((string) $file->get_content());
            } else {
                continue;
            }

            foreach ($cues as $entry) {
                $cue = $entry['vtt'];
                $lang = (string) $entry['lang'];
                $text = self::cues_to_text($cue);
                if ($text === '') {
                    continue;
                }
                // The same track can ship both as a file and inside the player bundle.
                $key = md5($text);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $blocks[] = $text;
                $bytes += strlen($text);
                if ($courselang !== '' && $lang !== '' && self::language_matches($lang, $courselang)) {
                    $preferred[] = $text;
                }
            }
        }

        // When tracks name their language and some match the course, keep only
        // those — otherwise every language would be indexed side by side. Any
        // other case falls back to everything, so the only transcript a
        // package has is never dropped.
        if (!empty($preferred)) {
            $blocks = $preferred;
        }

        if (empty($blocks)) {
            return '';
        }

        $html = '<h2>' . htmlspecialchars(
            get_string('transcript', 'aisourcesextractor_scorm'),
            ENT_QUOTES,
            'UTF-8'
        ) . '</h2>' . "\n";

        foreach ($blocks as $block) {
            $html .= '<p>' . htmlspecialchars($block, ENT_QUOTES, 'UTF-8') . '</p>' . "\n";
        }

        return $html;
    }

    /**
     * Pull the WebVTT payloads out of an Articulate Storyline captions script.
     *
     * The file is a single `window.globalLoadJsAsset('<path>', {...})` call whose
     * JSON holds one entry per language, each carrying URL-encoded WebVTT.
     *
     * @param string $js The script contents.
     * @return array<int, array{lang: string, vtt: string}> Decoded tracks per language.
     */
    private static function unwrap_storyline_captions(string $js): array {
        if (!preg_match('/\{.*\}/s', $js, $matches)) {
            return [];
        }

        $data = json_decode($matches[0], true);
        if (!is_array($data) || empty($data['captions']) || !is_array($data['captions'])) {
            return [];
        }

        $out = [];
        foreach ($data['captions'] as $caption) {
            if (!is_array($caption) || !isset($caption['data']) || !is_string($caption['data'])) {
                continue;
            }
            $decoded = urldecode($caption['data']);
            if (trim($decoded) !== '') {
                $out[] = [
                    'lang' => (string) ($caption['langCode'] ?? ''),
                    'vtt' => $decoded,
                ];
            }
        }

        return $out;
    }

    /**
     * The course language, or '' when the site default applies.
     *
     * @param \cm_info $cm The course module.
     * @return string
     */
    private static function course_language(\cm_info $cm): string {
        try {
            return (string) ($cm->get_course()->lang ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * The language code embedded in a subtitle filename, if any.
     *
     * Recognises the common `name.<lang>.vtt` convention.
     *
     * @param string $path The file path.
     * @return string The code, or '' when the name carries none.
     */
    private static function filename_language(string $path): string {
        $name = pathinfo($path, PATHINFO_FILENAME);
        if (preg_match('/\.([a-z]{2}(?:[-_][a-z]{2})?)$/i', $name, $matches)) {
            return $matches[1];
        }
        return '';
    }

    /**
     * Whether a track language satisfies the course language.
     *
     * Compared on the primary subtag, so a `de` course accepts `de-DE`.
     *
     * @param string $tracklang The track's language code.
     * @param string $courselang The course language.
     * @return bool
     */
    private static function language_matches(string $tracklang, string $courselang): bool {
        $primary = static function (string $code): string {
            $code = strtolower(str_replace('_', '-', trim($code)));
            return explode('-', $code)[0];
        };
        return $primary($tracklang) !== '' && $primary($tracklang) === $primary($courselang);
    }

    /**
     * Whether an on-screen string is player chrome rather than content.
     *
     * @param string $text The harvested string.
     * @return bool
     */
    private static function is_chrome(string $text): bool {
        foreach (self::CHROME_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Reduce a WebVTT or SubRip document to its spoken text.
     *
     * Both formats are blocks separated by blank lines. Dropping the header, the
     * NOTE blocks, the cue identifiers and the timing lines leaves the words.
     *
     * @param string $subtitles The subtitle document.
     * @return string Plain text, or '' when nothing is left.
     */
    private static function cues_to_text(string $subtitles): string {
        $subtitles = str_replace(["\r\n", "\r"], "\n", $subtitles);
        $lines = [];

        foreach (preg_split('/\n{2,}/', $subtitles) as $block) {
            $block = trim($block);
            if ($block === '' || strpos($block, 'WEBVTT') === 0 || strpos($block, 'NOTE') === 0) {
                continue;
            }

            foreach (explode("\n", $block) as $line) {
                $line = trim($line);
                // Timing lines, cue numbers and WebVTT region/style blocks carry no words.
                if ($line === '' || strpos($line, '-->') !== false || ctype_digit($line)) {
                    continue;
                }
                if (preg_match('/^(STYLE|REGION|Kind:|Language:|Source:)/i', $line)) {
                    continue;
                }
                $lines[] = $line;
            }
        }

        return trim(implode(' ', $lines));
    }

    /**
     * Collect the on-screen text of an Articulate Storyline package.
     *
     * Each slide is a `window.globalProvideData('slide', '<json>')` call. This is
     * that tool's internal format rather than anything SCORM prescribes, so it is
     * read defensively: anything that does not parse is skipped.
     *
     * @param array<string, \stored_file> $filesbypath Package files keyed by relative path.
     * @return string HTML section, or '' when the package has no such slides.
     */
    private static function collect_slide_text(array $filesbypath): string {
        // On-screen text is the noisier half of a package; sites that find it
        // unhelpful can switch it off without losing the transcript.
        if (get_config('local_elediaai_sources', 'scorm_harvest_slidetext') === '0') {
            return '';
        }

        $html = '';
        $bytes = 0;

        foreach ($filesbypath as $path => $file) {
            if ($bytes >= self::MAX_COLLECTED_BYTES || $file->get_filesize() > self::MAX_ASSET_BYTES) {
                continue;
            }
            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'js') {
                continue;
            }

            $slide = self::decode_storyline_slide((string) $file->get_content());
            if ($slide === null) {
                continue;
            }

            $texts = [];
            self::harvest_text_values($slide, $texts);
            if (empty($texts)) {
                continue;
            }

            if (!empty($slide['title']) && is_string($slide['title'])) {
                $html .= '<h3>' . htmlspecialchars($slide['title'], ENT_QUOTES, 'UTF-8') . '</h3>' . "\n";
            }
            foreach ($texts as $text) {
                $html .= '<p>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>' . "\n";
                $bytes += strlen($text);
            }
        }

        if ($html === '') {
            return '';
        }

        return '<h2>' . htmlspecialchars(
            get_string('slidetext', 'aisourcesextractor_scorm'),
            ENT_QUOTES,
            'UTF-8'
        ) . '</h2>' . "\n" . $html;
    }

    /**
     * Decode the JSON argument of a Storyline slide script.
     *
     * @param string $js The script contents.
     * @return array|null The decoded slide, or null when this is not one.
     */
    private static function decode_storyline_slide(string $js): ?array {
        if (strpos($js, "globalProvideData('slide'") === false) {
            return null;
        }
        if (!preg_match("/globalProvideData\(\s*'slide'\s*,\s*'(.*)'\s*\)/s", $js, $matches)) {
            return null;
        }

        // The JSON travels inside a single-quoted JavaScript string, so its own
        // quotes arrive escaped for that outer layer and have to be unescaped
        // before it is JSON again. \n is left alone: it belongs to the JSON
        // below, where it is a valid escape, and turning it into a real newline
        // would break the string it sits in.
        $json = str_replace(['\\"', "\\'"], ['"', "'"], $matches[1]);
        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Walk a decoded slide and collect its human-readable strings.
     *
     * @param mixed $node The current node.
     * @param string[] $texts Collected strings, passed by reference.
     * @return void
     */
    private static function harvest_text_values($node, array &$texts): void {
        if (is_array($node)) {
            if (isset($node['text']) && is_string($node['text'])) {
                // The tool marks its own line breaks with a literal backslash-n
                // inside the string; as text it is an artefact, not content.
                $value = str_replace('\\n', ' ', $node['text']);
                $value = trim((string) preg_replace('/\s+/u', ' ', $value));
                // Values like %_playerVars.menuSlideNumber% are runtime
                // placeholders; navigation labels are chrome, not content.
                $usable = $value !== ''
                    && strpos($value, '%') !== 0
                    && !self::is_chrome($value)
                    && !in_array($value, $texts, true);
                if ($usable) {
                    $texts[] = $value;
                }
            }
            foreach ($node as $child) {
                self::harvest_text_values($child, $texts);
            }
        }
    }
}
