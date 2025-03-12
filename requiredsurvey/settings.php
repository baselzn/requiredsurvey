<?php
// This file is part of Moodle - http://moodle.org/
//
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    global $CFG, $PAGE, $OUTPUT, $DB;
    
    // Process form submissions
    $select_course = optional_param('select_course', 0, PARAM_INT);
    $questionnaire_id = optional_param('questionnaire_id', 0, PARAM_INT);
    $saved = optional_param('saved', 0, PARAM_INT);
    
    // Create settings page
    $settings = new admin_settingpage('local_requiredsurvey', get_string('pluginname', 'local_requiredsurvey'));
    $ADMIN->add('localplugins', $settings);
    
    // Handle course selection
    if ($select_course && confirm_sesskey()) {
        set_config('course_id', $select_course, 'local_requiredsurvey');
        // Reset questionnaire selection when changing course
        set_config('questionnaire_id', 0, 'local_requiredsurvey');
        // Using JavaScript redirect to avoid POST issues
        $PAGE->requires->js_init_code("
            window.location.href = '{$CFG->wwwroot}/admin/settings.php?section=local_requiredsurvey';
        ");
    }
    
    // Handle questionnaire selection
    if ($questionnaire_id && confirm_sesskey()) {
        set_config('questionnaire_id', $questionnaire_id, 'local_requiredsurvey');
        $saved = 1; // Set flag to show success message
    }
    
    // Add notification if settings were saved
    if ($saved) {
        $notification = $OUTPUT->notification(get_string('settings_saved', 'local_requiredsurvey'), 'notifysuccess');
        $settings->add(new admin_setting_heading('save_notification', '', $notification));
    }
    
    // Main heading
    $settings->add(new admin_setting_heading('local_requiredsurvey_heading', 
        get_string('heading', 'local_requiredsurvey'), 
        get_string('heading_desc', 'local_requiredsurvey')));
    
    // Get current settings
    $current_course_id = get_config('local_requiredsurvey', 'course_id');
    $current_questionnaire_id = get_config('local_requiredsurvey', 'questionnaire_id');
    
    // Get all courses
    $courses = array(0 => get_string('choose_course', 'local_requiredsurvey'));
    $allcourses = get_courses();
    foreach ($allcourses as $course) {
        if ($course->id == SITEID) {
            continue; // Skip site course
        }
        $courses[$course->id] = format_string($course->fullname);
    }
    
    // Course selection part
    $course_html = html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $CFG->wwwroot . '/admin/settings.php?section=local_requiredsurvey',
        'class' => 'mform',
        'id' => 'course_select_form'
    ]);
    
    $course_html .= html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'sesskey',
        'value' => sesskey()
    ]);
    
    $course_html .= html_writer::start_div('form-group row');
    $course_html .= html_writer::start_div('col-md-3');
    $course_html .= html_writer::tag('label', get_string('course_id', 'local_requiredsurvey'), [
        'for' => 'select_course',
        'class' => 'col-form-label d-inline'
    ]);
    $course_html .= html_writer::end_div();
    
    $course_html .= html_writer::start_div('col-md-9');
    $course_html .= html_writer::select($courses, 'select_course', $current_course_id, false, [
        'class' => 'custom-select'
    ]);
    $course_html .= html_writer::tag('div', get_string('course_id_desc', 'local_requiredsurvey'), [
        'class' => 'form-text text-muted'
    ]);
    $course_html .= html_writer::end_div();
    $course_html .= html_writer::end_div();
    
    $course_html .= html_writer::start_div('form-group row');
    $course_html .= html_writer::start_div('col-md-9 offset-md-3');
    $course_html .= html_writer::tag('button', get_string('select_course', 'local_requiredsurvey'), [
        'type' => 'submit',
        'class' => 'btn btn-primary'
    ]);
    $course_html .= html_writer::end_div();
    $course_html .= html_writer::end_div();
    
    $course_html .= html_writer::end_tag('form');
    
    $settings->add(new admin_setting_heading('course_selection', '', $course_html));
    
    // If course is selected, show questionnaire selection
    if (!empty($current_course_id) && $current_course_id > 0) {
        // Show selected course info
        $course = $DB->get_record('course', array('id' => $current_course_id));
        if ($course) {
            $course_info = $OUTPUT->notification(
                get_string('selected_course', 'local_requiredsurvey', format_string($course->fullname)), 
                'info'
            );
            $settings->add(new admin_setting_heading('selected_course_info', '', $course_info));
        }
        
        // Get questionnaires for this course
        $questionnaires = array(0 => get_string('choose_questionnaire', 'local_requiredsurvey'));
        $module = $DB->get_record('modules', array('name' => 'questionnaire'));
        
        if ($module) {
            $sql = "SELECT q.id, q.name 
                 FROM {questionnaire} q
                 JOIN {course_modules} cm ON cm.instance = q.id
                 WHERE cm.module = ? AND cm.course = ?";
            
            $questionnaire_records = $DB->get_records_sql($sql, array($module->id, $current_course_id));
            
            foreach ($questionnaire_records as $questionnaire) {
                $questionnaires[$questionnaire->id] = format_string($questionnaire->name);
            }
        }
        
        // Only show form if we found questionnaires
        if (count($questionnaires) > 1) {
            // Questionnaire selection part
            $questionnaire_html = html_writer::start_tag('form', [
                'method' => 'post',
                'action' => $CFG->wwwroot . '/admin/settings.php?section=local_requiredsurvey',
                'class' => 'mform',
                'id' => 'questionnaire_select_form'
            ]);
            
            $questionnaire_html .= html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => 'sesskey',
                'value' => sesskey()
            ]);
            
            $questionnaire_html .= html_writer::start_div('form-group row');
            $questionnaire_html .= html_writer::start_div('col-md-3');
            $questionnaire_html .= html_writer::tag('label', get_string('questionnaire_id', 'local_requiredsurvey'), [
                'for' => 'questionnaire_id',
                'class' => 'col-form-label d-inline'
            ]);
            $questionnaire_html .= html_writer::end_div();
            
            $questionnaire_html .= html_writer::start_div('col-md-9');
            $questionnaire_html .= html_writer::select($questionnaires, 'questionnaire_id', $current_questionnaire_id, false, [
                'class' => 'custom-select'
            ]);
            $questionnaire_html .= html_writer::tag('div', get_string('questionnaire_id_desc', 'local_requiredsurvey'), [
                'class' => 'form-text text-muted'
            ]);
            $questionnaire_html .= html_writer::end_div();
            $questionnaire_html .= html_writer::end_div();
            
            $questionnaire_html .= html_writer::start_div('form-group row');
            $questionnaire_html .= html_writer::start_div('col-md-9 offset-md-3');
            $questionnaire_html .= html_writer::tag('button', get_string('save_settings', 'local_requiredsurvey'), [
                'type' => 'submit',
                'class' => 'btn btn-primary'
            ]);
            $questionnaire_html .= html_writer::end_div();
            $questionnaire_html .= html_writer::end_div();
            
            $questionnaire_html .= html_writer::end_tag('form');
            
            $settings->add(new admin_setting_heading('questionnaire_selection', '', $questionnaire_html));
            
            // Show current selection if any
            if (!empty($current_questionnaire_id)) {
                $questionnaire = $DB->get_record('questionnaire', array('id' => $current_questionnaire_id));
                if ($questionnaire) {
                    $selected_info = $OUTPUT->notification(
                        get_string('selected_questionnaire', 'local_requiredsurvey', format_string($questionnaire->name)), 
                        'success'
                    );
                    $settings->add(new admin_setting_heading('current_selection', '', $selected_info));
                }
            }
        } else {
            $no_questionnaires = $OUTPUT->notification(
                get_string('no_questionnaires', 'local_requiredsurvey'), 
                'warning'
            );
            $settings->add(new admin_setting_heading('no_questionnaires_heading', '', $no_questionnaires));
        }
    }
}