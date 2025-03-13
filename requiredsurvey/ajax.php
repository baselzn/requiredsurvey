<?php
// This file is part of Moodle - http://moodle.org/
//
define('AJAX_SCRIPT', true);
require_once('../../config.php');

// Security checks
require_login();
require_sesskey();

$action = required_param('action', PARAM_ALPHA);

switch ($action) {
    case 'get_questionnaires':
        $courseid = required_param('courseid', PARAM_INT);
        
        // Check if the user can view the course
        $context = context_course::instance($courseid);
        require_capability('moodle/course:view', $context);
        
        $questionnaires = array();
        
        // Get the questionnaire module ID
        $module = $DB->get_record('modules', array('name' => 'questionnaire'));
        
        if ($module) {
            $sql = "SELECT q.id, q.name 
                 FROM {questionnaire} q
                 JOIN {course_modules} cm ON cm.instance = q.id
                 WHERE cm.module = ? AND cm.course = ?";
            
            $questionnaire_records = $DB->get_records_sql($sql, array($module->id, $courseid));
            
            foreach ($questionnaire_records as $questionnaire) {
                $questionnaires[$questionnaire->id] = format_string($questionnaire->name);
            }
        }
        
        // Return the questionnaires as JSON
        $response = array(
            'questionnaires' => $questionnaires,
            'count' => count($questionnaires)
        );
        
        echo json_encode($response);
        break;
    
    default:
        // Invalid action
        throw new moodle_exception('invalidaction');
}