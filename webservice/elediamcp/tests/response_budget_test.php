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

namespace webservice_elediamcp;

use advanced_testcase;
use webservice_elediamcp\form\configuration_form;
use webservice_elediamcp\local\ai\response_budget;

/**
 * Tests for the character budget of list tool answers.
 *
 * The expected lengths were computed with Python 3:
 * len(json.dumps(json.loads(wire), ensure_ascii=False, indent=2, sort_keys=True)),
 * where wire is the JSON the server sends for the same payload, and the same
 * inside the envelope: json.dumps({"result": json.loads(wire)}, ...).
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_elediamcp\local\ai\response_budget
 * @covers      \webservice_elediamcp\form\configuration_form
 */
final class response_budget_test extends advanced_testcase {
    /** @var int Python length of {@see self::sample_simple()}. */
    private const PYTHON_SIMPLE = 302;

    /** @var int Python length of {@see self::sample_simple()} inside {"result": ...}. */
    private const PYTHON_SIMPLE_ENVELOPE = 354;

    /** @var int Python length of {@see self::sample_nested()}. */
    private const PYTHON_NESTED = 235;

    /** @var int Python length of {@see self::sample_nested()} inside {"result": ...}. */
    private const PYTHON_NESTED_ENVELOPE = 285;

    /** @var int Python length of {@see self::sample_numbers()}. */
    private const PYTHON_NUMBERS = 226;

    /** @var int Python length of {@see self::sample_numbers()} inside {"result": ...}. */
    private const PYTHON_NUMBERS_ENVELOPE = 282;

    /**
     * List answer with umlauts, a slash, escapes, null and an integral float.
     *
     * @return array
     */
    private static function sample_simple(): array {
        return [
            'total' => 2,
            'has_more' => false,
            'next_offset' => null,
            'summary' => 'Found 2 items.',
            'items' => [
                ['id' => 1, 'name' => 'Größe / Übung', 'grade' => 80.0, 'ratio' => 0.5],
                ['id' => 2, 'name' => "Line\nbreak \"quoted\" \\ tab\t", 'grade' => null],
            ],
        ];
    }

    /**
     * Nested structure: empty list and object, numeric keys, emoji, line separator, control character.
     *
     * @return array
     */
    private static function sample_nested(): array {
        return [
            'b' => [],
            'a' => (object) [],
            'c' => ['z' => [1, [2, []]], 'y' => true, '10' => 'ten', '9' => 'nine'],
            'emoji' => "😀 \u{2028} ctrl\x01",
            'url' => 'https://example.org/mod/quiz/view.php?id=5',
        ];
    }

    /**
     * Floats and integers in the forms Python writes differently from PHP.
     *
     * @return array
     */
    private static function sample_numbers(): array {
        return [
            'f' => [12.5, 1.0E+15, 1.0E+16, 0.0001, 0.00001, -3.25, 1 / 3, 123456789.125, 0.0, -0.0, 2.5e-10, 7.0e20],
            'i' => [0, -7, 1234567],
        ];
    }

    /**
     * measure() gives the same length as the client's Python serialisation.
     */
    public function test_measure_matches_python(): void {
        $this->assertSame(self::PYTHON_SIMPLE, response_budget::measure(self::sample_simple(), false));
        $this->assertSame(self::PYTHON_NESTED, response_budget::measure(self::sample_nested(), false));
        $this->assertSame(self::PYTHON_NUMBERS, response_budget::measure(self::sample_numbers(), false));
    }

    /**
     * By default measure() counts the {"result": ...} envelope of protocol 2025-03-26, which is longer.
     */
    public function test_measure_counts_envelope(): void {
        $this->assertSame(self::PYTHON_SIMPLE_ENVELOPE, response_budget::measure(self::sample_simple()));
        $this->assertSame(self::PYTHON_NESTED_ENVELOPE, response_budget::measure(self::sample_nested()));
        $this->assertSame(self::PYTHON_NUMBERS_ENVELOPE, response_budget::measure(self::sample_numbers()));
        $this->assertSame(self::PYTHON_SIMPLE_ENVELOPE, response_budget::measure(self::sample_simple(), true));
        // Python: 46 with envelope, 22 without.
        $this->assertSame(46, response_budget::measure(['a' => [1]]));
        foreach ([self::sample_simple(), self::sample_nested(), self::sample_numbers()] as $sample) {
            $this->assertGreaterThan(response_budget::measure($sample, false), response_budget::measure($sample));
        }
    }

    /**
     * A payload with invalid UTF-8 is one the server cannot send: it never counts as fitting.
     */
    public function test_measure_invalid_utf8_like_server(): void {
        $payload = ['a' => "ab\xffcd"];
        // Same flags as server::safe_json_encode(): the server fails on this payload.
        $this->assertFalse(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->assertSame(PHP_INT_MAX, response_budget::measure($payload));
        $this->assertSame(PHP_INT_MAX, response_budget::measure($payload, false));

        $entries = [['n' => 1], ['n' => 2], ['n' => "bad\xff"], ['n' => 4]];
        $first = response_budget::fit_page($entries, self::builder(4), 12000);
        $this->assertSame([['n' => 1], ['n' => 2]], $first['entries']);
        $bad = response_budget::fit_page(array_slice($entries, 2), self::builder(2), 12000);
        $this->assertCount(1, $bad['entries']);
    }

    /**
     * measure() indents by two spaces and counts characters, not bytes.
     */
    public function test_measure_small_cases(): void {
        // Five lines in Python: opening brace, key with opening bracket, the number, closing bracket, closing brace.
        $this->assertSame(22, response_budget::measure(['a' => [1]], false));
        // Four characters, eight bytes in UTF-8.
        $this->assertSame(\core_text::strlen("{\n  \"k\": \"äöüß\"\n}"), response_budget::measure(['k' => 'äöüß'], false));
        $this->assertSame(17, response_budget::measure(['k' => 'äöüß'], false));
        $this->assertSame(37, response_budget::measure(['k' => 'äöüß']));
    }

    /**
     * Data provider for max_chars().
     *
     * @return array
     */
    public static function max_chars_provider(): array {
        return [
            'unset' => [null, 12000],
            'in range' => ['5000', 5000],
            'lowest' => ['2000', 2000],
            'highest' => ['100000', 100000],
            'too small' => ['500', 2000],
            'zero' => ['0', 2000],
            'negative' => ['-10', 2000],
            'too large' => ['500000', 100000],
            'not a number' => ['abc', 12000],
            'empty' => ['', 12000],
        ];
    }

    /**
     * max_chars() uses the default for missing or invalid values and clamps the rest.
     *
     * @dataProvider max_chars_provider
     * @param string|null $config Stored setting, null for none.
     * @param int $expected Expected limit.
     */
    public function test_max_chars(?string $config, int $expected): void {
        $this->resetAfterTest();
        if ($config !== null) {
            set_config('response_max_chars', $config, 'webservice_elediamcp');
        }
        $this->assertSame($expected, response_budget::max_chars());
    }

    /**
     * Build function for a simple list answer.
     *
     * @param int $total Number of all entries.
     * @return callable
     */
    private static function builder(int $total): callable {
        return static function (array $page) use ($total): array {
            $paging = response_budget::paging(0, count($page), $total);
            return [
                'entries' => $page,
                'total' => $total,
                'has_more' => $paging['has_more'],
                'next_offset' => $paging['next_offset'],
                'summary' => response_budget::summary('Found entries.', $paging['has_more']),
            ];
        };
    }

    /**
     * Entries of about 300 characters each.
     *
     * @param int $count Number of entries.
     * @return array
     */
    private static function entries(int $count): array {
        $entries = [];
        for ($i = 0; $i < $count; $i++) {
            $entries[] = ['id' => $i, 'text' => str_repeat('x', 280)];
        }
        return $entries;
    }

    /**
     * fit_page() returns the largest page that stays within 95 percent of the limit.
     */
    public function test_fit_page_takes_largest_fitting_page(): void {
        $entries = self::entries(40);
        $build = self::builder(40);
        $answer = response_budget::fit_page($entries, $build, 5000);

        $count = count($answer['entries']);
        $this->assertGreaterThan(1, $count);
        $this->assertLessThan(40, $count);
        $this->assertLessThanOrEqual(4750, response_budget::measure($answer));
        // One entry more would not fit.
        $this->assertGreaterThan(4750, response_budget::measure($build(array_slice($entries, 0, $count + 1))));
        $this->assertSame(array_slice($entries, 0, $count), $answer['entries']);
        $this->assertTrue($answer['has_more']);
        $this->assertSame($count, $answer['next_offset']);
        $this->assertStringContainsString(response_budget::MORE_SENTENCE, $answer['summary']);
    }

    /**
     * fit_page() uses the limit minus exactly 5 percent: small entries show the step.
     */
    public function test_fit_page_uses_safety_margin(): void {
        $entries = [];
        for ($i = 0; $i < 200; $i++) {
            $entries[] = ['n' => $i];
        }
        $build = self::builder(200);
        $expected = 0;
        for ($k = 1; $k <= 200; $k++) {
            if (response_budget::measure($build(array_slice($entries, 0, $k))) > 3800) {
                break;
            }
            $expected = $k;
        }
        $answer = response_budget::fit_page($entries, $build, 4000);
        $this->assertSame(3800, response_budget::usable_chars(4000));
        $this->assertCount($expected, $answer['entries']);
    }

    /**
     * An answer exactly as long as the usable characters fits; one character less does not.
     */
    public function test_fit_page_exact_limit(): void {
        $entries = self::entries(5);
        $build = self::builder(5);
        $length = response_budget::measure($build(array_slice($entries, 0, 3)));
        $maxchars = (int) ceil($length * 100 / response_budget::USABLE_PERCENT);
        $this->assertSame($length, response_budget::usable_chars($maxchars));

        $this->assertCount(3, response_budget::fit_page($entries, $build, $maxchars)['entries']);
        $this->assertCount(2, response_budget::fit_page($entries, $build, $maxchars - 2)['entries']);
    }

    /**
     * fit_page() keeps a page that fits as it is.
     */
    public function test_fit_page_keeps_small_page(): void {
        $entries = self::entries(3);
        $answer = response_budget::fit_page($entries, self::builder(3), 12000);
        $this->assertCount(3, $answer['entries']);
        $this->assertFalse($answer['has_more']);
        $this->assertNull($answer['next_offset']);
        $this->assertStringNotContainsString(response_budget::MORE_SENTENCE, $answer['summary']);
    }

    /**
     * An entry longer than the whole limit is returned alone, never dropped.
     */
    public function test_fit_page_returns_single_oversized_entry(): void {
        $entries = [['id' => 1, 'text' => str_repeat('y', 3000)], ['id' => 2, 'text' => 'short']];
        $answer = response_budget::fit_page($entries, self::builder(2), 2000);
        $this->assertSame([$entries[0]], $answer['entries']);
        $this->assertTrue($answer['has_more']);
        $this->assertSame(1, $answer['next_offset']);

        $alone = response_budget::fit_page([$entries[0]], self::builder(1), 2000);
        $this->assertSame([$entries[0]], $alone['entries']);
        $this->assertFalse($alone['has_more']);
    }

    /**
     * fit_page() with no entries returns the empty answer.
     */
    public function test_fit_page_without_entries(): void {
        $answer = response_budget::fit_page([], self::builder(0), 2000);
        $this->assertSame([], $answer['entries']);
        $this->assertFalse($answer['has_more']);
        $this->assertNull($answer['next_offset']);
    }

    /**
     * fit_page() uses the configured limit when none is passed.
     */
    public function test_fit_page_uses_setting(): void {
        $this->resetAfterTest();
        $entries = self::entries(40);
        set_config('response_max_chars', 3000, 'webservice_elediamcp');
        $small = response_budget::fit_page($entries, self::builder(40));
        set_config('response_max_chars', 9000, 'webservice_elediamcp');
        $large = response_budget::fit_page($entries, self::builder(40));
        $this->assertLessThanOrEqual(2850, response_budget::measure($small));
        $this->assertLessThan(count($large['entries']), count($small['entries']));
    }

    /**
     * paging() derives has_more and next_offset from offset, page size and total.
     */
    public function test_paging(): void {
        $this->assertSame(['has_more' => true, 'next_offset' => 15], response_budget::paging(10, 5, 20));
        $this->assertSame(['has_more' => false, 'next_offset' => null], response_budget::paging(15, 5, 20));
        $this->assertSame(['has_more' => false, 'next_offset' => null], response_budget::paging(0, 0, 0));
    }

    /**
     * The configuration form accepts only limits from 2000 to 100000 characters.
     */
    public function test_configuration_form_checks_range(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $form = new configuration_form(null, ['services' => []]);
        foreach ([2000 => false, 12000 => false, 100000 => false, 1999 => true, 100001 => true, 0 => true] as $value => $error) {
            $errors = $form->validation(['response_max_chars' => $value], []);
            $this->assertSame($error, isset($errors['response_max_chars']), (string) $value);
        }
    }
}
