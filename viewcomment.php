<?php

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
require_once(dirname(__FILE__).'/mypdflib.php');

$id   = optional_param('id', 0, PARAM_INT);          // Course module ID
$a    = optional_param('a', 0, PARAM_INT);           // Assignment ID
$pageno = optional_param('pageno', 1, PARAM_INT);

$url = new moodle_url('/mod/assignment/type/uploadpdf/viewcomment.php', array('pageno'=>$pageno) );
if ($id) {
    if (! $cm = get_coursemodule_from_id('assignment', $id)) {
        error("Course Module ID was incorrect");
    }

    if (! $assignment = $DB->get_record('assignment', array('id' => $cm->instance) )) {
        error("assignment ID was incorrect");
    }

    if (! $course = $DB->get_record('course', array('id' => $assignment->course) )) {
        error("Course is misconfigured");
    }
    $url->param('id', $id);

} else {
    if (!$assignment = $DB->get_record('assignment', array('id' => $a) )) {
        error("Course module is incorrect");
    }
    if (! $course = $DB->get_record("course", array('id' => $assignment->course) )) {
        error("Course is misconfigured");
    }
    if (! $cm = get_coursemodule_from_instance("assignment", $assignment->id, $course->id)) {
        error("Course Module ID was incorrect");
    }
    $url->param('a', $a);
}

$PAGE->set_url($url);
require_login($course->id, false, $cm);

require(dirname(__FILE__).'/assignment.class.php');
$assignmentinstance = new assignment_uploadpdf($cm->id, $assignment, $cm, $course);

$assignmentinstance->edit_comment_page($USER->id, $pageno, false);

?>
