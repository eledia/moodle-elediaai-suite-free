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

use stored_file;

/**
 * CRUD for saved, site-wide tutor profiles (the block_elediaai_tutor_tutor table).
 *
 * A profile is a named bundle of registry-key => value settings (persona + every
 * design token + behaviour/launcher/footer) plus optional logo/avatar images,
 * kept in the system context (file areas tutorlogo/tutoravatar, itemid = profile
 * id). Built-in {@see presets} live in code, not here; this table holds only the
 * admin-created / imported profiles. Settings are sanitised through the
 * {@see registry} on save, so a profile can never carry an unknown or unsafe key.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tutor_profile {
    /** @var string The DB table name. */
    private const TABLE = 'block_elediaai_tutor_tutor';

    /**
     * All saved profiles, ordered for display.
     *
     * @return \stdClass[]
     */
    public static function get_all(): array {
        global $DB;
        return $DB->get_records(self::TABLE, null, 'sortorder ASC, name ASC');
    }

    /**
     * A profile by id, or null.
     *
     * @param int $id Profile id.
     * @return \stdClass|null
     */
    public static function get(int $id): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id]) ?: null;
    }

    /**
     * Decode a profile's settings JSON into a sanitised registry-key => value map.
     *
     * @param \stdClass $profile A profile record.
     * @return array<string,mixed>
     */
    public static function settings(\stdClass $profile): array {
        $decoded = json_decode((string) $profile->settings, true);
        return is_array($decoded) ? self::clean_settings($decoded) : [];
    }

    /**
     * Create a profile from a name + settings map (settings are sanitised).
     *
     * @param string $name Display name.
     * @param string $description Optional notes.
     * @param array $settings Registry-key => value map.
     * @param string $shortname Desired machine name ('' ⇒ derived from the name).
     * @return int The new profile id.
     */
    public static function create(
        string $name,
        string $description,
        array $settings,
        string $shortname = ''
    ): int {
        global $DB;
        $now = time();
        $record = (object) [
            'name' => self::trim_name($name),
            'shortname' => self::unique_shortname($shortname !== '' ? $shortname : $name),
            'description' => $description !== '' ? $description : null,
            'settings' => json_encode(self::clean_settings($settings)),
            'sortorder' => self::next_sortorder(),
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        return (int) $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Update a profile's name, description and settings.
     *
     * @param int $id Profile id.
     * @param string $name Display name.
     * @param string $description Optional notes.
     * @param array $settings Registry-key => value map.
     * @return void
     */
    public static function update(int $id, string $name, string $description, array $settings): void {
        global $DB;
        $DB->update_record(self::TABLE, (object) [
            'id' => $id,
            'name' => self::trim_name($name),
            'description' => $description !== '' ? $description : null,
            'settings' => json_encode(self::clean_settings($settings)),
            'timemodified' => time(),
        ]);
    }

    /**
     * Delete a profile and its stored images.
     *
     * @param int $id Profile id.
     * @return void
     */
    public static function delete(int $id): void {
        global $DB;
        $fs = get_file_storage();
        $syscontext = \core\context\system::instance();
        foreach ([branding::TUTOR_LOGO_FILEAREA, branding::TUTOR_AVATAR_FILEAREA] as $area) {
            $fs->delete_area_files($syscontext->id, 'block_elediaai_tutor', $area, $id);
        }
        $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * The stored logo/avatar file for a profile, or null.
     *
     * @param int $id Profile id.
     * @param string $filearea TUTOR_LOGO_FILEAREA or TUTOR_AVATAR_FILEAREA.
     * @return stored_file|null
     */
    public static function image(int $id, string $filearea): ?stored_file {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            \core\context\system::instance()->id,
            'block_elediaai_tutor',
            $filearea,
            $id,
            'itemid, filepath, filename',
            false
        );
        return $files ? reset($files) : null;
    }

    /**
     * Store an image for a profile from raw content, replacing any existing one.
     *
     * @param int $id Profile id.
     * @param string $filearea TUTOR_LOGO_FILEAREA or TUTOR_AVATAR_FILEAREA.
     * @param string $filename The file name.
     * @param string $content Raw file bytes.
     * @return void
     */
    public static function store_image(int $id, string $filearea, string $filename, string $content): void {
        $fs = get_file_storage();
        $syscontext = \core\context\system::instance();
        $fs->delete_area_files($syscontext->id, 'block_elediaai_tutor', $filearea, $id);
        $fs->create_file_from_string([
            'contextid' => $syscontext->id,
            'component' => 'block_elediaai_tutor',
            'filearea' => $filearea,
            'itemid' => $id,
            'filepath' => '/',
            'filename' => clean_param($filename, PARAM_FILE),
        ], $content);
    }

    /**
     * Store an image for a profile by copying an existing stored file.
     *
     * @param int $id Profile id.
     * @param string $filearea Destination file area.
     * @param stored_file $source Source file to copy.
     * @return void
     */
    public static function copy_image_in(int $id, string $filearea, stored_file $source): void {
        self::store_image($id, $filearea, $source->get_filename(), $source->get_content());
    }

    /**
     * Keep only known registry keys with sanitised values; drop file keys
     * (images are stored separately) and anything unknown or invalid.
     *
     * @param array $settings Raw settings map.
     * @return array<string,mixed>
     */
    public static function clean_settings(array $settings): array {
        $out = [];
        foreach ($settings as $key => $value) {
            $entry = registry::get((string) $key);
            if ($entry === null || !registry::is_available((string) $key) || $entry['type'] === 'file') {
                continue;
            }
            $clean = registry::sanitise((string) $key, $value);
            if ($clean !== null) {
                $out[$key] = $clean;
            }
        }
        return $out;
    }

    /**
     * Generate a unique, valid shortname from a base string.
     *
     * @param string $base Candidate name.
     * @return string
     */
    public static function unique_shortname(string $base): string {
        global $DB;
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($base)) ?: 'tutor');
        $slug = trim($slug, '_');
        if ($slug === '') {
            $slug = 'tutor';
        }
        $slug = \core_text::substr($slug, 0, 90);
        $candidate = $slug;
        $i = 2;
        while ($DB->record_exists(self::TABLE, ['shortname' => $candidate])) {
            $candidate = $slug . '_' . $i;
            $i++;
        }
        return $candidate;
    }

    /**
     * Trim a display name to the column length.
     *
     * @param string $name Candidate name.
     * @return string
     */
    private static function trim_name(string $name): string {
        $name = trim($name);
        if ($name === '') {
            $name = get_string('tutor_untitled', 'block_elediaai_tutor');
        }
        return \core_text::substr($name, 0, 255);
    }

    /**
     * The next sort order (append to the end of the list).
     *
     * @return int
     */
    private static function next_sortorder(): int {
        global $DB;
        return (int) $DB->get_field_sql('SELECT COALESCE(MAX(sortorder), 0) + 1 FROM {' . self::TABLE . '}');
    }
}
