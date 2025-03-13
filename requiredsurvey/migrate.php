<?php
// This file is part of Moodle - http://moodle.org/
//
require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Security checks
admin_externalpage_setup('requiredsurvey_management');
require_capability('local/requiredsurvey:manage', context_system::instance());
require_sesskey();

// Check if we have old settings to migrate
$questionnaire_id = get_config('local_requiredsurvey', 'questionnaire_id');
$course_id = get_config('local_requiredsurvey', 'course_id');

if (empty($questionnaire_id) || empty($course_id) || $questionnaire_id == 0 || $course_id == 0) {
    // Nothing to migrate
    redirect(new moodle_url('/admin/settings.php', array('section' => 'local_requiredsurvey')));
}

// Check if the questionnaire and course exist
$questionnaire = $DB->get_record('questionnaire', array('id' => $questionnaire_id));
$course = $DB->get_record('course', array('id' => $course_id));

if (!$questionnaire || !$course) {
    // Invalid configuration
    \core\notification::error(get_string('invalidconfiguration', 'local_requiredsurvey'));
    redirect(new moodle_url('/admin/settings.php', array('section' => 'local_requiredsurvey')));
}

// Create a new configuration record
$config = new stdClass();
$config->name = get_string('migrated_config_name', 'local_requiredsurvey', array(
    'questionnaire' => format_string($questionnaire->name),
    'course' => format_string($course->fullname)
));
$config->course_id = $course_id;
$config->questionnaire_id = $questionnaire_id;
$config->target_roles = '';
$config->target_cohorts = '';
$config->enabled = 1;
$config->priority = 1;
$config->timecreated = time();
$config->timemodified = time();

// Insert the new configuration
$DB->insert_record('local_requiredsurvey_config', $config);

// We could clear the old settings, but we'll leave them for now
// in case something goes wrong, and we need to revert
// set_config('questionnaire_id', 0, 'local_requiredsurvey');
// set_config('course_id', 0, 'local_requiredsurvey');

// Show success message and redirect
\core\notification::success(get_string('migration_success', 'local_requiredsurvey'));
redirect(new moodle_url('/admin/settings.php', array('section' => 'local_requiredsurvey')));