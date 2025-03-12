<?php
// This file is part of Moodle - http://moodle.org/
//
defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for the required survey plugin.
 */
class local_requiredsurvey_observer {
    /**
     * User login event handler.
     *
     * @param \core\event\user_loggedin $event The event.
     * @return bool True on success.
     */
    public static function user_loggedin(\core\event\user_loggedin $event) {
        global $CFG, $USER, $DB, $SESSION;
        
        // Initialize redirect counter if not set
        if (!isset($SESSION->questionnaire_redirect_count)) {
            $SESSION->questionnaire_redirect_count = 0;
        }
        
        // Safety check to prevent redirect loops - if we've redirected too many times, stop
        if ($SESSION->questionnaire_redirect_count > 3) {
            // Reset counter and allow the user to proceed
            $SESSION->questionnaire_redirect_count = 0;
            return true;
        }
        
        // Skip for admin users, guests, or if in a script context
        if (is_siteadmin() || isguestuser() || CLI_SCRIPT || AJAX_SCRIPT) {
            return true;
        }
        
        // Get the questionnaire ID from config
        $questionnaire_id = get_config('local_requiredsurvey', 'questionnaire_id');
        $course_id = get_config('local_requiredsurvey', 'course_id');
        
        if (empty($questionnaire_id) || empty($course_id) || $questionnaire_id == 0 || $course_id == 0) {
            return true; // Not configured yet
        }
        
        // Check if user has already completed the questionnaire
        $completed = self::has_completed_questionnaire($USER->id, $questionnaire_id);
        
        if (!$completed) {
            // Get the cmid for the questionnaire
            $cm = get_coursemodule_from_instance('questionnaire', $questionnaire_id, $course_id);
            
            if ($cm) {
                // Increment redirect counter
                $SESSION->questionnaire_redirect_count++;
                
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
                
                // Redirect to the questionnaire
                $questionnaire_url = new moodle_url('/mod/questionnaire/view.php', [
                    'id' => $cm->id
                ]);
                
                redirect($questionnaire_url, get_string('must_complete_questionnaire', 'local_requiredsurvey'));
            }
        }
        
        // Reset redirect counter when no redirection is needed
        $SESSION->questionnaire_redirect_count = 0;
        return true;
    }
    
    /**
     * Check if a user has completed the specified questionnaire
     * 
     * @param int $userid User ID
     * @param int $questionnaire_id Questionnaire ID
     * @return bool True if completed, false otherwise
     */
    public static function has_completed_questionnaire($userid, $questionnaire_id) {
        global $DB;
        
        // For mod_questionnaire, check the response table
        return $DB->record_exists_sql(
            "SELECT r.id 
             FROM {questionnaire_response} r
             WHERE r.userid = ? AND r.questionnaireid = ? AND r.complete = 'y'",
            [$userid, $questionnaire_id]
        );
    }
}