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
 * Feature gate for capabilities implemented by the optional premium add-on.
 *
 * The free block must stay functional without the add-on, so every premium
 * feature defaults to unavailable. A companion plugin can unlock features by
 * being installed as local_elediaai_tutor_premium and exposing
 * local_elediaai_tutor_premium\feature::has_feature($feature).
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class premium {
    /** @var string Feature id for footer white-label/customisation. */
    public const FEATURE_FOOTER_BRANDING = 'footerbranding';

    /** @var string Feature id for the larger centered chat view. */
    public const FEATURE_CHAT_EXPAND = 'chat_expand';

    /** @var string Expected component name of the optional premium add-on. */
    private const PREMIUM_COMPONENT = 'local_elediaai_tutor_premium';

    /**
     * Whether a premium feature is available in this installation.
     *
     * @param string $feature Feature id.
     * @return bool
     */
    public static function has_feature(string $feature): bool {
        if (!self::addon_enabled()) {
            return false;
        }

        $provider = '\\local_elediaai_tutor_premium\\feature';
        if (class_exists($provider) && method_exists($provider, 'has_feature')) {
            return (bool) $provider::has_feature($feature);
        }

        return true;
    }

    /**
     * Whether the optional premium add-on is installed and enabled.
     *
     * @return bool
     */
    private static function addon_enabled(): bool {
        if (!class_exists('\\core_plugin_manager')) {
            return false;
        }

        $plugininfo = \core_plugin_manager::instance()->get_plugin_info(self::PREMIUM_COMPONENT);
        if ($plugininfo === null) {
            return false;
        }

        return $plugininfo->is_enabled() !== false;
    }
}
