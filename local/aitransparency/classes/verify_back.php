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
 * The way back from the provenance check.
 *
 * @package    local_aitransparency
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_aitransparency;

/**
 * Where the verification page leads back to.
 */
final class verify_back {
    /**
     * The way back from the verification page.
     *
     * The page used to be a dead end: reached through "Herkunft pruefen" under an
     * answer, left only through the browser or the menu. The way back goes, in
     * this order, to the page the person came from (the link opens in the same
     * tab, so that is usually the chat), else to where the content was made, else
     * to the dashboard. A course or activity is named only to somebody who may see
     * it -- the check must not tell anybody else which course a text came from.
     *
     * @param int|null $contextid Context of the provenance record, null without one.
     * @param string $referer The local referer, '' when there is none.
     * @return array{0: \moodle_url, 1: string} Target and label.
     */
    public static function resolve(?int $contextid, string $referer): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        if ($referer !== '' && strpos($referer, '/local/aitransparency/verify.php') === false) {
            return [new \moodle_url($referer), get_string('verify_back', 'local_aitransparency')];
        }
        $dashboard = [new \moodle_url('/my/'), get_string('verify_back_dashboard', 'local_aitransparency')];
        $context = $contextid ? \core\context::instance_by_id($contextid, IGNORE_MISSING) : false;
        if (!$context) {
            return $dashboard;
        }
        $coursecontext = $context->get_course_context(false);
        if (!$coursecontext || (int) $coursecontext->instanceid === SITEID) {
            return $dashboard;
        }
        $course = get_course((int) $coursecontext->instanceid);
        if (!can_access_course($course)) {
            return $dashboard;
        }
        if ($context->contextlevel == CONTEXT_MODULE) {
            $cm = get_fast_modinfo($course)->get_cm((int) $context->instanceid);
            if ($cm->uservisible) {
                return [
                    $cm->url ?? course_get_url($course),
                    get_string('verify_back_to', 'local_aitransparency', format_string($cm->name)),
                ];
            }
        }
        $name = format_string($course->fullname, true, ['context' => $coursecontext]);
        return [course_get_url($course), get_string('verify_back_to_course', 'local_aitransparency', $name)];
    }
}
