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

use context;
use stored_file;

/**
 * Snapshot-applies a tutor (settings + images) to the site or a block instance.
 *
 * Applying is a *copy*, not a link: after applying, the target carries its own
 * independent values, so later edits to the source profile do not change it.
 * A site apply writes the full tutor; an instance apply writes only the keys the
 * admin has exposed (the per-instance override gate), so a teacher can never be
 * handed control of a setting the admin kept site-wide.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tutor_apply {
    /**
     * Apply a tutor to the whole site: every registry value is set (or cleared
     * when absent, so the result is an exact snapshot), and the logo/avatar are
     * copied into the system-context brand file areas.
     *
     * @param array $settings Registry-key => value map.
     * @param stored_file|null $logo Source logo file, or null.
     * @param stored_file|null $avatar Source avatar file, or null.
     * @return void
     */
    public static function to_site(array $settings, ?stored_file $logo, ?stored_file $avatar): void {
        $settings = tutor_profile::clean_settings($settings);
        foreach (registry::all() as $key => $entry) {
            if ($entry['type'] === 'file') {
                continue;
            }
            $cfgkey = registry::sitekey($key);
            if (array_key_exists($key, $settings) && $settings[$key] !== '' && $settings[$key] !== null) {
                set_config($cfgkey, $settings[$key], 'block_elediaai_tutor');
            } else {
                unset_config($cfgkey, 'block_elediaai_tutor');
            }
        }

        $syscontext = \core\context\system::instance();
        self::replace_image($syscontext, branding::LOGO_FILEAREA, 0, $logo);
        self::replace_image($syscontext, branding::AVATAR_FILEAREA, 0, $avatar);
        // The site brand file config keys mirror the file presence.
        self::sync_storedfile_config('brandlogo', $logo);
        self::sync_storedfile_config('brandavatar', $avatar);
    }

    /**
     * Apply a tutor to a single block instance, limited to the keys the admin
     * has exposed for per-instance override. Exposed keys absent from the tutor
     * are cleared (so the instance falls back to the site for them).
     *
     * @param int $blockinstanceid The block_instances.id.
     * @param array $settings Registry-key => value map.
     * @param stored_file|null $logo Source logo file, or null.
     * @param stored_file|null $avatar Source avatar file, or null.
     * @return void
     */
    public static function to_instance(
        int $blockinstanceid,
        array $settings,
        ?stored_file $logo,
        ?stored_file $avatar
    ): void {
        self::apply_settings_to_instance($blockinstanceid, $settings);

        $blockcontext = \core\context\block::instance($blockinstanceid);
        if (registry::is_exposed('logo')) {
            self::replace_image($blockcontext, branding::INSTANCE_LOGO_FILEAREA, 0, $logo);
        }
        if (registry::is_exposed('avatar')) {
            self::replace_image($blockcontext, branding::INSTANCE_AVATAR_FILEAREA, 0, $avatar);
        }
    }

    /**
     * Return all eLeDia.ai Tutor block instances for display in admin screens.
     *
     * @return array<int,\stdClass> block_instances records keyed by id.
     */
    public static function instance_records(): array {
        global $DB;

        return $DB->get_records('block_instances', ['blockname' => 'elediaai_tutor'], 'id ASC');
    }

    /**
     * Apply an imported bundle (settings + raw image bytes) to a block instance,
     * limited to the keys the admin has exposed. Used by the teacher-facing page
     * and the admin instance import — no throwaway profile needed.
     *
     * @param int $blockinstanceid The block_instances.id.
     * @param array $bundle A parsed bundle (settings plus optional logo/avatar bytes).
     * @return void
     */
    public static function to_instance_from_bundle(int $blockinstanceid, array $bundle): void {
        self::apply_settings_to_instance($blockinstanceid, $bundle['settings'] ?? []);

        $blockcontext = \core\context\block::instance($blockinstanceid);
        $images = [
            'logo' => [branding::INSTANCE_LOGO_FILEAREA, $bundle['logo'] ?? null],
            'avatar' => [branding::INSTANCE_AVATAR_FILEAREA, $bundle['avatar'] ?? null],
        ];
        foreach ($images as $key => [$filearea, $image]) {
            if (!registry::is_exposed($key)) {
                continue;
            }
            $fs = get_file_storage();
            $fs->delete_area_files($blockcontext->id, 'block_elediaai_tutor', $filearea, 0);
            if (is_array($image) && !empty($image['content'])) {
                $fs->create_file_from_string([
                    'contextid' => $blockcontext->id,
                    'component' => 'block_elediaai_tutor',
                    'filearea' => $filearea,
                    'itemid' => 0,
                    'filepath' => '/',
                    'filename' => clean_param($image['filename'], PARAM_FILE),
                ], $image['content']);
            }
        }
    }

    /**
     * Write a settings map onto a block instance's config, limited to exposed,
     * non-file keys. Exposed keys absent from the map are cleared (so the
     * instance falls back to the site value for them).
     *
     * @param int $blockinstanceid The block_instances.id.
     * @param array $settings Registry-key => value map.
     * @return void
     */
    private static function apply_settings_to_instance(int $blockinstanceid, array $settings): void {
        global $DB;

        $settings = tutor_profile::clean_settings($settings);
        $record = $DB->get_record('block_instances', ['id' => $blockinstanceid], '*', MUST_EXIST);
        $config = !empty($record->configdata)
            ? unserialize_object(base64_decode($record->configdata)) : new \stdClass();
        if (!is_object($config)) {
            $config = new \stdClass();
        }

        foreach (registry::instanceable_keys() as $key) {
            if (!registry::is_exposed($key)) {
                continue;
            }
            if (registry::get($key)['type'] === 'file') {
                continue;
            }
            if (array_key_exists($key, $settings) && $settings[$key] !== '' && $settings[$key] !== null) {
                $config->$key = $settings[$key];
            } else {
                unset($config->$key);
            }
        }

        $DB->set_field(
            'block_instances',
            'configdata',
            base64_encode(serialize($config)),
            ['id' => $blockinstanceid]
        );
    }

    /**
     * The settings + images of a source tutor (a saved profile or a built-in
     * preset), ready to hand to {@see to_site()} / {@see to_instance()}.
     *
     * @param string $source 'profile:<id>' or 'preset:<id>'.
     * @return array{settings: array<string,mixed>, logo: stored_file|null, avatar: stored_file|null}|null
     */
    public static function source(string $source): ?array {
        [$type, $id] = array_pad(explode(':', $source, 2), 2, '');
        if ($type === 'preset') {
            if (!presets::exists($id)) {
                return null;
            }
            return ['settings' => presets::settings($id), 'logo' => null, 'avatar' => null];
        }
        if ($type === 'profile') {
            $profile = tutor_profile::get((int) $id);
            if ($profile === null) {
                return null;
            }
            return [
                'settings' => tutor_profile::settings($profile),
                'logo' => tutor_profile::image((int) $id, branding::TUTOR_LOGO_FILEAREA),
                'avatar' => tutor_profile::image((int) $id, branding::TUTOR_AVATAR_FILEAREA),
            ];
        }
        return null;
    }

    /**
     * The current tutor of a block instance (its stored overrides + images),
     * for export. Only registry keys actually set on the instance are included.
     *
     * @param int $blockinstanceid The block_instances.id.
     * @return array{settings: array<string,mixed>, logo: stored_file|null, avatar: stored_file|null}
     */
    public static function instance_source(int $blockinstanceid): array {
        global $DB;
        $record = $DB->get_record('block_instances', ['id' => $blockinstanceid], '*', MUST_EXIST);
        $config = !empty($record->configdata)
            ? unserialize_object(base64_decode($record->configdata)) : new \stdClass();
        $settings = [];
        if (is_object($config)) {
            foreach (registry::all() as $key => $entry) {
                if ($entry['type'] !== 'file' && isset($config->$key) && $config->$key !== '') {
                    $settings[$key] = $config->$key;
                }
            }
        }
        // Export the images actually shown on the instance: its own upload if
        // present, otherwise the site logo/avatar it inherits — so the bundle
        // is self-contained and reproduces the look elsewhere.
        $blockcontext = \core\context\block::instance($blockinstanceid);
        $logo = self::first_file($blockcontext->id, branding::INSTANCE_LOGO_FILEAREA, 0)
            ?? self::first_file(\core\context\system::instance()->id, branding::LOGO_FILEAREA, 0);
        $avatar = self::first_file($blockcontext->id, branding::INSTANCE_AVATAR_FILEAREA, 0)
            ?? self::first_file(\core\context\system::instance()->id, branding::AVATAR_FILEAREA, 0);
        return [
            'settings' => tutor_profile::clean_settings($settings),
            'logo' => $logo,
            'avatar' => $avatar,
        ];
    }

    /**
     * The first stored file in a context's file area, or null.
     *
     * @param int $contextid Context id.
     * @param string $filearea File area.
     * @param int $itemid Item id.
     * @return stored_file|null
     */
    private static function first_file(int $contextid, string $filearea, int $itemid): ?stored_file {
        $files = get_file_storage()->get_area_files(
            $contextid,
            'block_elediaai_tutor',
            $filearea,
            $itemid,
            'itemid, filepath, filename',
            false
        );
        return $files ? reset($files) : null;
    }

    /**
     * Replace the single file in a context's file area with a copy of the
     * source file, or clear the area when the source is null.
     *
     * @param context $context Destination context.
     * @param string $filearea Destination file area.
     * @param int $itemid Destination itemid.
     * @param stored_file|null $source Source file, or null to clear.
     * @return void
     */
    private static function replace_image(
        context $context,
        string $filearea,
        int $itemid,
        ?stored_file $source
    ): void {
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'block_elediaai_tutor', $filearea, $itemid);
        if ($source === null) {
            return;
        }
        $fs->create_file_from_storedfile([
            'contextid' => $context->id,
            'component' => 'block_elediaai_tutor',
            'filearea' => $filearea,
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $source->get_filename(),
        ], $source);
    }

    /**
     * Mirror an admin_setting_configstoredfile config value to the file presence
     * (it stores the filename so the URL helpers know a file exists).
     *
     * @param string $configname The config key (brandlogo / brandavatar).
     * @param stored_file|null $file The stored file, or null.
     * @return void
     */
    private static function sync_storedfile_config(string $configname, ?stored_file $file): void {
        if ($file === null) {
            unset_config($configname, 'block_elediaai_tutor');
        } else {
            set_config($configname, '/' . $file->get_filename(), 'block_elediaai_tutor');
        }
    }
}
