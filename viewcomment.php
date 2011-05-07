<?php

// This file is part of the UploadPDF assignment type for Moodle
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

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
require_once("mypdflib.php");

$id   = optional_param('id', 0, PARAM_INT);          // Course module ID
$a    = optional_param('a', 0, PARAM_INT);           // Assignment ID
$pageno = optional_param('pageno', 1, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

if ($id) {
    if (! $cm = get_coursemodule_from_id('assignment', $id)) {
        error("Course Module ID was incorrect");
    }

    if (! $assignment = get_record("assignment", "id", $cm->instance)) {
        error("assignment ID was incorrect");
    }

    if (! $course = get_record("course", "id", $assignment->course)) {
        error("Course is misconfigured");
    }
} else {
    if (!$assignment = get_record("assignment", "id", $a)) {
        error("Course module is incorrect");
    }
    if (! $course = get_record("course", "id", $assignment->course)) {
        error("Course is misconfigured");
    }
    if (! $cm = get_coursemodule_from_instance("assignment", $assignment->id, $course->id)) {
        error("Course Module ID was incorrect");
    }
}

require_login($course->id, false, $cm);

if ($userid && $userid != $USER->id) {
    require_capability('mod/assignment:grade', get_context_instance(CONTEXT_MODULE, $cm->id));
} else {
    require_capability('mod/assignment:submit', get_context_instance(CONTEXT_MODULE, $cm->id));
    $userid = $USER->id;
}

require_once(dirname(__FILE__).'/assignment.class.php');
$assignmentinstance = new assignment_uploadpdf($cm->id, $assignment, $cm, $course);

// false => able to view, but not edit
$assignmentinstance->edit_comment_page($userid, $pageno, false);
