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

namespace block_elediaai_tutor\external;

use context;
use block_elediaai_tutor\local\widget;
use moodle_exception;

/**
 * Shared helpers for the block's external functions.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Resolve a context id passed from the client into a context object.
     *
     * Only block, course and system contexts are accepted — the three places
     * the widget renders (block instance, the standalone view.php page in a
     * course, and global chat); anything else is rejected so a caller cannot
     * point the capability check at an unrelated context. For course contexts
     * the external functions' validate_context() additionally enforces course
     * access for the calling user.
     *
     * @param int $contextid The context id.
     * @return context
     * @throws moodle_exception When the context is missing or of the wrong type.
     */
    public static function resolve_context(int $contextid): context {
        global $DB;

        $context = context::instance_by_id($contextid, IGNORE_MISSING);
        if ($context === false) {
            throw new moodle_exception('error_invalid_context', 'block_elediaai_tutor');
        }
        $allowed = [CONTEXT_BLOCK, CONTEXT_COURSE, CONTEXT_SYSTEM];
        if (!in_array($context->contextlevel, $allowed, true)) {
            throw new moodle_exception('error_invalid_context', 'block_elediaai_tutor');
        }
        if ($context->contextlevel === CONTEXT_BLOCK) {
            $instance = $DB->get_record('block_instances', ['id' => $context->instanceid]);
            if (!$instance || $instance->blockname !== 'elediaai_tutor') {
                throw new moodle_exception('error_invalid_context', 'block_elediaai_tutor');
            }
            $parent = context::instance_by_id((int) $instance->parentcontextid, IGNORE_MISSING);
            $contextparent = $context->get_parent_context();
            if ($parent === false || $contextparent === false || $contextparent->id !== $parent->id) {
                throw new moodle_exception('error_invalid_context', 'block_elediaai_tutor');
            }
        }
        return $context;
    }

    /**
     * Resolve and bind a client context to the authoritative chat scope.
     *
     * System contexts are valid only for global chat. Course contexts must
     * name that exact course. A block context must be a real Tutor instance;
     * for course chat its fixed/current-course configuration must resolve to
     * the same course. A validated Tutor block may still host global chat.
     *
     * @param int $contextid Client-provided context id.
     * @param int $courseid Authoritative course id, or 0 for global chat.
     * @return context
     * @throws moodle_exception When context and scope do not match.
     */
    public static function resolve_scoped_context(int $contextid, int $courseid): context {
        $context = self::resolve_context($contextid);

        if ($context->contextlevel === CONTEXT_SYSTEM) {
            if ($courseid !== 0) {
                throw new moodle_exception('error_invalid_context', 'block_elediaai_tutor');
            }
            return $context;
        }

        if ($context->contextlevel === CONTEXT_COURSE) {
            if ($courseid <= 0 || (int) $context->instanceid !== $courseid) {
                throw new moodle_exception('error_invalid_context', 'block_elediaai_tutor');
            }
            return $context;
        }

        if ($courseid > 0 && self::block_courseid($context) !== $courseid) {
            throw new moodle_exception('error_invalid_context', 'block_elediaai_tutor');
        }
        return $context;
    }

    /**
     * Require current access to an authoritative chat scope.
     *
     * @param int $courseid Authoritative course id, or 0 for global chat.
     * @return void
     */
    public static function require_scope_login(int $courseid): void {
        if ($courseid > 0) {
            $course = get_course($courseid);
            require_login($course, false);
            self::require_course_tutor_enabled($courseid);
            return;
        }
        require_login();
    }

    /**
     * Load the per-instance block configuration for a block context.
     *
     * Used to enforce instance settings server-side (e.g. the answer-style
     * lock) — client-supplied values are never trusted. Returns an empty
     * object for non-block contexts or unconfigured instances.
     *
     * @param context $context The context the request was made in.
     * @return \stdClass The instance configuration (possibly empty).
     */
    public static function block_config(context $context): \stdClass {
        global $DB;

        if ($context->contextlevel !== CONTEXT_BLOCK) {
            return new \stdClass();
        }
        $instance = $DB->get_record('block_instances', ['id' => $context->instanceid]);
        if (!$instance || $instance->configdata === null || $instance->configdata === '') {
            return new \stdClass();
        }
        $config = unserialize_object(base64_decode($instance->configdata));
        return $config instanceof \stdClass ? $config : new \stdClass();
    }

    /**
     * Resolve the course selected by a concrete Tutor block instance.
     *
     * @param context $context Validated Tutor block context.
     * @return int Course id, or 0 when the instance is configured for global chat.
     */
    private static function block_courseid(context $context): int {
        $config = self::block_config($context);
        $passcoursecontext = !isset($config->passcoursecontext) || (int) $config->passcoursecontext === 1;
        if (!$passcoursecontext) {
            return 0;
        }
        // Walks up to the enclosing course, so an instance placed inside an
        // activity still passes its course context instead of falling back to
        // global chat.
        return \block_elediaai_tutor\local\placement::courseid($context);
    }

    /**
     * Enforce the course-level tutor opt-in signal.
     *
     * A teacher opts a course into the tutor by adding the block to the course.
     * Standalone UI entry points already honour that rule; external functions
     * use this helper so direct AJAX calls follow the same contract.
     *
     * @param int $courseid Course id, or 0 for non-course/global chat.
     * @return void
     * @throws moodle_exception When the course has no tutor block.
     */
    public static function require_course_tutor_enabled(int $courseid): void {
        if ($courseid > 0 && !widget::course_has_tutor($courseid)) {
            throw new moodle_exception('notenabledincourse', 'block_elediaai_tutor');
        }
    }

    /**
     * Enforce a business capability in the authoritative course context.
     *
     * A block may be scoped to a course while living outside that course's
     * context tree — on the Dashboard or inside another course.
     * resolve_scoped_context() accepts such a block for its course, but the
     * endpoints then check the capability in the block's own context, which
     * never lies under the scoped course. Moodle only evaluates roles and
     * CAP_PROHIBIT along the checked context's path, so a prohibition set in
     * the scoped course would be silently skipped. Re-checking the capability
     * in the course context makes
     * course-level prohibitions apply regardless of where the block instance
     * lives. Callers keep their block-/system-context capability check, so a
     * local prohibition on the concrete block instance still takes effect too.
     *
     * @param string $capability The capability to require in the course context.
     * @param int $courseid Course id, or 0 for non-course/global chat.
     * @return void
     * @throws moodle_exception When the course context denies the capability.
     */
    public static function require_course_capability(string $capability, int $courseid): void {
        if ($courseid <= 0 || $courseid === (int) SITEID) {
            return;
        }
        // IGNORE_MISSING: a conversation may still reference a deleted course;
        // with no course context to check, the block-context check remains the
        // sole gate rather than blocking the request outright.
        $coursecontext = \core\context\course::instance($courseid, IGNORE_MISSING);
        if ($coursecontext) {
            require_capability($capability, $coursecontext);
        }
    }
}
