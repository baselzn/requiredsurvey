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
        
        // Get all active questionnaire configurations
        $configs = $DB->get_records('local_requiredsurvey_config', ['enabled' => 1], 'priority ASC');
        
        if (empty($configs)) {
            // Fall back to old configuration style if no new configs are found
            $questionnaire_id = get_config('local_requiredsurvey', 'questionnaire_id');
            $course_id = get_config('local_requiredsurvey', 'course_id');
            
            if (empty($questionnaire_id) || empty($course_id) || $questionnaire_id == 0 || $course_id == 0) {
                return true; // Not configured yet
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
            
            // Check if user has already completed the questionnaire
            $completed = self::has_completed_questionnaire($USER->id, $config->questionnaire_id);
            
            if (!$completed) {
                // Get the cmid for the questionnaire
                $cm = get_coursemodule_from_instance('questionnaire', $config->questionnaire_id, $config->course_id);
                
                if ($cm) {
                    // Increment redirect counter
                    $SESSION->questionnaire_redirect_count++;
                    
                    // Redirect to the questionnaire
                    $questionnaire_url = new moodle_url('/mod/questionnaire/view.php', [
                        'id' => $cm->id
                    ]);
                    
                    redirect($questionnaire_url, get_string('must_complete_questionnaire', 'local_requiredsurvey'));
                }
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