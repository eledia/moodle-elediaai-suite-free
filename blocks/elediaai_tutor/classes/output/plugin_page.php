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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

declare(strict_types=1);

namespace block_elediaai_tutor\output;

use coding_exception;
use html_writer;

/**
 * Plugin Shell page wrapper for eLeDia.ai Tutor pages.
 *
 * @package     block_elediaai_tutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plugin_page {
    /** @var string Default shell width. */
    public const MODIFIER_DEFAULT = 'default';
    /** @var string Narrow, reading-optimised shell width. */
    public const MODIFIER_READING = 'reading';
    /** @var string Editing shell width. */
    public const MODIFIER_EDITING = 'editing';
    /** @var string Wide shell width. */
    public const MODIFIER_WIDE = 'wide';
    /** @var string Full-bleed shell width. */
    public const MODIFIER_FULL = 'full';

    /** @var bool Tracks the open shell wrapper. */
    private static bool $opened = false;

    /**
     * Open the shell wrapper and render the header.
     *
     * @param array $headerdata Template data.
     * @param string $modifier Width modifier.
     */
    public static function open(array $headerdata, string $modifier = self::MODIFIER_DEFAULT): void {
        global $OUTPUT;

        if (self::$opened) {
            throw new coding_exception('plugin_page::open() called twice without close().');
        }

        if (
            !in_array($modifier, [
            self::MODIFIER_DEFAULT,
            self::MODIFIER_READING,
            self::MODIFIER_EDITING,
            self::MODIFIER_WIDE,
            self::MODIFIER_FULL,
            ], true)
        ) {
            throw new coding_exception("Unknown plugin_page modifier '{$modifier}'.");
        }

        $cssmodifier = match ($modifier) {
            self::MODIFIER_DEFAULT => '',
            self::MODIFIER_READING, self::MODIFIER_EDITING => 'lh-plugin-shell--reading',
            self::MODIFIER_WIDE => 'lh-plugin-shell--wide',
            self::MODIFIER_FULL => 'lh-plugin-shell--full',
        };

        if (!isset($headerdata['hasactionicons'])) {
            $headerdata['hasactionicons'] = !empty($headerdata['actionicons']);
        }

        echo html_writer::start_div(trim('lh-plugin-shell ' . $cssmodifier));
        echo $OUTPUT->render_from_template('block_elediaai_tutor/plugin_shell_header', $headerdata);
        self::$opened = true;
    }

    /**
     * Close the shell wrapper.
     */
    public static function close(): void {
        if (!self::$opened) {
            return;
        }

        echo html_writer::end_div();
        self::$opened = false;
    }
}
