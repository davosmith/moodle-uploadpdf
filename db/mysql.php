<?php

function assignment_uploadpdf_upgrade($oldversion) {
    global $CFG;

    if ($oldversion < 2009041700) {
        execute_sql("
        CREATE TABLE `{$CFG->prefix}assignment_uploadpdf` (
        `id` int(10) unsigned NOT NULL auto_increment,
        `assignment` int(10) unsigned NOT NULL default '0',
        `coversheet` text NULL default '',
        `template` int(10) unsigned NOT NULL default '0',
        PRIMARY KEY  (`id`),
        KEY `assignment` (`assignment`)
        );
        ");
        
        execute_sql("
        CREATE TABLE `{$CFG->prefix}assignment_uploadpdf_template` (
        `id` int(10) unsigned NOT NULL auto_increment,
        `name` text NOT NULL default '',
        `config` text NOT NULL default '',
        PRIMARY KEY (`id`)
        );
        ");
    }

    return true;
}
?>