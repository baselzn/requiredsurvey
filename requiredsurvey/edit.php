<?php
// This file is part of Moodle - http://moodle.org/
//
require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');

// Security checks
admin_externalpage_setup('requiredsurvey_management');
require_capability('local/requiredsurvey:manage', context_system::instance());

// Get parameters
$id = optional_param('id', 0, PARAM_INT); // config ID, 0 means new

// Setup page
$PAGE->set_url('/local/requiredsurvey/edit.php', array('id' => $id));
if ($id) {
    $PAGE->set_title(get_string('edit_questionnaire_config', 'local_requiredsurvey'));
    $PAGE->set_heading(get_string('edit_questionnaire_config', 'local_requiredsurvey'));
} else {
    $PAGE->set_title(get_string('add_questionnaire_config', 'local_requiredsurvey'));
    $PAGE->set_heading(get_string('add_questionnaire_config', 'local_requiredsurvey'));
}

// Define the form
class questionnaire_config_form extends moodleform {
    protected function definition() {
        global $DB, $PAGE;
        
        $mform = $this->_form;
        
        // Name
        $mform->addElement('text', 'name', get_string('config_name', 'local_requiredsurvey'), 
            array('size' => '50'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addHelpButton('name', 'config_name', 'local_requiredsurvey');
        
        // Course selection
        $courses = array(0 => get_string('choose_course', 'local_requiredsurvey'));
        $allcourses = get_courses();
        foreach ($allcourses as $course) {
            if ($course->id == SITEID) {
                continue; // Skip site course
            }
            $courses[$course->id] = format_string($course->fullname);
        }
        
        $mform->addElement('select', 'course_id', get_string('course_id', 'local_requiredsurvey'), $courses);
        $mform->addRule('course_id', null, 'required', null, 'client');
        $mform->addHelpButton('course_id', 'course_id', 'local_requiredsurvey');
        
        // Questionnaire selection (will be populated via AJAX)
        $questionnaires = array(0 => get_string('choose_questionnaire', 'local_requiredsurvey'));
        if (isset($this->_customdata['course_id']) && $this->_customdata['course_id'] > 0) {
            $module = $DB->get_record('modules', array('name' => 'questionnaire'));
            if ($module) {
                $sql = "SELECT q.id, q.name 
                     FROM {questionnaire} q
                     JOIN {course_modules} cm ON cm.instance = q.id
                     WHERE cm.module = ? AND cm.course = ?";
                
                $questionnaire_records = $DB->get_records_sql($sql, 
                    array($module->id, $this->_customdata['course_id']));
                
                foreach ($questionnaire_records as $questionnaire) {
                    $questionnaires[$questionnaire->id] = format_string($questionnaire->name);
                }
            }
        }
        
        $mform->addElement('select', 'questionnaire_id', get_string('questionnaire_id', 'local_requiredsurvey'), 
            $questionnaires);
        $mform->addRule('questionnaire_id', null, 'required', null, 'client');
        $mform->addHelpButton('questionnaire_id', 'questionnaire_id', 'local_requiredsurvey');
        
        // Roles selection
        $roles = array();
        $allroles = get_all_roles();
        foreach ($allroles as $role) {
            $roles[$role->id] = role_get_name($role);
        }
        
        $mform->addElement('select', 'target_roles', get_string('target_roles', 'local_requiredsurvey'), 
            $roles, array('multiple' => true));
        $mform->addHelpButton('target_roles', 'target_roles', 'local_requiredsurvey');
        
        // Cohorts selection
        $cohorts = array();
        $allcohorts = $DB->get_records('cohort', array('contextid' => context_system::instance()->id));
        foreach ($allcohorts as $cohort) {
            $cohorts[$cohort->id] = format_string($cohort->name);
        }
        
        $mform->addElement('select', 'target_cohorts', get_string('target_cohorts', 'local_requiredsurvey'), 
            $cohorts, array('multiple' => true));
        $mform->addHelpButton('target_cohorts', 'target_cohorts', 'local_requiredsurvey');
        
        // Priority
        $mform->addElement('text', 'priority', get_string('priority', 'local_requiredsurvey'), 
            array('size' => '5'));
        $mform->setType('priority', PARAM_INT);
        $mform->setDefault('priority', 1);
        $mform->addHelpButton('priority', 'priority', 'local_requiredsurvey');
        
        // Enabled
        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'local_requiredsurvey'));
        $mform->setDefault('enabled', 1);
        $mform->addHelpButton('enabled', 'enabled', 'local_requiredsurvey');
        
        // Hidden elements
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        
        // Submit buttons
        $this->add_action_buttons();
        
        // Add JavaScript for dynamic questionnaire list
        $PAGE->requires->js_init_code('
            $("#id_course_id").change(function() {
                var courseId = $(this).val();
                if (courseId > 0) {
                    $.ajax({
                        url: "' . $CFG->wwwroot . '/local/requiredsurvey/ajax.php",
                        data: {
                            "action": "get_questionnaires",
                            "courseid": courseId,
                            "sesskey": "' . sesskey() . '"
                        },
                        dataType: "json",
                        success: function(data) {
                            var qSelect = $("#id_questionnaire_id");
                            qSelect.empty();
                            qSelect.append($("<option>").attr("value", "0")
                                .text("' . get_string('choose_questionnaire', 'local_requiredsurvey') . '"));
                            
                            $.each(data.questionnaires, function(id, name) {
                                qSelect.append($("<option>").attr("value", id).text(name));
                            });
                        }
                    });
                }
            });
        ');
    }
}

// Get data if editing existing record
$config = new stdClass();
if ($id) {
    $config = $DB->get_record('local_requiredsurvey_config', array('id' => $id), '*', MUST_EXIST);
    
    // Convert role and cohort lists from comma-separated to arrays
    if (!empty($config->target_roles)) {
        $config->target_roles = explode(',', $config->target_roles);
    }
    
    if (!empty($config->target_cohorts)) {
        $config->target_cohorts = explode(',', $config->target_cohorts);
    }
}

// Create form instance
$customdata = array();
if (!empty($config->course_id)) {
    $customdata['course_id'] = $config->course_id;
}

$form = new questionnaire_config_form(null, $customdata);

// Set existing data
if ($id) {
    $form->set_data($config);
}

// Handle form submission
if ($data = $form->get_data()) {
    $now = time();
    
    // Convert arrays to comma-separated strings
    if (isset($data->target_roles) && is_array($data->target_roles)) {
        $data->target_roles = implode(',', $data->target_roles);
    } else {
        $data->target_roles = '';
    }
    
    if (isset($data->target_cohorts) && is_array($data->target_cohorts)) {
        $data->target_cohorts = implode(',', $data->target_cohorts);
    } else {
        $data->target_cohorts = '';
    }
    
    if ($id) {
        // Update existing record
        $data->timemodified = $now;
        $DB->update_record('local_requiredsurvey_config', $data);
    } else {
        // Create new record
        $data->timecreated = $now;
        $data->timemodified = $now;
        $DB->insert_record('local_requiredsurvey_config', $data);
    }
    
    // Show success message
    \core\notification::success(get_string('config_saved', 'local_requiredsurvey'));
    
    // Redirect to settings page
    redirect(new moodle_url('/admin/settings.php', array('section' => 'local_requiredsurvey')));
}

// Display page
echo $OUTPUT->header();

echo $form->display();

echo $OUTPUT->footer();