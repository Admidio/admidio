<?php
namespace Admidio\CategoryReport\Service;

use Admidio\CategoryReport\Entity\CategoryReport as CategoryReportEntity;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Exception;

/** Persists and loads category reports and their column definitions. */
class CategoryReportRepository
{
    private array $arrConfiguration = array();

    /**
     * Method checks whether a configuration with the transferred name already exists.
     * If this is the case, "- copy" is appended.
     * @param string $name Name that should be checked.
     * @return  string
     * @throws Exception
     */
    public function createName(string $name): string
    {
        global $gDb, $gL10n, $gCurrentOrgId;

        $sql = ' SELECT crt_name
                   FROM ' . TBL_CATEGORY_REPORT . '
                  WHERE (  crt_org_id = ? -- $gCurrentOrgId
                        OR crt_org_id IS NULL ) ';
        $statement = $gDb->queryPrepared($sql, array($gCurrentOrgId));

        while ($row = $statement->fetch()) {
            $reportName = html_entity_decode(
                Language::translateIfTranslationStrId((string)$row['crt_name']),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
            if ($reportName === $name) {
                $name .= ' - ' . $gL10n->get('SYS_CARBON_COPY');
            }
        }

        return $name;
    }

    /**
     * Funktion liest das Konfigurationsarray ein
     * @return  array $config  das Konfigurationsarray
     * @throws Exception
     */
    public function getConfigArray(): array
    {
        global $gDb, $gSettingsManager, $gCurrentOrgId;

        if (count($this->arrConfiguration) === 0) {
            $sql = ' SELECT crt_id
                       FROM ' . TBL_CATEGORY_REPORT . '
                      WHERE ( crt_org_id = ? -- $gCurrentOrgId
                         OR crt_org_id IS NULL ) ';
            $statement = $gDb->queryPrepared($sql, array($gCurrentOrgId));

            while ($row = $statement->fetch()) {
                $categoryReport = new CategoryReportEntity($gDb, (int)$row['crt_id']);
                $columns = $categoryReport->getColumnDefinitions();
                $columnFields = array_column($columns, 'field');
                $columnConditions = array_column($columns, 'condition');

                $values = array();
                $values['id'] = $categoryReport->getValue('crt_id');
                $values['organization_id'] = $categoryReport->getValue('crt_org_id');
                $values['name'] = SecurityUtils::encodeHTML(Language::translateIfTranslationStrId(
                    (string)$categoryReport->getValue('crt_name', 'database')
                ));
                $values['description'] = SecurityUtils::encodeHTML((string)$categoryReport->getValue('crt_description', 'database'));
                $values['columns'] = $columns;
                $values['col_fields'] = implode(',', $columnFields);
                $values['col_conditions'] = implode(',', $columnConditions);
                $values['selection_role'] = $categoryReport->getValue('crt_selection_role', 'database');
                $values['selection_cat'] = $categoryReport->getValue('crt_selection_cat', 'database');
                $values['number_col'] = $categoryReport->getValue('crt_number_col');
                $values['default_conf'] = false;
                if ($gSettingsManager->getInt('category_report_default_configuration') === (int)$categoryReport->getValue('crt_id')) {
                    $values['default_conf'] = true;
                }
                $this->arrConfiguration[] = $values;
            }
        }

        return $this->arrConfiguration;
    }

    /**
     * Funktion speichert das Konfigurationsarray
     * @param array $arrConfiguration
     * @return  array das Konfigurationsarray
     * @throws Exception
     */
    public function saveConfigArray(array $arrConfiguration): array
    {
        global $gDb, $gCurrentOrgId, $gSettingsManager;

        $defaultConfiguration = 0;

        $gDb->startTransaction();

        foreach ($arrConfiguration as $values) {
            if ($values['id'] === '' || $values['id'] > 0) {                  // id > 0 (=edit a configuration) or '' (=append a configuration)
                $categoryReport = new CategoryReportEntity($gDb, (int)$values['id']);
                $categoryReport->setValue('crt_org_id', $gCurrentOrgId);
                $categoryReport->setValue('crt_name', $values['name']);
                $categoryReport->setValue('crt_description', self::normalizeDescription((string)($values['description'] ?? '')));
                $categoryReport->setValue('crt_selection_role', $values['selection_role']);
                $categoryReport->setValue('crt_selection_cat', $values['selection_cat']);
                $categoryReport->setValue('crt_number_col', $values['number_col']);
                $columns = $values['columns'] ?? array();
                $serializedFields = implode(',', array_column($columns, 'field'));
                $serializedConditions = implode(',', array_map(
                    static fn(array $column): string => (string)($column['condition'] ?? ''),
                    $columns
                ));
                $legacyFields = (string)($values['col_fields'] ?? $serializedFields);
                $legacyConditions = (string)($values['col_conditions'] ?? $serializedConditions);
                if (count($columns) === 0
                    || $serializedFields !== $legacyFields
                    || $serializedConditions !== $legacyConditions) {
                    $columns = array();
                    $fields = array_values(array_filter(
                        explode(',', $legacyFields),
                        static fn(string $field): bool => $field !== ''
                    ));
                    $conditions = explode(',', $legacyConditions);
                    foreach ($fields as $index => $field) {
                        $columns[] = array(
                            'field' => $field,
                            'condition' => $conditions[$index] ?? ''
                        );
                    }
                }
                $categoryReport->setColumns($columns);
                $categoryReport->save();
                $reportId = (int)$categoryReport->getValue('crt_id');

                if ($values['default_conf'] === true || $defaultConfiguration === 0) {
                    $defaultConfiguration = $reportId;
                }
                // set default configuration
                $gSettingsManager->set('category_report_default_configuration', $defaultConfiguration);
            } else {                                                            // delete
                $values['id'] = $values['id'] * (-1);
                $categoryReport = new CategoryReportEntity($gDb, (int)$values['id']);
                $categoryReport->delete();
            }
        }

        $gDb->endTransaction();

        $this->arrConfiguration = array();

        return $this->getConfigArray();
    }


    /** Save a single report submitted by the edit dialog. */
    public function saveFromRequest(array $request): array
    {
        global $gCurrentSession, $gDb, $gCurrentOrgId, $gSettingsManager, $gL10n;

        $token = (string)($request['adm_csrf_token'] ?? '');
        $form = $gCurrentSession->getFormObject($token);
        if ($form === null || $token !== $form->getCsrfToken()) {
            throw new Exception('Invalid or missing CSRF token!');
        }
        $action = (string)($request['report_action'] ?? '');
        if (!in_array($action, array('new', 'edit', 'copy'), true)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        $sourceId = (int)($request['source_id'] ?? 0);
        if ($action !== 'new' && $this->findReport($sourceId, $action === 'edit') === null) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        $name = trim((string)($request['name'] ?? ''));
        if ($name === '') {
            throw new Exception('SYS_FIELD_EMPTY', array('SYS_DESIGNATION'));
        }
        $description = self::normalizeDescription((string)($request['description'] ?? ''));
        $fields = $request['columns'] ?? array();
        $properties = $request['columnsRoleProp'] ?? array();
        $conditions = $request['conditions'] ?? array();
        if (!is_array($fields) || !is_array($properties) || !is_array($conditions) || count($fields) === 0) {
            throw new Exception('SYS_FIELD_EMPTY', array('SYS_COLUMN'));
        }
        $columns = array();
        $generator = new CategoryReportGenerator();
        foreach ($fields as $index => $field) {
            $field = (string)$field;
            if ($field === '') {
                continue;
            }
            if ($field[0] === 'r') {
                $property = (string)($properties[$index] ?? 'r');
                if (!in_array($property, array('r', 'l', 'w', 'f', 'b', 'e', 'd'), true)) {
                    throw new Exception('SYS_INVALID_PAGE_VIEW');
                }
                $field = $property . substr($field, 1);
            }
            if ($generator->isInHeaderSelection($field) === 0) {
                throw new Exception('SYS_INVALID_PAGE_VIEW');
            }
            $condition = str_replace(array('<', '>', "\r", "\n"), array('{', '}', ' ', ' '), (string)($conditions[$index] ?? ''));
            $columns[] = array('field' => $field, 'condition' => trim($condition));
        }
        if ($columns === array()) {
            throw new Exception('SYS_FIELD_EMPTY', array('SYS_COLUMN'));
        }
        $roles = $request['selection_role'] ?? array();
        $categories = $request['selection_cat'] ?? array();
        if (!is_array($roles) || !is_array($categories)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        $normalizeSelection = static function (array $selection): string {
            $ids = array();
            foreach ($selection as $value) {
                if ($value === '') {
                    continue;
                }
                if (!is_scalar($value) || !ctype_digit((string)$value) || (int)$value <= 0) {
                    throw new Exception('SYS_INVALID_PAGE_VIEW');
                }
                $ids[] = (int)$value;
            }
            return implode(',', array_unique($ids));
        };
        $gDb->startTransaction();
        $report = new CategoryReportEntity($gDb, $action === 'edit' ? $sourceId : 0);
        $report->setValue('crt_org_id', $gCurrentOrgId);
        $report->setValue('crt_name', $name);
        $report->setValue('crt_description', $description);
        $report->setValue('crt_selection_role', $normalizeSelection($roles));
        $report->setValue('crt_selection_cat', $normalizeSelection($categories));
        $report->setValue('crt_number_col', isset($request['number_col']) ? 1 : 0);
        $report->setColumns($columns);
        $report->save();
        $id = (int)$report->getValue('crt_id');
        if ($gSettingsManager->getInt('category_report_default_configuration') === 0) {
            $gSettingsManager->set('category_report_default_configuration', $id);
        }
        $gDb->endTransaction();
        $this->arrConfiguration = array();
        return array('status' => 'success', 'message' => $gL10n->get('SYS_SAVE_DATA'),
            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/category_report.php', array('mode' => 'manage')));
    }

    /** Delete a report belonging to the current organization and update the default report if necessary. */
    public function deleteReport(int $id): void
    {
        global $gDb, $gSettingsManager;
        $row = $this->findReport($id);
        if ($row === null) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        (new CategoryReportEntity($gDb, $id))->delete();
        $this->arrConfiguration = array();
        if ($row['default_conf']) {
            $remainingReports = $this->getConfigArray();
            $gSettingsManager->set('category_report_default_configuration',
                count($remainingReports) > 0 ? (int)$remainingReports[0]['id'] : 0);
        }
    }

    /** @return array<string,mixed>|null */
    public function findReport(int $id, bool $requireOwnership = true): ?array
    {
        global $gCurrentOrgId;
        foreach ($this->getConfigArray() as $row) {
            if ((int)$row['id'] === $id && (!$requireOwnership || (int)$row['organization_id'] === (int)$gCurrentOrgId)) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Normalize a plain-text report description and enforce its database length.
     *
     * @param string $description Submitted report description.
     * @return string Normalized plain-text description.
     * @throws Exception If the description exceeds 4000 characters.
     */
    private static function normalizeDescription(string $description): string
    {
        $description = trim(strip_tags($description));
        $length = function_exists('mb_strlen') ? mb_strlen($description, 'UTF-8') : strlen($description);
        if ($length > 4000) {
            throw new Exception('SYS_FIELD_INVALID_INPUT', array('SYS_DESCRIPTION'));
        }

        return $description;
    }
}
