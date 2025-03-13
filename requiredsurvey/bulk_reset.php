<?php
// This file is part of Moodle - http://moodle.org/
//
require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Security checks
admin_externalpage_setup('requiredsurvey_management');
require_capability('local/requiredsurvey:resetcompletions', context_system::instance());

// Get parameters
$id = optional_param('id', 0, PARAM_INT); // Questionnaire config ID
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$action = optional_param('action', '', PARAM_ALPHA);
$userids = optional_param_array('userids', array(), PARAM_INT);
$cohortid = optional_param('cohortid', 0, PARAM_INT);
$roleid = optional_param('roleid', 0, PARAM_INT);
$preview = optional_param('preview', 0, PARAM_BOOL);

// Validate questionnaire config
$config = $DB->get_record('local_requiredsurvey_config', array('id' => $id));
if (!$config) {
    // Try to get from old config
    $questionnaire_id = get_config('local_requiredsurvey', 'questionnaire_id');
    $course_id = get_config('local_requiredsurvey', 'course_id');
    
    if (empty($questionnaire_id) || empty($course_id)) {
        print_error('invalidconfigid', 'local_requiredsurvey');
    }
    
    $config = new stdClass();
    $config->questionnaire_id = $questionnaire_id;
    $config->course_id = $course_id;
}

// Get questionnaire and course info
$questionnaire = $DB->get_record('questionnaire', array('id' => $config->questionnaire_id));
$course = $DB->get_record('course', array('id' => $config->course_id));

if (!$questionnaire || !$course) {
    print_error('invalidconfiguration', 'local_requiredsurvey');
}

// Setup page
$PAGE->set_url('/local/requiredsurvey/bulk_reset.php', array('id' => $id));
$PAGE->set_title(get_string('bulk_reset', 'local_requiredsurvey'));
$PAGE->set_heading(get_string('bulk_reset', 'local_requiredsurvey'));

// Process reset action
if ($action === 'reset' && $confirm && confirm_sesskey()) {
    $users_to_reset = array();
    
    if (!empty($userids)) {
        // Reset specific users
        $users_to_reset = $userids;
    } else {
        // Build filters for bulk reset
        $params = array();
        $sql = "SELECT DISTINCT u.id
                FROM {user} u
                JOIN {role_assignments} ra ON ra.userid = u.id
                JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                JOIN {questionnaire_response} qr ON qr.userid = u.id
                WHERE u.deleted = 0 AND u.suspended = 0
                AND ctx.instanceid = ?
                AND qr.questionnaireid = ?
                AND qr.complete = 'y'";
        
        $params[] = $config->course_id;
        $params[] = $config->questionnaire_id;
        
        // Add role filter
        if ($roleid) {
            $sql .= " AND ra.roleid = ?";
            $params[] = $roleid;
        }
        
        // Add cohort filter
        if ($cohortid) {
            $sql .= " AND u.id IN (
                      SELECT cm.userid 
                      FROM {cohort_members} cm
                      WHERE cm.cohortid = ?)";
            $params[] = $cohortid;
        }
        
        $users_to_reset = $DB->get_fieldset_sql($sql, $params);
    }
    
    if (!empty($users_to_reset) && !$preview) {
        // Perform the reset by deleting responses
        $count = 0;
        
        foreach ($users_to_reset as $userid) {
            $deleted = $DB->delete_records('questionnaire_response', array(
                'userid' => $userid,
                'questionnaireid' => $config->questionnaire_id
            ));
            
            if ($deleted) {
                $count++;
            }
        }
        
        // Show success message
        \core\notification::success(get_string('reset_success', 'local_requiredsurvey', $count));
        
        // Redirect to dashboard
        redirect(new moodle_url('/local/requiredsurvey/dashboard.php'));
    }
}

// Get cohorts for filter
$cohorts = array(0 => get_string('all_cohorts', 'local_requiredsurvey'));
$allcohorts = $DB->get_records('cohort', array('contextid' => context_system::instance()->id));
foreach ($allcohorts as $cohort) {
    $cohorts[$cohort->id] = format_string($cohort->name);
}

// Get roles for filter
$roles = array(0 => get_string('all_roles', 'local_requiredsurvey'));
$allroles = get_all_roles();
foreach ($allroles as $role) {
    $roles[$role->id] = role_get_name($role);
}

// Start output
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('bulk_reset', 'local_requiredsurvey'));

// Show questionnaire info
echo html_writer::tag('p', get_string('reset_questionnaire_info', 'local_requiredsurvey', array(
    'questionnaire' => format_string($questionnaire->name),
    'course' => format_string($course->fullname)
)));

echo html_writer::tag('div', get_string('reset_warning', 'local_requiredsurvey'), 
    array('class' => 'alert alert-warning'));

// If confirmation page
if ($action === 'reset' && !$confirm) {
    // Preview users to be reset
    $params = array();
    $sql = "SELECT u.id, u.firstname, u.lastname, u.email
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
            JOIN {questionnaire_response} qr ON qr.userid = u.id
            WHERE u.deleted = 0 AND u.suspended = 0
            AND ctx.instanceid = ?
            AND qr.questionnaireid = ?
            AND qr.complete = 'y'";
    
    $params[] = $config->course_id;
    $params[] = $config->questionnaire_id;
    
    // Add role filter
    if ($roleid) {
        $sql .= " AND ra.roleid = ?";
        $params[] = $roleid;
    }
    
    // Add cohort filter
    if ($cohortid) {
        $sql .= " AND u.id IN (
                  SELECT cm.userid 
                  FROM {cohort_members} cm
                  WHERE cm.cohortid = ?)";
        $params[] = $cohortid;
    }
    
    $users = $DB->get_records_sql($sql, $params);
    
    echo html_writer::tag('h4', get_string('users_to_reset', 'local_requiredsurvey', count($users)));
    
    // If too many users, show warning
    if (count($users) > 200) {
        echo html_writer::tag('div', get_string('reset_many_users', 'local_requiredsurvey'), 
            array('class' => 'alert alert-danger'));
    }
    
    // Display confirmation form
    echo html_writer::start_tag('form', array('method' => 'post', 'action' => $PAGE->url));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $id));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'reset'));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'cohortid', 'value' => $cohortid));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'roleid', 'value' => $roleid));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'confirm', 'value' => 1));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
    
    // Show user list if not too many
    if (count($users) <= 200) {
        echo html_writer::start_tag('table', array('class' => 'table table-striped'));
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', get_string('name'));
        echo html_writer::tag('th', get_string('email'));
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        
        echo html_writer::start_tag('tbody');
        foreach ($users as $user) {
            echo html_writer::start_tag('tr');
            echo html_writer::tag('td', fullname($user));
            echo html_writer::tag('td', $user->email);
            echo html_writer::end_tag('tr');
        }
        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
    }
    
    echo html_writer::start_tag('div', array('class' => 'mt-3'));
    echo html_writer::empty_tag('input', array(
        'type' => 'submit',
        'value' => get_string('confirm_reset', 'local_requiredsurvey'),
        'class' => 'btn btn-danger'
    ));
    
    echo ' ';
    
    echo html_writer::link(
        new moodle_url('/local/requiredsurvey/dashboard.php'),
        get_string('cancel'),
        array('class' => 'btn btn-secondary')
    );
    
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('form');
} else {
    // Display filter form
    echo html_writer::start_tag('form', array('method' => 'post', 'action' => $PAGE->url, 'class' => 'mb-4'));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $id));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'reset'));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
    
    echo html_writer::start_div('form-group row');
    echo html_writer::start_div('col-md-3');
    echo html_writer::tag('label', get_string('role', 'role'), array('for' => 'roleid'));
    echo html_writer::end_div();
    
    echo html_writer::start_div('col-md-9');
    echo html_writer::select($roles, 'roleid', $roleid, false, array('class' => 'form-control'));
    echo html_writer::end_div();
    echo html_writer::end_div();
    
    echo html_writer::start_div('form-group row');
    echo html_writer::start_div('col-md-3');
    echo html_writer::tag('label', get_string('cohort', 'cohort'), array('for' => 'cohortid'));
    echo html_writer::end_div();
    
    echo html_writer::start_div('col-md-9');
    echo html_writer::select($cohorts, 'cohortid', $cohortid, false, array('class' => 'form-control'));
    echo html_writer::end_div();
    echo html_writer::end_div();
    
    echo html_writer::start_div('form-group row');
    echo html_writer::start_div('col-md-9 offset-md-3');
    echo html_writer::empty_tag('input', array(
        'type' => 'submit',
        'value' => get_string('preview_users', 'local_requiredsurvey'),
        'class' => 'btn btn-primary'
    ));
    echo html_writer::end_div();
    echo html_writer::end_div();
    
    echo html_writer::end_tag('form');
    
    // Individual user search and reset
    echo html_writer::tag('h4', get_string('reset_individual_users', 'local_requiredsurvey'), 
        array('class' => 'mt-4'));
    
    echo html_writer::start_tag('form', array(
        'method' => 'get',
        'action' => new moodle_url('/local/requiredsurvey/user_search.php'),
        'class' => 'mb-4'
    ));
    
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $id));
    
    echo html_writer::start_div('form-group row');
    echo html_writer::start_div('col-md-3');
    echo html_writer::tag('label', get_string('search'), array('for' => 'search'));
    echo html_writer::end_div();
    
    echo html_writer::start_div('col-md-6');
    echo html_writer::empty_tag('input', array(
        'type' => 'text',
        'name' => 'search',
        'id' => 'search',
        'class' => 'form-control',
        'placeholder' => get_string('search_placeholder', 'local_requiredsurvey')
    ));
    echo html_writer::end_div();
    
    echo html_writer::start_div('col-md-3');
    echo html_writer::empty_tag('input', array(
        'type' => 'submit',
        'value' => get_string('search'),
        'class' => 'btn btn-secondary'
    ));
    echo html_writer::end_div();
    echo html_writer::end_div();
    
    echo html_writer::end_tag('form');
}

echo $OUTPUT->footer();