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

use moodle_url;

/**
 * Resolves the effective tutor branding + persona for the widget.
 *
 * Branding has two layers: institution-wide **site** values (admin settings) and
 * optional **per-instance** overrides set on a block. An instance value wins when
 * the admin has exposed that key and it is set; otherwise the site value applies;
 * otherwise the built-in design default in styles.css stands (we simply emit no
 * override for that token).
 *
 * Every settable visual property is a `--eac-*` design token described in the
 * {@see registry}. {@see resolve()} walks the registry, reads each token's
 * effective value (instance-over-site), sanitises it, and produces a token map
 * that {@see css_variables()} serialises into a scoped inline style — so two
 * differently-branded instances can coexist on one page.
 *
 * The persona fields (name/role/tone/audience/instructions) are resolved the same
 * way and assembled by {@see persona()} for transmission to the RAG server.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class branding {
    /** @var string Footer shows the default "Powered by eLeDia.ai" credit + link. */
    public const FOOTER_DEFAULT = 'default';

    /** @var string Footer shows institution-defined text. */
    public const FOOTER_CUSTOM = 'custom';

    /** @var string Footer hidden entirely (full white-label). */
    public const FOOTER_NONE = 'none';

    /** @var string File area for the site-level brand logo (tutor logo). */
    public const LOGO_FILEAREA = 'brandlogo';

    /** @var string File area for the site-level conversation avatar. */
    public const AVATAR_FILEAREA = 'brandavatar';

    /** @var string Block-instance file area for the tutor logo override. */
    public const INSTANCE_LOGO_FILEAREA = 'instancelogo';

    /** @var string Block-instance file area for the conversation avatar override. */
    public const INSTANCE_AVATAR_FILEAREA = 'instanceavatar';

    /** @var string System-context file area for a saved tutor profile's logo. */
    public const TUTOR_LOGO_FILEAREA = 'tutorlogo';

    /** @var string System-context file area for a saved tutor profile's avatar. */
    public const TUTOR_AVATAR_FILEAREA = 'tutoravatar';

    /**
     * The effective branding kit, merging per-instance overrides over the site
     * defaults, driven entirely by the {@see registry}.
     *
     * @param array $instance Per-instance overrides keyed by
     *        registry key (e.g. 'brandaccent', 'launcherstyle', 'persona').
     * @return array{tokens: array<string,string>, accent: string, bubble: string,
     *         botbubble: string, launchlabel: string, launcherstyle: string,
     *         footermode: string, footertext: string}
     */
    public static function resolve(array $instance = []): array {
        // 1. Assemble the design-token map from every token key in the registry.
        $tokens = [];
        foreach (registry::token_keys() as $key => $tokenname) {
            $value = self::effective_value($key, $instance);
            if ($value === null || $value === '') {
                continue;
            }
            $clean = registry::sanitise($key, $value);
            if ($clean === null || $clean === '') {
                continue;
            }
            $tokens[$tokenname] = (string) $clean;
        }

        // 2. Derive the accent-hover shade from the accent when not set explicitly.
        if (isset($tokens['--eac-accent']) && !isset($tokens['--eac-accent-dark'])) {
            $tokens['--eac-accent-dark'] = self::darken($tokens['--eac-accent'], 0.12);
        }

        // 3. Launcher label: instance → site → built-in default string.
        $launchlabel = trim((string) self::effective_value('launchlabel', $instance));
        if ($launchlabel === '') {
            $launchlabel = get_string('launch', 'block_elediaai_tutor');
        }

        // 4. Launcher style: validated against the registry's option set.
        $launcherstyle = (string) self::effective_value('launcherstyle', $instance);
        if (!isset(registry::get('launcherstyle')['options'][$launcherstyle])) {
            $launcherstyle = (string) registry::default_for('launcherstyle');
        }

        return [
            'tokens' => $tokens,
            'accent' => $tokens['--eac-accent'] ?? '',
            'bubble' => $tokens['--eac-user-bg'] ?? '',
            'botbubble' => $tokens['--eac-bot-bg'] ?? '',
            'launchlabel' => $launchlabel,
            'launcherstyle' => $launcherstyle,
            'footermode' => self::footer_mode($instance),
            'footertext' => self::footer_text($instance),
        ];
    }

    /**
     * The effective persona for transmission to the RAG server.
     *
     * Returns only the populated sub-fields (name/role/tone/audience/instructions),
     * each resolved instance-over-site. Empty when nothing is configured.
     *
     * @param array $instance Per-instance overrides keyed by registry key.
     * @return array<string,string>
     */
    public static function persona(array $instance = []): array {
        $out = [];
        foreach (registry::persona_keys() as $key) {
            $value = trim((string) self::effective_value($key, $instance));
            if ($value === '') {
                continue;
            }
            $field = $key === 'persona' ? 'name' : substr($key, strlen('persona_'));
            $out[$field] = $value;
        }
        return $out;
    }

    /**
     * The effective value of a registry key (instance-over-site).
     *
     * Thin wrapper over {@see registry::effective()} so branding reads the same
     * precedence as the rest of the plugin.
     *
     * @param string $key Registry key.
     * @param array $instance Per-instance overrides keyed by registry key.
     * @return mixed
     */
    private static function effective_value(string $key, array $instance): mixed {
        return registry::effective($key, $instance);
    }

    /**
     * Serialise a kit's resolved `--eac-*` token map into CSS declarations.
     *
     * Empty when nothing is branded, so the styles.css defaults stand. The caller
     * injects these as a scoped inline style on the widget root + panel + launcher.
     *
     * @param array $brand A kit from {@see resolve()}.
     * @return string CSS declarations (may be empty).
     */
    public static function css_variables(array $brand): string {
        $out = '';
        foreach (($brand['tokens'] ?? []) as $name => $value) {
            $out .= $name . ':' . $value . ';';
        }
        return $out;
    }

    /**
     * The site-level conversation avatar URL (assistant message avatar), or ''.
     *
     * @return string
     */
    public static function site_avatar_url(): string {
        return self::system_file_url(self::AVATAR_FILEAREA);
    }

    /**
     * The site-level brand logo URL, or '' when none is uploaded.
     *
     * @return string
     */
    public static function site_logo_url(): string {
        return self::system_file_url(self::LOGO_FILEAREA);
    }

    /**
     * URL of a single file stored in the system context for a given file area.
     *
     * @param string $filearea System-context file area.
     * @return string Empty string when no file is present.
     */
    private static function system_file_url(string $filearea): string {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            \core\context\system::instance()->id,
            'block_elediaai_tutor',
            $filearea,
            0,
            'itemid, filepath, filename',
            false
        );
        if (empty($files)) {
            return '';
        }
        $file = reset($files);
        return moodle_url::make_pluginfile_url(
            \core\context\system::instance()->id,
            'block_elediaai_tutor',
            $filearea,
            0,
            $file->get_filepath(),
            $file->get_filename()
        )->out(false);
    }

    /**
     * URL of a per-instance uploaded file (logo/avatar), or '' when none.
     *
     * Files live in the block context; only block contexts can carry them
     * (the standalone view.php uses the system context and has none).
     *
     * @param \context $context The widget context.
     * @param string $filearea INSTANCE_LOGO_FILEAREA or INSTANCE_AVATAR_FILEAREA.
     * @return string
     */
    public static function instance_file_url(\context $context, string $filearea): string {
        if ($context->contextlevel !== CONTEXT_BLOCK) {
            return '';
        }
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'block_elediaai_tutor',
            $filearea,
            0,
            'itemid, filepath, filename',
            false
        );
        if (empty($files)) {
            return '';
        }
        $file = reset($files);
        return moodle_url::make_pluginfile_url(
            $context->id,
            'block_elediaai_tutor',
            $filearea,
            0,
            $file->get_filepath(),
            $file->get_filename()
        )->out(false);
    }

    /**
     * The configured footer mode, instance-over-site (when the admin has exposed
     * the footer for per-instance override).
     *
     * @param array $instance Per-instance overrides keyed by registry key.
     * @return string One of the FOOTER_* constants.
     */
    public static function footer_mode(array $instance = []): string {
        $mode = (string) registry::effective('footermode', $instance);
        return in_array($mode, [self::FOOTER_DEFAULT, self::FOOTER_CUSTOM, self::FOOTER_NONE], true)
            ? $mode : self::FOOTER_DEFAULT;
    }

    /**
     * The effective footer text for the current mode ('' when hidden),
     * instance-over-site.
     *
     * @param array $instance Per-instance overrides keyed by registry key.
     * @return string
     */
    public static function footer_text(array $instance = []): string {
        switch (self::footer_mode($instance)) {
            case self::FOOTER_NONE:
                return '';
            case self::FOOTER_CUSTOM:
                $text = trim((string) registry::effective('footertext', $instance));
                return $text !== '' ? $text : get_string('poweredby', 'block_elediaai_tutor');
            default:
                return get_string('poweredby', 'block_elediaai_tutor');
        }
    }

    /**
     * Validate a colour to a safe CSS hex value, or null if invalid/empty.
     *
     * @param string $value Candidate colour.
     * @return string|null A `#rgb`/`#rrggbb`(/aa) value, or null.
     */
    public static function sanitise_colour(string $value): ?string {
        $value = trim($value);
        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) ? $value : null;
    }

    /**
     * Reduce a free-text CSS token value to one that cannot break out of an
     * inline `style="…"` declaration.
     *
     * Used for non-colour tokens (radii, gaps, shadows, glows, translucent
     * washes, fab offsets). Allows the small character set needed for lengths,
     * rgba()/color-mix()/calc() and comma lists; rejects anything that could
     * terminate the declaration or inject (`;`, `{`, `}`, `<`, `>`, backslash,
     * `url(`, `expression`, comments, at-rules). Returns '' when unusable.
     *
     * @param string $value Candidate value.
     * @return string Sanitised value, or '' when nothing safe remains.
     */
    public static function sanitise_css_value(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/[;{}<>\\\\]/', $value)) {
            return '';
        }
        $lower = strtolower($value);
        foreach (['url(', 'expression', 'javascript:', '/*', '*/', '@'] as $bad) {
            if (strpos($lower, $bad) !== false) {
                return '';
            }
        }
        // Allowlist: hex, alphanumerics, spaces, commas, dots, %, parentheses, hyphen.
        if (!preg_match('/^[#a-z0-9 ,.%()\-]+$/i', $value)) {
            return '';
        }
        return $value;
    }

    /**
     * Reduce a font specification to a safe `font-family` value.
     *
     * Allows letters, digits, spaces, commas, hyphens and quotes only — enough
     * for a font stack, nothing that could break out of the declaration.
     *
     * @param string $value Candidate font stack.
     * @return string Sanitised value, or '' when nothing usable remains.
     */
    public static function sanitise_font(string $value): string {
        $value = trim((string) preg_replace('/[^A-Za-z0-9 ,\'"\-]/', '', $value));
        return $value;
    }

    /**
     * Darken a hex colour by a fraction (0..1) for the accent-hover token.
     *
     * @param string $hex A `#rgb` or `#rrggbb` colour.
     * @param float $fraction Amount to darken (0..1).
     * @return string A `#rrggbb` colour.
     */
    public static function darken(string $hex, float $fraction): string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) < 6) {
            return '#' . $hex;
        }
        $rgb = [];
        foreach ([0, 2, 4] as $i) {
            $channel = (int) hexdec(substr($hex, $i, 2));
            $rgb[] = max(0, min(255, (int) round($channel * (1 - $fraction))));
        }
        return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
    }
}
