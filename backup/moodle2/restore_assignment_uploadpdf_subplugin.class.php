<?php

class restore_assignment_uploadpdf_subplugin extends restore_subplugin {
    protected function define_assignment_subplugin_structure() {
        $paths = array();

        $elename = $this->get_namefor('assignment_uploadpdf');
        $elepath = $this->get_pathfor('/assignment_uploadpdf');
        $paths[] = new restore_path_element($elename, $elepath);

        $elename = $this->get_namefor('assignment_uploadpdf_tmpl');
        $elepath = $this->get_pathfor('/assignment_uploadpdf/assignment_uploadpdf_tmpl');
        $paths[] = new restore_path_element($elename, $elepath);

        $elename = $this->get_namefor('assignment_uploadpdf_tmplitm');
        $elepath = $this->get_pathfor('/assignment_uploadpdf/assignment_uploadpdf_tmpl/assignment_uploadpdf_tmplitm');
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths;
    }

    protected function define_submission_subplugin_structure() {
        $paths = array();

        $elename = $this->get_namefor('assignment_uploadpdf_comment');
        $elepath = $this->get_pathfor('/comments/assignment_uploadpdf_comment');
        $paths[] = new restore_path_element($elename, $elepath);

        $elename = $this->get_namefor('assignment_uploadpdf_annot');
        $elepath = $this->get_pathfor('/annotations/assignment_uploadpdf_annot');
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths;
    }

    public function process_assignment_uploadpdf_assignment_uploadpdf($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->assignment = $this->get_new_parentid('assignment');
        $newitemid = $DB->insert_record('assignment_uploadpdf', $data);

        $this->set_mapping('assignment_uploadpdf_assignment_uploadpdf', $oldid, $newitemid, true); // Needs files restoring as well
    }

    public function process_assignment_uploadpdf_assignment_uploadpdf_tmpl($data) {
        global $DB;

        $data = (object)$data;
        if ($tmplid = $this->get_mapping('assignment_uploadpdf_assignment_uploadpdf_tmpl', $data->id)) {
            // This template has already been restored
        } else {
            $oldid = $data->id;
            if ($data->course != 0) {
                $data->course = $this->task->get_courseid();
            }
            $tmplid = $DB->insert_record('assignment_uploadpdf_tmpl', $data);
            $this->set_mapping('assignment_uploadpdf_assignment_uploadpdf_tmpl', $oldid, $tmplid);
        }

        $assignment_uploadpdf = $DB->get_record('assignment_uploadpdf', array('id' => $this->get_new_parentid('assignment_uploadpdf_assignment_uploadpdf')));
        if ($assignment_uploadpdf) {
            $assignment_uploadpdf->template = $tmplid;
            $DB->update_record('assignment_uploadpdf', $assignment_uploadpdf);
        }
    }

    public function process_assignment_uploadpdf_assignment_uploadpdf_tmplitm($data) {
        global $DB;

        $data = (object)$data;
        if ($this->get_mapping('assignment_uploadpdf_assignment_uploadpdf_tmplitm', $data->id)) {
            // Template item already restored
        } else {
            $oldid = $data->id;
            $data->template = $this->get_new_parentid('assignment_uploadpdf_assignment_uploadpdf_tmpl');
            $newid = $DB->insert_record('assignment_uploadpdf_tmplitm', $data);
            $this->set_mapping('assignment_uploadpdf_assignment_uploadpdf_tmplitm', $oldid, $newid);
        }
    }

    public function process_assignment_uploadpdf_assignment_uploadpdf_comment($data) {
        global $DB;

        $data = (object)$data;
        $data->assignment_submission = $this->get_new_parentid('assignment_submission');
        $DB->insert_record('assignment_uploadpdf_comment', $data);
    }

    public function process_assignment_uploadpdf_assignment_uploadpdf_annot($data) {
        global $DB;

        $data = (object)$data;
        $data->assignment_submission = $this->get_new_parentid('assignment_submission');
        $DB->insert_record('assignment_uploadpdf_annot', $data);
    }

    public function after_execute_assignment() {
        $this->add_related_files('mod_assignment', 'coversheet', null); // No itemid for the coversheet
    }

    public function after_execute_submission() {
        $this->add_related_files('mod_assignment', 'submissionfinal', 'assignment_submission');
        $this->add_related_files('mod_assignment', 'response', 'assignment_submission');
    }
}

?>