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
use local_elediaai_sources\tenant;

/**
 * The external ingestion pipeline, reached over the documented HTTP API.
 *
 * The endpoints are fixed by docs/api-specification.md (v1.6): the service
 * exposes `POST /documents/upsert`, `POST /documents/delete`,
 * `POST /documents/status/batch` and `GET /health` beneath a base URL, authenticated with an `X-API-Key` header issued per
 * tenant. The administrator therefore configures the base URL, not one action
 * URL from which the others used to be derived.
 *
 * The upsert is asynchronous: `202 Accepted` means the job is stored, not
 * that the document is indexed. The status of the stored jobs is read back
 * through {@see document_status()} (ai_suite#232).
 *
 * The tenant itself is never configured: it is derived from the site's
 * wwwroot and travels in the payload, so the service can reject a request
 * whose tenant does not match the key.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ingestion_api_sink implements sink, status_reporting_sink {
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

    /**
     * Delete a document (or a prefix of them) for this site's tenant only.
     *
     * `tenant_id` binds the delete to the tenant (ingestionpipeline#39,
     * ai_suite#231): with it the service checks the tenant (403/503) and the
     * source_id prefix (422), and removes only this tenant's chunks. Without
     * it a prefix delete would still reach across tenants.
     *
     * The tenant is taken from the current wwwroot, like on upsert. A
     * source_id stored before a wwwroot change still carries the old prefix;
     * the service then answers 422 and the delete is reported as failed
     * instead of silently touching another tenant's data.
     *
     * @param string $sourceid The source_id to delete.
     * @param string $scope 'exact' or 'prefix'.
     * @return array The transport result row.
     */
    #[\Override]
    public function delete(string $sourceid, string $scope = 'exact'): array {
        return $this->client()->post($this->baseurl() . '/documents/delete', [
            'source_id' => $sourceid,
            'scope' => $scope,
            'tenant_id' => tenant::id(),
        ]);
    }

    /**
     * The stored processing status of up to 500 documents of this tenant.
     *
     * `POST /documents/status/batch` with `tenant_id` and the source ids. The
     * service keeps order and duplicates and answers ids it has no history
     * for as `unknown`; an id missing from the answer is therefore not
     * "unknown" but a broken answer, and the whole answer counts as
     * unavailable. Every API error (401, 403, 422, 429, 5xx, transport) means
     * "status not available" (README of the pipeline, section Dokumentstatus).
     *
     * @param string[] $sourceids The source ids to ask about.
     * @return array Normalised answer, see {@see status_reporting_sink::document_status()}.
     * @throws \coding_exception When more than MAX_BATCH ids are passed.
     */
    #[\Override]
    public function document_status(array $sourceids): array {
        $sourceids = array_values(array_unique(array_map('strval', $sourceids)));
        if (count($sourceids) > self::MAX_BATCH) {
            throw new \coding_exception('At most ' . self::MAX_BATCH . ' source ids per status request.');
        }

        $answer = [
            'available' => false,
            'documents' => [],
            'retryafter' => null,
            'http_code' => 0,
            'error' => '',
        ];

        if ($sourceids === []) {
            $answer['available'] = true;
            return $answer;
        }

        if (!$this->is_configured()) {
            $answer['error'] = get_string('sink_ingestionapi_notconfigured', 'local_elediaai_sources');
            return $answer;
        }

        $result = $this->client()->post($this->baseurl() . '/documents/status/batch', [
            'tenant_id' => tenant::id(),
            'source_ids' => $sourceids,
        ]);

        $answer['http_code'] = (int) ($result['http_code'] ?? 0);
        $answer['retryafter'] = self::retry_after($result['headers'] ?? []);

        if (empty($result['success'])) {
            $error = trim((string) ($result['error'] ?? ''));
            $answer['error'] = $error !== '' ? $error : 'HTTP ' . $answer['http_code'];
            return $answer;
        }

        $decoded = json_decode((string) ($result['response'] ?? ''), true);
        if (!is_array($decoded) || !is_array($decoded['documents'] ?? null)) {
            $answer['error'] = get_string('statuscheck_invalidanswer', 'local_elediaai_sources');
            return $answer;
        }

        $documents = [];
        foreach ($decoded['documents'] as $row) {
            if (!is_array($row) || !is_string($row['source_id'] ?? null)) {
                continue;
            }
            $documents[$row['source_id']] = self::normalise_status_row($row);
        }

        foreach ($sourceids as $sourceid) {
            if (!isset($documents[$sourceid])) {
                $answer['error'] = get_string('statuscheck_incompleteanswer', 'local_elediaai_sources');
                return $answer;
            }
        }

        $answer['available'] = true;
        $answer['documents'] = array_intersect_key($documents, array_flip($sourceids));
        return $answer;
    }

    /**
     * One status entry in the shape the plugin works with.
     *
     * @param array $row The entry as decoded from the answer.
     * @return array The normalised entry.
     */
    private static function normalise_status_row(array $row): array {
        $text = static function ($value): ?string {
            return is_scalar($value) && trim((string) $value) !== '' ? (string) $value : null;
        };

        return [
            'source_id' => (string) $row['source_id'],
            'status' => strtolower((string) ($text($row['status'] ?? null) ?? 'unknown')),
            'last_job_id' => $text($row['last_job_id'] ?? null),
            'error_type' => $text($row['error_type'] ?? null),
            'error_message' => $text($row['error_message'] ?? null),
            'attempt_count' => (int) ($row['attempt_count'] ?? 0),
            'max_attempts' => (int) ($row['max_attempts'] ?? 0),
            'next_retry_at' => self::timestamp($row['next_retry_at'] ?? null),
            'last_success_at' => self::timestamp($row['last_success_at'] ?? null),
            'updated_at' => self::timestamp($row['updated_at'] ?? null),
        ];
    }

    /**
     * An ISO 8601 time as Unix timestamp; 0 for anything unreadable.
     *
     * @param mixed $value The value from the answer.
     * @return int
     */
    private static function timestamp($value): int {
        if (!is_string($value) || trim($value) === '') {
            return 0;
        }
        try {
            return (new \DateTimeImmutable($value))->getTimestamp();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Seconds from a Retry-After header, in either of its two forms.
     *
     * @param array $headers Response headers with lowercase names.
     * @return int|null Seconds, or null without a usable header.
     */
    private static function retry_after(array $headers): ?int {
        $value = trim((string) ($headers['retry-after'] ?? ''));
        if ($value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            return (int) $value;
        }
        $time = strtotime($value);
        return $time === false ? null : max(0, $time - time());
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
     * Stored encrypted with the site key (admin_setting_encryptedpassword,
     * ai_suite#252). A value without the method prefix is a plaintext key from
     * before the upgrade and is used as it is, so delivery does not stop
     * between code and upgrade. A value that cannot be decrypted (site cloned
     * without the key in moodledata) fails closed: the destination then counts
     * as not configured instead of sending ciphertext as a credential.
     *
     * @return string The plaintext key, or '' when unset or undecryptable.
     */
    private function apikey(): string {
        $stored = trim((string) get_config('local_elediaai_sources', 'sink_ingestionapi_apikey'));
        if ($stored === '' || !str_starts_with($stored, \core\encryption::METHOD_SODIUM . ':')) {
            return $stored;
        }
        try {
            return trim(\core\encryption::decrypt($stored));
        } catch (\moodle_exception $e) {
            debugging('local_elediaai_sources: the Ingestion API key could not be decrypted', DEBUG_DEVELOPER);
            return '';
        }
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
