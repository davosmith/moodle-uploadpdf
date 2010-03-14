CREATE TABLE IF NOT EXISTS `prefix_assignment_uploadpdf_comment` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `assignment_submission` int(10) unsigned NOT NULL default '0',
  `posx` int(10) NOT NULL default '0',
  `posy` int(10) NOT NULL default '0',
  `width` int(10) NOT NULL default '0',
  `rawtext` MEDIUMTEXT NULL default '',
  `pageno` int(10) NOT NULL default '0',
  `colour` varchar(10) NULL default 'yellow',
  PRIMARY KEY  (`id`),
  KEY `assignment_submission` (`assignment_submission`)
) ; 

CREATE TABLE IF NOT EXISTS `prefix_assignment_uploadpdf` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `assignment` int(10) unsigned NOT NULL default '0',
  `coversheet` MEDIUMTEXT NULL default '',
  `template` int(10) unsigned NOT NULL default '0',
  `onlypdf` int(2) unsigned NULL default '1',
  `checklist` int(10) unsigned NULL default '0',
  `checklist_percent` int(10) unsigned NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `assignment` (`assignment`)
) ; 

CREATE TABLE IF NOT EXISTS `prefix_assignment_uploadpdf_tmpl` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  `course` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY (`id`),
  KEY `course` (`course`)
) ;

CREATE TABLE IF NOT EXISTS `prefix_assignment_uploadpdf_tmplitm` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `template` int(10) unsigned NOT NULL default '0',
  `type` varchar(15) NOT NULL default 'shorttext',
  `xpos` int(10) NOT NULL default '0',
  `ypos` int(10) NOT NULL default '0',
  `width` int(10) NULL default '0',
  `setting` varchar(255) NOT NULL default '',
  PRIMARY KEY (`id`),
  KEY `template` (`template`)
) ;

CREATE TABLE IF NOT EXISTS `prefix_assignment_uploadpdf_qcklist` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `userid` int(10) unsigned NOT NULL default '0',
  `text` MEDIUMTEXT default '',
  `width` int(10) NOT NULL default '0',
  `colour` varchar(10) NULL default 'yellow',
  PRIMARY KEY (`id`),
  KEY `userid` (`userid`)
) ;

CREATE TABLE IF NOT EXISTS `prefix_assignment_uploadpdf_annot` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `assignment_submission` int(10) unsigned NOT NULL default '0',
  `startx` int(10) NOT NULL default '0',
  `starty` int(10) NOT NULL default '0',
  `endx` int(10) NOT NULL default '0',
  `endy` int(10) NOT NULL default '0',
  `pageno` int(10) NOT NULL default '0',
  `colour` varchar(10) NULL default 'red',
  `type` varchar(10) NULL default 'line',
  PRIMARY KEY  (`id`),
  KEY `assignment_submission` (`assignment_submission`)
) ; 

