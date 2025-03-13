<?php
// This file is part of Moodle - http://moodle.org/
//
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category('local_requiredsurvey_category', get_string('pluginname', 'local_requiredsurvey')));
    
    $ADMIN->add('local_requiredsurvey_category', new admin_externalpage('requiredsurvey_management',
        get_string('manage_configurations', 'local_requiredsurvey'),
        new moodle_url('/admin/settings.php', array('section' => 'local_requiredsurvey'))
    ));
    
    $ADMIN->add('local_requiredsurvey_category', new admin_externalpage('requiredsurvey_dashboard',
        get_string('dashboard', 'local_requiredsurvey'),
        new moodle_url('/local/requiredsurvey/dashboard.php')
    ));
}