<?php
// This file is part of Moodle - http://moodle.org/
//
defined('MOODLE_INTERNAL') || die();

/**
 * Installation hook for the plugin
 */
function xmldb_local_requiredsurvey_install() {
    // Create default plugin settings
    set_config('questionnaire_id', 0, 'local_requiredsurvey');
    set_config('course_id', 0, 'local_requiredsurvey');
    
    return true;
}