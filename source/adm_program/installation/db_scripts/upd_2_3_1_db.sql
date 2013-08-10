-- manipulate data 
UPDATE %PREFIX%_preferences SET prf_value = 'postcard.tpl' WHERE prf_value like 'brief_standard.tpl';
UPDATE %PREFIX%_preferences SET prf_value = 'postcard_separate_photo.tpl' WHERE prf_value like 'brief_grosses_foto.tpl';
UPDATE %PREFIX%_preferences SET prf_value = 'greeting_card.tpl' WHERE prf_value like 'grusskarte.tpl';