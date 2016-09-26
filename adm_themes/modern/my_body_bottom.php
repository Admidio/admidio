
<!-- Here you can add your html code. This code will be applied at the end of the <body> area
     and after the Admidio module code.
-->

            </div><!-- closes "div#left-block" -->
        </div><!-- closes "div.col-md-9" -->
        <div class="col-md-3">
            <div id="right-block" class="admidio-container">
                <?php

                include(SERVER_PATH . '/adm_plugins/login_form/login_form.php');

                ?>
                <div id="plugin_menu" class="admidio-plugin-content">
                    <?php
                    
                    $sql = 'SELECT *
                      FROM '.TBL_MENU.'
                      where men_group = 4
                     ORDER BY men_group DESC, men_order';
                    $statement = $gDb->query($sql);

                    if($statement->rowCount() > 0)
                    {
                        while ($row = $statement->fetchObject())
                        {
                            // Read current roles rights of the menu
                            $displayMenu = new RolesRights($gDb, 'men_display_right', $row->men_id);
                            $rolesDisplayRight = $displayMenu->getRolesIds();
                            $men_display = true;
                            
                            if(count($rolesDisplayRight) >= 1)
                            {
                                // check for rigth to show the menue
                                if(!$displayMenu->hasRight($gCurrentUser->getRoleMemberships()))
                                {
                                    $men_display = false;
                                }
                            }

                            if($men_display == true)
                            {
                                include(SERVER_PATH . $row->men_url);
                            }
                        }
                    }
                    
                    // display Menu
                    $sql = 'SELECT *
                      FROM '.TBL_MENU.'
                      where men_group < 4
                     ORDER BY men_group DESC, men_order';
                    $statement = $gDb->query($sql);

                    if($statement->rowCount() > 0)
                    {
                        $men_groups = array('1' => 'Administration', '2' => 'Modules', '3' => 'Plugins');
                        $men_heads = array('1' => 'SYS_ADMINISTRATION', '2' => 'SYS_MODULES', '3' => 'SYS_PLUGIN');
                        $last = 0;
                        
                        while ($row = $statement->fetchObject())
                        {
                            if($row->men_group != $last)
                            {
                                if($last > 0)
                                {
                                    echo $Menu->show();
                                }
                                $Menu = new Menu($men_groups[$row->men_group], $gL10n->get($men_heads[$row->men_group]));
                                $last = $row->men_group;
                            }
                            
                            $men_display = true;
                            $desc = '';
                            
                            if(strlen($row->men_translate_desc) > 2)
                            {
                                $desc = $gL10n->get($row->men_translate_desc);
                            }
                            
                            // Read current roles rights of the menu
                            $displayMenu = new RolesRights($gDb, 'men_display_right', $row->men_id);
                            $rolesDisplayRight = $displayMenu->getRolesIds();

                            if($row->men_need_enable == 1)
                            {
                                if($gPreferences['enable_'.$row->men_modul_name.'_module'] == 1  || ($gPreferences['enable_'.$row->men_modul_name.'_module'] == 2 && $gValidLogin))
                                {
                                    $men_display = true;
                                }
                                else
                                {
                                    $men_display = false;
                                }
                            }

                            $men_url = $row->men_url;
                            $men_icon = $row->men_icon;
                            $men_translate_name = $gL10n->get($row->men_translate_name);

                            //special case because there are differnent links if you are logged in or out for mail
                            if($row->men_modul_name === 'mail' && $gValidLogin)
                            {
                                $unreadBadge = '';

                                // get number of unread messages for user
                                $message = new TableMessage($gDb);
                                $unread = $message->countUnreadMessageRecords($gCurrentUser->getValue('usr_id'));

                                if($unread > 0)
                                {
                                    $unreadBadge = '<span class="badge">' . $unread . '</span>';
                                }
                                
                                $men_url = '/adm_program/modules/messages/messages.php';
                                $men_icon = '/icons/messages.png';
                                $men_translate_name = $gL10n->get('SYS_MESSAGES') . $unreadBadge;
                            }

                            if(count($rolesDisplayRight) >= 1)
                            {
                                // check for rigth to show the menue
                                if(!$displayMenu->hasRight($gCurrentUser->getRoleMemberships()))
                                {
                                    $men_display = false;
                                }
                            }

                            // special check for "newreg"
                            if($row->men_modul_name === 'newreg')
                            {
                                $men_display = false;
                                if($gCurrentUser->approveUsers() && $gPreferences['registration_mode'] > 0)
                                {
                                    $men_display = true;
                                }
                            }

                            // special check for "usrmgt"
                            if($row->men_modul_name === 'usrmgt')
                            {
                                if(!$gCurrentUser->editUsers())
                                {
                                    $men_display = false;
                                }
                            }

                            // special check for "roladm"
                            if($row->men_modul_name === 'roladm')
                            {
                                if(!$gCurrentUser->manageRoles())
                                {
                                    $men_display = false;
                                }
                            }

                            if($men_display == true)
                            {
                                $Menu->addItem($row->men_modul_name, $men_url, $men_translate_name, $men_icon, $desc);
                            }
                        }
                        echo $Menu->show();
                    }
                    
                    $sql = 'SELECT *
                      FROM '.TBL_MENU.'
                      where men_group = 5
                     ORDER BY men_group DESC, men_order';
                    $statement = $gDb->query($sql);

                    if($statement->rowCount() > 0)
                    {
                        while ($row = $statement->fetchObject())
                        {
                            // Read current roles rights of the menu
                            $displayMenu = new RolesRights($gDb, 'men_display_right', $row->men_id);
                            $rolesDisplayRight = $displayMenu->getRolesIds();
                            $men_display = true;
                            
                            if(count($rolesDisplayRight) >= 1)
                            {
                                // check for rigth to show the menue
                                if(!$displayMenu->hasRight($gCurrentUser->getRoleMemberships()))
                                {
                                    $men_display = false;
                                }
                            }

                            if($men_display == true)
                            {
                                include(SERVER_PATH . $row->men_url);
                            }
                        }
                    }

                    ?>
                </div><!-- closes "div#plugin_menu" -->
            </div><!-- closes "div#right-block" -->
        </div><!-- closes "div.col-md-3" -->
    </div><!-- closes "div.row" -->
</div><!-- closes "div#page" -->


<p id="copyright">
    <a href="<?php echo ADMIDIO_HOMEPAGE; ?>" style="text-decoration: none;">
        <img src="<?php echo THEME_PATH; ?>/images/admidio_logo_20.png"
             alt="<?php echo $gL10n->get('SYS_ADMIDIO_SHORT_DESC'); ?>"
             title="<?php echo $gL10n->get('SYS_ADMIDIO_SHORT_DESC'); ?>"
             style="border: 0; vertical-align: bottom;" />
    </a>
    <span style="font-size: 9pt; padding-left: 8px;">&copy; 2004 - 2016&nbsp;&nbsp;<?php echo $gL10n->get('SYS_ADMIDIO_TEAM'); ?></span>
</p>
