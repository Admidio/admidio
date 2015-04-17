-- delete old gender entries with 0. This was set in Admidio 1.x
Delete from %PREFIX%_user_data
 where usd_usf_id =
         (Select usf_id from %PREFIX%_user_fields
           where usf_name_intern = 'GENDER')
   and usd_value = '0';
