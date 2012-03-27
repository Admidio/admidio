<?php
/******************************************************************************
 * Admidio specific enhancements of the exception class
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * The following functions are available:
 *
 * show() - show message window with translated message
 *
 *****************************************************************************/

class AdmException extends Exception
{
    public function __construct($message, $code = 0) 
	{
        global $gDb;
		
		$gDb->EndTransaction(true);

        // sicherstellen, dass alles korrekt zugewiesen wird
        parent::__construct($message, $code);
    }


    // show message window with translated message
    public function show()
	{
		global $gMessage, $gL10n;
		
		return $gMessage->show($gL10n->get($this->message));
    }
}

?>