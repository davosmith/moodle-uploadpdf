<?php

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
require_once(dirname(__FILE__).'/edittemplates_class.php');

$courseid   = required_param('courseid', PARAM_INT);          // Course ID
$templateid = optional_param('templateid', 0, PARAM_INT);
$itemid = optional_param('itemid', 0, PARAM_INT);
$imagetime = optional_param('imagetime', false, PARAM_FILE); // Time when the preview image was uploaded

if (! $course = $DB->get_record("course", array('id'=>$courseid) )) {
    error("Course is misconfigured");
}

require_login($course->id, false);

require_capability('moodle/course:manageactivities', get_context_instance(CONTEXT_COURSE, $course->id));

$edittmpl = new edit_templates($course->id, $templateid, $imagetime, $itemid);
$edittmpl->view();

?>