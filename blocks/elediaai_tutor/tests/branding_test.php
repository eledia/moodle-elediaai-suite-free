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
use block_elediaai_tutor\local\premium;
use block_elediaai_tutor\local\presets;
use block_elediaai_tutor\local\registry;

/**
 * Unit tests for registry-driven branding + persona resolution.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license      http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_elediaai_tutor\local\branding::class)]
final class branding_test extends \advanced_testcase {
    /**
     * With nothing configured, no token overrides are emitted (defaults stand)
     * and the footer is the eLeDia credit.
     */
    public function test_defaults_emit_no_overrides(): void {
        $this->resetAfterTest();

        $brand = branding::resolve([]);
        $this->assertSame('', $brand['accent']);
        $this->assertSame('', $brand['bubble']);
        $this->assertSame('', branding::css_variables($brand));
        $this->assertSame(branding::FOOTER_DEFAULT, $brand['footermode']);
        $this->assertSame(get_string('poweredby', 'block_elediaai_tutor'), $brand['footertext']);
    }

    /**
     * A per-instance accent overrides the site accent; the site value applies
     * where the instance is empty. The accent drives a darkened hover token.
     */
    public function test_instance_overrides_site(): void {
        $this->resetAfterTest();
        set_config('brandaccent', '#112233', 'block_elediaai_tutor');
        set_config('brandbubble', '#445566', 'block_elediaai_tutor');

        // No instance override → site values.
        $brand = branding::resolve([]);
        $this->assertSame('#112233', $brand['accent']);
        $this->assertSame('#445566', $brand['bubble']);

        // Instance accent wins (it is exposed by default); bubble still from site.
        $brand = branding::resolve(['brandaccent' => '#aabbcc']);
        $this->assertSame('#aabbcc', $brand['accent']);
        $this->assertSame('#445566', $brand['bubble']);

        $css = branding::css_variables($brand);
        $this->assertStringContainsString('--eac-accent:#aabbcc;', $css);
        $this->assertStringContainsString('--eac-user-bg:#445566;', $css);
        $this->assertStringContainsString('--eac-accent-dark:#', $css);
    }

    /**
     * Invalid colours and unsafe CSS/font values are rejected, never injected.
     */
    public function test_sanitisation(): void {
        $this->resetAfterTest();

        // A CSS-injection attempt in a colour is dropped (not a valid hex).
        $brand = branding::resolve(['brandaccent' => 'red;}#x{color:red']);
        $this->assertSame('', $brand['accent']);
        $this->assertSame('', branding::css_variables($brand));

        $this->assertNull(branding::sanitise_colour('blue'));
        $this->assertSame('#1e3f59', branding::sanitise_colour('#1e3f59'));

        // The free-text token sanitiser allows safe values but blocks break-outs.
        $this->assertSame('rgba(0, 0, 0, 0.5)', branding::sanitise_css_value('rgba(0, 0, 0, 0.5)'));
        $this->assertSame('18px', branding::sanitise_css_value('18px'));
        $this->assertSame('', branding::sanitise_css_value('red; } body{display:none'));
        $this->assertSame('', branding::sanitise_css_value('url(http://evil/x.png)'));
        $this->assertSame('', branding::sanitise_css_value('</style><script>'));

        $this->assertSame('Inter, sans-serif', branding::sanitise_font('Inter, sans-serif }'));
        $this->assertStringNotContainsString('{', branding::sanitise_font('a{}<b>'));
    }

    /**
     * Applying a built-in preset's settings (as an instance would receive on a
     * snapshot) yields the preset's palette; HAL is a dark red look.
     */
    public function test_preset_palette(): void {
        $this->resetAfterTest();

        // HAL preset, written into the site config (as tutor_apply would).
        foreach (presets::settings('hal') as $key => $value) {
            set_config(registry::sitekey($key), $value, 'block_elediaai_tutor');
        }
        $css = branding::css_variables(branding::resolve([]));
        $this->assertStringContainsString('--eac-accent:#ff1a1a;', $css);
        $this->assertStringContainsString('--eac-body-bg:#0c0c0e;', $css);
        $this->assertStringContainsString('--eac-avatar-glow:', $css);   // The glowing eye.
    }

    /**
     * Every non-default preset defines a complete palette (the required tokens),
     * so no component falls back to a clashing default on any preset.
     */
    public function test_presets_define_required_tokens(): void {
        $required = ['--eac-accent', '--eac-ink', '--eac-surface', '--eac-body-bg',
            '--eac-bot-bg', '--eac-user-bg', '--eac-line'];
        $tokentokey = array_flip(registry::token_keys());
        foreach (presets::all() as $id => $preset) {
            if ($id === presets::DEFAULT) {
                continue;
            }
            $settings = presets::settings($id);
            foreach ($required as $token) {
                $key = $tokentokey[$token] ?? null;
                $this->assertNotNull($key, "No registry key for {$token}");
                $this->assertArrayHasKey(
                    $key,
                    $settings,
                    "Preset '{$id}' is missing {$token}"
                );
            }
        }
    }

    /**
     * The tutor (assistant) bubble colour is settable, instance over site.
     */
    public function test_bot_bubble_colour(): void {
        $this->resetAfterTest();
        set_config('brandbotbubble', '#101820', 'block_elediaai_tutor');
        $this->assertStringContainsString(
            '--eac-bot-bg:#101820;',
            branding::css_variables(branding::resolve([]))
        );
        $this->assertStringContainsString(
            '--eac-bot-bg:#abcdef;',
            branding::css_variables(branding::resolve(['brandbotbubble' => '#abcdef']))
        );
    }

    /**
     * The launcher style resolves instance-over-site; an empty instance value
     * follows the site, and an invalid one falls back to the safe default.
     */
    public function test_launcher_style(): void {
        $this->resetAfterTest();
        $this->assertSame('pill', branding::resolve([])['launcherstyle']);

        set_config('launcherstyle', 'fab', 'block_elediaai_tutor');
        $this->assertSame('fab', branding::resolve([])['launcherstyle']);
        // Empty instance value follows the site setting.
        $this->assertSame('fab', branding::resolve(['launcherstyle' => ''])['launcherstyle']);
        // A valid instance value wins.
        $this->assertSame('solid', branding::resolve(['launcherstyle' => 'solid'])['launcherstyle']);
        // A tampered/invalid value falls back to the safe default.
        $this->assertSame('pill', branding::resolve(['launcherstyle' => 'bogus'])['launcherstyle']);
    }

    /**
     * Footer customisation is ignored in the free block.
     */
    public function test_footer_modes_require_premium(): void {
        $this->resetAfterTest();

        set_config('grant_footerbranding', 1, 'local_elediaai_tutor_premium');

        set_config('footermode', branding::FOOTER_CUSTOM, 'block_elediaai_tutor');
        set_config('footertext', 'A University', 'block_elediaai_tutor');

        if (premium::has_feature(premium::FEATURE_FOOTER_BRANDING)) {
            // Premium add-on present: footer customisation is unlocked.
            $this->assertSame(branding::FOOTER_CUSTOM, branding::resolve([])['footermode']);
            $this->assertSame('A University', branding::resolve([])['footertext']);
            return;
        }

        // Free block: footer customisation is ignored — always the default credit.
        $this->assertSame(branding::FOOTER_DEFAULT, branding::resolve([])['footermode']);
        $this->assertSame(get_string('poweredby', 'block_elediaai_tutor'), branding::resolve([])['footertext']);

        set_config('footermode', branding::FOOTER_NONE, 'block_elediaai_tutor');
        $this->assertSame(get_string('poweredby', 'block_elediaai_tutor'), branding::resolve([])['footertext']);
    }

    /**
     * Footer text is instance-overridable only when the admin exposes it.
     */
    public function test_footer_instance_override(): void {
        $this->resetAfterTest();
        set_config('grant_footerbranding', 1, 'local_elediaai_tutor_premium');
        set_config('footermode', branding::FOOTER_CUSTOM, 'block_elediaai_tutor');
        set_config('footertext', 'Site footer', 'block_elediaai_tutor');

        if (premium::has_feature(premium::FEATURE_FOOTER_BRANDING)) {
            // Premium present: the site custom footer applies; an instance override
            // only takes effect once the admin exposes the key.
            $this->assertSame('Site footer', branding::resolve(['footertext' => 'Course footer'])['footertext']);
            set_config('expose_footertext', 1, 'block_elediaai_tutor');
            $this->assertSame('Course footer', branding::resolve(['footertext' => 'Course footer'])['footertext']);
            return;
        }

        // Free block: footer is the default credit regardless of config or admin opt-in.
        $this->assertSame(
            get_string('poweredby', 'block_elediaai_tutor'),
            branding::resolve(['footertext' => 'Course footer'])['footertext']
        );
        set_config('expose_footertext', 1, 'block_elediaai_tutor');
        $this->assertSame(
            get_string('poweredby', 'block_elediaai_tutor'),
            branding::resolve(['footertext' => 'Course footer'])['footertext']
        );
    }

    /**
     * The structured persona is assembled instance-over-site, only populated
     * sub-fields, with the name mapped from the 'persona' key.
     */
    public function test_persona_assembly(): void {
        $this->resetAfterTest();
        set_config('persona', 'Site Tutor', 'block_elediaai_tutor');
        set_config('persona_tone', 'friendly', 'block_elediaai_tutor');

        $persona = branding::persona([]);
        $this->assertSame('Site Tutor', $persona['name']);
        $this->assertSame('friendly', $persona['tone']);
        $this->assertArrayNotHasKey('role', $persona);

        // Instance overrides the name (persona is exposed by default).
        $persona = branding::persona(['persona' => 'Course Tutor']);
        $this->assertSame('Course Tutor', $persona['name']);
    }
}
