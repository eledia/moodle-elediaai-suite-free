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
 * @package    local_aitransparency
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_aitransparency\elediaai_guide;

use local_elediaai_guide\content\audience;
use local_elediaai_guide\content\guide_provider as guide_provider_contract;
use local_elediaai_guide\content\topic;

/**
 * The provenance report explains itself.
 *
 * Every tile on the dashboard leads to a handbook chapter when it has no page
 * of its own, and the guide checks that none is silent. Since the report got
 * its tile (#30), it needs its chapter as well.
 */
final class guide_provider implements guide_provider_contract {
    /** @var string The language pack these texts live in. */
    private const LANG = 'local_aitransparency';

    #[\Override]
    public static function get_topics(): array {
        if (!interface_exists(guide_provider_contract::class)) {
            return [];
        }

        return [
            new topic(
                id: 'aitransparency_report',
                component: 'local_aitransparency',
                title: get_string('guide_title', self::LANG),
                summary: get_string('guide_summary', self::LANG),
                body: get_string('guide_body', self::LANG),
                audiences: [audience::ADMIN],
                icon: 'shield',
                order: 48,
                section: topic::SECTION_TRUST,
            ),
        ];
    }

    #[\Override]
    public static function get_announcements(): array {
        return [];
    }
}
