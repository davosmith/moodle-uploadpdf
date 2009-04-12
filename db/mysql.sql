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