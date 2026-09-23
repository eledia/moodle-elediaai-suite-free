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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Tests for MCP prompt registration, validation and rendering.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace webservice_elediamcp;

use externallib_advanced_testcase;
use stdClass;
use webservice_elediamcp\local\mcp\prompt_registry;
use webservice_elediamcp\local\mcp\prompts\wochenueberblick;
use webservice_elediamcp\tests\fixtures\contributed_prompt_fixture;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for MCP prompt registration, validation and rendering.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_elediamcp\local\mcp\prompt_registry
 * @covers      \webservice_elediamcp\local\mcp\prompts\catalog
 */
final class prompts_test extends externallib_advanced_testcase {
    /**
     * A built-in prompt satisfies the prompt contract and renders a user message.
     */
    public function test_builtin_prompt_contract(): void {
        $this->resetAfterTest(true);
        $this->assertSame('wochenueberblick', wochenueberblick::name());
        $this->assertSame([], wochenueberblick::arguments());
        $this->assertNotEmpty(wochenueberblick::requires_tools());
        $messages = wochenueberblick::render([], new stdClass());
        $this->assertSame('user', $messages[0]['role']);
        $this->assertSame('text', $messages[0]['content']['type']);
    }

    /**
     * A missing required argument is rejected.
     */
    public function test_missing_argument_is_rejected(): void {
        $this->resetAfterTest(true);
        $class = \webservice_elediamcp\local\mcp\prompts\kurs_health_check::class;
        $this->expectException(\InvalidArgumentException::class);
        prompt_registry::validate_arguments($class, []);
    }

    /**
     * An argument outside the declared set is rejected by name.
     */
    public function test_unknown_argument_is_rejected(): void {
        $this->resetAfterTest(true);
        $class = \webservice_elediamcp\local\mcp\prompts\kurs_health_check::class;
        try {
            prompt_registry::validate_arguments($class, ['kurs_id' => 1, 'extra' => true]);
            $this->fail('Expected an unknown argument to be rejected.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('extra', $exception->getMessage());
        }
    }

    /**
     * A page that fits in one response reports no next cursor.
     */
    public function test_prompt_cursor_pagination(): void {
        $this->resetAfterTest(true);
        $items = [['name' => 'one'], ['name' => 'two'], ['name' => 'three']];
        $page = prompt_registry::paginate($items, null);
        $this->assertSame($items, $page['items']);
        $this->assertNull($page['nextCursor']);
    }

    /**
     * A contributed prompt passes validation and shows up in the registry.
     */
    public function test_contributed_prompt_is_validated_and_discovered(): void {
        $this->resetAfterTest(true);
        require_once(__DIR__ . '/fixtures/contributed_prompt_fixture.php');
        prompt_registry::set_test_prompts([contributed_prompt_fixture::class]);
        $names = array_map(static fn(string $class): string => $class::name(), prompt_registry::all());
        $this->assertContains('fixture_prompt', $names);
    }
}
