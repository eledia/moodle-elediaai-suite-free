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

namespace block_elediaai_tutor\local;

use moodle_exception;
use stored_file;

/**
 * Import/export of a tutor as a portable ZIP bundle.
 *
 * A bundle contains `tutor.json` (a manifest with the name + the registry-key =>
 * value settings map) plus the optional `logo.*` and `avatar.*` images. Export
 * reads from a saved profile or a built-in preset; import validates the manifest
 * against the {@see registry} (unknown keys are dropped, values re-sanitised) so
 * an untrusted bundle can never inject an unknown or unsafe setting.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tutor_io {
    /** @var string Bundle format marker. */
    private const FORMAT = 'eledia-tutor';

    /** @var int Bundle format version. */
    private const VERSION = 1;

    /**
     * Build a downloadable ZIP for a tutor and return its temp path.
     *
     * @param string $name Display name.
     * @param string $shortname Machine name.
     * @param array $settings Registry-key => value map.
     * @param stored_file|null $logo Logo image, or null.
     * @param stored_file|null $avatar Avatar image, or null.
     * @return string Absolute path to the generated ZIP (in a temp dir).
     * @throws moodle_exception When the archive cannot be written.
     */
    public static function export(
        string $name,
        string $shortname,
        array $settings,
        ?stored_file $logo,
        ?stored_file $avatar
    ): string {
        $manifest = [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'name' => $name,
            'shortname' => $shortname,
            'settings' => tutor_profile::clean_settings($settings),
            'images' => [],
        ];

        $files = [];
        foreach (['logo' => $logo, 'avatar' => $avatar] as $role => $file) {
            if ($file instanceof stored_file) {
                $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION) ?: 'png');
                $entry = $role . '.' . preg_replace('/[^a-z0-9]/', '', $ext);
                $files[$entry] = [$file->get_content()];
                $manifest['images'][$role] = $entry;
            }
        }
        // When no logo was uploaded the tutor still shows the built-in eLeDia
        // mark; bundle it so the export is self-contained and always carries an
        // image (the avatar inherits the logo on import when not set).
        if (!isset($manifest['images']['logo'])) {
            $default = self::default_logo();
            if ($default !== null) {
                $files[$default['filename']] = [$default['content']];
                $manifest['images']['logo'] = $default['filename'];
            }
        }
        $files['tutor.json'] = [json_encode($manifest, JSON_PRETTY_PRINT)];

        $tmpdir = make_request_directory();
        $path = $tmpdir . '/' . self::safe_filename($shortname) . '.zip';
        $packer = get_file_packer('application/zip');
        if (!$packer->archive_to_pathname($files, $path)) {
            throw new moodle_exception('error_export_failed', 'block_elediaai_tutor');
        }
        return $path;
    }

    /**
     * Parse an uploaded bundle into a validated structure.
     *
     * @param string $zippath Absolute path to the uploaded ZIP.
     * @return array{name: string, shortname: string, settings: array<string,mixed>,
     *         logo: array{filename: string, content: string}|null,
     *         avatar: array{filename: string, content: string}|null}
     * @throws moodle_exception When the bundle is malformed.
     */
    public static function import(string $zippath): array {
        // Reject anything that is not a ZIP up front, both to give a clean error
        // and to avoid the packer's debugging() noise on a bad upload.
        $handle = @fopen($zippath, 'rb');
        $signature = $handle ? (string) fread($handle, 2) : '';
        if ($handle) {
            fclose($handle);
        }
        if ($signature !== 'PK') {
            throw new moodle_exception('error_import_invalid', 'block_elediaai_tutor');
        }

        $tmpdir = make_request_directory();
        $packer = get_file_packer('application/zip');
        $result = $packer->extract_to_pathname($zippath, $tmpdir);
        if ($result === false || !is_file($tmpdir . '/tutor.json')) {
            throw new moodle_exception('error_import_invalid', 'block_elediaai_tutor');
        }

        $manifest = json_decode((string) file_get_contents($tmpdir . '/tutor.json'), true);
        if (!is_array($manifest) || ($manifest['format'] ?? '') !== self::FORMAT) {
            throw new moodle_exception('error_import_invalid', 'block_elediaai_tutor');
        }

        $settings = tutor_profile::clean_settings(
            is_array($manifest['settings'] ?? null) ? $manifest['settings'] : []
        );

        $images = ['logo' => null, 'avatar' => null];
        foreach (['logo', 'avatar'] as $role) {
            $entry = $manifest['images'][$role] ?? '';
            if ($entry === '' || !is_string($entry)) {
                continue;
            }
            // Guard against path traversal: only a bare filename in the root.
            $entry = basename($entry);
            $file = $tmpdir . '/' . $entry;
            if (is_file($file) && self::is_safe_image($file)) {
                $images[$role] = ['filename' => $entry, 'content' => (string) file_get_contents($file)];
            }
        }

        return [
            'name' => trim((string) ($manifest['name'] ?? '')),
            'shortname' => trim((string) ($manifest['shortname'] ?? '')),
            'settings' => $settings,
            'logo' => $images['logo'],
            'avatar' => $images['avatar'],
        ];
    }

    /**
     * The plugin's built-in default logo as bundle content, or null if missing.
     *
     * @return array{filename: string, content: string}|null
     */
    private static function default_logo(): ?array {
        global $CFG;
        foreach (['logo.png', 'logo.svg', 'logo.jpg'] as $name) {
            $path = $CFG->dirroot . '/blocks/elediaai_tutor/pix/' . $name;
            if (is_readable($path)) {
                return ['filename' => 'logo.' . pathinfo($name, PATHINFO_EXTENSION),
                    'content' => (string) file_get_contents($path)];
            }
        }
        return null;
    }

    /**
     * Whether a file looks like a supported, non-malicious image.
     *
     * @param string $path Absolute file path.
     * @return bool
     */
    private static function is_safe_image(string $path): bool {
        if (filesize($path) > 5 * 1024 * 1024) {
            return false;
        }
        $info = @getimagesize($path);
        if ($info !== false) {
            return true;
        }
        // SVG is text, not raster. Accept only an <svg> document that carries no
        // common script-execution or XXE vector: these files are user-supplied
        // branding shown to every learner, so a crafted SVG must not be able to
        // run JavaScript. The serving layer additionally forces download as
        // defence in depth (see block_elediaai_tutor_pluginfile()).
        $svg = (string) file_get_contents($path);
        if (stripos($svg, '<svg') === false) {
            return false;
        }
        $blocked = ['<script', '<foreignobject', 'javascript:', '<!entity'];
        foreach ($blocked as $needle) {
            if (stripos($svg, $needle) !== false) {
                return false;
            }
        }
        // Inline event handlers such as onload= / onclick=.
        if (preg_match('/\son[a-z]+\s*=/i', $svg)) {
            return false;
        }
        return true;
    }

    /**
     * A filesystem-safe base filename.
     *
     * @param string $name Candidate name.
     * @return string
     */
    private static function safe_filename(string $name): string {
        $clean = preg_replace('/[^a-z0-9_-]+/i', '_', trim($name)) ?: 'tutor';
        return 'tutor_' . trim($clean, '_');
    }
}
