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
 * E-mail formatting from templates.
 *
 * @package    mod
 * @subpackage scheduler
 * @copyright  2011 Henning Bostelmann and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Gets the content of an e-mail from language strings
 *
 * Looks for the language string email_$template_$format and replaces the parameter values.
 *
 * @param template the template's identified
 * @param string $format tthe mail format ('subject', 'html' or 'plain')
 * @param infomap a hash containing pairs of parm => data to replace in template
 * @return a fully resolved template where all data has been injected
 */
function compile_mail_template($template, $format, $infomap, $module = 'scheduler') {
    $params = array();
    foreach ($infomap as $key => $value) {
        $params[strtolower($key)] = $value;
    }
    $mailstr = get_string( "email_{$template}_{$format}", $module, $params);
    return $mailstr;
}

/**
 * Sends an e-mail based on a template.
 * Several template substitution values are automatically filled by this routine.
 *
 * @uses $CFG
 * @uses $SITE
 * @param user $recipient A {@link $USER} object describing the recipient
 * @param user $sender A {@link $USER} object describing the sender
 * @param object $course The course that the activity is in. Can be null.
 * @param string $title the identifier for the e-mail subject.
 *        Value can include one parameter, which will be substituted
 *        with the course shortname.
 * @param string $template the virtual mail template name (without "_html" part)
 * @param array $infomap a hash containing pairs of parm => data to replace in template
 * @param string $modulename the current module
 * @param string $lang language to be used, if default language must be overriden
 * @return bool|string Returns "true" if mail was sent OK, "emailstop" if email
 *         was blocked by user and "false" if there was another sort of error.
 */
function send_email_from_template($recipient, $sender, $course, $title, $template, $infomap, $modulename, $lang = '') {

    global $CFG;
    global $SITE;

    $defaultvars = array(
        'SITE' => $SITE->shortname,
        'SITE_URL' => $CFG->wwwroot,
        'SENDER'  => fullname($sender),
        'RECIPIENT'  => fullname($recipient)
    );

    $subjectprefix = $SITE->shortname;

    if ($course) {
        $subjectprefix = $course->shortname;
        $defaultvars['COURSE_SHORT'] = $course->shortname;
        $defaultvars['COURSE']       = $course->fullname;
        $defaultvars['COURSE_URL']   = $CFG->wwwroot.'/course/view.php?id='.$course->id;
    }

    $vars = array_merge($defaultvars, $infomap);

    $subject = compile_mail_template($template, 'subject', $vars, $modulename);
    $plainmail = compile_mail_template($template, 'plain', $vars, $modulename);
    $htmlmail = compile_mail_template($template, 'html', $vars, $modulename);

    $res = email_to_user ($recipient, $sender, $subject, $plainmail, $htmlmail);
    return $res;
}
