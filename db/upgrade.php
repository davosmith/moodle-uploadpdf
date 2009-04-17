<?php

function xmldb_assignment_uploadpdf_upgrade($oldversion=0) {
    die 'Got here sucessfully, now please remove this line';

    
    global $CFG, $THEME, $db;

    $result = true;

    if ($result && $oldversion < 2009041700) {
        $table =  new XMLDBTable('assignment_uploadpdf');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('assignment', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('coversheet', XMLDB_TYPE_CHAR, '255', null, null, null, null, null, null);
        $table->addFieldInfo('template',, XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addIndexInfo('assignment', XMLDB_INDEX_UNIQUE, array('assignment'));
        $table->addIndexInfo('template', XMLDB_INDEX_NOTUNIQUE, array('template'));
        
        $result = $result && create_table($table);

        $table =  new XMLDBTable('assignment_uploadpdf_template');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('config', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null, null);
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        
        $result = $result && create_table($table);
    }

    return $result;
}