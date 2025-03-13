<?php
// This file is part of Moodle - http://moodle.org/
//
require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Security checks
admin_externalpage_setup('requiredsurvey_management');
require_capability('local/requiredsurvey:manage', context_system::instance());
require_sesskey();

// Get parameters
$id = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

// Setup page
$PAGE->set_url('/local/requiredsurvey/delete.php', array('id' => $id, 'sesskey' => sesskey()));
$PAGE->set_title(get_string('delete_questionnaire_config', 'local_requiredsurvey'));
$PAGE->set_heading(get_string('delete_questionnaire_config', 'local_requiredsurvey'));

// Check if config exists
$config = $DB->get_record('local_requiredsurvey_config', array('id' => $id), '*', MUST_EXIST);

// Process deletion
if ($confirm) {
    $DB->delete_records('local_requiredsurvey_config', array('id' => $id));
    
    // Show success message
    \core\notification::success(get_string('config_deleted', 'local_requiredsurvey'));
    
    // Redirect to settings page
    redirect(new moodle_url('/admin/settings.php', array('section' => 'local_requiredsurvey')));
}

// Display confirmation page
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('delete_questionnaire_config', 'local_requiredsurvey'));

$questionnaire = $DB->get_record('questionnaire', array('id' => $config->questionnaire_id));
$course = $DB->get_record('course', array('id' => $config->course_id));

echo html_writer::tag('p', get_string('confirm_delete', 'local_requiredsurvey'));

echo html_writer::tag('p', 
    get_string('config_name', 'local_requiredsurvey') . ': ' . format_string($config->name)
);

if ($questionnaire) {
    echo html_writer::tag('p', 
        get_string('questionnaire_id', 'local_requiredsurvey') . ': ' . format_string($questionnaire->name)
    );
}

if ($course) {
    echo html_writer::tag('p', 
        get_string('course_id', 'local_requiredsurvey') . ': ' . format_string($course->fullname)
    );
}

echo $OUTPUT->confirm(
    get_string('delete_questionnaire_config', 'local_requiredsurvey'),
    new moodle_url('/local/requiredsurvey/delete.php', array('id' => $id, 'confirm' => 1, 'sesskey' => sesskey())),
    new moodle_url('/admin/settings.php', array('section' => 'local_requiredsurvey'))
);

echo $OUTPUT->footer();