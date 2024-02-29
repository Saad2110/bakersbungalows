<?php
/**
 * @package     VikChannelManager
 * @subpackage  com_vikchannelmanager
 * @author      e4j - Extensionsforjoomla.com
 * @copyright   Copyright (C) 2022 e4j - Extensionsforjoomla.com. All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @link        https://e4jconnect.com - https://vikwp.com
 */

defined('ABSPATH') or die('No script kiddies please!');

/**
 * Google Hotel check status and perform operations.
 * 
 * @since 	1.8.9
 */
class VCMGhotelStatus
{
	/**
	 * @var 	array
	 */
	protected $channel = [];

	/**
	 * Proxy used to construct the object.
	 * 
	 * @param 	array  $channel  the channel record for Google Hotel.
	 * 
	 * @return 	self   			 a new instance of this class.
	 */
	public static function getInstance(array $channel = null)
	{
		if (!$channel) {
			$channel = VikChannelManager::getChannel(VikChannelManagerConfig::GOOGLEHOTEL);
		}

		return new static($channel);
	}

	/**
	 * Class constructor.
	 * 
	 * @param 	array  $channel  the channel record for Google Hotel.
	 */
	public function __construct($channel)
	{
		$this->channel = $channel;
	}

	/**
	 * Displays the current mapping information for Google Hotel.
	 * 
	 * @return 	array
	 */
	public function showMapping()
	{
		$result = [
			'number_of_accounts' => 0,
			'rooms_mapping' 	 => [],
		];

		$accounts = [];

		$dbo = JFactory::getDbo();

		$q = "SELECT * FROM `#__vikchannelmanager_roomsxref` WHERE `idchannel`=" . VikChannelManagerConfig::GOOGLEHOTEL . ";";
		$dbo->setQuery($q);
		$dbo->execute();
		if (!$dbo->getNumRows()) {
			return $result;
		}
		$result['rooms_mapping'] = $dbo->loadAssocList();

		foreach ($result['rooms_mapping'] as $room_mapped) {
			if (!in_array($room_mapped['prop_params'], $accounts)) {
				$accounts[] = $room_mapped['prop_params'];
			}
		}

		$result['number_of_accounts'] = count($accounts);

		return $result;
	}

	/**
	 * Prepares and transmits the mapping information to update any possible
	 * outdated detail about VAT/GST or room-types and rate plans relations.
	 * 
	 * @return 	array
	 */
	public function transmitPropertyData()
	{
		// check if the request param "hotel_id" is available
		$hotel_id = VikRequest::getString('hotel_id', '', 'request');
		if (!empty($hotel_id)) {
			// convert the value to a number
			$hotel_id = preg_replace("/[^0-9]+/", '', $hotel_id);

			// take care of the channel params
			if (is_string($this->channel['params'])) {
				$this->channel['params'] = json_decode($this->channel['params'], true);
				$this->channel['params'] = is_array($this->channel['params']) ? $this->channel['params'] : [];
			}

			// validate params structure
			if (!is_array($this->channel['params']) || empty($this->channel['params']['hotelid'])) {
				VCMHttpDocument::getInstance()->close(500, 'Invalid hotel ID in channel params');
			}

			// inject requested hotel ID in case of multiple accounts
			$this->channel['params']['hotelid'] = $hotel_id;
		}

		// re-transmit the mapping information for this account
		$re_transmit = VikChannelManager::transmitPropertyData($this->channel);

		if ($re_transmit === false) {
			// generic error
			VCMHttpDocument::getInstance()->close(500, 'Could not perform the request due to a generic error');
		}

		if (is_string($re_transmit)) {
			// error explanation
			VCMHttpDocument::getInstance()->close(500, $re_transmit);
		}

		if ($re_transmit === true) {
			// execution was successful
			return [
				'success' => 1,
				'channel' => $this->channel,
			];
		}

		// unexpected result
		VCMHttpDocument::getInstance()->close(500, 'Unexpected result');
	}
}
