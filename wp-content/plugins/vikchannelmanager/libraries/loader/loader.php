<?php
/** 
 * @package   	VikChannelManager - Libraries
 * @subpackage 	loader
 * @author    	E4J s.r.l.
 * @copyright 	Copyright (C) 2018 E4J s.r.l. All Rights Reserved.
 * @license  	http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * @link 		https://vikwp.com
 */

// No direct access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Plugin smart loader class.
 *
 * @since  1.0
 */
abstract class VikChannelManagerLoader extends JLoader
{
	/**
	 * Base path to load resources.
	 *
	 * @var string
	 */
	public static $base = VIKCHANNELMANAGER_LIBRARIES;
}
