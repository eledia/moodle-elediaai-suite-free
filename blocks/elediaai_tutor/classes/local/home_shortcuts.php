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

/**
 * Shortcut sidebar for the full-page eLeDia.ai Tutor home.
 *
 * @package     block_elediaai_tutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_elediaai_tutor\local;

use html_writer;

/**
 * Renders feature shortcuts from the shared eLeDia.ai suite registry.
 */
final class home_shortcuts {
    /**
     * Render the right-hand shortcut drawer.
     *
     * @param \context $context Capability context for visible suite features.
     * @return string HTML.
     */
    public static function render(\context $context): string {
        $shortcuts = self::shortcuts($context);
        if ($shortcuts === []) {
            return '';
        }

        $items = '';
        foreach ($shortcuts as $shortcut) {
            $items .= html_writer::link(
                $shortcut['url'],
                html_writer::tag(
                    'span',
                    $shortcut['icon'],
                    ['class' => 'elediaai-chat-featuredock__icon', 'aria-hidden' => 'true']
                )
                    . html_writer::tag(
                        'span',
                        html_writer::tag('span', s($shortcut['name']), [
                            'class' => 'elediaai-chat-featuredock__name',
                        ])
                            . html_writer::tag('span', s($shortcut['description']), [
                                'class' => 'elediaai-chat-featuredock__desc',
                            ]),
                        ['class' => 'elediaai-chat-featuredock__text']
                    ),
                [
                    'class' => 'elediaai-chat-featuredock__item',
                    'aria-label' => get_string('home_shortcuts_open', 'block_elediaai_tutor', $shortcut['name']),
                ]
            );
        }

        $html = html_writer::start_tag('details', [
            'class' => 'elediaai-chat-featuredock',
            'id' => 'eledia-aitutor-featuredock',
        ]);
        $html .= html_writer::tag(
            'summary',
            self::render_icon('sliders')
                . html_writer::tag('span', get_string('home_shortcuts_toggle', 'block_elediaai_tutor'), [
                    'class' => 'accesshide',
                ]),
            [
                'class' => 'elediaai-chat-featuredock__toggle',
                'title' => get_string('home_shortcuts_toggle', 'block_elediaai_tutor'),
            ]
        );
        $html .= html_writer::start_tag('aside', [
            'class' => 'elediaai-chat-featuredock__panel',
            'aria-labelledby' => 'eledia-aitutor-featuredock-title',
        ]);
        $html .= html_writer::tag(
            'h2',
            get_string('home_shortcuts_title', 'block_elediaai_tutor'),
            [
                'class' => 'elediaai-chat-featuredock__title',
                'id' => 'eledia-aitutor-featuredock-title',
            ]
        );
        $html .= html_writer::tag(
            'p',
            get_string('home_shortcuts_intro', 'block_elediaai_tutor'),
            ['class' => 'elediaai-chat-featuredock__intro']
        );
        $html .= html_writer::tag('nav', $items, [
            'class' => 'elediaai-chat-featuredock__list',
            'aria-label' => get_string('home_shortcuts_title', 'block_elediaai_tutor'),
        ]);
        $html .= html_writer::end_tag('aside');
        $html .= html_writer::end_tag('details');

        return $html;
    }

    /**
     * Return visible launchable suite features.
     *
     * @param \context $context
     * @return array<int, array{name: string, description: string, url: \moodle_url, icon: string}>
     */
    private static function shortcuts(\context $context): array {
        if (!class_exists(\local_elediaai_core\feature\registry::class)) {
            return [];
        }

        try {
            $features = \local_elediaai_core\feature\registry::visible($context);
        } catch (\Throwable $e) {
            return [];
        }

        $shortcuts = [];
        foreach ($features as $feature) {
            if ($feature->launchurl === null || $feature->comingsoon || $feature->installrequired) {
                continue;
            }
            if (in_array($feature->id, ['tutor', 'tutorpremium'], true)) {
                continue;
            }

            $shortcuts[] = [
                'name' => $feature->name,
                'description' => $feature->description,
                'url' => $feature->launchurl,
                'icon' => self::render_icon($feature->icon),
            ];
        }

        return array_slice($shortcuts, 0, 10);
    }

    /**
     * Render a suite icon with a small fallback.
     *
     * @param string $icon
     * @return string
     */
    private static function render_icon(string $icon): string {
        if (class_exists(\local_elediaai_core\output\lucide_icon::class)) {
            $svg = \local_elediaai_core\output\lucide_icon::render($icon);
            if ($svg !== '') {
                return $svg;
            }
        }

        return '<span class="elediaai-chat-featuredock__icon-dot" aria-hidden="true"></span>';
    }
}
