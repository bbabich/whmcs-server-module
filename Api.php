<?php
/**
 * Api file.
 *
 * @author    Mindaugas Valinskis <mindaugas@duomenucentras.lt>
 * @copyright 2013 UAB "Duomenų centras"
 * @version   $Id: $
 */

/**
 * Api class.
 */
class Api
{

  /**
   * Client ID.
   *
   * @var integer
   */
  private $_iClientId = 0;

  /**
   * Hashed password.
   *
   * @var string
   */
  private $_sPassWord = '';

  /**
   * URL to Public API.
   *
   * @var string
   */
  private $_sUrl = 'https://my.balticservers.com/korys/api/';


  /**
   * Constructor.
   *
   * @param array $aConfig Module parameters.
   */
  public function __construct(array $aConfig=array())
  {
    if (isset($aConfig['configoption1'], $aConfig['configoption2']) === TRUE) {
      $this->_iClientId = $aConfig['configoption1'];
      $this->_sPassWord = sha1($aConfig['configoption2']);
    }

  }//end __construct()


  /**
   * Execute command.
   *
   * @param string $sFunction Function name to execute.
   * @param array  $aParams   Function parameters.
   *
   * @return array
   */
  public function call($sFunction, array $aParams=array())
  {
    if ($this->_iClientId === 0) {
      $sAuth = '';
    } else {
      $sAuth = $this->_iClientId.':'.$this->_sPassWord;
    }

    $ch = curl_init();

    $aOptions = array(
                 CURLOPT_URL            => $this->_sUrl.$sFunction,
                 CURLOPT_RETURNTRANSFER => TRUE,
                 CURLOPT_SSL_VERIFYPEER => TRUE,
                 CURLOPT_POST           => TRUE,
                 CURLOPT_POSTFIELDS     => json_encode($aParams),
                 CURLOPT_TIMEOUT        => 10,
                 CURLOPT_HTTPHEADER     => array('Content-type: application/json'),
                 CURLOPT_USERPWD        => $sAuth,
                 CURLOPT_HTTPAUTH       => CURLAUTH_BASIC
                );

    curl_setopt_array($ch, $aOptions);

    $json = curl_exec($ch);
    $data = json_decode($json, TRUE);

    if ($data === NULL) {
      $data = array();
    }

    curl_close($ch);

    return $data;

  }//end call()


  /**
   * Convert product list into comma separated line.
   *
   * @param string $sLang User language.
   *
   * @return string
   */
  public function serverProductString($sLang)
  {
    $aResponse = $this->call('getServerPlanList', array());

    if ($aResponse['bSuccess'] === TRUE) {
      return implode(',', $aResponse['aResult']);
    }

    return '';

  }//end serverProductString()


  /**
   * WHMCS response to end client.
   *
   * @param array $aResponse API response.
   *
   * @return string
   */
  public static function getResult(array $aResponse)
  {
    if (empty($aResponse) === TRUE) {
      return 'Unexpected error.';
    }

    $sResult = 'success';

    if ($aResponse['bSuccess'] === FALSE) {
      $sResult = end($aResponse['aError']);
    }

    return $sResult;

  }//end getResult()


  /**
   * Gets available server commands.
   *
   * @param integer $iWhmcsSid WHMCS service ID.
   * @param integer $iUserId   WHMCS User ID.
   *
   * @return string
   */
  public function getServerCommands($iWhmcsSid, $iUserId)
  {
    $aParams = array();

    $aParams['iWhmcsSid'] = $iWhmcsSid;
    $aParams['iUserId']   = $iUserId;

    $aResponse = $this->call('whmcsServerCmdList', $aParams);

    if ($aResponse['bSuccess'] === TRUE) {
      return $aResponse['aResult'];
    }

    return array();

  }//end getServerCommands()


  /**
   * Translates WHMCS configurations into normal keys.
   *
   * @param array $aConfig WHMCS configuration values.
   *
   * @return string
   */
  public function translateConfig(array $aConfig)
  {
    $aParams = array();

    $aParams['sPlanName'] = $aConfig['configoption3'];
    $aParams['iPlanID']   = $aConfig['configoption3'];

    if (isset($aConfig['serviceid']) === TRUE) {
      $aParams['iServiceId'] = (int) $aConfig['serviceid'];
      $aParams['iProductId'] = (int) $aConfig['pid'];

      $aHosting = mysql_fetch_array(select_query('tblhosting', '*', array('id' => $aParams['iServiceId'])));
      $sText    = trim(strtolower($aHosting['billingcycle']));

      if (strpos($sText, 'monthly') !== FALSE) {
        $aParams['iCycleID'] = 3;
      } else if (strpos($sText, 'quarterly') !== FALSE) {
        $aParams['iCycleID'] = 4;
      } else if (strpos($sText, 'semi-annually') !== FALSE) {
        $aParams['iCycleID'] = 5;
      } else if (strpos($sText, 'annually') !== FALSE
          || strpos($sText, 'biennially') !== FALSE
          || strpos($sText, 'triennially') !== FALSE
      ) {
        $aParams['iCycleID'] = 6;
      } else {
        $aParams['iCycleID'] = 3;
      }

      $aVariants = array_filter($aConfig['configoptions'], 'trim');
      $aVariants = $this->parsePlanVariantIds($aParams['iPlanID'], array_values($aVariants));

      $aParams['aVariants'] = $aVariants;
    }//end if

    if (isset($aConfig['customfields']['iSrvPackageId']) === TRUE) {
      $aParams['iSrvPackageId'] = (int) $aConfig['customfields']['iSrvPackageId'];
    }

    return $aParams;

  }//end translateConfig()


  /**
   * Client contact block.
   *
   * @param array $aConfig WHMCS configuration values.
   *
   * @return string
   */
  public function getContactBlock(array $aConfig)
  {
    $aParams = array();
    $aClient = $aConfig['clientsdetails'];

    $aParams['sFirstName']   = $aClient['firstname'];
    $aParams['sLastName']    = $aClient['lastname'];
    $aParams['sCompanyName'] = $aClient['companyname'];
    $aParams['sEmail']       = $aClient['email'];
    $aParams['sAddress1']    = $aClient['address1'];
    $aParams['sAddress2']    = $aClient['address2'];
    $aParams['sCity']        = $aClient['city'];
    $aParams['sPhone']       = $aClient['phonenumber'];
    $aParams['sCountryCode'] = $aClient['countrycode'];
    $aParams['sZipCode']     = $aClient['postcode'];
    $aParams['sLanguage']    = $aClient['language'];
    $aParams['sUsername']    = $aConfig['username'];

    if ($aParams['sLanguage'] === '') {
      $aParams['sLanguage'] = 'en';
    }

    return $aParams;

  }//end getContactBlock()


  /**
   * Returns list of server custom options.
   *
   * @param string $sPlanName Plan name.
   *
   * @return array
   */
  public function getServerPlanCustomization($sPlanName)
  {
    $aParams   = array('sPlanName' => $sPlanName);
    $aResponse = $this->call('getServerPlanVariants', $aParams);

    if ($aResponse['bSuccess'] === TRUE) {
      return $aResponse['aResult'];
    }

    return array();

  }//end getServerPlanCustomization()


  /**
   * Checks if plan name is available in stock.
   *
   * @param string $sPlanName Plan name.
   *
   * @return boolean
   */
  public function availableInStock($sPlanName)
  {
    $aParams   = array('sPlanName' => $sPlanName);
    $aResponse = $this->call('checkServerStock', $aParams);

    if ($aResponse['bSuccess'] === TRUE) {
      return (bool) $aResponse['aResult']['bAvailable'];
    }

    return FALSE;

  }//end availableInStock()


  /**
   * Get API request results.
   *
   * @param array $aResponse API response.
   *
   * @return array
   */
  public static function getData(array $aResponse)
  {
    if (empty($aResponse['aResult']) === FALSE) {
      return $aResponse['aResult'];
    }

    return array();

  }//end getData()


  /**
   * Converts variants names to ids.
   *
   * @param integer $iPlanId   Server plan ID or plan name.
   * @param array   $aVariants Variant names.
   *
   * @return array
   */
  public function parsePlanVariantIds($iPlanId, array $aVariants)
  {
    $aResponse = $this->call('getServerPlanDetails', array('iPlanID' => $iPlanId));

    if (empty($aResponse) === TRUE || $aResponse['bSuccess'] === FALSE) {
      return array();
    }

    $aVariants   = array_map('strtolower', $aVariants);
    $aVariantIds = array();

    foreach ($aResponse['aResult']['aOptions'] as $aOption) {
      foreach ($aOption as $aVariant) {
        $mKey = array_search(strtolower($aVariant['sName']), $aVariants);

        if ($mKey !== FALSE) {
          $aVariantIds[] = (int) $aVariant['iVariantId'];
          unset($aVariants[$mKey]);
        }
      }
    }

    return $aVariantIds;

  }//end parsePlanVariantIds()


}//end class

?>