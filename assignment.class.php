<?php

// Based on assignment_upload class from main Moodle repository

require_once($CFG->libdir.'/formslib.php');
require_once('mypdflib.php');
if (!class_exists('assignment_base')) {
    require_once($CFG->dirroot . '/mod/assignment/lib.php');
}

define('ASSIGNMENT_UPLOADPDF_STATUS_SUBMITTED', 'submitted');
define('ASSIGNMENT_UPLOADPDF_STATUS_RESPONDED', 'responded');

/**
 * Extend the base assignment class for assignments where you upload a single file
 *
 */
class assignment_uploadpdf extends assignment_base {

    function assignment_uploadpdf($cmid='staticonly', $assignment=NULL, $cm=NULL, $course=NULL) {
        parent::assignment_base($cmid, $assignment, $cm, $course);
        $this->type = 'uploadpdf';
    }

    function view() {
        global $USER, $OUTPUT, $DB, $CFG;

        require_capability('mod/assignment:view', $this->context);

        add_to_log($this->course->id, 'assignment', 'view', "view.php?id={$this->cm->id}", $this->assignment->id, $this->cm->id);

        $this->view_header();

        if ($this->assignment->timeavailable > time()
          and !has_capability('mod/assignment:grade', $this->context)      // grading user can see it anytime
          and $this->assignment->var3) {                                   // force hiding before available date
            echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
            print_string('notavailableyet', 'assignment');
            echo $OUTPUT->box_end();
        } else {
            $this->view_intro();
        }

        $this->view_dates();

        $coversheet_filename = false;
        $coversheet_url = false;

        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'mod_assignment', 'coversheet', false, '', false);

        if (!empty($files)) {
            $coversheet_filename = array_shift(array_values($files))->get_filename();
            $coversheet_url = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$this->context->id.'/mod_assignment/coversheet/0/'.$coversheet_filename);
        }

        if (has_capability('mod/assignment:submit', $this->context)) {
            $submission = $this->get_submission($USER->id);
            $filecount = $submission ? $this->count_user_files($submission->id) : 0;

            $this->view_feedback();

            $this->view_final_submission();

            if ($this->is_finalized($submission)) {
                echo $OUTPUT->heading(get_string('submission', 'assignment'), 3);
            } else {
                echo $OUTPUT->heading(get_string('submissiondraft', 'assignment'), 3);
                if ($coversheet_filename) {
                    echo '<p>'.get_string('coversheetnotice','assignment_uploadpdf').': ';
                    echo '<a href="'.$coversheet_url.'" target="_blank">'.$coversheet_filename.'</a></p>';
                }
            }

            if ($filecount and $submission) {
                echo $OUTPUT->box($this->print_user_files($USER->id, true), 'generalbox boxaligncenter', 'userfiles');
            } else {
                if ($this->is_finalized($submission)) {
                    echo $OUTPUT->box(get_string('nofiles', 'assignment'), 'generalbox boxaligncenter nofiles', 'userfiles');
                } else {
                    echo $OUTPUT->box(get_string('nofilesyet', 'assignment'), 'generalbox boxaligncenter nofiles', 'userfiles');
                }
            }

            $this->view_upload_form();

            if ($this->notes_allowed()) {
                echo $OUTPUT->heading(get_string('notes', 'assignment'), 3);
                $this->view_notes();
            }
        } else {
            if ($coversheet_filename) {
                echo '<p>'.get_string('coversheetnotice','assignment_uploadpdf').': ';
                echo '<a href="'.$coversheet_url.'" target="_blank">'.$coversheet_filename.'</a></p>';
            }
        }
        $this->view_footer();
    }

    function view_intro() {
        global $CFG, $USER, $OUTPUT, $DB;

        echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
        echo format_module_intro('assignment', $this->assignment, $this->cm->id);

        if ($this->import_checklist_plugin()) {
            $extra = $DB->get_record('assignment_uploadpdf', array('assignment' => $this->assignment->id) );
            if ($extra->checklist) {
                $checklist = $DB->get_record('checklist', array('id' => $extra->checklist) );
                if ($checklist) {
                    $chklink = $CFG->wwwroot.'/mod/checklist/view.php?checklist='.$checklist->id;
                    echo '<div><a href="'.$chklink.'" target="_blank"><div style="float: left; dispaly: inline; margin-left: 40px; margin-right: 20px;">'.$checklist->name.': </div>';
                    checklist_class::print_user_progressbar($checklist->id, $USER->id);
                    echo '</a></div>';
                }
            }
        }

        echo $OUTPUT->box_end();
    }

    function view_feedback($submission=NULL) {
        global $USER, $CFG, $DB, $OUTPUT;

        require_once($CFG->libdir.'/gradelib.php');
        if (!$submission) { /// Get submission for this assignment
            $submission = $this->get_submission($USER->id);
        }

        $grading_info = grade_get_grades($this->course->id, 'mod', 'assignment', $this->assignment->id, $USER->id);
        $item = $grading_info->items[0];
        $grade = $item->grades[$USER->id];

        if ($grade->hidden or $grade->grade === false) { // hidden or error
            return;
        }

        if ($grade->grade === null and empty($grade->str_feedback)) {   /// Nothing to show yet
            if ($this->count_responsefiles($USER->id)) {
                echo $OUTPUT->heading(get_string('responsefiles', 'assignment'), 3);
                $responsefiles = $this->print_responsefiles($USER->id, true);
                echo $OUTPUT->box($responsefiles, 'generalbox boxaligncenter');
            }
            return;
        }

        $graded_date = $grade->dategraded;
        $graded_by   = $grade->usermodified;

        /// We need the teacher info
        if (! $teacher = $DB->get_record('user', array('id' => $graded_by) )) {
            print_error('Could not find the teacher');
        }

        /// Print the feedback
        echo $OUTPUT->heading(get_string('submissionfeedback', 'assignment'), 3);

        echo '<table cellspacing="0" class="feedback">';

        echo '<tr>';
        echo '<td class="left picture">';
        echo $OUTPUT->user_picture($teacher);
        echo '</td>';
        echo '<td class="topic">';
        echo '<div class="from">';
        echo '<div class="fullname">'.fullname($teacher).'</div>';
        echo '<div class="time">'.userdate($graded_date).'</div>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<td class="left side">&nbsp;</td>';
        echo '<td class="content">';
        if ($this->assignment->grade) {
            echo '<div class="grade">';
            echo get_string("grade").': '.$grade->str_long_grade;
            echo '</div>';
            echo '<div class="clearer"></div>';
        }

        echo '<div class="comment">';
        echo $grade->str_feedback;
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
        global $CFG, $USER, $OUTPUT;

        $submission = $this->get_submission($USER->id);

        $struploadafile = get_string('uploadafile');
        $maxbytes = $this->assignment->maxbytes == 0 ? $this->course->maxbytes : $this->assignment->maxbytes;
        $strmaxsize = get_string('maxsize', '', display_size($maxbytes));

        if ($this->is_finalized($submission)) {
            // no uploading
            return;
        }

        if ($this->can_upload_file($submission)) {
            $fs = get_file_storage();
            // edit files in another page
            if ($submission) {
                if ($files = $fs->get_area_files($this->context->id, 'mod_assignment', 'submission', $submission->id, "timemodified", false)) {
                    $str = get_string('editthesefiles', 'assignment');
                } else {
                    $str = get_string('uploadfiles', 'assignment');
                }
            } else {
                $str = get_string('uploadfiles', 'assignment');
            }
            echo $OUTPUT->single_button(new moodle_url('/mod/assignment/type/uploadpdf/upload.php', array('contextid'=>$this->context->id, 'userid'=>$USER->id)), $str, 'get');
        }

    }

    function view_notes() {
        global $USER, $OUTPUT;

        if ($submission = $this->get_submission($USER->id)
            and !empty($submission->data1)) {
            echo $OUTPUT->box(format_text($submission->data1, FORMAT_HTML), 'generalbox boxaligncenter boxwidthwide');
        } else {
            echo $OUTPUT->box(get_string('notesempty', 'assignment'), 'generalbox boxaligncenter');
        }
        if ($this->can_update_notes($submission)) {
            $options = array ('id'=>$this->cm->id, 'action'=>'editnotes');
            echo '<div style="text-align:center">';
            echo $OUTPUT->single_button(new moodle_url('upload.php', $options), get_string('edit'));
            echo '</div>';
        }
    }

    function view_final_submission() {
        global $CFG, $USER, $DB, $OUTPUT;

        $submission = $this->get_submission($USER->id);

        if ($this->can_finalize($submission)) {
            //print final submit button
            echo $OUTPUT->heading(get_string('submitformarking','assignment'), 3);
            echo '<div style="text-align:center">';
            echo '<form method="post" action="upload.php">';
            echo '<fieldset class="invisiblefieldset">';

            $extra = $DB->get_record('assignment_uploadpdf', array('assignment' => $this->cm->instance) );
            $disabled = '';
            $checklistmessage = '';

            if ($extra->checklist && $extra->checklist_percent) {
                if ($this->import_checklist_plugin()) {
                    list($ticked, $total) = checklist_class::get_user_progress($extra->checklist, $USER->id);
                    if ($total && (($ticked * 100 / $total) < $extra->checklist_percent)) {
                        $disabled = ' disabled="disabled" ';
                        $checklistmessage = '<p class="error">'.get_string('checklistunfinished', 'assignment_uploadpdf').'</p>';
                    }
                }
            }

            echo $checklistmessage;

            if ($extra && ($extra->template > 0)) {
                $fs = get_file_storage();
                $coversheet = $fs->get_area_files($this->context->id, 'mod_assignment', 'coversheet', false, '', false);
                if (!empty($coversheet)) {
                    $t_items = $DB->get_records('assignment_uploadpdf_tmplitm', array('template' => $extra->template) );
                    $ticount = 0;
                    if (!empty($t_items)) {
                        echo '<table>';
                        foreach ($t_items as $ti) {
                            if ($ti->type == 'text') {
                                $ticount++;
                                $inputname = 'templ'.$ticount;
                                echo '<tr><td align="right"><label for="'.$inputname.'">'.s($ti->setting).': </label></td>';
                                echo '<td><textarea name="'.$inputname.'" cols="30" rows="5" '.$disabled.'></textarea></td></tr>';

                            } elseif ($ti->type == 'shorttext') {
                                $ticount++;
                                $inputname = 'templ'.$ticount;
                                echo '<tr><td align="right"><label for="'.$inputname.'">'.s($ti->setting).': </label></td>';
                                echo '<td><input type="text" name="'.$inputname.'" '.$disabled.'/></td></tr>';
                            }
                            // Date type does not have an input box
                        }
                        echo '</table>';
                    }
                }
            }

            echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
            echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
            echo '<input type="hidden" name="action" value="finalize" />';
            echo '<input type="submit" name="formarking" value="'.get_string('sendformarking', 'assignment').'" '.$disabled.'/>';
            echo '</fieldset>';
            echo '</form>';
            echo '</div>';
        } else if ($this->is_finalized($submission)) {
            echo $OUTPUT->heading(get_string('submitedformarking','assignment'), 3);
        } else {
            //no submission yet
        }
    }

    function description_is_hidden() {
        return ($this->assignment->var3 && (time() <= $this->assignment->timeavailable));
    }

    function submissions($mode) {
        $unfinalize = optional_param('unfinalize', FALSE, PARAM_TEXT);
        if ($unfinalize) {
            $this->unfinalize('single');
        }

        parent::submissions($mode);
    }

    function custom_feedbackform($submission, $return=false) {
        global $OUTPUT;

        $mode         = optional_param('mode', '', PARAM_ALPHA);
        $offset       = optional_param('offset', 0, PARAM_INT);
        $forcerefresh = optional_param('forcerefresh', 0, PARAM_BOOL);

        $output = '';

        if ($forcerefresh) {
            $output .= $this->update_main_listing($submission);
        }

        $responsefiles = $this->print_responsefiles($submission->userid, true);
        if (!empty($responsefiles)) {
            $output .= $OUTPUT->box($responsefiles, 'generalbox boxaligncenter');
        }

        if ($return) {
            return $output;
        }
        echo $output;
        return;
    }


    function print_student_answer($userid, $return=false){
        global $CFG, $OUTPUT, $PAGE, $DB;

        $mode    = optional_param('mode', '', PARAM_ALPHA);
        $offset  = optional_param('offset', 0, PARAM_INT);

        $submission = $this->get_submission($userid);

        $output = '';

        if (!$this->is_finalized($submission)) {
            $output .= '<strong>'.get_string('draft', 'assignment').':</strong> ';
        }

        if ($this->notes_allowed() and !empty($submission->data1)) {
            $link = new moodle_url("/mod/assignment/type/upload/notes.php", array('id'=>$this->cm->id, 'userid'=>$userid));
            $action = new popup_action('click', $link, 'notes', array('height' => 500, 'width' => 780));
            $output .= $OUTPUT->action_link($link, get_string('notes', 'assignment'), $action, array('title'=>get_string('notes', 'assignment')));

            $output .= '&nbsp;';
        }

        if ($this->is_finalized($submission)) {
            $fs = get_file_storage();
            if ($files = $fs->get_area_files($this->context->id, 'mod_assignment', 'submissionfinal', $submission->id, 'timemodified', false)) {
                foreach ($files as $file) {
                    $filename = $file->get_filename();
                    $mimetype = $file->get_mimetype();
                    if ($mimetype == 'application/pdf') {
                        $editurl = new moodle_url('/mod/assignment/type/uploadpdf/editcomment.php',array('id'=>$this->cm->id, 'userid'=>$userid));
                        $img = '<img class="icon" src="'.$OUTPUT->pix_url(file_mimetype_icon($mimetype)).'" alt="'.$mimetype.'" /> ';
                        $output .= $OUTPUT->action_link($editurl, $img.get_string('annotatesubmission','assignment_uploadpdf'), new popup_action('click', $editurl, 'editcomment'.$submission->id, array('width'=>1000, 'height'=>700))).'&nbsp;';

                    } else {
                        $path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$this->context->id.'/mod_assignment/submission/'.$submission->id.'/'.$filename);
                        $output .= '<a href="'.$path.'" ><img class="icon" src="'.$OUTPUT->pix_url(file_mimetype_icon($mimetype)).'" alt="'.$mimetype.'" />'.s($filename).'</a>&nbsp;';
                    }
                }
                if (($mode == 'grade' || $mode == '') && $file = $fs->get_file($this->context->id, 'mod_assignment', 'response', $submission->id, '/', 'response.pdf')) {
                    $respmime = $file->get_mimetype();
                    $respicon = $OUTPUT->pix_url(file_mimetype_icon($respmime));
                    $respurl = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$this->context->id.'/mod_assignment/response/'.$submission->id.'/response.pdf');
                    $output .= '<br />=&gt; <a href="'.$respurl.'" ><img class="icon" src="'.$respicon.'" alt="'.$respmime.'" />'.get_string('viewresponse','assignment_uploadpdf').'</a>&nbsp;';

                    // To tidy up flags from older versions of this assignment
                    if ($submission->data2 != ASSIGNMENT_UPLOADPDF_STATUS_RESPONDED) {
                        $update = new Object();
                        $update->id = $submission->id;
                        $update->data2 = ASSIGNMENT_UPLOADPDF_STATUS_RESPONDED;
                        $DB->update_record('assignment_submissions', $update);
                    }
                }
            }


            $output = $OUTPUT->box_start('files').$output;
            $output .= $OUTPUT->box_end();

        } else {
            if ($submission) {
                $renderer = $PAGE->get_renderer('mod_assignment');
                $output = $OUTPUT->box_start('files').$output;
                $output .= $renderer->assignment_files($this->context, $submission->id);
                $output .= $OUTPUT->box_end();
            }
        }

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
        global $CFG, $USER, $OUTPUT, $PAGE;

        $mode    = optional_param('mode', '', PARAM_ALPHA);
        $offset  = optional_param('offset', 0, PARAM_INT);

        if (!$userid) {
            if (!isloggedin()) {
                return '';
            }
            $userid = $USER->id;
        }

        $output = $OUTPUT->box_start('files');

        $submission = $this->get_submission($userid);

        $candelete = $this->can_delete_files($submission);
        $strdelete   = get_string('delete');

        if (!$this->is_finalized($submission) and !empty($mode)) {                 // only during grading
            $output .= '<strong>'.get_string('draft', 'assignment').':</strong><br />';
        }

        if ($this->notes_allowed() and !empty($submission->data1) and !empty($mode)) { // only during grading
            $npurl = new moodle_url('/mod/assignment/type/uploadpdf/notes.php', array('id'=>$this->cm->id, 'userid'=>$userid, 'offset'=>$offset, 'mode'=>'single'));
            $output .= '<a href="'.$npurl.'">'.get_string('notes', 'assignment').'</a><br />';
        }

        if ($this->is_finalized($submission)) {
            $fs = get_file_storage();
            if ($files = $fs->get_area_files($this->context->id, 'mod_assignment', 'submissionfinal', $submission->id, 'timemodified', false)) {
                foreach ($files as $file) {
                    $filename = $file->get_filename();
                    $mimetype = $file->get_mimetype();
                    if ($mimetype == 'application/pdf' && has_capability('mod/assignment:grade', $this->context)) {
                        $editurl = new moodle_url('/mod/assignment/type/uploadpdf/editcomment.php',array('id'=>$this->cm->id, 'userid'=>$userid));
                        $img = '<img class="icon" src="'.$OUTPUT->pix_url(file_mimetype_icon($mimetype)).'" alt="'.$mimetype.'" />';
                        $filename = get_string('annotatesubmission', 'assignment_uploadpdf');
                        $output .= $OUTPUT->action_link($editurl, $img.s($filename), new popup_action('click', $editurl, 'editcomment'.$userid, array('width'=>1000, 'height'=>700))).'&nbsp;';
                    } else {
                        $path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$this->context->id.'/mod_assignment/submissionfinal/'.$submission->id.'/'.$filename);
                        if ($mimetype == 'application/pdf') {
                            $filename = get_string('yourcompletedsubmission', 'assignment_uploadpdf');
                        }
                        $output .= '<a href="'.$path.'" ><img src="'.$OUTPUT->pix_url(file_mimetype_icon($mimetype)).'" class="icon" alt="'.$mimetype.'" />'.s($filename).'</a>';
                        $output .= '<br />';
                    }
                }
            }
        } else {
            if ($submission) {
                $renderer = $PAGE->get_renderer('mod_assignment', null);
                $output .= $renderer->assignment_files($this->context, $submission->id);
            }
        }

        if (has_capability('mod/assignment:grade', $this->context)
            and $mode != 'grade'
            and $mode != '') { // we do not want it on view.php page
            if ($this->can_unfinalize($submission)) {
                $output .= '<br /><input type="submit" name="unfinalize" value="'.get_string('unfinalize', 'assignment').'" />';
            }
        }

        $output .= $OUTPUT->box_end();

        if ($return) {
            return $output;
        }
        echo $output;
    }

    function print_responsefiles($userid, $return=false) {
        global $CFG, $USER, $OUTPUT, $PAGE;

        $mode    = optional_param('mode', '', PARAM_ALPHA);
        $offset  = optional_param('offset', 0, PARAM_INT);

        $output = '';

        $candelete = $this->can_manage_responsefiles();
        $strdelete   = get_string('delete');

        if ($submission = $this->get_submission($userid)) {
            $viewurl = new moodle_url('/mod/assignment/type/uploadpdf/viewcomment.php',array('id'=>$this->cm->id, 'userid'=>$userid));
            $output = $OUTPUT->action_link($viewurl, get_string('viewfeedback','assignment_uploadpdf'), new popup_action('click', $viewurl, 'viewcomment'.$submission->id, array('width'=>1000, 'height'=>700))).'<br/>';
            $output .= $OUTPUT->box_start('responsefiles');
            $renderer = $PAGE->get_renderer('mod_assignment');
            $output .= $renderer->assignment_files($this->context, $submission->id, 'response');
            $output .= $OUTPUT->box_end();
        }


        if ($return) {
            return $output;
        }
        echo $output;
    }

    function count_real_submissions($groupid=0) {
        global $CFG, $DB;

        $context = get_context_instance(CONTEXT_MODULE, $this->cm->id);

        // this is all the users with this capability set, in this context or higher
        if ($users = get_users_by_capability($context, 'mod/assignment:submit', 'u.id', '', '', '', $groupid, '', false)) {
            $users = array_keys($users);
        }

        // if groupmembersonly used, remove users who are not in any group
        if ($users and !empty($CFG->enablegroupings) and $this->cm->groupmembersonly) {
            if ($groupingusers = groups_get_grouping_members($this->cm->groupingid, 'u.id', 'u.id')) {
                $users = array_intersect($users, array_keys($groupingusers));
            }
        }

        if (empty($users)) {
            return 0;
        }

        list($usql, $uparam) = $DB->get_in_or_equal($users, SQL_PARAMS_NAMED);
        $uparam['cminstance'] = $this->cm->instance;

        // Count the number of assignments that have been submitted and for
        // which a response file has been generated (ie data2 = 'responded',
        // not 'submitted')
        $uparam['data2'] = ASSIGNMENT_UPLOADPDF_STATUS_RESPONDED;
        $markedcount = $DB->count_records_sql('SELECT COUNT(*)
                                FROM {assignment_submissions}
                                WHERE assignment = :cminstance AND
                                data2 = :data2 AND
                                userid ' . $usql, $uparam);

        // Count the number of assignments that have been submitted, but for
        // which a response file has not been generated (ie data2 = 'submitted',
        // not 'responded')
        $uparam['data2'] = ASSIGNMENT_UPLOADPDF_STATUS_RESPONDED;
        $unmarkedcount = $DB->count_records_sql('SELECT COUNT(*)
                                FROM {assignment_submissions}
                                WHERE assignment = :cminstance AND
                                data2 = :data2 AND
                                userid ' . $usql, $uparam);

        $totalcount = $markedcount + $unmarkedcount;

        if ($unmarkedcount) {
            return "{$totalcount}({$unmarkedcount})";
        } else {
            return $totalcount;
        }
    }


    function upload($mform = null, $filemanager_options = null) {
        $action = required_param('action', PARAM_ALPHA);

        switch ($action) {
        case 'finalize':
            $this->finalize();
            break;
        case 'unfinalize':
            $this->unfinalize();
            break;
        case 'uploadfile':
            $this->upload_file($mform, $filemanager_options);
        case 'savenotes':
        case 'editnotes':
            $this->upload_notes();
        default:
            print_error('Error: Unknow upload action ('.$action.').');
        }
    }

    function upload_notes() {
        global $CFG, $USER, $DB, $OUTPUT;

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
            echo $OUTPUT->notification(get_string('uploaderror', 'assignment'));
            echo $OUTPUT->continue_button($returnurl);
            $this->view_footer();
            die;
        }

        if ($data = $mform->get_data() and $action == 'savenotes') {
            $submission = $this->get_submission($USER->id, true); // get or create submission
            $updated = new object();
            $updated->id           = $submission->id;
            $updated->timemodified = time();
            $updated->data1        = $data->text;

            if ($DB->update_record('assignment_submissions', $updated)) {
                add_to_log($this->course->id, 'assignment', 'upload', 'view.php?a='.$this->assignment->id, $this->assignment->id, $this->cm->id);
                redirect($returnurl);
                $submission = $this->get_submission($USER->id);
                $this->update_grade($submission);

            } else {
                $this->view_header(get_string('notes', 'assignment'));
                echo $OUTPUT->notification(get_string('notesupdateerror', 'assignment'));
                echo $OUTPUT->continue_button($returnurl);
                $this->view_footer();
                die;
            }
        }

        /// show notes edit form
        $this->view_header(get_string('notes', 'assignment'));

        echo $OUTPUT->heading(get_string('notes', 'assignment'));

        $mform->display();

        $this->view_footer();
        die;
    }

    function upload_file($mform, $options) {
        global $CFG, $USER, $DB, $OUTPUT;

        $mode   = optional_param('mode', '', PARAM_ALPHA);
        $offset = optional_param('offset', 0, PARAM_INT);

        $returnurl = 'view.php?id='.$this->cm->id;

        $submission = $this->get_submission($USER->id);
        if (!$this->can_upload_file($submission)) {
            $this->view_header(get_string('upload'));
            echo $OUTPUT->notification(get_string('uploaderror', 'assignment'));
            echo $OUTPUT->continue_button($returnurl);
            $this->view_footer();
            die;
        }

        if ($formdata = $mform->get_data()) {
            $fs = get_file_storage();
            $submission = $this->get_submission($USER->id, true); //create new submission if needed
            $fs->delete_area_files($this->context->id, 'mod_assignment', 'submission', $submission->id);
            $formdata = file_postupdate_standard_filemanager($formdata, 'files', $options, $this->context, 'mod_assignment', 'submission', $submission->id);

            // Make sure all submitted PDFs are compatible with FPDI
            /** @var $files stored_file[] */
            if ($files = $fs->get_area_files($this->context->id, 'mod_assignment', 'submission', $submission->id, 'timemodified', false)) {
                foreach ($files as $file) {
                    if ($file->get_mimetype() == 'application/pdf') {
                        if (!MyPDFLib::ensure_pdf_compatible($file)) { // Uses ghostscript to convert any PDFs > v1.4
                            throw new moodle_exception('invalidpdf', 'uploadpdf', $file->get_filename());
                        }
                    }
                }
            }

            $updates = new object();
            $updates->id = $submission->id;
            $updates->timemodified = time();
            if ($DB->update_record('assignment_submissions', $updates)) {
                add_to_log($this->course->id, 'assignment', 'upload',
                        'view.php?a='.$this->assignment->id, $this->assignment->id, $this->cm->id);
                $this->update_grade($submission);

                // send files to event system
                $files = $fs->get_area_files($this->context->id, 'mod_assignment', 'submission', $submission->id);
                // Let Moodle know that assessable files were  uploaded (eg for plagiarism detection)
                $eventdata = new object();
                $eventdata->modulename   = 'assignment';
                $eventdata->cmid         = $this->cm->id;
                $eventdata->itemid       = $submission->id;
                $eventdata->courseid     = $this->course->id;
                $eventdata->userid       = $USER->id;
                if ($files) {
                    $eventdata->files        = $files;
                }
                events_trigger('assessable_file_uploaded', $eventdata);
            }
            $returnurl  = new moodle_url('/mod/assignment/view.php', array('id'=>$this->cm->id));
            redirect($returnurl);
        }

        $this->view_header(get_string('upload'));
        echo $OUTPUT->notification(get_string('uploaderror', 'assignment'));
        echo $OUTPUT->continue_button($returnurl);
        $this->view_footer();
        die;
    }

    function send_file($filearea, $args, $forcedownload, array $options = array()) {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir.'/filelib.php');


        require_login($this->course, false, $this->cm);

        $submissionid = (int)array_shift($args);
        $relativepath = implode('/', $args);
        $fullpath = "/{$this->context->id}/mod_assignment/{$filearea}/{$submissionid}/{$relativepath}";

        $fs = get_file_storage();

        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }

        if ($filearea == 'submission' || $filearea == 'submissionfinal' || $filearea == 'response') {

            $submission = $DB->get_record('assignment_submissions', array('assignment'=>$this->assignment->id, 'id' => $submissionid));
            if ($USER->id != $submission->userid and !has_capability('mod/assignment:grade', $this->context)) {
                return false;
            }

        } else if ($filearea === 'image') { // Images generate from submitted PDF
            if (!has_capability('mod/assignment:grade', $this->context)) {
                $submission = $DB->get_record('assignment_submissions', array('assignment'=>$this->assignment->id, 'id' => $submissionid));
                if ($USER->id != $submission->userid || !has_capability('mod/assignment:submit', $this->context)) {
                    return false;
                }
            }

            send_stored_file($file);
            return;

        } else if ($filearea === 'coversheet') { // Coversheet to add to all submissions
            if (!has_capability('mod/assignment:view', $this->context)) {
                return false;
            }

        } else {
            return false;
        }

        send_stored_file($file, 0, 0, true); // download MUST be forced - security!
    }

    function finalize() {
        global $USER, $DB, $OUTPUT;

        $confirm = optional_param('confirm', 0, PARAM_BOOL);
        $confirmnotpdf = optional_param('confirmnotpdf', 0, PARAM_BOOL);

        $returnurl = new moodle_url('/mod/assignment/view.php', array('id'=>$this->cm->id));
        $continueurl = new moodle_url('/mod/assignment/upload.php', array('id'=>$this->cm->id, 'action'=>'finalize', 'sesskey'=>sesskey()) );

        $submission = $this->get_submission($USER->id);

        if (!$this->can_finalize($submission)) {
            redirect($returnurl); // probably already graded, redirect to assignment page, the reason should be obvious
        }

        $extra = $DB->get_record('assignment_uploadpdf', array('assignment' => $this->cm->instance) );

        // Check that they have finished everything on the checklist (if that option is selected)
        if ($extra->checklist && $extra->checklist_percent) {
            if ($this->import_checklist_plugin()) {
                list($ticked, $total) = checklist_class::get_user_progress($extra->checklist, $USER->id);
                if ($total && (($ticked * 100 / $total) < $extra->checklist_percent)) {
                    $this->view_header();
                    echo $OUTPUT->heading(get_string('checklistunfinishedheading', 'assignment_uploadpdf'));
                    echo $OUTPUT->notification(get_string('checklistunfinished', 'assignment_uploadpdf'));
                    echo $OUTPUT->continue_button($returnurl);
                    $this->view_footer();
                    die;
                }
            }
        }

        // Check that form for coversheet has been filled in
        // (but don't complain about it until the PDF check has been done)
        $templatedataOK = true;
        $templateitems = false;
        if ($extra &&  $extra->template > 0) {
            $fs = get_file_storage();
            $coversheet = $fs->get_area_files($this->context->id, 'mod_assignment', 'coversheet', false, '', false);
            if (!empty($coversheet)) {
                $templateitems = $DB->get_records('assignment_uploadpdf_tmplitm', array('template' => $extra->template) );
                $ticount = 0;
                foreach ($templateitems as $ti) {
                    if (($ti->type == 'text') || ($ti->type == 'shorttext')) {
                        $ticount++;
                        $itemname = 'templ'.$ticount;
                        $param = optional_param('templ'.$ticount, '', PARAM_TEXT);
                        if (trim($param) == '') {
                            $templatedataOK = false;
                        } else {
                            $continueurl->param('templ'.$ticount, $param); /* Keep to pass on after yes/no questions answered */
                            $ti->data = $param; /* Keep to pass on to the coversheet generation */
                        }
                    }
                }
            }
        }

        // Check that all files submitted are PDFs
        if ($file = $this->get_not_pdf($submission->id)) {
            if (!$confirmnotpdf) {
                $this->view_header();
                echo $OUTPUT->heading(get_string('nonpdfheading', 'assignment_uploadpdf'));
                if ($extra && $extra->onlypdf) {
                    echo $OUTPUT->notification(get_string('filenotpdf', 'assignment_uploadpdf', $file));
                    echo $OUTPUT->continue_button($returnurl);
                } else {
                    if ($this->get_pdf_count($submission->id) < 1) {
                        echo $OUTPUT->notification(get_string('nopdf', 'assignment_uploadpdf'));
                        echo $OUTPUT->continue_button($returnurl);
                    } else {
                        $continueurl->param('confirmnotpdf', 1);
                        echo $OUTPUT->confirm(get_string('filenotpdf_continue', 'assignment_uploadpdf', $file), $continueurl, $returnurl);
                    }
                }
                $this->view_footer();
                die;
            }
        }

        if (!$templatedataOK) {
            $this->view_header();
            echo $OUTPUT->heading(get_string('heading_templatedatamissing', 'assignment_uploadpdf'));
            echo $OUTPUT->notification(get_string('templatedatamissing', 'assignment_uploadpdf'));
            echo $OUTPUT->continue_button($returnurl);
            die;
        }

        if (!data_submitted() or !$confirm or !confirm_sesskey()) {
            $continueurl->param('confirmnotpdf', 1);
            $continueurl->param('confirm', 1);
            $this->view_header(get_string('submitformarking', 'assignment'));
            echo $OUTPUT->heading(get_string('submitformarking', 'assignment'));
            echo $OUTPUT->confirm(get_string('onceassignmentsent', 'assignment'), $continueurl, $returnurl);
            $this->view_footer();
            die;

        } else {
            if (!($pagecount = $this->create_submission_pdf($submission->id, $templateitems))) {
                $this->view_header(get_string('submitformarking', 'assignment'));
                echo $OUTPUT->notification(get_string('createsubmissionfailed', 'assignment_uploadpdf'));
                echo $OUTPUT->continue_button($returnurl);
                $this->view_footer();
                die;
            }

            $updated = new stdClass;
            $updated->id = $submission->id;
            $updated->data2 = ASSIGNMENT_UPLOADPDF_STATUS_SUBMITTED;
            $updated->timemodified = time();
            $updated->numfiles = $pagecount << 1;   // Last bit is already used to indicate which folders the cron job should check for images to delete
            if ($DB->update_record('assignment_submissions', $updated)) {
                add_to_log($this->course->id, 'assignment', 'upload', //TODO: add finilize action to log
                           'view.php?a='.$this->assignment->id, $this->assignment->id, $this->cm->id);
                $this->email_teachers($submission);
            } else {
                $this->view_header(get_string('submitformarking', 'assignment'));
                echo $OUTPUT->notification(get_string('finalizeerror', 'assignment'));
                echo $OUTPUT->continue_button($returnurl);
                $this->view_footer();
                die;
            }
        }
        redirect($returnurl);
    }

    function unfinalize($forcemode = null) {
        global $CFG, $DB;

        $userid = required_param('userid', PARAM_INT);
        $mode   = required_param('mode', PARAM_ALPHA);
        $offset = required_param('offset', PARAM_INT);

        if ($forcemode != null) {
            $mode = $forcemode;
        }

        $returnurl = new moodle_url('/mod/assignment/submissions.php', array('id'=>$this->cm->id, 'userid'=>$userid, 'mode'=>$mode, 'offset'=>$offset, 'forcerefresh'=>1) );

        if (data_submitted()
            and $submission = $this->get_submission($userid)
            and $this->can_unfinalize($submission)
            and confirm_sesskey()) {
            $fs = get_file_storage();
            $fs->delete_area_files($this->context->id, 'mod_assignment', 'submissionfinal', $submission->id);
            $fs->delete_area_files($this->context->id, 'mod_assignment', 'image', $submission->id);
            $fs->delete_area_files($this->context->id, 'mod_assignment', 'response', $submission->id);

            $updated = new object();
            $updated->id = $submission->id;
            $updated->data2 = '';
            if ($DB->update_record('assignment_submissions', $updated)) {
                //TODO: add unfinilize action to log
                add_to_log($this->course->id, 'assignment', 'view submission', 'submissions.php?id='.$this->assignment->id, $this->assignment->id, $this->cm->id);
                $this->update_grade($submission);
            } else {
                $this->view_header(get_string('submitformarking', 'assignment'));
                echo $OUTPUT->notification(get_string('unfinalizeerror', 'assignment'));
                echo $OUTPUT->continue_button($returnurl);
                $this->view_footer();
                die;
            }
        }
        redirect($returnurl);
    }

    function can_upload_file($submission) {
        global $USER;

        if (is_enrolled($this->context, $USER, 'mod/assignment:submit')           // can submit
            and $this->isopen()                                                 // assignment not closed yet
            and (empty($submission) or $submission->userid == $USER->id)        // his/her own submission
            and (empty($submission) or $this->count_user_files($submission->id) <= $this->assignment->var1)
            and !$this->is_finalized($submission)) {
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
        if ( !empty($submission)
             and (($submission->data2 == ASSIGNMENT_UPLOADPDF_STATUS_SUBMITTED)
                  or ($submission->data2 == ASSIGNMENT_UPLOADPDF_STATUS_RESPONDED)) ) {
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
            and !empty($submission)                                            // Not sure why this is needed (as already checked by 'is_finalized'
            and !$this->is_finalized($submission)                                      // not submitted already
            and $submission->userid == $USER->id                                // his/her own submission
            and $submission->grade == -1                                        // no reason to finalize already graded submission
            and $this->count_user_files($submission->id)) { // something must be submitted

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

    function count_responsefiles($userid) {
        if ($submission = $this->get_submission($userid)) {
            $fs = get_file_storage();
            $files = $fs->get_area_files($this->context->id, 'mod_assignment', 'response', $submission->id, "id", false);
            return count($files);
        } else {
            return 0;
        }
    }

    function get_not_pdf($submissionid) {
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'mod_assignment', 'submission', $submissionid, "id", false);
        foreach ($files as $file) {
            if ($file->get_mimetype() != 'application/pdf') {
                return $file->get_filename();
            }
        }

        return false;
    }

    function get_pdf_count($submissionid) {
        global $CFG;

        $count = 0;
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'mod_assignment', 'submission', $submissionid, 'id', false);
        foreach ($files as $file) {
            if ($file->get_mimetype() == 'application/pdf') {
                $count++;
            }
        }

        return $count;
    }

    protected function get_temp_folder($submissionid) {
        global $CFG, $USER;

        $tempfolder = $CFG->dataroot.'/temp/uploadpdf/';
        $tempfolder .= sha1("{$submissionid}_{$USER->id}_".time()).'/';
        return $tempfolder;
    }

    function create_submission_pdf($submissionid, $template) {
        $fs = get_file_storage();

        $mypdf = new MyPDFLib();

        $temparea = $this->get_temp_folder($submissionid);
        $destfile = $temparea.'sub/submission.pdf';

        if (!file_exists($temparea) || !file_exists($temparea.'sub')) {
            if (!mkdir($temparea.'sub', 0777, true)) {
                echo "Unable to create temporary folder";
                die;
            }
        }

        $combine_files = array();
        /** @var $files stored_file[] */
        if ($files = $fs->get_area_files($this->context->id, 'mod_assignment', 'submission', $submissionid, 'timemodified', false)) {
            foreach ($files as $key=>$file) {
                if ($file->get_mimetype() != 'application/pdf') {
                    $fs->create_file_from_storedfile(array('component'=>'mod_assignment', 'filearea'=>'submissionfinal'), $file); /* Copy any non-PDF files to submission filearea */
                    unset($files[$key]);
                } else {
                    $destpath = $temparea.$file->get_filename();
                    if ($file->copy_content_to($destpath)) {
                        $combine_files[] = $destpath;
                    } else {
                        return 0;
                    }
                }
            }
            if (!empty($combine_files)) { /* Should have already checked there is at least 1 PDF */
                $coversheet_path = null;
                $coversheet = $fs->get_area_files($this->context->id, 'mod_assignment', 'coversheet', false, '', false);

                if (!empty($coversheet)) {
                    $coversheet = array_shift(array_values($coversheet));
                    $coversheet_path = $temparea.'sub/coversheet.pdf';
                    if (!$coversheet->copy_content_to($coversheet_path)) {
                        return 0;
                    }
                }

                if (!$pagecount = $mypdf->combine_pdfs($combine_files, $destfile, $coversheet_path, $template)) {
                    return 0;
                }
                $fileinfo = array('contextid'=>$this->context->id,
                                  'component'=>'mod_assignment',
                                  'filearea'=>'submissionfinal',
                                  'itemid'=>$submissionid,
                                  'filename'=>'submission.pdf',
                                  'filepath'=>'/');
                $fs->create_file_from_pathname($fileinfo, $destfile);

                unlink($destfile);
                if ($coversheet_path != null) { unlink($coversheet_path); }
                foreach ($combine_files as $fl) {
                    unlink($fl);
                }
                // Try to clean up the temporary folder.
                @rmdir($temparea.'sub');
                @rmdir($temparea);

                return $pagecount;

            } else {
                return 0;
            }
        }
        return 0;
    }

    function create_response_pdf($submissionid) {
        global $DB;

        $fs = get_file_storage();
        if (!$file = $fs->get_file($this->context->id, 'mod_assignment', 'submissionfinal', $submissionid, '/', 'submission.pdf')) {
            print_error('Submitted PDF not found');
            return false;
        }
        $temparea = $this->get_temp_folder($submissionid).'sub';
        if (!file_exists($temparea)) {
            if (!mkdir($temparea, 0777, true)) {
                echo "Unable to create temporary folder";
                die;
            }
        }
        $sourcefile = $temparea.'/submission.pdf';
        $destfile = $temparea.'/response.pdf';

        $file->copy_content_to($sourcefile);

        $mypdf = new MyPDFLib();
        $mypdf->load_pdf($sourcefile);

        $comments = $DB->get_records('assignment_uploadpdf_comment', array('assignment_submission' => $submissionid), 'pageno');
        $annotations = $DB->get_records('assignment_uploadpdf_annot', array('assignment_submission' => $submissionid), 'pageno');

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
                    break 2;
                }
            }

            while (($comment) && ($comment->pageno == $mypdf->current_page())) {
                $mypdf->add_comment($comment->rawtext, $comment->posx, $comment->posy, $comment->width, $comment->colour);
                $comment = next($comments);
            }

            while (($annotation) && ($annotation->pageno == $mypdf->current_page())) {
                if ($annotation->type == 'freehand') {
                    $path = explode(',',$annotation->path);
                    $mypdf->add_annotation(0,0,0,0, $annotation->colour, 'freehand', $path);
                } else {
                    $mypdf->add_annotation($annotation->startx, $annotation->starty, $annotation->endx,
                                           $annotation->endy, $annotation->colour, $annotation->type, $annotation->path);
                }
                $annotation = next($annotations);
            }
        }

        $mypdf->copy_remaining_pages();
        $mypdf->save_pdf($destfile);

        // Delete any previous response file
        if ($file = $fs->get_file($this->context->id, 'mod_assignment', 'response', $submissionid, '/', 'response.pdf') ) {
            $file->delete();
        }

        $fileinfo = array('contextid'=>$this->context->id,
                          'component'=>'mod_assignment',
                          'filearea'=>'response',
                          'itemid'=>$submissionid,
                          'filename'=>'response.pdf',
                          'filepath'=>'/');
        $fs->create_file_from_pathname($fileinfo, $destfile);

        @unlink($sourcefile);
        @unlink($destfile);
        @rmdir($temparea);
        @rmdir(dirname($temparea));

        return true;
    }

    function get_page_image($pageno, $submission) {
        global $CFG, $DB;

        $pagefilename = 'page'.$pageno.'.png';
        $pdf = new MyPDFLib();

        $pagecount = $submission->numfiles >> 1; // Extract the pagecount from 'numfiles' (may be 0)

        $fs = get_file_storage();
        // If pagecount is 0, then we need to skip down to the next stage to find the real page count
        if ( $pagecount && ($file = $fs->get_file($this->context->id, 'mod_assignment', 'image', $submission->id, '/', $pagefilename)) ) {
            if ($imageinfo = $file->get_imageinfo()) {
                $imgurl = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$this->context->id.'/mod_assignment/image/'.$submission->id.'/'.$pagefilename);
                // Prevent browser from caching image if it has changed
                if (strpos($imgurl, '?') === false) {
                    $imgurl .= '?ts='.$file->get_timemodified();
                } else {
                    $imgurl .= '&amp;ts='.$file->get_timemodified();
                }
                return array($imgurl, $imageinfo['width'], $imageinfo['height'], $pagecount);
            }
            // If the image is bad in some way, try to create a new image instead
        }

        // Generate the image
        $tempfolder = $this->get_temp_folder($submission->id);
        $imagefolder = $tempfolder.'img';
        if (!file_exists($imagefolder)) {
            if (!mkdir($imagefolder, 0777, true)) {
                echo "Unable to create temporary image folder";
                die;
            }
        }
        $pdffolder = $tempfolder.'sub';
        $pdffile = $pdffolder.'/submission.pdf';
        if (!file_exists($pdffolder)) {
            if (!mkdir($pdffolder, 0777, true)) {
                echo "Unable to create temporary folder";
                die;
            }
        }

        if (!$file = $fs->get_file($this->context->id, 'mod_assignment', 'submissionfinal', $submission->id, '/', 'submission.pdf')) {
            print_error('Attempting to display image for non-existant submission');
        }
        $file->copy_content_to($pdffile);  // Copy the PDF out of the file storage, into the temp area

        $pagecount = $pdf->set_pdf($pdffile, $pagecount); // Only loads the PDF if the pagecount is unknown (0)
        if ($pageno > $pagecount) {
            unlink($pdffile);
            return array(null, 0, 0, $pagecount);
        }

        $pdf->set_image_folder($imagefolder);
        if (!$imgname = $pdf->get_image($pageno)) { // Generate the image in the temp area
            print_error(get_string('errorgenerateimage', 'assignment_uploadpdf'));
        }

        if (($submission->numfiles & 1) == 0) {
            $submission->numfiles = ($pagecount << 1) | 1; // Use this as a flag that there are images to delete at some point
            // Maybe switch to just searching the filestorage database to find old images?

            $updated = new stdClass;
            $updated->id = $submission->id;
            $updated->numfiles = $submission->numfiles;
            $DB->update_record('assignment_submissions', $updated);
        }

        $imginfo = array(
                         'contextid' => $this->context->id,
                         'component' => 'mod_assignment',
                         'filearea' => 'image',
                         'itemid' => $submission->id,
                         'filepath' => '/',
                         'filename' => $pagefilename
                         );

        $file = $fs->create_file_from_pathname($imginfo, $imagefolder.'/'.$imgname); // Copy the image into the file storage

        //Delete the temporary files
        unlink($pdffile);
        unlink($imagefolder.'/'.$imgname);
        rmdir($imagefolder);
        rmdir($pdffolder);
        rmdir($tempfolder);

        if ($imageinfo = $file->get_imageinfo()) {
            $imgurl = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$this->context->id.'/mod_assignment/image/'.$submission->id.'/'.$pagefilename);
            // Prevent browser from caching image if it has changed
            if (strpos($imgurl, '?') === false) {
                $imgurl .= '?ts='.$file->get_timemodified();
            } else {
                $imgurl .= '&amp;ts='.$file->get_timemodified();
            }
            return array($imgurl, $imageinfo['width'], $imageinfo['height'], $pagecount);
        }

        return array(null, 0, 0, $pagecount);
    }

    function edit_comment_page($userid, $pageno, $enableedit = true) {
        global $CFG, $DB, $OUTPUT, $PAGE, $USER;

        if (!$user = $DB->get_record('user', array('id' => $userid) )) {
            print_error('No such user!');
        }

        if (!$submission = $this->get_submission($user->id)) {
            print_error('User has no submission to comment on!');
        }

        if (!has_capability('mod/assignment:grade', $this->context)) {
            if (has_capability('mod/assignment:submit', $this->context) && $USER->id == $userid) {
                $enableedit = false;
            } else {
                print_error('No permission to view or edit this assignment');
            }
        }

        $showprevious = optional_param('showprevious', -1, PARAM_INT);

        if ($enableedit && optional_param('topframe', false, PARAM_INT)) {
            if ($showprevious != -1) {
                echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd">';
                echo '<html><head><title>'.get_string('feedback', 'assignment').':'.fullname($user, true).':'.format_string($this->assignment->name).'</title></head>';
                echo '<frameset cols="60%, 40%">';
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

        if ($enableedit && $savedraft) {
            echo $OUTPUT->header(get_string('feedback', 'assignment').':'.format_string($this->assignment->name));
            echo $OUTPUT->heading(get_string('draftsaved', 'assignment_uploadpdf'));
            echo html_writer::script('self.close()');  // FIXME - use 'close_window()', if it ever starts working again
            die;
        }

        if ($enableedit && $generateresponse) {
            if ($this->create_response_pdf($submission->id)) {
                $submission->data2 = ASSIGNMENT_UPLOADPDF_STATUS_RESPONDED;

                $updated = new stdClass;
                $updated->id = $submission->id;
                $updated->data2 = $submission->data2;
                $DB->update_record('assignment_submissions', $updated);

                $PAGE->set_title(get_string('feedback', 'assignment').':'.format_string($this->assignment->name));
                echo $OUTPUT->header();
                echo $OUTPUT->heading(get_string('responseok', 'assignment_uploadpdf'));
                require_once($CFG->libdir.'/gradelib.php');
                echo $this->update_main_listing($submission);
                echo html_writer::script('self.close()');  // FIXME - use 'close_window()', if it ever starts working again
                die;
            } else {
                echo $OUTPUT->header(get_string('feedback', 'assignment').':'.format_string($this->assignment->name));
                print_error(get_string('responseproblem', 'assignment_uploadpdf'));
                die;
            }
        }

        list($imageurl, $imgwidth, $imgheight, $pagecount) = $this->get_page_image($pageno, $submission);

        $PAGE->requires->js('/mod/assignment/type/uploadpdf/scripts/mootools-core-1.4.1.js');
        $PAGE->requires->js('/mod/assignment/type/uploadpdf/scripts/mootools-more-1.4.0.1.js');
        $PAGE->requires->js('/mod/assignment/type/uploadpdf/scripts/raphael-min.js');
        $PAGE->requires->js('/mod/assignment/type/uploadpdf/scripts/contextmenu.js');

        $PAGE->set_pagelayout('popup');
        $PAGE->set_title(get_string('feedback', 'assignment').':'.fullname($user, true).':'.format_string($this->assignment->name));
        $PAGE->set_heading('');

        echo $OUTPUT->header();

        echo '<div id="saveoptions">';
        if ($enableedit) {
            echo '<form action="'.$CFG->wwwroot.'/mod/assignment/type/uploadpdf/editcomment.php" method="post" target="_top" >';
            echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
            echo '<input type="hidden" name="userid" value="'.$userid.'" />';
            echo '<input type="hidden" name="pageno" value="'.$pageno.'" />';
            echo '<button type="submit" id="savedraft" name="savedraft" value="savedraft" title="'.get_string('savedraft', 'assignment_uploadpdf').'"><img src="'.$OUTPUT->pix_url('savequit','assignment_uploadpdf').'"/></button>';
            echo '<button type="submit" id="generateresponse" name="generateresponse" value="generateresponse" title="'.get_string('generateresponse','assignment_uploadpdf').'"><img src="'.$OUTPUT->pix_url('tostudent','assignment_uploadpdf').'"/></button>';
        }

        // 'Download original' button
        $pdfurl = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$this->context->id.'/mod_assignment/submissionfinal/'.$submission->id.'/submission.pdf');
        $downloadorig = get_string('downloadoriginal', 'assignment_uploadpdf');
        if (!$enableedit) {
            $pdfurl = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$this->context->id.'/mod_assignment/response/'.$submission->id.'/response.pdf');
        }
        echo '<a href="'.$pdfurl.'" target="_blank" id="downloadpdf" title="'.$downloadorig.'" alt="'.$downloadorig.'" ><img src="'.$OUTPUT->pix_url('download','assignment_uploadpdf').'" alt="'.$downloadorig.'" title="'.$downloadorig.'" /></a>';
        if ($enableedit) {
            echo '</form>';
        }

            // Show previous assignment
        if ($enableedit) {
            $ps_sql = 'SELECT asn.id, asn.name FROM {assignment} asn ';
            $ps_sql .= 'INNER JOIN {assignment_submissions} sub ON sub.assignment = asn.id ';
            $ps_sql .= 'WHERE course = ? ';
            $ps_sql .= 'AND asn.assignmenttype = \'uploadpdf\' ';
            $ps_sql .= 'AND userid = ? ';
            $ps_sql .= 'AND asn.id != ? ';
            $ps_sql .= 'ORDER BY sub.timemodified DESC;';
            $previoussubs = $DB->get_records_sql($ps_sql, array($this->course->id, $userid, $this->assignment->id) );
            if ($previoussubs) {
                echo '<input type="submit" id="showpreviousbutton" name="showpreviousbutton" value="'.get_string('showpreviousassignment','assignment_uploadpdf').'" />';
                echo '<select id="showpreviousselect" name="showprevious" onChange="this.form.submit();">';
                echo '<option value="-1">'.get_string('previousnone','assignment_uploadpdf').'</option>';
                foreach ($previoussubs as $prevsub) {
                    echo '<option value="'.$prevsub->id.'"';
                    if ($showprevious == $prevsub->id) echo ' selected="selected" ';
                    echo '>'.s($prevsub->name).'</option>';
                }
                echo '</select>';
            }
        }

        $comments = $DB->get_records('assignment_uploadpdf_comment', array('assignment_submission'=>$submission->id), 'pageno, posy');
        echo '<button id="findcommentsbutton">'.get_string('findcomments','assignment_uploadpdf').'</button>';
        echo '<select id="findcommentsselect" name="findcomments" >';
        if (empty($comments)) {
            echo '<option value="0:0">'.get_string('findcommentsempty', 'assignment_uploadpdf').'</option>';
        } else {
            foreach ($comments as $comment) {
                $text = $comment->rawtext;
                if (strlen($text) > 40) {
                    $text = substr($text, 0, 39).'&hellip;';
                }
                echo '<option value="'.$comment->pageno.':'.$comment->id.'"';
                echo '>'.$comment->pageno.': '.s($text).'</option>';
            }
        }
        echo '</select>';
        if (!$enableedit) {
            // If opening in same window - show 'back to comment list' link
            if (array_key_exists('uploadpdf_commentnewwindow', $_COOKIE) && !$_COOKIE['uploadpdf_commentnewwindow']) {
                $url = "editcomment.php?a={$this->assignment->id}&amp;userid={$userid}&amp;action=showprevious";
                echo '<a href="'.$url.'">'.get_string('backtocommentlist','assignment_uploadpdf').'</a>';
            }
        }

        echo '</div>';

        echo '<div id="toolbar-line2">';
        if (!$CFG->uploadpdf_js_navigation) {
            //UT
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
            $pageselector .= '</div>';

        } else {
            $pageselector = '';
            $disabled = ($pageno == 1) ? ' disabled = "disabled" ' : '';
            $pageselector .= '<button id="prevpage" '.$disabled.'onClick="gotoprevpage();" title="'.get_string('keyboardprev','assignment_uploadpdf').'" >&lt;--'.get_string('previous','assignment_uploadpdf').'</button>';

            $pageselector .= '<span style="position:relative; width:50px; display:inline-block; height:34px"><select name="selectpage" id="selectpage" onChange="selectpage();">';
            for ($i=1; $i<=$pagecount; $i++) {
                if ($i == $pageno) {
                    $pageselector .= "<option value='$i' selected='selected'>$i</option>";
                } else {
                    $pageselector .= "<option value='$i'>$i</option>";
                }
            }
            $pageselector .= '</select></span>';

            $disabled = ($pageno == $pagecount) ? ' disabled = "disabled" ' : '';
            $pageselector .= '<button id="nextpage" '.$disabled.'onClick="gotonextpage();" title="'.get_string('keyboardnext','assignment_uploadpdf').'">'.get_string('next','assignment_uploadpdf').'--&gt;</button>';
        }

        echo $pageselector;

        if ($enableedit) {
            // Choose comment colour
            echo '<input type="submit" id="choosecolour" style="line-height:normal;" name="choosecolour" value="" title="'.get_string('commentcolour','assignment_uploadpdf').'">';
            echo '<div id="choosecolourmenu" class="yuimenu" title="'.get_string('commentcolour', 'assignment_uploadpdf').'"><div class="bd"><ul class="first-of-type">';
            $colours = array('red','yellow','green','blue','white','clear');
            foreach ($colours as $colour) {
                echo '<li class="yuimenuitem choosecolour-'.$colour.'-"><img src="'.$OUTPUT->pix_url($colour,'assignment_uploadpdf').'"/></li>';
            }
            echo '</ul></div></div>';

            // Choose line colour
            echo '<input type="submit" id="chooselinecolour" style="line-height:normal;" name="chooselinecolour" value="" title="'.get_string('linecolour','assignment_uploadpdf').'">';
            echo '<div id="chooselinecolourmenu" class="yuimenu"><div class="bd"><ul class="first-of-type">';
            $colours = array('red','yellow','green','blue','white','black');
            foreach ($colours as $colour) {
                echo '<li class="yuimenuitem choosecolour-'.$colour.'-"><img src="'.$OUTPUT->pix_url('line'.$colour, 'assignment_uploadpdf').'"/></li>';
            }
            echo '</ul></div></div>';

            // Stamps
            echo '<input type="submit" id="choosestamp" style="line-height:normal;" name="choosestamp" value="" title="'.get_string('stamp','assignment_uploadpdf').'">';
            echo '<div id="choosestampmenu" class="yuimenu"><div class="bd"><ul class="first-of-type">';
            $stamps = MyPDFLib::get_stamps();
            foreach ($stamps as $stamp => $filename) {
                echo '<li class="yuimenuitem choosestamp-'.$stamp.'-"><img width="32" height="32" src="'.$OUTPUT->pix_url('stamps/'.$stamp, 'assignment_uploadpdf').'"/></li>';
            }
            echo '</ul></div></div>';


            // Choose annotation type
            $drawingtools = array('commenticon','lineicon','rectangleicon','ovalicon','freehandicon','highlighticon','stampicon','eraseicon');
            $checked = ' yui-button-checked';
            echo '<div id="choosetoolgroup" class="yui-buttongroup">';

            foreach ($drawingtools as $drawingtool) {
                echo '<span id="'.$drawingtool.'" class="yui-button yui-radio-button'.$checked.'">';
                echo ' <span class="first-child">';
                echo '  <button type="button" name="choosetoolradio" value="'.$drawingtool.'" title="'.get_string($drawingtool,'assignment_uploadpdf').'">';
                echo '   <img src="'.$OUTPUT->pix_url($drawingtool, 'assignment_uploadpdf').'" />';
                echo '  </button>';
                echo ' </span>';
                echo '</span>';
                $checked = '';
            }
            echo '</div>';

        }
        echo '</div>'; // toolbar-line-2

        // Output the page image
        echo '<div id="pdfsize" style="clear: both; width:'.$imgwidth.'px; height:'.$imgheight.'px; ">';
        echo '<div id="pdfouter" style="position: relative; "> <div id="pdfholder" > ';
        echo '<img id="pdfimg" src="'.$imageurl.'" width="'.$imgwidth.'" height="'.$imgheight.'" />';
        echo '</div></div></div>';
        if ($CFG->uploadpdf_js_navigation) {
            $pageselector = str_replace(array('selectpage','"nextpage"','"prevpage"'),array('selectpage2','"nextpage2"','"prevpage2"'),$pageselector);
        }
        echo '<br/>';
        echo $pageselector;
        if ($enableedit && $CFG->uploadpdf_js_navigation) {
            echo '<p><a id="opennewwindow" target="_blank" href="editcomment.php?id='.$this->cm->id.'&amp;userid='.$userid.'&amp;pageno='. $pageno .'&amp;showprevious='.$showprevious.'">'.get_string('opennewwindow','assignment_uploadpdf').'</a></p>';
        }
        echo '<br style="clear:both;" />';

        if ($enableedit) {
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
        }

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
                        'deleteicon' => $OUTPUT->pix_url('/t/delete'),
                        'pagecount' => $pagecount,
                        'js_navigation' => $CFG->uploadpdf_js_navigation,
                        'blank_image' => $CFG->wwwroot.'/mod/assignment/type/uploadpdf/style/blank.gif',
                        'image_path' => $CFG->wwwroot.'/mod/assignment/type/uploadpdf/pix/',
                        'css_path' => $CFG->wwwroot.'/lib/yui/'.$CFG->yui2version.'/build/assets/skins/sam/',
                        'editing' => ($enableedit ? 1 : 0),
                        'lang_nocomments' => get_string('findcommentsempty', 'assignment_uploadpdf')
                        );

        echo '<script type="text/javascript">server_config = {';
        foreach ($server as $key => $value) {
            echo $key.": '$value', \n";
        }
        echo "ignore: ''\n"; // Just there so IE does not complain
        echo '};</script>';

        $jsmodule = array('name' => 'assignment_uploadpdf',
                          'fullpath' => new moodle_url('/mod/assignment/type/uploadpdf/scripts/annotate.js'),
                          'requires' => array('yui2-yahoo-dom-event', 'yui2-container', 'yui2-element',
                                              'yui2-button', 'yui2-menu', 'yui2-utilities'));
        $PAGE->requires->js_init_call('uploadpdf_init', null, true, $jsmodule);

        echo $OUTPUT->footer();
    }

    // Respond to AJAX requests whilst teacher is editing comments
    function update_comment_page($userid, $pageno) {
        global $USER, $DB;

        $resp = array('error'=> ASSIGNMENT_UPLOADPDF_ERR_NONE);

        if (!$user = $DB->get_record('user', array('id' => $userid) )) {
            send_error('No such user!');
        }

        if (!$submission = $this->get_submission($user->id)) {
            send_error('User has no submission to comment on!');
        }

        $action = optional_param('action','', PARAM_ALPHA);

        if ($action == 'getcomments' || $action == 'getimageurl') {
            if (!has_capability('mod/assignment:grade', $this->context)) {
                if ($userid != $USER->id || !has_capability('mod/assignment:submit', $this->context)) {
                    // Students can view comments / images for their own assignment
                    send_error('You do not have permission to do this');
                }
            }
        } else {
            // All annotation requests need to have 'grade' capability
            if (!has_capability('mod/assignment:grade', $this->context)) {
                    send_error('You do not have permission to do this');
            }
        }

        if ($action == 'update') {
            $comment = new stdClass;
            $comment->id = optional_param('comment_id', -1, PARAM_INT);
            $comment->posx = optional_param('comment_position_x', -1, PARAM_INT);
            $comment->posy = optional_param('comment_position_y', -1, PARAM_INT);
            $comment->width = optional_param('comment_width', -1, PARAM_INT);
            $comment->rawtext = optional_param('comment_text', null, PARAM_TEXT);
			$comment->colour = optional_param('comment_colour', 'yellow', PARAM_TEXT);
            $comment->pageno = $pageno;
            $comment->assignment_submission = $submission->id;

            if (($comment->posx < 0) || ($comment->posy < 0) || ($comment->width < 0) || ($comment->rawtext === null)) {
                send_error('Missing comment data');
            }

            if ($comment->id === -1) {
                unset($comment->id);
                $oldcomments = $DB->get_records_select('assignment_uploadpdf_comment',
                                                       'assignment_submission = ? AND pageno = ? '.
                                                       'AND posx = ? AND posy = ? AND rawtext = ?',
                                                       array($comment->assignment_submission, $comment->pageno,
                                                             $comment->posx, $comment->posy, $comment->rawtext));
                if ($oldcomments && !empty($oldcomments)) {
                    $comment->id = reset(array_keys($oldcomments));
                } else {
                    $comment->id = $DB->insert_record('assignment_uploadpdf_comment', $comment);
                }
            } else {
                $oldcomment = $DB->get_record('assignment_uploadpdf_comment', array('id' => $comment->id) );
                if (!$oldcomment) {
                    unset($comment->id);
                    $comment->id = $DB->insert_record('assignment_uploadpdf_comment', $comment);
                } else if (($oldcomment->assignment_submission != $submission->id) || ($oldcomment->pageno != $pageno)) {
                    send_error('Comment id is for a different submission or page');
                } else {
                    $DB->update_record('assignment_uploadpdf_comment', $comment);
                }
            }

            $resp['id'] = $comment->id;

        } elseif ($action == 'getcomments') {
            $comments = $DB->get_records('assignment_uploadpdf_comment', array('assignment_submission' => $submission->id, 'pageno' => $pageno) );
            $respcomments = array();
            foreach ($comments as $comment) {
                $respcomment = array();
                $respcomment['id'] = ''.$comment->id;
                $respcomment['text'] = $comment->rawtext;
                $respcomment['width'] = $comment->width;
                $respcomment['position'] = array('x'=> $comment->posx, 'y'=> $comment->posy);
                $respcomment['colour'] = $comment->colour;
                $respcomments[] = $respcomment;
            }
            $resp['comments'] = $respcomments;

            $annotations = $DB->get_records('assignment_uploadpdf_annot', array('assignment_submission' => $submission->id, 'pageno' => $pageno) );
            $respannotations = array();
            foreach ($annotations as $annotation) {
                $respannotation = array();
                $respannotation['id'] = ''.$annotation->id;
                $respannotation['type'] = $annotation->type;
                if ($annotation->type == 'freehand') {
                    $respannotation['path'] = $annotation->path;
                    if (is_null($annotation->path)) {
                        $DB->delete_records('assignment_uploadpdf_annot', array('id'=>$annotation->id));
                        continue;
                    }
                } else {
                    $respannotation['coords'] = array('startx'=> $annotation->startx, 'starty'=> $annotation->starty,
                                                      'endx'=> $annotation->endx, 'endy'=> $annotation->endy );
                }
                if ($annotation->type == 'stamp') {
                    $respannotation['path'] = $annotation->path;
                }
                $respannotation['colour'] = $annotation->colour;
                $respannotations[] = $respannotation;
            }
            $resp['annotations'] = $respannotations;

        } elseif ($action == 'delete') {
            $commentid = optional_param('commentid', -1, PARAM_INT);
            if ($commentid < 0) {
                send_error('No comment id provided');
            }
            $oldcomment = $DB->get_record('assignment_uploadpdf_comment', array('id' => $commentid, 'assignment_submission' => $submission->id, 'pageno' => $pageno) );
            if (!($oldcomment)) {
                send_error('Could not find a comment with that id on this page');
            } else {
                $DB->delete_records('assignment_uploadpdf_comment', array('id' => $commentid) );
            }

        } elseif ($action == 'getquicklist') {

            $quicklist = $DB->get_records('assignment_uploadpdf_qcklist', array('userid' => $USER->id), 'id');
            $respquicklist = array();
            foreach ($quicklist as $item) {
                $respitem = array();
                $respitem['id'] = ''.$item->id;
                $respitem['text'] = $item->text;
                $respitem['width'] = $item->width;
                $respitem['colour'] = $item->colour;
                $respquicklist[] = $respitem;
            }
            $resp['quicklist'] = $respquicklist;

        } elseif ($action == 'addtoquicklist') {

            $item = new stdClass;
            $item->userid = $USER->id;
            $item->width = optional_param('width', -1, PARAM_INT);
            $item->text = optional_param('text', null, PARAM_TEXT);
			$item->colour = optional_param('colour', 'yellow', PARAM_TEXT);

            if (($item->width < 0) || ($item->text === null)) {
                send_error('Missing quicklist data');
            }

            $item->id = $DB->insert_record('assignment_uploadpdf_qcklist', $item);

            $resp['item'] = $item;

        } elseif ($action == 'removefromquicklist') {

            $itemid = optional_param('itemid', -1, PARAM_INT);
            if ($itemid < 0) {
                send_error('No quicklist id provided');
            }

            $olditem = $DB->get_record('assignment_uploadpdf_qcklist', array('id' => $itemid, 'userid' => $USER->id) );
            if (!($olditem)) {
                send_error('Could not find a quicklist item with that id on this page');
            } else {
                $DB->delete_records('assignment_uploadpdf_qcklist', array('id' => $itemid) );
            }

            $resp['itemid'] = $itemid;

        } elseif ($action == 'getimageurl') {

            if ($pageno < 1) {
                send_error('Requested page number is too small (< 1)');
            }

            list($imageurl, $imgwidth, $imgheight, $pagecount) = $this->get_page_image($pageno, $submission);

            if ($pageno > $pagecount) {
                send_error('Requested page number is bigger than the page count ('.$pageno.' > '.$pagecount.')');
            }

            $resp['image'] = new Object();
            $resp['image']->url = $imageurl;
            $resp['image']->width = $imgwidth;
            $resp['image']->height = $imgheight;

        } elseif ($action == 'addannotation') {

            $annotation = new stdClass;
            $annotation->startx = optional_param('annotation_startx', -1, PARAM_INT);
            $annotation->starty = optional_param('annotation_starty', -1, PARAM_INT);
            $annotation->endx = optional_param('annotation_endx', -1, PARAM_INT);
            $annotation->endy = optional_param('annotation_endy', -1, PARAM_INT);
            $annotation->path = optional_param('annotation_path', null, PARAM_TEXT);
            $annotation->colour = optional_param('annotation_colour', 'red', PARAM_TEXT);
            $annotation->type = optional_param('annotation_type', 'line', PARAM_TEXT);
            $annotation->id = optional_param('annotation_id', -1, PARAM_INT);
            $annotation->pageno = $pageno;
            $annotation->assignment_submission = $submission->id;

            if ($annotation->type == 'freehand') {
                if (!$annotation->path) {
                    send_error('Missing annotation data');
                }
                // Double-check path is valid list of points
                $points = explode(',', $annotation->path);
                if (count($points)%2 != 0) {
                    send_error('Odd number of coordinates in line - should be 2 coordinates per point');
                }
                foreach ($points as $point) {
                    if (!preg_match('/^\d+$/', $point)) {
                        send_error('Path point is invalid');
                    }
                }
            } else {
                if ($annotation->type != 'stamp') {
                    $annotation->path = null;
                }
                if (($annotation->startx < 0) || ($annotation->starty < 0) || ($annotation->endx < 0) || ($annotation->endy < 0)) {
                    if ($annotation->id < 0) {
                        send_error('Missing annotation data');
                    } else {
                        // OK not to send these when updating a line
                        unset($annotation->startx);
                        unset($annotation->starty);
                        unset($annotation->endx);
                        unset($annotation->endy);
                    }
                }
            }

            if ($annotation->id === -1) {
                unset($annotation->id);
                $annotation->id = $DB->insert_record('assignment_uploadpdf_annot', $annotation);
            } else {
                $oldannotation = $DB->get_record('assignment_uploadpdf_annot', array('id' => $annotation->id) );
                if (!$oldannotation) {
                    unset($annotation->id);
                    $annotation->id = $DB->insert_record('assignment_uploadpdf_annot', $annotation);
                } else if (($oldannotation->assignment_submission != $submission->id) || ($oldannotation->pageno != $pageno)) {
                    send_error('Annotation id is for a different submission or page');
                } else {
                    $DB->update_record('assignment_uploadpdf_annot', $annotation);
                }
            }

            $resp['id'] = $annotation->id;

        } elseif ($action == 'removeannotation') {

            $annotationid = optional_param('annotationid', -1, PARAM_INT);
            if ($annotationid < 0) {
                send_error('No annotation id provided');
            }
            $oldannotation = $DB->get_record('assignment_uploadpdf_annot', array('id' => $annotationid, 'assignment_submission' => $submission->id, 'pageno' => $pageno) );
            if (!($oldannotation)) {
                send_error('Could not find a annotation with that id on this page');
            } else {
                $DB->delete_records('assignment_uploadpdf_annot', array('id' => $annotationid) );
            }

        } else {
            send_error('Invalid action "'.$action.'"', ASSIGNMENT_UPLOADPDF_ERR_INVALID_ACTION);
        }

        echo json_encode($resp);
    }

    function show_previous_comments($userid) {
        global $CFG, $DB, $PAGE, $OUTPUT;

        require_capability('mod/assignment:grade', $this->context);

        if (!$user = $DB->get_record('user', array('id' => $userid) )) {
            print_error('No such user!');
        }

        if (!$submission = $this->get_submission($user->id)) {
            print_error('User has no previous submission to display!');
        }

        $PAGE->set_pagelayout('popup');
        $PAGE->set_title(get_string('feedback', 'assignment').':'.fullname($user, true).':'.format_string($this->assignment->name));

        $PAGE->set_heading('');
        echo $OUTPUT->header();

        // Nasty javascript hack to stop the page being a minimum of 900 pixels wide
        echo '<script type="text/javascript">document.getElementById("page-content").setAttribute("style", "min-width:0px;");</script>';

        echo $OUTPUT->heading(format_string($this->assignment->name), 2);

        // Add download link for submission
        $fs = get_file_storage();
        if ( !($file = $fs->get_file($this->context->id, 'mod_assignment', 'response', $submission->id, '/', 'response.pdf')) ) {
            $file = $fs->get_file($this->context->id, 'mod_assignment', 'submissionfinal', $submission->id, '/', 'submission.pdf');
        }

        if ($file) {
            $pdfurl = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$this->context->id.'/mod_assignment/response/'.$submission->id.'/response.pdf');
            echo '<a href="'.$pdfurl.'" target="_blank">'.get_string('downloadoriginal', 'assignment_uploadpdf').'</a><br />';
        }

        // 'Open in new window' check box
        $checked = "checked='checked'";
        if (array_key_exists('uploadpdf_commentnewwindow', $_COOKIE)) {
            if (!$_COOKIE['uploadpdf_commentnewwindow']) {
                $checked = '';
            }
        }
        $onclick = "var checked = this.checked ? 1 : 0; document.cookie='uploadpdf_commentnewwindow='+checked; return true;";
        echo '<br/><input type="checkbox" name="opennewwindow" id="opennewwindow" '.$checked.' onclick="'.$onclick.'" />';
        echo '<label for="opennewwindow">'.get_string('openlinknewwindow','assignment_uploadpdf').'</label><br/>';

        // Put all the comments in a table
        $comments = $DB->get_records('assignment_uploadpdf_comment', array('assignment_submission' => $submission->id), 'pageno, posy');
        if (!$comments) {
            echo '<p>'.get_string('nocomments','assignment_uploadpdf').'</p>';

            /* This does not work well when the student has not submitted anything
            $linkurl = '/mod/assignment/type/uploadpdf/editcomment.php?a='.$this->assignment->id.'&amp;userid='.$user->id.'&amp;pageno=1&amp;action=showpreviouspage';

            $title = fullname($user, true).':'.format_string($this->assignment->name);
            $onclick = "var el = document.getElementById('opennewwindow'); if (el && !el.checked) { return true; } ";
            $onclick .= "this.target='showpage{$userid}'; ";
            $onclick .= "return openpopup('{$linkurl}', 'showpage{$userid}', ";
            $onclick .= "'menubar=0,location=0,scrollbars,resizable,width=700,height=700', 0)";

            $link = '<a title="'.$title.'" href="'.$CFG->wwwroot.$linkurl.'" onclick="'.$onclick.'">'.get_string('openfirstpage','assignment_uploadpdf').'</a>';

            echo '<p>'.$link.'</p>';
            */
        } else {
            $style1 = ' style="border: black 1px solid;"';
            $style2 = ' style="border: black 1px solid; text-align: center;" ';
            echo '<table'.$style1.'><tr><th'.$style1.'>'.get_string('pagenumber','assignment_uploadpdf').'</th>';
            echo '<th'.$style1.'>'.get_string('comment','assignment_uploadpdf').'</th></tr>';
            foreach ($comments as $comment) {
                $linkurl = '/mod/assignment/type/uploadpdf/editcomment.php?a='.$this->assignment->id.'&amp;userid='.$user->id.'&amp;pageno='.$comment->pageno.'&amp;commentid='.$comment->id.'&amp;action=showpreviouspage';

                $title = fullname($user, true).':'.format_string($this->assignment->name).':'.$comment->pageno;
                $onclick = "var el = document.getElementById('opennewwindow'); if (el && !el.checked) { return true; } ";
                $onclick .= "this.target='showpage{$userid}'; ";
                $onclick .= "return openpopup('{$linkurl}', 'showpage{$userid}', ";
                $onclick .= "'menubar=0,location=0,scrollbars,resizable,width=700,height=700', 0)";

                $link = '<a title="'.$title.'" href="'.$CFG->wwwroot.$linkurl.'" onclick="'.$onclick.'">'.$comment->pageno.'</a>';

                echo '<tr><td'.$style2.'>'.$link.'</td>';
                echo '<td'.$style1.'>'.s($comment->rawtext).'</td></tr>';
            }
            echo '</table>';
        }
        echo $OUTPUT->footer();
    }

    function setup_elements(&$mform) {
        global $CFG, $COURSE, $DB;

        $ynoptions = array( 0 => get_string('no'), 1 => get_string('yes'));

        $courseid = 0;
        $assignment_extra = false;
        $update = optional_param('update', 0, PARAM_INT);
        $add = optional_param('add', 0, PARAM_ALPHA);
        if (!empty($update)) {
            if (! $cm = $DB->get_record('course_modules', array('id' => $update) )) {
                print_error('This course module does not exist');
            }
            $courseid = $cm->course;
            $assignment_extra = $DB->get_record('assignment_uploadpdf', array('assignment' => $cm->instance));
        } elseif (!empty($add)) {
            $courseid = required_param('course', PARAM_INT);
        }

        if (!$assignment_extra) {
            $assignment_extra = new stdClass;
            $assignment_extra->template = 0;
            $assignment_extra->coversheet = '';
            $assignment_extra->onlypdf = 1;
            $assignment_extra->checklist = 0;
            $assignment_extra->checklist_percent = 0;
        }

        $mform->addElement('filemanager', 'coversheet', get_string('coversheet','assignment_uploadpdf'), null,
                           array('subdirs' => 0, 'maxbytes' => $COURSE->maxbytes, 'maxfiles' => 1, 'accepted_types' => array('*.pdf')) );
        $mform->setDefault('coversheet', $assignment_extra->coversheet);
        $mform->addHelpButton('coversheet', 'coversheet','assignment_uploadpdf');

        $templates = array();
        $templates[0] = get_string('notemplate','assignment_uploadpdf');
        $templates_data = $DB->get_records_sql('SELECT id, name FROM {assignment_uploadpdf_tmpl} WHERE course = 0 OR course = ?', array($courseid));
        foreach ($templates_data as $td) {
            $templates[$td->id] = $td->name;
        }

        $mform->addElement('select', 'template', get_string('coversheettemplate','assignment_uploadpdf'), $templates);
        $mform->setDefault('template', $assignment_extra->template);
        $mform->addHelpButton('template', 'coversheettemplate','assignment_uploadpdf');

        $edittemplate = $mform->addElement('button', 'edittemplate', get_string('edittemplate', 'assignment_uploadpdf').'...');
        $edittmpl_url = new moodle_url('/mod/assignment/type/uploadpdf/edittemplates.php',array('courseid'=>$courseid));

        $buttonattributes = array('title'=>get_string('edittemplatetip', 'assignment_uploadpdf'), 'onclick'=>'return window.open("'.$edittmpl_url.'", "edittemplates", "menubar=0,location=0,directories=0,toolbar=0,scrollbars,resizable,width=800,height=600");');
        $edittemplate->updateAttributes($buttonattributes);

        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes);
        $choices[0] = get_string('courseuploadlimit') . ' ('.display_size($COURSE->maxbytes).')';
        $mform->addElement('select', 'maxbytes', get_string('maximumsize', 'assignment'), $choices);
        $mform->setDefault('maxbytes', $CFG->assignment_maxbytes);

        $mform->addElement('select', 'onlypdf', get_string('onlypdf', 'assignment_uploadpdf'), $ynoptions);
        $mform->setDefault('onlypdf', $assignment_extra->onlypdf);
        $mform->addHelpButton('onlypdf', 'onlypdf', 'assignment_uploadpdf');

        $options = array();
        for($i = 1; $i <= 40; $i++) {
            $options[$i] = $i;
        }
        $mform->addElement('select', 'var1', get_string("allowmaxfiles", "assignment"), $options);
        $mform->addHelpButton('var1', 'allowmaxfiles', 'assignment');
        $mform->setDefault('var1', 5);

        $mform->addElement('select', 'var2', get_string("allownotes", "assignment"), $ynoptions);
        $mform->addHelpButton('var2', 'allownotes', 'assignment');
        $mform->setDefault('var2', 0);

        $mform->addElement('select', 'var3', get_string("hideintro", "assignment"), $ynoptions);
        $mform->addHelpButton('var3', 'hideintro', 'assignment');
        $mform->setDefault('var3', 0);

        // Checklist elements
        if ($this->import_checklist_plugin()) {
            $checklists = array();
            $checklists[0] = get_string('none');
            $checklist_data = $DB->get_records('checklist', array('course' => $courseid) );
            if ($checklist_data) {
                foreach ($checklist_data as $chk) {
                    $checklists[$chk->id] = $chk->name;
                }
            }

            $mform->addElement('select', 'checklist', get_string('displaychecklist', 'assignment_uploadpdf'), $checklists);
            // $mform->addHelpButton('checklist', 'checklist', 'assignment_uploadpdf'); //FIXME - add some help text?
            $mform->setDefault('checklist', $assignment_extra->checklist);

            $mform->addElement('select', 'checklist_percent', get_string('mustcompletechecklist', 'assignment_uploadpdf'), array( 0 => get_string('no'), 100 => get_string('yes')));
            // $mform->setHelpButton('checklist_percent', 'checklist_percent', 'assignment_uploadpdf'); //FIXME - add some help text?
            $mform->setDefault('checklist_percent', $assignment_extra->checklist_percent);
        } else {
            $mform->addElement('hidden', 'checklist');
            $mform->setDefault('checklist', $assignment_extra->checklist);

            $mform->addElement('hidden', 'checklist_percent');
            $mform->setDefault('checklist_percent', $assignment_extra->checklist_percent);
        }

        $mform->addElement('select', 'emailteachers', get_string("emailteachers", "assignment"), $ynoptions);
        $mform->addHelpButton('emailteachers', 'emailteachers', 'assignment');
        $mform->setDefault('emailteachers', 0);
    }

    function form_data_preprocessing(&$default_values, $form) {
        if ($form->has_instance()) {
            $draftitemid = file_get_submitted_draft_itemid('coversheet');
            file_prepare_draft_area($draftitemid, $form->get_context()->id, 'mod_assignment', 'coversheet', 0, array('subdirs'=>0, 'maxfiles'=>1));
            $default_values['coversheet'] = $draftitemid;
        }
    }

    function add_instance($assignment) {
        global $DB;

        $assignment_extra = new stdClass();
        $assignment_extra->template = $assignment->template;
        $assignment_extra->onlypdf = $assignment->onlypdf;
        $assignment_extra->checklist = $assignment->checklist;
        $assignment_extra->checklist_percent = $assignment->checklist_percent;

        $fs = get_file_storage();
        $cmid = $assignment->coursemodule;
        $draftitemid = $assignment->coversheet;
        $context = get_context_instance(CONTEXT_MODULE, $cmid);
        if ($draftitemid) {
            file_save_draft_area_files($draftitemid, $context->id, 'mod_assignment', 'coversheet', 0, array('subdirs'=>false, 'maxfiles'=>1));
        }
        unset($assignment->coversheet);
        unset($assignment->template);
        unset($assignment->onlypdf);
        unset($assignment->checklist);
        unset($assignment->checklist_percent);

        $newid = parent::add_instance($assignment);

        if ($newid) {
            $assignment_extra->assignment = $newid;
            $DB->insert_record('assignment_uploadpdf', $assignment_extra);
        }

        return $newid;
    }

    function delete_instance($assignment) {
        global $DB;

        $result = true;

        if (! $DB->delete_records_select('assignment_uploadpdf_comment',
                  'assignment_submission IN (
                     SELECT s.id
                     FROM {assignment_submissions} s
                     WHERE s.assignment = ?
                  )', array($assignment->id) )) {
            $result = false;
        }

        if (! $DB->delete_records_select('assignment_uploadpdf_annot',
                  'assignment_submission IN (
                     SELECT s.id
                     FROM {assignment_submissions} s
                     WHERE s.assignment = ?
                  )', array($assignment->id) )) {
            $result = false;
        }

        if (! $DB->delete_records('assignment_uploadpdf', array('assignment' => $assignment->id) )) {
            $result = false;
        }

        $retval = parent::delete_instance($assignment);

        return $retval && $result;
    }

    function update_instance($assignment) {
        global $DB;

        $draftitemid = $assignment->coversheet;
        $template = $assignment->template;
        $onlypdf = $assignment->onlypdf;
        $checklist = $assignment->checklist;
        $checklist_percent = $assignment->checklist_percent;
        unset($assignment->coversheet);
        unset($assignment->template);
        unset($assignment->onlypdf);
        unset($assignment->checklist);
        unset($assignment->checklist_percent);

        $retval = parent::update_instance($assignment);

        if ($retval) {
            $assignmentid = $assignment->id;
            $assignment_extra = $DB->get_record('assignment_uploadpdf', array('assignment' => $assignmentid) );
            if ($assignment_extra) {
                $assignment_extra->template = $template;
                $assignment_extra->onlypdf = $onlypdf;
                $assignment_extra->checklist = $checklist;
                $assignment_extra->checklist_percent = $checklist_percent;
                $DB->update_record('assignment_uploadpdf', $assignment_extra);
            } else {
                // This shouldn't happen (unless an old development version of this plugin has already been used)
                $assignment_extra = new Object;
                $assignment_extra->assignment = $assignmentid;
                $assignment_extra->template = $template;
                $assignment_extra->onlypdf = $onlypdf;
                $assignment_extra->checklist = $checklist;
                $assignment_extra->checklist_percent = $checklist_percent;
                $DB->insert_record('assignment_uploadpdf', $assignment_extra);
            }

            $fs = get_file_storage();
            $cmid = $assignment->coursemodule;
            $context = get_context_instance(CONTEXT_MODULE, $cmid);
            if ($draftitemid) {
                file_save_draft_area_files($draftitemid, $context->id, 'mod_assignment', 'coversheet', 0, array('subdirs'=>false, 'maxfiles'=>1));
            }
        }

        return $retval;
    }

    function cron() {
        global $CFG, $DB;

        if ($lastcron = get_config('uploadpdf','lastcron')) {
            if ($lastcron + 86400 > time()) { /* Only check once a day for images */
                return;
            }
        }

        echo "Clear up images generated for uploadpdf assignments\n";

        $fs = get_file_storage();

        $deletetime = time() - (21 * 86400); // 3 weeks ago - as students can now view feedback online, we need to keep images around for longer
        //FIXME $fs->get_area_files('mod_assignment', 'image');
        $to_clear = $DB->get_records_select('files', "component = 'mod_assignment' AND filearea = 'image' AND timemodified < ?", array($deletetime));
        $tmpl_to_clear = $DB->get_records_select('files', "component = 'mod_assignment' AND filearea = 'previewimage' AND timemodified < ?", array($deletetime));
        $to_clear = array_merge($to_clear, $tmpl_to_clear);

        foreach ($to_clear as $filerecord) {
            $file = $fs->get_file_by_hash($filerecord->pathnamehash);
            if ($file && !$file->is_directory()) {
                $file->delete();
            }
        }

        $lastcron = time(); // Remember when the last cron job ran
        set_config('lastcron', $lastcron, 'uploadpdf');
    }

    function reset_userdata($data) {
        global $CFG, $DB;
        //UT

        if (!empty($data->reset_assignment_submissions)) {
            $DB->delete_records_select('assignment_uploadpdf_comment',
                                  'assignment_submission IN (
                   SELECT s.id
                   FROM {assignment_submissions} s
                   JOIN {assignment} a
                       ON s.assignment = a.id
                   WHERE a.course = ? ) ', array($data->courseid) );

            $DB->delete_records_select('assignment_uploadpdf_annot',
                                  'assignment_submission IN (
                   SELECT s.id
                   FROM {assignment_submissions} s
                   JOIN {assignment} a
                       ON s.assignment = a.id
                   WHERE a.course = ? ) ', array($data->courseid) );
        }

        return parent::reset_userdata($data);
    }

    function import_checklist_plugin() {
        global $CFG, $DB;

        $chk = $DB->get_record('modules', array('name' => 'checklist') );
        if (!$chk) {
            return false;
        }

        if ($chk->version < 2010031200) {
            return false;
        }

        if (!file_exists($CFG->dirroot.'/mod/checklist/locallib.php')) {
            return false;
        }

        require_once($CFG->dirroot.'/mod/checklist/locallib.php');
        return true;
    }
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

?>
