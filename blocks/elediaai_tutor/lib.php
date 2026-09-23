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
 * Plugin callbacks for the eLeDia.ai Tutor block.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the tutor question-analytics report to the course navigation.
 *
 * The link appears in the course's secondary navigation ("More" menu) for users
 * who may read the course insights.
 *
 * It used to be gated on the opt-in setting of the old question log, which is
 * off by default -- so on most sites the entry never appeared at all, and after
 * 27.08.2026 it would have led to an empty page anyway. The turn log of the
 * suite always runs, so capability alone decides now.
 *
 * @param navigation_node $navigation The course navigation node to extend.
 * @param stdClass $course The course record.
 * @param \core\context\course $context The course context.
 * @return void
 */
function block_elediaai_tutor_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    \core\context\course $context
): void {
    $allowed = has_capability('local/elediaai_core:viewcourseinsights', $context)
        || has_capability('block/elediaai_tutor:viewreports', $context);
    if (!$allowed) {
        return;
    }

    $navigation->add(
        get_string('surface_insights_title', 'local_elediaai_core'),
        new moodle_url('/local/elediaai_core/course_insights.php', ['courseid' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'elediaai_courseinsights',
        new pix_icon('i/report', '')
    );
}

/**
 * Serve branding files (tutor logo / conversation avatar). Two layers exist:
 * site-wide files set in the admin settings live in the SYSTEM context
 * (brandlogo / brandavatar); per-instance overrides set on a block live in that
 * BLOCK's context (instancelogo / instanceavatar). The images are non-sensitive
 * branding shown to every learner who can see the tutor, so any logged-in user
 * may fetch them.
 *
 * @param stdClass $course Course (or site) record.
 * @param stdClass $birecordorcm The block instance record (null for site files).
 * @param context $context The system or block context.
 * @param string $filearea The requested file area.
 * @param array $args The file path/name args.
 * @param bool $forcedownload Whether to force download.
 * @param array $options Serving options.
 * @return void Sends the file and exits, or returns false on failure.
 */
function block_elediaai_tutor_pluginfile(
    $course,
    $birecordorcm,
    $context,
    $filearea,
    $args,
    $forcedownload,
    array $options = []
) {
    $sitefileareas = [
        \block_elediaai_tutor\local\branding::LOGO_FILEAREA,
        \block_elediaai_tutor\local\branding::AVATAR_FILEAREA,
        \block_elediaai_tutor\local\branding::TUTOR_LOGO_FILEAREA,
        \block_elediaai_tutor\local\branding::TUTOR_AVATAR_FILEAREA,
    ];
    $instancefileareas = [
        \block_elediaai_tutor\local\branding::INSTANCE_LOGO_FILEAREA,
        \block_elediaai_tutor\local\branding::INSTANCE_AVATAR_FILEAREA,
    ];

    if ($context->contextlevel == CONTEXT_SYSTEM) {
        if (!in_array($filearea, $sitefileareas, true)) {
            send_file_not_found();
        }
    } else if ($context->contextlevel == CONTEXT_BLOCK) {
        if (!in_array($filearea, $instancefileareas, true)) {
            send_file_not_found();
        }
    } else {
        send_file_not_found();
    }

    require_login();

    // The pluginfile URL carries the itemid (always 0 for these single-file areas)
    // as the first path segment; shift it off so the remainder is the filepath.
    $itemid = (int) array_shift($args);
    $fs = get_file_storage();
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $file = $fs->get_file($context->id, 'block_elediaai_tutor', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    // Every area served here is a branding image embedded via <img src>. Force
    // download unconditionally so a crafted SVG fetched as a top-level document
    // cannot execute script in the Moodle origin; <img> embedding is unaffected
    // by the attachment disposition, so the visible UI does not change.
    send_stored_file($file, null, 0, true, $options);
}
