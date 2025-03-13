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
        '/local/requiredsurvey/ajax.php',
        '/local/requiredsurvey/dashboard.php',
        '/local/requiredsurvey/bulk_reset.php',
        '/local/requiredsurvey/edit.php',
        '/local/requiredsurvey/user_search.php'
    ];
    
    foreach ($allowed_paths as $path) {
        if (strpos($current_path, $path) !== false) {
            return;
        }
    }
    
    // If we're on the questionnaire page or admin pages, don't redirect
    if (strpos($current_path, '/mod/questionnaire/') === 0 || 
        strpos($current_path, '/admin/') === 0 ||
        strpos($current_path, '/local/requiredsurvey/') === 0) {
        return;
    }
    
    // Get all active questionnaire configurations
    $configs = $DB->get_records('local_requiredsurvey_config', ['enabled' => 1], 'priority ASC');
    
    if (empty($configs)) {
        // Fall back to old configuration style if no new configs are found
        $questionnaire_id = get_config('local_requiredsurvey', 'questionnaire_id');
        $course_id = get_config('local_requiredsurvey', 'course_id');
        
        if (empty($questionnaire_id) || empty($course_id) || $questionnaire_id == 0 || $course_id == 0) {
            return; // Not configured yet
        }
        
        // Create a config object from old settings
        $config = new stdClass();
        $config->course_id = $course_id;
        $config->questionnaire_id = $questionnaire_id;
        $config->target_roles = '';
        $config->target_cohorts = '';
        
        $configs = [$config];
    }
    
    // Check each questionnaire configuration
    foreach ($configs as $config) {
        // Check if user is enrolled in the course
        $context = context_course::instance($config->course_id);
        if (!is_enrolled($context, $USER->id)) {
            // User is not enrolled, so don't enforce this questionnaire
            continue;
        }
        
        // Check if targeting specific roles
        if (!empty($config->target_roles)) {
            $target_roles = explode(',', $config->target_roles);
            $user_roles = get_user_roles($context, $USER->id);
            
            $matching_role = false;
            foreach ($user_roles as $role) {
                if (in_array($role->roleid, $target_roles)) {
                    $matching_role = true;
                    break;
                }
            }
            
            if (!$matching_role) {
                // User doesn't have a targeted role, skip this questionnaire
                continue;
            }
        }
        
        // Check if targeting specific cohorts
        if (!empty($config->target_cohorts)) {
            $target_cohorts = explode(',', $config->target_cohorts);
            
            $sql = "SELECT c.id 
                    FROM {cohort} c
                    JOIN {cohort_members} cm ON cm.cohortid = c.id
                    WHERE cm.userid = ?";
            $user_cohorts = $DB->get_records_sql($sql, [$USER->id]);
            
            $matching_cohort = false;
            foreach ($user_cohorts as $cohort) {
                if (in_array($cohort->id, $target_cohorts)) {
                    $matching_cohort = true;
                    break;
                }
            }
            
            if (!$matching_cohort) {
                // User isn't in a targeted cohort, skip this questionnaire
                continue;
            }
        }
        
        // Check if user has completed the questionnaire
        $completed = local_requiredsurvey_observer::has_completed_questionnaire($USER->id, $config->questionnaire_id);
        
        if (!$completed) {
            // Increment redirect counter
            $SESSION->questionnaire_redirect_count++;
            
            // Get the cmid for the questionnaire
            $cm = get_coursemodule_from_instance('questionnaire', $config->questionnaire_id, $config->course_id);
            
            if ($cm) {
                $questionnaire_url = new moodle_url('/mod/questionnaire/view.php', [
                    'id' => $cm->id
                ]);
                
                redirect($questionnaire_url, get_string('must_complete_questionnaire', 'local_requiredsurvey'));
            }
        }
    }
    
    // Reset redirect counter when no redirection is needed
    $SESSION->questionnaire_redirect_count = 0;
}