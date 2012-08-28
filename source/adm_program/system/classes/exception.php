<?php
/*****************************************************************************/
/** @class AdmException
 *  @brief Admidio specific enhancements of the exception class
 *
 *  This class extends the default PHP exception class with an Admidio specific
 *  output. The exception get's a language string as parameter and returns a 
 *  html message with the translated error if an exception is thrown
 *
 * @section sec Example
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
 *    $e->show();
 * } @endcode
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2012 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class AdmException extends Exception
{
	/** constructor that will @b rollback an open database translation
	 *  @param $message Translation id that should be shown when exception is catched
	 *  @param $code Optional code for PHP exception constructor
	 */
    public function __construct($message, $code = 0) 
	{
        global $gDb;
		
		$gDb->EndTransaction(true);

        // sicherstellen, dass alles korrekt zugewiesen wird
        parent::__construct($message, $code);
    }


    /** show message window with translated message 
	 *  @param $inline 	If set to @b true than no html message dialog will be shown, output is only the plain text.
	 * 					This setting should be used then error is thrown in ajax context.
	 */
    public function show($inline = false)
	{
		global $gMessage, $gL10n;
		
		if($inline)
		{
			return $gL10n->get($this->message);
		}
		else
		{
			return $gMessage->show($gL10n->get($this->message));
		}
    }
}

?>