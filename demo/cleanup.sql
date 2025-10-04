/**
 ***********************************************************************************************
 * SQL script for cleanup of the Admidio database structure
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/*==============================================================*/
/* Table Cleanup                                                */
/*==============================================================*/
DROP TABLE IF EXISTS %PREFIX%_announcements                     CASCADE;
DROP TABLE IF EXISTS %PREFIX%_auto_login                        CASCADE;
DROP TABLE IF EXISTS %PREFIX%_category_report                   CASCADE;
DROP TABLE IF EXISTS %PREFIX%_components                        CASCADE;
DROP TABLE IF EXISTS %PREFIX%_events                            CASCADE;
DROP TABLE IF EXISTS %PREFIX%_dates                             CASCADE;
DROP TABLE IF EXISTS %PREFIX%_files                             CASCADE;
DROP TABLE IF EXISTS %PREFIX%_folders                           CASCADE;
DROP TABLE IF EXISTS %PREFIX%_forum_topics                      CASCADE;
DROP TABLE IF EXISTS %PREFIX%_forum_posts                       CASCADE;
DROP TABLE IF EXISTS %PREFIX%_guestbook_comments                CASCADE;
DROP TABLE IF EXISTS %PREFIX%_guestbook                         CASCADE;
DROP TABLE IF EXISTS %PREFIX%_inventory_fields                  CASCADE;
DROP TABLE IF EXISTS %PREFIX%_inventory_field_select_options    CASCADE;
DROP TABLE IF EXISTS %PREFIX%_inventory_item_data               CASCADE;
DROP TABLE IF EXISTS %PREFIX%_inventory_items                   CASCADE;
DROP TABLE IF EXISTS %PREFIX%_inventory_item_borrow_data        CASCADE;
DROP TABLE IF EXISTS %PREFIX%_log_changes                       CASCADE;
DROP TABLE IF EXISTS %PREFIX%_links                             CASCADE;
DROP TABLE IF EXISTS %PREFIX%_members                           CASCADE;
DROP TABLE IF EXISTS %PREFIX%_messages                          CASCADE;
DROP TABLE IF EXISTS %PREFIX%_messages_attachments              CASCADE;
DROP TABLE IF EXISTS %PREFIX%_messages_content                  CASCADE;
DROP TABLE IF EXISTS %PREFIX%_messages_recipients               CASCADE;
DROP TABLE IF EXISTS %PREFIX%_photos                            CASCADE;
DROP TABLE IF EXISTS %PREFIX%_preferences                       CASCADE;
DROP TABLE IF EXISTS %PREFIX%_registrations                     CASCADE;
DROP TABLE IF EXISTS %PREFIX%_role_dependencies                 CASCADE;
DROP TABLE IF EXISTS %PREFIX%_roles                             CASCADE;
DROP TABLE IF EXISTS %PREFIX%_roles_rights                      CASCADE;
DROP TABLE IF EXISTS %PREFIX%_roles_rights_data                 CASCADE;
DROP TABLE IF EXISTS %PREFIX%_list_columns                      CASCADE;
DROP TABLE IF EXISTS %PREFIX%_lists                             CASCADE;
DROP TABLE IF EXISTS %PREFIX%_oidc_access_tokens                CASCADE;
DROP TABLE IF EXISTS %PREFIX%_oidc_auth_codes                   CASCADE;
DROP TABLE IF EXISTS %PREFIX%_oidc_clients                      CASCADE;
DROP TABLE IF EXISTS %PREFIX%_oidc_refresh_tokens               CASCADE;
DROP TABLE IF EXISTS %PREFIX%_rooms                             CASCADE;
DROP TABLE IF EXISTS %PREFIX%_saml_clients                      CASCADE;
DROP TABLE IF EXISTS %PREFIX%_sessions                          CASCADE;
DROP TABLE IF EXISTS %PREFIX%_sso_keys                          CASCADE;
DROP TABLE IF EXISTS %PREFIX%_texts                             CASCADE;
DROP TABLE IF EXISTS %PREFIX%_user_relations                    CASCADE;
DROP TABLE IF EXISTS %PREFIX%_user_relation_types               CASCADE;
DROP TABLE IF EXISTS %PREFIX%_user_log                          CASCADE;
DROP TABLE IF EXISTS %PREFIX%_user_data                         CASCADE;
DROP TABLE IF EXISTS %PREFIX%_user_fields                       CASCADE;
DROP TABLE IF EXISTS %PREFIX%_user_field_select_options         CASCADE;
DROP TABLE IF EXISTS %PREFIX%_categories                        CASCADE;
DROP TABLE IF EXISTS %PREFIX%_users                             CASCADE;
DROP TABLE IF EXISTS %PREFIX%_organizations                     CASCADE;
DROP TABLE IF EXISTS %PREFIX%_ids                               CASCADE;
DROP TABLE IF EXISTS %PREFIX%_menu                              CASCADE;

