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

class VikChannelManagerViewdashboard extends JViewUI {
	
	function display($tpl = null) {
		// Set the toolbar
		$this->addToolBar();
		
		$this->backwardCompatibility();

		VCM::load_css_js();

		/**
		 * we load the VBO main library for accessing the Application class
		 */
		if (!class_exists('VikBooking') && file_exists(VBO_SITE_PATH.DIRECTORY_SEPARATOR.'helpers'.DIRECTORY_SEPARATOR.'lib.vikbooking.php')) {
			require_once (VBO_SITE_PATH.DIRECTORY_SEPARATOR.'helpers'.DIRECTORY_SEPARATOR.'lib.vikbooking.php');
		}
		
		$dbo = JFactory::getDbo();
		$mainframe = JFactory::getApplication();

		$fromwizard = VikRequest::getInt('fromwizard', 0, 'request');
		if ($fromwizard > 0) {
			$mainframe->redirect('index.php?option=com_vikchannelmanager&task=config&fromwizard=1');
			exit;
		}
		
		$rmnotifications = VikRequest::getInt('rmnotifications', 0, 'request');
		$notsids = VikRequest::getVar('notsids', array());
		$notif_filter = VikRequest::getString('notif_filter', '', 'request');
		$filter_hash = VikRequest::getString('filter_hash', '', 'request');
		
		$lim = $mainframe->getUserStateFromRequest("com_vikchannelmanager.limit", 'limit', $mainframe->get('list_limit'), 'int');
		$lim0 = VikRequest::getVar('limitstart', 0, '', 'int');
		
		if ($rmnotifications > 0 && count($notsids) > 0) {
			$lim = 15;
			$lim0 = 0;
			foreach ($notsids as $notid) {
				if (!empty($notid)) {
					$q = "DELETE FROM `#__vikchannelmanager_notifications` WHERE `id`='".$notid."';";
					$dbo->setQuery($q);
					$dbo->execute();
				}
			}
		}

		/**
		 * Check if the pagination limits should be reset
		 * 
		 * @since 	1.6.18
		 */
		$now_hash = !empty($notif_filter) ? md5($notif_filter) : '';
		if ($now_hash != $filter_hash) {
			$lim0 = 0;
		}
		//
		
		$notifications = array();
		$notif_clause = '';
		if (!empty($notif_filter)) {
			if (is_numeric($notif_filter)) {
				$notif_clause = '`n`.`idordervb`='.intval($notif_filter);
			} else {
				$notif_clause = "`n`.`cont` LIKE ".$dbo->quote('%'.$notif_filter.'%');
			}
		}
		
		$q = "SELECT SQL_CALC_FOUND_ROWS `n`.* 
		FROM `#__vikchannelmanager_notifications` AS `n`".(!empty($notif_clause) ? " WHERE ".$notif_clause : "")." ORDER BY `n`.`ts` DESC";
		$dbo->setQuery($q, $lim0, $lim);
		$dbo->execute();
		if ($dbo->getNumRows() > 0) {
			$notifications = $dbo->loadAssocList();
			$dbo->setQuery('SELECT FOUND_ROWS();');
			jimport('joomla.html.pagination');
			$pageNav = new JPagination( $dbo->loadResult(), $lim0, $lim );
			$navbut="<table align=\"center\"><tr><td>".$pageNav->getListFooter()."</td></tr></table>";
			$nparent_ids = array();
			foreach ($notifications as $nf) {
				$nparent_ids[] = $nf['id'];
			}
			$q = "SELECT * FROM `#__vikchannelmanager_notification_child` WHERE `id_parent` IN (".implode(',', $nparent_ids).");";
			$dbo->setQuery($q);
			$dbo->execute();
			if ($dbo->getNumRows() > 0) {
				$children = $dbo->loadAssocList();
				foreach ($notifications as $nk => $nf) {
					$notifications[$nk]['children'] = array();
					foreach ($children as $child) {
						if ($nf['id'] == $child['id_parent']) {
							$notifications[$nk]['children'][] = $child;
						}
					}
				}
			}
		}
		
		$active_channels = array();
		$q = "SELECT `uniquekey` FROM `#__vikchannelmanager_channel`;";
		$dbo->setQuery($q);
		$dbo->execute();
		if ($dbo->getNumRows() > 0) {
			$active_channels = $dbo->loadAssocList();
			for ($i = 0; $i < count($active_channels); $i++) {
				$active_channels[$i] = $active_channels[$i]['uniquekey'];
			}
		}
		
		$config = VikChannelManager::loadConfiguration();

		$q = "SELECT `id` FROM `#__vikchannelmanager_channel` WHERE `av_enabled`=1 LIMIT 1;";
		$dbo->setQuery($q);
		$dbo->execute();
		$show_sync = ($dbo->getNumRows() > 0);

		$tot_smbal_rules = 0;
		$q = "SELECT COUNT(*) FROM `#__vikchannelmanager_balancer_rules` WHERE `to_ts`>=".time().";";
		$dbo->setQuery($q);
		$dbo->execute();
		if ($dbo->getNumRows() > 0) {
			$tot_smbal_rules = (int)$dbo->loadResult();
		}

		// load email address in hotel details for e4jConnect notifications of reservations
		$hotel_email = '';
		$q = "SELECT `value` FROM `#__vikchannelmanager_hotel_details` WHERE `key`='email' LIMIT 1;";
		$dbo->setQuery($q);
		$dbo->execute();
		if ($dbo->getNumRows() > 0) {
			$hotel_email = $dbo->loadResult();
		}
		//
		
		$this->config = $config;
		$this->notifications = $notifications;
		$this->activeChannels = $active_channels;
		$this->lim0 = $lim0;
		$this->navbut = $navbut;
		$this->showSync = $show_sync;
		$this->tot_smbal_rules = $tot_smbal_rules;
		$this->hotel_email = $hotel_email;
		
		// Display the template (default.php)
		parent::display($tpl);
	}

	/**
	 * Setting the toolbar
	 */
	protected function addToolBar() {
		//Add menu title and some buttons to the page
		JToolBarHelper::title(JText::_('VCMMAINTDASHBOARD'), 'vikchannelmanager');
		
		if (JFactory::getUser()->authorise('core.admin', 'com_vikchannelmanager')) {
			JToolBarHelper::preferences('com_vikchannelmanager');
		}
		
	}

	/**
	 * Backward compatibility utils.
	 *
	 * - Update Joomla Manifest Cache for VCM versions lower than 1.6.5
	 * - Transmit OpenSSL Public Key for VCM versions lower than 1.6.8
	 *
	 * @return void.
	 */
	protected function backwardCompatibility()
	{
		$dbo = JFactory::getDbo();

		/**
		 * Update Joomla manifest.
		 *
		 * @since 1.6.5
		 */

		$q = "SELECT `setting` FROM `#__vikchannelmanager_config` WHERE `param`='atleast165';";
		$dbo->setQuery($q);
		$dbo->execute();

		if (!$dbo->getNumRows())
		{
			// Update Joomla Manifest cache (VCM 1.6.5)
			VikChannelManager::updateManifestCacheVersion();
			
			$q = "INSERT INTO `#__vikchannelmanager_config` (`param`,`setting`) VALUES ('atleast165', '1');";
			$dbo->setQuery($q);
			$dbo->execute();
		}

		/**
		 * Transmit OpenSSL public key.
		 *
		 * @since 1.6.8
		 */
		$mediator = VikChannelManager::loadCypherFramework();
		// get CipherOpenSSL instance from the mediator
		$openssl  = $mediator->getInstanceOf('CipherOpenSSL');

		/**
		 * It may be necessary to re-generate the OpenSSL keys for VCM and e4jConnect.
		 * This operation can be forced by passing a value via query string on this page.
		 * 
		 * @since 	1.7.4
		 */
		if (VikRequest::getInt('regenerate_keys', 0) && $openssl)
		{
			// Drop the pair of keys so that they can be regenerated
			$openssl->drop();

			// reload the page so that the new execution will have a new instance of the OpenSSL cypher for the re-transmission
			JFactory::getApplication()->redirect('index.php?option=com_vikchannelmanager');
			exit;
		}

		// check if the openssl instance has just generated a new pair of keys
		if ($openssl && $openssl->hasNewKeys())
		{
			// transmit the Public Key to e4jConnect server
			$res = VikChannelManager::transmitPublicKey($openssl->getPublicKey(), $openssl->getErrors());

			if (!$res)
			{
				// Something went wrong while transmitting the key...
				// Drop the pair of keys so that they could be regenerated
				// and sent again.
				$openssl->drop();
			}
		}

		/**
		 * Folder controllers must be generated in site when
		 * upgrading from an older version than 1.6.13.
		 * 
		 * @since 	1.6.13
		 */
		if (!is_dir(VCM_SITE_PATH . DIRECTORY_SEPARATOR . 'controllers') && 
			is_dir(VCM_ADMIN_PATH . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'bc' . DIRECTORY_SEPARATOR . 'site' . DIRECTORY_SEPARATOR . 'controllers'))
		{
			// always attempt to include the Folder class
			jimport('joomla.filesystem.folder');

			$res = JFolder::copy(VCM_ADMIN_PATH . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'bc' . DIRECTORY_SEPARATOR . 'site' . DIRECTORY_SEPARATOR . 'controllers', VCM_SITE_PATH . DIRECTORY_SEPARATOR . 'controllers', '', true);
			if ($res)
			{
				JFolder::delete(VCM_ADMIN_PATH . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'bc' . DIRECTORY_SEPARATOR . 'site' . DIRECTORY_SEPARATOR . 'controllers');
			}
		}

		/**
		 * Folder controllers must be generated in admin when
		 * upgrading from an older version than 1.6.13, and if not available.
		 * The directory "implementors" was added in the version 1.6.13 so for
		 * future releases we don't need to check this.
		 * 
		 * @since 	1.6.13
		 */
		if ((!is_dir(VCM_ADMIN_PATH . DIRECTORY_SEPARATOR . 'controllers') || !is_dir(VCM_ADMIN_PATH . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'implementors')) && 
			is_dir(VCM_ADMIN_PATH . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'bc' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'controllers'))
		{
			// always attempt to include the Folder class
			jimport('joomla.filesystem.folder');

			$res = JFolder::copy(VCM_ADMIN_PATH . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'bc' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'controllers', VCM_ADMIN_PATH . DIRECTORY_SEPARATOR . 'controllers', '', true);
			if ($res)
			{
				JFolder::delete(VCM_ADMIN_PATH . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'bc' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'controllers');
			}
		}

		/**
		 * Check for updates regularly
		 *
		 * @since 	1.6.8
		 */
		if (VikChannelManager::checkUpdates())
		{
			$mainframe = JFactory::getApplication();
			$mainframe->redirect('index.php?option=com_vikchannelmanager&task=update_program&forcecheck=1');
			exit;
		}
	}
}
