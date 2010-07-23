<?php

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
require_once(dirname(__FILE__).'/mypdflib.php');

require_once($CFG->libdir.'/formslib.php')

//UT

$courseid   = required_param('courseid', PARAM_INT);          // Course module ID
$templateid = optional_param('templateid', 0, PARAM_INT);
$itemid = optional_param('itemid', 0, PARAM_INT);
$savetemplate = optional_param('savetemplate', false, PARAM_TEXT);
$deletetemplate = optional_param('deletetemplate', false, PARAM_TEXT);
$duplicatetemplate = optional_param('duplicatetemplate', false, PARAM_TEXT);
$saveitem = optional_param('saveitem', false, PARAM_TEXT);
$deleteitem = optional_param('deleteitem', false, PARAM_TEXT);
$imagename = optional_param('imagename', false, PARAM_FILE);
$uploadpreview = optional_param('uploadpreview', false, PARAM_TEXT);

define('IMAGE_PATH','/moddata/assignment_template');

$thisurl = new moodle_url('/mod/assignment/type/uploadpdf/edittemplate.php', array('courseid'=>$courseid) );
if ($templateid) { $thisurl->param('templateid', $templateid); }
if ($itemid) { $thisurl->param('itemid', $itemid); }
if ($imagename) { $thisurl->param('imagename', $imagename); }

$PAGE->set_url($thisurl);
$PAGE->set_pagelayout('popup');

if (! $course = $DB->get_record("course", array('id'=>$courseid) )) {
    error("Course is misconfigured");
}

require_login($course->id, false);

require_capability('moodle/course:manageactivities', get_context_instance(CONTEXT_COURSE, $course->id));
$caneditsite = has_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));
$extrajs = '';

if ($savetemplate) {
    //UT
    if ($templateid != 0) {

        $oldname = null;
        if ($templateid == -1) {
            $template = new stdClass;
        } else {
            //UT
            $template = $DB->get_record('assignment_uploadpdf_tmpl', array('id' => $templateid) );
            if (!$template) {
                error("Template not found");
            }
            if (($template->course == 0) && (!$caneditsite)) {
                error("No permission to edit site templates");
            }
            $oldname = $template->name;
        }
        $template->name = required_param('templatename', PARAM_TEXT);

        if (optional_param('sitetemplate', false, PARAM_BOOL) && $caneditsite) {
            $template->course = 0;
        } else {
            $template->course = $courseid;
        }

        if ($templateid == -1) {
            //UT
            $templateid = $DB->insert_record('assignment_uploadpdf_tmpl', $template);
            $extrajs = '<script type="text/javascript">';
            $extrajs .= 'var el=window.opener.document.getElementById("id_template");';
            $extrajs .= 'if (el) {';
            $extrajs .= 'var newtemp = window.opener.document.createElement("option"); newtemp.value = "'.$templateid.'"; newtemp.innerHTML = "'.s($template->name).'";';
            $extrajs .= 'el.appendChild(newtemp); ';
            $extrajs .= '}';
            $extrajs .= '</script>';
            $itemid = -1;
        } else {
            //UT
            $DB->update_record('assignment_uploadpdf_tmpl', $template);
            if ($oldname != $template->name) {
                $extrajs = '<script type="text/javascript">';
                $extrajs .= 'var el=window.opener.document.getElementById("id_template");';
                $extrajs .= 'if (el) {';
                $extrajs .= 'var opts = el.getElementsByTagName("option"); var i=0;';
                $extrajs .= 'for (i=0; i<opts.length; i++) {';
                $extrajs .= 'if (opts[i].value == "'.$templateid.'") {';
                $extrajs .= 'opts[i].innerHTML = "'. s($template->name) .'";';
                $extrajs .= '}}}';
                $extrajs .= '</script>';
            }
        }
    }
} elseif ($deletetemplate) {
    //UT
    if ($templateid) {
        $uses = $DB->count_records('assignment_uploadpdf', array('template' => $templateid) );
        if ($uses == 0) {
            $template = $DB->get_record('assignment_uploadpdf_tmpl', array('id' => $templateid) );
            if ($template && $template->course == 0 && !$caneditsite) {
                error("No permission to edit site templates");
            }
            $DB->delete_records('assignment_uploadpdf_tmplitm', array('template' => $templateid) );
            $DB->delete_records('assignment_uploadpdf_tmpl', array('id' => $templateid) );

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
    //UT
    if (($templateid != 0) && ($itemid != 0)) {
        if ($itemid == -1) {
            $item = new stdClass;
        } else {
            $item = $DB->get_record('assignment_uploadpdf_tmplitm', array('id' => $itemid) );
            if (!$item) {
                error("Item not found");
            }
            $template = $DB->get_record('assignment_uploadpdf_tmpl', array('id' => $item->template) );
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
            $itemid = $DB->insert_record('assignment_uploadpdf_tmplitm', $item);
        } else {
            $DB->update_record('assignment_uploadpdf_tmplitm', $item);
        }
    }

} elseif ($deleteitem) {
    //UT
    
    if ($itemid) {
        $item = $DB->get_record('assignment_uploadpdf_tmplitm', array('id' => $itemid) );
        if ($item) {
            $template = $DB->get_record('assignment_uploadpdf_tmpl', array('id' => $item->template) );
            if ($template && $template->course == 0 && !$caneditsite) {
                error("No permission to edit site templates");
            }
            $DB->delete_records('assignment_uploadpdf_tmplitm', array('id' => $itemid) );
            $itemid = 0;
        }
    }

} elseif ($uploadpreview) {

    //UT

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
        //echo 'No file uploaded';
        //die;
    }
    
} elseif ($duplicatetemplate) {
    //UT
    
    // Should not have access to the 'duplicate' button unless a template is selected
    // but, just in case, we check here (but just do nothing if that is not the case)
    if ($templateid != -1) {
        $template = $DB->get_record('assignment_uploadpdf_tmpl', array('id' => $templateid) );
        if (!$template) {
            error("Old template not found");
        }
        $template->course = $courseid;
        $template->name = $template->name . get_string('templatecopy','assignment_uploadpdf');
        unset($template->id);
        $oldtemplateid = $templateid;
        $templateid = $DB->insert_record('assignment_uploadpdf_tmpl', $template);

        // Update the list on the main page
        $extrajs = '<script type="text/javascript">';
        $extrajs .= 'var el=window.opener.document.getElementById("id_template");';
        $extrajs .= 'if (el) {';
        $extrajs .= 'var newtemp = window.opener.document.createElement("option"); newtemp.value = "'.$templateid.'"; newtemp.innerHTML = "'.s($template->name).'";';
        $extrajs .= 'el.appendChild(newtemp); ';
        $extrajs .= '}';
        $extrajs .= '</script>';
        $itemid = -1;

        $items_data = $DB->get_records('assignment_uploadpdf_tmplitm', array('template' => $oldtemplateid) );
        foreach ($items_data as $item) {
            unset($item->id);
            $item->template = $templateid;
            $DB->insert_record('assignment_uploadpdf_tmplitm', $item);
        }
    }
}

echo $OUTPUT->header('Edit Templates', $course->fullname);

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
            $template = $DB->get_record('assignment_uploadpdf_tmpl', array('id' => $templateid) );
            if ($template && $template->course > 0) {
                $canedit = true;
            }
        }

        show_item_form($itemid, $hidden, $canedit);
    }
}

show_image($imagename, $templateid, $courseid, $hidden, $itemid);

echo $OUTPUT->footer($course);

function show_select_template($courseid, $hidden, $templateid = 0) {
    global $DB;
    
    //UT
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
    $templates_data = $DB->get_records_select('assignment_uploadpdf_tmpl', 'course = 0 OR course = ?', array($courseid) );
    foreach ($templates_data as $td) {
        $selected = '';
        if ($td->id == $templateid) {
            $selected = ' selected="selected" ';
        }
        echo '<option value="'.$td->id.'"'.$selected.'>'.s($td->name).'</option>';
    }
    echo '</select>';
    echo '<input type="submit" name="selecttemplate" value="'.get_string('select','assignment_uploadpdf').'" />';
    echo '</fieldset>';
    echo '</form>';
}

function show_template_edit_form($templateid, $itemid, $hidden, $caneditsite) {
    //UT
    global $DB;

    echo '<form name="edittemplate" enctype="multipart/form-data" method="post" action="edittemplates.php">';
    echo '<fieldset>';
    $uses = $DB->count_records('assignment_uploadpdf', array('template' => $templateid) );
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
        $usestemplate = $DB->get_records_sql('SELECT name FROM {assignment_uploadpdf} AS au, {assignment} AS a WHERE au.template= ? AND a.id = au.assignment;', array($templateid) );
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
    $template = $DB->get_record('assignment_uploadpdf_tmpl', array('id' => $templateid) );
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
    echo '<input type="submit" name="duplicatetemplate" value="'.get_string('duplicatetemplate','assignment_uploadpdf').'"/>';
    if ($templateid > 0) {
        echo '<br />';
        echo '<label for="itemid">'.get_string('chooseitem','assignment_uploadpdf').': </label>';
        echo '<select name="itemid" onchange="document.edittemplate.submit();">';
        if ($itemid == -1) {
            echo '<option value="-1" selected="selected">'.get_string('newitem','assignment_uploadpdf').'</option>';
        } else {
            echo '<option value="-1">'.get_string('newitem','assignment_uploadpdf').'</option>';
        }
        $items_data = $DB->get_records('assignment_uploadpdf_tmplitm', array('template' => $templateid) );
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
    //UT
    
    $disabled = '';
    if (!$canedit) {
        $disabled = ' disabled="disabled" ';
    }
    $item = false;
    if ($itemid > 0) {
        $item = $DB->get_record('assignment_uploadpdf_tmplitm', array('id' => $itemid) );
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
        $item->setting = get_string('enterformtext','assignment_uploadpdf');
    }

    // Javascript to allow more interactive editing of the items (by clicking on the image)
    echo '<script type="text/javascript">';
    echo 'var oldtype = "'.$item->type.'";';
    // Highlight the titles of items that are unsaved
    echo 'function Highlight(name) {';
    echo 'document.getElementById(name+"_label").style.fontWeight = "bold";';
    echo 'UpdatePreview();';
    echo '}';
    // Move the preview item on the page
    echo 'function UpdatePreview() {';
    echo 'currel = document.getElementById("current_template_item"); if (!currel) return;';
    echo 'currel.style.left = document.getElementById("itemx").value + "px";';
    echo 'currel.style.top = document.getElementById("itemy").value + "px";';
    echo 'type = document.getElementById("itemtype").value;';
    echo 'if ((type != "date") || (oldtype != "date"))';
    echo '{ currel.innerHTML = document.getElementById("itemsetting").value; }';
    echo 'if (type == "text") { currel.style.width = document.getElementById("itemwidth").value + "px"; }';
    echo 'else { currel.style.width = ""; }';
    echo '}';
    echo 'function Left(obj) { var curleft = 0; if (obj.offsetParent) while (1) { curleft += obj.offsetLeft; if (!obj.offsetParent) break; obj = obj.offsetParent; } else if (obj.x) curleft += obj.x; return curleft; }';
    echo 'function Top(obj) { var curtop = 0; if (obj.offsetParent) while (1) { curtop += obj.offsetTop; if (!obj.offsetParent) break; obj = obj.offsetParent; } else if (obj.y) curtop += obj.y; return curtop; }';
    // Update the 'item' position when the image is clicked on
    echo 'function clicked_on_image(e) {';
    echo 'var targ; if (!e) var e = e.target; if (e.target) { targ = e.target; } else if (e.srcElement) { targ = e.srcElement; }';
    echo 'pos_x = e.offsetX?(e.offsetX):e.pageX-Left(targ);';
	echo 'pos_y = e.offsetY?(e.offsetY):e.pageY-Top(targ);';
    echo 'document.getElementById("itemx").value = pos_x; document.getElementById("itemy").value = pos_y;';
    echo 'Highlight("itemx"); Highlight("itemy");';
    echo '}';
    // Fill in a default value when different types selected
    echo 'function type_changed() {';
    echo 'Highlight("itemtype");';
    echo 'var newtype = document.getElementById("itemtype").value; var settingel = document.getElementById("itemsetting");';
    echo 'if (newtype == "date") { settingel.value = "d/m/Y"; Highlight("itemsetting"); }';
    echo 'else if (oldtype == "date") { settingel.value = "'.get_string('enterformtext','assignment_uploadpdf').'"; Highlight("itemsetting"); } ';
    echo 'UpdatePreview();';
    echo 'oldtype = newtype;';
    echo '}';
    echo '</script>';

    echo '<form enctype="multipart/form-data" method="post" action="edittemplates.php">';
    echo $hidden;
    echo '<fieldset>';

    echo '<table>';
    echo '<tr><td width="150pt"><label id="itemtype_label" for="itemtype">'.get_string('itemtype','assignment_uploadpdf').': </label></td>';
    echo '<td><select id="itemtype" onchange = "type_changed();" name="itemtype"'.$disabled.'>';
    echo '<option value="shorttext"'.(($item->type == 'shorttext') ? ' selected="selected" ' : '').'>'.get_string('itemshorttext','assignment_uploadpdf').'</option>';
    echo '<option value="text"'.(($item->type == 'text') ? ' selected="selected" ' : '').'>'.get_string('itemtext','assignment_uploadpdf').'</option>';
    echo '<option value="date"'.(($item->type == 'date') ? ' selected="selected" ' : '').'>'.get_string('itemdate','assignment_uploadpdf').'</option>';
    echo '</select></td></tr>';

    echo '<tr><td><label for="itemx" id="itemx_label">'.get_string('itemx','assignment_uploadpdf').': </label></td>';
    echo '<td><input type="text" id="itemx" name="itemx" onChange="Highlight(\'itemx\');" value="'.$item->xpos.'"'.$disabled.' /> '.get_string('clicktosetposition','assignment_uploadpdf').'</td></tr>';
    echo '<tr><td><label for="itemy" id="itemy_label">'.get_string('itemy','assignment_uploadpdf').': </label></td>';
    echo '<td><input type="text" id="itemy" name="itemy" onChange="Highlight(\'itemy\');" value="'.$item->ypos.'"'.$disabled.' /></td></tr>';
    echo '<tr><td><label for="itemsetting" id="itemsetting_label">'.get_string('itemsetting','assignment_uploadpdf').': </label></td>';
    echo '<td><input type="text" id="itemsetting" name="itemsetting" onChange="Highlight(\'itemsetting\');" value="'.$item->setting.'"'.$disabled.' /> '.get_string('itemsettingmore','assignment_uploadpdf');
    echo ' <a href="http://www.php.net/date" target="_blank">'.get_string('dateformatlink','assignment_uploadpdf').'</td></tr>';
    echo '<tr><td><label for="itemwidth" id="itemwidth_label" >'.get_string('itemwidth','assignment_uploadpdf').': </label></td>';
    echo '<td><input type="text" id="itemwidth" name="itemwidth" onChange="Highlight(\'itemwidth\');" value="'.$item->width.'"'.$disabled.' /> '.get_string('textonly','assignment_uploadpdf').'</td></tr>';
    echo '</table>';
    echo '<input type="submit" name="saveitem" value="'.get_string('saveitem','assignment_uploadpdf').'"'.$disabled.'/>';
    echo '<input type="submit" name="deleteitem" value="'.get_string('deleteitem','assignment_uploadpdf').'"'.$deletedisabled.'/>';
    echo '</fieldset>';
    echo '</form>';
}

function show_image($imagename, $templateid, $courseid, $hidden, $itemid) {
    global $CFG;
    //UT

    if ($imagename) {
        $partpath = '/'.$courseid.IMAGE_PATH.'/'.$imagename;
        $fullpath = $CFG->dataroot.$partpath;
        if (file_exists($fullpath)) {
            list($width, $height, $type, $attr) = getimagesize($fullpath);
            echo "<div style='width: {$width}px; height: {$height}px; border: solid 1px black;'>";
            echo '<div style="position: relative;">';
            $imageurl = $CFG->wwwroot.'/file.php?file='.$partpath.'&amp;tmpl='.$templateid;  // Templateid added to stop browser from showing incorrect cached image
            echo '<img src="'.$imageurl.'" alt="Preview Template" style="position: absolute; top: 0px; left: 0px;" onclick="clicked_on_image(event);" />';
            if ($templateid > 0) {
                $templateitems = $DB->get_records('assignment_uploadpdf_tmplitm', array('template' => $templateid) );
                if ($templateitems) {
                    foreach ($templateitems as $ti) {
                        $tiwidth = '';
                        if ($ti->type == 'text') {
                            $tiwidth = ' width: '.$ti->width.'px; ';
                        }
                        $cssid = '';
                        $border = '';
                        if ($itemid == $ti->id) {
                            $border = ' border: dashed 1px red; ';
                            $cssid = ' id = "current_template_item" ';
                        }
                        echo '<div style="position: absolute;'.$border.' font-family: helvetica, arial, sans; font-size: 12px; ';
                        echo 'top: '.$ti->ypos.'px; left: '.$ti->xpos.'px; '.$tiwidth;
                        echo '"' . $cssid . '>';
                        if (($ti->type == 'text') || ($ti->type == 'shorttext')) {
                            echo s($ti->setting);
                        } elseif ($ti->type == 'date') {
                            echo date($ti->setting);
                        }
                        echo '</div>';
                    }
                }
                if ($itemid == -1) {
                    echo '<div style="position: absolute; border: dashed 1px red; font-family: helvetica, arial, sans; font-size: 12px; ';
                    echo 'top: 0px; left: 0px;" id = "current_template_item">';
                    echo get_string('enterformtext', 'assignment_uploadpdf');
                    echo '</div>';
                }
            }
            echo '</div>';
            echo '&nbsp;';
            echo '</div>';
            return;
        }
    }

    $mform = new moodleform();
    $mform->addElement('filepicker', 'uploadpreview', get_string('previewinstructions','assignment_uploadpdf'), null, array('accepted_types'=>array('*.pdf')));
    $mform->display();

    /*
    // FIXME - replace with file_select form element
    echo '<form enctype="multipart/form-data" method="post" action="edittemplates.php">';
    echo '<fieldset>';
    echo $hidden;
    echo '<p>'.get_string('previewinstructions','assignment_uploadpdf').'</p>';
    require_once($CFG->libdir.'/uploadlib.php');
    upload_print_form_fragment(1,array('preview'),null,false,null,0,0,false);
    echo '<input type="submit" name="uploadpreview" value="'.get_string('uploadpreview','assignment_uploadpdf').'" />';
    echo '</fieldset>';
    echo '</form>';*/
}
?>