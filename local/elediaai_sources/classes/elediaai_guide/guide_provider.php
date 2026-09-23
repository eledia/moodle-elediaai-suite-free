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
 * What this plugin tells its users, in the suite guide.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_elediaai_sources\elediaai_guide;

use local_elediaai_guide\content\audience;
use local_elediaai_guide\content\guide_provider as guide_provider_contract;
use local_elediaai_guide\content\topic;

/**
 * This plugin explains itself.
 *
 * The guide is only a soft dependency: without it the interface is absent,
 * the registry that would load this file does not exist either, and
 * get_topics() degrades to an empty result.
 */
final class guide_provider implements guide_provider_contract {
    /** @var string The language pack these texts live in. */
    private const LANG = 'local_elediaai_sources';

    #[\Override]
    public static function get_topics(): array {
        if (!interface_exists(guide_provider_contract::class)) {
            return [];
        }

        return [
            new topic(
                id: 'sources_what',
                component: 'local_elediaai_sources',
                title: get_string('guide_title', self::LANG),
                summary: get_string('guide_summary', self::LANG),
                body: get_string('guide_body', self::LANG),
                audiences: [audience::TEACHER, audience::ADMIN],
                icon: 'database',
                order: 230,
                section: topic::SECTION_USING,
            ),
        ];
    }

    #[\Override]
    public static function get_announcements(): array {
        return [];
    }
}
