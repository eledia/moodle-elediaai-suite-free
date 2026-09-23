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
 * Marking AI output, visibly and machine-readably.
 *
 * @package    local_aitransparency
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_aitransparency;

use html_writer;
use moodle_url;

/**
 * The two halves of Art. 50, and they are not the same duty.
 *
 * **Abs. 1 is for the reader**: a person interacting with an AI has to be able
 * to tell. That is {@see notice()} -- plain words, next to the output, no lookup
 * required.
 *
 * **Abs. 2 is for the machine**: synthetic output has to be marked in a way a
 * program can detect. That is {@see wrap_text()}. There is no normed standard
 * for marking *text*, so this follows the interpretation recorded in
 * `docs/offene-rechtsfragen.md` (R-01, still open with legal): the IPTC
 * `digitalSourceType` vocabulary term `trainedAlgorithmicMedia` as a data
 * attribute, plus the provenance record's UUID so the claim is resolvable
 * rather than merely asserted.
 *
 * Both are no-ops without a provenance record: a marker that points nowhere
 * would be worse than none, because it looks like evidence.
 */
final class marker {
    /** @var string IPTC digitalSourceType term for output created by a generative model. */
    public const SOURCE_TYPE = 'trainedAlgorithmicMedia';

    /** @var string CSS class the filter and the stylesheet look for. */
    public const CSS_CLASS = 'aitransparency-marked';

    /**
     * Static-only.
     */
    private function __construct() {
    }

    /**
     * Wrap AI-generated HTML so a machine can recognise it.
     *
     * The content is passed through untouched -- this adds a wrapper, it does
     * not sanitise. Callers hand over HTML they have already cleaned.
     *
     * @param string $html The generated output, already safe to render.
     * @param string $uuid The provenance record's UUID.
     * @return string
     */
    public static function wrap_text(string $html, string $uuid): string {
        $uuid = trim($uuid);
        if ($uuid === '') {
            return $html;
        }

        return html_writer::tag('span', $html, [
            'class' => self::CSS_CLASS,
            'data-ai-generated' => 'true',
            'data-ai-digital-source-type' => self::SOURCE_TYPE,
            'data-ai-record' => $uuid,
        ]);
    }

    /**
     * The visible hint that a reader is looking at AI output.
     *
     * @param string|null $uuid Provenance UUID; when given, the notice links to it.
     * @param string|null $provider Optional provider label for the sentence.
     * @return string
     */
    public static function notice(?string $uuid = null, ?string $provider = null): string {
        $label = $provider !== null && trim($provider) !== ''
            ? get_string('notice_with_provider', 'local_aitransparency', s(trim($provider)))
            : get_string('notice', 'local_aitransparency');

        $body = html_writer::tag('span', $label, ['class' => 'aitransparency-notice__text']);

        if ($uuid !== null && trim($uuid) !== '') {
            $body .= html_writer::link(
                self::verify_url(trim($uuid)),
                get_string('notice_verify', 'local_aitransparency'),
                ['class' => 'aitransparency-notice__link']
            );
        }

        return html_writer::div($body, 'aitransparency-notice', ['role' => 'note']);
    }

    /**
     * The readable name behind a stored provider value.
     *
     * A chat turn records the id of its backend, not an AI provider -- the
     * chat never calls a model itself. Shown raw that reads „ingestionapi".
     * The chat engine owns the list of backends and is asked for the name;
     * it is a soft dependency, so anything it does not know stays as it is.
     *
     * @param string $provider The stored value.
     * @return string
     */
    public static function provider_label(string $provider): string {
        $resolver = '\\local_elediaai_chatengine\\backend_resolver';
        if ($provider === '' || !class_exists($resolver)) {
            return $provider;
        }

        return $resolver::display_name($provider);
    }

    /**
     * A readable name for a generating component, asked from that plugin.
     *
     * The suite does not hold a table of plugin names (06.09.2026); it asks the
     * plugin for its own. A component whose plugin is gone keeps its frankenstyle
     * name -- a record that was written does not stop existing because the
     * plugin was removed.
     *
     * @param string $component Frankenstyle name.
     * @return string
     */
    public static function component_label(string $component): string {
        if (trim($component) === '') {
            return get_string('verify_component_unknown', 'local_aitransparency');
        }
        if (get_string_manager()->string_exists('pluginname', $component)) {
            return get_string('pluginname', $component);
        }
        return $component;
    }

    /**
     * Where a UUID resolves.
     *
     * @param string $uuid
     * @return moodle_url
     */
    public static function verify_url(string $uuid): moodle_url {
        return new moodle_url('/local/aitransparency/verify.php', ['uuid' => $uuid]);
    }
}
