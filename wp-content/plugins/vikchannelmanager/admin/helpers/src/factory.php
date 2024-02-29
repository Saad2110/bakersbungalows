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
 * Factory application class.
 *
 * @since 	1.8.11
 */
final class VCMFactory
{
	/**
	 * Application platform handler.
	 *
	 * @var VCMPlatformInterface
	 */
	private static $platform;

	/**
	 * Class constructor.
	 * @private This object cannot be instantiated. 
	 */
	private function __construct()
	{
		// never called
	}

	/**
	 * Class cloner.
	 * @private This object cannot be cloned.
	 */
	private function __clone()
	{
		// never called
	}

	/**
	 * Returns the current platform handler.
	 *
	 * @return 	VCMPlatformInterface
	 */
	public static function getPlatform()
	{
		// check if platform class is already instantiated
		if (is_null(static::$platform))
		{
			if (defined('ABSPATH') && function_exists('wp_die'))
			{
				// running WordPress platform
				static::$platform = new VCMPlatformOrgWordpress();
			}
			else
			{
				// running Joomla platform
				static::$platform = new VCMPlatformOrgJoomla();
			}
		}

		return static::$platform;
	}
}
