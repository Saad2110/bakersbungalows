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
 * Implements the page router interface for the Joomla platform.
 * 
 * @since 	1.8.11
 */
class VCMPlatformOrgJoomlaPagerouter implements VCMPlatformPagerouterInterface
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
		$bestitemid = 0;

		$current_lang = !empty($lang) ? $lang : JFactory::getLanguage()->getTag();

		$app = JFactory::getApplication();

		$menu = $app->getMenu('site');

		if (!$menu)
		{
			return 0;
		}

		$menu_items = $menu->getMenu();

		if (!$menu_items)
		{
			return 0;
		}

		foreach ($menu_items as $itemid => $item)
		{
			if (isset($item->query['option']) && $item->query['option'] == 'com_vikbooking' && in_array($item->query['view'], $views))
			{
				// proper menu item type found
				$bestitemid = empty($bestitemid) ? $itemid : $bestitemid;

				if (isset($item->language) && $item->language == $current_lang)
				{
					// we found the exact menu item type for the given language
					return $itemid;
				}
			}
		}

		return $bestitemid;
	}
}
