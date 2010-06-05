<?php

//UT
require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
require_once(dirname(dirname(dirname(__FILE__))).'/lib.php');
require_once(dirname(__FILE__).'/assignment.class.php');

$id     = required_param('id', PARAM_INT);      // Course Module ID
$userid = required_param('userid', PARAM_INT);  // User ID
$offset = optional_param('offset', 0, PARAM_INT);
$mode   = optional_param('mode', '', PARAM_ALPHA);

$url = new moodle_url('/mod/assignment/type/uploadpdf/notes.php', array('id' => $id, 'userid'=>$userid) );
if ($offset) { $url->param('offset', $offset); }
if ($mode) { $url->param('mode', $mode); }
if (! $cm = get_coursemodule_from_id('assignment', $id)) {
    error("Course Module ID was incorrect");
}

if (! $assignment = $DB->get_record('assignment', array('id' => $cm->instance) )) {
    error("Assignment ID was incorrect");
}

if (! $course = $DB->get_record('course', array('id' => $assignment->course) )) {
    error("Course is misconfigured");
}

if (! $user = $DB->get_record('user', array('id' => $userid) )) {
    error("User is misconfigured");
}

$PAGE->set_url($url);
require_login($course->id, false, $cm);

if (!has_capability('mod/assignment:grade', get_context_instance(CONTEXT_MODULE, $cm->id))) {
    error("You can not view this assignment");
}

if ($assignment->assignmenttype != 'uploadpdf') {
    error("Incorrect assignment type");
}

$assignmentinstance = new assignment_uploadpdf($cm->id, $assignment, $cm, $course);

$returnurl = "../../submissions.php?id={$assignmentinstance->cm->id}&amp;userid=$userid&amp;offset=$offset&amp;mode=single";

if ($submission = $assignmentinstance->get_submission($user->id)
    and !empty($submission->data1)) {
    //UT
    print_header(fullname($user,true).': '.$assignment->name);
    print_heading(get_string('notes', 'assignment').' - '.fullname($user,true));
    print_simple_box(format_text($submission->data1, FORMAT_HTML), 'center', '100%');
    if ($mode != 'single') {
        close_window_button();
    } else {
        print_continue($returnurl);
    }
    print_footer('none');
} else {
    //UT
    print_header(fullname($user,true).': '.$assignment->name);
    print_heading(get_string('notes', 'assignment').' - '.fullname($user,true));
    print_simple_box(get_string('notesempty', 'assignment'), 'center', '100%');
    if ($mode != 'single') {
        close_window_button();
    } else {
        print_continue($returnurl);
    }
    print_footer('none');
}

?>
