<?php

use Admidio\Infrastructure\Database;

/**
 * @brief Admidio specific enhancements of the exception class
 *
 * This class extends the default PHP exception class with an Admidio specific
 * output. The exception gets a language string as parameter and returns a
 * html or plain text message with the translated error if an exception is thrown
 *
 * **Code example**
 * ```
 * try {
 *    if($bla == 1)
 *    {
 *        throw new Exception(SYS_NOT_VALID_DATE_FORMAT);
 *    }
 *    ...
 * } catch(Throwable $e) {
 *    // show html message
 *    $e->showHtml();
 *
 *    // show simply text message
 *    $e->showText();
 * }
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 * @deprecated 5.0.0:5.1.0 Class "AdmException" is deprecated, use class "\Admidio\Exception" instead.
 */
class AdmException extends Exception
{
    /**
     * Constructor saves the parameters to the class and will call the parent constructor. Also, a **rollback**
     * of open database translation will be done.
     * @param string $message Translation **id** or simple text that should be shown when exception is caught
     * @param array<int,string> $params Optional parameter for language string of translation id
     * @throws Exception
     * @deprecated 5.0.0:5.1.0 Class "AdmException" is deprecated, use class "\Admidio\Exception" instead.
     */
    public function __construct($message, $params = array())
    {
        global $gLogger, $gDb, $gL10n;

        if ($gDb instanceof Database) {
            // if there is an open transaction we should perform a rollback
            $gDb->rollback();
        }

        // if text is a translation-id then translate it
        if (Admidio\Infrastructure\Language::isTranslationStringId($message)) {
            $message = $gL10n->get($message, $params);
        }

        $gLogger->notice('Exception is thrown!', array('message' => $message));

        parent::__construct($message);
    }

    /**
     * Set a new Admidio message id with their parameters. This method should be used
     * if during the exception processing a new better message should be set.
     * @param string $message Translation **id** that should be shown when exception is caught
     * @param array<int,string> $params  Optional parameter for language string of translation id
     * @deprecated 5.0.0:5.1.0 Class "AdmException" is deprecated, use class "\Admidio\Exception" instead.
     */
    public function setNewMessage(string $message, array $params = array())
    {
        global $gL10n;

        // if text is a translation-id then translate it
        if (Admidio\Infrastructure\Language::isTranslationStringId($message)) {
            $message = $gL10n->get($message, $params);
        }

        $this->message = $message;
    }

    /**
     * Show html message window with translated message
     * @deprecated 5.0.0:5.1.0 Class "AdmException" is deprecated, use class "\Admidio\Exception" instead.
     */
    public function showHtml()
    {
        global $gMessage;

        // display database error to user
        if ($gMessage instanceof Message) {
            $gMessage->show($this->getMessage());
        // => EXIT
        } else {
            $this->showText();
            // => EXIT
        }
    }

    /**
     * Simply return the plain translated error text without any markup and stop the script.
     * @deprecated 5.0.0:5.1.0 Class "AdmException" is deprecated, use class "\Admidio\Exception" instead.
     */
    public function showText()
    {
        if (!headers_sent()) {
            header('Content-type: text/html; charset=utf-8');
        }

        echo $this->getMessage();
        exit();
    }
}
