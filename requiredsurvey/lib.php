<?php
// This file is part of Moodle - http://moodle.org/
//
defined('MOODLE_INTERNAL') || die();

/**
 * Check if the current page should be redirected to the questionnaire
 */
function local_requiredsurvey_before_standard_html_head() {
    global $CFG, $USER, $PAGE, $SESSION;
    
    // Initialize redirect counter if not set
    if (!isset($SESSION->questionnaire_redirect_count)) {
        $SESSION->questionnaire_redirect_count = 0;
    }
    
    // Safety check to prevent redirect loops
    if ($SESSION->questionnaire_redirect_count > 3) {
        // Reset counter and allow the user to proceed
        $SESSION->questionnaire_redirect_count = 0;
        return;
    }
    
    // Skip for non-logged in users, admin users, or if in an installation/upgrade process
    if (!isloggedin() || isguestuser() || is_siteadmin() || during_initial_install() || CLI_SCRIPT || AJAX_SCRIPT) {
        return;
    }
    
    // Get the questionnaire ID from config
    $questionnaire_id = get_config('local_requiredsurvey', 'questionnaire_id');
    $course_id = get_config('local_requiredsurvey', 'course_id');
    
    if (empty($questionnaire_id) || empty($course_id) || $questionnaire_id == 0 || $course_id == 0) {
        return; // Not configured yet
    }
    
    // Skip for some allowed pages like the questionnaire itself, login pages, etc.
    $current_path = $PAGE->url->get_path();
    $allowed_paths = [
        '/mod/questionnaire/view.php',
        '/mod/questionnaire/complete.php',
        '/mod/questionnaire/questions.php',
        '/mod/questionnaire/preview.php',
        '/mod/questionnaire/qsettings.php',
        '/mod/questionnaire/report.php',
        '/login/logout.php',
        '/login/index.php',
        '/admin/index.php',
        '/admin/settings.php',
        '/admin/search.php',
        '/local/requiredsurvey/ajax.php'
    ];
    
    foreach ($allowed_paths as $path) {
        if (strpos($current_path, $path) !== false) {
            return;
        }
    }
    
    // If we're on the questionnaire page or admin pages, don't redirect
    if (strpos($current_path, '/mod/questionnaire/') === 0 || 
        strpos($current_path, '/admin/') === 0) {
        return;
    }
    
    // Check if user has completed the questionnaire
    $completed = local_requiredsurvey_observer::has_completed_questionnaire($USER->id, $questionnaire_id);
    
    if (!$completed) {
        // Increment redirect counter
        $SESSION->questionnaire_redirect_count++;
        
        // Get the cmid for the questionnaire
        $cm = get_coursemodule_from_instance('questionnaire', $questionnaire_id, $course_id);
        
        if ($cm) {
            // Make sure user has access to the course
            if (!is_enrolled($context = context_course::instance($course_id), $USER->id)) {
                $enrol = enrol_get_plugin('manual');
                if ($enrol) {
                    $instances = enrol_get_instances($course_id, true);
                    foreach ($instances as $instance) {
                        if ($instance->enrol === 'manual') {
                            $enrol->enrol_user($instance, $USER->id, 5); // 5 is the student role ID
                            break;
                        }
                    }
                }
            }
            
            $questionnaire_url = new moodle_url('/mod/questionnaire/view.php', [
                'id' => $cm->id
            ]);
            
            redirect($questionnaire_url, get_string('must_complete_questionnaire', 'local_requiredsurvey'));
        }
    }
    
    // Reset redirect counter when no redirection is needed
    $SESSION->questionnaire_redirect_count = 0;
}