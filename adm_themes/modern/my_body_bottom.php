
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
                    
                    // Plugin Menu

                    $sql = 'SELECT *
                      FROM '.TBL_MENU.'
                     WHERE men_group = 3 and men_display_right = 1
                     ORDER BY men_order';
                    $statement = $gDb->query($sql);

                    if($statement->rowCount() > 0)
                    {
                        $pluginMenu = new Menu('plugins', $gL10n->get('SYS_PLUGIN'));
                        while ($row = $statement->fetchObject())
                        {
                            $men_need_login = false;
                            if(($row->men_need_login == 1 && $gValidLogin) || $row->men_need_login == 0)
                            {
                                $men_need_login = true;
                            }
                            
                            $men_need_admin = false;
                            if(($row->men_need_admin == 1 && $gCurrentUser->isAdministrator()) || $row->men_need_admin == 0)
                            {
                                $men_need_admin = true;
                            }
                            
                            $desc = '';
                            if(strlen($row->men_translat_desc) > 2)
                            {
                                $desc = $gL10n->get($row->men_translat_desc);
                            }

                            if($men_need_login == true && $men_need_admin == true)
                            {
                                $pluginMenu->addItem($row->men_modul_name, $row->men_url,
                                             $gL10n->get($row->men_translat_name), $row->men_icon, $desc);
                            }
                        }
                        echo $pluginMenu->show();
                    }

                    // Module Menu
                    
                    $sql = 'SELECT *
                      FROM '.TBL_MENU.'
                     WHERE men_group = 2 and men_display_right = 1
                     ORDER BY men_order';
                    $statement = $gDb->query($sql);
                    
                    
                    if($statement->rowCount() > 0)
                    {
                        $moduleMenu = new Menu('modules', $gL10n->get('SYS_MODULES'));

                        while ($row = $statement->fetchObject())
                        {
                            
                            $men_need_enable = false;
                            if($row->men_need_enable == 1)
                            {
                                if($gPreferences['enable_'.$row->men_modul_name.'_module'] == 1)
                                {
                                    $men_need_enable = true;
                                }
                                elseif($gPreferences['enable_'.$row->men_modul_name.'_module'] == 2 && $gValidLogin)
                                {
                                    $men_need_enable = true;
                                }
                            }
                            elseif($row->men_need_enable == 0)
                            {
                                $men_need_enable = true;
                            }
                            
                            $men_need_login = false;
                            if(($row->men_need_login == 1 && $gValidLogin) || $row->men_need_login == 0)
                            {
                                $men_need_login = true;
                            }
                            
                            $men_need_admin = false;
                            if(($row->men_need_admin == 1 && $gCurrentUser->isAdministrator()) || $row->men_need_admin == 0)
                            {
                                $men_need_admin = true;
                            }
                            
                            $desc = '';
                            if(strlen($row->men_translat_desc) > 2)
                            {
                                $desc = $gL10n->get($row->men_translat_desc);
                            }
                            
                            $men_url = $row->men_url;
                            $men_icon = $row->men_icon;
                            $men_translat_name = $gL10n->get($row->men_translat_name);
                            
                            //special case because there are differnent links if you are logged in or out for mail
                            if($row->men_modul_name === 'mail' && $gValidLogin)
                            {
                                if($gPreferences['enable_pm_module'] == 1 || $men_need_enable == true)
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
                                    $men_translat_name = $gL10n->get('SYS_MESSAGES') . $unreadBadge;
                                }
                            }

                            if($men_need_enable == true && $men_need_login == true && $men_need_admin == true)
                            {
                                $moduleMenu->addItem($row->men_modul_name, $men_url,
                                             $men_translat_name, $men_icon, $desc);
                            }
                        }
                        echo $moduleMenu->show();
                    }

                    

                    // Administration Menu
                    if($gCurrentUser->approveUsers() || $gCurrentUser->editUsers()
                    || $gCurrentUser->manageRoles()  || $gCurrentUser->isAdministrator())
                    {
                        
                        $sql = 'SELECT *
                          FROM '.TBL_MENU.'
                         WHERE men_group = 1 and men_display_right = 1
                         ORDER BY men_order';
                        $statement = $gDb->query($sql);

                        if($statement->rowCount() > 0)
                        {
                            $adminMenu = new Menu('administration', $gL10n->get('SYS_ADMINISTRATION'));
                            while ($row = $statement->fetchObject())
                            {
                                
                                $men_need_enable = false;
                                if($row->men_need_enable == 1)
                                {
                                    if($gPreferences['enable_'.$row->men_modul_name.'_module'] == 1)
                                    {
                                        $men_need_enable = true;
                                    }
                                    elseif($gPreferences['enable_'.$row->men_modul_name.'_module'] == 2 && $gValidLogin)
                                    {
                                        $men_need_enable = true;
                                    }
                                }
                                elseif($row->men_need_enable == 0)
                                {
                                    $men_need_enable = true;
                                }
                                
                                $men_need_admin = false;
                                if(($row->men_need_admin == 1 && $gCurrentUser->isAdministrator()) || $row->men_need_admin == 0)
                                {
                                    $men_need_admin = true;
                                }

                                $desc = '';
                                if(strlen($row->men_translat_desc) > 2)
                                {
                                    $desc = $gL10n->get($row->men_translat_desc);
                                }
                                
                                // special check for "newreg"
                                if($row->men_modul_name === 'newreg')
                                {
                                    $men_need_admin = false;
                                    if($gCurrentUser->approveUsers() && $gPreferences['registration_mode'] > 0)
                                    {
                                        $men_need_admin = true;
                                    }
                                }
                                
                                // special check for "usrmgt"
                                if($row->men_modul_name === 'usrmgt')
                                {
                                    $men_need_admin = false;
                                    if($gCurrentUser->editUsers())
                                    {
                                        $men_need_admin = true;
                                    }
                                }
                                
                                // special check for "roladm"
                                if($row->men_modul_name === 'roladm')
                                {
                                    $men_need_admin = false;
                                    if($gCurrentUser->manageRoles())
                                    {
                                        $men_need_admin = true;
                                    }
                                }

                                if($men_need_enable == true && $men_need_admin == true)
                                {
                                    $adminMenu->addItem($row->men_modul_name, $row->men_url,
                                                 $gL10n->get($row->men_translat_name), $row->men_icon, $desc);
                                }
                            }
                            echo $adminMenu->show();
                        }
                    }

                    ?>
                </div><!-- closes "div#plugin_menu" -->
            </div><!-- closes "div#right-block" -->
        </div><!-- closes "div.col-md-3" -->
    </div><!-- closes "div.row" -->
</div><!-- closes "div#page" -->


<p id="copyright">
    <a href="http://www.admidio.org/" style="text-decoration: none;">
        <img src="<?php echo THEME_PATH; ?>/images/admidio_logo_20.png"
             alt="<?php echo $gL10n->get('SYS_ADMIDIO_SHORT_DESC'); ?>"
             title="<?php echo $gL10n->get('SYS_ADMIDIO_SHORT_DESC'); ?>"
             style="border: 0; vertical-align: bottom;" />
    </a>
    <span style="font-size: 9pt; padding-left: 8px;">&copy; 2004 - 2016&nbsp;&nbsp;<?php echo $gL10n->get('SYS_ADMIDIO_TEAM'); ?></span>
</p>
