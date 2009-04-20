<?php

require_once("../../../../config.php");
require_once("mypdflib.php");

$courseid   = optional_param('courseid', 0, PARAM_INT);          // Course module ID
$templateid = optional_param('templateid', 0, PARAM_INT);

if ($courseid) {
    if (! $course = get_record("course", "id", $assignment->course)) {
        error("Course is misconfigured");
    }
}

require_login($course->id, false, $cm);

require_capability('mod/assignment:grade', get_context_instance(CONTEXT_COURSE, $course->id));

if ($templateid == 0) {
    print_header('Edit Templates', $course->fullname);
    
    echo '<form enctype="multipart/form-data" method="post" action="edittemplates.php">';
    echo '<fieldset>';
    echo '<label for="templateid">'.get_string('choosetemplate','assignment_uploadpdf').': </label>';
    echo '<select name="templateid">';
    $templates_data = get_records_sql("SELECT id, name FROM {$CFG->prefix}assignment_uploadpdf_template WHERE course = 0 OR course = {$courseid}");
    if ($templates_data) {
        foreach ($templates_data as $td) {
            echo '<option value="'.$td->id.'">'.s($td->name).'</option>';
        }
    }
    echo '<option value="-1">'.get_string('newtemplate','assignment_uploadpdf').'</option>';
    echo '</select>';
    echo '</fieldset>';
    echo '</form>';

    print_footer($course->id);
    die;
} 

if ($templateid > 0) {
    
    
} else {
}

show_item_form(0);

// Form to:
// Give template a name
// For each item in template (type, x, y, width (only text), setting
// Upload a PDF to act as the background whilst editing the template
// (Put this file in $CFG->dataroot/$courseid/moddate/assignment/template

function show_item_form($itemnumber, $data=null) {
    echo '<p>It worked!</p>';
}



?>