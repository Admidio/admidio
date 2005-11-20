ALTER TABLE `adm_gruppierung` CHANGE `ag_mutter` `ag_mother` VARCHAR( 10 ) NULL DEFAULT NULL;
ALTER TABLE `adm_gruppierung` ADD `ag_mail_extern` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT '0';
ALTER TABLE `adm_gruppierung` ADD `ag_homepage` VARCHAR( 30 ) NULL DEFAULT 'www.admidio.org';
ALTER TABLE `adm_gruppierung` ADD `ag_mail_attachment_size` SMALLINT UNSIGNED NOT NULL DEFAULT '1024';
ALTER TABLE `adm_gruppierung` ADD `ag_bbcode` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT '0';
ALTER TABLE `adm_gruppierung` ADD `ag_enable_rss` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT '1';