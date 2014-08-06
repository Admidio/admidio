<?php 
/*****************************************************************************/
/** @class HtmlPage
 *  @brief Creates an Admidio specific complete html page
 *
 *  This class creates a html page with head and body and integrates some Admidio
 *  specific elements like css files, javascript files and javascript code. It
 *  also provides some methods to easily add new html data to the page. The generated
 *  page will automatically integrate the choosen theme. You can optional disable the 
 *  integration of the theme files.
 *  @par Examples
 *  @code // create a simple html page with some text
 *  $page = new HtmlPage();
 *  $page->addJavascriptFile($g_root_path.'/adm_program/libs/tooltip/text_tooltip.js');
 *  $page->addHeadline('A simple Html page');
 *  $page->addHtml('<strong>This is a simple Html page!</strong>');
 *  $page->show();@endcode
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2013 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class HtmlPage
{
    protected $pageContent;     ///< Contains the custom html of the current page. This will be added to the default html of each page.
    protected $javascriptContent; ///< Contains the custom javascript of the current page. This will be added to the header part of the page.
    protected $javascriptContentExecute; ///< Contains the custom javascript of the current page that should be executed after pageload. This will be added to the header part of the page.
    protected $title;           ///< The title for the html page and the headline for the Admidio content.
    protected $header;          ///< Additional header that could not be set with the other methods. This content will be add to head of html page without parsing.
    protected $containThemeHtml; ///< If set to true then the custom html code of the theme for each page will be included.
    protected $cssFiles;        ///< An array with all necessary cascading style sheets files for the html page.
    protected $jsFiles;         ///< An array with all necessary javascript files for the html page.
    protected $rssFiles;        ///< An array with all necessary rss files for the html page.
    
    /** Constructor creates the page object and initialized all parameters
     *  @param $title A string that contains the title for the page.
     */
    public function __construct($title = '')
    {
        global $g_root_path;
    
        $this->pageContent = '';
        $this->header = '';
        $this->title = $title;
		$this->bodyOnload = '';
        $this->containThemeHtml = true;
        
        $this->cssFiles = array($g_root_path.'/adm_program/libs/bootstrap/css/bootstrap.min.css', 
                                THEME_PATH.'/css/colorbox.css');
        $this->jsFiles  = array($g_root_path.'/adm_program/libs/jquery/jquery.js', 
                                $g_root_path.'/adm_program/libs/colorbox/jquery.colorbox.js',
                                $g_root_path. '/adm_program/system/js/common_functions.js',
                                $g_root_path.'/adm_program/libs/bootstrap/js/bootstrap.min.js');
        $this->rssFiles = array();
        $this->addJavascript('$(".colorbox-dialog").colorbox({preloading:true,photo:false,speed:300,rel:\'nofollow\'});', true);
    }

    /** Adds a cascading style sheets file to the html page.
     *  @param $file The url with filename of the css file.
     */
    public function addCssFile($file)
    {
        if(in_array($file, $this->cssFiles) == false)
        {
            $this->cssFiles[] = $file;
        }
    }
    
    public function addHeader($header)
    {
        $this->header .= $header;
    }
    
    /** Set the h1 headline of the current html page. If the title of the page was not set
     *  until now than this will also be the title.
     *  @param $headline A string that contains the headline for the page.
     */
    public function addHeadline($headline)
    {
        if(strlen($this->title) == 0)
        {
            $this->setTitle($headline);
        }
        
        $this->addHtml('<h1 class="admHeadline">'.$headline.'</h1>');
    }
    
    /** Adds any html content to the page. The content will be added in the order
     *  you call this method. The first call will place the content at the top of 
     *  the page. The second call below the first etc.
     *  @param $html A valid html code that will be added to the page.
     */
    public function addHtml($html)
    {
        $this->pageContent .= $html;
    } 

    /** Adds any javascript content to the page. The javascript will be added in the order
     *  you call this method.
     *  @param $javascriptCode A valid javascript code that will be added to the header of the page.
     *  @param $executeAfterPageLoad If set to @b true the javascript code will be executed after
     *                               the page is fully loaded.
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

    /** Adds a javascript file to the html page.
     *  @param $file The url with filename of the javascript file.
     */
    public function addJavascriptFile($file)
    {
        if(in_array($file, $this->jsFiles) == false)
        {
            $this->jsFiles[] = $file;
        }
    }
    
    /** Adds a RSS file to the html page.
     *  @param $file  The url with filename of the rss file.
     *  @param $title Optional set a title. This is the name of the feed and
     *                will be shown when adding the rss feed.
     */
    public function addRssFile($file, $title = null)
    {
        if($title <> null)
        {
            $this->rssFiles[$title] = $file;
        }
        else
        {
            $this->rssFiles[] = $file;            
        }
    }
    
    /** Initialize all member parameters of this class
     */
    public function clear()
    {
        $this->pageContent              = null;
        $this->javascriptContent        = null;
        $this->javascriptContentExecute = null;
        $this->title                    = null;
        $this->header                   = null;
        $this->containThemeHtml         = true;
        $this->cssFiles                 = array();
        $this->jsFiles                  = array();
        $this->rssFiles                 = array();
    }
    
    /** Every html page of Admidio contains three files of the custom theme.
     *  my_header.php, my_body_top.php and my_body_bottom.php
     *  With these files the webmaster can contain custom layout to Admidio.
     *  If these files should not be included in the current page, than
     *  this method must be called.
     */
    public function excludeThemeHtml()
    {
        $this->containThemeHtml = false;
    }
    
    /** Returns the title of the html page.
     *  @return Returns the title of the html page.
     */ 
    public function getTitle()
    {
        return $this->title;
    }
        
    /** Set the title of the html page. This will also be the h1 headline for the Admidio page.
     *  @param $title A string that contains the title for the page.
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }
	
	/** Set an additonal onload JS execution to the Body of the html page.
     *  @param $JScode A string that contains the JS code for the onload command in the body.
     */
    public function setBodyOnload($JScode)
    {
        $this->bodyOnload = $JScode;
    }
  
	/** This method send the whole html code of the page to the browser. Call this method
	 *  if you have finished your page layout.
     *  @param $directOutput If set to @b true (default) the html page will be directly send
     *                       to the browser. If set to @b false the html will be returned.
     *  @return If $directOutput is set to @b false this method will return the html code of the page.
	 */
    public function show($directOutput = true)
    {
        global $g_root_path, $gL10n, $gDb, $gCurrentSession, $gCurrentOrganization, $gCurrentUser, $gPreferences, $gValidLogin;
        
        $newLayout        = true;
        $headerContent    = '';
        $htmlMyHeader     = '';
        $htmlMyBodyTop    = '';
        $htmlMyBodyBottom = '';
        
        // add admidio css file at last because there the user can redefine all css
        $this->addCssFile(THEME_PATH.'/css/admidio.css');
        
        // load content of theme files
        if($this->containThemeHtml)
        {
            ob_start();
            include(THEME_SERVER_PATH. '/my_header_new.php');
            $htmlMyHeader = ob_get_contents();
            ob_end_clean();

            ob_start();
            include(THEME_SERVER_PATH. '/my_body_top_new.php');
            $htmlMyBodyTop = ob_get_contents();
            ob_end_clean();

            // if user had set another db in theme content then switch back to admidio db
            $gDb->setCurrentDB();

            ob_start();
            include(THEME_SERVER_PATH. '/my_body_bottom_new.php');
            $htmlMyBodyBottom = ob_get_contents();
            ob_end_clean();

            // if user had set another db in theme content then switch back to admidio db
            $gDb->setCurrentDB();
        }
        
        // add css files to page
        foreach($this->cssFiles as $file)
        {
            $headerContent .= '<link rel="stylesheet" type="text/css" href="'.$file.'" />';
        }

        // add javascript files to page
        foreach($this->jsFiles as $file)
        {
            $headerContent .= '<script type="text/javascript" src="'.$file.'"></script>';
        }

        // add rss feed files to page
        foreach($this->rssFiles as $title => $file)
        {
            if(is_numeric($title) == false)
            {
                $headerContent .= '<link rel="alternate" type="application/rss+xml" title="'.$title.'" href="'.$file.'" />';
            }
            else
            {
                $headerContent .= '<link rel="alternate" type="application/rss+xml" href="'.$file.'" />';                
            }
        }
		
		// add JS for onload of the body
        if(strlen($this->bodyOnload) > 0)
        {
            $this->bodyOnload = 'onload="'.$this->bodyOnload.'"';
        }
        else
        {
            $this->bodyOnload = '';
        }
        
        // add organization name to title
        if(strlen($this->title) > 0)
        {
            $this->title = $gCurrentOrganization->getValue('org_longname').' - '.$this->title;
        }
        else
        {
        	$this->title = $gCurrentOrganization->getValue('org_longname');
        }

        // add javascript code to page        
        if(strlen($this->javascriptContent) > 0)
        {
            $headerContent .= '<script type="text/javascript"><!-- 
                '.$this->javascriptContent.'
            --></script>';
        }

        // add javascript code to page that will be excecuted after page is fully loaded       
        if(strlen($this->javascriptContentExecute) > 0)
        {
            $headerContent .= '<script type="text/javascript"><!-- 
                $(document).ready(function(){
                    $("[data-toggle=tooltip]").tooltip();
                    $(".icon-information").tooltip();
                    '.$this->javascriptContentExecute.'
                });
            --></script>';
        }
        
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <!-- (c) 2004 - 2014 The Admidio Team - http://www.admidio.org -->
            
            <meta http-equiv="content-type" content="text/html; charset=utf-8" />
            
            <title>'.$this->title.'</title>

            <script type="text/javascript"><!-- 
        		var gRootPath  = "'. $g_root_path. '"; 
        		var gThemePath = "'. THEME_PATH. '";
        	--></script>';
            
            $html .= $headerContent;
            
            if(strlen($this->header) > 0)
            {
                $html .= $this->header;
            }
            
            $html .= $htmlMyHeader.'
        </head>
        <body class="admBody" '.$this->bodyOnload.'>'.
            $htmlMyBodyTop.'
            <div class="admContent">'.$this->pageContent.'</div>'.
            $htmlMyBodyBottom.'          
        </body>
        </html>';

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
?>