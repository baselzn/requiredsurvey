<?php
// This file is part of Moodle - http://moodle.org/
//
namespace local_requiredsurvey\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task to send completion rate reports to administrators
 */
class send_completion_report extends \core\task\scheduled_task {
    /**
     * Get a descriptive name for this task
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_send_completion_report', 'local_requiredsurvey');
    }
    
    /**
     * Execute the task
     */
    public function execute() {
        global $DB, $CFG, $SITE;
        
        require_once($CFG->libdir . '/moodlelib.php');
        
        // Check if email notifications are enabled
        $enabled = get_config('local_requiredsurvey', 'enable_email_reports');
        if (!$enabled) {
            return;
        }
        
        // Get email recipients
        $recipients = get_config('local_requiredsurvey', 'report_recipients');
        if (empty($recipients)) {
            // If no specific recipients, get site admins
            $recipients = get_admins();
        } else {
            // Parse email addresses
            $emails = explode(',', $recipients);
            $recipients = array();
            foreach ($emails as $email) {
                $email = trim($email);
                if (validate_email($email)) {
                    $user = new \stdClass();
                    $user->email = $email;
                    $user->id = 0; // Not a real user
                    $recipients[] = $user;
                }
            }
        }
        
        if (empty($recipients)) {
            return;
        }
        
        // Get completion data
        $completion_data = $this->get_completion_data();
        
        if (empty($completion_data)) {
            return;
        }
        
        // Build email message
        $subject = get_string('email_report_subject', 'local_requiredsurvey', array(
            'sitename' => format_string($SITE->fullname)
        ));
        
        $message = get_string('email_report_intro', 'local_requiredsurvey') . "\n\n";
        
        // Add completion summary
        $message .= get_string('email_report_summary', 'local_requiredsurvey') . "\n";
        $message .= "-----------------------------------------------\n";
        
        foreach ($completion_data as $data) {
            $message .= get_string('email_report_line', 'local_requiredsurvey', array(
                'name' => $data['name'],
                'questionnaire' => $data['questionnaire'],
                'course' => $data['course'],
                'completed' => $data['completed_users'],
                'total' => $data['total_users'],
                'rate' => $data['completion_rate']
            ));
            $message .= "\n";
        }
        
        $message .= "\n";
        $message .= get_string('email_report_link', 'local_requiredsurvey', array(
            'url' => $CFG->wwwroot . '/local/requiredsurvey/dashboard.php'
        ));
        
        // HTML version
        $messagehtml = '<h3>' . get_string('email_report_intro', 'local_requiredsurvey') . '</h3>';
        
        $messagehtml .= '<h4>' . get_string('email_report_summary', 'local_requiredsurvey') . '</h4>';
        $messagehtml .= '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
        $messagehtml .= '<tr><th>' . get_string('questionnaire_name', 'local_requiredsurvey') . '</th>';
        $messagehtml .= '<th>' . get_string('course', 'local_requiredsurvey') . '</th>';
        $messagehtml .= '<th>' . get_string('completed_users', 'local_requiredsurvey') . '</th>';
        $messagehtml .= '<th>' . get_string('total_users', 'local_requiredsurvey') . '</th>';
        $messagehtml .= '<th>' . get_string('completion_rate', 'local_requiredsurvey') . '</th></tr>';
        
        foreach ($completion_data as $data) {
            $messagehtml .= '<tr>';
            $messagehtml .= '<td>' . $data['name'] . ' (' . $data['questionnaire'] . ')</td>';
            $messagehtml .= '<td>' . $data['course'] . '</td>';
            $messagehtml .= '<td>' . $data['completed_users'] . '</td>';
            $messagehtml .= '<td>' . $data['total_users'] . '</td>';
            $messagehtml .= '<td>' . $data['completion_rate'] . '%';
            
            // Add visual indicator
            $color = $data['completion_rate'] < 50 ? 'red' : ($data['completion_rate'] < 80 ? 'orange' : 'green');
            $messagehtml .= '<div style="background-color: #eee; width: 100px; height: 20px;">';
            $messagehtml .= '<div style="background-color: ' . $color . '; width: ' . $data['completion_rate'] . '%; height: 20px;"></div>';
            $messagehtml .= '</div>';
            
            $messagehtml .= '</td></tr>';
        }
        
        $messagehtml .= '</table>';
        
        $messagehtml .= '<p><a href="' . $CFG->wwwroot . '/local/requiredsurvey/dashboard.php">' . 
                        get_string('view_full_report', 'local_requiredsurvey') . '</a></p>';
        
        // Send to all recipients
        foreach ($recipients as $recipient) {
            email_to_user($recipient, \core_user::get_noreply_user(), $subject, $message, $messagehtml);
        }
    }
    
    /**
     * Get completion data for all questionnaires
     *
     * @return array
     */
    private function get_completion_data() {
        global $DB;
        
        // Get questionnaire configurations
        $configs = $DB->get_records('local_requiredsurvey_config', array('enabled' => 1));
        
        if (empty($configs)) {
            return array();
        }
        
        $completion_data = array();
        
        foreach ($configs as $config) {
            // Get total applicable users
            $params = array();
            $sql = "SELECT COUNT(DISTINCT u.id) as usercount
                    FROM {user} u
                    JOIN {role_assignments} ra ON ra.userid = u.id
                    JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                    WHERE u.deleted = 0 AND u.suspended = 0
                    AND ctx.instanceid = ?";
            
            $params[] = $config->course_id;
            
            // Add role filters if applicable
            if (!empty($config->target_roles)) {
                $roles = explode(',', $config->target_roles);
                list($insql, $inparams) = $DB->get_in_or_equal($roles);
                $sql .= " AND ra.roleid $insql";
                $params = array_merge($params, $inparams);
            }
            
            // Add cohort filters if applicable
            if (!empty($config->target_cohorts)) {
                $cohorts = explode(',', $config->target_cohorts);
                $sql .= " AND u.id IN (
                          SELECT cm.userid 
                          FROM {cohort_members} cm 
                          WHERE cm.cohortid IN (";
                
                $cohort_placeholders = array();
                foreach ($cohorts as $cohort) {
                    $cohort_placeholders[] = '?';
                    $params[] = $cohort;
                }
                
                $sql .= implode(',', $cohort_placeholders) . "))";
            }
            
            $total_users = $DB->count_records_sql($sql, $params);
            
            // Get completed users
            $sql = "SELECT COUNT(DISTINCT r.userid) as completed
                    FROM {questionnaire_response} r
                    JOIN {user} u ON u.id = r.userid
                    JOIN {role_assignments} ra ON ra.userid = u.id
                    JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                    WHERE r.questionnaireid = ? AND r.complete = 'y'
                    AND u.deleted = 0 AND u.suspended = 0
                    AND ctx.instanceid = ?";
            
            $params = array($config->questionnaire_id, $config->course_id);
            
            // Add role filters if applicable
            if (!empty($config->target_roles)) {
                $roles = explode(',', $config->target_roles);
                list($insql, $inparams) = $DB->get_in_or_equal($roles);
                $sql .= " AND ra.roleid $insql";
                $params = array_merge($params, $inparams);
            }
            
            // Add cohort filters if applicable
            if (!empty($config->target_cohorts)) {
                $cohorts = explode(',', $config->target_cohorts);
                $sql .= " AND u.id IN (
                          SELECT cm.userid 
                          FROM {cohort_members} cm 
                          WHERE cm.cohortid IN (";
                
                $cohort_placeholders = array();
                foreach ($cohorts as $cohort) {
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
        
        return $completion_data;
    }
}