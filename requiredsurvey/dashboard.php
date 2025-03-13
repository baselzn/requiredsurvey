<?php
// This file is part of Moodle - http://moodle.org/
//
require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Security checks
admin_externalpage_setup('requiredsurvey_dashboard');
require_capability('local/requiredsurvey:viewreports', context_system::instance());

// Page setup
$PAGE->set_url('/local/requiredsurvey/dashboard.php');
$PAGE->set_title(get_string('dashboard', 'local_requiredsurvey'));
$PAGE->set_heading(get_string('dashboard', 'local_requiredsurvey'));

// Add JS for charts
$PAGE->requires->js_call_amd('local_requiredsurvey/dashboard', 'init');

// Get filter values
$courseid = optional_param('courseid', 0, PARAM_INT);
$roleid = optional_param('roleid', 0, PARAM_INT);
$cohortid = optional_param('cohortid', 0, PARAM_INT);

// Get available filters
$courses = array(0 => get_string('all_cohorts', 'local_requiredsurvey'));
$allcourses = get_courses();
foreach ($allcourses as $course) {
    if ($course->id == SITEID) {
        continue;
    }
    $courses[$course->id] = format_string($course->fullname);
}

$roles = array(0 => get_string('all_roles', 'local_requiredsurvey'));
$allroles = get_all_roles();
foreach ($allroles as $role) {
    $roles[$role->id] = role_get_name($role);
}

$cohorts = array(0 => get_string('all_cohorts', 'local_requiredsurvey'));
$allcohorts = $DB->get_records('cohort', array('contextid' => context_system::instance()->id));
foreach ($allcohorts as $cohort) {
    $cohorts[$cohort->id] = format_string($cohort->name);
}

// Get questionnaire configurations
$configs = $DB->get_records('local_requiredsurvey_config', array('enabled' => 1));

// Build SQL to get completion data
$completion_data = array();
foreach ($configs as $config) {
    $params = array();
    $sql = "SELECT COUNT(DISTINCT u.id) as usercount
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50 ";
    
    if ($cohortid) {
        $sql .= "JOIN {cohort_members} cm ON cm.userid = u.id ";
    }
    
    $sql .= "WHERE u.deleted = 0 AND u.suspended = 0 ";
    
    if ($courseid) {
        $sql .= "AND ctx.instanceid = ? ";
        $params[] = $courseid;
    } else {
        $sql .= "AND ctx.instanceid = ? ";
        $params[] = $config->course_id;
    }
    
    if ($roleid) {
        $sql .= "AND ra.roleid = ? ";
        $params[] = $roleid;
    }
    
    if ($cohortid) {
        $sql .= "AND cm.cohortid = ? ";
        $params[] = $cohortid;
    }
    
    // Add role filter from config if specified
    if (!empty($config->target_roles) && !$roleid) {
        $target_roles = explode(',', $config->target_roles);
        list($insql, $inparams) = $DB->get_in_or_equal($target_roles);
        $sql .= " AND ra.roleid $insql";
        $params = array_merge($params, $inparams);
    }
    
    // Add cohort filter from config if specified
    if (!empty($config->target_cohorts) && !$cohortid) {
        $target_cohorts = explode(',', $config->target_cohorts);
        $sql .= " AND u.id IN (
                  SELECT cm.userid 
                  FROM {cohort_members} cm 
                  WHERE cm.cohortid IN (";
        
        $cohort_placeholders = array();
        foreach ($target_cohorts as $cohort) {
            $cohort_placeholders[] = '?';
            $params[] = $cohort;
        }
        
        $sql .= implode(',', $cohort_placeholders) . "))";
    }
    
    $total_users = $DB->count_records_sql($sql, $params);
    
    // SQL to get completed users
    $sql = "SELECT COUNT(DISTINCT r.userid) as completed
            FROM {questionnaire_response} r
            JOIN {user} u ON u.id = r.userid
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50 ";
    
    if ($cohortid) {
        $sql .= "JOIN {cohort_members} cm ON cm.userid = u.id ";
    }
    
    $sql .= "WHERE r.questionnaireid = ? AND r.complete = 'y' 
             AND u.deleted = 0 AND u.suspended = 0 ";
    
    $params = array($config->questionnaire_id);
    
    if ($courseid) {
        $sql .= "AND ctx.instanceid = ? ";
        $params[] = $courseid;
    } else {
        $sql .= "AND ctx.instanceid = ? ";
        $params[] = $config->course_id;
    }
    
    if ($roleid) {
        $sql .= "AND ra.roleid = ? ";
        $params[] = $roleid;
    }
    
    if ($cohortid) {
        $sql .= "AND cm.cohortid = ? ";
        $params[] = $cohortid;
    }
    
    // Add role filter from config if specified
    if (!empty($config->target_roles) && !$roleid) {
        $target_roles = explode(',', $config->target_roles);
        list($insql, $inparams) = $DB->get_in_or_equal($target_roles);
        $sql .= " AND ra.roleid $insql";
        $params = array_merge($params, $inparams);
    }
    
    // Add cohort filter from config if specified
    if (!empty($config->target_cohorts) && !$cohortid) {
        $target_cohorts = explode(',', $config->target_cohorts);
        $sql .= " AND u.id IN (
                  SELECT cm.userid 
                  FROM {cohort_members} cm 
                  WHERE cm.cohortid IN (";
        
        $cohort_placeholders = array();
        foreach ($target_cohorts as $cohort) {
            $cohort_placeholders[] = '?';
            $params[] = $cohort;
        }
        
        $sql .= implode(',', $cohort_placeholders) . "))";
    }
    
    $completed_users = $DB->count_records_sql($sql, $params);
    
    // Get questionnaire and course names
    $questionnaire = $DB->get_record('questionnaire', array('id' => $config->questionnaire_id));
    $course = $DB->get_record('course', array('id' => $config->course_id));
    
    $completion_data[] = array(
        'config_id' => $config->id,
        'name' => $config->name,
        'questionnaire' => $questionnaire ? format_string($questionnaire->name) : '',
        'course' => $course ? format_string($course->fullname) : '',
        'total_users' => $total_users,
        'completed_users' => $completed_users,
        'completion_rate' => $total_users > 0 ? round(($completed_users / $total_users) * 100, 1) : 0
    );
}

// Start output
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('dashboard', 'local_requiredsurvey'));

// Display filters
$filter_form = html_writer::start_tag('form', array('method' => 'get', 'class' => 'form-inline mb-4'));
$filter_form .= html_writer::select($courses, 'courseid', $courseid, false, array('class' => 'form-control mr-2'));
$filter_form .= html_writer::select($roles, 'roleid', $roleid, false, array('class' => 'form-control mr-2'));
$filter_form .= html_writer::select($cohorts, 'cohortid', $cohortid, false, array('class' => 'form-control mr-2'));
$filter_form .= html_writer::empty_tag('input', array(
    'type' => 'submit',
    'value' => get_string('filter', 'local_requiredsurvey'),
    'class' => 'btn btn-primary'
));
$filter_form .= html_writer::end_tag('form');

echo $filter_form;

// Display charts container
echo html_writer::start_div('row mt-4');
echo html_writer::start_div('col-md-6');
echo html_writer::div('', '', array('id' => 'completion-chart'));
echo html_writer::end_div();
echo html_writer::start_div('col-md-6');
echo html_writer::div('', '', array('id' => 'trend-chart'));
echo html_writer::end_div();
echo html_writer::end_div();

// Display data table
echo html_writer::start_tag('table', array('class' => 'table table-striped mt-4'));
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('questionnaire_name', 'local_requiredsurvey'));
echo html_writer::tag('th', get_string('course', 'local_requiredsurvey'));
echo html_writer::tag('th', get_string('total_users', 'local_requiredsurvey'));
echo html_writer::tag('th', get_string('completed_users', 'local_requiredsurvey'));
echo html_writer::tag('th', get_string('completion_rate', 'local_requiredsurvey'));
echo html_writer::tag('th', get_string('actions', 'local_requiredsurvey'));
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');
foreach ($completion_data as $data) {
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', $data['name'] . ' (' . $data['questionnaire'] . ')');
    echo html_writer::tag('td', $data['course']);
    echo html_writer::tag('td', $data['total_users']);
    echo html_writer::tag('td', $data['completed_users']);
    echo html_writer::tag('td', 
        html_writer::div(
            html_writer::div(
                $data['completion_rate'] . '%',
                'progress-bar', 
                array(
                    'role' => 'progressbar',
                    'style' => 'width: ' . $data['completion_rate'] . '%;',
                    'aria-valuenow' => $data['completion_rate'],
                    'aria-valuemin' => '0',
                    'aria-valuemax' => '100'
                )
            ),
            'progress'
        )
    );
    echo html_writer::tag('td', 
        html_writer::link(
            new moodle_url('/local/requiredsurvey/report.php', ['id' => $data['config_id']]),
            get_string('detailed_report', 'local_requiredsurvey'),
            ['class' => 'btn btn-sm btn-info']
        ) . ' ' .
        html_writer::link(
            new moodle_url('/local/requiredsurvey/bulk_reset.php', ['id' => $data['config_id']]),
            get_string('bulk_reset', 'local_requiredsurvey'),
            ['class' => 'btn btn-sm btn-warning']
        )
    );
    echo html_writer::end_tag('tr');
}
echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

// Add export buttons
if (!empty($completion_data)) {
    echo html_writer::start_div('mt-3');
    echo html_writer::link(
        new moodle_url('/local/requiredsurvey/export.php', [
            'format' => 'csv',
            'courseid' => $courseid,
            'roleid' => $roleid,
            'cohortid' => $cohortid
        ]),
        get_string('export_csv', 'local_requiredsurvey'),
        ['class' => 'btn btn-secondary mr-2']
    );
    
    echo html_writer::link(
        new moodle_url('/local/requiredsurvey/export.php', [
            'format' => 'excel',
            'courseid' => $courseid,
            'roleid' => $roleid,
            'cohortid' => $cohortid
        ]),
        get_string('export_excel', 'local_requiredsurvey'),
        ['class' => 'btn btn-secondary']
    );
    echo html_writer::end_div();
} else {
    echo html_writer::tag('div', get_string('no_data', 'local_requiredsurvey'), 
        ['class' => 'alert alert-info']);
}

// Add JavaScript to page for charts
$PAGE->requires->js_init_code("
    const completionData = " . json_encode($completion_data) . ";
");

echo $OUTPUT->footer();