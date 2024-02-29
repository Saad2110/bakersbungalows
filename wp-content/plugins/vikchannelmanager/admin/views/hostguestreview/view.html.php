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

class VikChannelManagerViewHostguestreview extends JViewUI {
	
	function display($tpl = null) {
		// Set the toolbar
		$this->addToolBar();
		
		VCM::load_css_js();
		
		$dbo = JFactory::getDbo();
		$app = JFactory::getApplication();
		$ids = VikRequest::getVar('cid', array(0));

		if (empty($ids) || empty($ids[0])) {
			VikError::raiseWarning('', 'Missing booking');
			$app->redirect("index.php?option=com_vikchannelmanager");
			exit;
		}
		
		$q = "SELECT * FROM `#__vikbooking_orders` WHERE `id`=" . (int)$ids[0] . ";";
		$dbo->setQuery($q);
		$dbo->execute();
		if (!$dbo->getNumRows()) {
			VikError::raiseWarning('', 'Booking ID not found');
			$app->redirect("index.php?option=com_vikchannelmanager");
			exit;
		}
		$reservation = $dbo->loadAssoc();

		// check if the operation was successful
		$success = VikRequest::getInt('success', 0, 'request');
		
		if (!$success && !VikChannelManager::hostToGuestReviewSupported($reservation)) {
			VikError::raiseWarning('', 'Booking does not support host to guest review at this time, or a review was left already.');
			$app->redirect("index.php?option=com_vikbooking&task=editorder&cid[]=" . $reservation['id']);
			exit;
		}

		/**
		 * For the moment only Airbnb API supports host-to-guest reviews.
		 * If any other channel in the future will support this feature, the channel details
		 * will need to be loaded by using the channel name contained in $reservation['channel'].
		 * 
		 * @since 	1.8.0
		 */
		$channel = VikChannelManager::getChannel(VikChannelManagerConfig::AIRBNBAPI);
		if (!is_array($channel) || !count($channel)) {
			VikError::raiseWarning('', 'No valid channels available to write a review for the guest');
			$app->redirect("index.php?option=com_vikbooking&task=editorder&cid[]=" . $reservation['id']);
			exit;
		}
		
		$this->reservation = $reservation;
		$this->channel = $channel;

		// Display the template (default.php)
		parent::display($tpl);
	}

	/**
	 * Setting the toolbar
	 */
	protected function addToolBar() {
		//Add menu title and some buttons to the page
		JToolBarHelper::title(JText::_('VCMMAINTHOSTGUESTREVIEW'), 'vikchannelmanager');
		JToolBarHelper::apply('doHostGuestReview', JText::_('SAVE'));
		JToolBarHelper::spacer();
		JToolBarHelper::cancel('cancelHostGuestReview', JText::_('CANCEL'));
		JToolBarHelper::spacer();
	}
}
