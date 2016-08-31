<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class HtmlPage
 * @brief Creates an Admidio specific complete html page
 *
 * This class creates a html page with head and body and integrates some Admidio
 * specific elements like css files, javascript files and javascript code. It
 * also provides some methods to easily add new html data to the page. The generated
 * page will automatically integrate the choosen theme. You can optional disable the
 * integration of the theme files.
 * @par Examples
 * @code // create a simple html page with some text
 * $page = new HtmlPage();
 * $page->addJavascriptFile('adm_program/libs/jquery/jquery.min.js');
 * $page->setHeadline('A simple Html page');
 * $page->addHtml('<strong>This is a simple Html page!</strong>');
 * $page->show(); @endcode
 */
class HtmlPage
{
    protected $title;           ///< The title for the html page and the headline for the Admidio content.
    protected $header;          ///< Additional header that could not be set with the other methods. This content will be add to head of html page without parsing.
    protected $headline;        ///< The main headline for the html page.
    protected $pageContent;     ///< Contains the custom html of the current page. This will be added to the default html of each page.
    protected $menu;            ///< An object of the menu of this page
    protected $showThemeHtml;   ///< If set to true then the custom html code of the theme for each page will be included.
    protected $showMenu;        ///< If set to true then the menu will be included.
    protected $hasNavbar;       ///< Flag if the current page has a navbar.
    protected $showModal;       ///< If set to true then html code for a modal window will be included.
    protected $cssFiles;        ///< An array with all necessary cascading style sheets files for the html page.
    protected $jsFiles;         ///< An array with all necessary javascript files for the html page.
    protected $rssFiles;        ///< An array with all necessary rss files for the html page.
    protected $printMode;       ///< A flag that indicates if the page should be styled in print mode then no colors will be shown
    protected $javascriptContent; ///< Contains the custom javascript of the current page. This will be added to the header part of the page.
    protected $javascriptContentExecute; ///< Contains the custom javascript of the current page that should be executed after pageload. This will be added to the header part of the page.

    /**
     * Constructor creates the page object and initialized all parameters
     * @param string $headline A string that contains the headline for the page that will be shown in the <h1> tag.
     */
    public function __construct($headline = '')
    {
        global $g_root_path;

        $this->title         = '';
        $this->header        = '';
        $this->headline      = '';
        $this->pageContent   = '';
        $this->menu          = new HtmlNavbar('menu_main_script', $headline, $this);
        $this->showThemeHtml = true;
        $this->showMenu      = true;
        $this->showModal     = false;
        $this->hasNavbar     = false;
        $this->printMode     = false;
        $this->javascriptContent        = '';
        $this->javascriptContentExecute = '';
        $this->cssFiles      = array();
        $this->jsFiles       = array();

        $this->setHeadline($headline);

        $this->addCssFile('adm_program/libs/bootstrap/css/bootstrap.css');
        $this->addJavascriptFile('adm_program/libs/jquery/jquery.js');
        $this->addJavascriptFile('adm_program/system/js/common_functions.js');
        $this->addJavascriptFile('adm_program/libs/bootstrap/js/bootstrap.js');
        $this->rssFiles = array();
    }

    /**
     * Adds a cascading style sheets file to the html page.
     * @param string $file The url with filename or the relative path starting with @i adm_program of the css file.
     */
    public function addCssFile($file)
    {
        if(!in_array($file, $this->cssFiles, true))
        {
            if(strpos($file, 'http') !== false)
            {
                $this->cssFiles[] = $file;
            }
            else
            {
                $this->cssFiles[] = $this->getDebugOrMinFilepath($file);
            }
        }
    }

    /**
     * Add content to the header segment of a html page.
     * @param string $header Content for the html header segment.
     */
    public function addHeader($header)
    {
        $this->header .= $header;
    }

    /**
     * Adds any html content to the page. The content will be added in the order
     * you call this method. The first call will place the content at the top of
     * the page. The second call below the first etc.
     * @param string $html A valid html code that will be added to the page.
     */
    public function addHtml($html)
    {
        $this->pageContent .= $html;
    }

    /**
     * Adds any javascript content to the page. The javascript will be added in the order you call this method.
     * @param string $javascriptCode       A valid javascript code that will be added to the header of the page.
     * @param bool   $executeAfterPageLoad (optional) If set to @b true the javascript code will be executed after
     *                                     the page is fully loaded.
     */
    public function addJavascript($javascriptCode, $executeAfterPageLoad = false)
    {
        if($executeAfterPageLoad)
        {
            $this->javascriptContentExecute .= $javascriptCode;
        }
        else
        {
            $this->javascriptContent .= $javascriptCode;
        }
    }

    /**
     * Adds a javascript file to the html page.
     * @param string $file The url with filename or the relative path starting with @i adm_program of the javascript file.
     */
    public function addJavascriptFile($file)
    {
        if(!in_array($file, $this->jsFiles, true))
        {
            if(strpos($file, 'http') !== false)
            {
                $this->jsFiles[] = $file;
            }
            else
            {
                $this->jsFiles[] = $this->getDebugOrMinFilepath($file);
            }
        }
    }

    /**
     * Adds the default menu
     * @return void
     */
    public function addDefaultMenu()
    {
        global $gL10n, $gPreferences, $gValidLogin, $gDb, $gCurrentUser;
        
        $sql = 'SELECT *
          FROM '.TBL_MENU.'
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
                    $this->menu->addItem('menu_item_'.$men_groups[$row->men_group], null, $gL10n->get($men_heads[$row->men_group]), 'application_view_list.png', 'right', 'navbar', 'admidio-default-menu-item');
                    $last = $row->men_group;
                }
                
                $men_display = true;
                $desc = '';
                
                if(strlen($row->men_translate_desc) > 2)
                {
                    $desc = $gL10n->get($row->men_translate_desc);
                }
                
                // Read current roles rights of the menu
                $displayMenu = new RolesRights($gDb, 'men_display_boot', $row->men_id);
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
                    $this->menu->addItem($row->men_modul_name, $men_url, $men_translate_name, $men_icon, 'right', 'menu_item_'.$men_groups[$row->men_group], 'admidio-default-menu-item');
                }
            }
        }

        if($gValidLogin)
        {
            // show link to own profile
            $this->menu->addItem('menu_item_my_profile', '/adm_program/modules/profile/profile.php', $gL10n->get('PRO_MY_PROFILE'), 'profile.png', 'right', 'navbar', 'admidio-default-menu-item');
            // show logout link
            $this->menu->addItem('menu_item_logout', '/adm_program/system/logout.php', $gL10n->get('SYS_LOGOUT'), 'door_in.png', 'right', 'navbar', 'admidio-default-menu-item');
        }
        else
        {
            // show registration link
            $this->menu->addItem('menu_item_registration', '/adm_program/modules/registration/registration.php', $gL10n->get('SYS_REGISTRATION'), 'new_registrations.png', 'right', 'navbar', 'admidio-default-menu-item');
            // show login link
            $this->menu->addItem('menu_item_login', '/adm_program/system/login.php', $gL10n->get('SYS_LOGIN'), 'key.png', 'right', 'navbar', 'admidio-default-menu-item');
        }
    }

    /**
     * Adds a RSS file to the html page.
     * @param string $file  The url with filename of the rss file.
     * @param string $title (optional) Set a title. This is the name of the feed and will be shown when adding the rss feed.
     */
    public function addRssFile($file, $title = '')
    {
        if($title !== '')
        {
            $this->rssFiles[$title] = $file;
        }
        else
        {
            $this->rssFiles[] = $file;
        }
    }

    /*
     * Adds the html code for a modal window to the current script.
     * The link must have the following attributes: data-toggle="modal" data-target="#admidio_modal"
     */
    public function enableModal()
    {
        $this->showModal = true;
    }

    /**
     * The method will return the filename. If you are in debug mode than it will return the
     * not minified version of the filename otherwise it will return the minified version.
     * Therefore you must provide 2 versions of the file. One with a @b min before the file extension
     * and one version without the @b min.
     * @param string $filepath Filename of the NOT minified file.
     * @return string Returns the filename in dependence of the debug mode.
     */
    private function getDebugOrMinFilepath($filepath)
    {
        global $gDebug, $g_root_path;

        $fileInfo = pathinfo($filepath);
        $filename = basename($fileInfo['filename'], '.min');

        $filepathDebug = '/' . $fileInfo['dirname'] . '/' . $filename . '.'     . $fileInfo['extension'];
        $filepathMin   = '/' . $fileInfo['dirname'] . '/' . $filename . '.min.' . $fileInfo['extension'];

        if ((!$gDebug && file_exists(SERVER_PATH.$filepathMin)) || !file_exists(SERVER_PATH.$filepathDebug))
        {
            return $g_root_path.$filepathMin;
        }

        return $g_root_path.$filepathDebug;
    }

    /**
     * Returns the headline of the current Admidio page. This is the text of the <h1> tag of the page.
     * @return string Returns the headline of the current Admidio page.
     */
    public function getHeadline()
    {
        return $this->headline;
    }

    /**
     * Returns the menu object of this html page.
     * @return \HtmlNavbar Returns the menu object of this html page.
     */
    public function getMenu()
    {
        return $this->menu;
    }

    /**
     * Returns the title of the html page.
     * @return string Returns the title of the html page.
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Every html page of Admidio contains a menu.
     * If the menu should not be included in the current page, than this method must be called.
     * @return void
     */
    public function hideMenu()
    {
        $this->showMenu = false;
    }

    /**
     * Every html page of Admidio contains three files of the custom theme.
     * my_header.php, my_body_top.php and my_body_bottom.php
     * With these files the administrator can contain custom layout to Admidio.
     * If these files should not be included in the current page, than this method must be called.
     * @return void
     */
    public function hideThemeHtml()
    {
        $this->showThemeHtml = false;
    }

    /**
     * Flag if the current page has a navbar.
     * @return void
     */
    public function hasNavbar()
    {
        $this->hasNavbar = true;
    }

    /**
     * Set the h1 headline of the current html page. If the title of the page
     * was not set until now than this will also be the title.
     * @param string $headline A string that contains the headline for the page.
     * @return void
     */
    public function setHeadline($headline)
    {
        if($this->title === '')
        {
            $this->setTitle($headline);
        }

        $this->headline = $headline;
        $this->menu->setName($headline);
    }

    /**
     * If print mode is set then a print specific css file will be loaded.
     * All styles will be more print compatible and are only black, grey and white.
     * @return void
     */
    public function setPrintMode()
    {
        $this->printMode = true;
    }

    /**
     * Set the title of the html page that will be shown in the <title> tag.
     * @param string $title A string that contains the title for the page.
     * @return void
     */
    public function setTitle($title)
    {
        global $gCurrentOrganization;

        if($title !== '')
        {
            $this->title = $gCurrentOrganization->getValue('org_longname') . ' - ' . $title;
        }
        else
        {
            $this->title = $gCurrentOrganization->getValue('org_longname');
        }
    }

    /**
     * This method send the whole html code of the page to the browser. Call this method
     * if you have finished your page layout.
     * @param bool $directOutput If set to @b true (default) the html page will be directly send
     *                           to the browser. If set to @b false the html will be returned.
     * @return string|void If $directOutput is set to @b false this method will return the html code of the page.
     */
    public function show($directOutput = true)
    {
        global $g_root_path, $gL10n, $gDb, $gCurrentSession, $gCurrentOrganization, $gCurrentUser, $gPreferences;
        global $gValidLogin, $gProfileFields, $gHomepage, $gDbType;

        $headerContent    = '';
        $htmlMyHeader     = '';
        $htmlMyBodyTop    = '';
        $htmlMyBodyBottom = '';
        $htmlMenu         = '';
        $htmlHeadline     = '';

        if($this->showMenu)
        {
            // add modules and administration modules to the menu
            $this->addDefaultMenu();
            $htmlMenu = $this->menu->show();
        }

        if($this->headline !== '')
        {
            $htmlHeadline = '<h1>'.$this->headline.'</h1>';
        }

        // add admidio css file at last because there the user can redefine all css
        $this->addCssFile(THEME_PATH.'/css/admidio.css');

        // add custom css file if it exists to add own css styles without edit the original admidio css
        if(file_exists(THEME_PATH.'/css/custom.css'))
        {
            $this->addCssFile(THEME_PATH.'/css/custom.css');
        }

        // if print mode is set then add a print specific css file
        if($this->printMode)
        {
            $this->addCssFile(THEME_PATH.'/css/print.css');
        }

        // load content of theme files
        if($this->showThemeHtml)
        {
            ob_start();
            include(THEME_SERVER_PATH.'/my_header.php');
            $htmlMyHeader = ob_get_contents();
            ob_end_clean();

            ob_start();
            include(THEME_SERVER_PATH.'/my_body_top.php');
            $htmlMyBodyTop = ob_get_contents();
            ob_end_clean();

            ob_start();
            include(THEME_SERVER_PATH.'/my_body_bottom.php');
            $htmlMyBodyBottom = ob_get_contents();
            ob_end_clean();
        }

        // add css files to page
        foreach($this->cssFiles as $file)
        {
            $headerContent .= '<link rel="stylesheet" type="text/css" href="'.$file.'" />';
        }

        // add some special scripts so that ie8 could better understand the Bootstrap 3 framework
        $headerContent .= '<!--[if lt IE 9]>
            <script src="'.$g_root_path.'/adm_program/libs/html5shiv/html5shiv.min.js"></script>
            <script src="'.$g_root_path.'/adm_program/libs/respond/respond.min.js"></script>
        <![endif]-->';

        if (isset($gPreferences['system_browser_update_check']) && $gPreferences['system_browser_update_check'] == 1)
        {
            $this->addJavascriptFile('adm_program/libs/browser-update/browser-update.js');
        }

        // add javascript files to page
        foreach($this->jsFiles as $file)
        {
            $headerContent .= '<script type="text/javascript" src="'.$file.'"></script>';
        }

        // add rss feed files to page
        foreach($this->rssFiles as $title => $file)
        {
            if(!is_numeric($title))
            {
                $headerContent .= '<link rel="alternate" type="application/rss+xml" title="'.$title.'" href="'.$file.'" />';
            }
            else
            {
                $headerContent .= '<link rel="alternate" type="application/rss+xml" href="'.$file.'" />';
            }
        }

        // add code for a modal window
        if($this->showModal)
        {
            $this->addJavascript('$("body").on("hidden.bs.modal", ".modal", function () { $(this).removeData("bs.modal"); });', true);
            $this->addHtml('<div class="modal fade" id="admidio_modal" tabindex="-1" role="dialog" aria-hidden="true">
                                <div class="modal-dialog"><div class="modal-content"></div></div>
                            </div>');
        }

        // add javascript code to page
        if($this->javascriptContent !== '')
        {
            $headerContent .= '<script type="text/javascript"><!--
                '.$this->javascriptContent.'
            --></script>';
        }

        // add javascript code to page that will be executed after page is fully loaded
        if($this->javascriptContentExecute !== '')
        {
            $headerContent .= '<script type="text/javascript"><!--
                $(function() {
                    $("[data-toggle=\'popover\']").popover();
                    $(".admidio-icon-info, .admidio-icon-link img, [data-toggle=tooltip]").tooltip();
                    '.$this->javascriptContentExecute.'
                });
            --></script>';
        }

        $html = '
            <!DOCTYPE html>
            <html>
            <head>
                <!-- (c) 2004 - 2016 The Admidio Team - http://www.admidio.org/ -->

                <meta http-equiv="content-type" content="text/html; charset=utf-8" />
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1">

                <title>'.$this->title.'</title>

                <script type="text/javascript"><!--
                    var gRootPath  = "'. $g_root_path. '";
                    var gThemePath = "'. THEME_PATH. '";
                --></script>';

        $html .= $headerContent;
        $html .= $this->header;
        $html .= $htmlMyHeader;
        $html .= '</head><body>';
        $html .= $htmlMyBodyTop;
        $html .= '<div class="admidio-content">';
        $html .= $htmlHeadline;
        $html .= $htmlMenu;
        $html .= $this->pageContent;
        $html .= '</div>';
        $html .= $htmlMyBodyBottom;
        $html .= '</body></html>';

        if($this->hasNavbar)
        {
            // set css class to hide headline in mobile mode if navbar is shown
            $html = str_replace('<h1>'.$this->headline.'</h1>', '<h1 class="hidden-xs">'.$this->headline.'</h1>', $html);
        }

        // now show the complete html of the page
        if($directOutput)
        {
            header('Content-type: text/html; charset=utf-8');
            echo $html;
        }
        else
        {
            return $html;
        }
    }
}
