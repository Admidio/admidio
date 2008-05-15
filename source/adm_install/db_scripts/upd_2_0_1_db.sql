
-- Kategorienreihenfolge anpassen
UPDATE %PRAEFIX%_categories SET cat_sequence = cat_sequence + 1
 WHERE cat_type = 'USF';
