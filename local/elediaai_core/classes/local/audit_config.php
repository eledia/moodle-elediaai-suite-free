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

/**
 * Central audit display/access configuration.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\local;

defined('MOODLE_INTERNAL') || die();

use core\context;
use context_system;
use core_reportbuilder\local\helpers\database as reportbuilder_database;
use html_writer;
use moodle_url;

/**
 * Reads the audit-related plugin settings in one place.
 */
final class audit_config {
    /** @var string Audit access mode. */
    public const ACCESS_CORE_CAPABILITY = 'corecap';
    /** @var string Audit access mode. */
    public const ACCESS_ADMINS_ONLY = 'adminonly';
    /** @var string Audit access mode. */
    public const ACCESS_TEACHER_OWN_COURSES = 'teacherowncourses';

    /**
     * Static-only.
     */
    private function __construct() {
    }

    /**
     * Whether Moodle core ships the AI usage register the audit feature builds on.
     *
     * The audit report extends core's `ai_action_register` reportbuilder entity,
     * reads the `{ai_action_register}` table and gates on the
     * `moodle/ai:viewaiusagereport` capability — all introduced with the reworked
     * AI subsystem in Moodle 5.0. On Moodle 4.5 none of them exist, so the whole
     * feature is hidden instead of fataling; every other AI suite feature stays
     * fully usable. Feature-detection via the core entity class keeps this robust
     * across future core changes without hard-coding a Moodle version number.
     *
     * @return bool
     */
    public static function feature_available(): bool {
        return class_exists(\core_ai\reportbuilder\local\entities\ai_action_register::class);
    }

    /**
     * Whether the current user may open the LernHive AI audit view.
     *
     * @param context $context Report context.
     * @return bool
     */
    public static function can_view(context $context): bool {
        // Guard the core-only usage-report capability: on Moodle < 5.0 the
        // `moodle/ai:viewaiusagereport` capability does not exist, so calling
        // has_capability() with it would raise a coding exception. The audit
        // feature is unavailable there, so nobody may view it.
        if (!self::feature_available()) {
            return false;
        }
        $mode = (string) get_config('local_elediaai_core', 'audit_access');
        if ($mode === self::ACCESS_ADMINS_ONLY) {
            return is_siteadmin();
        }
        if ($mode === self::ACCESS_TEACHER_OWN_COURSES) {
            global $USER;
            return is_siteadmin()
                || has_capability('moodle/ai:viewaiusagereport', $context)
                || self::has_teacher_role((int) $USER->id);
        }
        return has_capability('moodle/ai:viewaiusagereport', $context);
    }

    /**
     * Render a Moodle-native "audit unavailable on this version" page and stop.
     *
     * Keeps audit entry pages consistent when configuration is incomplete.
     * They fail gracefully on Moodle < 5.0 instead of fataling on the missing
     * core AI usage register.
     *
     * @param \moodle_page $page
     * @param \core_renderer|\bootstrap_renderer $output
     * @param moodle_url $url
     * @return never
     */
    public static function render_unavailable_page(\moodle_page $page, \core_renderer|\bootstrap_renderer $output, moodle_url $url): never {
        $page->set_url($url);
        $page->set_context(context_system::instance());
        $page->set_pagelayout('admin');
        $page->set_title(get_string('audit_unavailable_title', 'local_elediaai_core'));
        $page->set_heading(get_string('audit_unavailable_title', 'local_elediaai_core'));

        echo $output->header();
        echo html_writer::tag('h2', get_string('audit_unavailable_heading', 'local_elediaai_core'));
        echo $output->notification(
            get_string('audit_unavailable_message', 'local_elediaai_core'),
            \core\output\notification::NOTIFY_WARNING
        );
        echo $output->footer();
        exit;
    }

    /**
     * Whether rows should be restricted to courses taught by the current user.
     *
     * @return bool
     */
    public static function restrict_to_teacher_courses(): bool {
        global $USER;

        $mode = (string) get_config('local_elediaai_core', 'audit_access');
        return $mode === self::ACCESS_TEACHER_OWN_COURSES
            && !is_siteadmin()
            && !has_capability('moodle/ai:viewaiusagereport', context_system::instance())
            && self::has_teacher_role((int) $USER->id);
    }

    /**
     * SQL condition limiting AI rows to contexts inside courses the user teaches.
     *
     * @param string $contextidfield Fully-qualified field that contains a Moodle context id.
     * @param int $userid Acting user id.
     * @return array{0:string,1:array}
     */
    public static function teacher_course_scope_sql(string $contextidfield, int $userid): array {
        global $DB;

        $useridparam = reportbuilder_database::generate_param_name();
        $teacherparam = reportbuilder_database::generate_param_name();
        $editingteacherparam = reportbuilder_database::generate_param_name();
        $rolecontextpath = $DB->sql_concat('lhaudit_teacherctx.path', "'/%'");

        $sql = "EXISTS (
                    SELECT 1
                      FROM {context} lhaudit_rowctx
                      JOIN {context} lhaudit_coursectx
                        ON lhaudit_coursectx.contextlevel = :{$teacherparam}
                       AND (
                            lhaudit_rowctx.id = lhaudit_coursectx.id
                            OR lhaudit_rowctx.path LIKE " . $DB->sql_concat('lhaudit_coursectx.path', "'/%'") . "
                       )
                      JOIN {role_assignments} lhaudit_ra
                        ON lhaudit_ra.userid = :{$useridparam}
                      JOIN {context} lhaudit_teacherctx
                        ON lhaudit_teacherctx.id = lhaudit_ra.contextid
                      JOIN {role} lhaudit_role
                        ON lhaudit_role.id = lhaudit_ra.roleid
                     WHERE lhaudit_rowctx.id = {$contextidfield}
                       AND lhaudit_role.archetype IN (:{$editingteacherparam}, 'teacher')
                       AND (
                            lhaudit_coursectx.id = lhaudit_teacherctx.id
                            OR lhaudit_coursectx.path LIKE {$rolecontextpath}
                       )
                )";

        return [$sql, [
            $useridparam => $userid,
            $teacherparam => CONTEXT_COURSE,
            $editingteacherparam => 'editingteacher',
        ]];
    }

    /**
     * Whether real user names should be replaced by a role/context label.
     *
     * @return bool
     */
    public static function anonymize_users(): bool {
        return (int) get_config('local_elediaai_core', 'audit_anonymize_users') === 1;
    }

    /**
     * Whether prompt previews are visible in the audit table.
     *
     * @return bool
     */
    public static function show_prompt(): bool {
        return self::enabled('audit_show_prompt', true);
    }

    /**
     * Whether response previews are visible in the audit table.
     *
     * @return bool
     */
    public static function show_response(): bool {
        return self::enabled('audit_show_response', true);
    }

    /**
     * Whether error-message previews are visible from failed rows.
     *
     * @return bool
     */
    public static function show_error(): bool {
        return self::enabled('audit_show_error', true);
    }

    /**
     * Whether token values are visible in overview and table.
     *
     * @return bool
     */
    public static function show_tokens(): bool {
        return self::enabled('audit_show_tokens', true);
    }

    /**
     * Config checkbox helper with an explicit default.
     *
     * @param string $name Config key without plugin prefix.
     * @param bool $default Default value for fresh installs.
     * @return bool
     */
    private static function enabled(string $name, bool $default): bool {
        $value = get_config('local_elediaai_core', $name);
        if ($value === false) {
            return $default;
        }
        return (int) $value === 1;
    }

    /**
     * Whether a user has any teacher-like role assignment.
     *
     * @param int $userid
     * @return bool
     */
    private static function has_teacher_role(int $userid): bool {
        global $DB;

        if ($userid <= 0) {
            return false;
        }

        return $DB->record_exists_sql(
            "SELECT 1
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid
              WHERE ra.userid = :userid
                AND r.archetype IN ('editingteacher', 'teacher')",
            ['userid' => $userid]
        );
    }
}
