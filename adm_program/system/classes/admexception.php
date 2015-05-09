<?php
/*****************************************************************************/
/** @class AdmException
 *  @brief Admidio specific enhancements of the exception class
 *
 *  This class extends the default PHP exception class with an Admidio specific
 *  output. The exception get's a language string as parameter and returns a
 *  html or plain text message with the translated error if an exception is thrown
 *
 * @par Example
 * @code try
 * {
 *    if($bla == 1)
 *    {
 *        throw new AdmException(LST_NOT_VALID_DATE_FORMAT);
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
 * } @endcode
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2015 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class AdmException extends Exception
{
    /** Constructor that will @b rollback an open database translation
     *  @param $message Translation @b id that should be shown when exception is catched
     *  @param $param1  Optional parameter for language string of translation id
     *  @param $param2  Another optional parameter for language string of translation id
     *  @param $param3  Another optional parameter for language string of translation id
     *  @param $param4  Another optional parameter for language string of translation id
     */
    public function __construct($message, $param1='', $param2='', $param3='', $param4='')
    {
        global $gDb;

        $gDb->EndTransaction(true);

        // save param in class parameters
        $this->param1 = $param1;
        $this->param2 = $param2;
        $this->param3 = $param3;
        $this->param4 = $param4;

        // sicherstellen, dass alles korrekt zugewiesen wird
        parent::__construct($message, 0);
    }

    /** Simply return the plain translated error text without any markup.
     *  @return Returns only a string with the exception text
     */
    public function getText()
    {
        global $gL10n;

        return $gL10n->get($this->message, $this->param1, $this->param2, $this->param3, $this->param4);
    }

    /** Set a new Admidio message id with their parameters. This method should be used
     *  if during the exception processing a new better message should be set.
     *  @param $message Translation @b id that should be shown when exception is catched
     *  @param $param1  Optional parameter for language string of translation id
     *  @param $param2  Another optional parameter for language string of translation id
     *  @param $param3  Another optional parameter for language string of translation id
     *  @param $param4  Another optional parameter for language string of translation id
     */
    public function setNewMessage($message, $param1='', $param2='', $param3='', $param4='')
    {
        $this->message = $message;

        // save param in class parameters
        $this->param1 = $param1;
        $this->param2 = $param2;
        $this->param3 = $param3;
        $this->param4 = $param4;
    }

    /** Show html message window with translated message
     *  @return Returns a html formated message with the exception text
     */
    public function showHtml()
    {
        global $gMessage;

        return $gMessage->show($this->getText());
    }

    /** Simply return the plain translated error text without any markup and stop the script.
     */
    public function showText()
    {
        echo $this->getText();
        exit();
    }
}

?>
