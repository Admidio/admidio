DROP TABLE IF EXISTS `adm_ankuendigungen`;
CREATE TABLE `adm_ankuendigungen` (
  `aa_id` int(7) unsigned NOT NULL auto_increment,
  `aa_ag_shortname` varchar(10) NOT NULL default '',
  `aa_global` tinyint(1) unsigned NOT NULL default '0',
  `aa_datum` datetime NOT NULL default '0000-00-00 00:00:00',
  `aa_ueberschrift` varchar(50) NOT NULL default '',
  `aa_beschreibung` text,
  `aa_au_id` smallint(6) unsigned NOT NULL default '0',
  `aa_timestamp` datetime NOT NULL default '0000-00-00 00:00:00',
  `aa_last_change` datetime default NULL,
  `aa_last_change_id` int(7) unsigned default NULL,
  PRIMARY KEY  (`aa_id`)
) TYPE=MyISAM AUTO_INCREMENT=1 ;
DROP TABLE IF EXISTS `adm_gruppierung`;
CREATE TABLE `adm_gruppierung` (
  `ag_id` tinyint(4) NOT NULL auto_increment,
  `ag_longname` varchar(60) NOT NULL default '',
  `ag_shortname` varchar(10) NOT NULL default '',
  `ag_mother` varchar(10) default NULL,
  `ag_mail_extern` tinyint(1) unsigned NOT NULL default '0',
  `ag_homepage` varchar(30) NULL default 'www.admidio.org',
  `ag_mail_attachment_size` smallint unsigned NOT NULL default '1024',
  `ag_enable_rss` tinyint(1) unsigned NOT NULL default '1',
  `ag_bbcode` tinyint( 1 ) unsigned NOT NULL default '1',
  PRIMARY KEY  (`ag_id`),
  UNIQUE KEY `bg_shortname` (`ag_shortname`)
) TYPE=MyISAM COMMENT='Gruppierung' AUTO_INCREMENT=1 ;
DROP TABLE IF EXISTS `adm_mitglieder`;
CREATE TABLE `adm_mitglieder` (
  `am_id` int(11) NOT NULL auto_increment,
  `am_ar_id` int(7) unsigned NOT NULL default '0',
  `am_au_id` int(7) unsigned NOT NULL default '0',
  `am_start` date NOT NULL default '0000-00-00',
  `am_ende` date NOT NULL default '0000-00-00',
  `am_valid` tinyint(1) unsigned NOT NULL default '0',
  `am_leiter` tinyint(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (`am_id`),
  UNIQUE KEY `am_ar_au_id` (`am_ar_id`,`am_au_id`),
  KEY `am_ar_id` (`am_ar_id`),
  KEY `am_au_id` (`am_au_id`)
) TYPE=MyISAM COMMENT='Benutzer und Ihre Rollen' AUTO_INCREMENT=1 ;
DROP TABLE IF EXISTS `adm_new_user`;
CREATE TABLE `adm_new_user` (
  `anu_id` int(7) unsigned NOT NULL auto_increment,
  `anu_ag_shortname` varchar(10) NOT NULL default '',
  `anu_name` varchar(30) NOT NULL default '',
  `anu_vorname` varchar(30) NOT NULL default '',
  `anu_mail` varchar(50) NOT NULL default '',
  `anu_login` varchar(20) NOT NULL default '',
  `anu_password` varchar(35) NOT NULL default '',
  PRIMARY KEY  (`anu_id`)
) TYPE=MyISAM AUTO_INCREMENT=1 ;
DROP TABLE IF EXISTS `adm_photo`;
CREATE TABLE `adm_photo` (
  `ap_id` int(11) unsigned NOT NULL auto_increment,
  `ap_number` int(11) NOT NULL default '0',
  `ap_name` varchar(50) NOT NULL default '',
  `ap_begin` date NOT NULL default '0000-00-00',
  `ap_end` date NOT NULL default '0000-00-00',
  `ap_photographers` varchar(100) default NULL,
  `ap_online_since` datetime default NULL,
  `ap_last_change` datetime default NULL,
  `ap_ag_shortname` varchar(10) NOT NULL default '',
  PRIMARY KEY  (`ap_id`)
) TYPE=MyISAM AUTO_INCREMENT=1 ;
DROP TABLE IF EXISTS `adm_rolle`;
CREATE TABLE `adm_rolle` (
  `ar_id` int(7) unsigned NOT NULL auto_increment,
  `ar_ag_shortname` varchar(10) NOT NULL default '',
  `ar_funktion` varchar(30) NOT NULL default '',
  `ar_beschreibung` varchar(255) default NULL,
  `ar_r_moderation` tinyint(1) unsigned NOT NULL default '0',
  `ar_r_termine` tinyint(1) unsigned NOT NULL default '0',
  `ar_r_user_bearbeiten` tinyint(1) unsigned NOT NULL default '0',
  `ar_r_foto` tinyint(1) unsigned NOT NULL default '0',
  `ar_r_download` tinyint(1) unsigned NOT NULL default '0',
  `ar_r_mail_logout` tinyint(1) unsigned NOT NULL default '0',
  `ar_r_mail_login` tinyint(1) unsigned NOT NULL default '0',
  `ar_r_locked` tinyint(1) unsigned NOT NULL default '0',
  `ar_gruppe` tinyint(1) unsigned NOT NULL default '0',
  `ar_datum_von` date default NULL,
  `ar_zeit_von` time default NULL,
  `ar_datum_bis` date default NULL,
  `ar_zeit_bis` time default NULL,
  `ar_wochentag` tinyint(1) default NULL,
  `ar_ort` varchar(30) default NULL,
  `ar_max_mitglieder` smallint(3) unsigned default NULL,
  `ar_beitrag` float unsigned default NULL,
  `ar_last_change` datetime default NULL,
  `ar_last_change_id` int(7) unsigned default NULL,
  `ar_valid` tinyint(1) unsigned NOT NULL default '1',
  PRIMARY KEY  (`ar_id`)
) TYPE=MyISAM COMMENT='Funktionen die ein User haben kann' AUTO_INCREMENT=1 ;
DROP TABLE IF EXISTS `adm_session`;
CREATE TABLE `adm_session` (
  `as_id` int(10) unsigned NOT NULL auto_increment,
  `as_au_id` int(7) unsigned NOT NULL default '0',
  `as_session` varchar(35) NOT NULL default '',
  `as_datetime` datetime NOT NULL default '0000-00-00 00:00:00',
  `as_long_login` tinyint(1) unsigned NOT NULL default '0',
  `as_ag_shortname` varchar(10) NOT NULL default '',
  `as_list_sql` text,
  PRIMARY KEY  (`as_id`),
  KEY `as_au_id` (`as_au_id`),
  KEY `as_session` (`as_session`)
) TYPE=MyISAM AUTO_INCREMENT=2986 ;
DROP TABLE IF EXISTS `adm_termine`;
CREATE TABLE `adm_termine` (
  `at_id` int(7) unsigned NOT NULL auto_increment,
  `at_ag_shortname` varchar(10) NOT NULL default '',
  `at_global` tinyint(1) unsigned NOT NULL default '0',
  `at_von` datetime NOT NULL default '0000-00-00 00:00:00',
  `at_bis` datetime default '0000-00-00 00:00:00',
  `at_beschreibung` text,
  `at_ort` varchar(100) default NULL,
  `at_ueberschrift` varchar(50) NOT NULL default '',
  `at_au_id` smallint(6) unsigned NOT NULL default '0',
  `at_timestamp` datetime NOT NULL default '0000-00-00 00:00:00',
  `at_last_change` datetime default NULL,
  `at_last_change_id` int(7) unsigned default NULL,
  PRIMARY KEY  (`at_id`)
) TYPE=MyISAM AUTO_INCREMENT=1 ;
DROP TABLE IF EXISTS `adm_user`;
CREATE TABLE `adm_user` (
  `au_id` int(7) unsigned NOT NULL auto_increment,
  `au_name` varchar(30) NOT NULL default '',
  `au_vorname` varchar(30) NOT NULL default '',
  `au_adresse` varchar(50) default NULL,
  `au_plz` varchar(10) default NULL,
  `au_ort` varchar(30) default NULL,
  `au_land` varchar(30) default NULL,
  `au_tel1` varchar(20) default NULL,
  `au_tel2` varchar(20) default NULL,
  `au_mobil` varchar(20) default NULL,
  `au_fax` varchar(20) default NULL,
  `au_geburtstag` date default NULL,
  `au_mail` varchar(50) default NULL,
  `au_weburl` varchar(50) default NULL,
  `au_login` varchar(20) default NULL,
  `au_password` varchar(35) default NULL,
  `au_last_login` datetime default NULL,
  `au_act_login` datetime default NULL,
  `au_num_login` smallint(5) unsigned NOT NULL default '0',
  `au_invalid_login` datetime default NULL,
  `au_num_invalid` tinyint(3) unsigned NOT NULL default '0',
  `au_last_change` datetime default NULL,
  `au_last_change_id` int(7) unsigned default NULL,
  PRIMARY KEY  (`au_id`),
  UNIQUE KEY `au_login` (`au_login`)
) TYPE=MyISAM COMMENT='Tabelle mit den Userinformationen' AUTO_INCREMENT=1 ;
DROP TABLE IF EXISTS `adm_user_data`;
CREATE TABLE `adm_user_data` (
  `aud_id` int(11) NOT NULL auto_increment,
  `aud_au_id` int(7) NOT NULL default '0',
  `aud_auf_id` int(11) NOT NULL default '0',
  `aud_value` varchar(255) default NULL,
  PRIMARY KEY  (`aud_id`),
  UNIQUE KEY `aud_au_auf_id` (`aud_au_id`,`aud_auf_id`),
  KEY `aud_au_id` (`aud_au_id`),
  KEY `aud_auf_id` (`aud_auf_id`)
) TYPE=MyISAM AUTO_INCREMENT=1 ;
DROP TABLE IF EXISTS `adm_user_field`;
CREATE TABLE `adm_user_field` (
  `auf_id` int(11) NOT NULL auto_increment,
  `auf_ag_shortname` varchar(10) NULL default '',
  `auf_type` varchar(10) NOT NULL default '',
  `auf_name` varchar(100) NOT NULL default '',
  `auf_description` varchar(255) default NULL,
  `auf_locked` tinyint(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (`auf_id`)
) TYPE=MyISAM AUTO_INCREMENT=1 ;