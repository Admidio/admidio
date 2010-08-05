
-- Kategorienreihenfolge anpassen
UPDATE %PREFIX%_categories SET cat_sequence = cat_sequence + 1
 WHERE cat_type = 'USF';

-- Counter bei Links einfügen
ALTER TABLE %PREFIX%_links ADD COLUMN `lnk_counter` INTEGER(11) UNSIGNED NULL AFTER `lnk_url`;