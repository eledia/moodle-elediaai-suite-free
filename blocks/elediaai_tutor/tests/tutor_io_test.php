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

namespace block_elediaai_tutor;

use PHPUnit\Framework\Attributes\CoversClass;
use block_elediaai_tutor\local\branding;
use block_elediaai_tutor\local\tutor_io;
use block_elediaai_tutor\local\tutor_profile;

/**
 * Unit tests for tutor import/export round-tripping.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license      http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_elediaai_tutor\local\tutor_io::class)]
final class tutor_io_test extends \advanced_testcase {
    /** @var string A 1×1 transparent PNG. */
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /**
     * A settings-only bundle round-trips: unknown keys are dropped on the way in.
     */
    public function test_settings_roundtrip(): void {
        $this->resetAfterTest();

        $settings = [
            'brandaccent' => '#123456',
            'persona' => 'Round Trip',
            'answerstyle' => 'hint',
            'bogus_key' => 'should be dropped',
        ];
        $path = tutor_io::export('My Tutor', 'mytutor', $settings, null, null);
        $this->assertFileExists($path);

        $bundle = tutor_io::import($path);
        $this->assertSame('My Tutor', $bundle['name']);
        $this->assertSame('mytutor', $bundle['shortname']);
        $this->assertSame('#123456', $bundle['settings']['brandaccent']);
        $this->assertSame('Round Trip', $bundle['settings']['persona']);
        $this->assertSame('hint', $bundle['settings']['answerstyle']);
        $this->assertArrayNotHasKey('bogus_key', $bundle['settings']);
        // With no uploaded logo, the export bundles the built-in default so the
        // bundle is self-contained.
        $this->assertNotNull($bundle['logo']);
        $this->assertNotEmpty($bundle['logo']['content']);
    }

    /**
     * Images survive the round trip byte-for-byte.
     */
    public function test_image_roundtrip(): void {
        $this->resetAfterTest();

        $png = base64_decode(self::PNG_1X1);
        // Stage the image as a profile logo, then export from that profile.
        $id = tutor_profile::create('Imaged', '', ['brandaccent' => '#abcdef']);
        tutor_profile::store_image($id, branding::TUTOR_LOGO_FILEAREA, 'logo.png', $png);
        $logo = tutor_profile::image($id, branding::TUTOR_LOGO_FILEAREA);
        $this->assertNotNull($logo);

        $path = tutor_io::export('Imaged', 'imaged', ['brandaccent' => '#abcdef'], $logo, null);
        $bundle = tutor_io::import($path);

        $this->assertNotNull($bundle['logo']);
        $this->assertSame($png, $bundle['logo']['content']);
        $this->assertNull($bundle['avatar']);
    }

    /**
     * A non-bundle file is rejected.
     */
    public function test_invalid_bundle_rejected(): void {
        $this->resetAfterTest();
        $tmp = make_request_directory() . '/notazip.zip';
        file_put_contents($tmp, 'this is not a zip');
        $this->expectException(\moodle_exception::class);
        tutor_io::import($tmp);
    }
}
