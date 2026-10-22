<?php
use Admidio\Components\Entity\Component;
use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\CategoryReport\Service\CategoryReportRepository;
use Admidio\UI\Presenter\CategoryReportAdministrationPresenter;
use Admidio\UI\Presenter\CategoryReportPresenter;
use Admidio\UI\Presenter\CategoryReportFormPresenter;

$getMode = 'html';
try {
    require_once(__DIR__ . '/../system/common.php');

    if (!Hooks::applyFilters('category_report_enabled', $gSettingsManager->getBool('category_report_module_enabled'))) {
        throw new Exception('SYS_MODULE_DISABLED');
    }
    if (!Component::isVisible('CATEGORY-REPORT')) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'html',
        'validValues' => array('html', 'print', 'xlsx', 'ods', 'csv-oo', 'pdf', 'pdfl', 'manage', 'new', 'edit', 'copy', 'report_save', 'report_delete')));
    if (in_array($getMode, array('manage', 'new', 'edit', 'copy', 'report_save', 'report_delete'), true) && !$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    switch ($getMode) {
        case 'manage':
            require_once(__DIR__ . '/../system/login_valid.php');
            $gNavigation->addUrl(CURRENT_URL, $gL10n->get('SYS_MANAGE_CATEGORY_REPORTS'));
            $page = new CategoryReportAdministrationPresenter();
            $page->createList();
            $page->show();
            break;
        case 'new':
        case 'edit':
        case 'copy':
            require_once(__DIR__ . '/../system/login_valid.php');
            $reportId = admFuncVariableIsValid($_GET, 'crt_id', 'int', array('defaultValue' => 0));
            $page = new CategoryReportFormPresenter();
            $page->createForm($getMode, $reportId);
            $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            $page->show();
            break;
        case 'report_save':
            require_once(__DIR__ . '/../system/login_valid.php');
            echo json_encode((new CategoryReportRepository())->saveFromRequest($_POST));
            break;
        case 'report_delete':
            require_once(__DIR__ . '/../system/login_valid.php');
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token'] ?? '');
            $reportId = admFuncVariableIsValid($_GET, 'crt_id', 'int');
            (new CategoryReportRepository())->deleteReport($reportId);
            echo json_encode(array('status' => 'success'));
            break;
        default:
            (new CategoryReportPresenter())->showReport();
    }
} catch (Throwable $e) {
    handleException($e, in_array($getMode, array('report_save', 'report_delete'), true));
}
