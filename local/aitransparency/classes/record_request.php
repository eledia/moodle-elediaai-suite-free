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
 * Value object describing one provenance record to create.
 *
 * @package    local_aitransparency
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aitransparency;

/**
 * Immutable input for {@see provenance::record()}.
 *
 * Provide either the raw content (its SHA-256 is computed and stored) or a
 * precomputed content hash; passing neither is a coding error.
 */
final class record_request {
    /** @var string Generating component, e.g. local_elediaai_coursegen or the recording provider. */
    public readonly string $component;

    /** @var string generate_text, generate_image, summarise_text, explain_text or translate. */
    public readonly string $actionname;

    /** @var string Provider frankenstyle, e.g. aiprovider_eledia or local_literag. */
    public readonly string $provider;

    /** @var string Model identifier from the response, empty if unknown. */
    public readonly string $model;

    /** @var int Triggering user id. */
    public readonly int $userid;

    /** @var int Moodle context id. */
    public readonly int $contextid;

    /** @var string Asset type: text, image or file. */
    public readonly string $assettype;

    /** @var string|null Raw generated output to hash; null when a content hash is given. */
    public readonly ?string $content;

    /** @var string|null Precomputed SHA-256 hex; null when raw content is given. */
    public readonly ?string $contenthash;

    /** @var int|null ai_action_register.id when derivable. */
    public readonly ?int $registerid;

    /** @var int|null local_elediaai_core_turn.id when the caller knows it. */
    public readonly ?int $turnid;

    /**
     * Build a provenance record request.
     *
     * @param string $component Generating component
     * @param string $actionname AI action name
     * @param string $provider Provider frankenstyle
     * @param string $model Model identifier, empty if unknown
     * @param int $userid Triggering user id
     * @param int $contextid Moodle context id
     * @param string $assettype text, image or file
     * @param string|null $content Raw generated output to hash
     * @param string|null $contenthash Precomputed SHA-256 hex
     * @param int|null $registerid ai_action_register.id when known
     * @param int|null $turnid local_elediaai_core_turn.id when known
     */
    public function __construct(
        string $component,
        string $actionname,
        string $provider,
        string $model,
        int $userid,
        int $contextid,
        string $assettype = 'text',
        ?string $content = null,
        ?string $contenthash = null,
        ?int $registerid = null,
        ?int $turnid = null
    ) {
        $this->component = $component;
        $this->actionname = $actionname;
        $this->provider = $provider;
        $this->model = $model;
        $this->userid = $userid;
        $this->contextid = $contextid;
        $this->assettype = $assettype;
        $this->content = $content;
        $this->contenthash = $contenthash;
        $this->registerid = $registerid;
        $this->turnid = $turnid;
    }

    /**
     * Resolve the content hash from the raw content or the precomputed value.
     *
     * @return string SHA-256 hex
     * @throws \coding_exception when neither content nor contenthash was provided
     */
    public function resolve_contenthash(): string {
        if ($this->contenthash !== null && $this->contenthash !== '') {
            return $this->contenthash;
        }
        if ($this->content !== null) {
            return hash('sha256', $this->content);
        }
        throw new \coding_exception('record_request needs either content or contenthash');
    }
}
