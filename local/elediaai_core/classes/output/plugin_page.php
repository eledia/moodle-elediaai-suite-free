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
 * Plugin-owned page frame.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\output;

defined('MOODLE_INTERNAL') || die();

use coding_exception;
use html_writer;

/**
 * Renders Core pages without an external UI plugin.
 */
final class plugin_page {
    /** @var string Default page width. */
    public const MODIFIER_DEFAULT = 'default';
    /** @var string Reading-optimised page width. */
    public const MODIFIER_READING = 'reading';
    /** @var string Wide dashboard page width. */
    public const MODIFIER_WIDE = 'wide';

    /** @var bool Whether the wrapper is currently open. */
    private static bool $opened = false;

    /**
     * Open the page frame and render its accessible header.
     *
     * `$extraclasses` is a hook for a plugin's own *content* styling, not an
     * invitation to restyle the shell. `filter_eledia_translate` needs it: 123
     * of its rules are descendants of `.lh-plugin-shell`, and 117 of those sit
     * inside a block its stylesheet marks as generated and not to be edited.
     * Passing the historic class alongside the shell's own keeps those rules
     * alive without rewriting someone else's generated output. Anything that
     * styles the frame itself belongs in this plugin's stylesheet instead.
     *
     * @param array<string, mixed> $headerdata Header values.
     * @param string $modifier Width modifier.
     * @param string $extraclasses Extra classes for the plugin's own content rules.
     */
    public static function open(
        array $headerdata,
        string $modifier = self::MODIFIER_DEFAULT,
        string $extraclasses = ''
    ): void {
        if (self::$opened) {
            throw new coding_exception('plugin_page::open() called twice without close().');
        }
        if (!in_array($modifier, [self::MODIFIER_DEFAULT, self::MODIFIER_READING, self::MODIFIER_WIDE], true)) {
            throw new coding_exception("Unknown plugin_page modifier '{$modifier}'.");
        }

        echo html_writer::start_div(
            trim('elediaai-core-shell elediaai-core-shell--' . $modifier . ' ' . $extraclasses)
        );
        echo self::header_html($headerdata);
        self::$opened = true;
    }

    /**
     * The page header on its own, as markup.
     *
     * `open()` echoes it; this returns it. Both exist because one plugin needs
     * the header as a string: `local_elediaai_sources` puts its settings under
     * Moodle's admin tree and a small AMD module relocates the header into that
     * page. It cannot be echoed at the point where the page is built.
     *
     * @param array<string, mixed> $headerdata Header values.
     * @return string
     */
    public static function header_html(array $headerdata): string {
        $out = html_writer::start_tag('header', ['class' => 'elediaai-core-shell__header']);
        $out .= html_writer::start_div('elediaai-core-shell__heading');
        $name = s((string) ($headerdata['name'] ?? ''));
        if (!empty($headerdata['homeurl'])) {
            $name = html_writer::link((string) $headerdata['homeurl'], $name, [
                'class' => 'elediaai-core-shell__home',
                'aria-label' => (string) ($headerdata['homelabel'] ?? $headerdata['name'] ?? ''),
            ]);
        }
        $tagline = s((string) ($headerdata['tagline'] ?? ''));
        $title = $name . ($tagline === '' ? '' : html_writer::span('|', 'elediaai-core-shell__separator')
            . html_writer::span($tagline, 'elediaai-core-shell__tagline'));
        $out .= html_writer::tag('h1', $title, ['class' => 'elediaai-core-shell__title']);
        if (!empty($headerdata['subtitle'])) {
            $out .= html_writer::tag('p', s((string) $headerdata['subtitle']), [
                'class' => 'elediaai-core-shell__subtitle',
            ]);
        }
        $out .= html_writer::end_div();

        ob_start();
        self::render_actions($headerdata);
        $out .= (string) ob_get_clean();

        $out .= html_writer::end_tag('header');
        if (!empty($headerdata['sectionnav'])) {
            $out .= html_writer::div((string) $headerdata['sectionnav'], 'elediaai-core-shell__navigation');
        }

        return $out;
    }

    /**
     * Close the page frame.
     */
    public static function close(): void {
        if (self::$opened) {
            echo html_writer::end_div();
            self::$opened = false;
        }
    }

    /**
     * Render optional header actions.
     *
     * @param array<string, mixed> $headerdata Header values.
     */
    private static function render_actions(array $headerdata): void {
        $hasactions = !empty($headerdata['helpurl']) || !empty($headerdata['settingsurl'])
            || !empty($headerdata['headeractionicons']);
        if (!$hasactions) {
            return;
        }
        echo html_writer::start_tag('nav', [
            'class' => 'elediaai-core-shell__actions',
            'aria-label' => get_string('actions'),
        ]);
        foreach (($headerdata['headeractionicons'] ?? []) as $action) {
            $icon = preg_replace('/^fa-/', '', (string) ($action['faicon'] ?? 'external-link'));
            echo html_writer::link(
                (string) ($action['url'] ?? '#'),
                lucide_icon::render($icon),
                [
                    'class' => trim('elediaai-core-shell__icon-action ' . ($action['modifierclass'] ?? '')),
                    'aria-label' => (string) ($action['label'] ?? ''),
                    'title' => (string) ($action['label'] ?? ''),
                ]
            );
        }
        $slots = [
            ['helpurl', 'helplabel', get_string('help')],
            ['settingsurl', 'settingslabel', get_string('settings')],
        ];
        foreach ($slots as $slot) {
            if (!empty($headerdata[$slot[0]])) {
                $attributes = [
                    'class' => 'btn btn-outline-secondary elediaai-core-shell__action--'
                        . ($slot[0] === 'helpurl' ? 'help' : 'settings'),
                ];
                if ($slot[0] === 'settingsurl' && !empty($headerdata['settingsiscurrent'])) {
                    $attributes['aria-current'] = 'page';
                }
                // Dieselbe Falle wie in action_slots(): `??` faengt nur
                // null. Eine leere Aufschrift macht den Knopf unsichtbar und
                // den Link namenlos.
                $beschriftung = (string) ($headerdata[$slot[1]] ?? '');
                echo html_writer::link(
                    (string) $headerdata[$slot[0]],
                    s($beschriftung !== '' ? $beschriftung : $slot[2]),
                    $attributes
                );
            }
        }
        echo html_writer::end_tag('nav');
    }
}
