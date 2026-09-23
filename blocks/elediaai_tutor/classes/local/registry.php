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

namespace block_elediaai_tutor\local;

/**
 * The single source of truth for every "tutor-defining" setting.
 *
 * A *tutor* is the full presentation + persona of the widget: its visual design
 * (every `--eac-*` CSS custom property), its persona/system-prompt fields, the
 * launcher, footer and a handful of behaviour toggles, plus the logo and avatar
 * images. Infrastructure and security settings (RAG server URL, tokens, MCP
 * service, network/timeout/rate-limit/analytics) are deliberately NOT here —
 * they are not part of a tutor and stay hand-declared in settings.php.
 *
 * This registry drives, as data rather than duplicated code:
 *   1. the admin settings page (settings.php loops these),
 *   2. the per-setting "expose to instance" admin checkboxes (expose_<key>),
 *   3. the per-instance edit form (only exposed keys are offered),
 *   4. branding token resolution ({@see branding::resolve()}),
 *   5. tutor import/export (exactly these keys round-trip),
 *   6. the built-in presets and saved tutor profiles.
 *
 * Each entry is a descriptor:
 *   group        — UI grouping (also the order of headings)
 *   type         — colour|cssvalue|font|text|textarea|select|checkbox|file
 *   token        — the `--eac-*` custom property this maps to, or null
 *   default      — site default (empty string ⇒ "use the styles.css default")
 *   options      — value => lang-string key, for selects
 *   instanceable — whether this may ever be overridden per block instance
 *   sendtorag    — whether this value is sent to the RAG server (persona)
 *   sitekey      — the site config key when it differs from the registry key
 *   exposedefault— default value of the expose_<key> admin checkbox
 *
 * The registry key doubles as the per-instance config field name
 * (`config_<key>`); the site config key is the same unless `sitekey` overrides
 * it (used to preserve a couple of legacy admin keys).
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class registry {
    /** @var string Config flag prefix marking a key as instance-overridable. */
    public const EXPOSE_PREFIX = 'expose_';

    /**
     * Group ids in display order.
     *
     * `knowledge` comes first, ahead of the persona and the design tokens.
     * What a tutor may answer from is a different kind of question from what
     * it looks like, and it was the one somebody went looking for: buried in
     * ninth place among fourteen collapsed sections, it was reported missing.
     *
     * @var string[]
     */
    private const GROUP_ORDER = [
        'knowledge', 'persona', 'accent', 'surfaces', 'text', 'bubbles', 'states',
        'shape', 'effects', 'conversation', 'dashboard', 'launcher', 'footer', 'files',
    ];

    /**
     * The full registry, key => descriptor.
     *
     * @return array<string, array<string,mixed>>
     */
    public static function all(): array {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $entries = [];

        // Persona / system prompt (sent to the RAG server).
        $entries['persona'] = self::entry('persona', 'text', [
            'sendtorag' => true, 'exposedefault' => true,
        ]);
        foreach (['role', 'tone', 'audience'] as $field) {
            $entries['persona_' . $field] = self::entry('persona', 'text', [
                'sendtorag' => true,
            ]);
        }
        $entries['persona_instructions'] = self::entry('persona', 'textarea', [
            'sendtorag' => true,
        ]);

        // Colour + value tokens. Each maps to a single --eac-* property.
        // [registry key, --eac- token, group, type, exposedefault, sitekey].
        $tokens = [
            // Accent family.
            ['brandaccent', '--eac-accent', 'accent', 'colour', true],
            ['tok_accentdark', '--eac-accent-dark', 'accent', 'colour', false],
            ['tok_accentcontrast', '--eac-accent-contrast', 'accent', 'colour', false],
            ['tok_brand', '--eac-brand', 'accent', 'colour', false],
            ['tok_focusring', '--eac-focus-ring', 'accent', 'cssvalue', false],
            // Surfaces.
            ['brandsurface', '--eac-body-bg', 'surfaces', 'colour', false],
            ['tok_surface', '--eac-surface', 'surfaces', 'colour', false],
            ['tok_line', '--eac-line', 'surfaces', 'colour', false],
            ['tok_tint', '--eac-tint', 'surfaces', 'colour', false],
            ['tok_overlay', '--eac-overlay', 'surfaces', 'cssvalue', false],
            ['tok_overlaystrong', '--eac-overlay-strong', 'surfaces', 'cssvalue', false],
            ['tok_codebg', '--eac-code-bg', 'surfaces', 'cssvalue', false],
            ['tok_backdrop', '--eac-backdrop', 'surfaces', 'cssvalue', false],
            // Text.
            ['tok_ink', '--eac-ink', 'text', 'colour', false],
            ['tok_headerfg', '--eac-header-fg', 'text', 'colour', false],
            ['tok_muted', '--eac-muted', 'text', 'colour', false],
            ['tok_mutedsoft', '--eac-muted-soft', 'text', 'colour', false],
            ['brandfont', '--eac-font', 'text', 'font', false],
            // Typography of the conversation itself. Everything else about the
            // tutor was adjustable and this was not: the size of an answer, its
            // leading, and how loud a heading inside one is were fixed in the
            // stylesheet. A site that sets its own type scale had no way to
            // bring the tutor along.
            ['tok_bubblesize', '--eac-bubble-font-size', 'text', 'cssvalue', false],
            ['tok_bubbleleading', '--eac-bubble-line-height', 'text', 'cssvalue', false],
            ['tok_bubbleheading', '--eac-bubble-heading-size', 'text', 'cssvalue', false],
            // Bubbles.
            ['brandbubble', '--eac-user-bg', 'bubbles', 'colour', true],
            ['tok_userfg', '--eac-user-fg', 'bubbles', 'colour', false],
            ['brandbotbubble', '--eac-bot-bg', 'bubbles', 'colour', true],
            ['tok_botfg', '--eac-bot-fg', 'bubbles', 'colour', false],
            ['tok_assistantbg', '--eac-assistant-bg', 'bubbles', 'colour', false],
            ['tok_assistantfg', '--eac-assistant-fg', 'bubbles', 'colour', false],
            // States.
            ['tok_statusonline', '--eac-status-online', 'states', 'colour', false],
            ['brandiconhover', '--eac-icon-hover', 'states', 'cssvalue', false],
            ['tok_errorbg', '--eac-error-bg', 'states', 'colour', false],
            ['tok_errorfg', '--eac-error-fg', 'states', 'colour', false],
            ['tok_errorline', '--eac-error-line', 'states', 'colour', false],
            ['tok_groundedbg', '--eac-grounded-bg', 'states', 'colour', false],
            ['tok_groundedline', '--eac-grounded-line', 'states', 'colour', false],
            // Shape & spacing.
            ['tok_radius', '--eac-radius', 'shape', 'cssvalue', false],
            ['tok_bubbleradius', '--eac-bubble-radius', 'shape', 'cssvalue', false],
            ['tok_gap', '--eac-gap', 'shape', 'cssvalue', false],
            ['tok_z', '--eac-z', 'shape', 'cssvalue', false],
            ['tok_fabbottom', '--eac-fab-bottom', 'shape', 'cssvalue', false],
            ['tok_fabright', '--eac-fab-right', 'shape', 'cssvalue', false],
            // Effects.
            ['tok_shadowsm', '--eac-shadow-sm', 'effects', 'cssvalue', false],
            ['tok_shadowmd', '--eac-shadow-md', 'effects', 'cssvalue', false],
            ['tok_shadowlg', '--eac-shadow-lg', 'effects', 'cssvalue', false],
            ['tok_avatarglow', '--eac-avatar-glow', 'effects', 'cssvalue', false],
        ];
        // Friendly named options for the non-colour tokens, so non-technical
        // staff pick "Medium" rather than type raw CSS. Keyed registry key =>
        // (CSS value => option lang-key); '' is always "use the built-in
        // default". Values stay real CSS strings so presets/imports are
        // unaffected (the field type remains cssvalue/font, which sanitises any
        // safe value). The light()/dark() preset wash values are included so
        // applying a preset keeps them.
        $choices = self::token_choices();

        // The 5th tuple element is retained for documentation of the original
        // intent, but every token is now exposed per instance by default (the
        // admin removes exposure where they want a setting kept site-wide).
        foreach ($tokens as [$key, $token, $group, $type]) {
            $entries[$key] = self::entry($group, $type, [
                'token' => $token,
                'choices' => $choices[$key] ?? null,
            ]);
        }

        // Conversation & display (instanceable).
        // What this tutor may answer from. A selection of course categories and
        // courses -- the wish; the engine intersects it with the enrolments and
        // with what is indexed before anything is searched, and on the start
        // page it does not apply at all (docs/wissensbasis-plan.md).
        $entries['coursescope'] = self::entry('knowledge', 'coursescope', [
            'exposedefault' => true,
        ]);

        $entries['welcomemessage'] = self::entry('conversation', 'textarea', [
            'exposedefault' => true,
        ]);
        $entries['promptstarters'] = self::entry('conversation', 'textarea', [
            'exposedefault' => true,
        ]);
        $entries['answerstyle'] = self::entry('conversation', 'select', [
            'default' => 'explain',
            'options' => [
                'explain' => 'answerstyle_explain',
                'hint' => 'answerstyle_hint',
                'quiz' => 'answerstyle_quiz',
            ],
            'exposedefault' => true,
        ]);
        $entries['allowstylechange'] = self::entry('conversation', 'checkbox', [
            'default' => 1, 'exposedefault' => true,
        ]);
        // The default presentation is the docked floating panel.
        $entries['displaymode'] = self::entry('conversation', 'select', [
            'default' => 'docked',
            'sitekey' => 'defaultdisplaymode',
            'options' => [
                'embedded' => 'displaymode_embedded',
                'docked' => 'displaymode_docked',
                'modal' => 'displaymode_modal',
                'fullscreen' => 'displaymode_fullscreen',
            ],
            'exposedefault' => true,
        ]);
        $entries['historyenabled'] = self::entry('conversation', 'checkbox', [
            'default' => 1, 'exposedefault' => true,
        ]);

        // Dashboard hero mode (/my/ pages only). The hero replaces the classic
        // widget with a prominent greeting, audience-specific prompt starters
        // and a briefing button; empty text values fall back to lang-string
        // defaults at render time (see widget::render()).
        $entries['dashboardenabled'] = self::entry('dashboard', 'checkbox', [
            'default' => 1, 'exposedefault' => true,
        ]);
        // Empty means "use the built-in greeting", so an empty text field
        // cannot say "none". This switch is the only way to express that, and
        // it defaults to on so no existing dashboard changes appearance.
        $entries['dashboardgreetingenabled'] = self::entry('dashboard', 'checkbox', [
            'default' => 1, 'exposedefault' => true,
        ]);
        $entries['dashboardgreeting'] = self::entry('dashboard', 'text', [
            'exposedefault' => true,
        ]);
        $entries['promptstarters_teacher'] = self::entry('dashboard', 'textarea', [
            'exposedefault' => true,
        ]);
        $entries['promptstarters_manager'] = self::entry('dashboard', 'textarea', [
            'exposedefault' => true,
        ]);
        $entries['briefingenabled'] = self::entry('dashboard', 'checkbox', [
            'default' => 1, 'exposedefault' => true,
        ]);
        $entries['briefingprompt'] = self::entry('dashboard', 'textarea', [
            'exposedefault' => true,
        ]);

        // Launcher.
        $entries['launcherstyle'] = self::entry('launcher', 'select', [
            'default' => 'pill',
            'options' => [
                'pill' => 'launcherstyle_pill',
                'solid' => 'launcherstyle_solid',
                'fab' => 'launcherstyle_fab',
                'compact' => 'launcherstyle_compact',
            ],
            'exposedefault' => true,
        ]);
        $entries['launchlabel'] = self::entry('launcher', 'text', [
            'sitekey' => 'brandlaunchlabel',
            'exposedefault' => true,
        ]);

        // Footer / white-label. Instance-overridable only when the admin
        // opts in (off by default — white-label is usually institution-wide).
        $entries['footermode'] = self::entry('footer', 'select', [
            'default' => branding::FOOTER_DEFAULT,
            'premiumfeature' => premium::FEATURE_FOOTER_BRANDING,
            'options' => [
                branding::FOOTER_DEFAULT => 'footermode_default',
                branding::FOOTER_CUSTOM => 'footermode_custom',
                branding::FOOTER_NONE => 'footermode_none',
            ],
            'exposedefault' => false,
        ]);
        $entries['footertext'] = self::entry('footer', 'text', [
            'premiumfeature' => premium::FEATURE_FOOTER_BRANDING,
            'exposedefault' => false,
        ]);

        // Images.
        $entries['logo'] = self::entry('files', 'file', [
            'exposedefault' => true,
        ]);
        $entries['avatar'] = self::entry('files', 'file', [
            'exposedefault' => true,
        ]);

        $cache = $entries;
        return $cache;
    }

    /**
     * Build a descriptor with sensible defaults filled in.
     *
     * @param string $group Group id.
     * @param string $type Field type.
     * @param array $overrides Any descriptor overrides.
     * @return array<string,mixed>
     */
    private static function entry(string $group, string $type, array $overrides = []): array {
        return $overrides + [
            'group' => $group,
            'type' => $type,
            'token' => null,
            'default' => self::type_default($type),
            'options' => null,
            'choices' => null,
            'instanceable' => true,
            'sendtorag' => false,
            'sitekey' => null,
            'premiumfeature' => null,
            // Every optical/persona/behaviour setting is overridable per instance
            // by default; the admin removes exposure where they want site-wide
            // control. Footer is the one opt-in exception (see below).
            'exposedefault' => true,
        ];
    }

    /**
     * The empty/neutral default for a field type.
     *
     * @param string $type Field type.
     * @return mixed
     */
    private static function type_default(string $type): mixed {
        return $type === 'checkbox' ? 0 : '';
    }

    /**
     * Friendly named options for the non-colour tokens (registry key => (CSS
     * value => option lang-key)). The empty value means "built-in default".
     * Wash options include the light()/dark() preset values verbatim so applying
     * a preset never loses them.
     *
     * @return array<string, array<string,string>>
     */
    private static function token_choices(): array {
        return [
            'tok_radius' => ['' => 'reg_opt_default', '0' => 'reg_opt_none', '8px' => 'reg_opt_small',
                '14px' => 'reg_opt_medium', '22px' => 'reg_opt_large', '999px' => 'reg_opt_pill'],
            'tok_bubbleradius' => ['' => 'reg_opt_default', '4px' => 'reg_opt_small',
                '10px' => 'reg_opt_medium', '16px' => 'reg_opt_large', '24px' => 'reg_opt_round'],
            'tok_gap' => ['' => 'reg_opt_default', '0.5rem' => 'reg_opt_compact', '0.65rem' => 'reg_opt_cosy',
                '0.8rem' => 'reg_opt_comfortable', '1.1rem' => 'reg_opt_spacious'],
            // Stufen der Theme-Skala: small, body, h4. 0.9375rem (15px) ist
            // entfallen -- es lag auf keiner Stufe und war der einzige Weg,
            // ueber eine Einstellung eine Groesse ausserhalb der Skala zu
            // erzeugen.
            'tok_bubblesize' => ['' => 'reg_opt_default', '0.875rem' => 'reg_opt_small',
                '1rem' => 'reg_opt_medium', '1.125rem' => 'reg_opt_large'],
            'tok_bubbleleading' => ['' => 'reg_opt_default', '1.45' => 'reg_opt_compact',
                '1.6' => 'reg_opt_cosy', '1.75' => 'reg_opt_spacious'],
            // A heading inside an answer is a step, not a headline. The largest
            // option is still under the greeting above it on purpose.
            'tok_bubbleheading' => ['' => 'reg_opt_default', '1em' => 'reg_opt_small',
                '1.125em' => 'reg_opt_medium', '1.25em' => 'reg_opt_large'],
            'tok_z' => ['' => 'reg_opt_default', '1080' => 'reg_opt_normal', '5000' => 'reg_opt_high',
                '99999' => 'reg_opt_highest'],
            'tok_fabbottom' => ['' => 'reg_opt_default', '1.25rem' => 'reg_opt_near',
                '5.5rem' => 'reg_opt_far'],
            'tok_fabright' => ['' => 'reg_opt_default', '1rem' => 'reg_opt_near', '2rem' => 'reg_opt_far'],
            'tok_shadowsm' => ['' => 'reg_opt_default', 'none' => 'reg_opt_none',
                '0 1px 2px rgba(16, 24, 40, 0.06)' => 'reg_opt_subtle',
                '0 1px 3px rgba(16, 24, 40, 0.12)' => 'reg_opt_soft'],
            'tok_shadowmd' => ['' => 'reg_opt_default', 'none' => 'reg_opt_none',
                '0 2px 8px rgba(16, 24, 40, 0.10)' => 'reg_opt_soft',
                '0 4px 14px rgba(16, 24, 40, 0.16)' => 'reg_opt_medium',
                '0 8px 24px rgba(16, 24, 40, 0.22)' => 'reg_opt_strong'],
            'tok_shadowlg' => ['' => 'reg_opt_default', 'none' => 'reg_opt_none',
                '0 8px 24px rgba(16, 24, 40, 0.16)' => 'reg_opt_medium',
                '0 16px 48px rgba(16, 24, 40, 0.24)' => 'reg_opt_strong'],
            'tok_avatarglow' => ['' => 'reg_opt_default', 'none' => 'reg_opt_none',
                '0 4px 12px rgba(16, 24, 40, 0.18)' => 'reg_opt_softglow',
                '0 0 14px 2px rgba(255, 24, 24, 0.75)' => 'reg_opt_redglow',
                '0 0 14px 2px rgba(92, 200, 255, 0.6)' => 'reg_opt_blueglow'],
            // Translucent washes. Include the exact light()/dark() preset values.
            'brandiconhover' => ['' => 'reg_opt_default',
                'rgba(16, 24, 40, 0.08)' => 'reg_opt_lightsubtle',
                'rgba(16, 24, 40, 0.16)' => 'reg_opt_lightstrong',
                'rgba(255, 255, 255, 0.14)' => 'reg_opt_darksubtle',
                'rgba(255, 255, 255, 0.24)' => 'reg_opt_darkstrong'],
            'tok_overlay' => ['' => 'reg_opt_default',
                'rgba(16, 24, 40, 0.06)' => 'reg_opt_lightsubtle',
                'rgba(16, 24, 40, 0.12)' => 'reg_opt_lightstrong',
                'rgba(255, 255, 255, 0.10)' => 'reg_opt_darksubtle',
                'rgba(255, 255, 255, 0.18)' => 'reg_opt_darkstrong'],
            'tok_overlaystrong' => ['' => 'reg_opt_default',
                'rgba(0, 0, 0, 0.04)' => 'reg_opt_lightsubtle',
                'rgba(0, 0, 0, 0.08)' => 'reg_opt_lightstrong',
                'rgba(255, 255, 255, 0.06)' => 'reg_opt_darksubtle',
                'rgba(255, 255, 255, 0.12)' => 'reg_opt_darkstrong'],
            'tok_codebg' => ['' => 'reg_opt_default',
                'rgba(0, 0, 0, 0.06)' => 'reg_opt_lightsubtle',
                'rgba(0, 0, 0, 0.12)' => 'reg_opt_lightstrong',
                'rgba(255, 255, 255, 0.08)' => 'reg_opt_darksubtle',
                'rgba(255, 255, 255, 0.16)' => 'reg_opt_darkstrong'],
            'tok_backdrop' => ['' => 'reg_opt_default',
                'rgba(16, 24, 40, 0.30)' => 'reg_opt_subtle',
                'rgba(16, 24, 40, 0.45)' => 'reg_opt_medium',
                'rgba(16, 24, 40, 0.65)' => 'reg_opt_strong'],
            'tok_focusring' => ['' => 'reg_opt_default',
                '0 0 0 0.2rem rgba(16, 24, 40, 0.18)' => 'reg_opt_subtle',
                '0 0 0 0.2rem rgba(16, 24, 40, 0.30)' => 'reg_opt_medium',
                '0 0 0 0.25rem rgba(16, 24, 40, 0.40)' => 'reg_opt_strong'],
            'brandfont' => ['' => 'reg_opt_default',
                'system-ui, sans-serif' => 'reg_font_system',
                'Inter, system-ui, sans-serif' => 'reg_font_inter',
                'Georgia, "Times New Roman", serif' => 'reg_font_serif',
                '"Trebuchet MS", Verdana, sans-serif' => 'reg_font_rounded',
                '"Courier New", monospace' => 'reg_font_mono',
                'Arial, Helvetica, sans-serif' => 'reg_font_classic'],
        ];
    }

    /**
     * The friendly named options for a key, or null when it has none.
     *
     * @param string $key Registry key.
     * @return array<string,string>|null CSS value => option lang-key.
     */
    public static function choices(string $key): ?array {
        $entry = self::get($key);
        return $entry['choices'] ?? null;
    }

    /**
     * Build a translated value => label option list for a choices-backed key,
     * with a caller-supplied leading (empty-value) label and the current stored
     * value appended as a "Current (…)" option when it is not one of the
     * presets — so a legacy/imported custom value is shown and never silently
     * overwritten on save.
     *
     * @param string $key Registry key.
     * @param string $emptylabel Label for the empty value (e.g. "Use site default").
     * @param string|null $current The currently stored value, or null.
     * @return array<string,string> value => translated label.
     */
    public static function choice_select_options(
        string $key,
        string $emptylabel,
        ?string $current = null
    ): array {
        $options = ['' => $emptylabel];
        foreach ((self::choices($key) ?? []) as $value => $langkey) {
            if ((string) $value === '') {
                continue;
            }
            $options[(string) $value] = get_string($langkey, 'block_elediaai_tutor');
        }
        if ($current !== null && (string) $current !== '' && !isset($options[(string) $current])) {
            $options[(string) $current] = get_string('reg_opt_current', 'block_elediaai_tutor', $current);
        }
        return $options;
    }

    /**
     * A descriptor by key, or null when unknown.
     *
     * @param string $key Registry key.
     * @return array<string,mixed>|null
     */
    public static function get(string $key): ?array {
        return self::all()[$key] ?? null;
    }

    /**
     * Whether a key exists in the registry.
     *
     * @param string $key Registry key.
     * @return bool
     */
    public static function exists(string $key): bool {
        return isset(self::all()[$key]);
    }

    /**
     * Group ids in display order.
     *
     * @return string[]
     */
    public static function groups(): array {
        return self::GROUP_ORDER;
    }

    /**
     * Registry keys belonging to a group, in registry order.
     *
     * @param string $group Group id.
     * @return string[]
     */
    public static function group_keys(string $group): array {
        $keys = [];
        foreach (self::all() as $key => $entry) {
            if ($entry['group'] === $group) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    /**
     * All keys that map to a `--eac-*` design token.
     *
     * @return array<string,string> registry key => token name.
     */
    public static function token_keys(): array {
        $out = [];
        foreach (self::all() as $key => $entry) {
            if ($entry['token'] !== null) {
                $out[$key] = $entry['token'];
            }
        }
        return $out;
    }

    /**
     * Keys whose value is sent to the RAG server (persona fields).
     *
     * @return string[]
     */
    public static function persona_keys(): array {
        $out = [];
        foreach (self::all() as $key => $entry) {
            if (!empty($entry['sendtorag'])) {
                $out[] = $key;
            }
        }
        return $out;
    }

    /**
     * Keys that may ever be overridden per instance.
     *
     * @return string[]
     */
    public static function instanceable_keys(): array {
        $out = [];
        foreach (self::all() as $key => $entry) {
            if (!empty($entry['instanceable'])) {
                $out[] = $key;
            }
        }
        return $out;
    }

    /**
     * The site config key for a registry key (honours the `sitekey` override).
     *
     * @param string $key Registry key.
     * @return string
     */
    public static function sitekey(string $key): string {
        $entry = self::get($key);
        return ($entry && $entry['sitekey']) ? $entry['sitekey'] : $key;
    }

    /**
     * The site default value for a key.
     *
     * @param string $key Registry key.
     * @return mixed
     */
    public static function default_for(string $key): mixed {
        $entry = self::get($key);
        return $entry['default'] ?? '';
    }

    /**
     * Whether the admin has exposed this key for per-instance override.
     *
     * Non-instanceable keys are never exposed. Otherwise the admin checkbox
     * `expose_<key>` governs it, defaulting to the descriptor's exposedefault.
     *
     * @param string $key Registry key.
     * @return bool
     */
    public static function is_exposed(string $key): bool {
        $entry = self::get($key);
        if (!$entry || !self::is_available($key) || empty($entry['instanceable'])) {
            return false;
        }
        $flag = get_config(security::CONFIG_COMPONENT, self::EXPOSE_PREFIX . $key);
        if ($flag === false) {
            return (bool) $entry['exposedefault'];
        }
        return (int) $flag === 1;
    }

    /**
     * The effective value of a key: an exposed, set instance override wins;
     * otherwise the site config value; otherwise the registry default.
     *
     * Shared by branding (tokens/persona), the widget (behaviour) and tutor
     * application, so instance-over-site precedence lives in exactly one place.
     *
     * @param string $key Registry key.
     * @param array $instance Per-instance values keyed by registry key.
     * @return mixed
     */
    public static function effective(string $key, array $instance = []): mixed {
        if (!self::is_available($key)) {
            return self::default_for($key);
        }
        if (self::is_exposed($key) && array_key_exists($key, $instance)) {
            $value = $instance[$key];
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
        return security::get_config(self::sitekey($key), self::default_for($key));
    }

    /**
     * Whether a registry key is available in this installation.
     *
     * Premium-gated keys are unavailable unless the optional premium add-on
     * explicitly unlocks their feature.
     *
     * @param string $key Registry key.
     * @return bool
     */
    public static function is_available(string $key): bool {
        $entry = self::get($key);
        if (!$entry) {
            return false;
        }
        if (empty($entry['premiumfeature'])) {
            return true;
        }
        return premium::has_feature((string) $entry['premiumfeature']);
    }

    /**
     * Sanitise a raw value for a registry key according to its type.
     *
     * Delegates colour/CSS/font cleaning to {@see branding}; everything that
     * could land in an inline `style="…"` is filtered so it cannot break out of
     * the declaration. Returns null when the value is empty/invalid (caller then
     * stores nothing and the styles.css/site default stands).
     *
     * @param string $key Registry key.
     * @param mixed $value Raw value.
     * @return string|int|null Cleaned value, or null to store nothing.
     */
    public static function sanitise(string $key, mixed $value): string|int|null {
        $entry = self::get($key);
        if (!$entry) {
            return null;
        }
        switch ($entry['type']) {
            case 'colour':
                return branding::sanitise_colour((string) $value);
            case 'cssvalue':
                $clean = branding::sanitise_css_value((string) $value);
                return $clean === '' ? null : $clean;
            case 'font':
                $clean = branding::sanitise_font((string) $value);
                return $clean === '' ? null : $clean;
            case 'checkbox':
                return (int) ((int) $value === 1);
            case 'select':
                $value = (string) $value;
                return isset($entry['options'][$value]) ? $value : null;
            case 'text':
                $clean = trim((string) $value);
                return $clean === '' ? null : $clean;
            case 'textarea':
                $clean = trim((string) $value);
                return $clean === '' ? null : $clean;
            case 'coursescope':
                // The form hands back option keys, an older value or an import
                // hands back the stored string; both end up in the one shape
                // the engine reads. Without this case the default below
                // returned null and the value was dropped on save.
                $wish = is_array($value)
                    ? \local_elediaai_chatengine\local\knowledge_scope::from_selection($value)
                    : \local_elediaai_chatengine\local\knowledge_scope::parse((string) $value);
                return $wish->is_empty() ? null : $wish->as_string();
            default:
                // The 'file' type and anything else are not plain config values.
                return null;
        }
    }
}
