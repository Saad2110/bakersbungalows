<?php
/**
 * @package     VikChannelManager
 * @subpackage  com_vikchannelmanager
 * @author      E4J s.r.l.
 * @copyright   Copyright (C) 2022 e4j - Extensionsforjoomla.com. All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @link        https://e4jconnect.com - https://vikwp.com
 */

// No direct access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Implements the page router interface for the WordPress platform.
 * 
 * @since 	1.8.11
 */
class VCMPlatformOrgWordpressPagerouter implements VCMPlatformPagerouterInterface
{
	/**
	 * Given a list of Views, finds the most appropriate page/menu item ID.
	 * 
	 * @param   array  	$views  The list of View names to match.
	 * @param   string 	$lang   The language to which we should give higher preference.
	 * 
	 * @return  int 			The post/menu item ID or 0.
	 */
	public function findProperPageId(array $views, $lang = null)
	{
		$model = JModel::getInstance('vikbooking', 'shortcodes', 'admin');

		$itemid = $model->best($views, $lang);

		if ($itemid)
		{
			return $itemid;
		}

		return 0;
	}
}
