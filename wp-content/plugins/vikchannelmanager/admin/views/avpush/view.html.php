<?php
/**
 * @package     VikChannelManager
 * @subpackage  com_vikchannelmanager
 * @author      e4j - Extensionsforjoomla.com
 * @copyright   Copyright (C) 2018 e4j - Extensionsforjoomla.com. All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @link        https://e4jconnect.com - https://vikwp.com
 */

defined('ABSPATH') or die('No script kiddies please!');

jimport('joomla.application.component.view');

class VikChannelManagerViewAvpush extends JViewUI {
	function display($tpl = null) {
		require_once(VBO_SITE_PATH . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "lib.vikbooking.php");
		
		$lang = JFactory::getLanguage();
		$lang->load('com_vikbooking', VIKBOOKING_ADMIN_LANG, $lang->getTag(), true);
		
		$this->addToolBar();
		
		VCM::load_css_js();
		VCM::loadDatePicker();

		$dbo = JFactory::getDbo();
		$session = JFactory::getSession();
		
		$mainframe = JFactory::getApplication();
		$lim = $mainframe->getUserStateFromRequest("com_vikchannelmanager.limit", 'limit', $mainframe->get('list_limit'), 'int');
		$lim0 = VikRequest::getVar('limitstart', 0, '', 'int');
		$navbut = '';
		$channels_mapped = false;

		/**
		 * If for some reasons, VBO contains several rooms per page, but the first rooms
		 * are not mapped to any channel but other rooms are mapped, then it is not possible
		 * to proceed. In this case we need to grab all rooms mapped in VCM.
		 * We now grab all room IDs mapped to at least one channel and then we apply the pagination
		 * only over those specific room IDs.
		 * 
		 * @since 	1.7.5
		 */
		$mapped_rids = array();
		$q = "SELECT `idroomvb` FROM `#__vikchannelmanager_roomsxref` GROUP BY `idroomvb`;";
		$dbo->setQuery($q);
		$dbo->execute();
		if (!$dbo->getNumRows()) {
			// useless to proceed
			VikError::raiseWarning('', JText::_('VCMNOROOMSASSOCFOUND'));
			$mainframe->redirect("index.php?option=com_vikchannelmanager");
			exit;
		}
		$mapped_rooms = $dbo->loadAssocList();
		foreach ($mapped_rooms as $mp) {
			array_push($mapped_rids, $mp['idroomvb']);
		}
		//

		$q = "SELECT SQL_CALC_FOUND_ROWS * FROM `#__vikbooking_rooms` WHERE `id` IN (" . implode(', ', $mapped_rids) . ") ORDER BY `#__vikbooking_rooms`.`name` ASC";
		$dbo->setQuery($q, $lim0, $lim);
		$dbo->execute();
		if ($dbo->getNumRows() > 0) {
			$rows = $dbo->loadAssocList();
			$dbo->setQuery('SELECT FOUND_ROWS();');
			jimport('joomla.html.pagination');
			$pageNav = new JPagination( $dbo->loadResult(), $lim0, $lim );
			$navbut = "<table align=\"center\"><tr><td>".$pageNav->getListFooter()."</td></tr></table>";
			foreach ($rows as $k => $r) {
				$rows[$k]['channels'] = array();
				//the old query was modified to be compliant with the strict mode (ONLY_FULL_GROUP_BY)
				//$q = "SELECT `id`,`idroomvb`,`idroomota`,`idchannel`,`channel`,`otaroomname`,`otapricing`,`prop_name`,`prop_params` FROM `#__vikchannelmanager_roomsxref` WHERE `idroomvb`=".(int)$r['id']." GROUP BY `idchannel`;";
				$q = "SELECT MIN(`id`) as `id`, MIN(`idroomvb`) AS `idroomvb`, MIN(`idroomota`) AS `idroomota`, `idchannel`, `channel`, MIN(`otaroomname`) AS `otaroomname`, MIN(`otapricing`) AS `otapricing`, MIN(`prop_name`) AS `prop_name`, MIN(`prop_params`) AS `prop_params` FROM `#__vikchannelmanager_roomsxref` WHERE `idroomvb`=".(int)$r['id']." GROUP BY  `idchannel`,`channel`;";
				$dbo->setQuery($q);
				$dbo->execute();
				if ($dbo->getNumRows() > 0) {
					$rows[$k]['channels'] = $dbo->loadAssocList();
					$channels_mapped = true;
				}
			}
		} else {
			$rows = array();
		}

		if ($channels_mapped !== true || !count($rows)) {
			VikError::raiseWarning('', JText::_('VCMNOROOMSASSOCFOUND'));
			$mainframe->redirect("index.php?option=com_vikchannelmanager");
			exit;
		}

		$this->rows = $rows;
		$this->lim0 = $lim0;
		$this->navbut = $navbut;
		
		parent::display($tpl);
	}
	
	/**
	 * Setting the toolbar
	 */
	protected function addToolBar() {
		//Add menu title and some buttons to the page
		JToolBarHelper::title(JText::_('VCMMAINTAVPUSH'), 'vikchannelmanager');
		
		JToolBarHelper::save('avpushsubmit', JText::_('VCMAVPUSHSUBMIT'));
		JToolBarHelper::spacer();
		JToolBarHelper::cancel( 'cancel', JText::_('BACK'));
		
	}

}
