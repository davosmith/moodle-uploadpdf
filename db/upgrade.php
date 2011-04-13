<?php

function xmldb_assignment_uploadpdf_upgrade($oldversion=0) {
    global $CFG, $THEME, $DB;

    $dbman = $DB->get_manager();
    $result = true;

    if ($result && $oldversion < 2009041700) {
        $table =  new xmldb_table('assignment_uploadpdf');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('assignment', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('coversheet', XMLDB_TYPE_CHAR, '255', null, null, null, '');
        $table->add_field('template', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('onlypdf', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, null, null, '1');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('assignment', XMLDB_INDEX_UNIQUE, array('assignment'));
        $table->add_index('template', XMLDB_INDEX_NOTUNIQUE, array('template'));
        $dbman->create_table($table);

        $table =  new xmldb_table('assignment_uploadpdf_template');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, '');
        $table->add_field('course', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('course', XMLDB_INDEX_NOTUNIQUE, array('course'));
        $dbman->create_table($table);

        $table =  new xmldb_table('assignment_uploadpdf_template_item');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('template', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('type', XMLDB_TYPE_CHAR, '15', null, XMLDB_NOTNULL, null, 'shorttext');
        $table->add_field('xpos', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('ypos', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('width', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('setting', XMLDB_TYPE_CHAR, '255', null, null, null, '');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('template', XMLDB_INDEX_NOTUNIQUE, array('template'));
        $dbman->create_table($table);

        $table = new xmldb_table('assignment_uploadpdf_comment');
        $field = new xmldb_field('colour');
        $field->set_attributes(XMLDB_TYPE_CHAR, '10', null, null, null, 'yellow', null);
        $dbman->add_field($table, $field);

        upgrade_plugin_savepoint($result, 2009041700, 'assignment', 'uploadpdf');
    }

    if ($result && $oldversion < 2009111800) {
        $table =  new xmldb_table('assignment_uploadpdf_quicklist');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('text', XMLDB_TYPE_CHAR, '255', null, null, null, '');
        $table->add_field('width', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('colour', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'yellow');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));
        $dbman->create_table($table);

        upgrade_plugin_savepoint($result, 2009111800, 'uploadpdf');
    }

    if ($result && $oldversion < 2009112800) {
        $table =  new xmldb_table('assignment_uploadpdf_annotation');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('assignment_submission', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('startx', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('starty', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('endx', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('endy', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('pageno', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('colour', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'red');
        $table->add_field('type', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'line');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('assignment_submission', XMLDB_INDEX_NOTUNIQUE, array('assignment_submission'));
        $table->add_index('assignment_submission_pageno', XMLDB_INDEX_NOTUNIQUE, array('assignment_submission','pageno'));
        $dbman->create_table($table);

        upgrade_plugin_savepoint($result, 2009112800, 'assignment', 'uploadpdf');
    }

    if ($result && $oldversion < 2009120100) {
        // Rename the tables to fit within Oracle's 30 char limits (including 2 char prefix)
        $table = new xmldb_table('assignment_uploadpdf_template');
        $dbman->rename_table($table, 'assignment_uploadpdf_tmpl');

        $table = new xmldb_table('assignment_uploadpdf_template_item');
        $dbman->rename_table($table, 'assignment_uploadpdf_tmplitm');

        $table = new xmldb_table('assignment_uploadpdf_quicklist');
        $dbman->rename_table($table, 'assignment_uploadpdf_qcklist');

        $table = new xmldb_table('assignment_uploadpdf_annotation');
        $dbman->rename_table($table, 'assignment_uploadpdf_annot');


        // Change the data type of the text field from 'char' to 'text' (removing 255 char limit)
        $table = new xmldb_table('assignment_uploadpdf_qcklist');
        $field = new xmldb_field('text');
        $field->set_attributes(XMLDB_TYPE_TEXT. 'medium', null, null, null, '');
        $dbman->change_field_type($table, $field);

        // Remove 255 char limit from comments
        $table = new xmldb_table('assignment_uploadpdf_comment');
        $field = new xmldb_field('rawtext');
        $field->set_attributes(XMLDB_TYPE_TEXT. 'medium', null, null, null, '');
        $dbman->change_field_type($table, $field);

        // Remove 255 char limit from coversheet path
        $table = new xmldb_table('assignment_uploadpdf');
        $field = new xmldb_field('coversheet');
        $field->set_attributes(XMLDB_TYPE_TEXT. 'medium', null, null, null, '');
        $dbman->change_field_type($table, $field);

        upgrade_plugin_savepoint($result, 2009120100, 'assignment', 'uploadpdf');
    }

    if ($result && $oldversion < 2010031300) {
        // Add new fields to allow linking with the checklist module
        $table = new xmldb_table('assignment_uploadpdf');
        $field = new xmldb_field('checklist');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, '0', 'onlypdf');
        $dbman->add_field($table, $field);

        $field = new xmldb_field('checklist_percent');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, '0', 'checklist');
        $dbman->add_field($table, $field);

        upgrade_plugin_savepoint($result, 2010031300, 'assignment', 'uploadpdf');
    }

    if ($result && $oldversion < 2011040400) {
        $table = new xmldb_table('assignment_uploadpdf_annot');
        $field = new xmldb_field('path', XMLDB_TYPE_TEXT, 'medium', null, null, null, null, 'endy');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint($result, 2011040400, 'assignment', 'uploadpdf');
    }

    return $result;
}