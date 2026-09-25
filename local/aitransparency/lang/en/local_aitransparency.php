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
 * English strings for local_aitransparency.
 *
 * @package    local_aitransparency
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['aitransparency:viewreport'] = 'View the AI transparency provenance report';
$string['guide_body'] = 'Art. 50 of the EU AI Act asks for two things: people must be able to tell that they are dealing with an AI, and AI-generated content must carry a machine-readable marking. The suite keeps a provenance record for every AI output for this purpose.

The report under "AI transparency" lists these records, newest first: which plugin produced the output, with which service and model, when, and whether it carried its marking.

What to look for:

- **"Not marked" is the finding that matters.** Such a record means the output went out without its Art. 50 marking.
- **Each record can be checked on its own.** "Open" shows the details of exactly that output.
- **The person is removed after a while.** After the configured number of days the link to the person who triggered the output is removed; the record itself stays.

The report is available to people with the capability "local/aitransparency:viewreport", by default managers and administrators.';
$string['guide_summary'] = 'Which AI outputs the site keeps a record of, and whether they went out marked under Art. 50.';
$string['guide_title'] = 'AI transparency: the provenance report';
$string['pluginname'] = 'AI transparency';
$string['privacy:metadata:local_aitransparency_file'] = 'Links a provenance record to a stored file so the marking survives copying, moving and backup. Contains no personal identifier.';
$string['privacy:metadata:local_aitransparency_file:filecontenthash'] = 'The content hash of the marked file.';
$string['privacy:metadata:local_aitransparency_file:timecreated'] = 'The time the file link was created.';
$string['privacy:metadata:local_aitransparency_rec'] = 'One provenance record per AI-generated output, kept as legal compliance evidence (Art. 50 EU AI Act). The user link is retained for legal compliance and anonymised once the retention window has passed.';
$string['privacy:metadata:local_aitransparency_rec:actionname'] = 'The kind of AI action, e.g. generate_text or generate_image.';
$string['privacy:metadata:local_aitransparency_rec:contenthash'] = 'A hash of the generated output, binding the record to the concrete content.';
$string['privacy:metadata:local_aitransparency_rec:contextid'] = 'The Moodle context in which the output was generated.';
$string['privacy:metadata:local_aitransparency_rec:model'] = 'The AI model identifier reported in the response.';
$string['privacy:metadata:local_aitransparency_rec:provider'] = 'The AI provider that produced the output.';
$string['privacy:metadata:local_aitransparency_rec:timecreated'] = 'The time the output was generated.';
$string['privacy:metadata:local_aitransparency_rec:userid'] = 'The user who triggered the generation.';
$string['setting_retentiondays'] = 'Provenance user retention (days)';
$string['setting_retentiondays_desc'] = 'Number of days after which the triggering user is anonymised on a provenance record. The record itself is kept as compliance evidence. Set to 0 to keep the user link indefinitely.';
$string['suite_feature_desc'] = 'Every AI output the site has recorded, with its Art. 50 marking — and the ones that went out unmarked.';
$string['suite_feature_detail'] = 'The provenance report lists every AI-generated output this site recorded a provenance record for: which plugin created it, with which service and model, and whether it carried its machine-readable marking under Art. 50 of the EU AI Act. A record without marking is shown as such, so gaps become visible instead of staying silent.';
$string['suite_feature_key_1'] = 'All provenance records of the site, newest first.';
$string['suite_feature_key_2'] = 'Outputs that went out without their Art. 50 marking stand out.';
$string['suite_feature_key_3'] = 'Each record can be verified individually by its identifier.';
$string['suite_feature_name'] = 'AI transparency';
$string['task_anonymise_records'] = 'Anonymise the user on expired AI provenance records';
$string['assettype_file'] = 'File';
$string['assettype_image'] = 'Image';
$string['assettype_text'] = 'Text';
$string['markstate_embedded'] = 'Embedded';
$string['markstate_failed'] = 'Marking failed';
$string['markstate_marked'] = 'Marked';
$string['markstate_pending'] = 'Not yet marked';
$string['markstate_sidecar'] = 'Marked (sidecar)';
$string['markstate_unsupported'] = 'Cannot be marked';
$string['notice'] = 'This content was generated by an AI.';
$string['notice_verify'] = 'Check the origin';
$string['notice_with_provider'] = 'This content was generated by an AI ({$a}).';
$string['report_col_asset'] = 'Type';
$string['report_col_component'] = 'Created by';
$string['report_col_markstate'] = 'Marking';
$string['report_col_provider'] = 'Service / model';
$string['report_col_time'] = 'When';
$string['report_col_verify'] = 'Record';
$string['report_empty'] = 'No AI output has been recorded on this site yet.';
$string['report_intro'] = 'Every AI output this site has recorded a provenance record for, newest first. A record that is not marked means the output went out without its Art. 50 marking.';
$string['report_open'] = 'Open';
$string['report_title'] = 'AI provenance records';
$string['report_total'] = 'records';
$string['report_unmarked'] = 'not marked';
$string['verify_assettype'] = 'Type of output';
$string['verify_component'] = 'Generated by';
$string['verify_component_unknown'] = 'Unknown component';
$string['verify_component_value'] = '{$a->component}';
$string['verify_contenthash'] = 'Content checksum';
$string['verify_found'] = 'This content was generated by an AI';
$string['verify_found_intro'] = 'It carries a marking under Art. 50 of the EU AI Act. The details below come from the provenance record written when it was generated.';
$string['verify_markstate'] = 'Marking';
$string['verify_noperson'] = 'Who generated the content is not shown here. The record answers whether an AI was involved, not who operated it.';
$string['verify_notfound'] = 'No provenance record with that identifier exists on this site.';
$string['verify_nouuid'] = 'No identifier was given, so there is nothing to look up.';
$string['verify_provider'] = 'Provider and model';
$string['verify_time'] = 'Generated at';
$string['verify_title'] = 'Origin of a piece of content';
