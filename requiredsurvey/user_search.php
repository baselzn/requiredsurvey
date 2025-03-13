<?php
// This file is part of Moodle - http://moodle.org/
//
require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Security checks
admin_externalpage_setup('requiredsurvey_management');
require_capability('local/requiredsurvey:resetcompletions', context_system::instance());

// Get parameters
$id = required_param('id', PARAM_INT); // Questionnaire config ID
$search = required_param('search', PARAM_RAW);
$action = optional_param('action', '', PARAM_ALPHA);
$userids = optional_param_array('userids', array(), PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

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
$PAGE->set_url('/local/requiredsurvey/user_search.php', array('id' => $id, 'search' => $search));
$PAGE->set_title(get_string('reset_individual_users', 'local_requiredsurvey'));
$PAGE->set_heading(get_string('reset_individual_users', 'local_requiredsurvey'));

// Process reset action
if ($action === 'reset' && $confirm && confirm_sesskey()) {
    if (!empty($userids)) {
        $count = 0;
        
        foreach ($userids as $userid) {
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
        
        // Redirect to search page again
        redirect(new moodle_url('/local/requiredsurvey/user_search.php', array(
            'id' => $id,
            'search' => $search
        )));
    }
}

// Search for users
$params = array();
$searchsql = "";

// Create search condition
$searchparams = explode(' ', $search);
$i = 0;
foreach ($searchparams as $searchparam) {
    $i++;
    $searchparam = trim($searchparam);
    if (empty($searchparam)) {
        continue;
    }
    
    $searchsql .= " AND (";
    $searchsql .= $DB->sql_like('u.firstname', ':search_firstname'.$i, false);
    $params['search_firstname'.$i] = "%$searchparam%";
    $searchsql .= " OR ";
    $searchsql .= $DB->sql_like('u.lastname', ':search_lastname'.$i, false);
    $params['search_lastname'.$i] = "%$searchparam%";
    $searchsql .= " OR ";
    $searchsql .= $DB->sql_like('u.email', ':search_email'.$i, false);
    $params['search_email'.$i] = "%$searchparam%";
    $searchsql .= " OR ";
    $searchsql .= $DB->sql_like('u.idnumber', ':search_idnumber'.$i, false);
    $params['search_idnumber'.$i] = "%$searchparam%";
    $searchsql .= ")";
}

// Find users who have completed the questionnaire
$sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.idnumber
        FROM {user} u
        JOIN {questionnaire_response} qr ON qr.userid = u.id
        WHERE qr.questionnaireid = ? AND qr.complete = 'y'
        AND u.deleted = 0 AND u.suspended = 0
        $searchsql
        ORDER BY u.lastname, u.firstname";

$params = array_merge(array($config->questionnaire_id), $params);
$users = $DB->get_records_sql($sql, $params);

// Start output
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reset_individual_users', 'local_requiredsurvey'));

// Show questionnaire info
echo html_writer::tag('p', get_string('reset_questionnaire_info', 'local_requiredsurvey', array(
    'questionnaire' => format_string($questionnaire->name),
    'course' => format_string($course->fullname)
)));

// Display search form
echo html_writer::start_tag('form', array(
    'method' => 'get',
    'action' => $PAGE->url,
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
    'value' => $search,
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

// If confirmation page
if ($action === 'reset' && !$confirm) {
    // Get selected users
    $selectedusers = array();
    if (!empty($userids)) {
        list($sql, $params) = $DB->get_in_or_equal($userids);
        $selectedusers = $DB->get_records_select('user', "id $sql", $params);
    }
    
    echo html_writer::tag('h4', get_string('users_to_reset', 'local_requiredsurvey', count($selectedusers)));
    
    // Display confirmation form
    echo html_writer::start_tag('form', array('method' => 'post', 'action' => $PAGE->url));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $id));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'search', 'value' => $search));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'reset'));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'confirm', 'value' => 1));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
    
    foreach ($userids as $userid) {
        echo html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'userids[]',
            'value' => $userid
        ));
    }
    
    // Show user list
    echo html_writer::start_tag('table', array('class' => 'table table-striped'));
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('name'));
    echo html_writer::tag('th', get_string('email'));
    echo html_writer::tag('th', get_string('idnumber'));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    
    echo html_writer::start_tag('tbody');
    foreach ($selectedusers as $user) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', fullname($user));
        echo html_writer::tag('td', $user->email);
        echo html_writer::tag('td', $user->idnumber);
        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    
    echo html_writer::tag('div', get_string('reset_warning', 'local_requiredsurvey'), 
        array('class' => 'alert alert-warning'));
    
    echo html_writer::start_tag('div', array('class' => 'mt-3'));
    echo html_writer::empty_tag('input', array(
        'type' => 'submit',
        'value' => get_string('confirm_reset', 'local_requiredsurvey'),
        'class' => 'btn btn-danger'
    ));
    
    echo ' ';
    
    echo html_writer::link(
        new moodle_url('/local/requiredsurvey/user_search.php', array('id' => $id, 'search' => $search)),
        get_string('cancel'),
        array('class' => 'btn btn-secondary')
    );
    
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('form');
} else {
    // Display search results if any
    if (empty($users)) {
        echo html_writer::tag('div', get_string('no_users_found', 'local_requiredsurvey', $search), 
            array('class' => 'alert alert-info'));
    } else {
        // Display results with checkboxes for selection
        echo html_writer::start_tag('form', array(
            'method' => 'post',
            'action' => $PAGE->url,
            'id' => 'user-select-form'
        ));
        
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $id));
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'search', 'value' => $search));
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'reset'));
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
        
        echo html_writer::start_tag('table', array('class' => 'table table-striped'));
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', get_string('select'));
        echo html_writer::tag('th', get_string('name'));
        echo html_writer::tag('th', get_string('email'));
        echo html_writer::tag('th', get_string('idnumber'));
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        
        echo html_writer::start_tag('tbody');
        foreach ($users as $user) {
            echo html_writer::start_tag('tr');
            echo html_writer::tag('td', 
                html_writer::checkbox('userids[]', $user->id, false)
            );
            echo html_writer::tag('td', fullname($user));
            echo html_writer::tag('td', $user->email);
            echo html_writer::tag('td', $user->idnumber);
            echo html_writer::end_tag('tr');
        }
        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
        
        // Add reset button
        echo html_writer::start_tag('div', array('class' => 'mt-3'));
        echo html_writer::empty_tag('input', array(
            'type' => 'submit',
            'value' => get_string('reset_selected_users', 'local_requiredsurvey'),
            'class' => 'btn btn-warning'
        ));
        
        echo ' ';
        
        echo html_writer::link(
            new moodle_url('/local/requiredsurvey/bulk_reset.php', array('id' => $id)),
            get_string('back'),
            array('class' => 'btn btn-secondary')
        );
        echo html_writer::end_tag('div');
        
        echo html_writer::end_tag('form');
        
        // Add JavaScript for select all/none
        $PAGE->requires->js_init_code('
            $("#select-all").click(function() {
                $("input[name=\'userids[]\']").prop("checked", true);
            });
            
            $("#select-none").click(function() {
                $("input[name=\'userids[]\']").prop("checked", false);
            });
        ');
    }
}

echo $OUTPUT->footer();