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

declare(strict_types=1);

namespace local_elediaai_chatengine\local;

use core_external\external_value;

/**
 * The hints a client may ask a turn to carry.
 *
 * A placement's interface knows things the shared endpoint cannot infer: which
 * answer style a learner picked, that a starter question is meant as an action
 * rather than a lookup, that a confirmation card was answered. They travel as
 * hints because that is what they are — a backend may honour or ignore any of
 * them, and a turn without them is a complete turn.
 *
 * What a client sends is a **wish**, never a decision. Every name and every
 * value is checked against the vocabulary below, and what survives is offered
 * to the placement, which has the final word — see
 * {@see \local_elediaai_chatengine\chat_service::send()}. That is the same rule
 * the mode already follows: a client that posts something it may not have is
 * not refused, it is simply not asked.
 *
 * The vocabulary is the single place both entry points read: the external
 * function builds its parameters from it, `stream.php` reads the same names,
 * and both sanitise through the same method, so the buffered and the streamed
 * path cannot come to accept different things.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hints {
    /**
     * The type every hint is read as.
     *
     * A hint is a short identifier, never prose: the values are enumerated
     * below and anything else is dropped. The parameter type is therefore only
     * the first filter, not the check that matters.
     *
     * @var string
     */
    public const PARAM_TYPE = PARAM_ALPHANUMEXT;

    /**
     * Every hint a client may ask for, with the values it may carry.
     *
     * The values mirror the backend contract (`rag_server_spec` A.1), not one
     * placement's interface: a name here is something a backend understands.
     *
     * @var array<string, array{values: string[], description: string}>
     */
    private const VOCABULARY = [
        'answerstyle' => [
            'values' => ['explain', 'hint', 'quiz'],
            'description' => 'Pedagogical answer style asked for; the placement decides whether to honour it',
        ],
        'intent' => [
            'values' => ['auto', 'action', 'knowledge'],
            'description' => 'Routing hint: action skips retrieval, knowledge skips Moodle tools, auto leaves it to the backend',
        ],
        'pendingdecision' => [
            'values' => ['confirm', 'decline'],
            'description' => 'Answers the write action a confirmation card offered',
        ],
    ];

    /**
     * The hint names, in the order both entry points declare them.
     *
     * @return string[] The names.
     */
    public static function names(): array {
        return array_keys(self::VOCABULARY);
    }

    /**
     * The values a hint may carry.
     *
     * @param string $name The hint name.
     * @return string[] The accepted values, empty for an unknown hint.
     */
    public static function values(string $name): array {
        return self::VOCABULARY[$name]['values'] ?? [];
    }

    /**
     * Keep what the vocabulary knows and drop the rest.
     *
     * Silently: a hint is optional by definition, so an older client sending a
     * name this release retired, or a newer one sending a value it does not
     * yet support, still gets its answer. Refusing the turn would trade a
     * missing nuance for no answer at all.
     *
     * @param array $requested Hint name => value, as the client asked.
     * @return array<string, string> The hints that survived, empty when none did.
     */
    public static function sanitise(array $requested): array {
        $accepted = [];
        foreach (self::VOCABULARY as $name => $hint) {
            $value = $requested[$name] ?? null;
            if (is_string($value) && in_array($value, $hint['values'], true)) {
                $accepted[$name] = $value;
            }
        }

        return $accepted;
    }

    /**
     * The hints as external function parameters, all optional.
     *
     * Merged into an endpoint's own parameters. The default is the empty
     * string throughout, which {@see self::sanitise()} drops — so a placement
     * that sends no hints reaches the backend with exactly what it did before.
     *
     * @return array<string, external_value> Parameter name => definition.
     */
    public static function parameters(): array {
        $parameters = [];
        foreach (self::VOCABULARY as $name => $hint) {
            $parameters[$name] = new external_value(
                self::PARAM_TYPE,
                $hint['description'] . ' (' . implode('|', $hint['values']) . ')',
                VALUE_DEFAULT,
                ''
            );
        }

        return $parameters;
    }
}
