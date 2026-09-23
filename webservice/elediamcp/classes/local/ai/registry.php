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

namespace webservice_elediamcp\local\ai;

use webservice_elediamcp\local\ai\tools\moodle_calendar_upcoming;
use webservice_elediamcp\local\ai\tools\moodle_course_contents;
use webservice_elediamcp\local\ai\tools\moodle_course_health;
use webservice_elediamcp\local\ai\tools\moodle_create_activity;
use webservice_elediamcp\local\ai\tools\moodle_create_course;
use webservice_elediamcp\local\ai\tools\moodle_create_user;
use webservice_elediamcp\local\ai\tools\moodle_due_work;
use webservice_elediamcp\local\ai\tools\moodle_enrol_user;
use webservice_elediamcp\local\ai\tools\moodle_find_user;
use webservice_elediamcp\local\ai\tools\moodle_forum_discussions;
use webservice_elediamcp\local\ai\tools\moodle_generate_h5p;
use webservice_elediamcp\local\ai\tools\moodle_generate_questions;
use webservice_elediamcp\local\ai\tools\moodle_grading_queue;
use webservice_elediamcp\local\ai\tools\moodle_get_announcements;
use webservice_elediamcp\local\ai\tools\moodle_get_resource;
use webservice_elediamcp\local\ai\tools\moodle_grade_submission;
use webservice_elediamcp\local\ai\tools\moodle_manage_sections;
use webservice_elediamcp\local\ai\tools\moodle_me;
use webservice_elediamcp\local\ai\tools\moodle_message_course_students;
use webservice_elediamcp\local\ai\tools\moodle_my_assignments;
use webservice_elediamcp\local\ai\tools\moodle_my_courses;
use webservice_elediamcp\local\ai\tools\moodle_my_grades;
use webservice_elediamcp\local\ai\tools\moodle_my_progress;
use webservice_elediamcp\local\ai\tools\moodle_my_submission_files;
use webservice_elediamcp\local\ai\tools\moodle_quiz_info;
use webservice_elediamcp\local\ai\tools\moodle_read_submission;
use webservice_elediamcp\local\ai\tools\moodle_search_content;
use webservice_elediamcp\local\ai\tools\moodle_search_courses;
use webservice_elediamcp\local\ai\tools\moodle_selfstudy_create_quiz;
use webservice_elediamcp\local\ai\tools\moodle_selfstudy_get_quiz;
use webservice_elediamcp\local\ai\tools\moodle_selfstudy_list_quizzes;
use webservice_elediamcp\local\ai\tools\moodle_selfstudy_submit_attempt;
use webservice_elediamcp\local\ai\tools\moodle_send_message;
use webservice_elediamcp\local\ai\tools\moodle_unanswered_forum_posts;
use webservice_elediamcp\local\ai\tools\moodle_update_activity;
use webservice_elediamcp\local\ai\tools\moodle_update_course;
use webservice_elediamcp\local\ai\tools\moodle_verify_user_context;

/**
 * Registry of AI-native MCP tools.
 *
 * Lists the curated tool implementations exposed by the MCP server. Keeping
 * the registry static keeps {@see \webservice_elediamcp\local\tool_provider} simple
 * and free of dynamic discovery overhead.
 *
 * Add new tools by:
 * 1. Creating an implementation under webservice_elediamcp\local\ai\tools\*.
 * 2. Adding the class name to the array returned by {@see all()}.
 *
 * Third-party plugins contribute tools without touching this file ("shared
 * verbs", LernHive ADR-P15 stage 4): implement `<component>_elediamcp_tools()`
 * in the plugin's lib.php, returning an array of class-strings that implement
 * {@see ai_tool}. Contributed tools flow through the same dispatch, capability
 * and audit pipeline; on name collisions the built-in tool wins.
 *
 * @package     webservice_elediamcp
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class registry {
    /** @var class-string<ai_tool>[]|null Request cache of validated contributed tools. */
    private static ?array $contributedcache = null;

    /** @var class-string<ai_tool>[] PHPUnit-only injected contributed tools. */
    private static array $testtools = [];

    /**
     * Return the list of registered AI tool class names.
     *
     * @return class-string<ai_tool>[]
     */
    public static function all(): array {
        $tools = [
            // Identity & context.
            moodle_me::class,
            moodle_verify_user_context::class,
            // People discovery (messageable users).
            moodle_find_user::class,
            // Course discovery & contents.
            moodle_my_courses::class,
            moodle_search_courses::class,
            moodle_course_contents::class,
            moodle_get_resource::class,
            moodle_search_content::class,
            // Communication & activity feeds.
            moodle_get_announcements::class,
            moodle_forum_discussions::class,
            moodle_calendar_upcoming::class,
            // Learner progress.
            moodle_due_work::class,
            moodle_my_assignments::class,
            moodle_my_grades::class,
            moodle_my_progress::class,
            moodle_quiz_info::class,
            moodle_my_submission_files::class,
            // Teacher workflows.
            moodle_grading_queue::class,
            moodle_unanswered_forum_posts::class,
            moodle_course_health::class,
            moodle_read_submission::class,
            // Write tools (require confirm).
            moodle_send_message::class,
            moodle_create_user::class,
            moodle_create_course::class,
            moodle_update_course::class,
            moodle_enrol_user::class,
            moodle_create_activity::class,
            moodle_update_activity::class,
            moodle_manage_sections::class,
            moodle_grade_submission::class,
            moodle_message_course_students::class,
        ];

        // Optional eledia.ai generation tools: registered only when the wrapped
        // plugin is installed, so marketplace installs never reference them.
        if (class_exists('\local_elediaai_h5pauthor\local\authoring_service')) {
            $tools[] = moodle_generate_h5p::class;
        }
        if (class_exists('\local_elediaai_questiongen\local\generator')) {
            $tools[] = moodle_generate_questions::class;
        }
        if (class_exists('\local_elediaai_selfstudy\local\quiz_service')) {
            $tools[] = moodle_selfstudy_create_quiz::class;
            $tools[] = moodle_selfstudy_list_quizzes::class;
            $tools[] = moodle_selfstudy_get_quiz::class;
            $tools[] = moodle_selfstudy_submit_attempt::class;
        }

        // Tools contributed by other plugins; built-ins win on name collision.
        $names = array_map(static fn(string $class): string => $class::name(), $tools);
        foreach (self::contributed() as $class) {
            if (in_array($class::name(), $names, true)) {
                continue;
            }
            $tools[] = $class;
            $names[] = $class::name();
        }

        return $tools;
    }

    /**
     * Validated tool classes contributed by other plugins via the
     * `<component>_elediamcp_tools()` lib.php callback.
     *
     * Invalid entries (unknown class, ai_tool not implemented, malformed tool
     * name) are skipped with a developer debugging message so one broken
     * provider never takes down the whole catalogue.
     *
     * @return class-string<ai_tool>[]
     */
    public static function contributed(): array {
        if (self::$contributedcache !== null) {
            return self::$contributedcache;
        }
        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST && !empty(self::$testtools)) {
            self::$contributedcache = self::validate_contributed(self::$testtools, 'phpunit');
            return self::$contributedcache;
        }

        $classes = [];
        foreach (get_plugins_with_function('elediamcp_tools', 'lib.php') as $plugins) {
            foreach ($plugins as $pluginname => $function) {
                try {
                    $returned = $function();
                } catch (\Throwable $e) {
                    debugging("webservice_elediamcp: tool provider '{$pluginname}' failed - "
                        . $e->getMessage(), DEBUG_DEVELOPER);
                    continue;
                }
                if (!is_array($returned)) {
                    debugging(
                        "webservice_elediamcp: tool provider '{$pluginname}' must return an array",
                        DEBUG_DEVELOPER
                    );
                    continue;
                }
                $classes = array_merge($classes, self::validate_contributed($returned, (string) $pluginname));
            }
        }

        self::$contributedcache = $classes;
        return $classes;
    }

    /**
     * Whether the named tool comes from a third-party provider.
     *
     * Contributed tools are installed by the site itself, so
     * {@see \webservice_elediamcp\local\tool_provider} treats them as available
     * regardless of the premium edition gating for built-in tools.
     *
     * @param string $name Tool name.
     * @return bool
     */
    public static function is_contributed(string $name): bool {
        foreach (self::contributed() as $class) {
            if ($class::name() === $name) {
                return true;
            }
        }
        return false;
    }

    /**
     * Inject contributed tools for PHPUnit (bypasses plugin discovery).
     *
     * @param class-string<ai_tool>[] $classes Tool classes, [] to reset.
     * @return void
     */
    public static function set_test_tools(array $classes): void {
        if (!defined('PHPUNIT_TEST') || !PHPUNIT_TEST) {
            throw new \coding_exception('set_test_tools() is only available under PHPUnit.');
        }
        self::$testtools = $classes;
        self::$contributedcache = null;
    }

    /**
     * Filter a provider's returned entries down to valid ai_tool classes.
     *
     * @param array<int, mixed> $entries Raw callback return value.
     * @param string $provider Provider label for debugging messages.
     * @return class-string<ai_tool>[]
     */
    private static function validate_contributed(array $entries, string $provider): array {
        $valid = [];
        foreach ($entries as $entry) {
            if (!is_string($entry) || !class_exists($entry)) {
                debugging(
                    "webservice_elediamcp: provider '{$provider}' returned an unknown class",
                    DEBUG_DEVELOPER
                );
                continue;
            }
            if (!is_subclass_of($entry, ai_tool::class)) {
                debugging(
                    "webservice_elediamcp: '{$entry}' from '{$provider}' does not implement ai_tool",
                    DEBUG_DEVELOPER
                );
                continue;
            }
            if (!preg_match('/^[a-z][a-z0-9_]*$/', $entry::name())) {
                debugging(
                    "webservice_elediamcp: '{$entry}' from '{$provider}' has a malformed tool name",
                    DEBUG_DEVELOPER
                );
                continue;
            }
            $valid[] = $entry;
        }
        return $valid;
    }

    /**
     * Look up an AI tool by name.
     *
     * @param string $name Tool name.
     * @return class-string<ai_tool>|null
     */
    public static function find(string $name): ?string {
        foreach (self::all() as $class) {
            if ($class::name() === $name) {
                return $class;
            }
        }
        return null;
    }

    /**
     * Return the set of tool names registered with the AI layer.
     *
     * @return string[]
     */
    public static function names(): array {
        return array_map(static fn(string $class): string => $class::name(), self::all());
    }
}
