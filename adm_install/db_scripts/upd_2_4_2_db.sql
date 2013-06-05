-- delete old gender entries with 0. This was set in Admidio 1.x
Delete from `adm_user_data` 
 where usd_usf_id = 
         (Select usf_id from adm_user_fields 
           where usf_name_intern = 'GENDER') 
   and usd_value = 0;