<?php
/**
 ***********************************************************************************************
 * Common functions for update and installation
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * A method to create a simple html page that shows a custom text and a navigation button.
 * This should be used to show notices or errors during installation or update.
 * @param string $message    A (html) message that should be displayed.
 * @param string $url        The url to which the user should be navigated if he clicks the button.
 * @param string $buttonText The text of the button.
 * @param string $buttonIcon The icon of the button.
 * @param bool   $update
 */
function showNotice($message, $url, $buttonText, $buttonIcon, $update = false)
{
    global $gL10n;

    $onClickText = '';

    // show dialog with success notification
    $form = new HtmlFormInstallation('installation-form', $url);

    if ($update)
    {
        $form->setUpdateModus();
    }

    if ($buttonText === $gL10n->get('INS_UPDATE_DATABASE'))
    {
        $onClickText = $gL10n->get('INS_DATABASE_IS_UPDATED');
    }

    $form->setFormDescription($message);
    $form->addSubmitButton('next_page', $buttonText, array('icon' => $buttonIcon, 'onClickText' => $onClickText));
    echo $form->show();
    exit();
}

/**
 * prueft, ob die Mindestvoraussetzungen bei PHP und MySQL eingehalten werden
 * @param Database $database
 * @return string
 */
function checkDatabaseVersion(Database $database)
{
    global $gL10n;

    // check database version
    if (version_compare($database->getVersion(), $database->getMinimumRequiredVersion(), '<'))
    {
        return $gL10n->get('SYS_DATABASE_VERSION') . ': <strong>' . $database->getVersion() . '</strong><br /><br />' .
               $gL10n->get('INS_WRONG_MYSQL_VERSION', array(ADMIDIO_VERSION_TEXT, $database->getMinimumRequiredVersion(),
                           '<a href="' . ADMIDIO_HOMEPAGE . 'download.php">', '</a>'));
    }

    return '';
}

/**
 * Read data from sql file and execute all statements to the current database
 * @param Database $db
 * @param string   $sqlFileName
 * @return true|string Returns true no error occurs ales error message is returned
 */
function querySqlFile(Database $db, $sqlFileName)
{
    global $gL10n;

    $sqlPath = ADMIDIO_PATH . '/adm_program/installation/db_scripts/';
    $sqlFilePath = $sqlPath . $sqlFileName;

    if (!is_file($sqlFilePath))
    {
        return $gL10n->get('INS_DATABASE_FILE_NOT_FOUND', array($sqlFileName, $sqlPath));
    }

    try
    {
        $sqlStatements = Database::getSqlStatementsFromSqlFile($sqlFilePath);
    }
    catch (\RuntimeException $exception)
    {
        return $gL10n->get('INS_ERROR_OPEN_FILE', array($sqlFilePath));
    }

    foreach ($sqlStatements as $sqlStatement)
    {
        $db->queryPrepared($sqlStatement);
    }

    return true;
}

/**
 * @param Database $db
 */
function disableSoundexSearchIfPgSql(Database $db)
{
    if (DB_ENGINE === Database::PDO_ENGINE_PGSQL)
    {
        // soundex is not a default function in PostgreSQL
        $sql = 'UPDATE ' . TBL_PREFERENCES . '
                   SET prf_value = \'0\'
                 WHERE prf_name = \'system_search_similar\'';
        $db->queryPrepared($sql);
    }
}
