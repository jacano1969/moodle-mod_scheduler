<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Global configuration settings for the scheduler module.
 *
 * @package    mod
 * @subpackage scheduler
 * @copyright  2011 Henning Bostelmann and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/mod/scheduler/lib.php');

$settings->add(new admin_setting_configcheckbox('scheduler_allteachersgrading', get_string('allteachersgrading', 'scheduler'),
    get_string('allteachersgrading_desc', 'scheduler'), 0));

$settings->add(new admin_setting_configcheckbox('scheduler_showemailplain', get_string('showemailplain', 'scheduler'),
    get_string('showemailplain_desc', 'scheduler'), 0));

$settings->add(new admin_setting_configcheckbox('scheduler_groupscheduling', get_string('groupscheduling', 'scheduler'),
    get_string('groupscheduling_desc', 'scheduler'), 1));
