<?php
// This file is part of Moodle - http://moodle.org/
//
$string['pluginname'] = 'Required Questionnaire';
$string['must_complete_questionnaire'] = 'You must complete the required questionnaire before accessing other parts of the site.';
$string['questionnaire_id'] = 'Questionnaire';
$string['questionnaire_id_desc'] = 'Select the questionnaire that users must complete.';
$string['course_id'] = 'Course';
$string['course_id_desc'] = 'Select the course containing the questionnaire.';
$string['heading'] = 'Required Questionnaire Settings';
$string['heading_desc'] = 'Configure which questionnaire users must complete before accessing the site.';
$string['choose_course'] = 'Choose a course...';
$string['choose_questionnaire'] = 'Choose a questionnaire...';
$string['select_course'] = 'Select Course';
$string['save_settings'] = 'Save Settings';
$string['settings_saved'] = 'Settings have been saved successfully.';
$string['selected_course'] = 'Selected course: {$a}';
$string['selected_questionnaire'] = 'Currently required questionnaire: {$a}';
$string['no_questionnaires'] = 'No questionnaires found in the selected course.';

// New strings for multi-questionnaire support
$string['config_name'] = 'Configuration Name';
$string['config_name_desc'] = 'Enter a descriptive name for this questionnaire configuration.';
$string['enabled'] = 'Enabled';
$string['enabled_desc'] = 'Enable or disable this questionnaire configuration.';
$string['priority'] = 'Priority';
$string['priority_desc'] = 'If a user matches multiple questionnaire configurations, the one with the lowest priority number will be enforced first.';
$string['add_questionnaire_config'] = 'Add New Questionnaire Configuration';
$string['edit_questionnaire_config'] = 'Edit Questionnaire Configuration';
$string['delete_questionnaire_config'] = 'Delete Questionnaire Configuration';
$string['confirm_delete'] = 'Are you sure you want to delete this questionnaire configuration?';
$string['config_deleted'] = 'Questionnaire configuration deleted successfully.';
$string['config_saved'] = 'Questionnaire configuration saved successfully.';

// Strings for user targeting
$string['target_roles'] = 'Target Roles';
$string['target_roles_desc'] = 'Select which roles should complete this questionnaire. If none are selected, all roles will be required to complete it.';
$string['target_cohorts'] = 'Target Cohorts';
$string['target_cohorts_desc'] = 'Select which cohorts should complete this questionnaire. If none are selected, all cohorts will be required to complete it.';
$string['all_roles'] = 'All roles';
$string['all_cohorts'] = 'All cohorts';

// Strings for dashboard and reports
$string['dashboard'] = 'Completion Dashboard';
$string['completion_rate'] = 'Completion Rate';
$string['total_users'] = 'Total Users';
$string['completed_users'] = 'Completed Users';
$string['questionnaire_name'] = 'Questionnaire Name';
$string['detailed_report'] = 'Detailed Report';
$string['filter'] = 'Filter';
$string['export_report'] = 'Export Report';
$string['export_csv'] = 'Export as CSV';
$string['export_excel'] = 'Export as Excel';
$string['no_data'] = 'No completion data available.';

// Strings for bulk reset
$string['bulk_reset'] = 'Bulk Reset Completions';
$string['reset_questionnaire_info'] = 'Reset completions for questionnaire: {$a->questionnaire} in course: {$a->course}';
$string['reset_warning'] = 'Warning: This will delete questionnaire responses for the selected users. They will need to complete the questionnaire again. This action cannot be undone.';
$string['users_to_reset'] = 'Users to reset: {$a}';
$string['reset_many_users'] = 'You are about to reset a large number of users. This may take some time to complete.';
$string['confirm_reset'] = 'Yes, reset these users';
$string['reset_success'] = 'Successfully reset {$a} user completions.';
$string['preview_users'] = 'Preview Users';
$string['reset_individual_users'] = 'Reset Individual Users';
$string['search_placeholder'] = 'Search by name, email, or ID number';

// Strings for email notifications
$string['email_settings'] = 'Email Notification Settings';
$string['enable_email_reports'] = 'Send Completion Reports';
$string['enable_email_reports_desc'] = 'Enable to send regular completion reports by email.';
$string['report_recipients'] = 'Email Recipients';
$string['report_recipients_desc'] = 'Enter email addresses separated by commas. If left empty, all site administrators will receive the reports.';
$string['email_report_subject'] = '{$a->sitename}: Questionnaire Completion Report';
$string['email_report_intro'] = 'This is an automated report of questionnaire completion rates.';
$string['email_report_summary'] = 'Questionnaire Completion Summary';
$string['email_report_line'] = '{$a->name} ({$a->questionnaire} in {$a->course}): {$a->completed}/{$a->total} users ({$a->rate}%)';
$string['email_report_link'] = 'View detailed report: {$a->url}';
$string['view_full_report'] = 'View full report';
$string['task_send_completion_report'] = 'Send questionnaire completion reports';

// Strings for user-facing block
$string['pending_questionnaires'] = 'Pending Questionnaires';
$string['completed_questionnaires'] = 'Completed Questionnaires';
$string['take_survey'] = 'Take Survey';

// Capability strings
$string['requiredsurvey:manage'] = 'Manage required questionnaire settings';
$string['requiredsurvey:viewreports'] = 'View questionnaire completion reports';
$string['requiredsurvey:resetcompletions'] = 'Reset questionnaire completions';

$string['current_configurations'] = 'Current Questionnaire Configurations';
$string['no_configurations'] = 'No questionnaire configurations found. Add one using the button below.';
$string['actions'] = 'Actions';
$string['legacy_config_notice'] = 'Legacy configuration found: {$a->questionnaire} in course {$a->course}';
$string['migrate_config'] = 'Migrate Legacy Configuration';
$string['migrated_config_name'] = 'Migrated: {$a->questionnaire} in {$a->course}';
$string['migration_success'] = 'Legacy configuration successfully migrated to the new format.';
$string['invalidconfiguration'] = 'Invalid questionnaire configuration.';
$string['manage_configurations'] = 'Manage Questionnaire Configurations';
$string['no_users_found'] = 'No users found matching "{$a}".';
$string['reset_selected_users'] = 'Reset Selected Users';