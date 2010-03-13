<?php

function assignment_type_uploadpdf_upgrade($oldversion) {
    return true;
}

function assignment_uploadpdf_upgrade($oldversion) {
    global $CFG, $THEME, $db;

    $result = true;

    if ($result && $oldversion < 2009041700) {
        $table =  new XMLDBTable('assignment_uploadpdf');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('assignment', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('coversheet', XMLDB_TYPE_CHAR, '255', null, null, null, null, null, '');
        $table->addFieldInfo('template', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('onlypdf', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, null, null, null, null, '1');
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addIndexInfo('assignment', XMLDB_INDEX_UNIQUE, array('assignment'));
        $table->addIndexInfo('template', XMLDB_INDEX_NOTUNIQUE, array('template'));
        $result = $result && create_table($table);

        $table =  new XMLDBTable('assignment_uploadpdf_template');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null, '');
        $table->addFieldInfo('course', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, '0');
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addIndexInfo('course', XMLDB_INDEX_NOTUNIQUE, array('course'));
        $result = $result && create_table($table);

        $table =  new XMLDBTable('assignment_uploadpdf_template_item');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('template', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('type', XMLDB_TYPE_CHAR, '15', null, XMLDB_NOTNULL, null, null, null, 'shorttext');
        $table->addFieldInfo('xpos', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('ypos', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('width', XMLDB_TYPE_INTEGER, '10', null, null, null, null, null, '0');
        $table->addFieldInfo('setting', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null, '');
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addIndexInfo('template', XMLDB_INDEX_NOTUNIQUE, array('template'));
        $result = $result && create_table($table);

        $table = new XMLDBTable('assignment_uploadpdf_comment');
        $field = new XMLDBField('colour');
        $field->setAttributes(XMLDB_TYPE_CHAR, '10', null, null, null, null, null, 'yellow', null);
        $result = $result && add_field($table, $field);
    }

    if ($result && $oldversion < 2009111800) {
        $table =  new XMLDBTable('assignment_uploadpdf_quicklist');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('text', XMLDB_TYPE_CHAR, '255', null, null, null, null, null, '');
        $table->addFieldInfo('width', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('colour', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null, null, 'yellow');
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addIndexInfo('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));
        $result = $result && create_table($table);
    }

    if ($result && $oldversion < 2009112800) {
        $table =  new XMLDBTable('assignment_uploadpdf_annotation');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('assignment_submission', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('startx', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('starty', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('endx', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('endy', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('pageno', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('colour', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null, null, 'red');
        $table->addFieldInfo('type', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null, null, 'line');

        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addIndexInfo('assignment_submission', XMLDB_INDEX_NOTUNIQUE, array('assignment_submission'));
        $table->addIndexInfo('assignment_submission_pageno', XMLDB_INDEX_NOTUNIQUE, array('assignment_submission','pageno'));
        $result = $result && create_table($table);
    }

    if ($result && $oldversion < 2009120100) {
        // Rename the tables to fit within Oracle's 30 char limits (including 2 char prefix)
        $table = new XMLDBTable('assignment_uploadpdf_template');
        $result = $result && rename_table($table, 'assignment_uploadpdf_tmpl');

        $table = new XMLDBTable('assignment_uploadpdf_template_item');
        $result = $result && rename_table($table, 'assignment_uploadpdf_tmplitm');

        $table = new XMLDBTable('assignment_uploadpdf_quicklist');
        $result = $result && rename_table($table, 'assignment_uploadpdf_qcklist');

        $table = new XMLDBTable('assignment_uploadpdf_annotation');
        $result = $result && rename_table($table, 'assignment_uploadpdf_annot');

        
        // Change the data type of the text field from 'char' to 'text' (removing 255 char limit)
        $table = new XMLDBTable('assignment_uploadpdf_qcklist');
        $field = new XMLDBField('text');
        $field->setAttributes(XMLDB_TYPE_TEXT. 'medium', null, null, null, null, null, '');
        $result = $result && change_field_type($table, $field);

        // Remove 255 char limit from comments
        $table = new XMLDBTable('assignment_uploadpdf_comment');
        $field = new XMLDBField('rawtext');
        $field->setAttributes(XMLDB_TYPE_TEXT. 'medium', null, null, null, null, null, '');
        $result = $result && change_field_type($table, $field);

        // Remove 255 char limit from coversheet path
        $table = new XMLDBTable('assignment_uploadpdf');
        $field = new XMLDBField('coversheet');
        $field->setAttributes(XMLDB_TYPE_TEXT. 'medium', null, null, null, null, null, '');
        $result = $result && change_field_type($table, $field);
    }

    if ($result && $oldversion < 2010031300) {
        // Add new fields to allow linking with the checklist module
        $table = new XMLDBTable('assignment_uploadpdf');
        $field = new XMLDBField('checklist');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, '0', 'onlypdf');
        $result = $result && add_field($table, $field);
        
        $field = new XMLDBField('checkist_percent');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, '0', 'checklist');
        $result = $result && add_field($table, $field);
    }

    return $result;
}
?>