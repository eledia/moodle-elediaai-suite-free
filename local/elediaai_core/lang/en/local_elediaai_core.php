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
 * English language strings for local_elediaai_core.
 *
 * Keys are sorted alphabetically.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['dashboard_audience_all'] = 'All';
$string['dashboard_kind_incourse'] = 'Available in your courses';
$string['dashboard_kind_incourse_intro'] = 'These are not places you go — they appear inside a course, where you teach. Each card says where to find it.';
$string['dashboard_kind_page'] = 'Tools you open';
$string['dashboard_kind_page_intro'] = 'Each of these has a page of its own. The card takes you straight there.';
$string['dashboard_matchcount'] = '{$a} features match.';
$string['dashboard_nomatch'] = 'Nothing here matches that. Try fewer words, or a different group.';
$string['dashboard_search_button'] = 'Search';
$string['dashboard_search_label'] = 'Search the AI features';
$string['dashboard_search_placeholder'] = 'What do you want to do?';
$string['dashboard_showall'] = 'Show everything again';
$string['error_extract_unsupported'] = 'Cannot extract text from activity type "{$a}". Supported types: Page, Book, File, Folder, Lesson.';
$string['error_premiumrequired'] = 'This feature is not unlocked. It is part of the AI suite\'s premium scope; an administrator releases it in the premium add-on settings.';
$string['error_quota_token_exceeded'] = 'You have reached your AI token limit of {$a->limit} tokens for this {$a->window}. Please try again later.';
$string['error_quota_unavailable'] = 'The AI token quota could not be checked right now. Please try again later.';
$string['health_heading'] = 'How the suite is doing';
$string['health_intro'] = 'Every line below comes from the plugin it is about. Nothing here is guessed on its behalf.';
$string['health_none'] = 'No plugin reports its state. That is not a fault - reporting is optional - but it means this page cannot tell you anything.';
$string['health_provider_failed'] = 'Report from {$a} failed';
$string['health_status_disabled'] = 'Switched off';
$string['health_status_error'] = 'Broken';
$string['health_status_ok'] = 'Working';
$string['health_status_unconfigured'] = 'Not set up';
$string['health_status_warning'] = 'Worth knowing';
$string['health_summary_attention'] = '{$a} thing(s) need attention.';
$string['health_summary_quiet'] = 'Nothing needs attention. {$a} check(s) reported.';
$string['launcher_heading'] = 'AI Suite overview';
$string['nav_aria_label'] = 'AI Suite sections';
$string['nav_audit'] = 'Audit';
$string['nav_help'] = 'Help';
$string['nav_infra_health'] = 'Health';
$string['nav_infra_literag'] = 'LiteRAG';
$string['nav_infra_mcp'] = 'MCP';
$string['nav_infra_sources'] = 'AI Sources';
$string['nav_infra_tutor'] = 'Tutor setup';
$string['nav_overview'] = 'Overview';
$string['premium_required_heading'] = 'Not unlocked';
$string['shell_aitutor_label'] = 'eLeDia.ai Tutor';
$string['shell_help_label'] = 'Help — AI Suite';
$string['shell_name'] = 'eLeDia.ai | AI Suite';
$string['help_heading'] = 'Help';
$string['help_intro'] = 'Read the AI Suite handbook without leaving the AI Suite navigation.';
$string['audit_unavailable_heading'] = 'AI audit not available';
$string['audit_unavailable_message'] = 'The AI audit report builds on Moodle\'s core AI usage register, which was introduced in Moodle 5.0. This site runs an older Moodle version, so the audit report is not available. All other AI Suite features remain fully usable.';
$string['audit_unavailable_title'] = 'AI audit';
$string['audit_access'] = 'Who may view the audit';
$string['audit_access_adminonly'] = 'Site administrators only';
$string['audit_access_corecap'] = 'Users with Moodle AI usage report permission';
$string['audit_access_teacherowncourses'] = 'Teachers for their own courses, plus AI usage report users';
$string['audit_access_desc'] = 'Controls who may open the audit. In teacher mode, teachers see only entries from courses where they are enrolled as teachers; users with moodle/ai:viewaiusagereport still see everything.';
$string['audit_action_explain_text'] = 'Explain text';
$string['audit_action_generate_image'] = 'Generate image';
$string['audit_action_generate_text'] = 'Generate text';
$string['audit_action_summarise_text'] = 'Summarise text';
$string['audit_actor_anonymized_role'] = '{$a}';
$string['audit_actor_anonymized_unknown'] = 'Role not available';
$string['audit_actor_not_recorded'] = 'Not recorded';
$string['audit_actor_not_recorded_help'] = 'This action was recorded without a user identification. Prompt, answer, place and time are kept; who asked is not. Chat turns are recorded this way.';
$string['audit_anonymize_users'] = 'Anonymise users in the audit';
$string['audit_anonymize_users_desc'] = 'Replaces real user names in the audit table with a role/context label. Moodle core still stores the original user id in its core AI audit tables.';
$string['audit_col_actor'] = 'Actor';
$string['audit_col_action'] = 'Action';
$string['audit_col_context'] = 'Content name';
$string['audit_col_error'] = 'Error message';
$string['audit_col_model'] = 'Model';
$string['audit_col_prompt'] = 'Prompt';
$string['audit_col_provider'] = 'Service';
$string['audit_col_provider_model'] = 'Service / model';
$string['audit_col_response'] = 'Response';
$string['audit_col_success'] = 'OK';
$string['audit_col_status'] = 'Status';
$string['audit_col_tokens'] = 'Tokens';
$string['audit_col_tokens_help'] = 'Prompt tokens / response tokens';
$string['audit_component_empty'] = 'No AI requests were booked in this period.';
$string['audit_component_eyebrow'] = 'Consumption';
$string['audit_component_intro'] = 'Which feature actually consumes the credit, over the last {$a} days. Counted from the suite\'s own turn log, one row per request — not from the credit ledger, whose component column only records whichever feature booked last.';
$string['audit_component_note'] = 'Limited by how long questions and answers are kept; a shorter retention shortens this list too.';
$string['audit_component_requests'] = '{$a} requests';
$string['audit_component_title'] = 'Which feature is being used';
$string['audit_component_unknown'] = 'Not attributed';
$string['audit_context_unknown'] = 'Unknown context';
$string['audit_heading'] = 'AI audit';
$string['audit_metric_failed'] = 'Failed';
$string['audit_metric_hidden'] = 'Hidden';
$string['audit_metric_requests'] = 'Requests';
$string['audit_metric_support'] = 'Summaries and explanations';
$string['audit_metric_tokens'] = 'Tokens';
$string['audit_overview_intro'] = 'Four views, one question each: what learners asked, what the AI did, every single request it made, and who is allowed to read all of that.';
$string['audit_preview_close'] = 'Close';
$string['audit_preview_open'] = 'Show full text';
$string['audit_preview_open_error'] = 'Show error message';
$string['audit_preview_title_error'] = 'Error message';
$string['audit_preview_title_prompt'] = 'Prompt';
$string['audit_preview_title_response'] = 'AI response';
$string['audit_quick_all'] = 'All';
$string['audit_quick_failed'] = 'Only errors';
$string['audit_quick_participants'] = 'Only participants';
$string['audit_report_filename'] = 'elediaai-ai-audit';
$string['audit_report_name'] = 'eLeDia.ai Audit';
$string['audit_settings_heading'] = 'AI audit';
$string['audit_settings_heading_desc'] = 'These settings control what the AI Suite audit displays. Moodle core AI may still write its own audit data independently.';
$string['audit_show_error'] = 'Show error details';
$string['audit_show_error_desc'] = 'Allows failed rows to open the full error message.';
$string['audit_show_prompt'] = 'Show prompts';
$string['audit_show_prompt_desc'] = 'Shows a prompt preview icon in the audit table. Prompts may contain personal data.';
$string['audit_show_response'] = 'Show AI responses';
$string['audit_show_response_desc'] = 'Shows a response preview icon in the audit table.';
$string['audit_show_tokens'] = 'Show token values';
$string['audit_show_tokens_desc'] = 'Shows prompt/completion tokens in the overview and table.';
$string['audit_success_no'] = 'Failed';
$string['audit_success_yes'] = 'Succeeded';
$string['dashboard_empty'] = 'No AI features have been registered yet.';
$string['dashboard_heading'] = 'eLeDia.ai | AI Suite';
$string['dashboard_subtitle'] = 'Bundled AI features you can switch on and off in one place.';
$string['feature_backtosuite'] = 'Back to AI Suite';
$string['feature_benefit_title'] = 'Value and positioning';
$string['feature_launch_desc'] = 'Open the live tool and continue in the feature workflow.';
$string['feature_launch_title'] = 'Start';
$string['feature_install'] = 'Install plugin';
$string['feature_install_desc'] = 'Open the plugin source page, add it to this Moodle instance and purge caches. Once installed, the live tile replaces this roadmap card automatically.';
$string['feature_install_title'] = 'Not installed yet';
$string['feature_audience_admin'] = 'Admin tools';
$string['feature_audience_designer'] = 'Course design';
$string['feature_audience_legend'] = 'Tool groups';
$string['feature_audience_student'] = 'Students';
$string['feature_audience_teacher'] = 'Teachers';
$string['feature_keyfeatures_title'] = 'Key functions';
$string['feature_notfound'] = 'This AI feature is not available.';
$string['feature_open'] = 'Open feature';
$string['feature_origin_eledia_note'] = 'Original development within the eLeDia.ai Suite. Where external libraries are used, their licence and attribution notices remain in the individual plugin.';
$string['feature_origin_eledia_title'] = 'eLeDia.ai Suite';
$string['feature_origin_intro'] = 'These notes show whether a tool is original eLeDia work or builds on an adopted or forked open-source plugin.';
$string['feature_origin_title'] = 'Source & credits';
$string['feature_status_coming'] = 'Coming soon';
$string['feature_status_install'] = 'Installable';
$string['feature_status_locked'] = 'Not licensed';
$string['feature_locked_hint'] = 'Not part of this site\'s licence.';
$string['feature_status_ready'] = 'Available';
$string['feature_audit_desc'] = 'Four views on the AI in this site: what learners asked, what the AI did, every single request, and who may read all of that.';
$string['feature_audit_detail'] = 'The audit keeps three separate logs, cut by how long they may live and whether they carry a person. The action log names the person and is kept longest, because it records who the AI acted for. Questions and answers carry free text, no person and the shortest window — a salted pseudonym stands in for the asker, which is what makes the per-course view possible without naming anybody. The provenance record holds only a hash and outlives the content it proves. Every call the suite makes passes one credit chokepoint, so the report can say which feature consumed what — a question Moodle\'s own action register cannot answer, because it has no component of its own. What it does not cover: the translation filter reaches DeepL on its own separate credit, and indexing and retrieval make no LLM call of their own to count. Moodle\'s own register is shown beside it, unfiltered: it holds every AI action of the site, the suite\'s included, and carries no component to tell them apart. All three retentions are configurable.';
$string['feature_audit_key_1'] = 'What learners asked — per course, which topics your material could not answer and where it exists but does not hold. Without names.';
$string['feature_audit_key_2'] = 'What the AI did — every tool call that changed something, and the person it acted for.';
$string['feature_audit_key_3'] = 'Every AI request — question, answer, provider, model, status and cost, as far as the site settings allow.';
$string['feature_audit_name'] = 'AI Audit';
$string['feature_coursegen_desc'] = 'Generate course drafts from a topic: sections, introductions and activity ideas.';
$string['feature_coursegen_install_desc'] = 'Install the eLeDia.ai course author to draft complete courses from a brief: sections, activities and learning objectives.';
$string['feature_coursegen_detail'] = 'Courses is intended to help teachers structure new learning offers quickly. From a topic it creates a first didactic draft with sections, introductions and suitable activity ideas. Its main value is in the concept phase: less blank-page friction, faster starts and more comparable course drafts.';
$string['feature_coursegen_key_1'] = 'Generates a first course outline from a topic, including sections and introductory text.';
$string['feature_coursegen_key_2'] = 'Suggests activity ideas for each section so teachers can refine the draft instead of starting from a blank course.';
$string['feature_coursegen_key_3'] = 'Designed as a concept-phase tool; publication remains under teacher/admin control.';
$string['feature_coursegen_name'] = 'AI Course Author';
$string['feature_questiongenerator_name'] = 'AI Questions';
$string['feature_settings'] = 'Settings';
$string['feature_translate_desc'] = 'Translate course content into other languages and manage review, glossary and import workflows.';
$string['feature_translate_detail'] = 'Translate accelerates multilingual course delivery. Existing Moodle content can be marked, translated, reviewed, imported and maintained through central workflows, with DeepL support when configured. Strategically, it helps with internationalisation, rollouts across multiple audiences and faster maintenance of multilingual learning materials.';
$string['feature_translate_install_desc'] = 'Install eledia Translate to translate course content, review changes, manage glossaries and run import workflows.';
$string['feature_translate_key_1'] = 'Marks translatable Moodle content with stable spans and target-language metadata.';
$string['feature_translate_key_2'] = 'Supports translation review, import/export workflows, glossaries and provider-backed translation such as DeepL.';
$string['feature_translate_key_3'] = 'Can limit target languages per course while still supporting site-wide translation workflows.';
$string['feature_translate_name'] = 'AI Translation';
$string['feature_tutor_desc'] = 'Personal AI tutor for contextual learner support. Coming soon.';
$string['feature_tutor_detail'] = 'Tutor is intended to support learners more individually than a general chat. It answers questions in course context, helps with comprehension problems and can make learning paths more personal. Didactically, it is useful for self-directed learning phases where teachers are not continuously available.';
$string['feature_tutor_install_desc'] = 'Install eLeDia.ai | Tutor to add contextual learner support with course-aware chat and guided assistance.';
$string['feature_tutor_key_1'] = 'Provides a learner-facing AI tutor block with contextual conversation support.';
$string['feature_tutor_key_2'] = 'Supports configurable persona, branding, consent and long-term memory options.';
$string['feature_tutor_key_3'] = 'Can connect to external RAG/MCP services when those separately installed plugins are available.';
$string['feature_tutor_name'] = 'AI Tutor';
$string['feature_tutorpremium_name'] = 'AI Tutor Premium';
$string['launcher_comingsoon'] = 'Coming soon';
$string['launcher_subtitle'] = 'Bundled AI tools, one click away.';
$string['launcher_title'] = 'AI Suite';
$string['pluginname'] = 'eLeDia.ai | AI Suite';
$string['privacy:metadata'] = 'The AI Suite stores compact per-user token counters for quota enforcement. Individual AI features and Moodle core AI logging declare their own additional privacy data.';
$string['privacy:metadata:core_ai'] = 'The AI audit reads its base data directly from Moodle core\'s AI subsystem (core_ai), which logs every AI action and its prompt, response and token usage. Responsibility for exporting and deleting that data stays with Moodle core; this plugin only displays it and stores its own site-wide audit display settings, which contain no personal data.';
$string['privacy:metadata:local_elediaai_core_turn'] = 'One row per AI turn of the suite: what was asked, what was answered, which feature asked and what it was grounded on. The row carries no user id. It does carry a salted pseudonym of the asker, which no interface displays or resolves, so that the course report can tell several askers from one and an erasure request can still be answered. That makes the data pseudonymous, not anonymous.';
$string['privacy:metadata:local_elediaai_core_turn:askerkey'] = 'A salted hash of the asking user, never displayed and never resolved. It exists so that this data can be exported and erased on request.';
$string['privacy:metadata:local_elediaai_core_turn:component'] = 'The suite feature that made the request.';
$string['privacy:metadata:local_elediaai_core_turn:courseid'] = 'The course the request was made in, when it was made in one.';
$string['privacy:metadata:local_elediaai_core_turn:origin'] = 'Whether the answer came from indexed course material, from the model itself or from Moodle data read through a tool.';
$string['privacy:metadata:local_elediaai_core_turn:prompt'] = 'The question as it was asked. Free text can contain personal data whatever the schema says, which is why this table has the shortest retention of the three.';
$string['privacy:metadata:local_elediaai_core_turn:response'] = 'The answer as it was given.';
$string['privacy:metadata:local_elediaai_core_turn:timecreated'] = 'When the turn happened.';
$string['privacy:metadata:local_elediaai_core_turn:topic'] = 'The canonical topic the backend assigned to the question.';
$string['privacy:path:turns'] = 'AI questions and answers';
$string['privacy:metadata:local_elediaai_core_usage'] = 'Per-user token counters used to enforce hourly, daily and monthly AI token limits.';
$string['privacy:metadata:local_elediaai_core_usage:completiontokens'] = 'Number of completion tokens counted in the quota window.';
$string['privacy:metadata:local_elediaai_core_usage:component'] = 'The eLeDia.ai component that last updated the quota row.';
$string['privacy:metadata:local_elediaai_core_usage:prompttokens'] = 'Number of prompt tokens counted in the quota window.';
$string['privacy:metadata:local_elediaai_core_usage:requestcount'] = 'Number of successful AI requests counted in the quota window.';
$string['privacy:metadata:local_elediaai_core_usage:reservedtokens'] = 'Tokens reserved for in-flight AI requests in the quota window.';
$string['privacy:metadata:local_elediaai_core_usage:rolebucket'] = 'Whether the user was counted against the student or teacher quota bucket.';
$string['privacy:metadata:local_elediaai_core_usage:totaltokens'] = 'Total number of prompt and completion tokens counted in the quota window.';
$string['privacy:metadata:local_elediaai_core_usage:userid'] = 'The user the quota counter belongs to.';
$string['privacy:metadata:local_elediaai_core_usage:windowstart'] = 'Start timestamp of the quota window.';
$string['privacy:metadata:local_elediaai_core_usage:windowtype'] = 'The quota window type: hour, day or month.';
$string['quota_completion_buffer'] = 'Reserved completion tokens per request';
$string['quota_completion_buffer_desc'] = 'Completion tokens reserved in addition to the estimated prompt tokens before an external AI request is sent. Each request with an enforced limit therefore needs prompt estimate plus this buffer as free headroom. Set to 0 to reserve only the prompt estimate.';
$string['quota_image_cost_256'] = 'Image credit cost up to 256px';
$string['quota_image_cost_512'] = 'Image credit cost up to 512px';
$string['quota_image_cost_1024'] = 'Image credit cost up to 1024px';
$string['quota_image_cost_desc'] = 'Token-equivalent credits charged per generated image. These credits use the same quota currency as text tokens. Set to 0 to use the built-in default.';
$string['quota_limit_desc'] = 'Maximum text tokens and image token-equivalent credits. Set to 0 for unlimited.';
$string['quota_settings_desc'] = 'Limits are enforced server-side before eLeDia.ai sends an external AI request. Text estimates and image credits are booked atomically per user in hourly, daily and monthly windows, so parallel requests cannot overdraw the limit; actual usage is corrected after the response. 0 means unlimited.';
$string['quota_settings_heading'] = 'AI credit limits';
$string['quota_student_day'] = 'Student token limit per day';
$string['quota_student_hour'] = 'Student token limit per hour';
$string['quota_student_month'] = 'Student token limit per month';
$string['quota_teacher_day'] = 'Teacher token limit per day';
$string['quota_teacher_hour'] = 'Teacher token limit per hour';
$string['quota_teacher_month'] = 'Teacher token limit per month';
$string['quota_window_day'] = 'day';
$string['quota_window_hour'] = 'hour';
$string['quota_window_month'] = 'month';
$string['showplaceholders'] = 'Preview features that are not installed';
$string['showplaceholders_desc'] = 'Shows tiles for suite features that are not installed on this site ("Coming soon", "Installable"). Useful on demo and sales instances; leave it off on customer systems, where these tiles announce tools the customer does not have. Installed premium features outside the licence are always shown, marked as not licensed.';
$string['support_handbook_pending_detail'] = 'The handbook is not available yet.';

// Entwicklerseite (task24, adr05 §6). Hinter dem Setting "developerdocs"
// und moodle/site:config; zeigt den Design-System-Vertrag an der laufenden
// Installation.
$string['developer_blocks'] = 'Building blocks, live';
$string['developer_blocks_intro'] = 'Rendered by this installation, with the styles a plugin actually gets.';
$string['developer_col_origin'] = 'Origin';
$string['developer_col_role'] = 'Role';
$string['developer_col_token'] = 'Token';
$string['developer_col_value'] = 'Resolved value';
$string['developer_contract'] = 'The contract';
$string['developer_contract_missing'] = 'The section "Design-System" was not found in 03-dev-doc.md.';
$string['developer_disabled'] = 'The page "Development" is not switched on for this site. It lives under Site administration > Plugins > Local plugins > eLeDia.ai | AI Suite as "Show developer documentation".';
$string['developer_environment'] = 'Environment';
$string['developer_heading'] = 'Development';
$string['developer_origin_core'] = 'Core';
$string['developer_origin_other'] = 'overridden';
$string['developer_origin_theme'] = 'Theme';
$string['developer_origin_unknown'] = 'not readable';
$string['developer_scales'] = 'The scales, rendered';
$string['developer_scales_intro'] = 'Every step at the size it actually resolves to in this browser.';
$string['developer_subtitle'] = 'The design system of the suite, shown against this installation.';
$string['developer_tokens'] = 'Tokens, resolved';
$string['developer_tokens_intro'] = 'Read from the running page, not from the source. "Origin" names the stylesheet that won.';
$string['developerdocs'] = 'Show developer documentation';
$string['developerdocs_desc'] = 'Adds the page "Development" for administrators: the design tokens of the suite resolved against this installation, the scales rendered, and the contract every suite plugin follows. Off by default — it is written for people who build plugins, not for people who run the site.';
$string['developerdocs_heading'] = 'For developers';
$string['developerdocs_heading_desc'] = 'Nothing here changes how the suite behaves for its users.';
$string['topic_suite_admin_body'] = 'Completeness matters more than order, but this order retraces the fewest steps:

1. Configure at least one provider under **Site administration > AI > AI providers**. No plugin in the suite carries its own API keys.
2. Open the AI Suite overview and switch on the features this site should offer.
3. For the tutor, set up the retrieval and tool backends and index at least one course. The tutor dashboard lists what is not ready.
4. Set a site-wide daily budget, so that one enthusiastic course cannot spend the year.

Then tell people. An announcement written under **Announcements** appears in the help drawer of everyone it is meant for, which reaches them earlier than a support ticket does.';
$string['topic_suite_admin_summary'] = 'A provider in Moodle core first, then the suite features, then the tutor backends. The overview and the tutor dashboard say what is still missing.';
$string['topic_suite_admin_title'] = 'Switching the suite on';
$string['topic_suite_developer_body'] = 'The suite has a design layer: font sizes, colours, spacing and radii are declared in one place and only read everywhere else. A plugin that keeps to it looks right under someone else\'s theme too.

The page **Development** shows that for this site, not in the abstract:

- what each token resolves to right now, and which stylesheet it came from,
- the eight steps of the type scale at the size they actually reach in this browser,
- the building blocks of the suite, rendered live,
- and the contract: what a plugin may set and what it may not.

Found under **Site administration > Plugins > Local plugins > eLeDia.ai | AI Suite**, once "Show developer documentation" is switched on there - the same setting that makes this chapter visible.

A chapter for people who build plugins. Anyone who only runs the site loses nothing by leaving the setting off.';
$string['topic_suite_developer_summary'] = 'Tokens, scales and the contract every suite plugin follows - shown against this installation.';
$string['topic_suite_developer_title'] = 'For developers: the design system';
$string['topic_suite_limits_body'] = 'When an answer does not arrive, the message usually says which of these it was.

- **A limit was reached.** Sites set a daily budget, and a course or activity may set a stricter one. Waiting until tomorrow works; a teacher or administrator can raise it.
- **The service cannot be reached.** Nothing to fix from your side — report it to whoever administers the site.
- **The feature is not set up yet.** Common shortly after a site installs the suite.

Answers can also be wrong while sounding certain. Treat what you get as a draft rather than a source, and check anything you would otherwise have looked up — especially numbers, dates and quotations.';
$string['topic_suite_limits_summary'] = 'AI answers cost the site money, so there are daily budgets. A refusal is usually a limit, a missing setting, or a service that cannot be reached.';
$string['topic_suite_limits_title'] = 'Why an answer sometimes does not come';
$string['topic_suite_privacy_body'] = 'Every AI request goes through the Moodle server. Your browser never talks to the AI service, which is why its credentials never reach your computer.

What is kept where:

- **In Moodle:** pointers to your conversations, the question and the answer without your name, the record that you confirmed the privacy notice, and usage counters. The chapter “What the AI suite records” says exactly what goes where and for how long.
- **At the AI service:** the transcripts themselves, if the service your site uses stores them at all.

You can have your own data removed. The tutor has a privacy area with **Delete all my tutor data**, and deleting your Moodle account removes the same local records automatically.

Two habits worth keeping: do not type anything into an AI feature that you would not put in a course forum; and remember that while other learners cannot read your conversations, administrators can see that AI was used and how much.';
$string['topic_suite_privacy_summary'] = 'Your text goes from the Moodle server to the AI service your site has configured — never from your browser directly. Moodle keeps only what it needs to show you your own history.';
$string['topic_suite_privacy_title'] = 'What happens to what you type';
$string['topic_suite_teacher_body'] = 'The parts you are most likely to want:

- **The tutor block.** Add it to your course, switch on course context, and learners can ask about your material instead of about the internet.
- **Question generation.** Produce draft questions from a text or from course material and review them in the question bank. They are drafts: nothing lands in a quiz without you.
- **AI feedback and AI text questions.** A model comments on free text against criteria you write.
- **The teacher dashboard.** Inside a course, it collects what the AI features have been doing there.

All of it stays inside the usual Moodle permissions. The tutor reaches material through the asking person\'s own access, so it cannot show somebody something they could not open themselves.';
$string['topic_suite_teacher_summary'] = 'Draft questions from your own material, AI comments on free text, and a tutor you can place in your course.';
$string['topic_suite_teacher_title'] = 'What the suite gives you as a teacher';
$string['topic_suite_what_body'] = 'The AI Suite is not a separate website. It is a set of features inside this Moodle that happen to use a language model.

What you may meet, depending on what your site has switched on:

- **The tutor** — a chat you can ask about your course, from a block in the course or from its own page.
- **AI activities** — a chat activity, a feedback activity, and free-text questions that a model comments on.
- **Authoring help** — draft questions and course outlines, for the people who build courses.
- **Translation** — a filter that translates content as it is displayed.

Not every site has all of them, and not every role sees all of them. The suite overview shows only what you are allowed to use, so a gap there means the feature is switched off or not meant for your role — not that something is broken.';
$string['topic_suite_what_summary'] = 'A group of AI features built into this Moodle: a tutor to ask, help with writing questions and feedback, and translation. They share one starting point and one set of rules.';
$string['topic_suite_what_title'] = 'What the AI Suite is';
$string['topic_suite_where_body'] = 'Look at the top of any page, next to your user menu: a small pill with the AI mark. That is the AI Suite launcher, and it opens the overview.

The overview lists every AI feature you may use, each with a line about what it does. Clicking a tile opens it.

Two things are deliberately not on the overview, because they belong to a place rather than to the suite:

- The **tutor block** appears in the course or dashboard somebody put it in.
- **AI activities** appear in the course, in the section they were added to.

The launcher is hidden while you are logged out, and on the login, pop-up and maintenance pages.';
$string['topic_suite_where_summary'] = 'One pill in the top bar opens the overview. Everything else is reached from there, or sits in the course where it is used.';
$string['topic_suite_where_title'] = 'Where to find the AI features';
$string['contract_language_note_de'] = 'This contract text is maintained in German and is shown here as written.';
$string['topic_contract_chat_summary'] = 'How a plugin becomes a placement on the shared chat engine.';
$string['topic_contract_chat_title'] = 'Contract 4: offering a chat';
$string['topic_contract_explain_summary'] = 'How a plugin puts its own chapters into this handbook instead of carrying a help '
    . 'page.';
$string['topic_contract_explain_title'] = 'Contract 2: explaining itself';
$string['topic_contract_health_summary'] = 'How a plugin tells the core whether it is configured, idle or broken.';
$string['topic_contract_health_title'] = 'Contract 3: reporting its own state';
$string['topic_contract_overview_summary'] = 'The seven interfaces between a suite plugin, the core and its neighbours, and which '
    . 'of them are conventions rather than registrations.';
$string['topic_contract_overview_title'] = 'Contracts: how a plugin joins the suite';
$string['topic_contract_premium_summary'] = 'How a plugin asks whether a paid capability is available on this site.';
$string['topic_contract_premium_title'] = 'Contract 5: unlocking premium';
$string['topic_contract_quota_summary'] = 'The one call that books quota and writes the audit entry, and what happens when it is '
    . 'bypassed.';
$string['topic_contract_quota_title'] = 'Contract 6: calling the AI';
$string['topic_contract_register_summary'] = 'How a plugin appears on the dashboard: one class, one descriptor, no registration '
    . 'table.';
$string['topic_contract_register_title'] = 'Contract 1: registering a feature';
$string['topic_contract_shell_summary'] = 'The page shell the core owns, and what a plugin may add to it.';
$string['topic_contract_shell_title'] = 'Contract 7: looking like the suite';
$string['task_prune_turns'] = 'Delete expired AI questions and answers';
$string['task_prune_usage'] = 'Delete expired AI credit counters';
$string['turn_retentiondays'] = 'Keep questions and answers for';
$string['turn_retentiondays_desc'] = 'Days before a recorded AI question and its answer are deleted. 0 keeps them indefinitely. This log holds free text and therefore deserves the shortest of the three retentions; below 30 days the topic analysis in the course insights becomes unusable.';
$string['usage_retentiondays'] = 'Keep credit counters for';
$string['usage_retentiondays_desc'] = 'Days before a credit-ledger row is deleted. 0 keeps them indefinitely. The ledger is also the source of the "which feature is being used" view, so a shorter window shortens that report too.';
$string['retention_settings_desc'] = 'Three logs, three cleanup jobs, three retentions. The action log keeps the person and the longest window, because it is the record of who the AI acted for; questions and answers hold free text and get the shortest; the provenance record outlives the content it proves and is set in the AI Transparency plugin. 0 keeps a log indefinitely.';
$string['retention_settings_heading'] = 'Retention';
$string['audit_col_feature'] = 'Feature';
$string['audit_col_origin'] = 'Source';
$string['audit_col_time'] = 'Time';
$string['audit_col_topic'] = 'Topic';
$string['audit_core_report_intro'] = 'What other plugins asked, as Moodle recorded it. Requests the suite made are left out here: they go through Moodle\'s AI subsystem too and would otherwise appear twice, so each turn remembers the register row it produced and that row is hidden. Two exceptions, both honest ones — a chat turn never reaches this register at all, and once a turn passes its retention window its old register row returns here, because by then this is the only place it still exists.';
$string['audit_core_report_title'] = 'AI outside the suite';
$string['audit_origin_general'] = 'Model';
$string['audit_origin_grounded'] = 'Course material';
$string['audit_origin_mcp'] = 'Moodle data';
$string['audit_quick_chat'] = 'Chat turns';
$string['audit_quick_ungrounded'] = 'Not covered by material';
$string['audit_suite_report_intro'] = 'Every AI request the suite made: which feature asked, what was asked and answered, what the answer was grounded on and what it cost. No actor column — a chat turn reaches this log without a user identification, and nothing here resolves the pseudonym that replaces it.';
$string['audit_suite_report_title'] = 'Requests from the suite';
$string['turn_entity_title'] = 'AI turn';
$string['turn_report_filename'] = 'ai-turns';
$string['turn_report_name'] = 'AI turns of the suite';
$string['action_actor_anonymised'] = 'Link removed';
$string['action_actor_anonymised_help'] = 'The retention window for this action has passed, so the personal link was removed. The action itself is kept: an oversight record that disappears proves nothing.';
$string['action_actor_deleted'] = 'Deleted account';
$string['action_col_action'] = 'Action';
$string['action_col_actor'] = 'On behalf of';
$string['action_col_duration'] = 'Duration';
$string['action_col_kind'] = 'Kind';
$string['action_entity_title'] = 'AI action';
$string['action_eyebrow'] = 'What is recorded here';
$string['action_kind_read'] = 'Reads';
$string['action_kind_write'] = 'Writes';
$string['action_metric_failed'] = 'Failed';
$string['action_metric_people'] = 'People';
$string['action_metric_total'] = 'Actions';
$string['action_metric_writes'] = 'Of those writing';
$string['action_metric_writes_hint'] = '{$a} % of all actions';
$string['action_page_intro'] = 'Every action the assistant carried out through a tool, and the person it acted for. No questions or answers here — those are under “Every AI request”, and there without a person.';
$string['action_panel_intro'] = 'A state-changing action — a grade written, an activity created, a message sent — is an act performed on somebody\'s behalf. This is where it is on record.';
$string['action_panel_title'] = 'In numbers';
$string['action_place_unknown'] = 'Not recorded';
$string['action_quick_write'] = 'Only writing';
$string['action_report_filename'] = 'ai-actions';
$string['action_report_name'] = 'AI actions';
$string['action_retention_note'] = 'The personal link is removed after {$a} days; the action itself is kept.';
$string['action_retention_note_forever'] = 'The personal link is kept indefinitely.';
$string['action_retentiondays'] = 'Keep the personal link on actions for';
$string['action_retentiondays_desc'] = 'Days before the person is removed from a recorded AI action. The action itself is always kept — an oversight record that disappears on request proves nothing. 0 keeps the link indefinitely. This is the longest of the three retentions.';
$string['privacy:metadata:local_elediaai_core_action'] = 'One row per action the AI carried out through a tool on a person\'s behalf: which tool, whether it changed anything, where and when. No prompt or answer is stored. Because this is the oversight record, an erasure request removes the person from the row and keeps the action.';
$string['privacy:metadata:local_elediaai_core_action:courseid'] = 'The course the action touched.';
$string['privacy:metadata:local_elediaai_core_action:iswrite'] = 'Whether the tool changed anything.';
$string['privacy:metadata:local_elediaai_core_action:success'] = 'Whether the action completed.';
$string['privacy:metadata:local_elediaai_core_action:timecreated'] = 'When it happened.';
$string['privacy:metadata:local_elediaai_core_action:toolname'] = 'The tool the assistant called.';
$string['privacy:metadata:local_elediaai_core_action:userid'] = 'The person the AI acted for.';
$string['privacy:path:actions'] = 'AI actions';
$string['task_anonymise_actions'] = 'Remove the personal link from old AI actions';
$string['insights_backtocourse'] = 'Back to the course';
$string['insights_covered'] = 'Covered';
$string['insights_gap_askers'] = 'from {$a} people';
$string['insights_gap_hassource'] = 'Material exists: {$a}';
$string['insights_gap_nosource'] = 'No course material covers this';
$string['insights_gap_share'] = '{$a} % not covered';
$string['insights_gap_share_label'] = '{$a->topic}: {$a->percent} % of the answers were not covered by course material';
$string['insights_gaps_empty'] = 'Every topic asked in this period was answered from your course material.';
$string['insights_gaps_eyebrow'] = 'Gaps';
$string['insights_gaps_intro'] = 'Sorted by the share of answers your material could not cover, not by how often a topic was asked. The first row is the one most worth your time.';
$string['insights_gaps_title'] = 'Asked, but not answered by the material';
$string['insights_lead'] = 'Questions to the AI tutor, grouped by topic. Without names — this shows where your material holds and where it does not, never who asked.';
$string['insights_metric_askers'] = 'People asking';
$string['insights_metric_askers_hint'] = 'counted without naming anybody';
$string['insights_metric_followup'] = 'Asked again';
$string['insights_metric_followup_hint'] = 'the answer did not land';
$string['insights_metric_grounded'] = 'Covered by material';
$string['insights_metric_grounded_hint'] = 'answered from your course';
$string['insights_metric_questions'] = 'Questions';
$string['insights_notcovered'] = 'Not covered';
$string['insights_nothing_yet'] = 'No questions were asked in this period.';
$string['insights_period_180'] = 'Semester';
$string['insights_period_30'] = '30 days';
$string['insights_period_7'] = '7 days';
$string['insights_retention_note'] = 'Questions are kept for {$a} days and then deleted.';
$string['insights_retention_note_forever'] = 'Questions are kept indefinitely.';
$string['insights_tools_hint'] = 'Turn a gap into material:';
$string['insights_trend_eyebrow'] = 'Trend';
$string['insights_trend_intro'] = 'Questions per day over the last two weeks.';
$string['insights_trend_title'] = 'When the questions came';
$string['insights_unclear_empty'] = 'No topic was asked again after an answer from your material.';
$string['insights_unclear_eyebrow'] = 'Unclear';
$string['insights_unclear_intro'] = 'Answered from your material, and asked again within half an hour anyway. Here the content is not missing — the clarity is.';
$string['insights_unclear_meta'] = '{$a->questions} questions · {$a->askers} people';
$string['insights_unclear_share'] = '{$a} % asked again';
$string['insights_unclear_title'] = 'Material exists, but does not hold';
$string['insights_verbatim_badge'] = 'Recorded without names';
$string['insights_verbatim_empty'] = 'No questions to show.';
$string['insights_verbatim_eyebrow'] = 'In their own words';
$string['insights_verbatim_intro'] = 'The most recent questions, as they were asked.';
$string['insights_verbatim_title'] = 'The questions themselves';
$string['elediaai_core:viewaiactions'] = 'View the log of what the AI did';
$string['elediaai_core:viewcourseinsights'] = 'View course insights into AI questions';
$string['insights_metric_grounded_label'] = '{$a->covered} % of the answers came from your course material, {$a->open} % did not';
$string['insights_metric_questions_hint'] = 'in the last {$a} days';
$string['insights_trend_empty'] = 'No questions were asked in this period, so there is nothing to plot.';
$string['insights_trend_label'] = 'Questions per day: {$a->total} over {$a->days} days';
$string['insights_trend_peak'] = 'Busiest day: {$a->day} with {$a->count} questions';
$string['insights_unclear_share_label'] = '{$a->topic}: {$a->percent} % of the answers were followed by another question';
$string['insights_unclear_source'] = 'Material: {$a->material}';
$string['chooser_empty'] = 'No course has AI questions in this period.';
$string['chooser_eyebrow'] = 'Pick a course';
$string['chooser_intro'] = 'Ordered by how much was asked. The share on the right is the part your material could not answer — the higher it is, the more a look at that course is worth.';
$string['chooser_meta'] = '{$a->questions} questions · {$a->askers} people';
$string['chooser_page_intro'] = 'Pick a course to see its topics, gaps and questions.';
$string['chooser_title'] = 'Courses with AI questions';
$string['chooser_uncovered'] = '{$a} % not covered';
$string['entry_actions_text'] = 'Which tool did what, on whose behalf, and whether it changed anything.';
$string['entry_insights_text'] = 'Per course: which topics your material could not answer, and where it exists but does not hold.';
$string['entry_requests_text'] = 'Every single AI request of the suite with question, answer, model and cost.';
$string['entry_settings_text'] = 'Who may read the audit, what it shows, and how long each log is kept.';
$string['insights_othercourse'] = 'Choose another course';
$string['audit_core_report_eyebrow'] = 'Moodle';
$string['audit_requests_page_intro'] = 'Two lists, because there are two logs: the suite\'s own, and Moodle\'s for everything else. They no longer overlap — a request the suite made is listed once, here at the top. Each shows question, answer, model, status and cost as far as the settings allow.';
$string['audit_state_eyebrow'] = 'Whole site, whole history';
$string['audit_state_intro'] = 'Counted from Moodle\'s own AI register: every plugin, since AI was switched on here. The breakdown underneath is narrower — the eLeDia.ai suite only, and only the last 30 days.';
$string['audit_state_title'] = 'How much AI was used';
$string['audit_suite_report_eyebrow'] = 'eLeDia.ai suite';
$string['entry_scope_admin'] = 'Administration';
$string['entry_scope_course'] = 'Per course';
$string['entry_scope_site'] = 'Whole site';
$string['nav_audit_home'] = 'Overview';
$string['surface_actions_title'] = 'What the AI did';
$string['surface_insights_title'] = 'What learners asked';
$string['surface_requests_title'] = 'Every AI request';
$string['surface_settings_title'] = 'Access and retention';
$string['action_table_eyebrow'] = 'Every entry';
$string['action_table_intro'] = 'Newest first. Narrow the list to actions that changed something, or to the ones that failed.';
$string['action_table_title'] = 'The actions themselves';
$string['audit_settings_page_intro'] = 'Who may read all of this, how much of a request is shown, and how long each of the three logs is kept.';
$string['topic_suite_logs_title'] = 'What the AI suite records';
$string['topic_suite_logs_summary'] = 'Three logs with three jobs: what was asked (without your name), what the AI did (with your name) and where a text came from (as a checksum only).';
$string['topic_suite_logs_body'] = 'The AI suite keeps three separate logs. They are different because they do different jobs — and only that is why they may live for different lengths of time.

**What you asked.** Your question and the answer are kept, without your name. In its place stands a short code that stands for you but does not lead back to you. This is what the course view is built from: which topics came up often, and where the course material had no answer. Your teacher sees the questions, not who asked them. This log has the shortest retention.

**What the AI did.** When the AI changes something — writes a grade, creates an activity, sends a message — that is recorded with your name. Not the content, only the act. This is the part that has to exist: an action carried out on somebody\'s behalf has to stay accountable. That is why this log has the longest retention, and why at the end it is not deleted but stripped of the name.

**Where a text came from.** Text produced by AI gets a checksum that can later show it came from the AI. It does not contain the text, only its fingerprint — which is why it outlives the text.

How long each of them is kept is your site\'s decision. Ask your administrator if you want the exact figures; they are in the AI audit under “Access and retention”.';
$string['audit_col_tokens_help_estimated'] = 'Prompt tokens / response tokens — estimated. The backend reported no usage, so the suite booked its own estimate against the credit and recorded the same figure here.';
$string['feature_maturity_alpha'] = 'Alpha';
$string['feature_maturity_alpha_help'] = 'In alpha: early, incomplete and liable to change without notice. Worth trying, not worth relying on.';
$string['feature_maturity_beta'] = 'Beta';
$string['feature_maturity_beta_help'] = 'In beta: usable and in use, but still changing. Expect rough edges and occasional changes to how it works.';
