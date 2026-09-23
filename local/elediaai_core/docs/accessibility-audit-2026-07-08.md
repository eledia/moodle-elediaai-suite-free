# Accessibility Audit — LernHive UI Plugins

Stand: 2026-07-08

## Scope

Geprüft wurden die in T1.4 genannten UI-Flächen:

- `local_elediaai_strategy`
- `local_elediaai_questiongen`
- `mod_aichat`
- `mod_aifeedback`
- `block_elediaai_chat`

`local_lernhive` existiert im aktuellen Repository nicht als eigener Plugin-Ordner.

## Findings

### A11Y-001 — Strategy goal picker exposed buttons as a radiogroup

**Status:** fixed

`local_elediaai_strategy/templates/phase1.mustache` rendered goal cards inside a `role="radiogroup"` but each option used `aria-pressed`. The controls now expose `role="radio"` and `aria-checked`, and the AMD controller updates `aria-checked` with the visual active state.

### A11Y-002 — Questiongen progress actions used empty links for JS-only actions

**Status:** fixed

`local_elediaai_questiongen/templates/progress.mustache` used `href="#"` links for opening the question bank and restarting. These are now real buttons, and `local_elediaai_questiongen/state` performs the navigation from click handlers.

### A11Y-003 — Chat panels use labelled inputs and live regions

**Status:** no critical issue found

`mod_aichat` and `block_elediaai_chat` provide hidden labels for the textarea, ARIA labels for icon/submit controls, and polite live/status regions. No critical WCAG 2.2 issue was found in the static audit.

### A11Y-004 — AIFeedback review/download actions are regular links

**Status:** no critical issue found

The visible controls in `mod_aifeedback` are navigation/download links or POST forms with labels. No critical WCAG 2.2 issue was found in the static audit.

## Follow-up

- Run an interactive axe/keyboard pass in a Moodle browser session for the Strategy phase flow and Questiongen progress screen.
- Add Behat coverage for keyboard-only selection in Strategy phase 1 and for Questiongen progress actions once the UI test stack is available.
