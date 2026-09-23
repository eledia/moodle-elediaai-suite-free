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

use local_elediaai_sources\api_client;
use local_elediaai_sources\format_matrix;

/**
 * The external ingestion pipeline, reached over the documented HTTP API.
 *
 * The endpoints are fixed by docs/api-specification.md (v1.3): the service
 * exposes `POST /documents/upsert`, `POST /documents/delete` and `GET /health`
 * beneath a base URL, authenticated with an `X-API-Key` header issued per
 * tenant. The administrator therefore configures the base URL, not one action
 * URL from which the others used to be derived.
 *
 * The tenant itself is never configured: it is derived from the site's
 * wwwroot and travels in the payload, so the service can reject a request
 * whose tenant does not match the key.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ingestion_api_sink implements sink {
    /** @var api_client|null Injected transport; built per call when null. */
    private ?api_client $client;

    /**
     * Constructor.
     *
     * @param api_client|null $client Injected transport, for tests.
     */
    public function __construct(?api_client $client = null) {
        $this->client = $client;
    }

    #[\Override]
    public static function id(): string {
        return 'ingestionapi';
    }

    #[\Override]
    public static function name(): string {
        return get_string('sink_ingestionapi', 'local_elediaai_sources');
    }

    #[\Override]
    public function is_configured(): bool {
        return $this->baseurl() !== '' && $this->apikey() !== '';
    }

    #[\Override]
    public function healthcheck(): array {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'http_code' => 0,
                'response' => '',
                'error' => get_string('sink_ingestionapi_notconfigured', 'local_elediaai_sources'),
            ];
        }
        return $this->client()->get($this->baseurl() . '/health');
    }

    /**
     * The pipeline does not report its embedding model over the API.
     *
     * v1.2 has no field for it, so returning null keeps the stored value empty
     * rather than inventing one. A model change on the service side is
     * consequently invisible here and needs a manual reindex — recorded rather
     * than papered over.
     *
     * @return string|null Always null.
     */
    #[\Override]
    public function embedding_model(): ?string {
        return null;
    }

    /**
     * The core types plus whatever the service announces for itself.
     *
     * v1.3 of the specification adds `supported_content_types` to the /health
     * response. A service that does not carry the field — every v1.2
     * deployment — is taken at the contractual minimum, which is exactly what
     * it could parse before: nothing new is sent to a pipeline that never said
     * it could read it, and nothing that worked stops working.
     *
     * The answer is cached (see db/caches.php): this is asked once per
     * document, and a cron run has thousands of those.
     *
     * @return string[] MIME types, normalised.
     */
    #[\Override]
    public function supported_content_types(): array {
        $core = format_matrix::core_types();

        if (!$this->is_configured()) {
            return $core;
        }

        $cache = \cache::make('local_elediaai_sources', 'servicecapabilities');
        $key = sha1($this->baseurl());

        $cached = $cache->get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $types = array_values(array_unique(array_merge($core, $this->announced_content_types())));
        $cache->set($key, $types);

        return $types;
    }

    #[\Override]
    public function upsert(array $payload): array {
        return $this->client()->post($this->baseurl() . '/documents/upsert', $payload);
    }

    #[\Override]
    public function delete(string $sourceid, string $scope = 'exact'): array {
        return $this->client()->post($this->baseurl() . '/documents/delete', [
            'source_id' => $sourceid,
            'scope' => $scope,
        ]);
    }

    /**
     * The formats the service names on its health endpoint.
     *
     * Deliberately forgiving in one direction only: an unreachable service, a
     * body that is not JSON and a missing field all mean "announces nothing"
     * rather than an error, because a format question must never be the reason
     * an ingestion run fails. What the field *does* contain is normalised and
     * then still has to pass the support matrix — a type this plugin does not
     * know stays unexported no matter who offers to parse it.
     *
     * @return string[] MIME types, normalised; empty when nothing was announced.
     */
    private function announced_content_types(): array {
        $result = $this->client()->get($this->baseurl() . '/health');

        if (empty($result['success'])) {
            return [];
        }

        $decoded = json_decode((string) ($result['response'] ?? ''), true);
        if (!is_array($decoded) || !is_array($decoded['supported_content_types'] ?? null)) {
            return [];
        }

        $types = [];
        foreach ($decoded['supported_content_types'] as $type) {
            if (is_string($type) && trim($type) !== '') {
                $types[] = format_matrix::normalise($type);
            }
        }

        return $types;
    }

    /**
     * The configured service base URL, without a trailing slash.
     *
     * @return string
     */
    private function baseurl(): string {
        return rtrim(trim((string) get_config('local_elediaai_sources', 'sink_ingestionapi_baseurl')), '/');
    }

    /**
     * The tenant's API key, sent as X-API-Key.
     *
     * @return string
     */
    private function apikey(): string {
        return trim((string) get_config('local_elediaai_sources', 'sink_ingestionapi_apikey'));
    }

    /**
     * The transport, carrying the current key.
     *
     * @return api_client
     */
    private function client(): api_client {
        return $this->client ?? new api_client($this->apikey());
    }
}
