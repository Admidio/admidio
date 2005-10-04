ALTER TABLE `adm_rolle` ADD `ar_r_foto` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT '0' AFTER `ar_r_user_bearbeiten` ;
ALTER TABLE `adm_rolle` ADD `ar_r_download` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT '0' AFTER `ar_r_user_bearbeiten` ;
ALTER TABLE `adm_rolle` ADD `ar_r_locked` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT '0' AFTER `ar_r_mail_login` ;
ALTER TABLE `adm_rolle` DROP `ar_r_liste_anzeigen`;
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
CREATE TABLE `adm_user_field` (
  `auf_id` int(11) NOT NULL auto_increment,
  `auf_ag_shortname` varchar(10) NULL default '',
  `auf_type` varchar(10) NOT NULL default '',
  `auf_name` varchar(100) NOT NULL default '',
  `auf_description` varchar(255) default NULL,
  `auf_locked` tinyint(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (`auf_id`)
) TYPE=MyISAM AUTO_INCREMENT=1 ;
INSERT INTO adm_user_field (auf_ag_shortname, auf_type, auf_name, auf_description) VALUES (NULL, 'MESSENGER', 'AIM', 'AOL Instant Messenger');
INSERT INTO adm_user_field (auf_ag_shortname, auf_type, auf_name, auf_description) VALUES (NULL, 'MESSENGER', 'ICQ', 'ICQ');
INSERT INTO adm_user_field (auf_ag_shortname, auf_type, auf_name, auf_description) VALUES (NULL, 'MESSENGER', 'MSN', 'MSN Messenger');
INSERT INTO adm_user_field (auf_ag_shortname, auf_type, auf_name, auf_description) VALUES (NULL, 'MESSENGER', 'Yahoo!', 'Yahoo! Messenger');
INSERT INTO adm_user_field (auf_ag_shortname, auf_type, auf_name, auf_description) VALUES (NULL, 'MESSENGER', 'Skype', 'Skype');
INSERT INTO adm_user_field (auf_ag_shortname, auf_type, auf_name, auf_description) VALUES (NULL, 'MESSENGER', 'Google Talk', 'Google Talk');
ALTER TABLE `adm_mitglieder` ADD UNIQUE `am_ar_au_id` ( `am_ar_id` , `am_au_id` );
CREATE TABLE `adm_foto` (
  `af_id` int(11) unsigned NOT NULL auto_increment,
  `af_anzahl` int(11) NOT NULL default '0',
  `af_name` varchar(50) NOT NULL default '',
  `af_kurzname` varchar(10) NOT NULL default '',
  `af_beginn` date NOT NULL default '0000-00-00',
  `af_ende` date NOT NULL default '0000-00-00',
  `af_fotografen` varchar(100) default NULL,
  `af_online_seit` datetime default NULL,
  `af_last_change` datetime default NULL,
  `af_ag_shortname` varchar(10) NOT NULL default '',
  PRIMARY KEY  (`af_id`)
) TYPE=MyISAM AUTO_INCREMENT=1 ;