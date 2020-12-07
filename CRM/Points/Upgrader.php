<?php
use CRM_Points_ExtensionUtil as E;
use CRM_Points_Upgrader_CiviRulesEntity as PointsCiviRulesEntity;

/**
 * Collection of upgrade steps.
 */
class CRM_Points_Upgrader extends CRM_Points_Upgrader_Base {

  private function getOurCiviRulesEntities(): array {
    return [
        new PointsCiviRulesEntity('Action', [
            'name' => 'civipoints_grant',
            'label' => 'Grant points',
            'class_name' => 'CRM_Points_CivirulesAction',
            'is_active' => 1
        ]),
        new PointsCiviRulesEntity('Condition', [
            'name'       => 'civipoints_getsum',
            'label'      => 'Contact has points',
            'class_name' => 'CRM_Points_CivirulesCondition',
            'is_active'  => 1,
        ])
    ];
  }

  public function install() {
  }

  /**
   * @throws CiviCRM_API3_Exception
   */
  private function installOurCiviRulesEntities() {
    $ourCiviRulesEntities = $this->getOurCiviRulesEntities();
    foreach ($ourCiviRulesEntities as $entity) {
      if ($entity->missingFromDatabase()) $entity->saveInstallationValuesToDatabase();
    }
  }

  /**
   * @throws CiviCRM_API3_Exception
   */
  public function enable() {
    $this->installOurCiviRulesEntities();
    $this->removeWarningsFromCiviRulesDescriptions();
  }

  /**
   * @throws CiviCRM_API3_Exception
   */
  public function disable() {
    $ourCiviRulesEntities = $this->getOurCiviRulesEntities();
    foreach ($ourCiviRulesEntities as $entity) {
      if ($entity->missingFromDatabase()) continue;
      foreach ($entity->getConnectedRules() as $ruleParams) {
        $this->disableRuleAndLeaveWarningInDescription($ruleParams);
      }
    }
  }

  /**
   * @param $ruleParams
   * @throws CiviCRM_API3_Exception
   */
  private function disableRuleAndLeaveWarningInDescription($ruleParams) {
    $description = $ruleParams['description'];
    $descriptionFieldMaxLength = 256;
    $warning = "WILL CAUSE ERRORS IF USED WITHOUT CIVIPOINTS EXTENSION ENABLED!";
    if (!CRM_Utils_String::startsWith($description, $warning)) {
      $description = CRM_Utils_String::ellipsify(trim("$warning $description"), $descriptionFieldMaxLength);
    }
    civicrm_api3('CiviRuleRule', 'create', [
        'id' => $ruleParams['id'],
        'is_active' => FALSE,
        'description' => $description,
    ]);
  }

  private function removeWarningsFromCiviRulesDescriptions() {
    try {
      $apiRuleResult = civicrm_api3('CiviRuleRule', 'get', []);
      foreach ($apiRuleResult['values'] ?? [] as $ruleParams) {
        $warning = "WILL CAUSE ERRORS IF USED WITHOUT CIVIPOINTS EXTENSION ENABLED!";
        $description = trim(str_replace($warning, '', $ruleParams['description']));
        civicrm_api3('CiviRuleRule', 'create', [
            'id' => $ruleParams['id'],
            'description' => $description,
        ]);
      }
    } catch (CiviCRM_API3_Exception $e) {}
  }

  /**
   */
  public function uninstall() {
  }

  /**
   * @throws CiviCRM_API3_Exception
   */
  public function upgrade_1000(): bool {
    $this->ctx->log->info('Installing any missing CiviPoints-related CiviRules conditions and actions');
    $this->installOurCiviRulesEntities();
    return TRUE;
  }

}
