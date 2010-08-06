
-- Kategorienreihenfolge anpassen
UPDATE %PREFIX%_categories SET cat_sequence = cat_sequence + 1
 WHERE cat_type = 'USF';
