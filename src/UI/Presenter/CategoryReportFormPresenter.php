<?php
namespace Admidio\UI\Presenter;

use Admidio\CategoryReport\Service\CategoryReportGenerator;
use Admidio\CategoryReport\Service\CategoryReportRepository;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;

/** Builds the create, edit and copy page for one category report. */
class CategoryReportFormPresenter extends PagePresenter
{
    public function createForm(string $action, int $reportId): void
    {
        global $gCurrentOrgId, $gCurrentSession, $gDb, $gL10n;

        if (!in_array($action, array('new', 'edit', 'copy'), true)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        $repository = new CategoryReportRepository();
        $generator = new CategoryReportGenerator();
        $report = $action === 'new' ? null : $repository->findReport($reportId, $action === 'edit');
        if ($action !== 'new' && $report === null) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        $report ??= array('id' => 0, 'name' => '', 'columns' => array(), 'selection_role' => '',
            'selection_cat' => '', 'number_col' => 0, 'default_conf' => false);
        if ($action === 'copy') {
            $report['name'] = $repository->createName(html_entity_decode((string)$report['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        $title = $gL10n->get(array('new' => 'SYS_CREATE_VAR', 'edit' => 'SYS_EDIT_VAR', 'copy' => 'SYS_COPY_VAR')[$action],
            array($gL10n->get('SYS_REPORT')));
        $this->setHtmlID('adm_category_report_edit');
        $this->setHeadline($title);

        $form = new FormPresenter('adm_category_report_edit_form', 'modules/category-report.edit.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/category_report.php', array('mode' => 'report_save')),
            $this);
        $form->addInput('name', $gL10n->get('SYS_DESIGNATION'), html_entity_decode((string)$report['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            array('property' => FormPresenter::FIELD_REQUIRED));
        $sql = 'SELECT rol_id, rol_name, cat_name FROM ' . TBL_CATEGORIES . ', ' . TBL_ROLES .
            ' WHERE cat_id = rol_cat_id AND (cat_org_id = ' . $gCurrentOrgId . ' OR cat_org_id IS NULL)';
        $form->addSelectBoxFromSql('selection_role', $gL10n->get('SYS_ROLE_SELECTION'), $gDb, $sql,
            array('defaultValue' => explode(',', (string)$report['selection_role']), 'multiselect' => true,
                'helpTextId' => 'SYS_CATEGORY_REPORT_ROLE_FILTER_DESC'));
        $sql = 'SELECT DISTINCT cat_id, cat_name FROM ' . TBL_CATEGORIES . ', ' . TBL_ROLES .
            ' WHERE cat_id = rol_cat_id AND (cat_org_id = ' . $gCurrentOrgId . ' OR cat_org_id IS NULL)';
        $form->addSelectBoxFromSql('selection_cat', $gL10n->get('SYS_CAT_SELECTION'), $gDb, $sql,
            array('defaultValue' => explode(',', (string)$report['selection_cat']), 'multiselect' => true,
                'helpTextId' => 'SYS_CATEGORY_REPORT_CATEGORY_FILTER_DESC'));
        $form->addCheckbox('number_col', $gL10n->get('SYS_QUANTITY') . ' (' . $gL10n->get('SYS_COLUMN') . ')',
            $report['number_col'], array('helpTextId' => 'SYS_NUMBER_COL_DESC'));
        $form->addInput('report_action', '', $action, array('property' => FormPresenter::FIELD_HIDDEN));
        $form->addInput('source_id', '', $action === 'new' ? 0 : $reportId, array('property' => FormPresenter::FIELD_HIDDEN));
        $form->addButton('category_report_add_column', $gL10n->get('SYS_ADD_COLUMN'),
            array('icon' => 'bi-plus-circle-fill', 'class' => 'btn-primary'));
        $form->addSubmitButton('adm_button_save_category_report', $gL10n->get('SYS_SAVE'), array('icon' => 'bi-check-lg'));

        $columns = array_values(array_filter($report['columns'],
            static fn(array $column): bool => $generator->isInHeaderSelection((string)$column['field']) > 0));
        if ($action === 'new') {
            $columns[] = array('field' => '', 'condition' => '');
        }
        $this->assignSmartyVariable('reportFormDataJson', json_encode(array(
            'fields' => array_values($generator->headerSelection),
            'roleProperties' => array_values($generator->headerRolePropSelection),
            'columns' => $columns,
            'moveLabel' => $gL10n->get('SYS_MOVE_VAR', array($gL10n->get('SYS_COLUMN'))),
            'deleteLabel' => $gL10n->get('SYS_DELETE')
        ), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT));
        $this->assignSmartyVariable('conditionHelpUrl', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_SYSTEM . '/msg_window.php',
            array('message_id' => 'mylist_condition', 'inline' => 'true')));
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }
}
