<?php 
/*****************************************************************************/
/** @class FormInstallation
 *  @brief Create the html script for an installation / update form 
 *
 *  This class will create the complete html for a installation / update page.
 *  First you set the modus (update or installation) and then you can optional
 *  add custom text to the page. The main configuration part will be the 
 *  form. You can use the complete methods of the Form class.
 *  @par Examples
 *  @code // create a simple installation form with a free text, a text field and a submit button
 *  $form = new FormInstallation('installation-form', 'next_html_page.php');
    $form->setText('This is an example.');
    $form->addSubmitButton('next_page', $gL10n->get('SYS_NEXT'), 'layout/forward.png', null, 'button');
    $form->show();
@endcode
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2013 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class FormInstallation extends Form
{
    private $descriptionTitle;  ///< A title for the description of the form. This will be displayed as h2
    private $descriptionText;   ///< A text that will be shown after the headline before the form will be set
    private $headline;          ///< Headline of the form
    private $title;             ///< Title of the html page
    
    /** Constructor creates the form element
     *  @param $id Id of the form
     *  @param $action Optional action attribute of the form
     */
    public function __construct($id, $action)
    {        
        
        parent::__construct($id, $action);
    }
    
    /** If the method is called then a text with an optional title will be displayed after 
     *  the headline before the form will be displayed.
     *  @param $description The (html) text that should be shown.
     *  @param $title       The headline of the description. If set than this will be displayed 
     *                      before the description as h2
     */
    public function setFormDescription($description, $title = '')
    {
        $this->descriptionText  = $description;
        $this->descriptionTitle = $title;
    }

    /** Set the form in the installation modus. Therefore headline and title will be changed.
     *  This is the default modus and will be set automatically if not modus is set in the calling code.
     */
    public function setInstallationModus()
    {
        global $gL10n;
        $this->title = $gL10n->get('INS_INSTALLATION');
        $this->headline = $gL10n->get('INS_INSTALLATION_VERSION', ADMIDIO_VERSION. BETA_VERSION_TEXT);
    }

    /** Set the form in the update modus. Therefore headline and title will be changed.
     */
    public function setUpdateModus()
    {
        global $gL10n;
        $this->title = $gL10n->get('INS_UPDATE');
        $this->headline = $gL10n->get('INS_UPDATE_VERSION', ADMIDIO_VERSION. BETA_VERSION_TEXT);
    }
    
    /** This method will create the whole html installation/update code. It will show the headline,
     *  text and the configured form. If no modus is set the installation modus will be set here.
     */
    public function show($directOutput = true)
    {
        global $gL10n;
    
        // if no modus set then set installation modus
        if(strlen($this->title) == 0)
        {
            $this->setInstallationModus();
        }
    
        header('Content-type: text/html; charset=utf-8'); 
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <!-- (c) 2004 - 2013 The Admidio Team - http://www.admidio.org -->
            
            <meta http-equiv="content-type" content="text/html; charset=utf-8" />
            <meta name="author"   content="Admidio Team" />
            <meta name="robots"   content="noindex" />
            
            <title>Admidio - '. $this->title. '</title>

            <link rel="shortcut icon" type="image/x-icon" href="layout/favicon.png" />
            <link rel="stylesheet" type="text/css" href="layout/install.css" />
            <script type="text/javascript" src="../libs/jquery/jquery.js"></script>
            <script type="text/javascript" src="../system/js/common_functions.js"></script>
            
            <script type="text/javascript"><!--
                $(document).ready(function() {
                    $("#next_page").click(function() {
                        if($(this).val() == "'.$gL10n->get('INS_UPDATE_DATABASE').'"
                        || $(this).val() == "'.$gL10n->get('INS_INSTALL_ADMIDIO').'")
                        {
                            $(this).prop("disabled", "true");
                            $("#btn_icon").attr("src", "layout/loader.gif");
                            
                            if($(this).val() == "'.$gL10n->get('INS_UPDATE_DATABASE').'")
                            {
                                $("#btn_text").html("'.$gL10n->get('INS_DATABASE_IS_UPDATED').'");
                            }
                            else
                            {
                                $("#btn_text").html("'.$gL10n->get('INS_DATABASE_WILL_BE_ESTABLISHED').'");
                            }
                        }
                        $("#installation-form").submit();                
                    });
                });
            //--></script>
        </head>
        <body>
            <div class="admContent" id="adm_content">&nbsp;
                <img id="adm-logo" src="layout/logo.png" alt="Logo" />
                <h1 class="admHeadline">'. $this->headline. '</h1>';
                // if set then show description
                if(strlen($this->descriptionText) > 0)
                {
                    if(strlen($this->descriptionTitle) > 0)
                    {
                        $html .= '<h3 class="admHeadline3">'.$this->descriptionTitle.'</h3>';
                    }
                    $html .= $this->descriptionText;
                }
                // now show the configured form
                $html .= parent::show(false);
            $html .= '</div>
        </body>
        </html>';
        
        if($directOutput)
        {
            echo $html;
        }
        else
        {
            return $html;
        }
    }
}
?>