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

namespace local_elediaai_sources;

/**
 * Unit tests for the HTTP transport.
 *
 * The client no longer knows any endpoint: a sink passes the URL in. What is
 * left to test is the transport behaviour itself — result shape, the local
 * loopback rewrite, and that the rewrite does not weaken cURL security.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\api_client
 */
final class api_client_test extends \advanced_testcase {
    /**
     * A successful POST reports success and the status code.
     */
    public function test_post_success_with_mock(): void {
        $this->resetAfterTest();

        \curl::mock_response('{"status": "ok"}');

        $client = new api_client('test-key');
        $result = $client->post('http://localhost:8001/documents/upsert', [
            'source_id' => 'test:course1:cmid1',
            'content' => base64_encode('Hello'),
            'content_type' => 'text/plain',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['http_code']);
    }

    /**
     * A successful GET reports success — used for health probes.
     */
    public function test_get_success_with_mock(): void {
        $this->resetAfterTest();

        \curl::mock_response('{"status": "ok"}');

        $client = new api_client('test-key');
        $result = $client->get('http://localhost:8001/health');

        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['http_code']);
    }

    /**
     * A URL under a loopback wwwroot is routed through the Docker host while
     * the site's own Host header is preserved.
     */
    public function test_loopback_target_is_rewritten(): void {
        global $CFG;
        $this->resetAfterTest();

        $CFG->wwwroot = 'http://localhost:8080';

        $target = $this->resolve_target(new api_client('k'), 'http://localhost:8080/local/literag/ingest.php?action=upsert');

        $this->assertSame(
            'http://host.docker.internal:8080/local/literag/ingest.php?action=upsert',
            $target['url']
        );
        $this->assertSame(['Host: localhost:8080'], $target['headers']);
    }

    /**
     * A URL outside the site's wwwroot is left untouched.
     */
    public function test_external_target_is_left_alone(): void {
        global $CFG;
        $this->resetAfterTest();

        $CFG->wwwroot = 'http://localhost:8080';

        $target = $this->resolve_target(new api_client('k'), 'http://rag-service:8001/documents/upsert');

        $this->assertSame('http://rag-service:8001/documents/upsert', $target['url']);
        $this->assertSame([], $target['headers']);
    }

    /**
     * The rewrite must not bypass Moodle's cURL blocklist on its own: without
     * the allow_private_target opt-in, private targets stay disallowed.
     */
    public function test_rewrite_keeps_curl_security_by_default(): void {
        $this->resetAfterTest();

        unset_config('allow_private_target', 'local_elediaai_sources');

        $client = new api_client('k');

        $property = new \ReflectionProperty(api_client::class, 'allowprivatetarget');
        $property->setAccessible(true);
        $this->assertFalse($property->getValue($client));
    }

    /**
     * With the opt-in enabled, private targets stay reachable.
     */
    public function test_rewrite_respects_private_target_opt_in(): void {
        $this->resetAfterTest();

        set_config('allow_private_target', 1, 'local_elediaai_sources');

        $client = new api_client('k');

        $property = new \ReflectionProperty(api_client::class, 'allowprivatetarget');
        $property->setAccessible(true);
        $this->assertTrue($property->getValue($client));
    }

    /**
     * Call the private target resolver.
     *
     * @param api_client $client The client instance.
     * @param string $url The URL a sink asked for.
     * @return array Keys 'url' and 'headers'.
     */
    private function resolve_target(api_client $client, string $url): array {
        $method = new \ReflectionMethod(api_client::class, 'resolve_target');
        $method->setAccessible(true);
        return $method->invoke($client, $url);
    }
}
