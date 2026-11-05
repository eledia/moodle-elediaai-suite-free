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

/**
 * Functions from Moodle libraries that are not autoloaded are only called in files that load them (#182).
 *
 * `course/lib.php`, `lib/gradelib.php`, `grade/querylib.php` and `lib/questionlib.php` are not loaded
 * by Moodle's setup. A class that calls `course_create_section()` and does not `require_once` the
 * library works in a test run that happens to have loaded it and fails with "Call to undefined function"
 * on a live request. That was the cause of #167 in the H5P author and of #129 in the course author. The
 * test reads which functions each library defines and checks every call in `classes/` against the
 * `require_once` lines of the same file.
 *
 * @package     webservice_elediamcp
 * @author      Immanuel Pasanec <immanuel.pasanec@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class core_library_require_test extends advanced_testcase {
    /** @var string[] Libraries below the Moodle root that setup.php does not load. */
    private const LIBRARIES = ['course/lib.php', 'lib/gradelib.php', 'grade/querylib.php', 'lib/questionlib.php'];

    /**
     * Every call of a library function sits in a file that requires the library.
     */
    public function test_files_load_the_libraries_whose_functions_they_call(): void {
        global $CFG;

        $functions = [];
        foreach (self::LIBRARIES as $library) {
            $source = file_get_contents($CFG->dirroot . '/' . $library);
            $this->assertNotFalse($source, "$library can be read");
            preg_match_all('/^function\s+(\w+)\s*\(/m', $source, $matches);
            foreach ($matches[1] as $name) {
                $functions[$name] = $library;
            }
        }
        $this->assertNotEmpty($functions);

        $violations = [];
        $root = dirname(__DIR__) . '/classes';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            foreach (self::called_functions($source) as $name) {
                if (!isset($functions[$name])) {
                    continue;
                }
                $library = $functions[$name];
                // The library is named as dirroot . '/course/lib.php' or as libdir . '/gradelib.php'.
                $tail = str_starts_with($library, 'lib/') ? substr($library, 3) : '/' . $library;
                $pattern = '/\brequire(?:_once)?\b[^;]*' . preg_quote($tail, '/') . '/';
                if (!preg_match($pattern, $source)) {
                    $shortpath = str_replace(dirname($root) . '/', '', $file->getPathname());
                    $violations[$file->getPathname() . ' ' . $name] =
                        "$shortpath: $name() needs require_once \$CFG->dirroot . '/$library'";
                }
            }
        }
        $message = "Functions of libraries that are not loaded:\n" . implode("\n", $violations);
        $this->assertSame([], array_values($violations), $message);
    }

    /**
     * Names of functions the source calls, from its tokens and so without comments and strings.
     *
     * Method calls, static calls, `new`, and function definitions do not count.
     *
     * @param string $source PHP source.
     * @return string[]
     */
    private static function called_functions(string $source): array {
        $tokens = array_values(array_filter(
            token_get_all($source),
            fn($token) => !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
        ));
        $skipbefore = [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CONST];
        $names = [];
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || !in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }
            if (($tokens[$index + 1] ?? null) !== '(') {
                continue;
            }
            $previous = $tokens[$index - 1] ?? null;
            if (is_array($previous) && in_array($previous[0], $skipbefore, true)) {
                continue;
            }
            $names[] = ltrim($token[1], '\\');
        }
        return array_values(array_unique($names));
    }
}
