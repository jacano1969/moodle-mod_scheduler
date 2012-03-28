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
 * Statistics report for the scheduler
 *
 * @package    mod
 * @subpackage scheduler
 * @copyright  2011 Henning Bostelmann and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// a function utility for sorting stat results
function byname($a, $b) {
    return strcasecmp($a[0], $b[0]);
}

// precompute groups in case partial popuation is considered by grouping
$groups = groups_get_all_groups($COURSE->id, 0, $cm->groupingid);
$usergroups = array_keys($groups);

//display statistics tabs
$tabs = array('overall', 'studentbreakdown', 'staffbreakdown', 'lengthbreakdown', 'groupbreakdown');
$tabrows = array();
$row  = array();
$currenttab = '';
foreach ($tabs as $tab) {
    $a = ($tab == 'staffbreakdown') ? format_string(scheduler_get_teacher_name($scheduler)) : '';
    $tabname = get_string($tab, 'scheduler', strtolower($a));
    $row[] = new tabobject($tabname, "view.php?what=viewstatistics&amp;id=$cm->id&amp;course=$scheduler->course&amp;page=".$tab, $tabname);
}
$tabrows[] = $row;

print_tabs($tabrows, get_string($page, 'scheduler'));

//display correct type of statistics by request
$attendees = scheduler_get_possible_attendees ($cm, $usergroups);

switch ($page) {
    case 'overall':
        $sql = '
            SELECT
            COUNT(DISTINCT(a.studentid))
            FROM
            {scheduler_slots} s,
            {scheduler_appointment} a
            WHERE
            s.id = a.slotid AND
            s.schedulerid = ? AND
            a.attended = 1
            ';
        $attended = $DB->count_records_sql($sql, array($scheduler->id));

        $sql = '
            SELECT
            COUNT(DISTINCT(a.studentid))
            FROM
            {scheduler_slots} s,
            {scheduler_appointment} a
            WHERE
            s.id = a.slotid AND
            s.schedulerid = ? AND
            a.attended = 0
            ';
        $registered = $DB->count_records_sql($sql, array($scheduler->id));

        $sql = '
            SELECT
            COUNT(DISTINCT(s.id))
            FROM
            {scheduler_slots} s
            LEFT JOIN
            {scheduler_appointment} a
            ON
            s.id = a.slotid
            WHERE
            s.schedulerid = ? AND
            s.teacherid = ? AND
            a.attended IS NULL
            ';
        $freeowned = $DB->count_records_sql($sql, array($scheduler->id, $USER->id));

        $sql = '
            SELECT
            COUNT(DISTINCT(s.id))
            FROM
            {scheduler_slots} s
            LEFT JOIN
            {scheduler_appointment} a
            ON
            s.id = a.slotid
            WHERE
            s.schedulerid = ? AND
            s.teacherid != ? AND
            a.attended IS NULL
            ';
        $freenotowned = $DB->count_records_sql($sql, array($scheduler->id, $USER->id));

        $allattendees = ($attendees) ? count($attendees) : 0;

        $str = '<h3>'.get_string('attendable', 'scheduler').'</h3>';
        $str .= '<b>'.get_string('attendablelbl', 'scheduler').'</b>: ' . $allattendees . '<br/>';
        $str .= '<h3>'.get_string('attended', 'scheduler').'</h3>';
        $str .= '<b>'.get_string('attendedlbl', 'scheduler').'</b>: ' . $attended . '<br/><br/>';
        $str .= '<h3>'.get_string('unattended', 'scheduler').'</h3>';
        $str .= '<b>'.get_string('registeredlbl', 'scheduler').'</b>: ' . $registered . '<br/>';
        $str .= '<b>'.get_string('unregisteredlbl', 'scheduler').'</b>: ' . ($allattendees - $registered - $attended) . '<br/>'; //BUGFIX
        $str .= '<h3>'.get_string('availableslots', 'scheduler').'</h3>';
        $str .= '<b>'.get_string('availableslotsowned', 'scheduler').'</b>: ' . $freeowned . '<br/>';
        $str .= '<b>'.get_string('availableslotsnotowned', 'scheduler').'</b>: ' . $freenotowned . '<br/>';
        $str .= '<b>'.get_string('availableslotsall', 'scheduler').'</b>: ' . ($freeowned + $freenotowned) . '<br/>';

        echo $OUTPUT->box($str);

        break;
    case 'studentbreakdown':
        //display the amount of time each student has received

        if (!empty($attendees)) {
            $table = new html_table();
            $table->head  = array (get_string('student', 'scheduler'), get_string('duration', 'scheduler'));
            $table->align = array ('LEFT', 'CENTER');
            $table->width = '70%';
            $table->data = array();
            $sql = '
                SELECT
                a.studentid,
                SUM(s.duration) as totaltime
                FROM
                {scheduler_slots} s,
                {scheduler_appointment} a
                WHERE
                s.id = a.slotid AND
                a.studentid > 0 AND
                s.schedulerid = ?
                GROUP BY
                a.studentid
                ';
            if ($statrecords = $DB->get_records_sql($sql, array($scheduler->id))) {
                foreach ($statrecords as $arecord) {
                    $table->data[] = array (fullname($attendees[$arecord->studentid]), $arecord->totaltime); // BUGFIX
                }

                uasort($table->data, 'byName');
            }
            echo html_writer::table($table);
        } else {
            echo $OUTPUT->box(get_string('nostudents', 'scheduler'), 'center', '70%');
        }
        break;
    case 'staffbreakdown':
        //display break down by member of staff
        $sql = '
            SELECT
            s.teacherid,
            SUM(s.duration) as totaltime
            FROM
            {scheduler_slots} s
            LEFT JOIN
            {scheduler_appointment} a
            ON
            a.slotid = s.id
            WHERE
            s.schedulerid = ? AND

            a.studentid IS NOT NULL
            GROUP BY
            s.teacherid
            ';
        if ($statrecords = $DB->get_records_sql($sql, array($scheduler->id))) {
            $table = new html_table();
            $table->width = '70%';
            $table->head  = array (s(scheduler_get_teacher_name($scheduler)), get_string('cumulatedduration', 'scheduler'));
            $table->align = array ('LEFT', 'CENTER');
            foreach ($statrecords as $arecord) {
                $ateacher = $DB->get_record('user', array('id'=>$arecord->teacherid));
                $table->data[] = array (fullname($ateacher), $arecord->totaltime);
            }
            uasort($table->data, 'byName');
            echo html_writer::table($table);
        }
        break;
    case 'lengthbreakdown':
        //display by number of atendees to one member of staff
        $sql = '
            SELECT
            s.starttime,
            COUNT(*) as groupsize,
            MAX(s.duration) as duration
            FROM
            {scheduler_slots} s
            LEFT JOIN
            {scheduler_appointment} a
            ON
            a.slotid = s.id
            WHERE
            a.studentid IS NOT NULL AND
            schedulerid = ?
            GROUP BY
            s.starttime
            ORDER BY
            groupsize DESC
            ';
        if ($groupslots = $DB->get_records_sql($sql, array($scheduler->id))) {
            $table = new html_table();
            $table->head  = array (get_string('duration', 'scheduler'), get_string('appointments', 'scheduler'));
            $table->align = array ('LEFT', 'CENTER');
            $table->width = '70%';

            $durationcount = array();
            foreach ($groupslots as $slot) {
                if (array_key_exists($slot->duration, $durationcount)) {
                    $durationcount[$slot->duration] ++;
                } else {
                    $durationcount[$slot->duration] = 1;
                }
            }
            foreach ($durationcount as $key => $duration) {
                $table->data[] = array ($key, $duration);
            }
            echo html_writer::table($table);
        }
        break;
    case 'groupbreakdown':
        //display by number of atendees to one member of staff
        $sql = "
            SELECT
            s.starttime,
            COUNT(*) as groupsize,
            MAX(s.duration) as duration
            FROM
            {scheduler_slots} s
            LEFT JOIN
            {scheduler_appointment} a
            ON
            a.slotid = s.id
            WHERE
            a.studentid IS NOT NULL AND
            schedulerid = '{$scheduler->id}'
            GROUP BY
            s.starttime
            ORDER BY
            groupsize DESC
            ";
        if ($groupslots = $DB->get_records_sql($sql)) {
            $table = new html_table();
            $table->head  = array (get_string('groupsize', 'scheduler'), get_string('occurrences', 'scheduler'), get_string('cumulatedduration', 'scheduler'));
            $table->align = array ('LEFT', 'CENTER', 'CENTER');
            $table->width = '70%';
            $grouprows = array();
            foreach ($groupslots as $agroup) {
                if (!array_key_exists($agroup->groupsize, $grouprows)) {
                    $grouprows[$agroup->groupsize]->occurrences = 0;
                    $grouprows[$agroup->groupsize]->duration = 0;
                }
                $grouprows[$agroup->groupsize]->occurrences++;
                $grouprows[$agroup->groupsize]->duration += $agroup->duration;
            }
            foreach (array_keys($grouprows) as $agroupsize) {
                $table->data[] = array ($agroupsize, $grouprows[$agroupsize]->occurrences, $grouprows[$agroupsize]->duration);
            }
            echo html_writer::table($table);
        }
}
echo '<br/>';
print_continue("$CFG->wwwroot/mod/scheduler/view.php?id=".$cm->id);
/// Finish the page
echo $OUTPUT->footer($course);
exit;
