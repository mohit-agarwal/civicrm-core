<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.6                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2014                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
 */

/**
 * This class introduces component to the system and provides all the
 * information about it. It needs to extend CRM_Core_Component_Info
 * abstract class.
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2014
 * $Id$
 *
 */
class CRM_Mailing_Info extends CRM_Core_Component_Info {

  /**
   * @inheritDoc
   */
  protected $keyword = 'mailing';


  /**
   * @inheritDoc
   * @return array
   */
  public function getInfo() {
    return array(
      'name' => 'CiviMail',
      'translatedName' => ts('CiviMail'),
      'title' => 'CiviCRM Mailing Engine',
      'search' => 1,
      'showActivitiesInCore' => 1,
    );
  }

  /**
   * EXPERIMENTAL: Get a list of AngularJS modules
   *
   * @return array
   *   list of modules; same format as CRM_Utils_Hook::angularModules(&$angularModules)
   * @see CRM_Utils_Hook::angularModules
   */
  public function getAngularModules() {
    $result = array();
    $result['crmMailing'] = array(
      'ext' => 'civicrm',
      'js' => array(
        'js/angular-crmMailing.js',
        'js/angular-crmMailing/*.js',
      ),
      'css' => array('css/angular-crmMailing.css'),
      'partials' => array('partials/crmMailing'),
    );
    $result['crmMailingAB'] = array(
      'ext' => 'civicrm',
      'js' => array(
        'js/angular-crmMailingAB.js',
        'js/angular-crmMailingAB/*.js',
      ),
      'css' => array('css/angular-crmMailingAB.css'),
      'partials' => array('partials/crmMailingAB'),
    );
    $result['crmD3'] = array(
      'ext' => 'civicrm',
      'js' => array(
        'js/angular-crmD3.js',
        'bower_components/d3/d3.min.js',
      ),
    );

    $session = CRM_Core_Session::singleton();
    $contactID = $session->get('userID');

    $params = array('options' => array('limit' => 0));
    $civiMails = civicrm_api3('Mailing', 'get', $params + array(
      'is_completed' => 1,
      'return' => array('id', 'name', 'scheduled_date'),
    ));
    $campNames = civicrm_api3('Campaign', 'get', $params);
    $groupNames = civicrm_api3('Group', 'get', $params);
    $headerfooterList = civicrm_api3('MailingComponent', 'get', $params);

    $emailAdd = civicrm_api3('Email', 'get', array(
      'sequential' => 1,
      'return' => "email",
      'contact_id' => $contactID,
    ));
    $mesTemplate = civicrm_api3('MessageTemplate', 'get', $params + array(
      'sequential' => 1,
      'return' => array("msg_html", "id", "msg_title", "msg_subject", "msg_text"),
      'workflow_id' => array('IS NULL' => ""),
    ));
    $mailGrp = civicrm_api3('MailingGroup', 'get', $params);
    $mailTokens = civicrm_api3('Mailing', 'gettokens', array('entity' => array('contact', 'mailing'), 'sequential' => 1));
    $fromAddress = civicrm_api3('OptionValue', 'get', $params + array(
      'option_group_id' => "from_email_address",
    ));
    CRM_Core_Resources::singleton()->addSetting(array(
      'crmMailing' => array(
        'civiMails' => array_values($civiMails['values']),
        'campNames' => array_values($campNames['values']),
        'groupNames' => array_values($groupNames['values']),
        'headerfooterList' => array_values($headerfooterList['values']),
        'mesTemplate' => array_values($mesTemplate['values']),
        'emailAdd' => array_values($emailAdd['values']),
        'mailGrp' => array_values($mailGrp['values']),
        'mailTokens' => $mailTokens['values'],
        'contactid' => $contactID,
        'requiredTokens' => CRM_Utils_Token::getRequiredTokens(),
        'enableReplyTo' => (int) CRM_Core_BAO_Setting::getItem(CRM_Core_BAO_Setting::MAILING_PREFERENCES_NAME, 'replyTo'),
        'fromAddress' => array_values($fromAddress['values']),
        'defaultTestEmail' => civicrm_api3('Contact', 'getvalue', array(
            'id' => 'user_contact_id',
            'return' => 'email',
          )),
        'visibility' => CRM_Utils_Array::makeNonAssociative(CRM_Core_SelectValues::groupVisibility()),
        'workflowEnabled' => CRM_Mailing_Info::workflowEnabled(),
      ),
    ));
    CRM_Core_Resources::singleton()->addPermissions(array(
      'view all contacts',
      'access CiviMail',
      'create mailings',
      'schedule mailings',
      'approve mailings',
      'delete in CiviMail',
    ));

    return $result;
  }

  /**
   * @return bool
   */
  public static function workflowEnabled() {
    $config = CRM_Core_Config::singleton();

    // early exit, since not true for most
    if (!$config->userSystem->is_drupal ||
      !function_exists('module_exists')
    ) {
      return FALSE;
    }

    if (!module_exists('rules')) {
      return FALSE;
    }

    $enableWorkflow = CRM_Core_BAO_Setting::getItem(CRM_Core_BAO_Setting::MAILING_PREFERENCES_NAME,
      'civimail_workflow',
      NULL,
      FALSE
    );

    return ($enableWorkflow &&
      $config->userSystem->is_drupal
    ) ? TRUE : FALSE;
  }

  /**
   * @inheritDoc
   * @param bool $getAllUnconditionally
   *
   * @return array
   */
  public function getPermissions($getAllUnconditionally = FALSE) {
    $permissions = array(
      'access CiviMail',
      'access CiviMail subscribe/unsubscribe pages',
      'delete in CiviMail',
      'view public CiviMail content',
    );

    if (self::workflowEnabled() || $getAllUnconditionally) {
      $permissions[] = 'create mailings';
      $permissions[] = 'schedule mailings';
      $permissions[] = 'approve mailings';
    }

    return $permissions;
  }


  /**
   * @inheritDoc
   * @return null
   */
  public function getUserDashboardElement() {
    // no dashboard element for this component
    return NULL;
  }

  /**
   * @return null
   */
  public function getUserDashboardObject() {
    // no dashboard element for this component
    return NULL;
  }

  /**
   * @inheritDoc
   * @return array
   */
  public function registerTab() {
    return array(
      'title' => ts('Mailings'),
      'id' => 'mailing',
      'url' => 'mailing',
      'weight' => 45,
    );
  }

  /**
   * @inheritDoc
   * @return array
   */
  public function registerAdvancedSearchPane() {
    return array(
      'title' => ts('Mailings'),
      'weight' => 20,
    );
  }

  /**
   * @inheritDoc
   * @return null
   */
  public function getActivityTypes() {
    return NULL;
  }

  /**
   * add shortcut to Create New.
   * @param $shortCuts
   */
  public function creatNewShortcut(&$shortCuts) {
  }

}
