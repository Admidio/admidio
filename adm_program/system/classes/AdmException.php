<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Admidio specific enhancements of the exception class
 *
 * This class extends the default PHP exception class with an Admidio specific
 * output. The exception get's a language string as parameter and returns a
 * html or plain text message with the translated error if an exception is thrown
 *
 * **Code example**
 * ```
 * try
 * {
 *    if($bla == 1)
 *    {
 *        throw new AdmException(SYS_NOT_VALID_DATE_FORMAT);
 *    }
 *    ..
 *
 * }
 * catch(AdmException $e)
 * {
 *    // show html message
 *    $e->showHtml();
 *
 *    // show simply text message
 *    $e->showText();
 * }
 * ```
 */
class AdmException extends Exception
{
    /**
     * @var array<int,string>|string
     */
    protected $params = array();

    /**
     * Constructor saves the parameters to the class and will call the parent constructor. Also a **rollback**
     * of open database translation will be done.
     * @param string            $message Translation **id** or simple text that should be shown when exception is catched
     * @param array<int,string> $params  Optional parameter for language string of translation id
     */
    public function __construct($message, $params = array())
    {
        global $gLogger, $gDb;

        if ($gDb instanceof Database) {
            // if there is an open transaction we should perform a rollback
            $gDb->rollback();
        }

        $gLogger->notice('AdmException is thrown!', array('message' => $message, 'params' => $this->params));

        $this->params = $params;

        parent::__construct($message);
    }

    /**
     * Simply return the plain translated error text without any markup.
     * @return string Returns only a string with the exception text
     */
    public function getText()
    {
        global $gL10n;

        // if text is a translation-id then translate it
        if (Language::isTranslationStringId($this->message)) {
            return $gL10n->get($this->message, $this->params);
        }

        return $this->message;
    }

    /**
     * Set a new Admidio message id with their parameters. This method should be used
     * if during the exception processing a new better message should be set.
     * @param string            $message Translation **id** that should be shown when exception is catched
     * @param array<int,string> $params  Optional parameter for language string of translation id
     */
    public function setNewMessage($message, array $params = array())
    {
        $this->message = $message;
        $this->params = $params;
    }

    /**
     * Show html message window with translated message
     */
    public function showHtml()
    {
        global $gMessage;

        // display database error to user
        if ($gMessage instanceof Message) {
            $gMessage->show($this->getText());
        // => EXIT
        } else {
            $this->showText();
            // => EXIT
        }
    }

    /**
     * Simply return the plain translated error text without any markup and stop the script.
     */
    public function showText()
    {
        if (!headers_sent()) {
            header('Content-type: text/html; charset=utf-8');
        }

        echo $this->getText();
        exit();
    }
}
