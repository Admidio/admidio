
-- Texttabelle anpassen
ALTER TABLE %PRAEFIX%_texts MODIFY COLUMN `txt_id` int(11) unsigned not null AUTO_INCREMENT;

-- Ueberschriftgroessen anpasssen
ALTER TABLE %PRAEFIX%_announcements MODIFY COLUMN `ann_headline` VARCHAR(100) NOT NULL;
ALTER TABLE %PRAEFIX%_dates MODIFY COLUMN `dat_headline` VARCHAR(100) NOT NULL;

-- Loginnamen auf 35 Zeichen erweitern
ALTER TABLE %PRAEFIX%_users MODIFY COLUMN `usr_login_name` VARCHAR(35);