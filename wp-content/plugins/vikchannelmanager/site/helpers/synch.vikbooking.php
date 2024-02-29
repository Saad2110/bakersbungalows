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
 * This Class is used by VikChannelManager to send A Requests to
 * e4jConnect to synchronize VikBooking with the OTA.
 */
class SynchVikBooking
{
	private $order_id;
	private $exclude_ids;
	private $modified_order;
	private $cancelled_order;
	private $skip_check_auto_sync;
	private $push_type;
	private $config;
	private $dbo;

	/**
	 * @var 	string 	the booking original status
	 * @since 	1.8.0
	 */
	private $prev_status = null;

	public function __construct($orderid, $exclude_channels = array())
	{
		if (!class_exists('VikChannelManager')) {
			require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'lib.vikchannelmanager.php');
		}
		if (!class_exists('VikChannelManagerConfig')) {
			require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'vcm_config.php');
		}

		$this->order_id = (int)$orderid;
		$this->exclude_ids = $exclude_channels;
		$this->modified_order = array();
		$this->cancelled_order = array();
		$this->skip_check_auto_sync = false;
		$this->push_type = '';
		$this->config = VikChannelManager::loadConfiguration();
		$this->dbo = JFactory::getDbo();
	}
	
	/**
	 * The visibility of this method must be public as it may be useful
	 * for who invokes the class to know if there is at least one API channel.
	 * Useful for the App to know if an error occurred only because there are
	 * no active API channels that require an update of the availability.
	 * 
	 * @since 	1.6.13
	 */
	public function isAvailabilityRequest()
	{
		$q = "SELECT `id` FROM `#__vikchannelmanager_channel` WHERE `av_enabled`=1".(count($this->exclude_ids) > 0 ? " AND `uniquekey` NOT IN (".implode(',', $this->exclude_ids).")" : "")." LIMIT 1;";
		$this->dbo->setQuery($q);
		$this->dbo->execute();
		return ($this->dbo->getNumRows() > 0);
	}
	
	/**
	 * Returns a list of channels available supporting availability updates.
	 * 
	 * @return 	array
	 */
	private function getAvChannelIds()
	{
		$ch_ids = array();
		$q = "SELECT `id`,`name`,`uniquekey` FROM `#__vikchannelmanager_channel` WHERE `av_enabled`=1".(count($this->exclude_ids) > 0 ? " AND `uniquekey` NOT IN (".implode(',', $this->exclude_ids).")" : "").";";
		$this->dbo->setQuery($q);
		$this->dbo->execute();
		if ($this->dbo->getNumRows() > 0) {
			$channels = $this->dbo->loadAssocList();
			foreach ($channels as $cha) {
				$ch_ids[] = $cha['uniquekey'];
			}
		}
		return $ch_ids;
	}

	/**
	 * This method sets the original booking array of VBO before it was Updated.
	 * If called, the system will merge dates and room types of the original booking
	 * with the dates and room types of the new and updated order.
	 *
	 * @param 	array 	$m_order 	the modified (before modification) booking array
	 *
	 * @return 	self
	 */
	public function setFromModification($m_order)
	{
		if (is_array($m_order)) {
			$this->modified_order = $m_order;
		}

		return $this;
	}

	/**
	 * This method sets the original booking array of VBO before it was Cancelled.
	 * If called, the system will fetch the dates and room types of the original booking
	 * and will notify the new availability to all channels.
	 *
	 * @param 	array 	$c_order 	the cancelled (before cancellation) booking array
	 *
	 * @return 	self
	 */
	public function setFromCancellation($c_order)
	{
		if (is_array($c_order)) {
			$this->cancelled_order = $c_order;
		}

		return $this;
	}

	/**
	 * If called before the main method, the system will execute the
	 * A_RQ to e4jConnect even if the Configuration setting Auto-Sync of VCM
	 * is disabled. This is useful in the back-end of VBO for Modifications and
	 * Cancellations to be executed no matter if the sync is disabled.
	 *
	 * @return 	self
	 */
	public function setSkipCheckAutoSync()
	{
		$this->skip_check_auto_sync = $this->skip_check_auto_sync === false ? true : false;

		return $this;
	}

	/**
	 * Registers what was the previous status of the booking. Maybe a booking that was
	 * set to confirmed from a pending state, can inject "standby" as previous status
	 * so that VCM will know that an additional action may be necessary for some channels.
	 * In fact, "Request to Book" reservations may need to accept or deny the request.
	 * 
	 * @param 	string 	$prev_status 	the booking original status before any updates.
	 * 
	 * @return 	self
	 */
	public function setBookingPreviousStatus($prev_status)
	{
		$this->prev_status = $prev_status;

		return $this;
	}

	/**
	 * This method sets the push-type of the A Request.
	 * New bookings generated via VBO front-end will set the
	 * type to 'new' to send e4jConnect additional information.
	 * When $type = 'new', e4jConnect may send push notifications
	 * to the enabled mobile devices.
	 *
	 * @param 	mixed  		$type
	 *
	 * @return 	self
	 */
	public function setPushType($type)
	{
		$set_type = '';
		if ($type == 'new') {
			$q = "SELECT `id` FROM `#__vikchannelmanager_channel` WHERE `uniquekey`=".(int)VikChannelManagerConfig::MOBILEAPP." LIMIT 1;";
			$this->dbo->setQuery($q);
			$this->dbo->execute();
			if ($this->dbo->getNumRows() > 0) {
				$q = "SELECT `setting` FROM `#__vikchannelmanager_config` WHERE `param` = 'app_settings';";
				$this->dbo->setQuery($q);
				$this->dbo->execute();
				if ($this->dbo->getNumRows() > 0) {
					$app_settings = json_decode($this->dbo->loadResult(), true);
					if (is_array($app_settings) && isset($app_settings['vbBookings']['on']) && intval($app_settings['vbBookings']['on']) > 0) {
						$set_type = $type;
					}
				}
			}
		}
		$this->push_type = $set_type;

		return $this;
	}
	
	/**
	 * Sends A(U)_RQ to E4jConnect.com.
	 * Called by VBO every time the availability is modified for certain rooms.
	 * The same method can also be called by VCM newbookings.vikbooking.php for a
	 * Cancellation of a booking or a Modification or for other channels like TripConnect.
	 * Calls the ReservationsLogger Class to log the updates of any date/room combination.
	 *
	 * @return 	boolean
	 */
	public function sendRequest()
	{
		$result = false;
		if ((intval($this->config['vikbookingsynch']) == 1 || $this->skip_check_auto_sync === true) && $this->isAvailabilityRequest()) {
			$arr_order = $this->getOrderDetails();
			$order = array_key_exists('vikbooking_order', $arr_order) ? $arr_order['vikbooking_order'] : array();
			unset($arr_order['vikbooking_order']);
			if (count($order) && count($arr_order)) {
				// VCM 1.6.8 - ReservationsLogger
				$res = VikChannelManager::getResLoggerInstance()
					->typeModification((count($this->modified_order) > 0))
					->typeCancellation((count($this->cancelled_order) > 0))
					/**
					 * if sendRequest() is called from newbookings.vikbooking.php
					 * the channel uniquekey is passed to the constructor, so we
					 * know the update command was started from an OTA reservation.
					 */
					->typeFromChannels($this->exclude_ids)
					->trackLog($order, $arr_order);
				//
				$xml = $this->composeXmlARequest($order, $arr_order);
				// return the result of the request execution or false if some errors occurred with the XML building
				$result = $xml !== false && strlen($xml) > 0 ? $this->executeARequest($xml) : false;
			}
			// no channels probably need to be updated or the booking was not found. Return true anyway.
			$result = true;
		}

		/**
		 * Trigger the execution of the method responsible for the booking conversion tracking
		 * for some meta-search channels that may require this additional request to update values.
		 * 
		 * @since 	1.8.3
		 */
		$order = isset($order) ? $order : null;
		$this->dispatchBookingConversion($order);

		// return false because the request could not be executed
		return $result;
	}

	/**
	 * This method checks if booking conversion requests should be performed.
	 * Originally introduced to perform booking conversion requests for trivago
	 * in case of booking cancellations.
	 * 
	 * @param 	mixed 	$order 	null or array record of current reservation.
	 * 
	 * @return 	bool 			true if booking conversion performed or false.
	 * 
	 * @since 	1.8.3
	 */
	protected function dispatchBookingConversion($order)
	{
		if (!count($this->cancelled_order)) {
			// an update request due to a booking cancellation is necessary
			return false;
		}

		if (!is_array($order) || !count($order)) {
			$order = $this->getOrderDetails();
			if (isset($order['vikbooking_order'])) {
				$order = $order['vikbooking_order'];
			}
		}

		if (!count($order)) {
			return false;
		}

		// check if conversion is necessary
		if (empty($order['channel']) || stripos($order['channel'], 'trivago') === false || empty($order['idorderota'])) {
			// no conversion needed
			return false;
		}

		// get the tracking reference object from history
		$trk_reference = null;
		try {
			if (!class_exists('VikBooking')) {
				require_once VBO_SITE_PATH . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'lib.vikbooking.php';
			}
			if (method_exists('VikBooking', 'getBookingHistoryInstance')) {
				$history_obj = VikBooking::getBookingHistoryInstance();
				$history_obj->setBid($order['id']);
				// history data validation callback
				$data_callback = function($data) {
					if (!is_object($data) || !isset($data->channel) || !isset($data->bconv_type)) {
						return false;
					}
					return (stripos($data->channel, 'trivago') !== false && stripos($data->bconv_type, 'Confirmation') !== false);
				};
				$prev_data = $history_obj->getEventsWithData('CM', $data_callback);
				if (is_array($prev_data) && count($prev_data)) {
					// grab the last event data (object)
					$trk_reference = $prev_data[0];
				}
			}
		} catch (Exception $e) {
			// do nothing
		}

		if (!is_object($trk_reference)) {
			// if nothing was saved, then tracking would be useless
			return false;
		}
		if (!isset($trk_reference->bconv_data)) {
			// this should never happen, but we cannot stop the conversion tracking request at this point
			$trk_reference->bconv_data = new stdClass;
		}

		// prepare the request for e4jConnect
		$e4jc_url = "https://e4jconnect.com/channelmanager/?r=bconv&c=trivago";
		$api_key  = VikChannelManager::getApiKey(true);
		if (empty($api_key)) {
			return false;
		}

		// trivago settings
		$account_id = VikChannelManager::getTrivagoAccountID();
		$partner_id = VikChannelManager::getTrivagoPartnerID();
		$curr_name  = VikChannelManager::getCurrencyName();

		$xml = '<?xml version="1.0" encoding="UTF-8"?>
<!-- VikChannelManager BCONV Request e4jConnect.com - VikBooking -->
<BookingConversionRQ xmlns="http://www.e4jconnect.com/schemas/bconvrq">
	<Notify client="' . JUri::root() . '"/>
	<Api key="' . $api_key . '"/>
	<Information>' . "\n";

		// include private information node
		$xml .= "\t\t" . '<PrivateDetails>
		<PrivateDetail type="_trackmethod" value="Cancellation" />
		<PrivateDetail type="ref" value="' . $account_id . '" />
		<PrivateDetail type="hotel" value="' . $partner_id . '" />
		<PrivateDetail type="trv_reference" value="' . (isset($trk_reference->bconv_data->trv_reference) ? $trk_reference->bconv_data->trv_reference : '') . '" />
		<PrivateDetail type="tebadv" value="' . (isset($trk_reference->bconv_data->tebadv) && (int)$trk_reference->bconv_data->tebadv > 0 ? '1' : '0') . '" />
	</PrivateDetails>' . "\n";

		// calculate the locale
		$locale = strtoupper(substr(JFactory::getLanguage()->getTag(), -2));
		if (!empty($trk_reference->bconv_data->locale)) {
			$locale = $trk_reference->bconv_data->locale;
		}

		// build public booking information
		$pubinfo = array(
			'arrival' 		  => date('Y-m-d', $order['checkin']),
			'departure' 	  => date('Y-m-d', $order['checkout']),
			'created_on' 	  => date('Y-m-d H:i:s', $order['ts']),
			'currency' 		  => $curr_name,
			'volume' 		  => number_format($order['total'], 2, '.', ''),
			'booking_id' 	  => $order['id'],
			'locale' 		  => $locale,
			'number_of_rooms' => (isset($order['roomsnum']) ? (int)$order['roomsnum'] : 1),
			'cancelled_on' 	  => date('Y-m-d H:i:s P'),
			'refund_amount'   => (isset($order['refund']) ? (float)$order['refund'] : ''),
		);

		$xml .= "\t\t" . '<PublicDetails>' . "\n";
		foreach ($pubinfo as $k => $v) {
			$xml .= "\t\t\t" . '<PublicDetail type="' . htmlentities($k) . '" value="' . htmlentities($v) . '" />' . "\n";
		}
		$xml .= "\t\t" . '</PublicDetails>' . "\n";
		//
		$xml .= "\t" . '</Information>
</BookingConversionRQ>';

		// prepare the event data object
		$ev_data = new stdClass;
		$ev_data->channel 	 = 'trivago';
		$ev_data->bconv_type = 'Cancellation';
		$ev_data->bconv_data = $trk_reference->bconv_data;
		// set event description
		$ev_descr = $ev_data->channel . ' - Booking Conversion Tracking (' . $ev_data->bconv_type . ')';

		/**
		 * Try to instantiate the history object from VBO.
		 * Logs and event data may need to be stored.
		 */
		$history_obj = null;
		try {
			if (!class_exists('VikBooking')) {
				require_once VBO_SITE_PATH . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'lib.vikbooking.php';
			}
			if (method_exists('VikBooking', 'getBookingHistoryInstance')) {
				$history_obj = VikBooking::getBookingHistoryInstance();
				$history_obj->setBid($order['id']);
			}
		} catch (Exception $e) {
			// do nothing
		}
		
		// start the request
		$e4jC = new E4jConnectRequest($e4jc_url);
		$e4jC->slaveEnabled = true;
		$e4jC->setPostFields($xml);
		$rs = $e4jC->exec();

		// check any possible communication error
		if ($e4jC->getErrorNo()) {
			$error = VikChannelManager::getErrorFromMap($e4jC->getErrorMsg());
			if ($history_obj) {
				// log the error
				$ev_data->bconv_type = 'Error';
				$history_obj->setExtraData($ev_data)->store('CM', $ev_descr . "\n" . $error);
			}
			return false;
		}
		if (substr($rs, 0, 9) == 'e4j.error' || substr($rs, 0, 11) == 'e4j.warning') {
			$error = VikChannelManager::getErrorFromMap($rs);
			if ($history_obj) {
				// log the error
				$ev_data->bconv_type = 'Error';
				$history_obj->setExtraData($ev_data)->store('CM', $ev_descr . "\n" . $error);
			}
			return false;
		}

		if ($history_obj) {
			// log the successful operation
			$history_obj->setExtraData($ev_data)->store('CM', $ev_descr);
		}
		
		return true;
	}
	
	/**
	 * Executes the A(U)_RQ sending the XML to e4jConnect
	 *
	 * @param 	string 		$xml 	the composed XML request string.
	 *
	 * @return 	boolean
	 */
	private function executeARequest($xml)
	{
		if (!function_exists('curl_init')) {
			$this->saveNotify('0', 'VCM', 'e4j.error.Curl', '');
			return false;
		}
		$e4jC = new E4jConnectRequest("https://e4jconnect.com/channelmanager/?r=a&c=channels");
		$e4jC->setPostFields($xml);
		$e4jC->slaveEnabled = true;
		$rs = $e4jC->exec();
		if ($e4jC->getErrorNo()) {
			$this->saveNotify('0', 'VCM', $e4jC->getErrorMsg(), $this->order_id);
			return false;
		}
		if (substr($rs, 0, 4) == 'e4j.') {
			//Response for single channel request
			if (substr($rs, 0, 9) == 'e4j.error') {
				if ($rs != 'e4j.error.Skip') {
					$this->saveNotify('0', 'VCM', $rs, $this->order_id);
				}
				return false;
			}
			$this->saveNotify('1', 'VCM', 'e4j.OK.Channels.AR_RQ', $this->order_id);
		} else {
			//JSON Response for multiple channels request
			$arr_rs = json_decode($rs, true);
			if (is_array($arr_rs) && @count($arr_rs) > 0) {
				$this->saveMultipleNotifications($arr_rs);
			}
		}
		
		return true;
	}
	
	/**
	 * Generates the XML string for the A(U)_RQ.
	 * If a specific "order" (push) type is available,
	 * this is passed to e4jConnect for the PND.
	 *
	 * @param 	array 	$order
	 * @param 	array 	$rooms
	 *
	 * @return  string 	the XML message for the request. False in case of errors.
	 */
	private function composeXmlARequest($order, $rooms)
	{
		$build = array();
		foreach ($rooms as $k => $room) {
			$build[$k] = $room;
			foreach ($room['adates'] as $day => $daydet) {
				$build[$k]['newavail'][$day] = $daydet['newavail'];
			}
		}

		if (count($build) > 0) {
			$nkey = $this->generateNKey($order['id']);
			$vbrooms_parsed = array();
			$order_node_attr = array(
				'id="' . $order['id'] . '"',
				'confirmnumb="' . $order['confirmnumber'] . '"',
			);
			if (!empty($this->push_type)) {
				array_push($order_node_attr, 'type="' . $this->push_type . '"');
			}
			/**
			 * We pass along some extra information because some channels may need
			 * to support Request to Book reservations that require additional updates.
			 * 
			 * @since 	1.8.0
			 */
			if (!empty($order['idorderota']) && !empty($order['channel'])) {
				if (defined('ENT_XML1')) {
					// only available from PHP 5.4 and on
					$order['idorderota'] = htmlspecialchars($order['idorderota'], ENT_XML1 | ENT_COMPAT, 'UTF-8');
					$order['channel'] = htmlspecialchars($order['channel'], ENT_XML1 | ENT_COMPAT, 'UTF-8');
				} else {
					// fallback to plain all html entities
					$order['idorderota'] = htmlentities($order['idorderota']);
					$order['channel'] = htmlentities($order['channel']);
				}
				array_push($order_node_attr, 'otaid="' . $order['idorderota'] . '"');
				array_push($order_node_attr, 'otaname="' . $order['channel'] . '"');
			}
			if (!empty($this->prev_status)) {
				array_push($order_node_attr, 'prevstatus="' . $this->prev_status . ':' . $order['status'] . '"');
			}
			//
			
			$xmlstr = '<?xml version="1.0" encoding="UTF-8"?>
<!-- A Request e4jConnect.com - VikChannelManager - VikBooking -->
<AvailUpdateRQ xmlns="http://www.e4jconnect.com/avail/arq">
	<Notify client="' . JUri::root() . '" nkey="' . $nkey . '"/>
	<Api key="' . $this->config['apikey'] . '"/>
	<AvailUpdate>
		<Order ' . implode(' ', $order_node_attr) . '>
			<DateRange from="' . date('Y-m-d', $order['checkin']) . '" to="' . date('Y-m-d', $order['checkout']) . '"/>
		</Order>'."\n";
			foreach ($build as $k => $data) {
				if (in_array($data['idroom'], $vbrooms_parsed)) {
					continue;
				}
				$vbrooms_parsed[] = $data['idroom'];
				foreach ($data['newavail'] as $day => $avail) {
					$xmlstr .= "\t\t" . '<RoomType newavail="' . $avail . '">
			<Channels>' . "\n";
					foreach ($data['channels'] as $channel) {
						$rateplanid = '0';
						if (((int)$channel['idchannel'] == (int)VikChannelManagerConfig::AGODA || (int)$channel['idchannel'] == (int)VikChannelManagerConfig::YCS50) && !empty($channel['otapricing'])) {
							$ota_pricing = json_decode($channel['otapricing'], true);
							if (count($ota_pricing) > 0 && array_key_exists('RatePlan', $ota_pricing)) {
								foreach ($ota_pricing['RatePlan'] as $rp_id => $rp_val) {
									$rateplanid = $rp_id;
									break;
								}
							}
						}
						/**
						 * We also support 'property_id' or 'user_id' for channels like
						 * Hostelworld or Airbnb API beside the classic parameter 'hotelid'.
						 * 
						 * @see 	getOrderDetails()
						 * 
						 * @since 	1.6.22 (Hostelworld) - 1.8.0 (Airbnb API)
						 */
						$xmlstr .= "\t\t\t\t" . '<Channel id="' . $channel['idchannel'] . '" roomid="' . $channel['idroomota'] . '" rateplanid="' . $rateplanid . '"' . (array_key_exists('hotelid', $channel) ? ' hotelid="' . $channel['hotelid'] . '"' : '') . '/>' . "\n";
					}
					$xmlstr .= "\t\t\t" . '</Channels>
			<Adults num="' . $data['adults'] . '"/>
			<Children num="' . $data['children'] . '"/>
			<Day date="' . $day . '"/>
		</RoomType>' . "\n";
				}
			}
			$xmlstr .= "\t" . '</AvailUpdate>
</AvailUpdateRQ>';

			return $xmlstr;
		}

		return false;
	}
	
	/**
	 * Get one availability number for the room of the OTA
	 * In case one room of the OTA is linked to more than one room
	 * of VikBooking, this method returns the highest value for the
	 * availability of the rooms in VikBooking for these dates.
	 * It also returns the number of Children and Adults in the first room assigned.
	 *
	 * @param 	array 	$room
	 *
	 * @return 	array
	 */
	private function getUniqueRoomAvailabilityAndPeople($room)
	{
		$values = array();
		$ret = array();
		foreach ($room as $k => $r) {
			foreach ($r['adates'] as $day => $daydet) {
				$values[$day][] = $daydet['newavail'];
			}
		}
		foreach ($values as $k => $v) {
			$values[$k] = max($v);
		}
		$ret['newavail'] = $values;
		$ret['adults'] = $room[key($room)]['adults'];
		$ret['children'] = $room[key($room)]['children'];

		return $ret;
	}
	
	private function getOrderDetails()
	{
		$rooms = array();
		$q = "SELECT * FROM `#__vikbooking_orders` WHERE `id`=" . (int)$this->order_id . ";";
		$this->dbo->setQuery($q);
		$this->dbo->execute();
		if ($this->dbo->getNumRows() > 0) {
			$rows = $this->dbo->loadAssocList();
			if ($rows[0]['status'] != 'confirmed' && !count($this->cancelled_order)) {
				return array();
			}
			$rooms['vikbooking_order'] = $rows[0];
			$q = "SELECT `or`.`idroom`,`or`.`adults`,`or`.`children`,`or`.`idtar`,`or`.`optionals`,`r`.`name`,`r`.`units`,`r`.`fromadult`,`r`.`toadult` FROM `#__vikbooking_ordersrooms` AS `or` LEFT JOIN `#__vikbooking_rooms` `r` ON `or`.`idroom`=`r`.`id` WHERE `or`.`idorder`='".$rows[0]['id']."' ORDER BY `or`.`id` ASC;";
			$this->dbo->setQuery($q);
			$this->dbo->execute();
			if ($this->dbo->getNumRows() > 0) {
				$orderrooms = $this->dbo->loadAssocList();
				//in case of modification, if the rooms were different in $this->modified_order['rooms_info'], the new availability should be taken also for the previous rooms
				if (count($this->modified_order) > 0) {
					$new_room_ids = array();
					foreach ($orderrooms as $orderroom) {
						$new_room_ids[] = $orderroom['idroom'];
					}
					if (count($this->modified_order['rooms_info']) > 0) {
						$or_next_index = count($orderrooms);
						foreach ($this->modified_order['rooms_info'] as $mod_orderroom) {
							if (!in_array($mod_orderroom['idroom'], $new_room_ids)) {
								$mod_orderroom['modification'] = 1;
								$orderrooms[$or_next_index] = $mod_orderroom;
								$or_next_index++;
							}
						}
					}
				}

				/**
				 * Merge the current rooms with all the ones affected by a shared calendar.
				 * 
				 * @since 	1.7.1
				 */
				$rooms_shared_cals = $this->getRoomsSharedCalsInvolved($orderrooms);
				$orderrooms = array_merge($orderrooms, $rooms_shared_cals);
				//

				//build channels relations with VB Rooms
				$av_ch_ids = $this->getAvChannelIds();
				foreach ($orderrooms as $kor => $or) {
					$orderrooms[$kor]['channels'] = array();
					$q = "SELECT * FROM `#__vikchannelmanager_roomsxref` WHERE `idroomvb`=".(int)$or['idroom'].";";
					$this->dbo->setQuery($q);
					$this->dbo->execute();
					if ($this->dbo->getNumRows() > 0) {
						$ch_rooms = $this->dbo->loadAssocList();
						foreach ($ch_rooms as $ch_room) {
							if (strlen($ch_room['idroomota']) && strlen($ch_room['idchannel']) && in_array($ch_room['idchannel'], $av_ch_ids)) {
								$ch_r_info = array('idroomota' => $ch_room['idroomota'], 'idchannel' => $ch_room['idchannel'], 'otapricing' => $ch_room['otapricing']);
								if (!empty($ch_room['prop_params'])) {
									$prop_params_info = json_decode($ch_room['prop_params'], true);
									if (!empty($prop_params_info['hotelid'])) {
										$ch_r_info['hotelid'] = $prop_params_info['hotelid'];
									} elseif (!empty($prop_params_info['property_id'])) {
										$ch_r_info['hotelid'] = $prop_params_info['property_id'];
									} elseif (!empty($prop_params_info['id'])) {
										$ch_r_info['hotelid'] = $prop_params_info['id'];
									} elseif (!empty($prop_params_info['user_id'])) {
										$ch_r_info['hotelid'] = $prop_params_info['user_id'];
									}
								}
								$orderrooms[$kor]['channels'][] = $ch_r_info;
							} elseif (strlen($ch_room['idroomota']) && strlen($ch_room['idchannel']) && in_array($ch_room['idchannel'], $this->exclude_ids)) {
								/**
								 * Smart Balancer - booking coming from a channel may need to update the availability also for this OTA without excluding it, if a rule is in place.
								 * The Smart Balancer should then consider the relations with 'excluded' => 1 if no rules must apply for these dates and unset them.
								 * 
								 * Updated @since 1.7.2
								 * The Shared Calendars feature was in conflict with the property 'excluded' => 1, because the SmartBalancer method cleanAvailabilityExcludedRooms()
								 * was excluding the rooms we added through the merge with $this->getRoomsSharedCalsInvolved(). Therefore, the 'excluded' property should not always be 1.
								 */
								$smartbal_excluded = 1;
								foreach ($rooms_shared_cals as $shared_room_cal) {
									if (isset($shared_room_cal['idroom']) && $shared_room_cal['idroom'] == $or['idroom']) {
										// never exclude this room ID through the SmartBalancer
										$smartbal_excluded = 0;
										break;
									}
								}
								//
								$ch_r_info = array(
									'excluded' => $smartbal_excluded, 
									'idroomota' => $ch_room['idroomota'], 
									'idchannel' => $ch_room['idchannel'], 
									'otapricing' => $ch_room['otapricing'],
								);
								if (!empty($ch_room['prop_params'])) {
									$prop_params_info = json_decode($ch_room['prop_params'], true);
									if (!empty($prop_params_info['hotelid'])) {
										$ch_r_info['hotelid'] = $prop_params_info['hotelid'];
									} elseif (!empty($prop_params_info['property_id'])) {
										$ch_r_info['hotelid'] = $prop_params_info['property_id'];
									} elseif (!empty($prop_params_info['id'])) {
										$ch_r_info['hotelid'] = $prop_params_info['id'];
									} elseif (!empty($prop_params_info['user_id'])) {
										$ch_r_info['hotelid'] = $prop_params_info['user_id'];
									}
								}
								$orderrooms[$kor]['channels'][] = $ch_r_info;
								//
							}
						}
					}
					if (!(count($orderrooms[$kor]['channels']) > 0)) {
						//room is not on any channel
						unset($orderrooms[$kor]);
					}
				}
				if (!(count($orderrooms) > 0)) {
					return array();
				}
				//
				$earliest_checkin = $rows[0]['checkin'];
				$prev_groupdays = array();
				//in case of modification, if the check-in/out dates were different in $this->modified_order, the new availability should be calculated also for the previous dates
				if (count($this->modified_order) > 0) {
					if ($this->modified_order['checkin'] != $rows[0]['checkin'] || $this->modified_order['checkout'] != $rows[0]['checkout']) {
						$prev_groupdays = $this->getGroupDays($this->modified_order['checkin'], $this->modified_order['checkout'], $this->modified_order['days']);
						if ($this->modified_order['checkin'] < $earliest_checkin) {
							$earliest_checkin = $this->modified_order['checkin'];
						}
					}
				}
				$groupdays = $this->getGroupDays($rows[0]['checkin'], $rows[0]['checkout'], $rows[0]['days']);
				if (count($prev_groupdays)) {
					$groupdays = array_merge($groupdays, $prev_groupdays);
					$groupdays = array_unique($groupdays);
				}
				$morehst = $this->getHoursRoomAvail() * 3600;
				foreach ($orderrooms as $kor => $or) {
					if (count($or['channels']) > 0) {
						$rooms[$kor] = $or;
						$check = "SELECT `id`,`checkin`,`checkout` FROM `#__vikbooking_busy` WHERE `idroom`='" . $or['idroom'] . "' AND `checkout` > ".$earliest_checkin.";";
						$this->dbo->setQuery($check);
						$this->dbo->execute();
						if ($this->dbo->getNumRows() > 0) {
							$busy = $this->dbo->loadAssocList();
							foreach ($groupdays as $gday) {
								$oday = date('Y-m-d', $gday);
								$gday_info = getdate($gday);
								$midn_gday = mktime(0, 0, 0, $gday_info['mon'], $gday_info['mday'], $gday_info['year']);
								$bfound = 0;
								foreach ($busy as $bu) {
									//old method before VCM 1.4.0
									/*
									if ($gday >= $bu['checkin'] && $gday <= ($morehst + $bu['checkout'])) {
										$bfound++;
									}
									*/
									$checkin_info = getdate($bu['checkin']);
									$checkout_info = getdate($bu['checkout']);
									$midn_checkin = mktime(0, 0, 0, $checkin_info['mon'], $checkin_info['mday'], $checkin_info['year']);
									$midn_checkout = mktime(0, 0, 0, $checkout_info['mon'], $checkout_info['mday'], $checkout_info['year']);
									if ($midn_gday >= $midn_checkin && $midn_gday < $midn_checkout) {
										$bfound++;
									}
								}
								if ($bfound >= $or['units']) {
									$rooms[$kor]['adates'][$oday]['newavail'] = 0;
								} else {
									$rooms[$kor]['adates'][$oday]['newavail'] = ($or['units'] - $bfound);
								}
							}
						} else {
							foreach ($groupdays as $gday) {
								$oday = date('Y-m-d', $gday);
								$rooms[$kor]['adates'][$oday]['newavail'] = $or['units'];
							}
						}
					}
				}
				
				if (count($rooms) > 0) {
					//Invoke the Smart Balancer to adjust the remaining availability for the channels
					$smartbal = VikChannelManager::getSmartBalancerInstance();
					$rooms = $smartbal->applyAvailabilityRulesOnSync($rooms, $rows[0]);
					
					return $rooms;
				} else {
					$this->saveNotify('0', 'VCM', 'e4j.error.Channels.NoSynchRooms', $this->order_id);
				}
			}
		}
		return array();
	}
	
	private function getGroupDays($first, $second, $daysdiff)
	{
		$ret = array();
		$ret[] = $first;
		if ($daysdiff > 1) {
			$start = getdate($first);
			$end = getdate($second);
			$endcheck = mktime(0, 0, 0, $end['mon'], $end['mday'], $end['year']);
			for ($i = 1; $i < $daysdiff; $i++) {
				$checkday = $start['mday'] + $i;
				$dayts = mktime(0, 0, 0, $start['mon'], $checkday, $start['year']);
				if ($dayts != $endcheck) {
					$ret[] = $dayts;
				}
			}
		}
		//do not send the availability information about the checkout day
		//$ret[] = $second;

		return $ret;
	}
	
	private function getHoursRoomAvail()
	{
		$q = "SELECT `setting` FROM `#__vikbooking_config` WHERE `param`='hoursmoreroomavail';";
		$this->dbo->setQuery($q);
		$this->dbo->execute();
		$s = $this->dbo->loadAssocList();

		return $s[0]['setting'];
	}
	
	/**
	 * Stores a notification in the db for VikChannelManager
	 * Type can be: 0 (Error), 1 (Success), 2 (Warning)
	 */
	private function saveNotify($type, $from, $cont, $idordervb = '')
	{
		$q = "INSERT INTO `#__vikchannelmanager_notifications` (`ts`,`type`,`from`,`cont`,`idordervb`,`read`) VALUES('".time()."', '".$type."', '".$from."', ".$this->dbo->quote($cont).", '".$idordervb."', 0);";
		$this->dbo->setQuery($q);
		$this->dbo->execute();

		return true;
	}
	
	/**
	 * Stores multiple notifications in the db for VikChannelManager
	 */
	private function saveMultipleNotifications($arr_rs)
	{
		$gen_type = 1;
		foreach ($arr_rs as $chid => $chrs) {
			if (substr($chrs, 0, 9) == 'e4j.error') {
				$gen_type = 0;
				break;
			} elseif (substr($chrs, 0, 11) == 'e4j.warning') {
				$gen_type = 2;
			}
		}
		//Store parent notification
		$q = "INSERT INTO `#__vikchannelmanager_notifications` (`ts`,`type`,`from`,`cont`,`idordervb`,`read`) VALUES('".time()."', ".$gen_type.", 'VCM', ".$this->dbo->quote('Availability Update RQ').", ".$this->order_id.", 0);";
		$this->dbo->setQuery($q);
		$this->dbo->execute();
		$id_parent = $this->dbo->insertId();
		if (!empty($id_parent)) {
			//Store child notifications
			foreach ($arr_rs as $chid => $chrs) {
				if (substr($chrs, 0, 9) == 'e4j.error') {
					$q = "INSERT INTO `#__vikchannelmanager_notification_child` (`id_parent`,`type`,`cont`,`channel`) VALUES(".$id_parent.", 0, ".$this->dbo->quote($chrs).", ".(int)$chid.");";
					$this->dbo->setQuery($q);
					$this->dbo->execute();
				} elseif (substr($chrs, 0, 11) == 'e4j.warning') {
					$q = "INSERT INTO `#__vikchannelmanager_notification_child` (`id_parent`,`type`,`cont`,`channel`) VALUES(".$id_parent.", 2, ".$this->dbo->quote($chrs).", ".(int)$chid.");";
					$this->dbo->setQuery($q);
					$this->dbo->execute();
				} else {
					$q = "INSERT INTO `#__vikchannelmanager_notification_child` (`id_parent`,`type`,`cont`,`channel`) VALUES(".$id_parent.", 1, ".$this->dbo->quote($chrs).", ".(int)$chid.");";
					$this->dbo->setQuery($q);
					$this->dbo->execute();
				}
			}
		}
		
		return true;
	}
	
	/**
	 * Generates and Saves a notification key for e4jConnect and VikChannelManager
	 * 
	 */
	private function generateNKey($idordervb)
	{
		$nkey = rand(1000, 9999);
		$q = "INSERT INTO `#__vikchannelmanager_keys` (`idordervb`,`key`) VALUES('".$idordervb."', '".$nkey."');";
		$this->dbo->setQuery($q);
		$this->dbo->execute();
		return $nkey;
	}

	/**
	 * Finds all the rooms involved with a shared calendar.
	 * 
	 * @param 		array 	$orderrooms 	all rooms booked and modified.
	 * 
	 * @return 		array 	list of room IDs, names, units involved (or empty array).
	 * 
	 * @since 		VCM 1.7.1 (February 2020) - VBO (J)1.13/(WP)1.3.0 (February 2020)
	 *
	 * @requires 	VCM 1.7.1 - VBO (J)1.13/(WP)1.3.0
	 * 
	 * @uses 		VikBooking::updateSharedCalendars()
	 */
	private function getRoomsSharedCalsInvolved($orderrooms)
	{
		if (!class_exists('VikBooking')) {
			require_once VBO_SITE_PATH . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'lib.vikbooking.php';
		}
		if (!method_exists('VikBooking', 'updateSharedCalendars')) {
			// VBO >= 1.13 (Joomla) - 1.3.0 (WordPress) is required.
			return array();
		}

		// gather all rooms booked or modified by this booking
		$roomids = array();
		foreach ($orderrooms as $or) {
			if (!in_array($or['idroom'], $roomids)) {
				array_push($roomids, $or['idroom']);
			}
		}

		// build room IDs not already involved
		$involved = array();
		try {
			$q = "SELECT * FROM `#__vikbooking_calendars_xref` WHERE `mainroom` IN (" . implode(', ', $roomids) . ") OR `childroom` IN (" . implode(', ', $roomids) . ");";
			$this->dbo->setQuery($q);
			$this->dbo->execute();
			if ($this->dbo->getNumRows()) {
				$rooms_found = $this->dbo->loadAssocList();
				foreach ($rooms_found as $rf) {
					if (!in_array($rf['mainroom'], $roomids)) {
						array_push($involved, $rf['mainroom']);
					}
					if (!in_array($rf['childroom'], $roomids)) {
						array_push($involved, $rf['childroom']);
					}
				}
			}
		} catch (Exception $e) {
			return array();
		}

		if (!count($involved)) {
			// do not proceed any further
			return array();
		}

		// make sure we do not have duplicate values
		$involved = array_unique($involved);

		// build extra rooms information
		$shared_rooms = array();

		// get information about names and units
		$q = "SELECT `id`,`name`,`units` FROM `#__vikbooking_rooms` WHERE `id` IN (" . implode(', ', $involved) . ");";
		$this->dbo->setQuery($q);
		$this->dbo->execute();
		if ($this->dbo->getNumRows()) {
			$extarooms = $this->dbo->loadAssocList();
			foreach ($extarooms as $v) {
				$clone = $orderrooms[0];
				$clone['idroom'] = $v['id'];
				$clone['units'] = $v['units'];
				if (isset($clone['name'])) {
					$clone['name'] = $v['name'];
				} elseif (isset($clone['room_name'])) {
					$clone['room_name'] = $v['name'];
				}
				array_push($shared_rooms, $clone);
			}
		}

		return $shared_rooms;
	}
	
}
