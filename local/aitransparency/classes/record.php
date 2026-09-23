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
 * Read-only view of a stored provenance record.
 *
 * @package    local_aitransparency
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aitransparency;

/**
 * Immutable representation of one local_aitransparency_rec row.
 */
final class record {
    /** @var int Row id. */
    public readonly int $id;

    /** @var string Public identifier. */
    public readonly string $uuid;

    /** @var string Generating component. */
    public readonly string $component;

    /** @var string AI action name. */
    public readonly string $actionname;

    /** @var string Provider frankenstyle. */
    public readonly string $provider;

    /** @var string Model identifier. */
    public readonly string $model;

    /** @var int Triggering user id (0 once anonymised). */
    public readonly int $userid;

    /** @var int Moodle context id. */
    public readonly int $contextid;

    /** @var string Asset type: text, image or file. */
    public readonly string $assettype;

    /** @var string SHA-256 of the output. */
    public readonly string $contenthash;

    /** @var string pending, marked, embedded, sidecar, failed or unsupported. */
    public readonly string $markstate;

    /** @var int Creation time. */
    public readonly int $timecreated;

    /** @var int|null ai_action_register.id when known. */
    public readonly ?int $registerid;

    /** @var int|null The turn this output came out of. */
    public readonly ?int $turnid;

    /**
     * Build a record view.
     *
     * @param int $id Row id
     * @param string $uuid Public identifier
     * @param string $component Generating component
     * @param string $actionname AI action name
     * @param string $provider Provider frankenstyle
     * @param string $model Model identifier
     * @param int $userid Triggering user id
     * @param int $contextid Moodle context id
     * @param string $assettype text, image or file
     * @param string $contenthash SHA-256 of the output
     * @param string $markstate Marking state
     * @param int $timecreated Creation time
     * @param int|null $registerid ai_action_register.id when known
     * @param int|null $turnid local_elediaai_core_turn.id when known
     */
    public function __construct(
        int $id,
        string $uuid,
        string $component,
        string $actionname,
        string $provider,
        string $model,
        int $userid,
        int $contextid,
        string $assettype,
        string $contenthash,
        string $markstate,
        int $timecreated,
        ?int $registerid = null,
        ?int $turnid = null
    ) {
        $this->id = $id;
        $this->uuid = $uuid;
        $this->component = $component;
        $this->actionname = $actionname;
        $this->provider = $provider;
        $this->model = $model;
        $this->userid = $userid;
        $this->contextid = $contextid;
        $this->assettype = $assettype;
        $this->contenthash = $contenthash;
        $this->markstate = $markstate;
        $this->timecreated = $timecreated;
        $this->registerid = $registerid;
        $this->turnid = $turnid;
    }

    /**
     * Build a record from a database row.
     *
     * @param \stdClass $row
     * @return self
     */
    public static function from_row(\stdClass $row): self {
        return new self(
            (int) $row->id,
            (string) $row->uuid,
            (string) $row->component,
            (string) $row->actionname,
            (string) $row->provider,
            (string) $row->model,
            (int) $row->userid,
            (int) $row->contextid,
            (string) $row->assettype,
            (string) $row->contenthash,
            (string) $row->markstate,
            (int) $row->timecreated,
            isset($row->registerid) ? (int) $row->registerid : null,
            isset($row->turnid) ? (int) $row->turnid : null
        );
    }
}
