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
 * One thing a plugin reports about its own state.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_elediaai_core\health;

use moodle_url;

/**
 * What a plugin says about one aspect of itself.
 *
 * Four states, and the difference between the first two is the one that
 * matters most in practice: something that was never set up looks exactly
 * like something that broke, unless the report says which it is. An
 * administrator reads "not set up" as a task and "broken" as an alarm.
 */
final class check {
    /** @var string Set up and answering. */
    public const STATUS_OK = 'ok';

    /** @var string Not set up at all. Nothing was tried, nothing is broken. */
    public const STATUS_UNCONFIGURED = 'unconfigured';

    /** @var string Set up, but something is wrong. */
    public const STATUS_ERROR = 'error';

    /** @var string Set up and working, with something worth knowing. */
    public const STATUS_WARNING = 'warning';

    /** @var string Deliberately switched off. Not a fault and not a task. */
    public const STATUS_DISABLED = 'disabled';

    /**
     * Report one aspect.
     *
     * @param string $id Stable id, unique within the component.
     * @param string $component Frankenstyle name of the reporting plugin.
     * @param string $label What is being reported, localised.
     * @param string $status One of the STATUS_* constants.
     * @param string $detail One sentence: what was found, or why not.
     *                       Shown as-is; keep provider secrets out of it.
     * @param moodle_url|null $actionurl Where the reader fixes or inspects it.
     * @param string|null $actionlabel Label for that link, localised.
     */
    public function __construct(
        /** @var string Constructor-promoted value. */
        public readonly string $id,
        /** @var string Constructor-promoted value. */
        public readonly string $component,
        /** @var string Constructor-promoted value. */
        public readonly string $label,
        /** @var string Constructor-promoted value. */
        public readonly string $status = self::STATUS_OK,
        /** @var string Constructor-promoted value. */
        public readonly string $detail = '',
        /** @var ?moodle_url Constructor-promoted value. */
        public readonly ?moodle_url $actionurl = null,
        /** @var ?string Constructor-promoted value. */
        public readonly ?string $actionlabel = null,
    ) {
    }

    /**
     * Every status a check may report.
     *
     * @return string[]
     */
    public static function statuses(): array {
        return [
            self::STATUS_OK,
            self::STATUS_UNCONFIGURED,
            self::STATUS_ERROR,
            self::STATUS_WARNING,
            self::STATUS_DISABLED,
        ];
    }

    /**
     * Whether this status needs somebody to act.
     *
     * Switched off is a decision, not a defect; unconfigured is a task but
     * not an alarm. Only those two are quiet.
     *
     * @return bool
     */
    public function needs_attention(): bool {
        return in_array($this->status, [self::STATUS_ERROR, self::STATUS_WARNING], true);
    }
}
