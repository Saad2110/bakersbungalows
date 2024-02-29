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

class VikChannelManagerViewSpecialoffer extends JViewUI {
	
	function display($tpl = null) {
		// Set the toolbar
		$this->addToolBar();
		
		VCM::load_css_js();

		if (!class_exists('VikBooking')) {
			require_once VBO_SITE_PATH . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'lib.vikbooking.php';
		}
		
		$dbo = JFactory::getDbo();
		$app = JFactory::getApplication();
		$bid = VikRequest::getInt('bid', 0, 'request');

		if (empty($bid)) {
			VikError::raiseWarning('', 'Missing booking ID for special offer');
			$app->redirect("index.php?option=com_vikchannelmanager");
			exit;
		}
		
		$q = "SELECT * FROM `#__vikbooking_orders` WHERE `id`=" . $bid . ";";
		$dbo->setQuery($q);
		$dbo->execute();
		if (!$dbo->getNumRows()) {
			VikError::raiseWarning('', 'Booking ID not found for special offer');
			$app->redirect("index.php?option=com_vikchannelmanager");
			exit;
		}
		$reservation = $dbo->loadAssoc();

		$listing_id = VikRequest::getString('listing_id', '', 'request');
		$ota_thread_id = VikRequest::getString('ota_thread_id', '', 'request');
		
		if (empty($listing_id) || empty($ota_thread_id)) {
			VikError::raiseWarning('', 'Missing channel room or thread ID');
			$app->redirect("index.php?option=com_vikbooking&task=editorder&cid[]=" . $reservation['id']);
			exit;
		}

		/**
		 * For the moment only Airbnb API supports Special Offers.
		 * If any other channel in the future will support this feature, the channel details
		 * will need to be loaded by using the channel name contained in $reservation['channel'].
		 * 
		 * @since 	1.8.0
		 */
		$channel = VikChannelManager::getChannel(VikChannelManagerConfig::AIRBNBAPI);
		if (!is_array($channel) || !count($channel)) {
			VikError::raiseWarning('', 'No valid channels available to support Special Offers');
			$app->redirect("index.php?option=com_vikbooking&task=editorder&cid[]=" . $reservation['id']);
			exit;
		}

		// load VBO room information from the given listing ID
		$q = "SELECT `x`.`idroomvb`, `x`.`idroomota`, `r`.`name`, `r`.`img` FROM `#__vikchannelmanager_roomsxref` AS `x` LEFT JOIN `#__vikbooking_rooms` AS `r` ON `x`.`idroomvb`=`r`.`id` WHERE `x`.`idroomota`=" . $dbo->quote($listing_id) . " AND `x`.`idchannel`=" . $dbo->quote($channel['uniquekey']);
		$dbo->setQuery($q, 0, 1);
		$dbo->execute();
		if (!$dbo->getNumRows()) {
			VikError::raiseWarning('', 'Channel room not found or not mapped');
			$app->redirect("index.php?option=com_vikbooking&task=editorder&cid[]=" . $reservation['id']);
			exit;
		}
		$room_info = $dbo->loadAssoc();

		// load order room details
		$q = "SELECT * FROM `#__vikbooking_ordersrooms` WHERE `idorder`=" . (int)$reservation['id'];
		$dbo->setQuery($q, 0, 1);
		$dbo->execute();
		if (!$dbo->getNumRows()) {
			VikError::raiseWarning('', 'No rooms booked');
			$app->redirect("index.php?option=com_vikbooking&task=editorder&cid[]=" . $reservation['id']);
			exit;
		}
		$booking_room = $dbo->loadAssoc();

		// load customer details
		$q = "SELECT `co`.`idcustomer`, `c`.`first_name`, `c`.`last_name` FROM `#__vikbooking_customers_orders` AS `co` LEFT JOIN `#__vikbooking_customers` AS `c` ON `co`.`idcustomer`=`c`.`id` WHERE `co`.`idorder`=" . $dbo->quote($reservation['id']);
		$dbo->setQuery($q, 0, 1);
		$dbo->execute();
		if (!$dbo->getNumRows()) {
			VikError::raiseWarning('', 'The booking has got no customers assigned');
			$app->redirect("index.php?option=com_vikbooking&task=editorder&cid[]=" . $reservation['id']);
			exit;
		}
		$customer = $dbo->loadAssoc();
		
		$this->reservation = $reservation;
		$this->channel = $channel;
		$this->listing_id = $listing_id;
		$this->ota_thread_id = $ota_thread_id;
		$this->room_info = $room_info;
		$this->booking_room = $booking_room;
		$this->customer = $customer;

		// Display the template (default.php)
		parent::display($tpl);
	}

	/**
	 * Setting the toolbar
	 */
	protected function addToolBar() {
		//Add menu title and some buttons to the page
		JToolBarHelper::title(JText::_('VCMMAINTSENDSPECIALOFFER'), 'vikchannelmanager');
		JToolBarHelper::apply('sendSpecialOffer', JText::_('SAVE'));
		JToolBarHelper::spacer();
		JToolBarHelper::cancel('cancelSpecialOffer', JText::_('CANCEL'));
		JToolBarHelper::spacer();
	}
}
