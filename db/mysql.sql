CREATE TABLE IF NOT EXISTS `prefix_assignment_uploadpdf_comment` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `assignment_submission` int(10) unsigned NOT NULL default '0',
  `posx` int(10) NOT NULL default '0',
  `posy` int(10) NOT NULL default '0',
  `width` int(10) NOT NULL default '0',
  `rawtext` text NOT NULL default '',
  `pageno` int(10) NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `assignment_submission` (`assignment_submission`)
) ; 

CREATE TABLE IF NOT EXISTS `prefix_assignment_uploadpdf` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `assignment` int(10) unsigned NOT NULL default '0',
  `coversheet` text NULL default '',
  `template` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `assignment` (`assignment`)
) ; 

CREATE TABLE IF NOT EXISTS `prefix_assignment_uploadpdf_template` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `name` text NOT NULL default '',
  `config` text NOT NULL default '',
  PRIMARY KEY (`id`)
) ;