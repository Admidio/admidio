<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Create the html script for an installation / update form
 *
 * This class will create the complete html for a installation / update page.
 * First you set the modus (update or installation) and then you can optional
 * add custom text to the page. The main configuration part will be the
 * form. You can use the complete methods of the Form class.
 *
 * **Code example:**
 * ```
 * // create a simple installation form with a free text, a text field and a submit button
 * $form = new HtmlFormInstallation('installation-form', 'next_html_page.php');
 * $form->setText('This is an example.');
 * $form->addSubmitButton('next_page', $gL10n->get('SYS_NEXT'), array('icon' => 'layout/forward.png', 'type' => 'button'));
 * $form->show();
 * ```
 */
class HtmlFormInstallation extends HtmlForm
{
    /**
     * @var string Title of the html page
     */
    private $title = '';
    /**
     * @var string Headline of the form
     */
    private $headline = '';
    /**
     * @var string A title for the description of the form. This will be displayed as h2
     */
    private $descriptionTitle = '';
    /**
     * @var string A text that will be shown after the headline before the form will be set
     */
    private $descriptionText = '';
    /**
     * @var array<int,string>
     */
    private $headers = array();

    /**
     * Constructor creates the form element
     * @param string $id     Id of the form
     * @param string $action Optional action attribute of the form
     */
    public function __construct($id, $action)
    {
        parent::__construct($id, $action);
    }

    /**
     * @param $header string
     */
    public function addHeader($header)
    {
        $this->headers[] = $header;
    }

    /**
     * If the method is called then a text with an optional title will be displayed after
     * the headline before the form will be displayed.
     * @param string $description The (html) text that should be shown.
     * @param string $title       The headline of the description. If set than this will be displayed
     *                            before the description as h2
     */
    public function setFormDescription($description, $title = '')
    {
        $this->descriptionText  = $description;
        $this->descriptionTitle = $title;
    }

    /**
     * Set the form in the installation modus. Therefore headline and title will be changed.
     * This is the default modus and will be set automatically if not modus is set in the calling code.
     */
    public function setInstallationModus()
    {
        global $gL10n;

        $this->title = $gL10n->get('INS_INSTALLATION');
        $this->headline = $gL10n->get('INS_INSTALLATION_VERSION', array(ADMIDIO_VERSION_TEXT));
    }

    /**
     * Set the form in the update modus. Therefore headline and title will be changed.
     */
    public function setUpdateModus()
    {
        global $gL10n;

        $this->title = $gL10n->get('INS_UPDATE');
        $this->headline = $gL10n->get('INS_UPDATE_VERSION', array(ADMIDIO_VERSION_TEXT));
    }

    /**
     * This method will create the whole html installation/update code. It will show the headline,
     * text and the configured form. If no modus is set the installation modus will be set here.
     * @return string Return the html code of the form.
     */
    public function show()
    {
        // if no modus set then set installation modus
        if ($this->title === '')
        {
            $this->setInstallationModus();
        }

        header('Content-type: text/html; charset=utf-8');
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <!-- (c) 2004 - 2018 The Admidio Team - ' . ADMIDIO_HOMEPAGE . ' -->

            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1" />
            <meta name="author"   content="Admidio Team" />
            <meta name="robots"   content="noindex" />

            <title>Admidio - ' . $this->title . '</title>

            <link rel="shortcut icon" type="image/x-icon" href="layout/favicon.ico" />
            <link rel="icon" type="image/png" href="layout/favicon-32x32.png" sizes="32x32" />
            <link rel="icon" type="image/png" href="layout/favicon-16x16.png" sizes="16x16" />
            <link rel="apple-touch-icon" type="image/png" href="layout/apple-touch-icon.png" sizes="180x180" />

            <link rel="stylesheet" type="text/css" href="'.ADMIDIO_URL.FOLDER_LIBS_CLIENT.'/bootstrap/css/bootstrap.min.css" />
            <link rel="stylesheet" type="text/css" href="layout/admidio.css" />

            <script type="text/javascript" src="'.ADMIDIO_URL.FOLDER_LIBS_CLIENT.'/jquery/dist/jquery.min.js"></script>
            <script type="text/javascript" src="'.ADMIDIO_URL.FOLDER_LIBS_CLIENT.'/bootstrap/js/bootstrap.min.js"></script>
            <script type="text/javascript" src="'.ADMIDIO_URL.'/adm_program/system/js/common_functions.js"></script>

            <script type="text/javascript">
                $(function() {
                    $("[data-toggle=\'popover\']").popover();
                });
            </script>
            ' . implode(' ', $this->headers) . '
        </head>
        <body>
            <div class="admidio-container" id="adm_content">&nbsp;
                <img id="admidio-logo" src="layout/logo.png" alt="Logo" />
                <h1>' . $this->headline . '</h1>';
                // if set then show description
                if ($this->descriptionText !== '')
                {
                    if ($this->descriptionTitle !== '')
                    {
                        $html .= '<h3>' . $this->descriptionTitle . '</h3>';
                    }
                    $html .= '<p>' . $this->descriptionText . '</p>';
                }
                // now show the configured form
                $html .= parent::show();
            $html .= '</div>
        </body>
        </html>';

        return $html;
    }
}
