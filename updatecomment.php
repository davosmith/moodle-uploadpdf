<?php

require_once('../../../../config.php');

define('ASSIGNMENT_UPLOADPDF_ERR_NONE', 0);
define('ASSIGNMENT_UPLOADPDF_ERR_INVALID_ACTION', 1);
define('ASSIGNMENT_UPLOADPDF_ERR_NO_LOGIN', 2);
define('ASSIGNMENT_UPLOADPDF_ERR_', 3);
define('ASSIGNMENT_UPLOADPDF_ERR_BAD_PAGE_NO', 4);
define('ASSIGNMENT_UPLOADPDF_ERR_INVALID_COMMENT_DATA', 5);
define('ASSIGNMENT_UPLOADPDF_ERR_DATABASE_UPDATE', 6);
define('ASSIGNMENT_UPLOADPDF_ERR_DATABASE_DELETE', 7);
define('ASSIGNMENT_UPLOADPDF_ERR_GENERIC', 200);

function send_error($msg, $id = ASSIGNMENT_UPLOADPDF_ERR_GENERIC) {
    $resp = array('error'=>$id, 'errmsg'=>$msg);
    echo json_encode($resp);
    die;
}

$id = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$pageno = required_param('pageno', PARAM_INT);

if ($id) {
    if (! $cm = get_coursemodule_from_id('assignment', $id)) {
        send_error("Course Module ID was incorrect");
    }

    if (! $assignment = get_record("assignment", "id", $cm->instance)) {
        send_error("assignment ID was incorrect");
    }

    if (! $course = get_record("course", "id", $assignment->course)) {
        send_error("Course is misconfigured");
    }
} else {
    if (!$assignment = get_record("assignment", "id", $a)) {
        send_error("Course module is incorrect");
    }
    if (! $course = get_record("course", "id", $assignment->course)) {
        send_error("Course is misconfigured");
    }
    if (! $cm = get_coursemodule_from_instance("assignment", $assignment->id, $course->id)) {
        send_error("Course Module ID was incorrect");
    }
}

require_login($course->id, false, $cm);
require_capability('mod/assignment:grade', get_context_instance(CONTEXT_MODULE, $cm->id));
if (!confirm_sesskey()) {
    send_error('You must be logged in to do this', ASSIGNMENT_UPLOADPDF_ERR_NO_LOGIN);
}

require_once('assignment.class.php');
$assignmentinstance = new assignment_uploadpdf($cm->id, $assignment, $cm, $course);
$assignmentinstance->update_comment_page($userid, $pageno);

?>