ALTER TABLE `adm_gruppierung` CHANGE `ag_mutter` `ag_mother` VARCHAR( 10 ) NULL DEFAULT NULL;
ALTER TABLE `adm_gruppierung` ADD `ag_mail_extern` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT '0';