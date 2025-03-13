<?php
// This file is part of Moodle - http://moodle.org/
//
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    global $CFG, $PAGE, $OUTPUT, $DB;
    
    // Create settings page
    $settings = new admin_settingpage('local_requiredsurvey', get_string('pluginname', 'local_requiredsurvey'));
    $ADMIN->add('localplugins', $settings);
    
    // Main heading
    $settings->add(new admin_setting_heading('local_requiredsurvey_heading', 
        get_string('heading', 'local_requiredsurvey'), 
        get_string('heading_desc', 'local_requiredsurvey')));
    
    // Show all current questionnaire configurations
    $configs = $DB->get_records('local_requiredsurvey_config', null, 'priority ASC');
    
    // Display table of current configurations
    if (!empty($configs)) {
        $table_html = html_writer::start_tag('table', ['class' => 'table table-striped']);
        $table_html .= html_writer::start_tag('thead');
        $table_html .= html_writer::start_tag('tr');
        $table_html .= html_writer::tag('th', get_string('config_name', 'local_requiredsurvey'));
        $table_html .= html_writer::tag('th', get_string('course_id', 'local_requiredsurvey'));
        $table_html .= html_writer::tag('th', get_string('questionnaire_id', 'local_requiredsurvey'));
        $table_html .= html_writer::tag('th', get_string('target_roles', 'local_requiredsurvey'));
        $table_html .= html_writer::tag('th', get_string('target_cohorts', 'local_requiredsurvey'));
        $table_html .= html_writer::tag('th', get_string('priority', 'local_requiredsurvey'));
        $table_html .= html_writer::tag('th', get_string('enabled', 'local_requiredsurvey'));
        $table_html .= html_writer::tag('th', get_string('actions', 'local_requiredsurvey'));
        $table_html .= html_writer::end_tag('tr');
        $table_html .= html_writer::end_tag('thead');

        $table_html .= html_writer::start_tag('tbody');
        foreach ($configs as $config) {
            $course = $DB->get_record('course', array('id' => $config->course_id));
            $questionnaire = $DB->get_record('questionnaire', array('id' => $config->questionnaire_id));
            
            // Get role names
            $role_names = array();
            if (!empty($config->target_roles)) {
                $role_ids = explode(',', $config->target_roles);
                foreach ($role_ids as $role_id) {
                    $role = $DB->get_record('role', array('id' => $role_id));
                    if ($role) {
                        $role_names[] = role_get_name($role);
                    }
                }
            }
            
            // Get cohort names
            $cohort_names = array();
            if (!empty($config->target_cohorts)) {
                $cohort_ids = explode(',', $config->target_cohorts);
                foreach ($cohort_ids as $cohort_id) {
                    $cohort = $DB->get_record('cohort', array('id' => $cohort_id));
                    if ($cohort) {
                        $cohort_names[] = format_string($cohort->name);
                    }
                }
            }
            
            $table_html .= html_writer::start_tag('tr');
            $table_html .= html_writer::tag('td', format_string($config->name));
            $table_html .= html_writer::tag('td', $course ? format_string($course->fullname) : '');
            $table_html .= html_writer::tag('td', $questionnaire ? format_string($questionnaire->name) : '');
            $table_html .= html_writer::tag('td', !empty($role_names) ? implode(', ', $role_names) : get_string('all_roles', 'local_requiredsurvey'));
            $table_html .= html_writer::tag('td', !empty($cohort_names) ? implode(', ', $cohort_names) : get_string('all_cohorts', 'local_requiredsurvey'));
            $table_html .= html_writer::tag('td', $config->priority);
            $table_html .= html_writer::tag('td', $config->enabled ? get_string('yes') : get_string('no'));
            $table_html .= html_writer::tag('td', 
                html_writer::link(
                    new moodle_url('/local/requiredsurvey/edit.php', ['id' => $config->id]),
                    $OUTPUT->pix_icon('t/edit', get_string('edit')),
                    ['title' => get_string('edit')]
                ) . ' ' .
                html_writer::link(
                    new moodle_url('/local/requiredsurvey/delete.php', ['id' => $config->id, 'sesskey' => sesskey()]),
                    $OUTPUT->pix_icon('t/delete', get_string('delete')),
                    ['title' => get_string('delete')]
                )
            );
            $table_html .= html_writer::end_tag('tr');
        }
        $table_html .= html_writer::end_tag('tbody');
        $table_html .= html_writer::end_tag('table');
        
        $settings->add(new admin_setting_heading('requiredsurvey_configs', get_string('current_configurations', 'local_requiredsurvey'), $table_html));
    } else {
        // No configurations yet
        $settings->add(new admin_setting_heading('requiredsurvey_configs', get_string('current_configurations', 'local_requiredsurvey'), 
            get_string('no_configurations', 'local_requiredsurvey')));
    }
    
    // Add button for new configuration
    $add_button = html_writer::link(
        new moodle_url('/local/requiredsurvey/edit.php'),
        get_string('add_questionnaire_config', 'local_requiredsurvey'),
        ['class' => 'btn btn-primary']
    );
    
    $settings->add(new admin_setting_heading('add_config_button', '', $add_button));
    
    // Dashboard link
    $dashboard_link = html_writer::link(
        new moodle_url('/local/requiredsurvey/dashboard.php'),
        get_string('dashboard', 'local_requiredsurvey'),
        ['class' => 'btn btn-secondary ml-2']
    );
    
    $settings->add(new admin_setting_heading('dashboard_link', '', $dashboard_link));
    
    // Email notification settings
    $settings->add(new admin_setting_heading('email_settings_heading', 
        get_string('email_settings', 'local_requiredsurvey'), ''));
    
    $settings->add(new admin_setting_configcheckbox(
        'local_requiredsurvey/enable_email_reports',
        get_string('enable_email_reports', 'local_requiredsurvey'),
        get_string('enable_email_reports_desc', 'local_requiredsurvey'),
        0
    ));
    
    $settings->add(new admin_setting_configtext(
        'local_requiredsurvey/report_recipients',
        get_string('report_recipients', 'local_requiredsurvey'),
        get_string('report_recipients_desc', 'local_requiredsurvey'),
        ''
    ));
    
    // Handle old settings migration if necessary - check if the old style settings are set but no configs exist
    if (empty($configs)) {
        $questionnaire_id = get_config('local_requiredsurvey', 'questionnaire_id');
        $course_id = get_config('local_requiredsurvey', 'course_id');
        
        if (!empty($questionnaire_id) && !empty($course_id) && $questionnaire_id > 0 && $course_id > 0) {
            // Show a notice about the old configuration
            $questionnaire = $DB->get_record('questionnaire', array('id' => $questionnaire_id));
            $course = $DB->get_record('course', array('id' => $course_id));
            
            if ($questionnaire && $course) {
                $legacy_notice = $OUTPUT->notification(
                    get_string('legacy_config_notice', 'local_requiredsurvey', array(
                        'questionnaire' => format_string($questionnaire->name),
                        'course' => format_string($course->fullname)
                    )),
                    'info'
                );
                
                $settings->add(new admin_setting_heading('legacy_config', '', $legacy_notice));
                
                // Add a button to migrate
                $migrate_button = html_writer::link(
                    new moodle_url('/local/requiredsurvey/migrate.php', array('sesskey' => sesskey())),
                    get_string('migrate_config', 'local_requiredsurvey'),
                    ['class' => 'btn btn-info']
                );
                
                $settings->add(new admin_setting_heading('migrate_button', '', $migrate_button));
            }
        }
    }
    
    // For backward compatibility, maintain the old settings form, but hide it if we have new configs
    if (empty($configs)) {
        // Process form submissions for old style configuration
        $select_course = optional_param('select_course', 0, PARAM_INT);
        $questionnaire_id = optional_param('questionnaire_id', 0, PARAM_INT);
        $saved = optional_param('saved', 0, PARAM_INT);
        
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
        
        $settings->add(new admin_setting_heading('course_selection', get_string('legacy_settings', 'local_requiredsurvey'), $course_html));
        
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
}