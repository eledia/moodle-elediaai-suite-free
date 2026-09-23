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

use cache;
use moodle_exception;
use moodle_url;

/**
 * Security and configuration helper for the eLeDia.ai Tutor block.
 *
 * Centralises plugin configuration access, outgoing-URL (SSRF) validation,
 * message-length enforcement and per-user rate limiting. Keeping these concerns
 * in one auditable place keeps the external functions and the RAG client small
 * and easy to review.
 *
 * The configured RAG server URL is the *only* host the block ever talks to:
 * users can never supply or influence the destination URL, which is the primary
 * SSRF control.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class security {
    /** @var string Configuration component name. */
    public const CONFIG_COMPONENT = 'block_elediaai_tutor';

    /**
     * Read a plugin configuration value with a default fallback.
     *
     * @param string $name Configuration setting name.
     * @param mixed $default Default value returned when the setting is unset.
     * @return mixed
     */
    public static function get_config(string $name, mixed $default = null): mixed {
        $value = get_config(self::CONFIG_COMPONENT, $name);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return $value;
    }















    /**
     * Whether global (non-course) chat is allowed.
     *
     * @return bool
     */
    public static function global_chat_enabled(): bool {
        return (int) self::get_config('enableglobalchat', 1) === 1;
    }

    /**
     * Whether course-context chat is allowed.
     *
     * @return bool
     */
    public static function course_chat_enabled(): bool {
        return (int) self::get_config('enablecoursechat', 1) === 1;
    }



    /**
     * Logging verbosity: 0 = errors only, 1 = normal, 2 = verbose (no secrets ever).
     *
     * @return int
     */
    public static function logging_verbosity(): int {
        return (int) self::get_config('loggingverbosity', 1);
    }

    /**
     * Admin custom CSS (trusted; targets the widget's .elediaai-chat-* classes),
     * or '' when unset.
     *
     * The per-token branding values, persona fields and other tutor settings are
     * resolved through the {@see registry} and {@see branding}, not via dedicated
     * getters here; this class keeps only the infrastructure/security settings.
     *
     * @return string
     */
    public static function custom_css(): string {
        $css = trim((string) self::get_config('customcss', ''));
        if ($css === '') {
            return '';
        }

        // The value is rendered inside a <style> element. CSS itself does not
        // need angle brackets, so remove them to prevent </style> breakouts.
        $css = str_replace(["\0", '<', '>'], '', $css);

        // Keep the setting intentionally small: no remote imports, no legacy
        // expression() payloads, no javascript: URLs.
        $css = preg_replace('/@import\b[^;]*(;|$)/i', '', $css) ?? '';
        $css = preg_replace('/expression\s*\([^)]*\)/i', '', $css) ?? '';
        $css = preg_replace('/javascript\s*:/i', '', $css) ?? '';

        return trim($css);
    }
}
