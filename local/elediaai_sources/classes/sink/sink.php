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

namespace local_elediaai_sources\sink;

/**
 * A destination that course documents can be pushed to.
 *
 * The plugin used to work out its destination by matching the configured URL
 * against a list of shapes: an external `/documents/upsert` endpoint looked
 * different from a local `ingest.php` route, so the shape stood in for the
 * choice. That breaks as soon as a destination uses a URL the list does not
 * anticipate. A destination is now chosen explicitly and knows its own
 * endpoints, its own authentication and the embedding model it indexes with.
 *
 * Exactly one destination is active at a time; switching is supported, running
 * two in parallel is not.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface sink {
    /**
     * Short identifier used in settings and stored per course.
     *
     * Stored alongside each ingested course, so a change of destination makes
     * every course diverge and be re-ingested. Keep it stable across releases.
     *
     * @return string A frankenstyle-safe identifier, e.g. 'literag'.
     */
    public static function id(): string;

    /**
     * Human-readable name for the settings page.
     *
     * @return string Translated name.
     */
    public static function name(): string;

    /**
     * Whether this destination has everything it needs to be used.
     *
     * @return bool True when configured.
     */
    public function is_configured(): bool;

    /**
     * Ask the destination whether it is reachable and healthy.
     *
     * @return array Result row as returned by {@see \local_elediaai_sources\api_client}:
     *               'success' (bool), 'http_code' (int), 'response' (string), 'error' (string).
     */
    public function healthcheck(): array;

    /**
     * The embedding model this destination indexes with.
     *
     * Carried in the upsert metadata and stored per course, so that a model
     * change is detected and triggers a re-index instead of leaving a corpus
     * mixed across two models.
     *
     * @return string|null Model identifier, or null when none is exposed.
     */
    public function embedding_model(): ?string;

    /**
     * The MIME types this destination can actually parse.
     *
     * Asked before a document is built, so a format Moodle could export but
     * the destination could not read is skipped with a reason instead of
     * being accepted into a queue and failing out of sight. The three core
     * types of the support matrix are the contractual minimum; anything
     * beyond them is what this destination announces for itself.
     *
     * @return string[] MIME types, normalised (lowercase, no parameters).
     */
    public function supported_content_types(): array;

    /**
     * Send documents to the destination.
     *
     * @param array $payload Documents as defined by docs/api-specification.md.
     * @return array Result row as returned by {@see \local_elediaai_sources\api_client}:
     *               'success' (bool), 'http_code' (int), 'response' (string), 'error' (string).
     */
    public function upsert(array $payload): array;

    /**
     * Remove documents from the destination.
     *
     * @param string $sourceid The source identifier to remove.
     * @param string $scope Either 'exact' for one document or 'prefix' for everything beneath it.
     * @return array Result row as returned by {@see \local_elediaai_sources\api_client}:
     *               'success' (bool), 'http_code' (int), 'response' (string), 'error' (string).
     */
    public function delete(string $sourceid, string $scope = 'exact'): array;
}
