<?php

function xmldb_assignment_uploadpdf_upgrade($oldversion=0) {
    die 'Got here sucessfully, now please remove this line';

    
    global $CFG, $THEME, $db;

    $result = true;

    if ($result && $oldversion < 2009041700) {
        $table =  new XMLDBTable('assignment_uploadpdf');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('assignment', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('coversheet', XMLDB_TYPE_CHAR, '255', null, XMLDB_NULL, null, null, null, '');
        $table->addFieldInfo('template',, XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
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
        $table->addFieldInfo('width', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NULL, null, null, null, '0');
        $table->addFieldInfo('setting', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null, '');
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addIndexInfo('template', XMLDB_INDEX_NOTUNIQUE, array('template'));
        $result = $result && create_table($table);

        $table = new XMLDBTable('assignment_uploadpdf_comment');
        $field = new XMLDBField('colour');
        $field->setAttributes(XMLDB_TYPE_CHAR, '10', null, XMLDB_NULL, null, null, null, null, 'yellow');
        $result = $result && add_field($table, $field);
    }

    return $result;
}