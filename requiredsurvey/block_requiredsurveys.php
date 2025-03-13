<?php
// This file is part of Moodle - http://moodle.org/
//
class block_requiredsurveys extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_requiredsurveys');
    }
    
    public function get_content() {
        global $DB, $USER, $CFG;
        
        if ($this->content !== null) {
            return $this->content;
        }
        
        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';
        
        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }
        
        // Get all active questionnaire configurations
        $configs = $DB->get_records('local_requiredsurvey_config', array('enabled' => 1), 'priority ASC');
        
        if (empty($configs)) {
            // Fall back to old configuration style if no new configs are found
            $questionnaire_id = get_config('local_requiredsurvey', 'questionnaire_id');
            $course_id = get_config('local_requiredsurvey', 'course_id');
            
            if (empty($questionnaire_id) || empty($course_id) || $questionnaire_id == 0 || $course_id == 0) {
                return $this->content;
            }
            
            // Create a config object from old settings
            $config = new stdClass();
            $config->course_id = $course_id;
            $config->questionnaire_id = $questionnaire_id;
            $config->target_roles = '';
            $config->target_cohorts = '';
            $config->name = get_string('pluginname', 'local_requiredsurvey');
            
            $configs = [$config];
        }
        
        // Check each questionnaire for the current user
        $pending_questionnaires = array();
        $completed_questionnaires = array();
        
        foreach ($configs as $config) {
            // Check if user should complete this questionnaire based on roles and enrollments
            $should_complete = true;
            
            // Check if enrolled in the course
            $context = context_course::instance($config->course_id);
            if (!is_enrolled($context, $USER->id)) {
                $should_complete = false;
            }
            
            // Check roles if specified
            if ($should_complete && !empty($config->target_roles)) {
                $should_complete = false;
                $roles = explode(',', $config->target_roles);
                
                $user_roles = get_user_roles($context, $USER->id);
                
                foreach ($user_roles as $role) {
                    if (in_array($role->roleid, $roles)) {
                        $should_complete = true;
                        break;
                    }
                }
            }
            
            // Check cohorts if specified
            if ($should_complete && !empty($config->target_cohorts)) {
                $should_complete = false;
                $cohorts = explode(',', $config->target_cohorts);
                
                $sql = "SELECT c.id 
                        FROM {cohort} c
                        JOIN {cohort_members} cm ON cm.cohortid = c.id
                        WHERE cm.userid = ?";
                $user_cohorts = $DB->get_records_sql($sql, array($USER->id));
                
                foreach ($user_cohorts as $cohort) {
                    if (in_array($cohort->id, $cohorts)) {
                        $should_complete = true;
                        break;
                    }
                }
            }
            
            if ($should_complete) {
                // Check if completed
                $completed = local_requiredsurvey_observer::has_completed_questionnaire(
                    $USER->id, $config->questionnaire_id);
                
                $questionnaire = $DB->get_record('questionnaire', array('id' => $config->questionnaire_id));
                $cm = get_coursemodule_from_instance('questionnaire', $config->questionnaire_id, $config->course_id);
                
                if (!$completed && $questionnaire && $cm) {
                    $pending_questionnaires[] = array(
                        'name' => $questionnaire->name,
                        'url' => new moodle_url('/mod/questionnaire/view.php', array('id' => $cm->id)),
                        'config' => $config
                    );
                } elseif ($completed && $questionnaire) {
                    $completed_questionnaires[] = array(
                        'name' => $questionnaire->name,
                        'config' => $config
                    );
                }
            }
        }
        
        // Build block content
        if (!empty($pending_questionnaires)) {
            $this->content->text .= html_writer::tag('h5', get_string('pending_questionnaires', 'block_requiredsurveys'));
            
            foreach ($pending_questionnaires as $questionnaire) {
                $this->content->text .= html_writer::start_tag('div', array('class' => 'required-questionnaire-item'));
                
                // Display alert icon and name
                $icon = html_writer::tag('i', '', array('class' => 'fa fa-exclamation-triangle text-warning mr-2'));
                $link = html_writer::link($questionnaire['url'], $questionnaire['name'], array('class' => 'survey-link'));
                $this->content->text .= $icon . $link;
                
                // Display progress indicator (0% for pending)
                $this->content->text .= html_writer::start_tag('div', array('class' => 'progress mt-2'));
                $this->content->text .= html_writer::tag('div', '0%', array(
                    'class' => 'progress-bar bg-warning',
                    'role' => 'progressbar',
                    'style' => 'width: 0%',
                    'aria-valuenow' => '0',
                    'aria-valuemin' => '0',
                    'aria-valuemax' => '100'
                ));
                $this->content->text .= html_writer::end_tag('div');
                
                // Add "Take Survey" button
                $this->content->text .= html_writer::link(
                    $questionnaire['url'],
                    get_string('take_survey', 'block_requiredsurveys'),
                    array('class' => 'btn btn-sm btn-primary mt-2')
                );
                
                $this->content->text .= html_writer::end_tag('div');
                $this->content->text .= html_writer::tag('hr', '');
            }
        }
        
        if (!empty($completed_questionnaires)) {
            $this->content->text .= html_writer::tag('h5', get_string('completed_questionnaires', 'block_requiredsurveys'));
            
            foreach ($completed_questionnaires as $questionnaire) {
                $this->content->text .= html_writer::start_tag('div', array('class' => 'completed-questionnaire-item'));
                
                // Display check icon and name
                $icon = html_writer::tag('i', '', array('class' => 'fa fa-check-circle text-success mr-2'));
                $this->content->text .= $icon . $questionnaire['name'];
                
                // Display progress indicator (100% for completed)
                $this->content->text .= html_writer::start_tag('div', array('class' => 'progress mt-2'));
                $this->content->text .= html_writer::tag('div', '100%', array(
                    'class' => 'progress-bar bg-success',
                    'role' => 'progressbar',
                    'style' => 'width: 100%',
                    'aria-valuenow' => '100',
                    'aria-valuemin' => '0',
                    'aria-valuemax' => '100'
                ));
                $this->content->text .= html_writer::end_tag('div');
                
                $this->content->text .= html_writer::end_tag('div');
                $this->content->text .= html_writer::tag('hr', '');
            }
        }
        
        if (empty($pending_questionnaires) && empty($completed_questionnaires)) {
            $this->content->text = get_string('no_questionnaires', 'block_requiredsurveys');
        }
        
        return $this->content;
    }
    
    public function applicable_formats() {
        return array(
            'all' => true
        );
    }
    
    public function has_config() {
        return false;
    }
}