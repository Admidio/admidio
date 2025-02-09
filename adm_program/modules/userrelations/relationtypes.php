<?php
/**
 ***********************************************************************************************
 * Overview and maintenance of all relationtypes
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Component\Form;
use Admidio\Users\Entity\UserRelationType;
use Admidio\Changelog\Service\ChangelogService;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    if (!$gSettingsManager->getBool('contacts_user_relations_enabled')) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // set module headline
    $headline = $gL10n->get('SYS_RELATIONSHIP_CONFIGURATIONS');
    $gNavigation->addUrl(CURRENT_URL, $headline);

    // create html page object
    $page = new HtmlPage('admidio-relationtypes', $headline);

    // define link to create new category
    $page->addPageFunctionsMenuItem(
        'menu_item_relation_type_add',
        $gL10n->get('SYS_CREATE_VAR', array($gL10n->get('SYS_CONFIGURATION'))),
        ADMIDIO_URL . FOLDER_MODULES . '/userrelations/relationtypes_new.php',
        'bi-plus-circle-fill'
    );

    ChangelogService::displayHistoryButton($page, 'user_relation_types', 'user_relation_types');

    // Create table object
    $relationTypesOverview = new HtmlTable('tbl_relationtypes', $page, true);

    $relationTypes = array(
        'asymmetrical' => $gL10n->get('SYS_ASYMMETRICAL'),
        'symmetrical' => $gL10n->get('SYS_SYMMETRICAL'),
        'unidirectional' => $gL10n->get('SYS_UNIDIRECTIONAL')
    );

    // create array with all column heading values
    $columnHeading = array(
        $gL10n->get('SYS_USER_RELATION'),
        $gL10n->get('SYS_USER_RELATION_TYPE') . Form::getHelpTextIcon('SYS_RELATIONSHIP_TYPE_DESC'),
        '&nbsp;'
    );
    $relationTypesOverview->setColumnAlignByArray(array('left', 'left', 'right'));
    $relationTypesOverview->addRowHeadingByArray($columnHeading);

    $sql = 'SELECT urt1.*, urt2.urt_name AS urt_name_inverse, urt2.urt_name_male AS urt_name_male_inverse, urt2.urt_name_female AS urt_name_female_inverse, urt2.urt_edit_user AS urt_edit_user_inverse
          FROM ' . TBL_USER_RELATION_TYPES . ' AS urt1
    LEFT OUTER JOIN ' . TBL_USER_RELATION_TYPES . ' AS urt2
            ON urt1.urt_id_inverse = urt2.urt_id
         WHERE urt1.urt_id <= urt1.urt_id_inverse
            OR urt1.urt_id_inverse IS NULL
      ORDER BY urt1.urt_name, urt2.urt_name';

    $relationTypesStatement = $gDb->queryPrepared($sql);

    $relationType1 = new UserRelationType($gDb);
    $relationType2 = new UserRelationType($gDb);

    // Get data
    while ($relRow = $relationTypesStatement->fetch()) {
        $editUserIcon = '';
        $editUserInverseIcon = '';

        $relationType1->clear();
        $relationType1->setArray($relRow);

        if ((bool)$relRow['urt_edit_user']) {
            $editUserIcon = ' <i class="bi bi-pencil-fill" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_EDIT_USER_IN_RELATION') . '"></i>';
        }

        $nameRelationshiptype = $relationType1->getValue('urt_name') . $editUserIcon;

        // if it's a asymmetrical relationship type we must add the name of the other relationship type
        if ($relationType1->getRelationTypeString() === 'asymmetrical') {
            $relationType2->clear();
            $relRow2 = $relRow;
            $relRow2['urt_id'] = $relRow2['urt_id_inverse'];
            $relRow2['urt_name'] = $relRow2['urt_name_inverse'];
            $relRow2['urt_name_male'] = $relRow2['urt_name_male_inverse'];
            $relRow2['urt_name_female'] = $relRow2['urt_name_female_inverse'];
            $relationType2->setArray($relRow2);

            if ((bool)$relRow['urt_edit_user_inverse']) {
                $editUserInverseIcon = ' <i class="bi bi-pencil-fill" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_EDIT_USER_IN_RELATION') . '"></i>';
            }

            $nameRelationshiptype .= '&nbsp;&nbsp;-&nbsp;&nbsp;' . $relationType2->getValue('urt_name') . $editUserInverseIcon;
        }

        $relationtypeAdministration = '
            <a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(
                ADMIDIO_URL . FOLDER_MODULES . '/userrelations/relationtypes_new.php',
                array('urt_uuid' => $relationType1->getValue('urt_uuid'))
            ) . '"><i class="bi bi-pencil-square" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_EDIT') . '"></i></a>
            <a class="admidio-icon-link admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no"
                data-message="' . $gL10n->get('SYS_RELATIONSHIP_TYPE_DELETE', array($relationType1->getValue('urt_name') . ($relationType1->isUnidirectional() ? '' : ('/' . $relationType2->getValue('urt_name'))))) . '"
                data-href="callUrlHideElement(\'row_' . $relationType1->getValue('urt_uuid') . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/userrelations/relationtypes_function.php', array('mode' => 'delete', 'urt_uuid' => $relationType1->getValue('urt_uuid'))) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')">
                <i class="bi bi-trash" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_DELETE') . '"></i></a>';

        // create array with all column values
        $columnValues = array(
            $nameRelationshiptype,
            $relationTypes[$relationType1->getRelationTypeString()],
            $relationtypeAdministration
        );
        $relationTypesOverview->addRowByArray($columnValues, 'row_' . $relationType1->getValue('urt_uuid'));
    }

    $page->addHtml($relationTypesOverview->show());
    $page->show();
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
