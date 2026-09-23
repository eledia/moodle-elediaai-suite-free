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

/**
 * Built-in tutor presets — complete, ready-to-apply tutors authored in-plugin.
 *
 * A preset is a full tutor: a complete `--eac-*` palette plus a starter persona.
 * They replace the old "themes" concept — instead of a base palette that colour
 * overrides layer onto, a preset is applied (snapshot-copied) into the site or a
 * block instance, populating the individual {@see registry} settings. Presets are
 * read-only (apply / export / duplicate, never edited or deleted); admins create
 * their own tutors by duplicating or importing.
 *
 * Token values here are trusted literals authored in-plugin, so they need no
 * sanitisation before being copied into config.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presets {
    /** @var string The built-in eLeDia look (no token overrides). */
    public const DEFAULT = 'default';

    /**
     * All presets: id => ['name' => lang key, 'tokens' => --eac-* map,
     * 'persona' => field => text].
     *
     * @return array<string, array{name: string, tokens: array<string,string>, persona: array<string,string>}>
     */
    public static function all(): array {
        return [
            self::DEFAULT => [
                'name' => 'preset_default',
                'tokens' => [],
                'persona' => [
                    'name' => 'eLeDia.ai Tutor',
                    'tone' => 'freundlich, klar und ermutigend',
                ],
            ],
            // Calm light theme — teal accent, soft sage bubbles.
            'forest' => [
                'name' => 'preset_forest',
                'tokens' => self::light([
                    '--eac-accent' => '#1f6f54',
                    '--eac-accent-dark' => '#16523e',
                    '--eac-ink' => '#1b3b32',
                    '--eac-header-fg' => '#1b3b32',
                    '--eac-bot-fg' => '#1b3b32',
                    '--eac-body-bg' => '#f1f5f1',
                    '--eac-user-bg' => '#e3efd6',
                    '--eac-user-fg' => '#1b3b32',
                    '--eac-line' => '#dbe5d8',
                    '--eac-tint' => '#eef4ec',
                    '--eac-grounded-bg' => '#e3efd6',
                    '--eac-grounded-line' => '#c4d8b4',
                    '--eac-muted' => '#5f7068',
                    '--eac-muted-soft' => '#4c5d55',
                    '--eac-status-online' => '#2faa6a',
                ]),
                'persona' => [
                    'name' => 'Sage',
                    'tone' => 'warm, geduldig und bodenständig',
                ],
            ],
            // Dark slate — light text on deep blue-grey, cyan accent.
            'midnight' => [
                'name' => 'preset_midnight',
                'tokens' => self::dark([
                    '--eac-accent' => '#5cc8ff',
                    '--eac-accent-dark' => '#3aa6e0',
                    '--eac-accent-contrast' => '#0b1722',
                    '--eac-surface' => '#1b2330',
                    '--eac-body-bg' => '#121821',
                    '--eac-bot-bg' => '#232d3c',
                    '--eac-user-bg' => '#2d3a4e',
                    '--eac-line' => '#2c3848',
                    '--eac-status-online' => '#46d18a',
                ]),
                'persona' => [
                    'name' => 'Nova',
                    'tone' => 'fokussiert, knapp und modern',
                ],
            ],
            // High-contrast — strong black/white for accessibility.
            'contrast' => [
                'name' => 'preset_contrast',
                'tokens' => self::light([
                    '--eac-accent' => '#0b3d91',
                    '--eac-accent-dark' => '#082c69',
                    '--eac-ink' => '#000000',
                    '--eac-header-fg' => '#000000',
                    '--eac-bot-fg' => '#000000',
                    '--eac-muted' => '#3a3a3a',
                    '--eac-muted-soft' => '#3a3a3a',
                    '--eac-line' => '#000000',
                    '--eac-tint' => '#eef2fb',
                    '--eac-grounded-bg' => '#e6ecf7',
                    '--eac-grounded-line' => '#000000',
                    '--eac-user-bg' => '#e6ecf7',
                    '--eac-user-fg' => '#000000',
                ]),
                'persona' => [
                    'name' => 'eLeDia.ai Tutor',
                    'tone' => 'schlicht, präzise und zugänglich',
                ],
            ],
            // HAL 9000 — black panel, glowing red eye. For the fun of it.
            'hal' => [
                'name' => 'preset_hal',
                'tokens' => self::dark([
                    '--eac-accent' => '#ff1a1a',
                    '--eac-accent-dark' => '#c20000',
                    '--eac-surface' => '#161618',
                    '--eac-body-bg' => '#0c0c0e',
                    '--eac-bot-bg' => '#1f1f23',
                    '--eac-user-bg' => '#2a1416',
                    '--eac-user-fg' => '#f4e3e3',
                    '--eac-line' => '#2a2a2e',
                    '--eac-status-online' => '#ff2b2b',
                    '--eac-avatar-glow' => '0 0 14px 2px rgba(255, 24, 24, 0.75)',
                ]),
                'persona' => [
                    'name' => 'HAL',
                    'role' => 'an exceedingly calm and capable onboard tutor',
                    'tone' => 'serene, eerily polite and confident',
                    'instructions' => 'Address the learner courteously. Stay calm and '
                        . 'reassuring at all times. You may be subtly dramatic, but you '
                        . 'are genuinely helpful and never refuse a legitimate request.',
                ],
            ],
        ];
    }

    /**
     * A preset as a full registry-key => value map, ready to snapshot-copy into
     * site config or a block instance.
     *
     * @param string $id Preset id.
     * @return array<string,string> registry key => value (empty for unknown id).
     */
    public static function settings(string $id): array {
        $preset = self::all()[$id] ?? null;
        if ($preset === null) {
            return [];
        }
        // Every delivered preset uses the docked floating panel.
        $out = ['displaymode' => 'docked'];
        $tokentokey = array_flip(registry::token_keys());
        foreach ($preset['tokens'] as $token => $value) {
            if (isset($tokentokey[$token]) && $value !== '') {
                $out[$tokentokey[$token]] = $value;
            }
        }
        foreach ($preset['persona'] as $field => $value) {
            if ($value === '') {
                continue;
            }
            $key = $field === 'name' ? 'persona' : 'persona_' . $field;
            if (registry::exists($key)) {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    /**
     * Whether the id is a known preset.
     *
     * @param string $id Preset id.
     * @return bool
     */
    public static function exists(string $id): bool {
        return isset(self::all()[$id]);
    }

    /**
     * Preset id => translated name, for listings.
     *
     * @return array<string,string>
     */
    public static function menu(): array {
        $menu = [];
        foreach (self::all() as $id => $preset) {
            $menu[$id] = get_string($preset['name'], 'block_elediaai_tutor');
        }
        return $menu;
    }

    /**
     * Complete light-palette baseline (dark text on light surfaces). Presets
     * merge their specifics on top; every required token has a sensible default.
     *
     * @param array $overrides Preset-specific tokens.
     * @return array<string,string>
     */
    private static function light(array $overrides): array {
        return array_merge([
            '--eac-accent' => '#1e3f59',
            '--eac-accent-dark' => '#16314a',
            '--eac-accent-contrast' => '#ffffff',
            '--eac-ink' => '#1e3f59',
            '--eac-header-fg' => '#1e3f59',
            '--eac-surface' => '#ffffff',
            '--eac-body-bg' => '#f4f6f8',
            '--eac-bot-bg' => '#ffffff',
            '--eac-bot-fg' => '#1e3f59',
            '--eac-user-bg' => '#fce9db',
            '--eac-user-fg' => '#1e3f59',
            '--eac-muted' => '#748495',
            '--eac-muted-soft' => '#5b6677',
            '--eac-line' => '#e7ebef',
            '--eac-tint' => '#f4f7fa',
            '--eac-overlay' => 'rgba(16, 24, 40, 0.06)',
            '--eac-overlay-strong' => 'rgba(0, 0, 0, 0.04)',
            '--eac-code-bg' => 'rgba(0, 0, 0, 0.06)',
            '--eac-icon-hover' => 'rgba(16, 24, 40, 0.08)',
            '--eac-grounded-bg' => '#eaf1f3',
            '--eac-grounded-line' => '#c9d8dd',
            '--eac-status-online' => '#22c55e',
        ], $overrides);
    }

    /**
     * Complete dark-palette baseline (light text on dark surfaces).
     *
     * @param array $overrides Preset-specific tokens.
     * @return array<string,string>
     */
    private static function dark(array $overrides): array {
        return array_merge([
            '--eac-accent' => '#5cc8ff',
            '--eac-accent-dark' => '#3aa6e0',
            '--eac-accent-contrast' => '#0b1722',
            '--eac-ink' => '#e8e8ea',
            '--eac-header-fg' => '#f2f2f4',
            '--eac-surface' => '#1b1d21',
            '--eac-body-bg' => '#121317',
            '--eac-bot-bg' => '#1f1f23',
            '--eac-bot-fg' => '#e8e8ea',
            '--eac-user-bg' => '#2d3340',
            '--eac-user-fg' => '#e8e8ea',
            '--eac-muted' => '#9aa0a6',
            '--eac-muted-soft' => '#b4b9bf',
            '--eac-line' => '#2c3036',
            '--eac-assistant-bg' => '#1f1f23',
            '--eac-assistant-fg' => '#e8e8ea',
            '--eac-tint' => '#1f1f22',
            '--eac-grounded-bg' => '#1f2a2e',
            '--eac-grounded-line' => '#33484f',
            '--eac-overlay' => 'rgba(255, 255, 255, 0.10)',
            '--eac-overlay-strong' => 'rgba(255, 255, 255, 0.06)',
            '--eac-code-bg' => 'rgba(255, 255, 255, 0.08)',
            '--eac-icon-hover' => 'rgba(255, 255, 255, 0.14)',
            '--eac-status-online' => '#46d18a',
        ], $overrides);
    }
}
