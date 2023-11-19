<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Simple presentation of messages to the user
 *
 * This class creates a new html page with a simple headline and a message. It's
 * designed to easily integrate this class into your code. An object **$gMessage**
 * of this class is created in the common.php. You can set a url that should be
 * open after user confirmed the message or you can show a question with two
 * default buttons yes and no. There is also an option to automatically leave the
 * message after some time.
 *
 * **Code example**
 * ```
 * // show a message with a back button, the object $gMessage is created in common.php
 * $gMessage->show($gL10n->get('SYS_MESSAGE_TEXT_ID'));
 *
 * // show a message and set a link to a page that should be shown after user click ok
 * $gMessage->setForwardUrl('https://www.example.com/mypage.php');
 * $gMessage->show($gL10n->get('SYS_MESSAGE_TEXT_ID'));
 *
 * // show a message with yes and no button and set a link to a page that should be shown after user click yes
 * $gMessage->setForwardYesNo('https://www.example.com/mypage.php');
 * $gMessage->show($gL10n->get('SYS_MESSAGE_TEXT_ID'));
 * ```
 */
class Message
{
    /**
     * @var bool wird ermittelt, ob bereits eine Ausgabe an den Browser erfolgt ist
     */
    private $inline = false;
    /**
     * @var string Url auf die durch den Weiter-Button verwiesen wird
     */
    private $forwardUrl = '';
    /**
     * @var int Anzahl ms bis automatisch zu forwardUrl weitergeleitet wird
     */
    private $timer = 0;
    /**
     * @var bool Includes the header and body of the theme to the message. This will be included as default.
     */
    private $includeThemeBody = true;
    /**
     * @var bool If set to true then no html elements will be shown, only the pure text message.
     */
    private $showTextOnly = false;
    /**
     * @var bool If set to true then only the message with their html elements will be shown.
     */
    private $showHtmlTextOnly = false;
    /**
     * @var bool Anstelle von Weiter werden Ja/Nein-Buttons angezeigt
     */
    private $showYesNoButtons = false;
    /**
     * @var bool If this is set to true than the message will be show with html of the bootstrap modal window
     */
    private $modalWindowMode = false;

    /**
     * Constructor that initialize the class member parameters
     */
    public function __construct()
    {
    }

    /**
     * If this is set to true than the message will be show with html of the bootstrap modal window.
     */
    public function showInModalWindow()
    {
        $this->modalWindowMode  = true;
        $this->includeThemeBody = false;
        $this->inline = true;
    }

    /**
     * Set a URL to which the user should be directed if he confirmed the message.
     * It's possible to set a timer after that the page of the url will be
     * automatically displayed without user interaction.
     * @param string $url   The full url to which the user should be directed.
     * @param int    $timer Optional a timer in millisecond after the user will be automatically redirected to the $url.
     */
    public function setForwardUrl($url, $timer = 0)
    {
        $this->forwardUrl = $url;
        $this->timer      = $timer;
    }

    /**
     * Add two buttons with the labels **yes** and **no** to the message. If the user choose yes
     * he will be redirected to the $url. If he chooses no he will be directed back to the previous page.
     * @param string $url The full url to which the user should be directed if he chooses **yes**.
     */
    public function setForwardYesNo($url)
    {
        $this->forwardUrl       = $url;
        $this->showYesNoButtons = true;
    }

    /**
     * Create a html page if necessary and show the message with the configured buttons.
     * The message is presented depending on the settings. By default, this is an HTML page with
     * title, message and buttons. The message can also be displayed in a modal window.
     * Alternatively there is the possibility to display only the message text.
     * @param string $content  The message text that should be shown. The content could have html.
     * @param string $headline Optional a headline for the message. Default will be SYS_NOTE.
     */
    public function show($content, $headline = '')
    {
        global $gDb, $gL10n, $page;

        // first perform a rollback in database if there is an open transaction
        $gDb->rollback();

        // Set caption, if it was not set explicitly before
        if ($headline === '') {
            $headline = $gL10n->get('SYS_NOTE');
        }

        if (!$this->inline) {
            // check only if not already set to true
            $this->inline = headers_sent();
        }

        if (!isset($page) || !$this->inline) {
            // create html page object
            $page = new HtmlPage('admidio-message', $headline);
            $page->hideBackLink();

            if (!$this->includeThemeBody) {
                // don't show custom html of the current theme
                $page->setInlineMode();
            }

            // forward to next page after x seconds
            if ($this->timer > 0) {
                $page->addJavascript(
                    '
                    setTimeout(function() {
                        window.location.href = "'. $this->forwardUrl. '";
                    }, '. $this->timer. ');'
                );
            }
        }

        if ($this->showTextOnly) {
            // show the pure message text without any html
            echo strip_tags($content);
        } elseif ($this->showHtmlTextOnly) {
            // show the pure message text with their html
            echo $content;
        } elseif ($this->inline) {
            // show the message in html but without the theme specific header and body
            $page->assign('message', $content);
            $page->assign('messageHeadline', $headline);
            $page->assign('forwardUrl', $this->forwardUrl);
            $page->assign('showYesNoButtons', $this->showYesNoButtons);
            $page->assign('l10n', $gL10n);
            $page->display('message_modal.tpl');
        } else {
            // show a Admidio html page with complete theme header and body
            $page->assign('message', $content);
            $page->assign('forwardUrl', $this->forwardUrl);
            $page->assign('showYesNoButtons', $this->showYesNoButtons);
            $page->addTemplateFile('message.tpl');
            $page->show();
        }
        exit();
    }

    /**
     * If this will be set then only the text message will be shown.
     * If this message contains html elements then these will also be shown in the output.
     * @param bool $showText If set to true than only the message text with their html elements will be shown.
     */
    public function showHtmlTextOnly($showText)
    {
        $this->showHtmlTextOnly = $showText;
    }

    /**
     * If set no theme files will be integrated in the page.
     * This setting is useful if the message should be loaded in a small window.
     * @param bool $showTheme If set to true than theme body and header will be shown. Otherwise this will be hidden.
     */
    public function showThemeBody($showTheme)
    {
        $this->includeThemeBody = $showTheme;
    }

    /**
     * If this will be set then no html elements will be shown in the output,
     * only pure text. This is useful if you have a script that is used in ajax mode.
     * @param bool $showText If set to true than only the message text without any html will be shown.
     */
    public function showTextOnly($showText)
    {
        $this->showTextOnly = $showText;
    }
}
