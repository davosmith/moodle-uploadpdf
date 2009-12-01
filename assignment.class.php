<?php

// Based on assignment_upload class from main Moodle repository

require_once($CFG->libdir.'/formslib.php');
require_once('mypdflib.php');

if (!class_exists('assignment_base')) {
    require_once(dirname(dirname(dirname(__FILE__))) . '/lib.php');
}

define('ASSIGNMENT_UPLOADPDF_STATUS_SUBMITTED', 'submitted');

/**
 * Extend the base assignment class for assignments where you upload a single file
 *
 */
class assignment_uploadpdf extends assignment_base {

    function assignment_uploadpdf($cmid=0) {
        parent::assignment_base($cmid);
        $this->type = 'uploadpdf';
    }

    function view() {
        global $USER, $CFG;

        require_capability('mod/assignment:view', $this->context);

        add_to_log($this->course->id, 'assignment', 'view', "view.php?id={$this->cm->id}", $this->assignment->id, $this->cm->id);

        $this->view_header();

        if ($this->assignment->timeavailable > time()
            and !has_capability('mod/assignment:grade', $this->context)      // grading user can see it anytime
            and $this->assignment->var3) {                                   // force hiding before available date
            print_simple_box_start('center', '', '', 0, 'generalbox', 'intro');
            print_string('notavailableyet', 'assignment');
            print_simple_box_end();
        } else {
            $this->view_intro();
        }

        $this->view_dates();

        if (has_capability('mod/assignment:submit', $this->context)) {
            $filecount = $this->count_user_files($USER->id);
            $submission = $this->get_submission($USER->id);

            $this->view_feedback();

            if ($this->is_finalized($submission)) {
                print_heading(get_string('submission', 'assignment'), '', 3);
            } else {
                print_heading(get_string('submissiondraft', 'assignment'), '', 3);
            }

            $extra = get_record('assignment_uploadpdf', 'assignment', $this->cm->instance);
            if ($extra && $extra->coversheet != '') {
                $filename = end(explode('/',$extra->coversheet));
                $partpath = '/'.$this->course->id.'/'.$extra->coversheet;
                $fullpath = $CFG->dataroot.$partpath;
                $url = $CFG->wwwroot.'/file.php?file='.$partpath;
                if (file_exists($fullpath)) {
                    echo '<p>'.get_string('coversheetnotice','assignment_uploadpdf').': ';
                    echo '<a href="'.$url.'" target="_blank">'.$filename.'</a></p>';
                }
            }

            if ($filecount and $submission) {
                print_simple_box($this->print_user_files($USER->id, true), 'center');
            } else {
                if ($this->is_finalized($submission)) {
                    print_simple_box(get_string('nofiles', 'assignment'), 'center');
                } else {
                    print_simple_box(get_string('nofilesyet', 'assignment'), 'center');
                }
            }

            $this->view_upload_form();

            if ($this->notes_allowed()) {
                print_heading(get_string('notes', 'assignment'), '', 3);
                $this->view_notes();
            }

            $this->view_final_submission();
        }
        $this->view_footer();
    }


    function view_feedback($submission=NULL) {
        global $USER;

        if (!$submission) { /// Get submission for this assignment
            $submission = $this->get_submission($USER->id);
        }

        if (empty($submission->timemarked)) {   /// Nothing to show, so print nothing
            if ($this->count_responsefiles($USER->id)) {
                print_heading(get_string('responsefiles', 'assignment', $this->course->teacher), '', 3);
                $responsefiles = $this->print_responsefiles($USER->id, true);
                print_simple_box($responsefiles, 'center');
            }
            return;
        }

        /// We need the teacher info
        if (! $teacher = get_record('user', 'id', $submission->teacher)) {
            error('Could not find the teacher');
        }

        /// Print the feedback
        print_heading(get_string('submissionfeedback', 'assignment'), '', 3);

        echo '<table cellspacing="0" class="feedback">';

        echo '<tr>';
        echo '<td class="left picture">';
        print_user_picture($teacher->id, $this->course->id, $teacher->picture);
        echo '</td>';
        echo '<td class="topic">';
        echo '<div class="from">';
        echo '<div class="fullname">'.fullname($teacher).'</div>';
        echo '<div class="time">'.userdate($submission->timemarked).'</div>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<td class="left side">&nbsp;</td>';
        echo '<td class="content">';
        if ($this->assignment->grade) {
            echo '<div class="grade">';
            echo get_string("grade").': '.$this->display_grade($submission->grade);
            echo '</div>';
            echo '<div class="clearer"></div>';
        }

        echo '<div class="comment">';
        echo format_text($submission->submissioncomment, $submission->format);
        echo '</div>';
        echo '</tr>';

        echo '<tr>';
        echo '<td class="left side">&nbsp;</td>';
        echo '<td class="content">';
        echo $this->print_responsefiles($USER->id, true);
        echo '</tr>';

        echo '</table>';
    }


    function view_upload_form() {
        global $CFG, $USER;

        $submission = $this->get_submission($USER->id);

        $struploadafile = get_string('uploadafile');
        $strmaxsize = get_string('maxsize', '', display_size($this->assignment->maxbytes));

        if ($this->is_finalized($submission)) {
            // no uploading
            return;
        }

        if ($this->can_upload_file($submission)) {
            echo '<div style="text-align:center">';
            echo '<form enctype="multipart/form-data" method="post" action="upload.php">';
            echo '<fieldset class="invisiblefieldset">';
            echo "<p>$struploadafile ($strmaxsize)</p>";
            echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
            echo '<input type="hidden" name="action" value="uploadfile" />';
            require_once($CFG->libdir.'/uploadlib.php');
            upload_print_form_fragment(1,array('newfile'),null,false,null,0,$this->assignment->maxbytes,false);
            echo '<input type="submit" name="save" value="'.get_string('uploadthisfile').'" />';
            echo '</fieldset>';
            echo '</form>';
            echo '</div>';
            echo '<br />';
        }

    }

    function view_notes() {
        global $USER;

        if ($submission = $this->get_submission($USER->id)
            and !empty($submission->data1)) {
            print_simple_box(format_text($submission->data1, FORMAT_HTML), 'center', '630px');
        } else {
            print_simple_box(get_string('notesempty', 'assignment'), 'center');
        }
        if ($this->can_update_notes($submission)) {
            $options = array ('id'=>$this->cm->id, 'action'=>'editnotes');
            echo '<div style="text-align:center">';
            print_single_button('upload.php', $options, get_string('edit'), 'post', '_self', false);
            echo '</div>';
        }
    }

    function view_final_submission() {
        global $CFG, $USER;

        $submission = $this->get_submission($USER->id);

        if ($this->can_finalize($submission)) {
            //print final submit button
            print_heading(get_string('submitformarking','assignment'), '', 3);
            echo '<div style="text-align:center">';
            echo '<form method="post" action="upload.php">';
            echo '<fieldset class="invisiblefieldset">';

            $extra = get_record('assignment_uploadpdf', 'assignment', $this->cm->instance);
            if ($extra &&  ($extra->coversheet != '') && ($extra->template > 0)) {
                $t_items = get_records('assignment_uploadpdf_tmplitm','template', $extra->template);
                $ticount = 0;
                if ($t_items) {
                    echo '<table>';
                    foreach ($t_items as $ti) {
                        if ($ti->type == 'text') {
                            $ticount++;
                            $inputname = 'templ'.$ticount;
                            echo '<tr><td align="right"><label for="'.$inputname.'">'.s($ti->setting).': </label></td>';
                            echo '<td><textarea name="'.$inputname.'" cols="30" rows="5"></textarea></td></tr>';

                        } elseif ($ti->type == 'shorttext') {
                            $ticount++;
                            $inputname = 'templ'.$ticount;
                            echo '<tr><td align="right"><label for="'.$inputname.'">'.s($ti->setting).': </label></td>';
                            echo '<td><input type="text" name="'.$inputname.'" /></td></tr>';
                        }
                        // Date type does not have an input box
                    }
                    echo '</table>';
                }
            }

            echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
            echo '<input type="hidden" name="action" value="finalize" />';
            echo '<input type="submit" name="formarking" value="'.get_string('sendformarking', 'assignment').'" />';
            echo '</fieldset>';
            echo '</form>';
            echo '</div>';
        } else if ($this->is_finalized($submission)) {
            print_heading(get_string('submitedformarking','assignment'), '', 3);
        } else {
            //no submission yet
        }
    }

    function custom_feedbackform($submission, $return=false) {
        global $CFG;

        $mode         = optional_param('mode', '', PARAM_ALPHA);
        $offset       = optional_param('offset', 0, PARAM_INT);
        $forcerefresh = optional_param('forcerefresh', 0, PARAM_BOOL);

        /*        $output = get_string('responsefiles', 'assignment').': ';

                  $output .= '<form enctype="multipart/form-data" method="post" '.
                  "action=\"$CFG->wwwroot/mod/assignment/upload.php\">";
                  $output .= '<div>';
                  $output .= '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                  $output .= '<input type="hidden" name="action" value="uploadresponse" />';
                  $output .= '<input type="hidden" name="mode" value="'.$mode.'" />';
                  $output .= '<input type="hidden" name="offset" value="'.$offset.'" />';
                  $output .= '<input type="hidden" name="userid" value="'.$submission->userid.'" />';
                  require_once($CFG->libdir.'/uploadlib.php');
                  $output .= upload_print_form_fragment(1,array('newfile'),null,false,null,0,0,true);
                  $output .= '<input type="submit" name="save" value="'.get_string('uploadthisfile').'" />';
                  $output .= '</div>';
                  $output .= '</form>';
        */

        $output = '';
        
        if ($forcerefresh) {
            $output .= $this->update_main_listing($submission);
        }

        $responsefiles = $this->print_responsefiles($submission->userid, true);
        if (!empty($responsefiles)) {
            $output .= $responsefiles;
        }

        if ($return) {
            return $output;
        }
        echo $output;
        return;
    }


    function print_student_answer($userid, $return=false){
        global $CFG;

        $filearea = $this->file_area_name($userid);
        $submission = $this->get_submission($userid);

        $output = '';

        if ($basedir = $this->file_area($userid)) {
            if (!$this->is_finalized($submission)) {
                $output .= '<strong>'.get_string('draft', 'assignment').':</strong> ';
            }

            if ($this->notes_allowed() and !empty($submission->data1)) {
                $output .= link_to_popup_window ('/mod/assignment/type/uploadpdf/notes.php?id='.$this->cm->id.'&amp;userid='.$userid,
                                                 'notes'.$userid, get_string('notes', 'assignment'), 500, 780, get_string('notes', 'assignment'), 'none', true, 'notesbutton'.$userid);
                $output .= '&nbsp;';
            }

            if ($this->is_finalized($submission)) {
                if ($files = get_directory_list($basedir.'/submission')) {
                    foreach ($files as $key => $file) {
                        require_once($CFG->libdir.'/filelib.php');
                        $icon = mimeinfo('icon', $file);
                        if (mimeinfo('type', $file) == 'application/pdf') {
                            $ffurl = '/mod/assignment/type/uploadpdf/editcomment.php?id='.$this->cm->id.'&amp;userid='.$userid;
                            $output .= link_to_popup_window($ffurl, 'editcomment'.$userid, '<img class="icon" src="'.$CFG->pixpath.'/f/'.$icon.'" alt="'.$icon.'" />'.$file,
                                                            700, 1000, get_string('annotatesubmission', 'assignment_uploadpdf'), 'none', true, 'editcommentbutton'.$userid);
                        } else {
                            $ffurl = "$CFG->wwwroot/file.php?file=/$filearea/submission/$file"; 
                            $output .= '<a href="'.$ffurl.'" ><img class="icon" src="'.$CFG->pixpath.'/f/'.$icon.'" alt="'.$icon.'" />'.$file.'</a>&nbsp;';
                        }
                    }
                    if (file_exists($basedir.'/responses/response.pdf')) {
                        $respicon = mimeinfo('icon', $basedir.'/responses/response.pdf');
                        $respurl = "$CFG->wwwroot/file.php?file=/$filearea/responses/response.pdf";
                        $output .= '<br />=&gt; <a href="'.$respurl.'" ><img class="icon" src="'.$CFG->pixpath.'/f/'.$respicon.'" alt="'.$respicon.'" />response.pdf</a>&nbsp;';
                    }
                }
            } else {
                if ($files = get_directory_list($basedir, array('responses','submission','images'))) {
                    foreach ($files as $key => $file) {
                        require_once($CFG->libdir.'/filelib.php');
                        $icon = mimeinfo('icon', $file);
                        $ffurl = "$CFG->wwwroot/file.php?file=/$filearea/$file";
                        $output .= '<a href="'.$ffurl.'" ><img class="icon" src="'.$CFG->pixpath.'/f/'.$icon.'" alt="'.$icon.'" />'.$file.'</a>&nbsp;';
                    }
                }
            }
        }
        $output = '<div class="files">'.$output.'</div>';
        $output .= '<br />';

        return $output;
    }


    /**
     * Produces a list of links to the files uploaded by a user
     *
     * @param $userid int optional id of the user. If 0 then $USER->id is used.
     * @param $return boolean optional defaults to false. If true the list is returned rather than printed
     * @return string optional
     */
    function print_user_files($userid=0, $return=false) {
        global $CFG, $USER;

        $mode    = optional_param('mode', '', PARAM_ALPHA);
        $offset  = optional_param('offset', 0, PARAM_INT);

        if (!$userid) {
            if (!isloggedin()) {
                return '';
            }
            $userid = $USER->id;
        }

        $filearea = $this->file_area_name($userid);

        $output = '';

        if ($submission = $this->get_submission($userid)) {

            $candelete = $this->can_delete_files($submission);
            $strdelete   = get_string('delete');

            if (!$this->is_finalized($submission) and !empty($mode)) {                 // only during grading
                $output .= '<strong>'.get_string('draft', 'assignment').':</strong><br />';
            }

            if ($this->notes_allowed() and !empty($submission->data1) and !empty($mode)) { // only during grading
                $offset = required_param('offset', PARAM_INT);

                $npurl = "type/upload/notes.php?id={$this->cm->id}&amp;userid=$userid&amp;offset=$offset&amp;mode=single";
                $output .= '<a href="'.$npurl.'">'.get_string('notes', 'assignment').'</a><br />';

            }

            if ($this->is_finalized($submission)) {
                if ($basedir = $this->file_area($userid)) {
                    $basedir .= '/submission';
                    if ($files = get_directory_list($basedir)) {
                        require_once($CFG->libdir.'/filelib.php');
                        foreach ($files as $key => $file) {
                            $icon = mimeinfo('icon', $file);
                            if ((mimeinfo('type',$file) == 'application/pdf') && (has_capability('mod/assignment:grade', $this->context))) {
                                $ffurl = '/mod/assignment/type/uploadpdf/editcomment.php?id='.$this->cm->id.'&amp;userid='.$userid;
                                $output .= link_to_popup_window($ffurl, 'editcomment'.$userid,
                                                                '<img class="icon" src="'.$CFG->pixpath.'/f/'.$icon.'" alt="'.$icon.'" />'.$file, 700, 1000,
                                                                get_string('annotatesubmission', 'assignment_uploadpdf'), 'none', true, 'editcommentbutton'.$userid);
                            } else {
                                $ffurl   = "$CFG->wwwroot/file.php?file=/$filearea/submission/$file"; // download pdf
                                $output .= '<a href="'.$ffurl.'" ><img src="'.$CFG->pixpath.'/f/'.$icon.'" class="icon" alt="'.$icon.'" />'.$file.'</a>';
                            }
                            if ($candelete) {
                                $delurl  = "$CFG->wwwroot/mod/assignment/delete.php?id={$this->cm->id}&amp;file=$file&amp;userid={$submission->userid}&amp;mode=$mode&amp;offset=$offset";
                                $output .= '<a href="'.$delurl.'">&nbsp;'
                                    .'<img title="'.$strdelete.'" src="'.$CFG->pixpath.'/t/delete.gif" class="iconsmall" alt="" /></a> ';
                            }
                            $output .= '<br />';
                        }
                    }
                }
                
            } else {
                if ($basedir = $this->file_area($userid)) {
                    if ($files = get_directory_list($basedir, array('responses','submission','images'))) {
                        require_once($CFG->libdir.'/filelib.php');
                        foreach ($files as $key => $file) {

                            $icon = mimeinfo('icon', $file);

                            $ffurl   = "$CFG->wwwroot/file.php?file=/$filearea/$file";


                            $output .= '<a href="'.$ffurl.'" ><img src="'.$CFG->pixpath.'/f/'.$icon.'" class="icon" alt="'.$icon.'" />'.$file.'</a>';

                            if ($candelete) {
                                $delurl  = "$CFG->wwwroot/mod/assignment/delete.php?id={$this->cm->id}&amp;file=$file&amp;userid={$submission->userid}&amp;mode=$mode&amp;offset=$offset";

                                $output .= '<a href="'.$delurl.'">&nbsp;'
                                    .'<img title="'.$strdelete.'" src="'.$CFG->pixpath.'/t/delete.gif" class="iconsmall" alt="" /></a> ';
                            }

                            $output .= '<br />';
                        }
                    }
                }
            }
            if (has_capability('mod/assignment:grade', $this->context)
                and $this->can_unfinalize($submission)
                and $mode != '') { // we do not want it on view.php page
                $options = array ('id'=>$this->cm->id, 'userid'=>$userid, 'action'=>'unfinalize', 'mode'=>$mode, 'offset'=>$offset);
                $output .= print_single_button('upload.php', $options, get_string('unfinalize', 'assignment'), 'post', '_self', true);
            }

            $output = '<div class="files">'.$output.'</div>';

        }

        if ($return) {
            return $output;
        }
        echo $output;
    }

    function print_responsefiles($userid, $return=false) {
        global $CFG, $USER;

        $mode    = optional_param('mode', '', PARAM_ALPHA);
        $offset  = optional_param('offset', 0, PARAM_INT);

        $filearea = $this->file_area_name($userid).'/responses';

        $output = '';

        $candelete = $this->can_manage_responsefiles();
        $strdelete   = get_string('delete');

        if ($basedir = $this->file_area($userid)) {
            $basedir .= '/responses';

            if ($files = get_directory_list($basedir)) {
                require_once($CFG->libdir.'/filelib.php');
                foreach ($files as $key => $file) {

                    $icon = mimeinfo('icon', $file);
                    $ffurl   = "$CFG->wwwroot/file.php?file=/$filearea/$file";
                    $output .= '<a href="'.$ffurl.'" ><img class="align" src="'.$CFG->pixpath.'/f/'.$icon.'" alt="'.$icon.'" />'.$file.'</a>';
                    if ($candelete) {
                        $delurl  = "$CFG->wwwroot/mod/assignment/delete.php?id={$this->cm->id}&amp;file=$file&amp;userid=$userid&amp;mode=$mode&amp;offset=$offset&amp;action=response";
                        $output .= '<a href="'.$delurl.'">&nbsp;'
                            .'<img title="'.$strdelete.'" src="'.$CFG->pixpath.'/t/delete.gif" class="iconsmall" alt=""/></a> ';
                    }
                    $output .= '&nbsp;';
                }
            }

            $output = '<div class="responsefiles">'.$output.'</div>';
        }

        if ($return) {
            return $output;
        }
        echo $output;
    }


    function upload() {
        $action = required_param('action', PARAM_ALPHA);

        switch ($action) {
        case 'finalize':
            $this->finalize();
            break;
        case 'unfinalize':
            $this->unfinalize();
            break;
        case 'uploadresponse':
            $this->upload_responsefile();
            break;
        case 'uploadfile':
            $this->upload_file();
        case 'savenotes':
        case 'editnotes':
            $this->upload_notes();
        default:
            error('Error: Unknow upload action ('.$action.').');
        }
    }

    function upload_notes() {
        global $CFG, $USER;

        $action = required_param('action', PARAM_ALPHA);

        $returnurl = 'view.php?id='.$this->cm->id;

        $mform = new mod_assignment_uploadpdf_notes_form();

        $defaults = new object();
        $defaults->id = $this->cm->id;

        if ($submission = $this->get_submission($USER->id)) {
            $defaults->text = $submission->data1;
        } else {
            $defaults->text = '';
        }

        $mform->set_data($defaults);

        if ($mform->is_cancelled()) {
            redirect('view.php?id='.$this->cm->id);
        }

        if (!$this->can_update_notes($submission)) {
            $this->view_header(get_string('upload'));
            notify(get_string('uploaderror', 'assignment'));
            print_continue($returnurl);
            $this->view_footer();
            die;
        }

        if ($data = $mform->get_data() and $action == 'savenotes') {
            $submission = $this->get_submission($USER->id, true); // get or create submission
            $updated = new object();
            $updated->id           = $submission->id;
            $updated->timemodified = time();
            $updated->data1        = $data->text;

            if (update_record('assignment_submissions', $updated)) {
                add_to_log($this->course->id, 'assignment', 'upload', 'view.php?a='.$this->assignment->id, $this->assignment->id, $this->cm->id);
                redirect($returnurl);
            } else {
                $this->view_header(get_string('notes', 'assignment'));
                notify(get_string('notesupdateerror', 'assignment'));
                print_continue($returnurl);
                $this->view_footer();
                die;
            }
        }

        /// show notes edit form
        $this->view_header(get_string('notes', 'assignment'));

        print_heading(get_string('notes', 'assignment'), '');

        $mform->display();

        $this->view_footer();
        die;
    }

    function upload_responsefile() {
        global $CFG;

        $userid = required_param('userid', PARAM_INT);
        $mode   = required_param('mode', PARAM_ALPHA);
        $offset = required_param('offset', PARAM_INT);

        $returnurl = "submissions.php?id={$this->cm->id}&amp;userid=$userid&amp;mode=$mode&amp;offset=$offset";

        if (data_submitted('nomatch') and $this->can_manage_responsefiles()) {
            $dir = $this->file_area_name($userid).'/responses';
            check_dir_exists($CFG->dataroot.'/'.$dir, true, true);

            require_once($CFG->dirroot.'/lib/uploadlib.php');
            $um = new upload_manager('newfile',false,true,$this->course,false,0,true);

            if (!$um->process_file_uploads($dir)) {
                print_header(get_string('upload'));
                notify(get_string('uploaderror', 'assignment'));
                echo $um->get_errors();
                print_continue($returnurl);
                print_footer('none');
                die;
            }
        }
        redirect($returnurl);
    }

    function upload_file() {
        global $CFG, $USER;

        $mode   = optional_param('mode', '', PARAM_ALPHA);
        $offset = optional_param('offset', 0, PARAM_INT);

        $returnurl = 'view.php?id='.$this->cm->id;

        $filecount = $this->count_user_files($USER->id);
        $submission = $this->get_submission($USER->id);

        if (!$this->can_upload_file($submission)) {
            $this->view_header(get_string('upload'));
            notify(get_string('uploaderror', 'assignment'));
            print_continue($returnurl);
            $this->view_footer();
            die;
        }

        $dir = $this->file_area_name($USER->id);
        check_dir_exists($CFG->dataroot.'/'.$dir, true, true); // better to create now so that student submissions do not block it later

        require_once($CFG->dirroot.'/lib/uploadlib.php');
        $um = new upload_manager('newfile',false,true,$this->course,false,$this->assignment->maxbytes,true);

        if ($um->process_file_uploads($dir)) {
            $fp = $um->get_new_filepath();
            $fn = $um->get_new_filename();
            if ($fp && $fn) {
                $dest = $CFG->dataroot.'/'.$dir.'/'.sprintf('%02d',$filecount+1).'-'.$fn;
                rename($fp, $dest);
            }
            $submission = $this->get_submission($USER->id, true); //create new submission if needed
            $updated = new object();
            $updated->id           = $submission->id;
            $updated->timemodified = time();

            if (update_record('assignment_submissions', $updated)) {
                add_to_log($this->course->id, 'assignment', 'upload',
                           'view.php?a='.$this->assignment->id, $this->assignment->id, $this->cm->id);
            } else {
                $new_filename = $um->get_new_filename();
                $this->view_header(get_string('upload'));
                notify(get_string('uploadnotregistered', 'assignment', $new_filename));
                print_continue($returnurl);
                $this->view_footer();
                die;
            }
            redirect('view.php?id='.$this->cm->id);
        }
        $this->view_header(get_string('upload'));
        notify(get_string('uploaderror', 'assignment'));
        echo $um->get_errors();
        print_continue($returnurl);
        $this->view_footer();
        die;
    }

    function finalize() {
        global $USER;

        $confirm = optional_param('confirm', 0, PARAM_BOOL);
        $confirmnotpdf = optional_param('confirmnotpdf', 0, PARAM_BOOL);

        $returnurl = 'view.php?id='.$this->cm->id;
        $submission = $this->get_submission($USER->id);

        if (!$this->can_finalize($submission)) {
            redirect($returnurl); // probably already graded, erdirect to assignment page, the reason should be obvious
        }

        $optionsno = array('id'=>$this->cm->id);
        $optionsyes = array('id'=>$this->cm->id, 'action'=>'finalize');
        
        // Check that form for coversheet has been filled in
        // (but don't complain about it until the PDF check has been done) 
        $templatedataOK = true;
        $extra = get_record('assignment_uploadpdf', 'assignment', $this->cm->instance);
        $templateitems = false;
        if ($extra &&  ($extra->coversheet != '') && ($extra->template > 0)) {
            $templateitems = get_records('assignment_uploadpdf_tmplitm','template', $extra->template);
            $ticount = 0;
            if ($templateitems) {
                foreach ($templateitems as $ti) {
                    if (($ti->type == 'text') || ($ti->type == 'shorttext')) {
                        $ticount++;
                        $itemname = 'templ'.$ticount;
                        $param = optional_param('templ'.$ticount, '', PARAM_TEXT);
                        if (trim($param) == '') {
                            $templatedataOK = false;
                        } else {
                            $optionsyes['templ'.$ticount] = $param; /* Keep to pass on after yes/no questions answered */
                            $ti->data = $param; /* Keep to pass on to the coversheet generation */
                        }
                    }
                }
            }
        }

        // Check that all files submitted are PDFs
        if ($file = $this->get_not_pdf($USER->id)) {
            if (!$confirmnotpdf) {
                $this->view_header();
                print_heading(get_string('nonpdfheading', 'assignment_uploadpdf'));
                if ($extra && $extra->onlypdf) {
                    notify(get_string('filenotpdf', 'assignment_uploadpdf', $file));
                    print_continue($returnurl);
                } else {
                    if ($this->get_pdf_count($USER->id) < 1) {
                        notify(get_string('nopdf', 'assignment_uploadpdf'));
                        print_continue($returnurl);
                    } else {
                        $optionsyes['confirmnotpdf'] = 1;
                        notice_yesno(get_string('filenotpdf_continue', 'assignment_uploadpdf', $file), 'upload.php', 'view.php', $optionsyes, $optionsno, 'post', 'get');
                    }
                }
                $this->view_footer();
                die;
            }
        }

        if (!$templatedataOK) {
            $this->view_header();
            print_heading(get_string('heading_templatedatamissing', 'assignment_uploadpdf'));
            notify(get_string('templatedatamissing', 'assignment_uploadpdf'));
            print_continue($returnurl);
            die;
        }

        if (!data_submitted('nomatch') or !$confirm) {
            $optionsyes['confirmnotpdf'] = 1;
            $optionsyes['confirm'] = 1;
            $this->view_header(get_string('submitformarking', 'assignment'));
            print_heading(get_string('submitformarking', 'assignment'));
            notice_yesno(get_string('onceassignmentsent', 'assignment'), 'upload.php', 'view.php', $optionsyes, $optionsno, 'post', 'get');
            $this->view_footer();
            die;

        } else {
            if (!($pagecount = $this->create_submission_pdf($USER->id, $templateitems))) {
                $this->view_header(get_string('submitformarking', 'assignment'));
                notify(get_string('createsubmissionfailed', 'assignment_uploadpdf'));
                print_continue($returnurl);
                $this->view_footer();
                die;
            }

            $updated = new object();
            $updated->id = $submission->id;
            $updated->data2 = ASSIGNMENT_UPLOADPDF_STATUS_SUBMITTED;
            $updated->timemodified = time();
            $updated->numfiles = $pagecount << 1;   // Last bit is already used to indicate which folders the cron job should check for images to delete
            if (update_record('assignment_submissions', $updated)) {
                add_to_log($this->course->id, 'assignment', 'upload', //TODO: add finilize action to log
                           'view.php?a='.$this->assignment->id, $this->assignment->id, $this->cm->id);
                $this->email_teachers($submission);
            } else {
                $this->view_header(get_string('submitformarking', 'assignment'));
                notify(get_string('finalizeerror', 'assignment'));
                print_continue($returnurl);
                $this->view_footer();
                die;
            }
        }
        redirect($returnurl);
    }

    function unfinalize() {
        global $CFG;

        $userid = required_param('userid', PARAM_INT);
        $mode   = required_param('mode', PARAM_ALPHA);
        $offset = required_param('offset', PARAM_INT);

        $returnurl = "submissions.php?id={$this->cm->id}&amp;userid=$userid&amp;mode=$mode&amp;offset=$offset&amp;forcerefresh=1";

        if (data_submitted('nomatch')
            and $submission = $this->get_submission($userid)
            and $this->can_unfinalize($submission)) {
            
            require_once($CFG->libdir.'/filelib.php');
            $subpath = $CFG->dataroot.'/'.$this->file_area_name($userid).'/submission';
            $imgpath = $CFG->dataroot.'/'.$this->file_area_name($userid).'/images';
            fulldelete($subpath);
            fulldelete($imgpath);

            $updated = new object();
            $updated->id = $submission->id;
            $updated->data2 = '';
            if (update_record('assignment_submissions', $updated)) {
                //TODO: add unfinilize action to log
                add_to_log($this->course->id, 'assignment', 'view submission', 'submissions.php?id='.$this->assignment->id, $this->assignment->id, $this->cm->id);
            } else {
                $this->view_header(get_string('submitformarking'));
                notify(get_string('finalizeerror', 'assignment'));
                print_continue($returnurl);
                $this->view_footer();
                die;
            }
        }
        redirect($returnurl);
    }


    function delete() {
        $action   = optional_param('action', '', PARAM_ALPHA);

        switch ($action) {
        case 'response':
            $this->delete_responsefile();
            break;
        default:
            $this->delete_file();
        }
        die;
    }


    function delete_responsefile() {
        global $CFG;

        $file     = required_param('file', PARAM_FILE);
        $userid   = required_param('userid', PARAM_INT);
        $mode     = required_param('mode', PARAM_ALPHA);
        $offset   = required_param('offset', PARAM_INT);
        $confirm  = optional_param('confirm', 0, PARAM_BOOL);

        $returnurl = "submissions.php?id={$this->cm->id}&amp;userid=$userid&amp;mode=$mode&amp;offset=$offset";

        if (!$this->can_manage_responsefiles()) {
            redirect($returnurl);
        }

        $urlreturn = 'submissions.php';
        $optionsreturn = array('id'=>$this->cm->id, 'offset'=>$offset, 'mode'=>$mode, 'userid'=>$userid);

        if (!data_submitted('nomatch') or !$confirm) {
            $optionsyes = array ('id'=>$this->cm->id, 'file'=>$file, 'userid'=>$userid, 'confirm'=>1, 'action'=>'response', 'mode'=>$mode, 'offset'=>$offset);
            print_header(get_string('delete'));
            print_heading(get_string('delete'));
            notice_yesno(get_string('confirmdeletefile', 'assignment', $file), 'delete.php', $urlreturn, $optionsyes, $optionsreturn, 'post', 'get');
            print_footer('none');
            die;
        }

        $dir = $this->file_area_name($userid).'/responses';
        $filepath = $CFG->dataroot.'/'.$dir.'/'.$file;
        if (file_exists($filepath)) {
            if (@unlink($filepath)) {
                redirect($returnurl);
            }
        }

        // print delete error
        print_header(get_string('delete'));
        notify(get_string('deletefilefailed', 'assignment'));
        print_continue($returnurl);
        print_footer('none');
        die;

    }


    function delete_file() {
        global $CFG;

        $file     = required_param('file', PARAM_FILE);
        $userid   = required_param('userid', PARAM_INT);
        $confirm  = optional_param('confirm', 0, PARAM_BOOL);
        $mode     = optional_param('mode', '', PARAM_ALPHA);
        $offset   = optional_param('offset', 0, PARAM_INT);

        require_login($this->course->id, false, $this->cm);

        if (empty($mode)) {
            $urlreturn = 'view.php';
            $optionsreturn = array('id'=>$this->cm->id);
            $returnurl = 'view.php?id='.$this->cm->id;
        } else {
            $urlreturn = 'submissions.php';
            $optionsreturn = array('id'=>$this->cm->id, 'offset'=>$offset, 'mode'=>$mode, 'userid'=>$userid);
            $returnurl = "submissions.php?id={$this->cm->id}&amp;offset=$offset&amp;mode=$mode&amp;userid=$userid";
        }

        if (!$submission = $this->get_submission($userid) // incorrect submission
            or !$this->can_delete_files($submission)) {     // can not delete
            $this->view_header(get_string('delete'));
            notify(get_string('cannotdeletefiles', 'assignment'));
            print_continue($returnurl);
            $this->view_footer();
            die;
        }
        $dir = $this->file_area_name($userid);

        if (!data_submitted('nomatch') or !$confirm) {
            $optionsyes = array ('id'=>$this->cm->id, 'file'=>$file, 'userid'=>$userid, 'confirm'=>1, 'sesskey'=>sesskey(), 'mode'=>$mode, 'offset'=>$offset);
            if (empty($mode)) {
                $this->view_header(get_string('delete'));
            } else {
                print_header(get_string('delete'));
            }
            print_heading(get_string('delete'));
            notice_yesno(get_string('confirmdeletefile', 'assignment', $file), 'delete.php', $urlreturn, $optionsyes, $optionsreturn, 'post', 'get');
            if (empty($mode)) {
                $this->view_footer();
            } else {
                print_footer('none');
            }
            die;
        }

        $filepath = $CFG->dataroot.'/'.$dir.'/'.$file;
        if (file_exists($filepath)) {
            if (@unlink($filepath)) {
                $updated = new object();
                $updated->id = $submission->id;
                $updated->timemodified = time();
                if (update_record('assignment_submissions', $updated)) {
                    add_to_log($this->course->id, 'assignment', 'upload', //TODO: add delete action to log
                               'view.php?a='.$this->assignment->id, $this->assignment->id, $this->cm->id);
                }
                $this->renumber_files($userid);
                redirect($returnurl);
            }
        }

        // print delete error
        if (empty($mode)) {
            $this->view_header(get_string('delete'));
        } else {
            print_header(get_string('delete'));
        }
        notify(get_string('deletefilefailed', 'assignment'));
        print_continue($returnurl);
        if (empty($mode)) {
            $this->view_footer();
        } else {
            print_footer('none');
        }
        die;
    }


    function can_upload_file($submission) {
        global $USER;

        if (has_capability('mod/assignment:submit', $this->context)           // can submit
            and $this->isopen()                                                 // assignment not closed yet
            and (empty($submission) or $submission->grade == -1)                // not graded
            and (empty($submission) or $submission->userid == $USER->id)        // his/her own submission
            and $this->count_user_files($USER->id) < $this->assignment->var1) { // file limit not reached
            return true;
        } else {
            return false;
        }
    }

    function can_manage_responsefiles() {
        if (has_capability('mod/assignment:grade', $this->context)) {
            return true;
        } else {
            return false;
        }
    }

    function can_delete_files($submission) {
        global $USER;

        if (has_capability('mod/assignment:grade', $this->context)) {
            return true;
        }

        if (has_capability('mod/assignment:submit', $this->context)
            and $this->isopen()                                      // assignment not closed yet
            and (!empty($submission) and $submission->grade == -1)   // not graded
            and $this->assignment->resubmit                          // deleting allowed
            and $USER->id == $submission->userid                     // his/her own submission
            and !$this->is_finalized($submission)) {                 // no deleting after final submission
            return true;
        } else {
            return false;
        }
    }

    function is_finalized($submission) {
        if (!empty($submission)
            and $submission->data2 == ASSIGNMENT_UPLOADPDF_STATUS_SUBMITTED) {
            return true;
        } else {
            return false;
        }
    }

    function can_unfinalize($submission) {
        if (has_capability('mod/assignment:grade', $this->context)
            and !empty($submission)
            and $this->is_finalized($submission)
            and $submission->grade == -1) {
            return true;
        } else {
            return false;
        }
    }

    function can_finalize($submission) {
        global $USER;

        if (has_capability('mod/assignment:submit', $this->context)           // can submit
            and $this->isopen()                                                 // assignment not closed yet
            and !empty($submission)                                             // submission must exist
            and $submission->data2 != ASSIGNMENT_UPLOADPDF_STATUS_SUBMITTED               // not graded
            and $submission->userid == $USER->id                                // his/her own submission
            and $submission->grade == -1                                        // no reason to finalize already graded submission
            and ($this->count_user_files($USER->id)
                 or ($this->notes_allowed() and !empty($submission->data1)))) { // something must be submitted

            return true;
        } else {
            return false;
        }
    }

    function can_update_notes($submission) {
        global $USER;

        if (has_capability('mod/assignment:submit', $this->context)
            and $this->notes_allowed()                                               // notesd must be allowed
            and $this->isopen()                                                 // assignment not closed yet
            and (empty($submission) or $submission->grade == -1)                // not graded
            and (empty($submission) or $USER->id == $submission->userid)        // his/her own submission
            and !$this->is_finalized($submission)) {                            // no updateingafter final submission
            return true;
        } else {
            return false;
        }
    }

    function notes_allowed() {
        return (boolean)$this->assignment->var2;
    }

    /**
     * Count the files uploaded by a given user
     *
     * @param $userid int The user id
     * @return int
     */
    function count_user_files($userid) {
        global $CFG;

        $filearea = $this->file_area_name($userid);

        if ( is_dir($CFG->dataroot.'/'.$filearea) && $basedir = $this->file_area($userid)) {
            if ($files = get_directory_list($basedir, array('responses','submission','images'))) {
                return count($files);
            }
        }
        return 0;
    }

    function count_responsefiles($userid) {
        global $CFG;

        $filearea = $this->file_area_name($userid).'/responses';

        if ( is_dir($CFG->dataroot.'/'.$filearea) && $basedir = $this->file_area($userid)) {
            $basedir .= '/responses';
            if ($files = get_directory_list($basedir)) {
                return count($files);
            }
        }
        return 0;
    }

    function get_not_pdf($userid) {
        
        global $CFG;

        $filearea = $this->file_area_name($userid);
        
        if ( is_dir($CFG->dataroot.'/'.$filearea) && $basedir = $this->file_area($userid)) {
            if ($files = get_directory_list($basedir, array('responses','submission','images'))) {
                require_once($CFG->libdir.'/filelib.php');
                foreach ($files as $key => $file) {
                    if (mimeinfo('type', $file) != 'application/pdf') {
                        return $file;
                    }
                }
            }
        }
        return false;
    }

    function get_pdf_count($userid) {
        global $CFG;

        $count = 0;
        $filearea = $this->file_area_name($userid);
        
        if ( is_dir($CFG->dataroot.'/'.$filearea) && $basedir = $this->file_area($userid)) {
            if ($files = get_directory_list($basedir, array('responses','submission','images'))) {
                foreach ($files as $key => $file) {
                    if (mimeinfo('type', $file) == 'application/pdf') {
                        $count++;
                    }
                }
            }
        }
        return $count;
    }

    function renumber_files($userid) {
        global $CFG;

        $count = 0;
        $filearea = $this->file_area_name($userid);
                
        if ( is_dir($CFG->dataroot.'/'.$filearea) && $basedir = $this->file_area($userid)) {
            if ($files = get_directory_list($basedir, array('responses','submission','images'))) {
                foreach ($files as $key => $file) {
                    $count++;
                    $prefix = sprintf('%02d', $count).'-';
                    if (substr($file,0,3) != $prefix) {
                        $newname = $prefix.substr($file,3);
                        rename($basedir.'/'.$file, $basedir.'/'.$newname);
                    }
                }
            }
        }
        return $count;
    }

    function create_submission_pdf($userid, $template) {
        global $CFG;

        $filearea = $CFG->dataroot.'/'.$this->file_area_name($userid);
        $destarea = $filearea.'/submission';
        check_dir_exists($destarea, true, true);
        $destfile = $destarea.'/submission.pdf';

        $mypdf = new MyPDFLib();
      
        if ( is_dir($filearea) ) {
            if ($files = get_directory_list($filearea, array('responses','submission','images'))) {
                foreach ($files as $key=>$fl) { 
                    if (mimeinfo('type', $fl) != 'application/pdf') {
                        copy($filearea.'/'.$fl, $filearea.'/submission/'.$fl); /* Copy any non-PDF files to submission folder */
                        unset($files[$key]);
                    }
                }
                if (count($files) > 0) { /* Should have already checked there is at least 1 PDF */
                    $coversheet = null;
                    $extra = get_record('assignment_uploadpdf', 'assignment', $this->assignment->id);
                    if ($extra) {
                        if (!$extra->coversheet) {
                            $coversheet = null;
                        } else {
                            $coversheet = $CFG->dataroot.'/'.$this->course->id.'/'.$extra->coversheet;
                            if (!file_exists($coversheet)) {
                                // FIXME - Add a meaningful error message onto the screen at this point!
                                $coversheet = null;
                            }
                        }
                    }
                    
                    return $mypdf->combine_pdfs($filearea, $files, $destfile, $coversheet, $template);
                } else {
                    return 0;
                }
            }
        }
        return 0;
    }

    function create_response_pdf($userid, $submissionid) {
        global $CFG;

        $filearea = $CFG->dataroot.'/'.$this->file_area_name($userid);
        $sourcearea = $filearea.'/submission';
        $sourcefile = $sourcearea.'/submission.pdf';
        if (!is_dir($sourcearea) || !file_exists($sourcefile)) {
            error('Submitted PDF not found');
            return false;
        }
        
        $destarea = $filearea.'/responses';
        $destfile = $destarea.'/response.pdf';
        check_dir_exists($destarea, true, true);

        $mypdf = new MyPDFLib();
        $mypdf->load_pdf($sourcefile);

        $comments = get_records('assignment_uploadpdf_comment', 'assignment_submission', $submissionid, 'pageno');
        $annotations = get_records('assignment_uploadpdf_annot', 'assignment_submission', $submissionid, 'pageno');

        if ($comments) { $comment = current($comments); } else { $comment = false; }
        if ($annotations) { $annotation = current($annotations); } else { $annotation = false; }
        while(true) {
            if ($comment) {
                $nextpage = $comment->pageno;
                if ($annotation) {
                    if ($annotation->pageno < $nextpage) {
                        $nextpage = $annotation->pageno;
                    }
                }
            } else {
                if ($annotation) {
                    $nextpage = $annotation->pageno;
                } else {
                    break;
                }
            }

            while ($nextpage > $mypdf->current_page()) {
                if (!$mypdf->copy_page()) {
                    error('Ran out of pages - this should not happen! - comment.pageno = '.$comment->pageno.'; currrentpage = '.$mypdf->CurrentPage());
                    return false;
                }
            }

            while (($comment) && ($comment->pageno == $mypdf->current_page())) {
                $mypdf->add_comment($comment->rawtext, $comment->posx, $comment->posy, $comment->width, $comment->colour);
                $comment = next($comments);
            }

            while (($annotation) && ($annotation->pageno == $mypdf->current_page())) {
                $mypdf->add_annotation($annotation->startx, $annotation->starty, $annotation->endx, $annotation->endy, $annotation->colour);
                $annotation = next($annotations);
            }

        }
        
        $mypdf->copy_remaining_pages();
        $mypdf->save_pdf($destfile);
        
        return true;
    }

    function get_page_image($userid, $pageno, $submission) {
        global $CFG;

        $imagefolder = $CFG->dataroot.'/'.$this->file_area_name($userid).'/images';
        check_dir_exists($imagefolder, true, true);
        $pdffile = $CFG->dataroot.'/'.$this->file_area_name($userid).'/submission/submission.pdf'; // Check folder exists + file exists
        if (!file_exists($pdffile)) {
            error('Attempting to display image for non-existing submission');
        }

        $pdf = new MyPDFLib();
        $pdf->set_image_folder($imagefolder);
        $pagecount = $submission->numfiles >> 1; // Extract the pagecount from 'numfiles' (may be 0)
        $pagecount = $pdf->set_pdf($pdffile, $pagecount); // Only loads the PDF if the pagecount is unknown (0)

        if ($pageno > $pagecount) {
            return array(null, 0, 0, $pagecount);
        }

        if (!$imgname = $pdf->get_image($pageno)) {
            error(get_string('errorgenerateimage', 'assignment_uploadpdf'));
        }

        if (($submission->numfiles & 1) == 0) {
            $submission->numfiles = ($pagecount << 1) | 1; /* Use this as a flag that there are images to delete at some point */
            update_record('assignment_submissions', $submission);
        }

        $imageurl = $CFG->wwwroot.'/file.php?file=/'.$this->file_area_name($userid).'/images/'.$imgname;
        list($imgwidth, $imgheight, $imgtype, $imgattr) = getimagesize($CFG->dataroot.'/'.$this->file_area_name($userid).'/images/'.$imgname);

        return array($imageurl, $imgwidth, $imgheight, $pagecount);
    }

    function edit_comment_page($userid, $pageno) {
        global $CFG;

        require_capability('mod/assignment:grade', $this->context);

        if (!$user = get_record('user', 'id', $userid)) {
            error('No such user!');
        }

        if (!$submission = $this->get_submission($user->id)) {
            error('User has no submission to comment on!');
        }

        $showprevious = optional_param('showprevious', -1, PARAM_INT);
        
        if (optional_param('topframe', false, PARAM_INT)) {
            if ($showprevious != -1) {
                echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd">';
                echo '<html><head><title>'.get_string('feedback', 'assignment').':'.fullname($user, true).':'.format_string($this->assignment->name).'</title></head>';
                echo '<frameset cols="70%, 30%">';
                echo '<frame src="editcomment.php?';
                echo 'id='.$this->cm->id.'&amp;userid='.$userid.'&amp;pageno='.$pageno.'&amp;showprevious='.$showprevious;
                echo '">';
                echo '<frame src="editcomment.php?';
                echo 'a='.$showprevious.'&amp;userid='.$userid.'&amp;action=showprevious';
                echo '">';
                echo '</frameset></html>';
                die;
            }
        }

        $savedraft = optional_param('savedraft', null, PARAM_TEXT);
        $generateresponse = optional_param('generateresponse', null, PARAM_TEXT);

        if ($savedraft) {
            print_header(get_string('feedback', 'assignment').':'.format_string($this->assignment->name));
            print_heading(get_string('draftsaved', 'assignment_uploadpdf'));
            close_window();
            die;
        }

        if ($generateresponse) {
            if ($this->create_response_pdf($userid, $submission->id)) {
                print_header(get_string('feedback', 'assignment').':'.format_string($this->assignment->name));
                print_heading(get_string('responseok', 'assignment_uploadpdf'));
				require_once($CFG->dirroot.'/version.php');
				if ($version >= 2007101500) {
					require_once($CFG->libdir.'/gradelib.php');
				}
                print $this->update_main_listing($submission);
                close_window();
                die;
            } else {
                print_header(get_string('feedback', 'assignment').':'.format_string($this->assignment->name));
                error(get_string('responseproblem', 'assignment_uploadpdf'));
                close_window();
                die;
            }
        }

        list($imageurl, $imgwidth, $imgheight, $pagecount) = $this->get_page_image($userid, $pageno, $submission);

        //require_js($CFG->wwwroot.'/mod/assignment/type/uploadpdf/scripts/mootools-1.2.1-core-compressed.js');
        //require_js($CFG->wwwroot.'/mod/assignment/type/uploadpdf/scripts/mootools-1.2.1-more-compressed.js');
        require_js($CFG->wwwroot.'/mod/assignment/type/uploadpdf/scripts/mootools-1.2.4-core-yc.js');
        require_js($CFG->wwwroot.'/mod/assignment/type/uploadpdf/scripts/mootools-1.2.4.2-more-yc.js');
        require_js($CFG->wwwroot.'/mod/assignment/type/uploadpdf/scripts/raphael-min.js');
        require_js($CFG->wwwroot.'/mod/assignment/type/uploadpdf/scripts/contextmenu.js');
        require_js($CFG->wwwroot.'/mod/assignment/type/uploadpdf/scripts/annotate.js');
        
        print_header(get_string('feedback', 'assignment').':'.fullname($user, true).':'.format_string($this->assignment->name));

        echo '<div id="saveoptions"><form action="'.$CFG->wwwroot.'/mod/assignment/type/uploadpdf/editcomment.php" method="post" target="_top" >';
        echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
        echo '<input type="hidden" name="userid" value="'.$userid.'" />';
        echo '<input type="hidden" name="pageno" value="'.$pageno.'" />';
        echo '<input type="submit" name="savedraft" value="'.get_string('savedraft', 'assignment_uploadpdf').'" /> ';
        echo '<input type="submit" name="generateresponse" value="'.get_string('generateresponse', 'assignment_uploadpdf').'" /> ';
		$pdfurl = $CFG->wwwroot.'/file.php?file=/'.$this->file_area_name($userid).'/submission/submission.pdf';
		echo '<a href="'.$pdfurl.'" target="_blank">'.get_string('downloadoriginal', 'assignment_uploadpdf').'</a>';
        echo '</form>';
		echo '</div>';

        if (!$CFG->uploadpdf_js_navigation) {
            $pageselector = '<div style="float: left; margin-top: 5px; margin-right: 10px;" class="pageselector">';

            if ($pageno > 1) {
                $pageselector .= '<a href="editcomment.php?id='.$this->cm->id.'&amp;userid='.$userid.'&amp;pageno='. ($pageno-1) .'&amp;showprevious='.$showprevious.'" accesskey="p">&lt;--'.get_string('previous','assignment_uploadpdf').'</a> ';
            } else {
                $pageselector .= '&lt;--'.get_string('previous','assignment_uploadpdf').' ';
            }

            for ($i=1; $i<=$pagecount; $i++) {
                if ($i == $pageno) {
                    $pageselector .= "$i ";
                } else {
                    $pageselector .= '<a href="editcomment.php?id='.$this->cm->id.'&amp;userid='.$userid.'&amp;pageno='.$i.'&amp;showprevious='.$showprevious.'">'.$i.'</a> ';
                }
                if (($i % 20) == 0) {
                    $pageselector .= '<br />';
                }
            }
       
            if ($pageno < $pagecount) {
                $pageselector .= '<a href="editcomment.php?id='.$this->cm->id.'&amp;userid='.$userid.'&amp;pageno='. ($pageno+1) .'&amp;showprevious='.$showprevious.'" accesskey="n">'.get_string('next','assignment_uploadpdf').'--&gt;</a>';
            } else {
                $pageselector .= get_string('next','assignment_uploadpdf').'--&gt;';
            }
        } else {

            $pageselector = '<div style="float: left; margin-top: 5px; margin-right: 10px; padding: 10px 0px;" class="pageselector">';
            $disabled = ($pageno == 1) ? ' disabled = "disabled" ' : '';
            $pageselector .= '<button id="prevpage" '.$disabled.'onClick="gotoprevpage();">&lt;--'.get_string('previous','assignment_uploadpdf').'</button>';

            $pageselector .= '<select name="selectpage" id="selectpage" onChange="selectpage();">';
            for ($i=1; $i<=$pagecount; $i++) {
                if ($i == $pageno) {
                    $pageselector .= "<option value='$i' selected='selected'>$i</option>";
                } else {
                    $pageselector .= "<option value='$i'>$i</option>";
                }
            }
            $pageselector .= '</select>';
        
            $disabled = ($pageno == $pagecount) ? ' disabled = "disabled" ' : '';
            $pageselector .= '<button id="nextpage" '.$disabled.'onClick="gotonextpage();">'.get_string('next','assignment_uploadpdf').'--&gt;</button>';
        }
		$pageselector .= '</div>';
        
        echo $pageselector;
        echo '<div id="colourselector">';
        // Show previous assignment
        $ps_sql = 'SELECT asn.id, asn.name FROM `'.$CFG->prefix.'assignment` AS asn ';
        $ps_sql .= 'INNER JOIN `'.$CFG->prefix.'assignment_submissions` AS sub ON sub.assignment = asn.id ';
        $ps_sql .= 'WHERE course = '.$this->course->id;
        $ps_sql .= ' AND asn.assignmenttype = "uploadpdf" ';
        $ps_sql .= ' AND userid = '.$userid;
        $ps_sql .= ' AND asn.id != '.$this->assignment->id;
        $ps_sql .= ' ORDER BY sub.timemodified DESC;';
        $previoussubs = get_records_sql($ps_sql);
        if ($previoussubs) {
            echo '<form name="showprevious" target="_top" action="editcomment.php" method="get">';
            echo ' '.get_string('showpreviousassignment','assignment_uploadpdf').': ';
            echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
            echo '<input type="hidden" name="userid" value="'.$userid.'" />';
            echo '<input type="hidden" name="pageno" value="'.$pageno.'" />';
            echo '<input type="hidden" name="topframe" value="1" />';
            echo '<select id="showprevious" name="showprevious" onChange="this.form.submit();">';
            echo '<option value="-1">'.get_string('previousnone','assignment_uploadpdf').'</option>';
            foreach ($previoussubs as $prevsub) {
                echo '<option value="'.$prevsub->id.'"';
                if ($showprevious == $prevsub->id) echo ' selected="selected" ';
                echo '>'.s($prevsub->name).'</option>';
            }
            echo '</select>';
            echo '<noscript><input type="submit" name="showpreviouspress" value="'.get_string('showprevious','assignment_uploadpdf').'" /></noscript>';
            echo '</form>';
        }
        // Choose comment colour
        echo ' '.get_string('commentcolour','assignment_uploadpdf').': ';
		echo '<select id="choosecolour" name="choosecolour">';
		echo '<option value="red">'.get_string('colourred','assignment_uploadpdf').'</option>';
		echo '<option value="yellow" selected="selected">'.get_string('colouryellow','assignment_uploadpdf').'</option>';
		echo '<option value="green">'.get_string('colourgreen','assignment_uploadpdf').'</option>';
		echo '<option value="blue">'.get_string('colourblue','assignment_uploadpdf').'</option>';
		echo '<option value="white">'.get_string('colourwhite','assignment_uploadpdf').'</option>';
		echo '<option value="clear">'.get_string('colourclear','assignment_uploadpdf').'</option>';
		echo '</select></div>';

        // Output the page image
        echo '<div id="pdfsize" style="clear: both; width:'.$imgwidth.'px; height:'.$imgheight.'px; ">';
        echo '<div id="pdfouter" style="position: relative; "> <div id="pdfholder" > ';
        echo '<img id="pdfimg" src="'.$imageurl.'" width="'.$imgwidth.'" height="'.$imgheight.'" />';
        echo '</div></div></div>';
        if ($CFG->uploadpdf_js_navigation) {
            $pageselector = str_replace(array('selectpage','"nextpage"','"prevpage"'),array('selectpage2','"nextpage2"','"prevpage2"'),$pageselector);
        }
        echo $pageselector;
        if ($CFG->uploadpdf_js_navigation) {
            echo '<p><a id="opennewwindow" target="_blank" href="editcomment.php?id='.$this->cm->id.'&amp;userid='.$userid.'&amp;pageno='. $pageno .'&amp;showprevious='.$showprevious.'">'.get_string('opennewwindow','assignment_uploadpdf').'</a></p>';
        }
        echo '<br style="clear:both;" />';

        // Definitions for the right-click menus
        echo '<ul class="contextmenu" style="display: none;" id="context-quicklist"><li class="separator">'.get_string('quicklist','assignment_uploadpdf').'</li></ul>';
        echo '<ul class="contextmenu" style="display: none;" id="context-comment"><li><a href="#addtoquicklist">'.get_string('addquicklist','assignment_uploadpdf').'</a></li>';
        echo '<li class="separator"><a href="#red">'.get_string('colourred','assignment_uploadpdf').'</a></li>';
        echo '<li><a href="#yellow">'.get_string('colouryellow','assignment_uploadpdf').'</a></li>';
        echo '<li><a href="#green">'.get_string('colourgreen','assignment_uploadpdf').'</a></li>';
        echo '<li><a href="#blue">'.get_string('colourblue','assignment_uploadpdf').'</a></li>';
        echo '<li><a href="#white">'.get_string('colourwhite','assignment_uploadpdf').'</a></li>';
        echo '<li><a href="#clear">'.get_string('colourclear','assignment_uploadpdf').'</a></li>';
        echo '<li class="separator"><a href="#deletecomment">'.get_string('deletecomment','assignment_uploadpdf').'</a></li>';
        echo '</ul>';
        // Definition for 'resend' box
        echo '<div id="sendfailed" style="display: none;"><p>'.get_string('servercommfailed','assignment_uploadpdf').'</p><button id="sendagain">'.get_string('resend','assignment_uploadpdf').'</button><button onClick="hidesendfailed();">'.get_string('cancel','assignment_uploadpdf').'</button></div>';

        $server = array(
                        'id' => $this->cm->id,
                        'userid' => $userid,
                        'pageno' => $pageno,
                        'sesskey' => sesskey(),
                        'updatepage' => $CFG->wwwroot.'/mod/assignment/type/uploadpdf/updatecomment.php',
                        'lang_servercommfailed' => get_string('servercommfailed', 'assignment_uploadpdf'),
                        'lang_errormessage' => get_string('errormessage', 'assignment_uploadpdf'),
                        'lang_okagain' => get_string('okagain', 'assignment_uploadpdf'),
                        'lang_emptyquicklist' => get_string('emptyquicklist', 'assignment_uploadpdf'),
                        'lang_emptyquicklist_instructions' => get_string('emptyquicklist_instructions', 'assignment_uploadpdf'),
                        'deleteicon' => $CFG->pixpath . '/t/delete.gif',
                        'pagecount' => $pagecount,
                        'js_navigation' => $CFG->uploadpdf_js_navigation,
                        'blank_image' => $CFG->wwwroot.'/mod/assignment/type/uploadpdf/style/blank.gif'
                        );
        
        //        print_js_config($server, 'server_config'); // Not in Moodle 1.8
        echo '<script type="text/javascript">server_config = {';
        foreach ($server as $key => $value) {
            echo $key.": '$value', \n";
        }
        echo "ignore: ''\n"; // Just there so IE does not complain
        echo '};</script>';

        print_footer('none');
    }

    // Respond to AJAX requests whilst teacher is editing comments
    function update_comment_page($userid, $pageno) {
        global $USER;
        
        $resp = array('error'=> ASSIGNMENT_UPLOADPDF_ERR_NONE);
        require_capability('mod/assignment:grade', $this->context);

        if (!$user = get_record('user', 'id', $userid)) {
            send_error('No such user!');
        }
        
        if (!$submission = $this->get_submission($user->id)) {
            send_error('User has no submission to comment on!');
        }

        $action = optional_param('action','', PARAM_ALPHA);

        if ($action == 'update') {
            $comment = new Object();
            $comment->id = optional_param('comment_id', -1, PARAM_INT);
            $comment->posx = optional_param('comment_position_x', -1, PARAM_INT);
            $comment->posy = optional_param('comment_position_y', -1, PARAM_INT);
            $comment->width = optional_param('comment_width', -1, PARAM_INT);
            $comment->rawtext = optional_param('comment_text', null, PARAM_TEXT);
			$comment->colour = optional_param('comment_colour', 'yellow', PARAM_TEXT);
            $comment->pageno = $pageno;

            if (($comment->posx < 0) || ($comment->posy < 0) || ($comment->width < 0) || ($comment->rawtext === null)) {
                send_error('Missing comment data');
            }
            
            if ($comment->id === -1) {
                unset($comment->id);
                $comment->assignment_submission = $submission->id;
                $comment->id = insert_record('assignment_uploadpdf_comment', $comment);
            } else {
                $oldcomment = get_record('assignment_uploadpdf_comment', 'id', $comment->id);
                if (!$oldcomment) {
                    unset($comment->id);
                    $comment->id = insert_record('assignment_uploadpdf_comment', $comment);
                } else if (($oldcomment->assignment_submission != $submission->id) || ($oldcomment->pageno != $pageno)) {
                    send_error('Comment id is for a different submission or page');
                } else {
                    update_record('assignment_uploadpdf_comment', $comment);
                }
            }

            $resp['id'] = $comment->id;
               
        } elseif ($action == 'getcomments') {
            $comments = get_records_select('assignment_uploadpdf_comment', 'assignment_submission='.$submission->id.' AND pageno='.$pageno);
            $respcomments = array();
            if ($comments) {
                foreach ($comments as $comment) {
                    $respcomment = array();
                    $respcomment['id'] = ''.$comment->id;
                    $respcomment['text'] = $comment->rawtext;
                    $respcomment['width'] = $comment->width;
                    $respcomment['position'] = array('x'=> $comment->posx, 'y'=> $comment->posy);
					$respcomment['colour'] = $comment->colour;
                    $respcomments[] = $respcomment;
                }
            }
            $resp['comments'] = $respcomments;

            $annotations = get_records_select('assignment_uploadpdf_annot', 'assignment_submission='.$submission->id.' AND pageno='.$pageno);
            $respannotations = array();
            if ($annotations) {
                foreach ($annotations as $annotation) {
                    $respannotation = array();
                    $respannotation['id'] = ''.$annotation->id;
                    $respannotation['type'] = $annotation->type;
                    $respannotation['coords'] = array('startx'=> $annotation->startx, 'starty'=> $annotation->starty, 'endx'=> $annotation->endx, 'endy'=> $annotation->endy );
					$respannotation['colour'] = $annotation->colour;
                    $respannotations[] = $respannotation;
                }
            }
            $resp['annotations'] = $respannotations;

        } elseif ($action == 'delete') {
            $commentid = optional_param('commentid', -1, PARAM_INT);
            if ($commentid < 0) {
                send_error('No comment id provided');
            }
            $oldcomment = get_record('assignment_uploadpdf_comment', 'id', $commentid, 'assignment_submission', $submission->id, 'pageno', $pageno);
            if (!($oldcomment)) {
                send_error('Could not find a comment with that id on this page');
            } else {
                delete_records('assignment_uploadpdf_comment', 'id', $commentid);
            }
            
        } elseif ($action == 'getquicklist') {

            $quicklist = get_records('assignment_uploadpdf_qcklist', 'userid', $USER->id, 'id');
            $respquicklist = array();
            if ($quicklist) {
                foreach ($quicklist as $item) {
                    $respitem = array();
                    $respitem['id'] = ''.$item->id;
                    $respitem['text'] = $item->text;
                    $respitem['width'] = $item->width;
                    $respitem['colour'] = $item->colour;
                    $respquicklist[] = $respitem;
                }
            }
            $resp['quicklist'] = $respquicklist;

        } elseif ($action == 'addtoquicklist') {

            $item = new Object();
            $item->userid = $USER->id;
            $item->width = optional_param('width', -1, PARAM_INT);
            $item->text = optional_param('text', null, PARAM_TEXT);
			$item->colour = optional_param('colour', 'yellow', PARAM_TEXT);

            if (($item->width < 0) || ($item->text === null)) {
                send_error('Missing quicklist data');
            }

            $item->id = insert_record('assignment_uploadpdf_qcklist', $item);

            $resp['item'] = $item;

        } elseif ($action == 'removefromquicklist') {

            $itemid = optional_param('itemid', -1, PARAM_INT);
            if ($itemid < 0) {
                send_error('No quicklist id provided');
            }

            $olditem = get_record('assignment_uploadpdf_qcklist', 'id', $itemid, 'userid', $USER->id);
            if (!($olditem)) {
                send_error('Could not find a quicklist item with that id on this page');
            } else {
                delete_records('assignment_uploadpdf_qcklist', 'id', $itemid);
            }

            $resp['itemid'] = $itemid;

        } elseif ($action == 'getimageurl') {

            if ($pageno < 1) {
                send_error('Requested page number is too small (< 1)');
            }

            list($imageurl, $imgwidth, $imgheight, $pagecount) = $this->get_page_image($userid, $pageno, $submission);

            if ($pageno > $pagecount) {
                send_error('Requested page number is bigger than the page count ('.$pageno.' > '.$pagecount.')');
            }   

            $resp['image'] = new Object();
            $resp['image']->url = $imageurl;
            $resp['image']->width = $imgwidth;
            $resp['image']->height = $imgheight;

        } elseif ($action == 'addannotation') {

            $annotation = new Object();
            $annotation->startx = optional_param('annotation_startx', -1, PARAM_INT);
            $annotation->starty = optional_param('annotation_starty', -1, PARAM_INT);
            $annotation->endx = optional_param('annotation_endx', -1, PARAM_INT);
            $annotation->endy = optional_param('annotation_endy', -1, PARAM_INT);
            $annotation->colour = optional_param('annotation_colour', 'red', PARAM_TEXT);
            $annotation->type = optional_param('annotation_type', 'line', PARAM_TEXT);
            $annotation->pageno = $pageno;
            $annotation->assignment_submission = $submission->id;

            if (($annotation->startx < 0) || ($annotation->starty < 0) || ($annotation->endx < 0) || ($annotation->endy < 0)) {
                send_error('Missing annotation data');
            }
            $annotation->id = insert_record('assignment_uploadpdf_annot', $annotation);

            $resp['id'] = $annotation->id;

        } elseif ($action == 'removeannotation') {
            $annotationid = optional_param('annotationid', -1, PARAM_INT);
            if ($annotationid < 0) {
                send_error('No annotation id provided');
            }
            $oldannotation = get_record('assignment_uploadpdf_annot', 'id', $annotationid, 'assignment_submission', $submission->id, 'pageno', $pageno);
            if (!($oldannotation)) {
                send_error('Could not find a annotation with that id on this page');
            } else {
                delete_records('assignment_uploadpdf_annot', 'id', $annotationid);
            }
            
        } else {
            send_error('Invalid action "'.$action.'"', ASSIGNMENT_UPLOADPDF_ERR_INVALID_ACTION);
        }

        echo json_encode($resp);
    }

    function show_previous_comments($userid) {
        global $CFG;
        
        require_capability('mod/assignment:grade', $this->context);

        if (!$user = get_record('user', 'id', $userid)) {
            error('No such user!');
        }

        if (!$submission = $this->get_submission($user->id)) {
            error('User has no previous submission to display!');
        }
        
        print_header(get_string('feedback', 'assignment').':'.fullname($user, true).':'.format_string($this->assignment->name));

        echo '<h2>'.format_string($this->assignment->name).'</h2>';

        // Add download link for submission
        $pdfurl = $CFG->wwwroot.'/file.php?file=/'.$this->file_area_name($userid).'/submission/submission.pdf';
        if (file_exists($CFG->dataroot.'/'.$this->file_area_name($userid).'/responses/response.pdf')) {
            $pdfurl = $CFG->wwwroot.'/file.php?file=/'.$this->file_area_name($userid).'/responses/response.pdf';
        }
        echo '<a href="'.$pdfurl.'" target="_blank">'.get_string('downloadoriginal', 'assignment_uploadpdf').'</a><br />';


        // Put all the comments in a table
        $comments = get_records('assignment_uploadpdf_comment', 'assignment_submission', $submission->id, 'pageno, posy');
        if (!$comments) {
            echo '<p>'.get_string('nocomments','assignment_uploadpdf').'</p>';
        } else {
            $style1 = ' style="border: black 1px solid;"';
            $style2 = ' style="border: black 1px solid; text-align: center;" ';
            echo '<table'.$style1.'><tr><th'.$style1.'>'.get_string('pagenumber','assignment_uploadpdf').'</th>';
            echo '<th'.$style1.'>'.get_string('comment','assignment_uploadpdf').'</th></tr>';
            foreach ($comments as $comment) {
                $linkurl = '/mod/assignment/type/uploadpdf/editcomment.php?a='.$this->assignment->id.'&amp;userid='.$user->id.'&amp;pageno='.$comment->pageno.'&amp;commentid='.$comment->id.'&amp;action=showpreviouspage';
                $link = link_to_popup_window($linkurl, 'showpage'.$userid, $comment->pageno,
                                             700, 700, fullname($user, true).':'.format_string($this->assignment->name).':'.$comment->pageno, null, true);
                echo '<tr><td'.$style2.'>'.$link.'</td>';
                echo '<td'.$style1.'>'.s($comment->rawtext).'</td></tr>';
            }
            echo '</table>';
        }
        print_footer('none');
    }

    function show_previous_page($userid, $pageno) {
        global $CFG;
        
        require_capability('mod/assignment:grade', $this->context);

        if (!$user = get_record('user', 'id', $userid)) {
            error('No such user!');
        }

        if (!$submission = $this->get_submission($user->id)) {
            error('User has no previous submission to display!');
        }
        
        list($imageurl, $imgwidth, $imgheight, $pagecount) = $this->get_page_image($userid, $pageno, $submission);

        print_header(fullname($user, true).':'.format_string($this->assignment->name).':'.$pageno);

        // Nasty hack to insert the style-sheet needed for my comment boxes
        // (without having to rewrite every Moodle theme in existence)
        echo '<script type="text/javascript">';
        echo 'var fileref = document.createElement("link"); fileref.setAttribute("rel", "stylesheet"); fileref.setAttribute("type", "text/css");';
        echo 'fileref.setAttribute("href", "style/annotate.css");';
        echo 'if (typeof fileref!="undefined") document.getElementsByTagName("head")[0].appendChild(fileref);';
        echo '</script>';

        echo '<div style="clear: both; width:'.$imgwidth.'px; height:'.$imgheight.'px; ">';
        echo '<div id="pdfouter" style="position: relative; "> <div id="pdfholder" > ';
        echo '<img id="pdfimg" src="'.$imageurl.'" />';

        // Insert comment boxes
        $comments = get_records_select('assignment_uploadpdf_comment', 'assignment_submission='.$submission->id.' AND pageno='.$pageno);
        if ($comments) {
            foreach ($comments as $comment) {
                $displaytext = s($comment->rawtext);
                $displaytext = str_replace("\n",'<br />',$displaytext);
                echo '<div class="comment comment'.$comment->colour.'" style="position: absolute; ';
                echo 'left: '.$comment->posx.'px; top: '.$comment->posy.'px; width: '.$comment->width.'px;">';
                echo $displaytext.'</div>';
            }
        }

        echo '</div></div></div>';

        print_footer('none');
    }

    function setup_elements(&$mform) {
        global $CFG, $COURSE;

        $ynoptions = array( 0 => get_string('no'), 1 => get_string('yes'));

        $courseid = 0;
        $assignment_extra = false;
        $update = optional_param('update', 0, PARAM_INT);
        $add = optional_param('add', 0, PARAM_ALPHA);
        if (!empty($update)) {
            if (! $cm = get_record("course_modules", "id", $update)) {
                error("This course module doesn't exist");
            }
            $courseid = $cm->course;
            $assignment_extra = get_record('assignment_uploadpdf', 'assignment', $cm->instance);
        } elseif (!empty($add)) {
            $courseid = required_param('course', PARAM_INT);
        }

        if (!$assignment_extra) {
            $assignment_extra = new Object;
            $assignment_extra->template = 0;
            $assignment_extra->coversheet = '';
            $assignment_extra->onlypdf = 1;
        }

        $mform->addElement('choosecoursefile', 'coversheet', get_string('coversheet','assignment_uploadpdf'));
        $mform->addRule('coversheet', get_string('coversheetnotpdf','assignment_uploadpdf'), 'callback', 'check_coversheet_pdf');
        $mform->setDefault('coversheet', $assignment_extra->coversheet);
        $mform->setHelpButton('coversheet', array('coversheet', get_string('coversheet','assignment_uploadpdf'), 'assignment_uploadpdf'));

        $templates = array();
        $templates[0] = get_string('notemplate','assignment_uploadpdf');
        $templates_data = get_records_sql("SELECT id, name FROM {$CFG->prefix}assignment_uploadpdf_tmpl WHERE course = 0 OR course = {$courseid}");
        if ($templates_data) {
            foreach ($templates_data as $td) {
                $templates[$td->id] = $td->name;
            }
        }

        $mform->addElement('select', 'template', get_string('coversheettemplate','assignment_uploadpdf'), $templates);
        $mform->setDefault('template', $assignment_extra->template);
        $mform->setHelpButton('template', array('coversheettemplate', get_string('coversheettemplate','assignment_uploadpdf'), 'assignment_uploadpdf'));
        
        $edittemplate = $mform->addElement('button', 'edittemplate', get_string('edittemplate', 'assignment_uploadpdf').'...');
        $buttonattributes = array('title'=>get_string('edittemplatetip', 'assignment_uploadpdf'), 'onclick'=>"return window.open('$CFG->wwwroot/mod/assignment/type/uploadpdf/edittemplates.php?courseid=$courseid', 'edittemplates', 'menubar=0,location=0,directories=0,toolbar=0,scrollbars,resizable,width=800,height=600');");
        $edittemplate->updateAttributes($buttonattributes);

        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes);
        $choices[1] = get_string('uploadnotallowed');
        $choices[0] = get_string('courseuploadlimit') . ' ('.display_size($COURSE->maxbytes).')';
        $mform->addElement('select', 'maxbytes', get_string('maximumsize', 'assignment'), $choices);
        $mform->setDefault('maxbytes', $CFG->assignment_maxbytes);

        $mform->addElement('select', 'onlypdf', get_string('onlypdf', 'assignment_uploadpdf'), $ynoptions);
        $mform->setDefault('onlypdf', $assignment_extra->onlypdf);
        $mform->setHelpButton('onlypdf', array('onlypdf', get_string('onlypdf','assignment_uploadpdf'), 'assignment_uploadpdf'));

        $mform->addElement('select', 'resubmit', get_string("allowdeleting", "assignment"), $ynoptions);
        $mform->setHelpButton('resubmit', array('allowdeleting', get_string('allowdeleting', 'assignment'), 'assignment'));
        $mform->setDefault('resubmit', 1);

        $options = array();
        for($i = 1; $i <= 20; $i++) {
            $options[$i] = $i;
        }
        $mform->addElement('select', 'var1', get_string("allowmaxfiles", "assignment"), $options);
        $mform->setHelpButton('var1', array('allowmaxfiles', get_string('allowmaxfiles', 'assignment'), 'assignment'));
        $mform->setDefault('var1', 3);

        $mform->addElement('select', 'var2', get_string("allownotes", "assignment"), $ynoptions);
        $mform->setHelpButton('var2', array('allownotes', get_string('allownotes', 'assignment'), 'assignment'));
        $mform->setDefault('var2', 0);

        $mform->addElement('select', 'var3', get_string("hideintro", "assignment"), $ynoptions);
        $mform->setHelpButton('var3', array('hideintro', get_string('hideintro', 'assignment'), 'assignment'));
        $mform->setDefault('var3', 0);

        $mform->addElement('select', 'emailteachers', get_string("emailteachers", "assignment"), $ynoptions);
        $mform->setHelpButton('emailteachers', array('emailteachers', get_string('emailteachers', 'assignment'), 'assignment'));
        $mform->setDefault('emailteachers', 0);
    }

    function add_instance($assignment) {
        $assignment_extra = new Object();
        $assignment_extra->coversheet = $assignment->coversheet; // FIXME: This should be sanitised and checked it is a PDF
        $assignment_extra->template = $assignment->template;
        $assignment_extra->onlypdf = $assignment->onlypdf;
        unset($assignment->coversheet);
        unset($assignment->template);
        unset($assignment->onlypdf);

        $newid = parent::add_instance($assignment);

        if ($newid) {
            $assignment_extra->assignment = $newid;
            insert_record('assignment_uploadpdf', $assignment_extra);
        }

        return $newid;
    }

    function delete_instance($assignment) {
        global $CFG;
        $result = true;

        if (! delete_records_select('assignment_uploadpdf_comment',
                  'assignment_submission IN (
                     SELECT s.id
                     FROM ' . $CFG->prefix . 'assignment_submissions AS s
                     WHERE s.assignment = ' . $assignment->id . '
                   )'
                                    )) {
            $result = false;
        }
        
        if (! delete_records('assignment_uploadpdf', 'assignment', $assignment->id)) {
            $result = false;
        }
        
        $retval = parent::delete_instance($assignment);
        
        return $retval && $result;
    }

    function update_instance($assignment) {
        $coversheet = $assignment->coversheet; // FIXME - this should be sanitised and checked that it is a PDF
        $template = $assignment->template;
        $onlypdf = $assignment->onlypdf;
        unset($assignment->coversheet);
        unset($assignment->template);
        unset($assignment->onlypdf);

        $retval = parent::update_instance($assignment);
        
        if ($retval) {
            $assignmentid = $assignment->id;
            $assignment_extra = get_record('assignment_uploadpdf', 'assignment', $assignmentid);
            if ($assignment_extra) {
                $assignment_extra->coversheet = $coversheet;
                $assignment_extra->template = $template;
                $assignment_extra->onlypdf = $onlypdf;
                update_record('assignment_uploadpdf', $assignment_extra);
            } else {
                // This shouldn't happen (unless an old development version of this plugin has already been used)
                $assignment_extra = new Object;
                $assignment_extra->assignment = $assignmentid;
                $assignment_extra->coversheet = $coversheet;
                $assignment_extra->template = $template;
                $assignment_extra->onlypdf = $onlypdf;
                insert_record('assignment_uploadpdf', $assignment_extra);
            }
        }

        return $retval;
    }

    function cron() {
        global $CFG;

        if ($lastcron = get_config('uploadpdf','lastcron')) {
            if ($lastcron + 86400 > time()) { /* Only check once a day for images */
                return;
            }
        }

        echo "Clear up images generated for uploadpdf assignments\n";

        // Find all the old images and delete them
        $to_clear = get_records_sql("SELECT sub.*, ass.course FROM {$CFG->prefix}assignment_submissions AS sub, {$CFG->prefix}assignment AS ass ".
                                    'WHERE ass.assignmenttype = "uploadpdf" '.
                                    'AND sub.assignment = ass.id AND ((sub.numfiles & 1) = 1);');
        if ($to_clear) {
            foreach ($to_clear as $submission) {
                echo 'Checking images for uploadpdf submission: '.$submission->id;

                $filescleared = true;
                // This should be the same as the return value of 'file_area_name'
                $file_area_name = $submission->course.'/'.$CFG->moddata.'/assignment/'.$submission->assignment.'/'.$submission->userid;
                $imagefolder = $CFG->dataroot.'/'.$file_area_name.'/images';

                if (is_dir($imagefolder)) {
                    if ($files = get_directory_list($imagefolder)) {
                        if ($files) {
                            foreach ($files as $key=>$fl) {
                                if ((substr($fl,0,10) == 'image_page') && (substr($fl,-4) == '.png')) {
                                    $age = time() - filemtime($imagefolder.'/'.$fl);
                                    if ($age > 86400) { /* only clear images more than 24 hours old */
                                        unlink($imagefolder.'/'.$fl);
                                    } else {
                                        $filescleared = false;
                                    }
                                }
                            }
                        }
                    }
                }

                // Mark submissions as having files cleared (if all of them are gone)
                if ($filescleared) {
                    echo ' cleared.';
                    $submission->numfiles &= ~1; // Clear flag in final bit
                    update_record('assignment_submissions', $submission);
                } else {
                    echo ' too recent - not cleared.';
                }
                echo "\n";
            }
        }

        // Delete any template images
        // I am going to skip doing this, on the grounds that this could take a lot of searching
        // (assuming an install has lots of courses) and that a few 100kb image files (max 1 per
        // course) are unlikely to cause a problem.

        $lastcron = time(); // Remember when the last cron job ran
        set_config('lastcron', $lastcron, 'uploadpdf');
    }

    /**
     * Backup extra data about the assignment.
     * This includes extra settings, and the template and tempate items used by it.
    */
    function backup_one_mod($bf, $preferences, $assignment) {
        if (!$extras = get_record('assignment_uploadpdf', 'assignment', $assignment->id)) {
            debugging("something wrong in assignment/uploadpdf backup_one_mod - couldn't find extra data");
            return false;
        }
        if ($extras->template != 0 && !$template = get_record('assignment_uploadpdf_tmpl', 'id', $extras->template)) {
            debugging("something wrong in assignment/uploadpdf backup_one_mod - couldn't find template data");
            return false;
        }
        fwrite ($bf,full_tag("COVERSHEET",4,false,$extras->coversheet));
        fwrite ($bf,full_tag("TEMPLATEID",4,false,$extras->template));
        fwrite ($bf,full_tag("ONLYPDF",4,false,$extras->onlypdf));
        if ($template) {
            if (!$items = get_records('assignment_uploadpdf_tmplitm', 'template', $template->id)) {
                $items = array();
            }
            fwrite ($bf,start_tag("TEMPLATE",4,true));
            fwrite ($bf,full_tag("ID",5,false,$template->id));
            fwrite ($bf,full_tag("NAME",5,false,$template->name));
            fwrite ($bf,start_tag("TEMPLATEITEMS",5,true));
            foreach ($items as $item) {
                fwrite ($bf,start_tag("TEMPLATEITEM",6,true));
                fwrite ($bf,full_tag("TYPE",6,false,$item->type));
                fwrite ($bf,full_tag("XPOS",6,false,$item->xpos));
                fwrite ($bf,full_tag("YPOS",6,false,$item->ypos));
                fwrite ($bf,full_tag("WIDTH",6,false,$item->width));
                fwrite ($bf,full_tag("SETTING",6,false,$item->setting));
                fwrite ($bf,end_tag("TEMPLATEITEM",6,true));
            }
            fwrite ($bf,end_tag("TEMPLATEITEMS",5,true));
            fwrite ($bf,end_tag("TEMPLATE",4,true));
        }
    }

    /**
     *Backup extra data about the assignment submission. This is just comments at the moment.
     */
    function backup_one_submission($bf, $preferences, $assignment, $submission) {
        if (!$comments = get_records('assignment_uploadpdf_comment', 'assignment_submission', $submission->id)) {
            return true;
        }
        fwrite ($bf,start_tag("COMMENTS",4,true));
        foreach ($comments as $comment) {
            fwrite ($bf,start_tag("COMMENT",5,true));
            fwrite ($bf,full_tag("POSX",6,false,$comment->posx));
            fwrite ($bf,full_tag("POSY",6,false,$comment->posy));
            fwrite ($bf,full_tag("WIDTH",6,false,$comment->width));
            fwrite ($bf,full_tag("RAWTEXT",6,false,$comment->rawtext));
            fwrite ($bf,full_tag("PAGENO",6,false,$comment->pageno));
            fwrite ($bf,full_tag("COLOUR",6,false,$comment->colour));
            fwrite ($bf,end_tag("COMMENT",5,true));
        }
        fwrite ($bf,end_tag("COMMENTS",4,true));
    }

    function restore_one_mod($info, $restore, $assignment) {
        // template first, since other things reference it.
        $templateid = 0;
        if (@isset($info['MOD']['#']['TEMPLATE']['0']['#'])) {
            $oldid = backup_todb($info['MOD']['#']['TEMPLATE']['0']['#']['ID']['0']['#']);
            // first check to see if we haven't already restored this template for another module...
            if (!$templateid = backup_getid($restore->backup_unique_code, 'assignment_uploadpdf_tmpl', $oldid)) {
                $template = new stdclass;
                $template->name = backup_todb($info['MOD']['#']['TEMPLATE']['0']['#']['NAME']['0']['#']);
                $template->course = $restore->course_id;

                $templateid = insert_record('assignment_uploadpdf_tmpl', $template);


                // now templateitems
                if (@isset($info['MOD']['#']['TEMPLATE']['0']['#']['TEMPLATEITEMS']['0']['#']['TEMPLATEITEM'])) {
                    $items = $info['MOD']['#']['TEMPLATE']['0']['#']['TEMPLATEITEMS']['0']['#']['TEMPLATEITEM'];
                } else {
                    $items = array();
                }
                foreach ($items as $item) {
                    $ti = new stdclass;
                    $ti->template = $templateid;
                    $ti->type = backup_todb($item['#']['TYPE']['0']['#']);
                    $ti->posx = backup_todb($item['#']['XPOS']['0']['#']);
                    $ti->posy = backup_todb($item['#']['YPOS']['0']['#']);
                    $ti->width = backup_todb($item['#']['WIDTH']['0']['#']);
                    $ti->setting = backup_todb($item['#']['SETTING']['0']['#']);

                    insert_record('assignment_uploadpdf_tmplitm', $ti);
                }
                backup_putid($restore->backup_unique_code, 'assignment_uploadpdf_tmpl', $oldid, $templateid);
            } else {
                $templateid = $templateid->new_id;
            }
        }
        // finally, the extra settings
        $newassid = backup_getid($restore->backup_unique_code, 'assignment', backup_todb($info['MOD']['#']['ID']['0']['#']));
        $uploadpdf = new stdclass;
        $uploadpdf->assignment = $newassid->new_id;
        $uploadpdf->coversheet = backup_todb($info['MOD']['#']['COVERSHEET']['0']['#']);
        $uploadpdf->onlypdf = backup_todb($info['MOD']['#']['ONLYPDF']['0']['#']);
        $uploadpdf->template = $templateid;

        insert_record('assignment_uploadpdf', $uploadpdf);
    }

    function restore_one_submission($info, $restore, $assignment, $submission) {
        //Get the submissions array - it might not be present
        if (@isset($info['#']['COMMENTS']['0']['#']['COMMENT'])) {
            $comments = $info['#']['COMMENTS']['0']['#']['COMMENT'];
        } else {
            $comments = array();
        }
        foreach ($comments as $comment) {
            $dbc = new stdclass;
            $dbc->posx = backup_todb($comment['#']['POSX']['0']['#']);
            $dbc->posy = backup_todb($comment['#']['POSY']['0']['#']);
            $dbc->width = backup_todb($comment['#']['WIDTH']['0']['#']);
            $dbc->rawtext = backup_todb($comment['#']['RAWTEXT']['0']['#']);
            $dbc->pageno = backup_todb($comment['#']['PAGENO']['0']['#']);
            $dbc->colour = backup_todb($commentitei['#']['COLOUR']['0']['#']);
            $dbc->assignment_submission = $submission->id;
            insert_record('assignment_uploadpdf_comment', $dbc);
        }
    }
}

function reset_userdata($data) {
    global $CFG;
    if (!empty($data->reset_assignment_submissions)) {
        delete_records_select('assignment_uploadpdf_comment',
               'assignment_submission IN (
                   SELECT s.id
                   FROM ' . $CFG->prefix . 'assignment_submissions s
                   JOIN ' . $CFG->prefix . 'assignment a
                       ON s.assignment = a.id
                   WHERE a.course = ' . $data->courseid . '
               )'
        );
    }
    return parent::reset_userdata($data);
}

class mod_assignment_uploadpdf_notes_form extends moodleform {
    function definition() {
        $mform =& $this->_form;

        // visible elements
        $mform->addElement('htmleditor', 'text', get_string('notes', 'assignment'), array('cols'=>85, 'rows'=>30));
        $mform->setType('text', PARAM_RAW); // to be cleaned before display
        $mform->setHelpButton('text', array('reading', 'writing'), false, 'editorhelpbutton');

        // hidden params
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'savenotes');
        $mform->setType('id', PARAM_ALPHA);

        // buttons
        $this->add_action_buttons();
    }
}


function check_coversheet_pdf($value) {
    if ($value['value'] == '') {
        return true;
    }

    if (strtolower(substr($value['value'], -4)) == '.pdf') {
        return true;
    }
    
    return false;
}


?>
