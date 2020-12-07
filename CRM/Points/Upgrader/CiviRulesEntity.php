<?php


class CRM_Points_Upgrader_CiviRulesEntity {

  private $entityType;
  private $databaseValues;
  private $installationValues;

  /**
   * CRM_Points_Upgrader_CiviRulesEntity constructor.
   * @param string $entityType
   * @param array $installationValues
   */
  public function __construct(string $entityType, array $installationValues) {
    $this->entityType = $entityType;
    $this->installationValues = $installationValues;
  }

  private function loadFromDatabase() {
    try {
      $apiEntityResult = civicrm_api3('CiviRule' . $this->entityType, 'get', [
          'sequential' => TRUE,
          'class_name' => $this->installationValues['class_name'],
      ]);
    } catch (CiviCRM_API3_Exception $e) {
      $this->databaseValues = [];
    }
    $this->databaseValues = $apiEntityResult['values'][0] ?? [];
  }

  /**
   * @throws CiviCRM_API3_Exception
   */
  public function saveInstallationValuesToDatabase() {
    civicrm_api3('CiviRule' . $this->entityType, 'create', $this->installationValues);
  }

  /**
   * @return bool
   */
  public function missingFromDatabase(): bool {
    if (!isset($this->databaseValues)) $this->loadFromDatabase();
    return empty($this->databaseValues);
  }

  /**
   * @return array
   */
  public function getConnectedRules(): array {
    if ($this->missingFromDatabase()) return [];
    try {
      $apiRuleConnectionResult = civicrm_api3('CiviRuleRule' . $this->entityType, 'get', [
          'sequential' => 1,
          strtolower($this->entityType) . '_id' => $this->databaseValues['id'],
      ]);
    } catch (CiviCRM_API3_Exception $e) {
      return [];
    }
    $ruleConnections = $apiRuleConnectionResult['values'] ?? [];
    return $this->getRulesFromRuleConnections($ruleConnections);
  }

  private function getRulesFromRuleConnections(array $ruleConnections): array {
    $rules = [];
    foreach ($ruleConnections as $ruleConnection) {
      try {
        $rules[] = civicrm_api3('CiviRuleRule', 'getsingle', ['id' => $ruleConnection['rule_id']]);
      } catch (CiviCRM_API3_Exception $e) {}
    }
    return $rules;
  }

}