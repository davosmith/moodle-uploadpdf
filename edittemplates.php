<?php

require_once("../../../../config.php");
require_once("mypdflib.php");

$courseid   = required_param('courseid', PARAM_INT);          // Course module ID
$templateid = optional_param('templateid', 0, PARAM_INT);
$itemid = optional_param('itemid', 0, PARAM_INT);
$savetemplate = optional_param('savetemplate', false, PARAM_TEXT);
$deletetemplate = optional_param('deletetemplate', false, PARAM_TEXT);
$saveitem = optional_param('saveitem', false, PARAM_TEXT);
$deleteitem = optional_param('deleteitem', false, PARAM_TEXT);
$imagename = optional_param('imagename', false, PARAM_FILE);
$uploadpreview = optional_param('uploadpreview', false, PARAM_TEXT);

define('IMAGE_PATH','/moddata/assignment/template');

if (! $course = get_record("course", "id", $courseid)) {
    error("Course is misconfigured");
}

require_login($course->id, false);

require_capability('moodle/course:manageactivities', get_context_instance(CONTEXT_COURSE, $course->id));
$caneditsite = has_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));
$extrajs = '';

if ($savetemplate) {
    if ($templateid != 0) {

        if ($templateid == -1) {
            $template = new Object;
        } else {
            $template = get_record('assignment_uploadpdf_template', 'id', $templateid);
            if (!$template) {
                error("Template not found");
            }
            if (($template->course == 0) && (!$caneditsite)) {
                error("No permission to edit site templates");
            }
        }
        $template->name = required_param('templatename', PARAM_TEXT);

        if (optional_param('sitetemplate', false, PARAM_BOOL) && $caneditsite) {
            $template->course = 0;
        } else {
            $template->course = $courseid;
        }

        if ($templateid == -1) {
            $templateid = insert_record('assignment_uploadpdf_template', $template);
            $extrajs = '<script type="text/javascript">';
            $extrajs .= 'var el=window.opener.document.getElementById("id_template");';
            $extrajs .= 'if (el) {';
            $extrajs .= 'var newtemp = window.opener.document.createElement("option"); newtemp.value = "'.$templateid.'"; newtemp.innerHTML = "'.s($template->name).'";';
            $extrajs .= 'el.appendChild(newtemp); ';
            $extrajs .= '}';
            $extrajs .= '</script>';
            $itemid = -1;
        } else {
            update_record('assignment_uploadpdf_template', $template);
        }
    }
} elseif ($deletetemplate) {
    if ($templateid) {
        $uses = count_records('assignment_uploadpdf','template', $templateid);
        if ($uses == 0) {
            $template = get_record('assignment_uploadpdf_template','id',$templateid);
            if ($template && $template->course == 0 && !$caneditsite) {
                error("No permission to edit site templates");
            }
            delete_records('assignment_uploadpdf_template_item','template',$templateid);
            delete_records('assignment_uploadpdf_template','id', $templateid);

            $extrajs = '<script type="text/javascript">';
            $extrajs .= 'var el=window.opener.document.getElementById("id_template");';
            $extrajs .= 'if (el) {';
            $extrajs .= 'var opts = el.getElementsByTagName("option"); var i=0;';
            $extrajs .= 'for (i=0; i<opts.length; i++) {';
            $extrajs .= 'if (opts[i].value == "'.$templateid.'") {';
            $extrajs .= 'el.removeChild(opts[i]);';
            $extrajs .= '}}}';
            $extrajs .= '</script>';

            $templateid = 0;
        }
    }
} elseif ($saveitem) {
    if (($templateid != 0) && ($itemid != 0)) {
        if ($itemid == -1) {
            $item = new Object;
        } else {
            $item = get_record('assignment_uploadpdf_template_item', 'id', $itemid);
            if (!$item) {
                error("Item not found");
            }
            $template = get_record('assignment_uploadpdf_template', 'id', $item->template);
            if (!$template) {
                error("Template not found");
            }
            if (($template->course == 0) && (!$caneditsite)) {
                error("No permission to edit site templates");
            }
        }
        $item->type = required_param('itemtype', PARAM_TEXT);
        $item->xpos = required_param('itemx', PARAM_INT);
        $item->ypos = required_param('itemy', PARAM_INT);
        $item->width = required_param('itemwidth', PARAM_INT);
        $item->setting = required_param('itemsetting', PARAM_TEXT);
        $item->template = $templateid;

        if ($itemid == -1) {
            $itemid = insert_record('assignment_uploadpdf_template_item', $item);
        } else {
            update_record('assignment_uploadpdf_template_item', $item);
        }
    }

} elseif ($deleteitem) {
    if ($itemid) {
        $item = get_record('assignment_uploadpdf_template_item', 'id', $itemid);
        if ($item) {
            $template = get_record('assignment_uploadpdf_template', 'id', $item->template);
            if ($template && $template->course == 0 && !$caneditsite) {
                error("No permission to edit site templates");
            }
            delete_records('assignment_uploadpdf_template_item', 'id', $itemid);
            $itemid = 0;
        }
    }

} elseif ($uploadpreview) {

    $partdest = $courseid.IMAGE_PATH;
    $fulldest = $CFG->dataroot.'/'.$partdest;
    check_dir_exists($fulldest);
    require_once($CFG->dirroot.'/lib/uploadlib.php');
    $um = new upload_manager('preview',false,false,$course,false,0,true);

    if ($um->process_file_uploads($partdest)) {
        $fp = $um->get_new_filepath();
        $fn = $um->get_new_filename();

        require_once('mypdflib.php');
        $pdf = new MyPDFLib();
        $pdf->load_pdf($fp);
        $pdf->set_image_folder($fulldest);
        $imagename = $pdf->get_image(1);
        unlink($fp);
    } else {
        echo 'Bad thing happen';
        die;
    }
}

print_header('Edit Templates', $course->fullname);

echo $extrajs;
$hidden = '<input type="hidden" name="courseid" value="'.$courseid.'" />';
if ($imagename) {
    $hidden .= '<input type="hidden" name="imagename" value="'.$imagename.'" />';
}
show_select_template($course->id, $hidden, $templateid);

if ($templateid != 0) {
    $hidden .= '<input type="hidden" name="templateid" value="'.$templateid.'" />';
    show_template_edit_form($templateid, $itemid, $hidden, $caneditsite);
    
    if ($itemid != 0) {
        $hidden .= '<input type="hidden" name="itemid" value="'.$itemid.'" />';
        $canedit = false;
        if ($caneditsite) {
            $canedit = true;
        } else {
            $template = get_record('assignment_uploadpdf_template', 'id', $templateid);
            if ($template && $template->course > 0) {
                $canedit = true;
            }
        }

        show_item_form($itemid, $hidden, $canedit);
    }
}

show_image($imagename, $templateid, $courseid, $hidden, $itemid);

print_footer($course);

function show_select_template($courseid, $hidden, $templateid = 0) {
    global $CFG;
    
    echo '<form name="selecttemplate" enctype="multipart/form-data" method="post" action="edittemplates.php">';
    echo '<fieldset>';
    echo $hidden;
    echo '<label for="templateid">'.get_string('choosetemplate','assignment_uploadpdf').': </label>';
    echo '<select name="templateid" onchange="document.selecttemplate.submit();">';
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

function show_template_edit_form($templateid, $itemid, $hidden, $caneditsite) {
    global $CFG;

    echo '<form name="edittemplate" enctype="multipart/form-data" method="post" action="edittemplates.php">';
    echo '<fieldset>';
    $uses = count_records('assignment_uploadpdf','template', $templateid);
    if ($uses) {
        echo '<p>'.get_string('templateusecount','assignment_uploadpdf', $uses);
        echo ' <input type="submit" name="showwhereused" value="'.get_string('showwhereused','assignment_uploadpdf').'" />';
    } else {
        echo '<p>'.get_string('templatenotused', 'assignment_uploadpdf').'';
    }
    if (optional_param('showwhereused', false, PARAM_TEXT)) {
        echo '<br/>';
        echo get_string('showused', 'assignment_uploadpdf').':<br />';
        echo '<ul>';
        $usestemplate = get_records_sql("SELECT name FROM {$CFG->prefix}assignment_uploadpdf AS au, {$CFG->prefix}assignment AS a WHERE au.template='$templateid' AND a.id = au.assignment;");
        foreach ($usestemplate as $ut) {
            echo '<li>'.s($ut->name).'</li>';
        }
        echo '</ul>';
    }
    echo '</p>';
    echo $hidden;
    $templatename = '';
    $sitetemplate = '';
    $editdisabled = '';
    $template = get_record('assignment_uploadpdf_template','id', $templateid);
    if ($template) {
        $templatename = $template->name;
        if ($template->course == 0) {
            $sitetemplate = ' checked="checked" ';
            if (!$caneditsite) {
                $editdisabled = ' disabled="disabled" ';
                echo '<p>'.get_string('cannotedit', 'assignment_uploadpdf').'</p>';
            }
        }
    }
    if (!$caneditsite) {
        $sitetemplate .= ' disabled="disabled" ';
    } 
    echo '<label for="templatename">'.get_string('templatename', 'assignment_uploadpdf').': </label>';
    echo '<input type="text" name="templatename" value="'.$templatename.'"'.$editdisabled.' /><br />';
    echo '<input type="checkbox" name="sitetemplate"'.$sitetemplate.' >'.get_string('sitetemplate', 'assignment_uploadpdf').' </input>';
    echo get_string('sitetemplatehelp','assignment_uploadpdf');
    echo '<br /><br />';
    echo '<input type="submit" name="savetemplate" value="'.get_string('savetemplate','assignment_uploadpdf').'"'.$editdisabled.' />';
    if (($templateid > 0) && ($uses == 0)) {
        $deletedisabled = $editdisabled;
    } else {
        $deletedisabled = ' disabled="disabled" ';
    }
    echo '<input type="submit" name="deletetemplate" value="'.get_string('deletetemplate','assignment_uploadpdf').'"'.$deletedisabled.'/>';
    echo '<br />';
    if ($templateid > 0) {
        echo '<br />';
        echo '<label for="itemid">'.get_string('chooseitem','assignment_uploadpdf').': </label>';
        echo '<select name="itemid" onchange="document.edittemplate.submit();">';
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

function show_item_form($itemid, $hidden, $canedit) {
    $disabled = '';
    if (!$canedit) {
        $disabled = ' disabled="disabled" ';
    }
    $item = false;
    if ($itemid > 0) {
        $item = get_record('assignment_uploadpdf_template_item', 'id', $itemid);
    }
    $deletedisabled = '';
    if (!$item || !$canedit) {
        $deletedisabled = ' disabled="disabled" ';
    }
    if (!$item) {
        $item = new Object;
        $item->type = 'shorttext';
        $item->xpos = 0;
        $item->ypos = 0;
        $item->width = 0;
        $item->setting = '';
    }

    echo '<form enctype="multipart/form-data" method="post" action="edittemplates.php">';
    echo $hidden;
    echo '<fieldset>';

    echo '<table>';
    echo '<tr><td><label for="itemtype">'.get_string('itemtype','assignment_uploadpdf').': </label></td>';
    echo '<td><select name="itemtype"'.$disabled.'>';
    echo '<option value="shorttext"'.(($item->type == 'shorttext') ? ' selected="selected" ' : '').'>'.get_string('itemshorttext','assignment_uploadpdf').'</option>';
    echo '<option value="text"'.(($item->type == 'text') ? ' selected="selected" ' : '').'>'.get_string('itemtext','assignment_uploadpdf').'</option>';
    echo '<option value="date"'.(($item->type == 'date') ? ' selected="selected" ' : '').'>'.get_string('itemdate','assignment_uploadpdf').'</option>';
    echo '</select></td></tr>';

    echo '<tr><td><label for="itemx">'.get_string('itemx','assignment_uploadpdf').': </label></td>';
    echo '<td><input type="text" name="itemx" value="'.$item->xpos.'"'.$disabled.' /></td></tr>';
    echo '<tr><td><label for="itemy">'.get_string('itemy','assignment_uploadpdf').': </label></td>';
    echo '<td><input type="text" name="itemy" value="'.$item->ypos.'"'.$disabled.' /></td></tr>';
    echo '<tr><td><label for="itemwidth">'.get_string('itemwidth','assignment_uploadpdf').': </label></td>';
    echo '<td><input type="text" name="itemwidth" value="'.$item->width.'"'.$disabled.' /></td></tr>';
    echo '<tr><td><label for="itemsetting">'.get_string('itemsetting','assignment_uploadpdf').': </label></td>';
    echo '<td><input type="text" name="itemsetting" value="'.$item->setting.'"'.$disabled.' /> '.get_string('itemsettingmore','assignment_uploadpdf');
    echo ' <a href="http://www.php.net/date" target="_blank">'.get_string('dateformatlink','assignment_uploadpdf').'</td></tr>';
    echo '</table>';
    echo '<input type="submit" name="saveitem" value="'.get_string('saveitem','assignment_uploadpdf').'"'.$disabled.'/>';
    echo '<input type="submit" name="deleteitem" value="'.get_string('deleteitem','assignment_uploadpdf').'"'.$deletedisabled.'/>';
    echo '</fieldset>';
    echo '</form>';
}

function show_image($imagename, $templateid, $courseid, $hidden, $itemid) {
    global $CFG;

    if ($imagename) {
        $partpath = '/'.$courseid.IMAGE_PATH.'/'.$imagename;
        $fullpath = $CFG->dataroot.$partpath;
        if (file_exists($fullpath)) {
            list($width, $height, $type, $attr) = getimagesize($fullpath);
            echo "<div style='width: {$width}px; height: {$height}px; border: solid 1px black;'>";
            echo '<div style="position: relative;">';
            $imageurl = $CFG->wwwroot.'/file.php?file='.$partpath;
            echo '<img src="'.$imageurl.'" alt="Preview Template" style="position: absolute; top: 0px; left: 0px;" />';
            if ($templateid > 0) {
                $templateitems = get_records('assignment_uploadpdf_template_item','template',$templateid);
                if ($templateitems) {
                    foreach ($templateitems as $ti) {
                        $tiwidth = '';
                        if ($ti->type == 'text') {
                            $tiwidth = ' width: '.$ti->width.'px; ';
                        }
                        $border = '';
                        if ($itemid == $ti->id) {
                            $border = ' border: dashed 1px red; ';
                        }
                        echo '<div style="position: absolute;'.$border.' font-family: helvetica, arial, sans; font-size: 12px; ';
                        echo 'top: '.$ti->ypos.'px; left: '.$ti->xpos.'px; '.$tiwidth;
                        echo '">';
                        if (($ti->type == 'text') || ($ti->type == 'shorttext')) {
                            echo s($ti->setting);
                        } elseif ($ti->type == 'date') {
                            echo date($ti->setting);
                        }
                        echo '</div>';
                    }
                }
            }
            echo '</div>';
            echo '&nbsp;';
            echo '</div>';
            return;
        }
    }

    echo '<form enctype="multipart/form-data" method="post" action="edittemplates.php">';
    echo '<fieldset>';
    echo $hidden;
    echo '<p>'.get_string('previewinstructions','assignment_uploadpdf').'</p>';
    require_once($CFG->libdir.'/uploadlib.php');
    upload_print_form_fragment(1,array('preview'),null,false,null,0,0,false);
    echo '<input type="submit" name="uploadpreview" value="'.get_string('uploadpreview','assignment_uploadpdf').'" />';
    echo '</fieldset>';
    echo '</form>';
}
?>