<?php

// NOTE: See mod/assignment/type/offline/backup/moodle2/backup_assignment_offline_subplugin.class.php for more details

class backup_assignment_uploadpdf_subplugin extends backup_subplugin {

    protected function define_assignment_subplugin_structure() {

        $subplugin = $this->get_subplugin_element(null, '/assignment/assignmenttype', 'uploadpdf');

        $assuploadpdf = new backup_nested_element($this->get_recommended_name());
        $extra = new backup_nested_element('assignment_uploadpdf', array('id'),
                                           array('template', 'onlypdf', 'checklist', 'checklist_percent'));
        $template = new backup_nested_element('assignment_uploadpdf_tmpl', array('id'),
                                              array('name', 'course'));
        $template_item = new backup_nested_element('assignment_uploadpdf_tmplitm', array('id'),
                                                   array('type','xpos','ypos','width','setting'));

        $subplugin->add_child($assuploadpdf);
        $assuploadpdf->add_child($extra);
        $extra->add_child($template);
        $template->add_child($template_item);

        $extra->set_source_table('assignment_uploadpdf', array('assignment' => '/assignment/id'));
        $template->set_source_table('assignment_uploadpdf_tmpl', array('id' => '../template'));
        $template_item->set_source_table('assignment_uploadpdf_tmplitm', array('template' => backup::VAR_PARENTID));

        $extra->annotate_files('mod_assignment', 'coversheet', null); // No itemid for the coversheet

        return $subplugin;
    }

    protected function define_submission_subplugin_structure() {

        $subplugin = $this->get_subplugin_element(null, '/assignment/assignmenttype', 'uploadpdf');

        $assuploadpdf = new backup_nested_element($this->get_recommended_name());
        $comments = new backup_nested_element('comments');
        $comment = new backup_nested_element('assignment_uploadpdf_comment', null,
                                             array('posx', 'posy', 'width', 'rawtext', 'pageno', 'colour'));
        $annotations = new backup_nested_element('annotations');
        $annotation = new backup_nested_element('assignment_uploadpdf_annot', null,
                                                array('startx', 'starty', 'endx', 'endy', 'path', 'pageno', 'colour', 'type'));

        $subplugin->add_child($assuploadpdf);
        $assuploadpdf->add_child($comments);
        $comments->add_child($comment);
        $assuploadpdf->add_child($annotations);
        $annotations->add_child($annotation);

        $comment->set_source_table('assignment_uploadpdf_comment', array('assignment_submission' => backup::VAR_PARENTID));
        $annotation->set_source_table('assignment_uploadpdf_annot', array('assignment_submission' => backup::VAR_PARENTID));

        $assuploadpdf->annotate_files('mod_assignment', 'submissionfinal', backup::VAR_PARENTID);
        $assuploadpdf->annotate_files('mod_assignment', 'response', backup::VAR_PARENTID);

        return $subplugin;
    }
}

?>