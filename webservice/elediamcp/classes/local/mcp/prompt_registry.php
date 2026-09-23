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

declare(strict_types=1);

namespace webservice_elediamcp\local\mcp;

use stdClass;
use webservice_elediamcp\local\security;
use webservice_elediamcp\local\tool_provider;

/**
 * Registry and availability filter for MCP prompts.
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prompt_registry {
    /** @var class-string<prompt>[]|null */
    private static ?array $contributedcache = null;

    /** @var class-string<prompt>[] PHPUnit-only injected prompts. */
    private static array $testprompts = [];

    /**
     * Return all built-in and contributed prompt classes.
     *
     * @return class-string<prompt>[]
     */
    public static function all(): array {
        // The built-in catalogue intentionally keeps related prompt classes in
        // one file; load it explicitly for Moodle's class autoloader.
        require_once(__DIR__ . '/prompts/catalog.php');
        $prompts = [
            prompts\kurs_aus_dokument::class,
            prompts\wochenueberblick::class,
            prompts\kurs_health_check::class,
            prompts\bewertungs_session::class,
            prompts\kursmaterial_zusammenfassen::class,
        ];
        $names = array_map(static fn(string $class): string => $class::name(), $prompts);
        foreach (self::contributed() as $class) {
            if (!in_array($class::name(), $names, true)) {
                $prompts[] = $class;
                $names[] = $class::name();
            }
        }
        return $prompts;
    }

    /**
     * Return prompt definitions visible with a token.
     *
     * @param string $token External service token.
     * @return array<int, array<string, mixed>>
     */
    public static function get_prompts(string $token): array {
        $availabletools = array_column(tool_provider::get_tools($token), 'name');
        $result = [];
        foreach (self::all() as $class) {
            if (array_diff($class::requires_tools(), $availabletools)) {
                continue;
            }
            $definition = [
                'name' => $class::name(),
                'title' => $class::title(),
                'description' => $class::description(),
            ];
            if ($class::arguments() !== []) {
                // The `type` key is an internal validation hint, not part of the MCP
                // prompts/list argument object defined by the protocol.
                $definition['arguments'] = array_map(static function (array $argument): array {
                    unset($argument['type']);
                    return $argument;
                }, $class::arguments());
            }
            $result[] = $definition;
        }
        return $result;
    }

    /**
     * Find a prompt only if it is available to the token.
     *
     * @param string $name Prompt name.
     * @param string $token External service token.
     * @return class-string<prompt>|null
     */
    public static function find(string $name, string $token): ?string {
        foreach (self::get_prompts($token) as $definition) {
            if ($definition['name'] !== $name) {
                continue;
            }
            foreach (self::all() as $class) {
                if ($class::name() === $name) {
                    return $class;
                }
            }
        }
        return null;
    }

    /**
     * Validate prompt arguments before rendering.
     *
     * @param class-string<prompt> $class Prompt class.
     * @param array<string, mixed> $arguments Arguments supplied by the client.
     * @return array<string, mixed>
     */
    public static function validate_arguments(string $class, array $arguments): array {
        $definitions = $class::arguments();
        $known = [];
        foreach ($definitions as $definition) {
            $name = (string) $definition['name'];
            $known[$name] = $definition;
            if (!empty($definition['required']) && !array_key_exists($name, $arguments)) {
                throw new \InvalidArgumentException(
                    get_string('err_prompt_missing_argument', 'webservice_elediamcp', $name)
                );
            }
        }
        foreach ($arguments as $name => $value) {
            if (!isset($known[$name])) {
                throw new \InvalidArgumentException(
                    get_string('err_prompt_unknown_argument', 'webservice_elediamcp', $name)
                );
            }
            $type = $known[$name]['type'] ?? 'string';
            if (
                ($type === 'integer' && filter_var($value, FILTER_VALIDATE_INT) === false)
                    || ($type === 'string' && !is_string($value))
            ) {
                throw new \InvalidArgumentException(
                    get_string('err_prompt_invalid_argument', 'webservice_elediamcp', $name)
                );
            }
        }
        return $arguments;
    }

    /**
     * Paginate arbitrary MCP catalogue entries using the shared cursor logic.
     *
     * @param array<int, array<string, mixed>> $items
     * @param string|null $cursor
     * @return array{items: array<int, array<string, mixed>>, nextCursor: string|null}
     */
    public static function paginate(array $items, ?string $cursor): array {
        return tool_provider::paginate_items($items, $cursor, security::tools_page_size());
    }

    /**
     * Return the prompt classes contributed by other plugins.
     *
     * @return class-string<prompt>[] Validated contributed prompt classes.
     */
    public static function contributed(): array {
        if (self::$contributedcache !== null) {
            return self::$contributedcache;
        }
        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST && self::$testprompts !== []) {
            return self::$contributedcache = self::validate_contributed(self::$testprompts, 'phpunit');
        }
        $classes = [];
        foreach (get_plugins_with_function('elediamcp_prompts', 'lib.php') as $plugins) {
            foreach ($plugins as $pluginname => $function) {
                try {
                    $entries = $function();
                } catch (\Throwable $exception) {
                    debugging("webservice_elediamcp: prompt provider '{$pluginname}' failed - "
                        . $exception->getMessage(), DEBUG_DEVELOPER);
                    continue;
                }
                if (is_array($entries)) {
                    $classes = array_merge($classes, self::validate_contributed($entries, (string) $pluginname));
                }
            }
        }
        return self::$contributedcache = $classes;
    }

    /**
     * Inject contributed prompts for PHPUnit runs.
     *
     * @param class-string<prompt>[] $classes Prompt classes to register.
     */
    public static function set_test_prompts(array $classes): void {
        if (!defined('PHPUNIT_TEST') || !PHPUNIT_TEST) {
            throw new \coding_exception('set_test_prompts() is only available under PHPUnit.');
        }
        self::$testprompts = $classes;
        self::$contributedcache = null;
    }

    /**
     * Drop contributed entries that are not usable prompt classes.
     *
     * @param array<int, mixed> $entries Raw callback return value.
     * @param string $provider Component that contributed the entries.
     * @return class-string<prompt>[] Validated prompt classes.
     */
    private static function validate_contributed(array $entries, string $provider): array {
        $valid = [];
        foreach ($entries as $entry) {
            if (!is_string($entry) || !class_exists($entry) || !is_subclass_of($entry, prompt::class)) {
                debugging("webservice_elediamcp: invalid prompt from '{$provider}'", DEBUG_DEVELOPER);
                continue;
            }
            if (!preg_match('/^[a-z][a-z0-9_]*$/', $entry::name())) {
                debugging("webservice_elediamcp: malformed prompt from '{$provider}'", DEBUG_DEVELOPER);
                continue;
            }
            $valid[] = $entry;
        }
        return $valid;
    }
}
