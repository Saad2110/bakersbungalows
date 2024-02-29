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

/**
 * This class is mainly used by Vik Booking to check if a
 * specific channel has a logo defined to be displayed.
 */
class VikChannelManagerLogos
{
	/**
	 * The (raw) 'channel' string stored 
	 * for the booking ID in Vik Booking.
	 *
	 * @var string
	 */
	public 	$provenience;

	/**
	 * The URL to the base path where the logos
	 * (images) of the channels are locate.
	 *
	 * @var string
	 */
	private $baseurl;

	/**
	 * Full channel name before exploding the underscore.
	 * Useful for displaying the custom iCal channel name.
	 *
	 * @var 		string
	 * 
	 * @since 		1.7.0
	 * @requires 	VBO 1.3.0
	 */
	private $raw_ota_name;

	/**
	 * An array to map the channels keys (names)
	 * to their corresponding value (logo).
	 *
	 * @var array
	 */
	private $chmap = array(
		'agoda' => 'channel_agoda.png',
		'ycs' => 'channel_agoda.png',
		'ycs50' => 'channel_agoda.png',
		'airbnb' => 'channel_airbnb.png',
		'airbnbapi' => 'channel_airbnb.png',
		'googlehotel' => 'channel_googlehotel.png',
		'bed-and-breakfast' => 'channel_bed-and-breakfast.png',
		'booking' => 'channel_booking.png',
		'despegar' => 'channel_despegar.png',
		'ebookers' => 'channel_ebookers.png',
		'egencia' => 'channel_egencia.png',
		'expedia' => 'channel_expedia.png',
		'flipkey' => 'channel_flipkey.png',
		'holidaylettings' => 'channel_holidaylettings.png',
		'homeaway' => 'channel_homeaway.png',
		'hotels' => 'channel_hotels.png',
		'lastminute' => 'channel_lastminute.png',
		'orbitz' => 'channel_orbitz.png',
		'otelz' => 'channel_otelz.png',
		'tripadvisor' => 'channel_tripadvisor.png',
		'tripconnect' => 'channel_tripadvisor.png',
		'trivago' => 'channel_trivago.png',
		'venere' => 'channel_venere.png',
		'vrbo' => 'channel_vrbo.png',
		'wimdu' => 'channel_wimdu.png',
		'bedandbreakfast' => 'channel_bedandbreakfasteu.png',
		'feratel' => 'channel_feratel.png',
		'pitchup' => 'channel_pitchup.png',
		'campsitescouk' => 'channel_campsitescouk.png',
		'hostelworld' => 'channel_hostelworld.png',
		'ical' => 'channel_ical.png',
	);
	
	function __construct($provenience)
	{
		$this->provenience = $provenience;
		$this->setBaseUri();
	}

	/**
	 * Method to set the 'from channel' (raw) name
	 * used to check whether a logo exists.
	 *
	 * @param 	string  $val 			channel main source ([0]).
	 * @param 	string 	$raw_ota_name 	full channel source before exploding the "_".
	 *
	 * @return 	self
	 * 
	 * @see 	param $raw_ota_name was introduced in the version 1.7.0 and VBO 1.3.0.
	 */
	public function setProvenience($val, $raw_ota_name = '')
	{
		$this->provenience = $val;
		$this->raw_ota_name = $raw_ota_name;

		return $this;
	}

	/**
	 * Main public method that should be called
	 * to retrieve just the name of the logo.
	 *
	 * @return 	boolean
	 */
	public function findLogo()
	{
		return $this->findLogoFromProvenience();
	}

	/**
	 * Main public method that should be called
	 * to retrieve the full logo URL.
	 *
	 * @return 	mixed 	false on failure, string otherwise.
	 */
	public function getLogoURL()
	{
		// always restore the base URI as it could be unset
		$this->setBaseUri();

		if ($fname = $this->findLogoFromProvenience()) {
			return $this->baseurl.$fname;
		}

		return false;
	}

	/**
	 * Method needed to fetch the small version of the logo.
	 *
	 * @return 	mixed 	false on failure, string otherwise.
	 */
	public function getSmallLogoURL()
	{
		// clean provenience
		if (!$this->findLogoFromProvenience()) {
			return false;
		}

		// small logos are located in a different path
		return VCM_ADMIN_URI . 'assets/css/images/' . $this->provenience . '-logo.png';
	}

	/**
	 * Attempts to retrieve the tiny version of the channel logo URL.
	 * 
	 * @return 	mixed 	false on failure, URL string to logo otherwise.
	 * 
	 * @since 	1.8.11
	 */
	public function getTinyLogoURL()
	{
		$logo_name = $this->findLogoFromProvenience();

		if (!$logo_name) {
			return false;
		}

		// big logo was found, try to see if the tiny version exists (channel logo name with no dashes)
		$tiny_fname = str_replace(array('channel_', '-'), '', $logo_name);

		if (is_file(VCM_ADMIN_PATH . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'channels' . DIRECTORY_SEPARATOR . $tiny_fname)) {
			return $this->baseurl . $tiny_fname;
		}

		return false;
	}

	/**
	 * Private method to clean up the raw
	 * channel name to make it match with
	 * the array map key.
	 *
	 * @return 	boolean
	 */
	private function cleanProvenience()
	{
		if (empty($this->provenience)) {
			return false;
		}

		//separate string from dot(s)
		if (strpos($this->provenience, '.') !== false) {
			$parts = explode('.', $this->provenience);
			/**
			 * If more than one dot replace it, otherwise take the first part of the string.
			 * 
			 * @since 	1.6.18
			 */
			$this->provenience = count($parts) > 2 ? str_replace('.', '', $this->provenience) : $parts[0];
		}

		$this->provenience = strtolower($this->provenience);

		//remove 'a-' for Affiliate Network
		$this->provenience = str_replace('a-', '', $this->provenience);

		return true;
	}

	/**
	 * Private method to check if the cleaned
	 * logo name matches with a key in the map.
	 *
	 * @return 	mixed 	boolean false in case of error, string in case of success
	 */
	private function findLogoFromProvenience()
	{
		if ($this->cleanProvenience() && isset($this->chmap[$this->provenience])) {
			// main channel found
			
			if ($this->provenience == 'ical' && !empty($this->raw_ota_name)) {
				// try to find the custom iCal channel logo
				$custom_ical_logo_uri = $this->findCustomIcalChLogoUri();
				if ($custom_ical_logo_uri !== false) {
					// make the base URI empty since we've got a full URI
					$this->baseurl = '';
					// return full URI to custom iCal channel logo
					return $custom_ical_logo_uri;
				}
			}

			// default channel pre-installed
			return $this->chmap[$this->provenience];
		}

		if (stripos($this->raw_ota_name, 'ical_') !== false) {
			// try to find the custom iCal channel logo
			$custom_ical_logo_uri = $this->findCustomIcalChLogoUri();
			if ($custom_ical_logo_uri !== false) {
				// make the base URI empty since we've got a full URI
				$this->baseurl = '';
				// return full URI to custom iCal channel logo
				return $custom_ical_logo_uri;
			}
		}

		return false;
	}

	/**
	 * Finds the logo URI from the given full channel name
	 * when the main channel is the iCal.
	 * 
	 * @return 		mixed 		false on failure, string otherwise.
	 * 
	 * @since 		1.7.0
	 * @requires 	VBO 1.3.0
	 */
	private function findCustomIcalChLogoUri()
	{
		// get the custom channel name
		$parts = explode('_', $this->raw_ota_name);
		if (count($parts) < 2) {
			// no sub-channel found
			return false;
		}
		// get rid of the main channel name (ical)
		unset($parts[0]);
		// custom channel name
		$custom_ical_name = implode('_', $parts);

		// query the db
		$dbo = JFactory::getDbo();
		$record = array();
		$q = "SELECT * FROM `#__vikchannelmanager_ical_channels` WHERE `name` LIKE " . $dbo->quote('%' . $custom_ical_name . '%') . ";";
		try {
			$dbo->setQuery($q);
			$dbo->execute();
			if ($dbo->getNumRows()) {
				$record = $dbo->loadAssoc();
			}
		} catch (Exception $e) {
			$record = array();
		}

		if (!count($record)) {
			// record not found
			return false;
		}

		return !empty($record['logo']) ? JUri::root() . ltrim($record['logo'], "/") : false;
	}

	/**
	 * Defines/resets the base URI for the logos.
	 * 
	 * @since 	1.7.0
	 */
	private function setBaseUri()
	{
		$this->baseurl = VCM_ADMIN_URI.'assets/css/channels/';
	}

	/**
	 * Gets a list of OTA small logos where the room is mapped.
	 * Useful to display the information of a specific room's mapping.
	 * 
	 * @param 	int 	$idroomvb 	the ID of the room in Vik Booking.
	 * 
	 * @return 	array 	list of OTAs where the room has been mapped.
	 * 
	 * @since 	1.7.1
	 */
	public function getVboRoomLogosMapped($idroomvb)
	{
		$dbo = JFactory::getDbo();
		$otalogos = array();
		$channelnames = array();

		// fetch first the logos of the API channels (if any)
		$q = "SELECT `r`.`channel` FROM `#__vikchannelmanager_roomsxref` AS `r` WHERE `r`.`idroomvb`=" . (int)$idroomvb . " GROUP BY `r`.`channel` ORDER BY `r`.`channel` ASC;";
		$dbo->setQuery($q);
		$dbo->execute();
		if ($dbo->getNumRows()) {
			$mapping = $dbo->loadAssocList();
			foreach ($mapping as $i) {
				// push the found API channel name to the list
				array_push($channelnames, $i['channel']);
			}
		}

		// fetch the logos of the iCal channels (if any)
		$ical_ch_ids = array();
		$q = "SELECT `l`.`channel` FROM `#__vikchannelmanager_listings` AS `l` WHERE `l`.`id_vb_room`=" . (int)$idroomvb . " GROUP BY `l`.`channel`;";
		$dbo->setQuery($q);
		$dbo->execute();
		if ($dbo->getNumRows()) {
			$data = $dbo->loadAssocList();
			foreach ($data as $i) {
				$ch_parts = explode('-', $i['channel']);
				if (in_array($ch_parts[0], $ical_ch_ids)) {
					continue;
				}
				array_push($ical_ch_ids, $ch_parts[0]);
			}
		}
		if (count($ical_ch_ids)) {
			$q = "SELECT `c`.`name` FROM `#__vikchannelmanager_channel` AS `c` WHERE `c`.`uniquekey` IN (" . implode(', ', $ical_ch_ids) . ") GROUP BY `c`.`name` ORDER BY `c`.`name` ASC;";
			$dbo->setQuery($q);
			$dbo->execute();
			if ($dbo->getNumRows()) {
				$data = $dbo->loadAssocList();
				foreach ($data as $d) {
					// push the found iCal channel name to the list
					array_push($channelnames, $d['name']);
				}
			}
		}

		// gather all logos found
		foreach ($channelnames as $chname) {
			// default fallback channel full name and logo (first letter)
			$channel_name = ucfirst($chname);
			$channel_logo = strtoupper(substr($chname, 0, 1));

			// try to find the channel logo
			$this->setProvenience($chname);
			if ($this->cleanProvenience() && isset($this->chmap[$this->provenience])) {
				// big logo was found, try to see if the tiny version exists (channel logo name with no dashes)
				$tiny_fname = str_replace(array('channel_', '-'), '', $this->chmap[$this->provenience]);
				if (is_file(VCM_ADMIN_PATH . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'channels' . DIRECTORY_SEPARATOR . $tiny_fname)) {
					$channel_logo = $this->baseurl . $tiny_fname;
				} else {
					// we use the big logo version
					$channel_logo = $this->baseurl . $this->chmap[$this->provenience];
				}
			}

			// push the channel information
			$otalogos[$channel_name] = $channel_logo;
		}

		return $otalogos;
	}
}
