CREATE TABLE IF NOT EXISTS `prefix_assignment_uploadpdf_comment` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `assignment_submission` int(10) unsigned NOT NULL default '0',
  `posx` int(10) NOT NULL default '0',
  `posy` int(10) NOT NULL default '0',
  `width` int(10) NOT NULL default '0',
  `rawtext` varchar(255) NOT NULL default '',
  `pageno` int(10) NOT NULL default '0',
  `colour` varchar(10) NULL default 'yellow',
  PRIMARY KEY  (`id`),
  KEY `assignment_submission` (`assignment_submission`)
) ; 

CREATE TABLE IF NOT EXISTS `prefix_assignment_uploadpdf` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `assignment` int(10) unsigned NOT NULL default '0',
  `coversheet` varchar(255) NULL default '',
  `template` int(10) unsigned NOT NULL default '0',
  `onlypdf` int(2) unsigned NULL default '1',
  PRIMARY KEY  (`id`),
  KEY `assignment` (`assignment`)
) ; 

CREATE TABLE IF NOT EXISTS `prefix_assignment_uploadpdf_template` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  `course` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY (`id`),
  KEY `course` (`course`)
) ;

CREATE TABLE IF NOT EXISTS `prefix_assignment_uploadpdf_template_item` (
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

CREATE TABLE IF NOT EXISTS `prefix_assignment_uploadpdf_quicklist` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `userid` int(10) unsigned NOT NULL default '0',
  `text` varchar(255) default '',
  `width` int(10) NOT NULL default '0',
  `colour` varchar(10) NULL default 'yellow',
  PRIMARY KEY (`id`),
  KEY `userid` (`userid`)
) ;