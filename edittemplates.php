<?php

require_once("../../../../config.php");
require_once("mypdflib.php");

$courseid   = required_param('courseid', PARAM_INT);          // Course module ID
$templateid = optional_param('templateid', 0, PARAM_INT);
$itemid = optional_param('itemid', 0, PARAM_INT);
$savetemplate = optional_param('savetemplate', false, PARAM_TEXT);
$deletetemplate = optional_param('deletetemplate', false, PARAM_TEXT);

if (! $course = get_record("course", "id", $courseid)) {
    error("Course is misconfigured");
}

require_login($course->id, false);

require_capability('mod/assignment:grade', get_context_instance(CONTEXT_COURSE, $course->id));

if ($savetemplate) {
    if ($templateid != 0) {
        if ($templateid == -1) {
            $template = new Object;
        } else {
            $template = get_record('assignment_uploadpdf_template', 'id', $templateid);
            if (!$template) {
                error("Template not found");
            }
        }
        $template->name = required_param('templatename', PARAM_TEXT);
        if (optional_param('sitetemplate', false, PARAM_BOOL)) {
            $template->course = 0;
        } else {
            $template->course = $courseid;
        }

        if ($templateid == -1) {
            $templateid = insert_record('assignment_uploadpdf_template', $template);
        } else {
            update_record('assignment_uploadpdf_template', $template);
        }
    }
} elseif ($deletetemplate) {
    $uses = count_records('assignment_uploadpdf','template', $templateid);
    if ($uses == 0) {
        delete_records('assignment_uploadpdf_template_items','template',$templateid);
        delete_records('assignment_uploadpdf_template','id', $templateid);
        $templateid = 0;
    }
}

print_header('Edit Templates', $course->fullname);

$hidden = '<input type="hidden" name="courseid" value="'.$courseid.'" />';
show_select_template($course->id, $hidden, $templateid);

if ($templateid != 0) {
    $hidden .= '<input type="hidden" name="templateid" value="'.$templateid.'" />';
    show_template_edit_form($templateid, $itemid, $hidden);
}

if ($itemid != 0) {
    $hidden .= '<input type="hidden" name="itemid" value="'.$itemid.'" />';
    show_item_form($itemid, $hidden);
}

print_footer($course);

// Form to:
// Give template a name
// For each item in template (type, x, y, width (only text), setting
// Upload a PDF to act as the background whilst editing the template
// (Put this file in $CFG->dataroot/$courseid/moddate/assignment/template


function show_select_template($courseid, $hidden, $templateid = 0) {
    global $CFG;
    
    echo '<form enctype="multipart/form-data" method="post" action="edittemplates.php">';
    echo '<fieldset>';
    echo $hidden;
    echo '<label for="templateid">'.get_string('choosetemplate','assignment_uploadpdf').': </label>';
    echo '<select name="templateid">';
    if ($templateid == -1) {
        echo '<option value="-1" selected="selected">'.get_string('newtemplate','assignment_uploadpdf').'</option>';
    } else {
        echo '<option value="-1">'.get_string('newtemplate','assignment_uploadpdf').'</option>';
    }
    $templates_data = get_records_sql("SELECT id, name FROM {$CFG->prefix}assignment_uploadpdf_template WHERE course = 0 OR course = {$courseid}");
    if ($templates_data) {
        foreach ($templates_data as $td) {
            $selected = '';
            if ($td->id == $templateid) {
                $selected = ' selected="selected" ';
            }
            echo '<option value="'.$td->id.'"'.$selected.'>'.s($td->name).'</option>';
        }
    }
    echo '</select>';
    echo '<input type="submit" name="selecttemplate" value="'.get_string('select','assignment_uploadpdf').'" />';
    echo '</fieldset>';
    echo '</form>';
}

function show_template_edit_form($templateid, $itemid, $hidden) {
    echo '<form enctype="multipart/form-data" method="post" action="edittemplates.php">';
    echo '<fieldset>';
    $uses = count_records('assignment_uploadpdf','template', $templateid);
    if ($uses) {
        echo '<p>'.get_string('templateusecount','assignment_uploadpdf', $uses).'</p>';
    } else {
        echo '<p>'.get_string('templatenotused', 'assignment_uploadpdf').'</p>';
    }
    echo $hidden;
    $templatename = '';
    $sitetemplate = ' checked="checked" ';
    $template = get_record('assignment_uploadpdf_template','id', $templateid);
    if ($template) {
        $templatename = $template->name;
        $sitetemplate = ($template->course == 0) ? ' checked="checked" ' : '';
    }
    echo '<label for="templatename">'.get_string('templatename', 'assignment_uploadpdf').': </label>';
    echo '<input type="text" name="templatename" value="'.$templatename.'" /><br />';
    echo '<input type="checkbox" name="sitetemplate"'.$sitetemplate.' >'.get_string('sitetemplate', 'assignment_uploadpdf').' </input>';
    echo get_string('sitetemplatehelp','assignment_uploadpdf');
    echo '<br /><br />';
    echo '<input type="submit" name="savetemplate" value="'.get_string('savetemplate','assignment_uploadpdf').'" />';
    if (($templateid > 0) && ($uses == 0)) {
        $deletedisabled = '';
    } else {
        $deletedisabled = ' disabled="disabled" ';
    }
    echo '<input type="submit" name="deletetemplate" value="'.get_string('deletetemplate','assignment_uploadpdf').'"'.$deletedisabled.'/>';
    echo '<br />';
    if ($templateid > 0) {
        echo '<br />';
        echo '<label for="itemid">'.get_string('chooseitem','assignment_uploadpdf').': </label>';
        echo '<select name="itemid">';
        if ($itemid == -1) {
            echo '<option value="-1" selected="selected">'.get_string('newitem','assignment_uploadpdf').'</option>';
        } else {
            echo '<option value="-1">'.get_string('newitem','assignment_uploadpdf').'</option>';
        }
        $items_data = get_records('assignment_uploadpdf_template_item', 'template', $templateid);
        if ($items_data) {
            $datenum = 1;
            foreach ($items_data as $item) {
                $selected = '';
                if ($item->id == $itemid) {
                    $selected = ' selected="selected" ';
                }
                $itemtext = 'Unknown';
                if ($item->type == 'date') {
                    $itemtext = get_string('itemdate', 'assignment_uploadpdf').$datenum;
                    $datenum++;
                } elseif (($item->type == 'text') || ($item->type == 'shorttext')) {
                    $itemtext = $item->setting;
                    if (strlen($itemtext) > 22) {
                        $itemtext = substr($itemtext, 0, 20).'...';
                    }
                }
                
                echo '<option value="'.$item->id.'"'.$selected.'>'.s($itemtext).'</option>';
            }
        }
        echo '<input type="submit" name="selectitem" value="'.get_string('select','assignment_uploadpdf').'" />';
    }
    echo '</fieldset>';
    echo '</form>';
}

function show_item_form($itemid, $hidden) {
    
}

?>