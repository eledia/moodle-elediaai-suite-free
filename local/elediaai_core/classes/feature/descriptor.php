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
 * AI feature descriptor.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\feature;

defined('MOODLE_INTERNAL') || die();

use moodle_url;

/**
 * Plain value object describing one AI feature shipped via a sister
 * plugin. Returned by `feature_provider::get_descriptors()`.
 */
final class descriptor {
    /**
     * @var string A place you open: the feature has a page of its own, and the
     * dashboard tile takes you straight there.
     */
    public const KIND_PAGE = 'page';

    /**
     * @var string A capability you use inside a course -- an activity, a
     * question type, a block, an entry in the activity chooser. There is
     * nowhere to "open", so the tile says where to find it instead. Presenting
     * these as launchable was what made the dashboard look arbitrary.
     */
    public const KIND_IN_COURSE = 'incourse';

    /**
     * Create a feature descriptor.
     *
     * @param string $id Stable feature ID, e.g. `questiongenerator`.
     *                  Prefix-namespace by the providing plugin to avoid
     *                  collisions across forks.
     * @param string $component Frankenstyle component name of the plugin
     *                          that owns this feature (e.g. `local_aiquestions`).
     * @param string $name Display name, already localised.
     * @param string $description Short user-facing summary for dashboard cards.
     * @param moodle_url|null $launchurl Where the dashboard tile should
     *                          deep-link to. Null = the feature has no
     *                          standalone entry point.
     * @param string $icon Lucide icon name (historic Font-Awesome name without
     *                          the `fa-` prefix; resolved by
     *                          {@see \local_elediaai_core\output\lucide_icon}).
     * @param string|null $capability Capability the visitor must hold to
     *                          see and use the feature; null means the
     *                          feature is always visible.
     * @param moodle_url|null $configurl Admin settings page; rendered as a
     *                          gear-link on the dashboard tile.
     * @param tier $tier Commercial tier. Free features are visible as
     *                          before; premium features need a policy
     *                          provider grant.
     * @param bool $installrequired Whether this is an install CTA for a
     *                          known feature whose plugin is not installed.
     * @param moodle_url|null $installurl Where admins can install or get the
     *                          plugin package.
     * @param bool $comingsoon When true, the launcher renders the tile
     *                          disabled with a "Coming soon" badge.
     * @param string|null $detaildescription Longer decision-oriented
     *                          explanation for the feature overview page.
     * @param string[] $keyfeatures Short admin-facing list of core
     *                          functions shown on the feature overview page.
     * @param string|null $audience Which group this feature is primarily for:
     *                          one of the `registry::AUDIENCE_*` constants.
     *                          Null means the plugin has not said, and the
     *                          registry falls back to its own table -- see
     *                          {@see \local_elediaai_core\feature\registry::audience()}.
     * @param string|null $imagename Base name of an image in the providing
     *                          component's `pix/` directory, without the
     *                          extension. Drawn on the dashboard card in place
     *                          of the icon tile. Null keeps the icon tile.
     * @param string|null $kind What sort of thing this feature is: one of the
     *                          `KIND_*` constants. `KIND_PAGE` is somewhere you
     *                          go; `KIND_IN_COURSE` is something you use inside
     *                          a course and cannot open from the dashboard.
     *                          Null means the plugin has not said, and the
     *                          registry derives it from `launchurl` -- see
     *                          {@see \local_elediaai_core\feature\registry::kind()}.
     * @param string|null $usagehint One sentence saying where a course
     *                          capability is found, e.g. "Add an activity and
     *                          choose AI feedback". Only meaningful for
     *                          `KIND_IN_COURSE`, where the card has no
     *                          destination of its own to offer.
     * @param array|null $origin Credit for a feature built on someone else's
     *                          work: `title`, `authors`, `url`, `note`. Only
     *                          the plugin knows whose shoulders it stands on;
     *                          null means it is eLeDia's own.
     */
    public function __construct(
        /** @var string Constructor-promoted value. */
        public readonly string $id,
        /** @var string Constructor-promoted value. */
        public readonly string $component,
        /** @var string Constructor-promoted value. */
        public readonly string $name,
        /** @var string Constructor-promoted value. */
        public readonly string $description,
        /** @var ?moodle_url Constructor-promoted value. */
        public readonly ?moodle_url $launchurl,
        /** @var string Constructor-promoted value. */
        public readonly string $icon,
        /** @var ?string Constructor-promoted value. */
        public readonly ?string $capability = null,
        /** @var ?moodle_url Constructor-promoted value. */
        public readonly ?moodle_url $configurl = null,
        /** @var bool Constructor-promoted value. */
        public readonly bool $comingsoon = false,
        /** @var ?string Constructor-promoted value. */
        public readonly ?string $detaildescription = null,
        /** @var tier Constructor-promoted value. */
        public readonly tier $tier = tier::FREE,
        /** @var bool Constructor-promoted value. */
        public readonly bool $installrequired = false,
        /** @var ?moodle_url Constructor-promoted value. */
        public readonly ?moodle_url $installurl = null,
        /** @var array Constructor-promoted value. */
        public readonly array $keyfeatures = [],
        /** @var ?string Constructor-promoted value. */
        public readonly ?string $audience = null,
        /** @var ?string Constructor-promoted value. */
        public readonly ?string $imagename = null,
        /** @var ?string Constructor-promoted value. */
        public readonly ?string $kind = null,
        /** @var ?string Constructor-promoted value. */
        public readonly ?string $usagehint = null,
        /** @var ?array Constructor-promoted value. */
        public readonly ?array $origin = null,
    ) {
    }

    /**
     * Whether this feature requires a premium policy grant.
     *
     * @return bool
     */
    public function is_premium(): bool {
        return $this->tier === tier::PREMIUM;
    }
}
