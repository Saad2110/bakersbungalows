<?php
/**
 * @package     VikChannelManager
 * @subpackage  com_vikchannelmanager
 * @author      e4j - Extensionsforjoomla.com
 * @copyright   Copyright (C) 2018 e4j - Extensionsforjoomla.com. All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @link        https://e4jconnect.com - https://vikwp.com
 */

// No direct access to this file
defined('ABSPATH') or die('No script kiddies please!');

// import Joomla view library
jimport('joomla.application.component.view');

class VikChannelManagerViewexecpcid extends JViewUI {
	
	function display($tpl = null) {

		$dbo = JFactory::getDbo();
		
		if (!function_exists('curl_init')) {
			echo VikChannelManager::getErrorFromMap('e4j.error.Curl');
			exit;
		}
		
		$config = VikChannelManager::loadConfiguration();
		$validate = array('apikey');
		foreach ($validate as $v) {
			if (empty($config[$v])) {
				echo VikChannelManager::getErrorFromMap('e4j.error.Settings');
				exit;
			}
		}

		$channel_source = VikRequest::getString('channel_source');
		$ota_id = VikRequest::getString('otaid');
		
		$e4jc_url = "https://e4jconnect.com/channelmanager/?r=pcid&c=generic";
		$xml = '<?xml version="1.0" encoding="UTF-8"?>
<!-- VikChannelManager PCID Request e4jConnect.com - Module Extensionsforjoomla.com -->
<PCIDataRQ xmlns="http://www.e4jconnect.com/schemas/pcidrq">
	<Notify client="'.JUri::root().'"/>
	<Api key="'.$config['apikey'].'"/>
	<Channel source="'.$channel_source.'"/>
	<Booking otaid="'.$ota_id.'"/>
</PCIDataRQ>';
		
		$e4jC = new E4jConnectRequest($e4jc_url);
		$e4jC->setPostFields($xml);
		$e4jC->slaveEnabled = true;
		$rs = $e4jC->exec();
		if ($e4jC->getErrorNo()) {
			echo @curl_error($e4jC->getCurlHeader());
			exit;
		}
		if (substr($rs, 0, 9) == 'e4j.error' || substr($rs, 0, 11) == 'e4j.warning') {
			echo '<p style="margin: 10px 0px; padding: 12px; text-align: center; color: #D8000C; background: #FFBABA;">'.VikChannelManager::getErrorFromMap($rs).'</p>';
			exit;
		}
		
		// the salt is hashed twice
		$cipher = VikChannelManager::loadCypherFramework(md5($config['apikey'] . "e4j" . $ota_id));
		
		// @array credit card response
		// [card_number] @string : 4242 4242 4242 ****
		// [cvv] @int : 123
		$credit_card_response = json_decode($cipher->decrypt($rs), true);
		$credit_card_response = !is_array($credit_card_response) ? array() : $credit_card_response;

		$order = array();
		$q = "SELECT `id`, `idorderota`, `channel`, `paymentlog` FROM `#__vikbooking_orders` WHERE `idorderota`=".$dbo->quote($ota_id)." LIMIT 1;";
		$dbo->setQuery($q);
		$dbo->execute();
		if ($dbo->getNumRows() > 0) {
			$order = $dbo->loadAssoc();
		}

		if (!count($order)) {
			VikError::raiseWarning('', 'Reservation not found');
		}

		$this->config = $config;
		$this->creditCardResponse = $credit_card_response;
		$this->order = $order;
		
		// Display the template (default.php)
		parent::display($tpl);
		
	}

}
