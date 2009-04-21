<?php

function assignment_uploadpdf_upgrade($oldversion) {
    global $CFG;

    if ($oldversion < 2009041700) {
        execute_sql("
        CREATE TABLE `{$CFG->prefix}assignment_uploadpdf` (
        `id` int(10) unsigned NOT NULL auto_increment,
        `assignment` int(10) unsigned NOT NULL default '0',
        `coversheet` varchar(255) NULL default '',
        `template` int(10) unsigned NOT NULL default '0',
        `onlypdf` int(2) unsigned NULL default '1',
        PRIMARY KEY  (`id`),
        KEY `assignment` (`assignment`)
        );
        ");
        
        execute_sql("
        CREATE TABLE `{$CFG->prefix}assignment_uploadpdf_template` (
        `id` int(10) unsigned NOT NULL auto_increment,
        `name` varchar(255) NOT NULL default '',
        `course` int(10) NOT NULL default '0',
        PRIMARY KEY (`id`),
        KEY `course` (`course`)
        );
        ");

        execute_sql("
        CREATE TABLE `{$CFG->prefix}assignment_uploadpdf_template_item` (
        `id` int(10) unsigned NOT NULL auto_increment,
        `template` int(10) unsigned NOT NULL default '0',
        `type` varchar(15) NOT NULL default 'shorttext',
        `xpos` int(10) NOT NULL default '0',
        `ypos` int(10) NOT NULL default '0',
        `width` int(10) NULL default '0',
        `setting` varchar(255) NOT NULL default '',
        PRIMARY KEY (`id`),
        KEY `template` (`template`)
        );
        ");

        execute_sql("
        ALTER TABLE `{$CFG->prefix}assignment_uploadpdf_comment` ADD `colour` varchar(10) NULL default 'yellow';
        ");
    }

    return true;
}
?>