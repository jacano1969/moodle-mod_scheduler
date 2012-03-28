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
 * Student scheduler screen (where students choose appointments).
 *
 * @package    mod
 * @subpackage scheduler
 * @copyright  2011 Henning Bostelmann and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/scheduler/studentview.controller.php');

$mygroups = groups_get_all_groups($course->id, $USER->id, $cm->groupingid, 'g.id, g.name');

// printing head information

echo $OUTPUT->heading($scheduler->name);
if (trim(strip_tags($scheduler->intro))) {
    echo $OUTPUT->box_start('mod_introbox');
    echo format_module_intro('scheduler', $scheduler, $cm->id);
    echo $OUTPUT->box_end();
}

$OUTPUT->box_start('center', '80%');
if (scheduler_has_slot($USER->id, $scheduler, true)) {
    print_string('welcomebackstudent', 'scheduler');
} else {
    print_string('welcomenewstudent', 'scheduler');
}
$OUTPUT->box_end();

// clean all late slots (for every body, anyway, they are passed !!)
scheduler_free_late_unused_slots($scheduler->id);

// get information about appointment attention

$sql = '
    SELECT
    COUNT(*)
    FROM
    {scheduler_slots} s,
    {scheduler_appointment} a
    WHERE
    s.id = a.slotid AND
    a.studentid = ? AND
    a.attended = 1 AND
    s.schedulerid = ?
    ';
$hasattended = $DB->count_records_sql($sql, array($USER->id, $scheduler->id));

// get available slots

$haveunattendedappointments = false;
if ($slots = scheduler_get_available_slots($USER->id, $scheduler->id, true)) {
    $minhidedate = 0; // very far in the past
    $studentslots = array();
    $studentattendedslots = array();
    foreach ($slots as $slot) {
        // check if other appointement is not "on the way". Student could not apply to it.
        if (scheduler_get_conflicts($scheduler->id, $slot->starttime, $slot->starttime + $slot->duration * 60, 0, $USER->id, SCHEDULER_OTHERS)) {
            continue;
        }

        // check if not mine and late, don't care
        if (!$slot->appointedbyme and $slot->starttime + (60 * $slot->duration) < time()) {
            continue;
        }

        // check what to print in groupsession indication
        if ($slot->exclusivity == 0) {
            $slot->groupsession = get_string('yes');
        } else {
            // $consumed = scheduler_get_consumed($scheduler->id, $slot->starttime, $slot->starttime + $slot->duration * 60, $slot->teacher);
            if ($slot->exclusivity > $slot->population) {
                $remaining = ($slot->exclusivity - $slot->population).'/'.$slot->exclusivity;
                $slot->groupsession = get_string('limited', 'scheduler', $remaining);
            } else { // should not be visible to students
                $slot->groupsession = get_string('complete', 'scheduler');
            }
        }

        // examine slot situations and elects those which have sense for the current student

        // I am in slot, unconditionnally
        if ($slot->appointedbyme) {
            if ($slot->attended) {
                $studentattendedslots[$slot->starttime.'_'.$slot->teacherid] = $slot;
            } else {
                $studentslots[$slot->starttime.'_'.$slot->teacherid] = $slot;
            }
            // binary or and and required here to calculate properly
            $haveunattendedappointments = $haveunattendedappointments | ($slot->appointedbyme & !$slot->attended);
        } else {
            // slot is free
            if (!$slot->appointed) {
                //if student is only allowed one appointment and this student has already had their then skip this record
                if (($hasattended) and ($scheduler->schedulermode == 'oneonly')) {
                    continue;
                } else if ($slot->hideuntil <= time()) {
                    $studentslots[$slot->starttime.'_'.$slot->teacherid] = $slot;
                }
                $minhidedate = ($slot->hideuntil < $minhidedate || $minhidedate == 0) ? $slot->hideuntil : $minhidedate;
            } else if ($slot->appointed and (($slot->exclusivity == 0) || ($slot->exclusivity > $slot->population))) {
                // slot is booked by another student, group booking is allowed and there is still room
                // there is already a record fot this time/teacher : sure its our's
                if (array_key_exists($slot->starttime.'_'.$slot->teacherid, $studentslots)) {
                    continue;
                }
                // else record the slot with this user (not me).
                $studentslots[$slot->starttime.'_'.$slot->teacherid] = $slot;
            }
        }
    }

    // prepare attended slot table

    if (count($studentattendedslots)) {
        echo $OUTPUT->heading(get_string('attendedslots', 'scheduler'));

        $table = new html_table();

        $table->head  = array ($strdate, s(scheduler_get_teacher_name($scheduler)), $strnote, $strgrade);
        $table->align = array ('LEFT', 'CENTER', 'LEFT', 'LEFT');
        $table->size = array ('', '', '40%', '150');
        $table->width = '90%';
        $table->data = array();
        $previousdate = '';
        $previoustime = 0;
        $previousendtime = 0;

        foreach ($studentattendedslots as $key => $aslot) {
            // preparing data
            $startdate = scheduler_userdate($aslot->starttime, 1);
            $starttime = scheduler_usertime($aslot->starttime, 1);
            $endtime = scheduler_usertime($aslot->starttime + ($aslot->duration * 60), 1);
            $startdatestr = ($startdate == $previousdate) ? '' : $startdate;
            $starttimestr = ($starttime == $previoustime) ? '' : $starttime;
            $endtimestr = ($endtime == $previousendtime) ? '' : $endtime;
            $studentappointment = $DB->get_record('scheduler_appointment', array('slotid' => $aslot->id, 'studentid' => $USER->id));
            if ($scheduler->scale  > 0) {
                $studentappointment->grade = $studentappointment->grade.'/'.$scheduler->scale;
            }

            if (has_capability('mod/scheduler:seeotherstudentsresults', $context)) {
                $appointments = scheduler_get_appointments($aslot->id);
                $collegues = '';
                foreach ($appointments as $appstudent) {
                    $grade = $appstudent->grade;
                    if ($scheduler->scale > 0) {
                        $grade = $grade . '/' . $scheduler->scale;
                    }
                    $student = $DB->get_record('user', array('id' => $appstudent->studentid));
                    $picture = print_user_picture($appstudent->studentid, $course->id, $student->picture, 0, true, true);
                    $name = fullname($student);
                    if ($appstudent->studentid == $USER->id) {
                        $name = "<b>$name</b>"; // it's me!!
                    }
                    $collegues .= " $picture $name ($grade)<br/>";
                }
            } else {
                $collegues = $studentappointment->grade;
            }

            $studentnotes1 = '';
            $studentnotes2 = '';
            if ($aslot->notes != '') {
                $studentnotes1 = '<div class="slotnotes">';
                $studentnotes1 .= '<b>'.get_string('yourslotnotes', 'scheduler').'</b><br/>';
                $studentnotes1 .= format_string($aslot->notes).'</div>';
            }
            if ($studentappointment->appointmentnote != '') {
                $studentnotes2 .= '<div class="appointmentnote">';
                $studentnotes2 .= '<b>'.get_string('yourappointmentnote', 'scheduler').'</b><br/>';
                $studentnotes2 .= format_string($studentappointment->appointmentnote).'</div>';
            }
            $studentnotes = "{$studentnotes1}{$studentnotes2}";

            // recording data into table
            $teacher = $DB->get_record('user', array('id'=>$aslot->teacherid));
            $table->data[] = array ("<span class=\"attended\">$startdatestr</span><br/><div class=\"timelabel\">[$starttimestr - $endtimestr]</div>",
                "<a href=\"../../user/view.php?id={$aslot->teacherid}&amp;course={$scheduler->course}\">".fullname($teacher).'</a>', $studentnotes, $collegues);

            $previoustime = $starttime;
            $previousendtime = $endtime;
            $previousdate = $startdate;
        }

        echo html_writer::table($table);
    }

    // prepare appointable slot table

    echo $OUTPUT->heading(get_string('slots', 'scheduler'));

    $table = new html_table;
    $table->head  = array ($strdate, $strstart, $strend, get_string('choice', 'scheduler'), s(scheduler_get_teacher_name($scheduler)), get_string('groupsession', 'scheduler'));
    $table->align = array ('LEFT', 'LEFT', 'CENTER', 'CENTER', 'LEFT');
    $table->data = array();
    $previousdate = '';
    $previoustime = 0;
    $previousendtime = 0;
    $canappoint = false;
    foreach ($studentslots as $key => $aslot) {
        $startdate = scheduler_userdate($aslot->starttime, 1);
        $starttime = scheduler_usertime($aslot->starttime, 1);
        $endtime = scheduler_usertime($aslot->starttime + ($aslot->duration * 60), 1);
        $startdatestr = ($startdate == $previousdate) ? '' : $startdate;
        $starttimestr = ($starttime == $previoustime) ? '' : $starttime;
        $endtimestr = ($endtime == $previousendtime) ? '' : $endtime;
        if ($aslot->appointedbyme and !$aslot->attended) {
            $teacher = $DB->get_record('user', array('id' => $aslot->teacherid));
            $radio = "<input type=\"radio\" name=\"slotid\" value=\"{$aslot->id}\" checked=\"checked\" />\n";
            $table->data[] = array ("<b>$startdatestr</b>", "<b>$starttime</b>", "<b>$endtime</b>", $radio, "<b>".
                "<a href=\"../../user/view.php?id={$aslot->teacherid}&amp;course=$scheduler->course\">".fullname($teacher).'</a></b>', '<b>'.$aslot->groupsession.'</b>');
        } else {
            if ($aslot->appointed and has_capability('mod/scheduler:seeotherstudentsbooking', $context)) {
                $appointments = scheduler_get_appointments($aslot->id);
                $collegues = "<div style=\"visibility:hidden; display:none\" id=\"collegues{$aslot->id}\"><br/>";
                foreach ($appointments as $appstudent) {
                    $student = $DB->get_record('user', array('id'=>$appstudent->studentid));
                    $picture = $OUTPUT->user_picture($student, array('courseid'=>$course->id));
                    $name = "<a href=\"view.php?what=viewstudent&amp;id={$cm->id}&amp;studentid={$student->id}&amp;course={$scheduler->course}&amp;order=DESC\">".
                        fullname($student).'</a>';
                    $collegues .= " $picture $name<br/>";
                }
                $collegues .= '</div>';
                $aslot->groupsession .= " <a href=\"javascript:toggleVisibility('{$aslot->id}')\"><img name=\"group<?php p($aslot->id) ?>\" src=\"{$CFG->wwwroot}/pix/t/switch_plus.gif\" border=\"0\" title=\"".get_string('whosthere', 'scheduler')."\"></a> {$collegues}";
            }
            $canappoint = true;
            $canusegroup = ($aslot->appointed) ? 0 : 1;
            $radio = "<input type=\"radio\" name=\"slotid\" value=\"{$aslot->id}\" onclick=\"checkGroupAppointment($canusegroup)\" />\n";
            $teacher = $DB->get_record('user', array('id' => $aslot->teacherid));
            $table->data[] = array ($startdatestr, $starttimestr, $endtimestr, $radio, "<a href=\"../../user/view.php?id={$aslot->teacherid}&amp;course={$scheduler->course}\">".fullname($teacher).'</a>', $aslot->groupsession);
        }
        $previoustime = $starttime;
        $previousendtime = $endtime;
        $previousdate = $startdate;
    }

    // print slot table

    if (count($table->data)) {
        ?>
        <center>
        <form name="appoint" action="view.php" method="get">
        <input type="hidden" name="what" value="savechoice" />
        <input type="hidden" name="id" value="<?php p($cm->id) ?>" />
        <script type="text/javascript">
        function checkGroupAppointment(enable){
            var numgroups = '<?php p(count($mygroups)) ?>';
            if (!enable){
                if (numgroups > 1){ // we have a select. we must force "appointsolo".
                    document.forms['appoint'].elements['appointgroup'].options[0].selected = true;
                }
            }
            document.forms['appoint'].elements['appointgroup'].disabled = !enable;
        }
        </script>
<?php
echo html_writer::table($table);

// add some global script

?>
                     <script type="text/javascript">
                        function toggleVisibility(id){
                            obj = document.getElementById('collegues' + id);
                            if (obj.style.visibility == "hidden"){
                                obj.style.visibility = "visible";
                                obj.style.display = "block";
                                document.images["group"+id].src='<?php echo $CFG->wwwroot."/pix/t/switch_minus.gif" ?>';
                            } else {
                                obj.style.visibility = "hidden";
                                obj.style.display = "none";
                                document.images["group"+id].src='<?php echo $CFG->wwwroot."/pix/t/switch_plus.gif" ?>';
                            }
                        }
                     </script>
<?php

    if ($canappoint) {
        /*
         Should add a note from the teacher to the student.
         TODO : addfield into appointments
         echo $OUTPUT->heading(get_string('savechoice', 'scheduler'), 3);
         echo '<table><tr><td valign="top" align="right"><b>';
         print_string('studentnotes', 'scheduler');
         echo ' :</b></td><td valign="top" align="left"><textarea name="notes" cols="60" rows="20"></textarea></td></tr></table>';
         */
        echo '<br /><input type="submit" value="'.get_string('savechoice', 'scheduler').'" /> ';
        if (scheduler_group_scheduling_enabled($course, $cm)) {
            if (count($mygroups) == 1) {
                $groups = array_values($mygroups);
                echo ' <input type="checkbox" name="appointgroup" value="'.$groups[0]->id.'" /> '.get_string('appointformygroup', 'scheduler').': '.$groups[0]->name;
                echo $OUTPUT->help_icon('appointagroup', 'scheduler');
            }
            if (count($mygroups) > 1) {
                print_string('appointfor', 'scheduler');
                foreach ($mygroups as $group) {
                    $groupchoice[0] = get_string('appointsolo', 'scheduler');
                    $groupchoice[$group->id] = $group->name;
                }
                echo html_writer::select($groupchoice, 'appointgroup', '', '');
                echo $OUTPUT->help_icon('appointagroup', 'scheduler');
            }
        }
    }

    echo '</form>';

    if ($haveunattendedappointments and has_capability('mod/scheduler:disengage', $context)) {
        echo "<br/><a href=\"view.php?id={$cm->id}&amp;what=disengage\">".get_string('disengage', 'scheduler').'</a>';
    }

    echo '</center>';

    } else {
        if ($minhidedate > time()) {
            $noslots = get_string('noslotsopennow', 'scheduler') .'<br/><br/>';
            $noslots .= get_string('firstslotavailable', 'scheduler') . '<span style="color:#C00000"><b>'.userdate($minhidedate).'</b></span>';
        } else {
            $noslots = get_string('noslotsavailable', 'scheduler') .'<br/><br/>';
        }
        $OUTPUT->box($noslots, 'center', '70%');
    }

} else {
    notify(get_string('noslots', 'scheduler'));
}
