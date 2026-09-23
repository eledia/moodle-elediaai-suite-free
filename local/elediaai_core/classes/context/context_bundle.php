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
 * Context bundle DTO.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\context;

defined('MOODLE_INTERNAL') || die();

use coding_exception;

/**
 * Ordered bundle of context items with deterministic combined text and hash.
 */
final class context_bundle {
    /** @var context_item[] */
    public readonly array $items;

    /** @var string Combined plain text representation of all items. */
    /** @var string Constructor-promoted value. */
    public readonly string $combinedtext;

    /** @var string Stable SHA-256 fingerprint of bundle-relevant content. */
    /** @var string Constructor-promoted value. */
    public readonly string $fingerprint;

    /**
     * Create an ordered context bundle.
     *
     * @param context_item[] $items Ordered items to include.
     * @param string $summary Optional short bundle summary.
     * @param int|null $targetcontextid Moodle context id the AI action targets.
     */
    public function __construct(
        array $items,
        /** @var string Constructor-promoted value. */
        public readonly string $summary = '',
        /** @var ?int Constructor-promoted value. */
        public readonly ?int $targetcontextid = null,
    ) {
        foreach ($items as $item) {
            if (!$item instanceof context_item) {
                throw new coding_exception('context_bundle items must be context_item instances.');
            }
        }

        $this->items = array_values($items);
        $this->combinedtext = self::build_combined_text($this->items);
        $this->fingerprint = self::build_fingerprint($this->items, $this->summary, $this->targetcontextid);
    }

    /**
     * Return a stable array representation for transport and hashing.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'items' => array_map(static fn(context_item $item): array => $item->to_array(), $this->items),
            'combinedtext' => $this->combinedtext,
            'summary' => $this->summary,
            'fingerprint' => $this->fingerprint,
            'targetcontextid' => $this->targetcontextid,
        ];
    }

    /**
     * Build a readable prompt grounding text from all non-empty items.
     *
     * @param context_item[] $items
     * @return string
     */
    private static function build_combined_text(array $items): string {
        $parts = [];
        foreach ($items as $item) {
            $text = trim($item->text);
            if ($text === '') {
                continue;
            }

            $title = trim($item->title);
            if ($title !== '') {
                $parts[] = '## ' . $title . "\n\n" . $text;
            } else {
                $parts[] = $text;
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * Build a stable fingerprint across PHP array insertion-order variants.
     *
     * @param context_item[] $items
     * @param string $summary
     * @param int|null $targetcontextid
     * @return string
     */
    private static function build_fingerprint(array $items, string $summary, ?int $targetcontextid): string {
        $payload = [
            'items' => array_map(static fn(context_item $item): array => self::normalise($item->to_array()), $items),
            'summary' => $summary,
            'targetcontextid' => $targetcontextid,
        ];

        return hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Sort associative keys recursively while preserving list order.
     *
     * @param mixed $value
     * @return mixed
     */
    private static function normalise($value) {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(static fn($child) => self::normalise($child), $value);
        }

        ksort($value);
        foreach ($value as $key => $child) {
            $value[$key] = self::normalise($child);
        }

        return $value;
    }
}
