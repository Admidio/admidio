<?php
namespace Admidio\UI\Presenter;

use Admidio\CategoryReport\Service\CategoryReportRepository;
use Admidio\Changelog\Service\ChangelogService;
use Admidio\Infrastructure\Utils\SecurityUtils;

/** Builds the administration page for category reports. */
class CategoryReportAdministrationPresenter extends PagePresenter
{
    /** Create the list of all category reports available to the current organization. */
    public function createList(): void
    {
        global $gCurrentOrgId, $gCurrentSession, $gL10n;

        $this->setHtmlID('adm_category_report_manage');
        $this->setHeadline($gL10n->get('SYS_MANAGE_REPORTS'));
        $baseUrl = ADMIDIO_URL . FOLDER_MODULES . '/category_report.php';
        $this->addJavascript('function refreshCategoryReportList() { location.reload(); }');

        $this->addPageFunctionsMenuItem(
            'menu_item_category_report_add',
            $gL10n->get('SYS_CREATE_VAR', array($gL10n->get('SYS_REPORT'))),
            SecurityUtils::encodeUrl($baseUrl, array('mode' => 'new')),
            'bi-plus-circle-fill'
        );
        ChangelogService::displayHistoryButton($this, 'categoryreport', 'category_report');

        $reports = array();
        foreach ((new CategoryReportRepository())->getConfigArray() as $report) {
            $reportId = (int)$report['id'];
            $isEditable = (int)$report['organization_id'] === (int)$gCurrentOrgId;
            $description = trim((string)preg_replace('/\s+/u', ' ', html_entity_decode(
                (string)$report['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $descriptionLength = function_exists('mb_strlen') ? mb_strlen($description, 'UTF-8') : strlen($description);
            if ($descriptionLength > 200) {
                $description = (function_exists('mb_substr')
                    ? mb_substr($description, 0, 199, 'UTF-8')
                    : substr($description, 0, 199)) . '…';
            }
            $templateReport = array(
                'uuid' => (string)$reportId,
                'id' => $reportId,
                'name' => $report['name'],
                'description' => SecurityUtils::encodeHTML($description),
                'columnCount' => count($report['columns']),
                'default' => (bool)$report['default_conf'],
                'urlEdit' => $isEditable ? SecurityUtils::encodeUrl($baseUrl,
                    array('mode' => 'edit', 'crt_id' => $reportId)) : '',
                'actions' => array()
            );

            $templateReport['actions'][] = array(
                'url' => SecurityUtils::encodeUrl($baseUrl, array('crt_id' => $reportId)),
                'icon' => 'bi bi-play-circle',
                'tooltip' => $gL10n->get('SYS_RUN_REPORT')
            );
            if ($isEditable) {
                $templateReport['actions'][] = array(
                    'url' => $templateReport['urlEdit'],
                    'icon' => 'bi bi-pencil-square',
                    'tooltip' => $gL10n->get('SYS_EDIT')
                );
            }
            $templateReport['actions'][] = array(
                'url' => SecurityUtils::encodeUrl($baseUrl, array('mode' => 'copy', 'crt_id' => $reportId)),
                'icon' => 'bi bi-copy',
                'tooltip' => $gL10n->get('SYS_COPY')
            );
            if ($isEditable) {
                $templateReport['actions'][] = array(
                    'dataHref' => 'callUrlHideElement(\'adm_category_report_' . $reportId . '\', \'' .
                        SecurityUtils::encodeUrl($baseUrl, array('mode' => 'report_delete', 'crt_id' => $reportId)) .
                        '\', \'' . $gCurrentSession->getCsrfToken() . '\', \'refreshCategoryReportList\')',
                    'dataMessage' => $gL10n->get('SYS_DELETE_REPORT'),
                    'icon' => 'bi bi-trash',
                    'tooltip' => $gL10n->get('SYS_DELETE')
                );
            }
            $reports[] = $templateReport;
        }

        $this->assignSmartyVariable('reports', $reports);
        $this->addHtmlByTemplate('modules/category-report.manage.tpl');
    }
}
