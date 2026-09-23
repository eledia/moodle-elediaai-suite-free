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
 * Plugin-owned accessible icon actions.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\output;

defined('MOODLE_INTERNAL') || die();

use html_writer;
use moodle_url;

/**
 * Small action-link renderer used by the teacher dashboard.
 */
final class icon_kit {
    /**
     * Render one icon-only action link.
     *
     * @param string $intent Action intent.
     * @param string $label Accessible action label.
     * @param moodle_url $url Destination.
     * @param string $variant Visual variant.
     * @return string
     */
    public static function button(string $intent, string $label, moodle_url $url, string $variant = 'secondary'): string {
        $icon = match ($intent) {
            'view' => 'eye',
            'edit' => 'pencil',
            'add' => 'plus',
            default => 'arrow-right',
        };
        return html_writer::link($url, lucide_icon::render($icon), [
            'class' => 'lh-icon-action is-' . clean_param($variant, PARAM_ALPHANUMEXT),
            'aria-label' => $label,
            'title' => $label,
        ]);
    }

    /**
     * Render a list of action specifications.
     *
     * @param array<int, array<string, mixed>> $actions Action specifications.
     * @return string
     */
    public static function row_actions(array $actions): string {
        $html = '';
        foreach ($actions as $action) {
            if (($action['url'] ?? null) instanceof moodle_url) {
                $html .= self::button(
                    (string) ($action['intent'] ?? 'open'),
                    (string) ($action['label'] ?? ''),
                    $action['url'],
                    (string) ($action['variant'] ?? 'secondary')
                );
            }
        }
        return $html;
    }
}
