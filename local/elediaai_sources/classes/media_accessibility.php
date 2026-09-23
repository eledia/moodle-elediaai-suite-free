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
 * Decides whether the media inside a package carries captions.
 *
 * Deliberately not written against one authoring tool. A package may come from
 * Storyline, Rise, Captivate, iSpring, Lectora or a hand-written export, and
 * each links captions differently — so this looks for the *forms* the link can
 * take rather than for a product:
 *
 * 1. `<track kind="captions|subtitles">` inside an HTML `<video>`/`<audio>`.
 * 2. A caption file named after its media file (`lesson.vtt` for `lesson.mp4`,
 *    including a language infix such as `lesson.de.vtt`).
 * 3. A machine-readable reference in packaged data, where a media path and a
 *    caption path sit inside the same JSON object.
 *
 * What it must never do is guess. A package can hold forty media files and
 * forty caption files that this cannot pair up; calling that "accessible" would
 * be a false clearance, and calling it "not accessible" a false accusation.
 * Such media are reported as undetermined, and the overall verdict says so.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class media_accessibility {
    /** @var string Every media file was matched to a caption track. */
    public const VERDICT_COMPLETE = 'complete';

    /** @var string At least one media file demonstrably has no captions. */
    public const VERDICT_INCOMPLETE = 'incomplete';

    /** @var string Captions exist but cannot be paired with their media. */
    public const VERDICT_UNDETERMINED = 'undetermined';

    /** @var string The package holds no audio or video at all. */
    public const VERDICT_NOMEDIA = 'nomedia';

    /** @var string[] Extensions of audio-only media. */
    private const AUDIO_EXTENSIONS = ['mp3', 'm4a', 'wav', 'oga', 'aac', 'flac'];

    /** @var string[] Extensions of media carrying a picture. */
    private const VIDEO_EXTENSIONS = ['mp4', 'm4v', 'ogv', 'webm', 'mov', 'mpeg', 'mpg', 'ogg'];

    /** @var string[] Extensions counted as media that needs a text alternative. */
    private const MEDIA_EXTENSIONS = [
        'mp3', 'mp4', 'm4a', 'm4v', 'wav', 'ogg', 'oga', 'ogv', 'webm', 'aac', 'flac', 'mov', 'mpeg', 'mpg',
    ];

    /** @var string Names that mark a file as a spoken-word transcript. */
    private const TRANSCRIPT_NAME = '/transcript|transkript|mitschrift/i';

    /** @var string[] Extensions counted as caption tracks. */
    private const CAPTION_EXTENSIONS = ['vtt', 'srt', 'dfxp', 'ttml', 'sbv', 'sub'];

    /** @var string[] Extensions of files that may carry caption references. */
    private const DATA_EXTENSIONS = ['js', 'json', 'xml'];

    /** @var int Cap on bytes read from any single data file while looking for references. */
    private const MAX_DATA_BYTES = 4194304;

    /**
     * Judge the caption coverage of a set of package files.
     *
     * @param array<string, string> $contents Relative path => file contents. Media
     *        files may map to an empty string; only text files are read here.
     * @return array{verdict: string, total: int, captioned: int, uncaptioned: string[], undetermined: string[]}
     */
    public static function scan(array $contents): array {
        $media = [];
        $captions = [];
        foreach (array_keys($contents) as $path) {
            $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($extension, self::MEDIA_EXTENSIONS, true)) {
                $media[self::normalise($path)] = $path;
            } else if (in_array($extension, self::CAPTION_EXTENSIONS, true)) {
                $captions[] = self::normalise($path);
            }
        }

        if (empty($media)) {
            return [
                'verdict' => self::VERDICT_NOMEDIA,
                'total' => 0,
                'captioned' => 0,
                'uncaptioned' => [],
                'undetermined' => [],
                'audio' => 0,
                'video' => 0,
                'hastranscript' => false,
                'danglingcaptions' => [],
            ];
        }

        ['linked' => $linked, 'bare' => $bare, 'dangling' => $dangling] = self::linked_media($contents);
        $hastranscript = self::has_transcript($contents);
        $hascaptionmaterial = !empty($captions) || !empty($linked);

        $captioned = [];
        $missing = [];
        $unknown = [];
        $audio = 0;
        foreach ($media as $normalised => $path) {
            $isaudio = in_array(
                strtolower((string) pathinfo($normalised, PATHINFO_EXTENSION)),
                self::AUDIO_EXTENSIONS,
                true
            );
            $audio += $isaudio ? 1 : 0;

            if (isset($linked[$normalised]) || self::has_sidecar($normalised, $captions)) {
                $captioned[] = $path;
            } else if ($isaudio && $hastranscript) {
                // WCAG 1.2.1: for audio without a picture a transcript is an
                // accepted alternative, so absent captions are no failure here.
                // Whether that transcript covers this recording cannot be told
                // from the package, hence doubt rather than clearance.
                $unknown[] = $path;
            } else if (isset($bare[$normalised]) || !$hascaptionmaterial) {
                // Embedded in a player element that names no track, or sitting
                // in a package that captions nothing at all: both are proof, not
                // a blind spot in this code.
                $missing[] = $path;
            } else {
                // Captions exist somewhere but could not be paired with this
                // file — the one honest answer is that we do not know.
                $unknown[] = $path;
            }
        }

        $report = self::verdict($media, $captioned, $missing, $unknown);
        $report['audio'] = $audio;
        $report['video'] = count($media) - $audio;
        $report['hastranscript'] = $hastranscript;
        sort($dangling);
        $report['danglingcaptions'] = $dangling;

        return $report;
    }

    /**
     * Whether the package ships a spoken-word transcript of its own.
     *
     * Only what is honestly visible counts: a file that says so in its name, or
     * a player configured to show a transcript panel. Caption tracks are
     * deliberately *not* counted here — they are already weighed per medium, and
     * treating them as a package-wide transcript would clear media they never
     * covered.
     *
     * @param array<string, string> $contents Relative path => file contents.
     * @return bool
     */
    private static function has_transcript(array $contents): bool {
        foreach ($contents as $path => $content) {
            if (preg_match(self::TRANSCRIPT_NAME, basename($path))) {
                return true;
            }
            $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($extension, self::DATA_EXTENSIONS, true) || $content === '') {
                continue;
            }
            if (preg_match('/["\']transcript["\']\s*:\s*(true|\{)/i', $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Media paths that some file explicitly ties to a caption track.
     *
     * @param array<string, string> $contents Relative path => file contents.
     * @return array{linked: array<string, true>, bare: array<string, true>} Media
     *         with a caption track, and media embedded in HTML without one.
     */
    private static function linked_media(array $contents): array {
        $present = [];
        foreach (array_keys($contents) as $path) {
            $present[self::normalise($path)] = true;
        }

        $linked = [];
        $bareembeds = [];
        $dangling = [];
        foreach ($contents as $path => $content) {
            $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            $ishtml = in_array($extension, ['html', 'htm', 'xhtml'], true);
            $isdata = in_array($extension, self::DATA_EXTENSIONS, true);
            if ((!$ishtml && !$isdata) || $content === '' || strlen($content) > self::MAX_DATA_BYTES) {
                continue;
            }

            $base = trim((string) pathinfo($path, PATHINFO_DIRNAME), '.');
            if ($ishtml) {
                foreach (self::media_without_tracks($content) as $bare) {
                    $resolved = self::resolve($bare, $base, $present);
                    if ($resolved !== null) {
                        $bareembeds[$resolved] = true;
                    }
                }
            }
            $pairs = $ishtml ? self::media_with_tracks($content) : self::media_beside_captions($content);
            foreach ($pairs as [$mediapath, $captionpath]) {
                // A reference is only evidence when what it points at is
                // actually in the package. A dangling caption path is exactly
                // what a broken export leaves behind, and counting it would
                // hand out a clean bill of health for missing subtitles.
                if (self::resolve($captionpath, $base, $present) === null) {
                    // Worth naming rather than only discounting: captions that
                    // were authored but did not make it into the package are a
                    // broken export, and that is repaired differently from
                    // captions that were never made.
                    $dangling[self::normalise($captionpath)] = true;
                    continue;
                }
                $resolved = self::resolve($mediapath, $base, $present);
                if ($resolved !== null) {
                    $linked[$resolved] = true;
                }
            }
        }

        return [
            'linked' => $linked,
            'bare' => array_diff_key($bareembeds, $linked),
            'dangling' => array_keys($dangling),
        ];
    }

    /**
     * Media embedded in HTML that carries no caption track.
     *
     * A player element without a track is not a blind spot in this code — it is
     * the classic accessibility failure, stated outright by the markup. Naming
     * it as proven rather than as doubtful is the whole point of the report.
     *
     * @param string $html The HTML document.
     * @return string[] Media sources.
     */
    private static function media_without_tracks(string $html): array {
        $sources = [];
        if (!preg_match_all('/<(video|audio)\b[^>]*>(.*?)<\/\1>/is', $html, $elements, PREG_SET_ORDER)) {
            return $sources;
        }

        foreach ($elements as $element) {
            $trackpattern = '/<track\b[^>]*\bkind\s*=\s*["\']?(?:captions|subtitles)["\']?[^>]*>/i';
            if (preg_match($trackpattern, (string) $element[2])) {
                continue;
            }
            if (preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', (string) $element[0], $src)) {
                $sources[] = $src[1];
            }
            if (preg_match_all('/<source\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i', (string) $element[2], $srcs)) {
                foreach ($srcs[1] as $source) {
                    $sources[] = $source;
                }
            }
        }

        return $sources;
    }

    /**
     * Find a referenced path among the package files.
     *
     * References are written either from the package root or relative to the
     * file holding them, and tools disagree on which; both are tried.
     *
     * @param string $reference The path as written in the file.
     * @param string $base Directory of the referring file.
     * @param array<string, true> $present Normalised paths present in the package.
     * @return string|null The normalised package path, or null when absent.
     */
    private static function resolve(string $reference, string $base, array $present): ?string {
        $candidates = [self::normalise($reference)];
        if ($base !== '' && $base !== '/') {
            $candidates[] = self::normalise(rtrim($base, '/') . '/' . ltrim($reference, '/'));
        }

        foreach ($candidates as $candidate) {
            // Collapse any ../ segments the reference carried.
            $segments = [];
            foreach (explode('/', $candidate) as $segment) {
                if ($segment === '..') {
                    array_pop($segments);
                } else if ($segment !== '' && $segment !== '.') {
                    $segments[] = $segment;
                }
            }
            $collapsed = implode('/', $segments);
            if (isset($present[$collapsed])) {
                return $collapsed;
            }
        }

        return null;
    }

    /**
     * Media elements in HTML that contain a caption or subtitle track.
     *
     * @param string $html The HTML document.
     * @return array<int, array{0: string, 1: string}> Media path and its track.
     */
    private static function media_with_tracks(string $html): array {
        $pairs = [];
        if (!preg_match_all('/<(video|audio)\b[^>]*>(.*?)<\/\1>/is', $html, $elements, PREG_SET_ORDER)) {
            return $pairs;
        }

        foreach ($elements as $element) {
            $inner = (string) $element[2];
            $trackpattern = '/<track\b[^>]*\bkind\s*=\s*["\']?(?:captions|subtitles)["\']?[^>]*>/i';
            if (!preg_match($trackpattern, $inner, $track)) {
                continue;
            }
            if (!preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', (string) $track[0], $trackssrc)) {
                continue;
            }
            $captionpath = $trackssrc[1];

            $sources = [];
            if (preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', (string) $element[0], $src)) {
                $sources[] = $src[1];
            }
            if (preg_match_all('/<source\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i', $inner, $srcs)) {
                foreach ($srcs[1] as $source) {
                    $sources[] = $source;
                }
            }
            foreach ($sources as $source) {
                $pairs[] = [$source, $captionpath];
            }
        }

        return $pairs;
    }

    /**
     * Media paths sitting in the same data object as a caption path.
     *
     * Authoring tools serialise their assets as objects; when one holds both a
     * media file and a caption file, that is the link, whatever the keys are
     * called. Working on the object rather than on key names is what makes this
     * survive a tool this code has never seen.
     *
     * @param string $content The data file.
     * @return array<int, array{0: string, 1: string}> Media path and its caption path.
     */
    private static function media_beside_captions(string $content): array {
        $mediapattern = '[^"\']+\.(?:' . implode('|', self::MEDIA_EXTENSIONS) . ')';
        // Tools also ship caption cues wrapped in a script file, so `js` counts
        // here — but only inside an object that names captions, and only when
        // the file it points at really exists.
        $captionpattern = '[^"\']+\.(?:' . implode('|', self::CAPTION_EXTENSIONS) . '|js)';

        $pairs = [];
        if (!preg_match_all('/\{[^{}]*\}/s', $content, $objects)) {
            return $pairs;
        }
        foreach ($objects[0] as $object) {
            if (!preg_match('/caption|subtitle|untertitel/i', $object)) {
                continue;
            }
            if (!preg_match('/["\'](' . $captionpattern . ')["\']/i', $object, $caption)) {
                continue;
            }
            if (preg_match_all('/["\'](' . $mediapattern . ')["\']/i', $object, $matches)) {
                foreach ($matches[1] as $media) {
                    $pairs[] = [$media, $caption[1]];
                }
            }
        }

        return $pairs;
    }

    /**
     * Whether a caption file is named after this media file.
     *
     * @param string $media Normalised media path.
     * @param string[] $captions Normalised caption paths.
     * @return bool
     */
    private static function has_sidecar(string $media, array $captions): bool {
        $stem = preg_replace('/\.[^.]+$/', '', $media);
        foreach ($captions as $caption) {
            $captionstem = preg_replace('/\.[^.]+$/', '', $caption);
            // Accepts both lesson.vtt and lesson.de.vtt for lesson.mp4.
            if ($captionstem === $stem || str_starts_with((string) $captionstem, $stem . '.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Turn the counts into a verdict.
     *
     * @param array<string, string> $media All media, normalised path => path.
     * @param string[] $captioned Media proven to have captions.
     * @param string[] $missing Media proven to have none.
     * @param string[] $unknown Media without proof either way.
     * @return array{verdict: string, total: int, captioned: int, uncaptioned: string[], undetermined: string[]}
     */
    private static function verdict(array $media, array $captioned, array $missing, array $unknown): array {
        if (!empty($missing)) {
            $verdict = self::VERDICT_INCOMPLETE;
        } else if (!empty($unknown)) {
            $verdict = self::VERDICT_UNDETERMINED;
        } else {
            $verdict = self::VERDICT_COMPLETE;
        }

        return [
            'verdict' => $verdict,
            'total' => count($media),
            'captioned' => count($captioned),
            'uncaptioned' => array_values($missing),
            'undetermined' => array_values($unknown),
        ];
    }

    /**
     * Reduce a path to something comparable across the ways tools spell it.
     *
     * @param string $path A path or URL from the package.
     * @return string
     */
    private static function normalise(string $path): string {
        $path = preg_replace('/[?#].*$/', '', trim($path));
        $path = str_replace('\\', '/', (string) $path);
        $path = preg_replace('#^\./#', '', $path);

        return strtolower(ltrim((string) $path, '/'));
    }
}
