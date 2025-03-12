<?php
// This file is part of Moodle - http://moodle.org/
//
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\user_loggedin',
        'callback'  => 'local_requiredsurvey_observer::user_loggedin',
        'internal'  => false
    ]
];