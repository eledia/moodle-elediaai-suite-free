# Changelog

## 0.12.3 - 2026-07-09

- Added the plugin-level GPL license file for Moodle Plugins Directory
  submission.
- Declared Moodle 5.2 support after the Marketplace preflight PHPUnit run
  passed on Moodle 5.2.

## 0.12.1 - 2026-06-28

- Renamed visible plugin labels, documentation and eLeDia.ai Tutor integration
  references to eLeDia.ai RagIngest.

## 0.12.0 - 2026-06-27

- Added the eLeDia.ai Tutor shell integration for eLeDia.ai RagIngest settings, reindex,
  and DevFlow pages.
- Reworked the settings page into one consolidated eLeDia.ai RagIngest page.
- Added searchable multi-select controls for ingested course categories and
  pilot courses.
- Added indexing status for released courses and a bulk action to queue pending
  released courses.
- Queue reconciliation automatically when eLeDia.ai RagIngest settings change.
- Added Privacy API metadata for the external RAG service transfer.
- Added explicit opt-in handling for private/local RAG targets.
- Improved reindex UX and course-state handling so failed reindex runs remain
  visible.
- Added and updated DevFlow documentation.
- Updated README documentation and added a German `README.de.md`.
- Cleaned PHP coding style with `moodlehq/moodle-cs`.
