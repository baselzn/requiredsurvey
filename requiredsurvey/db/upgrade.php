<?php
// This file is part of Moodle - http://moodle.org/
//
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade function for the plugin
 *
 * @param int $oldversion The old version of the plugin
 * @return bool True on success
 */
function xmldb_local_requiredsurvey_upgrade($oldversion) {
    global $DB;
    
    $dbman = $DB->get_manager();
    
    if ($oldversion < 2023061500) {
        // Define table for multiple questionnaire configurations
        $table = new xmldb_table('local_requiredsurvey_config');
        
        // Define fields
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('course_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('questionnaire_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('target_roles', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('target_cohorts', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('priority', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        
        // Define keys
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        
        // Create table
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        
        // Migrate existing settings to new table if they exist
        $course_id = get_config('local_requiredsurvey', 'course_id');
        $questionnaire_id = get_config('local_requiredsurvey', 'questionnaire_id');
        
        if (!empty($course_id) && !empty($questionnaire_id) && $course_id > 0 && $questionnaire_id > 0) {
            $record = new stdClass();
            $record->course_id = $course_id;
            $record->questionnaire_id = $questionnaire_id;
            $record->name = 'Default configuration';
            $record->enabled = 1;
            $record->priority = 1;
            $record->timecreated = time();
            $record->timemodified = time();
            
            $DB->insert_record('local_requiredsurvey_config', $record);
        }
        
        // Add default settings for email reports
        set_config('enable_email_reports', 0, 'local_requiredsurvey');
        set_config('report_recipients', '', 'local_requiredsurvey');
        
        // Upgrade successful
        upgrade_plugin_savepoint(true, 2023061500, 'local', 'requiredsurvey');
    }
    
    return true;
}