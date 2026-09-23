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
 * Language strings for the AI Sources plugin.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['feature_usagehint'] = 'In a course, open "AI Sources" in the course navigation.';
$string['guide_body'] = 'The tutor answers better when it knows the course - but only if someone tells it what it may read. That is this page.

In the course, "AI Sources" sits in the navigation. You tick activities and resources, and the index is built. You see how far the run has got, and you can preview what text was extracted from a file.

Three things worth doing:

- **Less and good beats a lot.** One well-written script is worth more than twenty slides of bullet points.
- **Check what came through.** A scanned PDF often yields nothing usable - the preview shows that immediately.
- **Reindex when material changes.** The index is a snapshot.

What to watch for: what you select here, the tutor can quote to learners. Material that is not meant for everyone does not belong in the selection.\n\nWhere the material goes is a setting: either to an external ingestion API (the default) or to LiteRAG on this site. Exactly one at a time, and switching means every course has to be ingested again.';
$string['guide_summary'] = 'You choose per course which material the AI may read. What is not selected is not indexed.';
$string['guide_title'] = 'What the tutor answers from';
$string['health_configure'] = 'Settings';
$string['health_destination'] = 'Ingestion destination';
$string['health_destination_ok'] = 'Reachable ({$a}).';
$string['health_destination_unconfigured'] = 'No destination is set up, so nothing is being indexed.';
$string['health_pending'] = 'Courses waiting to be indexed';
$string['health_pending_action'] = 'Index now';
$string['health_pending_detail'] = '{$a} course(s) are marked for indexing but have no index yet. Until they do, the tutor answers from the model alone there.';
$string['pluginname'] = 'eLeDia.ai | AI Sources';

// Settings.
$string['sink'] = 'Ingestion destination';
$string['sink_desc'] = 'Where course content is sent. Exactly one destination is active at a time; switching destinations re-indexes every marked course into the new one and leaves the previous one untouched.';
$string['sink_ingestionapi'] = 'eLeDia.ai ingestion API (external service)';
$string['sink_literag'] = 'LiteRAG (on this site)';
$string['sink_ingestionapi_baseurl'] = 'Service base URL';
$string['sink_ingestionapi_baseurl_desc'] = 'Base URL of the ingestion service, without a trailing path (e.g. http://rag-service:8001). The plugin appends the documented actions /documents/upsert, /documents/delete and /health.';
$string['sink_ingestionapi_apikey'] = 'API key';
$string['sink_ingestionapi_apikey_desc'] = 'The API key for authenticating with the ingestion service. Sent as X-API-Key header. Keys are issued per tenant.';
$string['sink_ingestionapi_notconfigured'] = 'Base URL or API key is missing.';
$string['sink_literag_missing'] = 'The LiteRAG plugin is not installed on this site.';
$string['sink_literag_nokey'] = 'LiteRAG is installed but its ingestion API key is not set.';
$string['head_sink'] = 'Destination';
$string['head_sink_desc'] = 'Choose where course content is indexed.';
$string['head_sink_ingestionapi'] = 'Destination: eLeDia.ai ingestion API';
$string['head_sink_ingestionapi_desc'] = 'Applies when the ingestion API is the selected destination.';
$string['head_sink_literag'] = 'Destination: LiteRAG';
$string['head_sink_literag_desc'] = 'Applies when LiteRAG is the selected destination. It needs no settings here: the route is derived from this site\'s address, and the API key is the one configured in LiteRAG itself.';
$string['allow_private_target'] = 'Allow private AI Sources target';
$string['allow_private_target_desc'] = 'Allow the configured AI Sources endpoint to use private hosts, internal service names or non-standard ports. Enable only when the service runs inside a trusted internal network, such as Docker or Kubernetes.';
$string['max_document_size_mb'] = 'Max Document Size (MB)';
$string['max_document_size_mb_desc'] = 'Maximum allowed document size in megabytes. Documents exceeding this limit will be skipped.';
$string['request_timeout_seconds'] = 'Request Timeout (seconds)';
$string['request_timeout_seconds_desc'] = 'HTTP request timeout in seconds for RAG API calls.';
$string['head_connection'] = 'Connection';
$string['head_connection_desc'] = 'Transport options that apply to whichever destination is selected.';
$string['head_courses'] = 'Course selection';
$string['head_courses_desc'] = 'Opt-in rules that decide which courses may be sent to the RAG service.';
$string['activitydefault'] = 'Activities without a decision';
$string['activitydefault_desc'] = 'What happens to activities in a released course while the teacher has not decided on them. Explicit per-activity decisions always win, and changing this setting never removes anything from the index — content is only removed when an activity is explicitly excluded. Note: after switching back to "included", undecided activities re-enter the index only on their next edit or a course reindex.';
$string['activitydefault_optout'] = 'Included until the teacher excludes them (opt-out)';
$string['activitydefault_optin'] = 'Not included until the teacher selects them (opt-in)';
$string['scorm_harvest_slidetext'] = 'Read on-screen text from SCORM packages';
$string['scorm_harvest_slidetext_desc'] = 'Besides the narration transcript, read the on-screen text of authored packages (currently Articulate Storyline). Navigation labels such as "Next" are filtered out, but the remaining text is still less coherent than the transcript. Switch off if retrieval becomes noisy; the transcript is never affected.';
$string['head_limits'] = 'Limits';
$string['head_limits_desc'] = 'Payload size and request runtime limits for ingestion tasks.';
$string['settings_hub_desc'] = 'Choose one AI Sources settings area.';
$string['settings_section_connection_desc'] = 'Ingestion destination and its endpoint settings.';
$string['settings_section_courses_desc'] = 'Pilot courses, category allow-list and test-phase lock.';
$string['settings_section_limits_desc'] = 'Document size and request timeout.';
$string['shell_tagline'] = 'AI Sources';
$string['shell_subtitle'] = 'Course content ingestion for external retrieval-augmented generation services.';
$string['shell_help_label'] = 'Help for AI Sources';
$string['nav_label'] = 'AI Sources sections';
$string['nav_settings'] = 'Settings';
$string['nav_reindex'] = 'Reindex';

// Reindex page.
$string['reindex'] = 'Reindex Course Content';
$string['reindexcourse'] = 'Reindex Course';
$string['reindex_btn'] = 'Reindex Course Content';
$string['reindex_force_btn'] = 'Reindex Course Content (force)';
$string['reindex_force_desc'] = 'The plain reindex skips activities whose content is unchanged since the last ingest. Force re-sends everything - use it when the destination lost data.';
$string['selectcourse'] = 'Select a course to reindex';
$string['reindexintro'] = 'Queue released courses for indexing or reindex one course manually by Moodle course ID.';
$string['manualreindex'] = 'Manual course reindex';
$string['manualreindex_desc'] = 'Use this for a targeted reindex of one course. Released courses can be queued together above.';
$string['courseid'] = 'Course ID';
$string['courseid_help'] = 'Enter the numeric ID of the course to reindex.';
$string['reindexresults'] = 'Reindex Results';
$string['purgeoldsink'] = 'Clear the previous destination';
$string['purgeoldsink_desc'] = 'This site switched away from {$a}. Its documents are still there — switching deliberately leaves them, because the old destination is often unreachable at that moment and a failing clean-up must not block the switch.';
$string['purgeoldsink_btn'] = 'Clear previous destination';
$string['purgeoldsinkconfirm'] = 'Remove the documents of {$a->courses} course(s) from {$a->sink}? This cannot be undone from here.';
$string['purgeoldsinkqueued'] = 'Queued clean-up of {$a} course(s) in the previous destination.';
$string['purgeoldsinknone'] = 'There is no previous destination waiting to be cleared.';
$string['purgeoldsinkunreachable'] = 'The previous destination does not answer ({$a}). Queued clean-up tasks will retry, but nothing will be removed while it stays unreachable.';
$string['purgeoldsinkfailed'] = 'Clearing course {$a->course} left {$a->failed} module(s) behind; the task will retry.';
$string['suite_feature_desc'] = 'Choose which course material the AI may use as a source, and see what it found.';
$string['suite_feature_detail'] = 'The tutor answers better when it knows the course. This tool decides what it may read: teachers pick the activities and resources per course, watch the indexing run and preview what was extracted before anyone asks a question. Nothing is indexed that was not chosen.';
$string['suite_feature_key_1'] = 'Pick per course which activities and resources feed the AI.';
$string['suite_feature_key_2'] = 'Follow the indexing run and re-run it when material changes.';
$string['suite_feature_key_3'] = 'Preview the extracted text before learners ask questions about it.';
$string['suite_feature_name'] = 'AI Sources';
$string['task_purge_old_sink'] = 'Clear one course from the previous AI Sources destination';
$string['recenterrors'] = 'Recent ingestion errors';
$string['recenterrors_desc'] = 'Activities whose last ingestion attempt failed. The previously indexed content, if any, is still in place.';
$string['reindexsuccess'] = 'Successfully ingested {$a} document(s).';
$string['reindexcomplete'] = 'Course reindex complete.';
$string['ingesting'] = 'Ingesting content for course: {$a}';
$string['unknownmodule'] = 'Module (cmid {$a})';
$string['pendingindexingtitle'] = 'Released courses waiting for indexing';
$string['pendingindexingcount'] = '{$a} course(s) are released for AI Sources but not indexed yet.';
$string['partialindexingtitle'] = 'Released courses only partly indexed';
$string['partialindexingcount'] = '{$a} course(s) are in the index, but some of their content is still missing.';
$string['pendingandpartialcount'] = '{$a->pending} course(s) not indexed yet, and {$a->partial} indexed only in part.';
$string['retriesexhausted'] = 'Skipped after {$a} failed attempts. Trigger a reindex for this course once the cause is fixed.';
$string['indexreleasedcourses'] = 'Index released courses now';
$string['pendingindexingqueued'] = 'Queued {$a} released course(s) for indexing.';
$string['indexingreadytitle'] = 'Released courses are indexed';
$string['indexingreadybody'] = 'There are currently no released courses waiting for indexing.';
$string['openreindex'] = 'Open reindex';

// Results table.
$string['modulename'] = 'Module';
$string['status'] = 'Status';
$string['details'] = 'Details';
$string['statussuccess'] = 'Success';
$string['statusskipped'] = 'Skipped';
$string['statuserror'] = 'Error';

// Messages.
$string['noextractor'] = 'No extractor available for this module type.';
$string['nocontent'] = 'No content to ingest.';
$string['documentsizeexceeded'] = 'Document size ({$a->size} MB) exceeds maximum ({$a->max} MB).';
$string['unsupportedcontenttype'] = 'Unsupported content type: {$a}';
$string['apiclienterror'] = 'RAG API error: {$a}';
$string['apinotconfigured'] = 'The selected ingestion destination is not configured. Please complete its settings.';
$string['invalidcourseid'] = 'Invalid course ID.';
$string['coursenotfound'] = 'Course not found.';
$string['deletemodule'] = 'Deleting module from RAG index: cmid {$a}';
$string['ingestionsuccess'] = 'Ingested: source_id={$a->source_id}, type={$a->content_type}, size={$a->size}';
$string['ingestionfailed'] = 'Ingestion failed: source_id={$a->source_id}, HTTP {$a->http_code}';
$string['ingestionmultisummary'] = 'Ingested {$a->sent} document(s), {$a->failed} failed';
$string['ingestionmultiskipped'] = '{$a} file(s) skipped.';

// Document formats (support matrix, AI-75).
$string['skipreason_unknowntype'] = 'Skipped "{$a->title}": the file type {$a->type} is not one of the supported document formats.';
$string['skipreason_notaccepted'] = 'Skipped "{$a->title}": the active destination ({$a->sink}) does not process {$a->type} at the moment.';
$string['skipreason_empty'] = 'Skipped "{$a}": the file is empty or could not be read.';
$string['skippedmore'] = 'And {$a} more file(s).';
$string['skippedfilelog'] = 'cmid {$a->cmid}: {$a->reason}';
$string['health_formats'] = 'Document formats';
$string['health_formats_detail'] = 'Currently exported: {$a}';
$string['deletionsuccess'] = 'Deleted from index: source_id={$a}';
$string['deletionfailed'] = 'Deletion failed: source_id={$a->source_id}, HTTP {$a->http_code}';
$string['taskingestion'] = 'AI Sources content indexing';
$string['taskdeletion'] = 'AI Sources content deletion';

// Course marking (opt-in ingestion).
$string['allcategories'] = 'Include all categories';
$string['allcategories_desc'] = 'Ingest courses from every category, including categories created later. The per-course setting still applies: a course explicitly excluded stays out. Leave this off to use the category list below instead.';
$string['enabledcategories'] = 'Ingested course categories';
$string['enabledcategories_desc'] = 'Only courses in the selected categories (or their subcategories) are sent to the RAG service. Ingestion is opt-in: with nothing selected, no course is ingested unless individually marked "Include" via the course\'s "AI Sources" setting.';
$string['searchcategories'] = 'Search categories';
$string['cfcategory'] = 'AI tutor';
$string['cffieldname'] = 'AI Sources';
$string['cffielddesc'] = 'Whether this course\'s content is sent to the AI tutor\'s knowledge base. "Default" follows the site\'s category settings; "Include" always sends; "Exclude" never sends.';
$string['coursenotmarked'] = 'Course is not marked for ingestion.';
$string['activitynotselected'] = 'Activity is not selected for ingestion.';
$string['activityhidden'] = 'Activity is hidden from learners; not part of the index.';
$string['contentunchanged'] = 'Unchanged since the last ingest - skipped.';
$string['activityexcluded'] = 'Activity is excluded from ingestion; removed from the index.';
$string['task_reconcile_all'] = 'Reconcile AI Sources course marking';
$string['task_cleanup'] = 'Clean up orphaned and hidden AI Sources content';
$string['task_converge_undecided'] = 'Queue undecided activities after a default change';
$string['pilotcourses'] = 'Pilot courses';
$string['pilotcourses_desc'] = 'Specific courses to ingest regardless of the category allow-list. Use the search field to select one or more courses for a controlled test/pilot phase.';
$string['searchcourses'] = 'Search courses';
$string['lockcoursemarking'] = 'Lock course marking (test phase)';
$string['lockcoursemarking_desc'] = 'When enabled, the per-course "AI Sources" setting has no effect at all — only the pilot-course list and the category allow-list decide what is ingested, and the course field is locked against teacher editing (visible read-only). Use this during a test phase so the set of ingested courses is controlled exclusively in this admin page. Existing per-course values are kept and become effective again when the lock is disabled.';

// Extractor content labels.
$string['alsoknownas'] = 'Also known as:';
$string['questionhint'] = 'Hint:';
$string['gradingcriteria'] = 'Grading criteria';
$string['contenttruncated'] = '[content truncated to fit the size limit]';
$string['contenttruncatedlog'] = 'Content truncated to the {$a->max} MB limit before sending: cmid {$a->cmid}';

// Activity selection (module form + course page).
$string['activityinclude'] = 'Include in the AI tutor\'s knowledge base';
$string['activityinclude_help'] = 'Whether this activity\'s content is sent to the knowledge base the AI tutor answers from. Unticking removes already indexed content of this activity. This only takes effect in courses that are released for ingestion.';
$string['activities_title'] = 'AI Sources — activity selection';
$string['activities_nav'] = 'AI Sources';
$string['activities_intro'] = 'Choose which activities of this course are sent to the AI tutor\'s knowledge base. Changes take effect with the next background run.';
$string['activities_mode_optout'] = 'New activities are included automatically until excluded here.';
$string['activities_mode_optin'] = 'New activities are only included once selected here.';
$string['activities_unsupported'] = 'Not supported';
$string['activities_unsupported_hint'] = 'No extractor can read this activity type; it is never sent.';
$string['activities_excluded_badge'] = 'Excluded';
$string['activities_toggle_aria'] = 'Toggle ingestion of {$a}';
$string['activities_saved'] = 'Selection for "{$a}" saved.';
$string['activities_savefailed'] = 'The selection for "{$a}" could not be saved.';
$string['activities_nosections'] = 'This course has no activities yet.';
$string['activities_filter'] = 'Filter activities';
$string['activities_reset'] = 'Reset';
$string['activities_reset_aria'] = 'Reset {$a} to the site default';
$string['activities_bulk_aria'] = 'Bulk actions for {$a}';
$string['activities_bulk_include'] = 'Include all';
$string['activities_bulk_exclude'] = 'Exclude all';
$string['activities_bulk_saved'] = 'Selection saved for {$a} activities.';
$string['activities_status_indexed'] = 'Indexed';
$string['activities_status_error'] = 'Error';
$string['activities_status_unknown'] = 'Unknown';

// Dry run.
$string['preview_title'] = 'Dry run';
$string['preview_intro'] = 'What would be sent to the knowledge base for this activity right now. Nothing is sent by opening this page.';
$string['preview_link'] = 'Dry run';
$string['preview_aria'] = 'Dry run for {$a}';
$string['preview_destination'] = 'Destination';
$string['preview_index_state'] = 'In the index';
$string['preview_fingerprint'] = 'Content fingerprint';
$string['preview_metadata'] = 'Metadata sent alongside';
$string['preview_documents'] = 'Documents ({$a})';
$string['preview_nodocuments'] = 'Nothing would be sent for this activity.';
$string['preview_binary'] = 'Binary content — sent as-is, not shown here.';
$string['preview_truncated'] = 'Shown shortened. The size above is the real one; the destination receives the full content.';
$string['preview_backtocourse'] = 'Back to the activity selection';
$string['preview_verdict_ingest'] = 'Would be sent';
$string['preview_verdict_delete'] = 'Would be removed from the index';
$string['preview_verdict_skip'] = 'Would not be sent';
$string['preview_unchanged_note'] = 'Unchanged since the last ingest, so a reindex would skip this activity. Use "force" on the reindex page to send it anyway.';
$string['preview_index_absent'] = 'Not in the index.';
$string['preview_index_identical'] = 'Identical to what is in the index (sent {$a}).';
$string['preview_index_older'] = 'The index holds an older version, sent {$a}. Only its fingerprint is stored, not its wording — so it cannot be shown here.';
$string['preview_index_modelchanged'] = 'The content is unchanged, but the embedding model recorded for this course differs from the destination\'s. The next run therefore sends it again.';
$string['preview_index_otherdestination'] = 'The record describes the previous destination {$a->old}. Nothing is recorded for {$a->active} yet.';
$string['preview_index_lastattemptfailed'] = 'The last attempt failed on {$a->date}: {$a->error} The index still holds whatever was sent successfully before, if anything.';
$string['preview_lookup'] = 'Dry run for a single activity';
$string['preview_lookup_desc'] = 'Open the dry run from an activity id — the number a source id ends with, and the one the error report below lists.';
$string['preview_cmid'] = 'Activity id (cmid)';
$string['preview_open'] = 'Open dry run';

// Accessibility of the media inside a package.
$string['a11y_heading'] = 'Accessibility';
$string['a11y_complete'] = 'All {$a->total} media file(s) carry captions.';
$string['a11y_incomplete'] = '{$a->captioned} of {$a->total} media file(s) carry captions — this activity is not accessible.';
$string['a11y_undetermined'] = '{$a->captioned} of {$a->total} media file(s) carry captions. The rest could not be matched to a caption track, which is not the same as having none.';
$string['a11y_undetermined_transcript'] = '{$a->captioned} of {$a->total} media file(s) carry captions, and the package ships a transcript. For audio without a picture a transcript is an accepted alternative (WCAG 1.2.1) — but whether it covers these recordings cannot be told from the package.';
$string['a11y_files_missing'] = 'Without captions:';
$string['a11y_files_undetermined'] = 'Could not be matched to a caption track:';
$string['a11y_dangling'] = 'The package refers to caption files it does not contain — the captions exist, the export is broken:';
$string['a11y_files_transcript'] = 'Without captions — check that the transcript covers these:';

// Capabilities.
$string['elediaai_sources:reindex'] = 'Reindex course content for AI Sources';
$string['elediaai_sources:selectactivities'] = 'Select which activities of a course are ingested';

// Privacy.
$string['privacy:metadata'] = 'The AI Sources plugin stores which user last changed an activity\'s ingestion selection; course content is sent to the configured external service.';
$string['privacy:metadata:cm'] = 'Explicit per-activity ingestion decisions.';
$string['privacy:metadata:cm:usermodified'] = 'The user who last changed the decision.';
$string['privacy:metadata:cm:included'] = 'Whether the activity is included in or excluded from ingestion.';
$string['privacy:metadata:cm:timemodified'] = 'When the decision was last changed.';
$string['privacy:metadata:rag_service'] = 'Course content and module metadata are sent to the configured AI Sources service.';
$string['privacy:metadata:rag_service:site_url'] = 'The Moodle site URL is sent so the RAG service can verify the tenant.';
$string['privacy:metadata:rag_service:course_id'] = 'The Moodle course ID is sent to associate content with its course.';
$string['privacy:metadata:rag_service:cmid'] = 'The Moodle course module ID is sent to identify the activity.';
$string['privacy:metadata:rag_service:module_url'] = 'The Moodle module URL is sent for later citations and source links.';
$string['privacy:metadata:rag_service:content'] = 'Extracted course activity content is sent for parsing, chunking and indexing.';

// Subplugin types.
$string['subplugintype_aisourcesextractor'] = 'Content extractor';
$string['subplugintype_aisourcesextractor_plural'] = 'Content extractors';
