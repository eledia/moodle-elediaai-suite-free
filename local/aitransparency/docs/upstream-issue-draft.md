# Upstream Moodle tracker issue — draft

Prepared for SUI-568 (Art. 50 EU AI Act, Spur 3). English, ready to file at
<https://tracker.moodle.org> by a human senior. After filing, link the MDL id in
`03-dev-doc.md` (`TODO(upstream-issue-id)`) and here.

- **Project:** Moodle
- **Component/s:** AI (core_ai)
- **Type:** Improvement
- **Affects Version/s:** 4.5, 5.x (current `main`, verified 2026-07-28)
- **Labels:** ai, provenance, c2pa, transparency

## Summary

Add a supported hook between an AI-generated file and its storage, e.g.
`\core_ai\hook\before_generated_file_stored`, so provider/third-party code can
embed content-provenance metadata (C2PA) into the file before it is written.

## Problem

`core_ai` offers no interception point between the model response and file
storage. The only hooks in `ai/classes/hook/` are admin/form hooks
(`after_ai_action_settings_form_hook`, `after_ai_provider_form_hook`,
`before_provider_disabled`, `before_provider_deleted`).

For image generation, every core provider (openai, gemini, azureai, awsbedrock)
runs, in its `process_generate_image`:

1. `(new \core_ai\ai_image($tempfile))->add_watermark()->save()` — a visible
   pixel watermark, and
2. `create_file_from_string(...)` to persist the file.

`\core_ai\ai_image::save()` re-encodes through GD (`imagepng()`/`imagejpeg()`/
`imagegif()`), which carries **no** metadata (no EXIF/XMP/JUMBF). Any incoming
C2PA manifest is therefore destroyed before the file is stored, and there is no
supported point at which a site could add its own manifest.

## Why this matters

The EU AI Act Art. 50(2) requires providers of generative AI to mark synthetic
output in a **machine-readable** format (transition deadline 2026-12-02). A
visible pixel watermark does not satisfy this. Sites and third-party providers
currently have no supported way to attach machine-readable provenance to
AI-generated files produced through `core_ai`, and cannot cover files produced
by core providers at all.

## Proposed solution

Dispatch a hook in the generate-image flow immediately **after**
`add_watermark()` and **before** the file is persisted, passing the pending file
(temp path or `stored_file`), the action metadata (provider, model,
`actionname`) and the target file record. Listeners may:

- embed a C2PA manifest into PNG/JPEG (or write a sidecar for other formats), and
- read any incoming provider manifest to record it as a `c2pa.ingredient`.

A generic `before_generated_file_stored` hook (not image-specific) would also let
future non-image file outputs be covered.

## Alternatives considered

- Rescuing the provider's incoming manifest past GD — not possible; GD drops all
  metadata on re-encode.
- Post-hoc stamping from `ai_action_register` — best-effort only: the register
  stores no file reference, and there is a race with the placement move.

## Contribution

We (eLeDia) are willing to provide the patch and tests. We already implement the
site-side signing/provenance in a local plugin and can validate the hook against
that integration.
