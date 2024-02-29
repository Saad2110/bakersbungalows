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
 * This Class is used by VikChannelManager to process the new bookings
 * received from e4jConnect in the BR_L task.
 * Saves the new bookings into VikBooking and returns a response for
 * e4jConnect, which is hanging via CURL for the process to complete.
 */
class NewBookingsVikBooking
{	
	private $config;
	private $arrbookings;
	private $cypher;
	private $roomsinfomap;
	private $vbCheckinSeconds;
	private $vbCheckoutSeconds;
	private $vbhoursmorebookingback;
	private $totbookings;
	private $savedbookings;
	private $arrconfirmnumbers;
	private $errorString;
	private $response;

	/**
	 * @var  	string  	The name of the channel for when in object context.
	 * 						This class can also be accessed statically to call
	 * 						some methods, so this serves for extra precision in
	 * 						finding an existing OTA reservation when in object
	 * 						context (BR_L), so not when in static context.
	 * @since 	1.6.8
	 */
	static $channelName = '';

	/**
	 * Flag to consider the processing of a booking as pending.
	 * 
	 * @var 	bool
	 * @since 	1.8.0
	 */
	private $pending_booking = false;

	/**
	 * Indicates a particular type of booking (Request to Book/Inquiry).
	 * 
	 * @var 	string
	 * @since 	1.8.0
	 */
	private $booking_type = null;

	/**
	 * Contains data for a particular type of booking (Request to Book/Inquiry).
	 * 
	 * @var 	mixed
	 * @since 	1.8.0
	 */
	private $booking_type_data = null;

	/**
	 * Pool of iCal bookings downloaded to check for cancellations.
	 * 
	 * @var 	array
	 * @since 	1.8.9
	 */
	private $ical_signature_map = [];

	/**
	 * Class constructor requires the VCM configuration array and list of bookings to parse.
	 * 
	 * @param 	array 	$config
	 * @param 	array 	$arrbookings
	 */
	public function __construct($config, $arrbookings)
	{
		$this->config = $config;
		$this->arrbookings = $arrbookings;

		if (!is_array($this->arrbookings)) {
			$this->arrbookings = [];
		}

		if (empty($this->arrbookings['orders'])) {
			$this->arrbookings['orders'] = [];
		}
		
		// load dependencies
		if (!class_exists('VikChannelManager') || !class_exists('VikApplication')) {
			require_once(VCM_SITE_PATH . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'lib.vikchannelmanager.php');
		}
		if (!class_exists('VikChannelManagerConfig')) {
			require_once(VCM_SITE_PATH . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'vcm_config.php');
		}
		if (!class_exists('VikBooking')) {
			require_once(VBO_SITE_PATH . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "lib.vikbooking.php");
		}
		if (!class_exists('SynchVikBooking')) {
			require_once(VCM_SITE_PATH . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "synch.vikbooking.php");
		}
		
		// the salt is hashed twice
		$this->cypher = VikChannelManager::loadCypherFramework(md5($this->config['apikey']));
		
		$this->roomsinfomap = array();
		$this->vbCheckinSeconds = '';
		$this->vbCheckoutSeconds = '';
		$this->vbhoursmorebookingback = '';
		$this->totbookings = count($this->arrbookings['orders']);
		$this->savedbookings = 0;
		$this->arrconfirmnumbers = array();
		$this->errorString = '';
		$this->response = 'e4j.error';

		self::$channelName = $this->config['channel']['name'];
	}
	
	/**
	 * Main method called by VikChannelManager in the BR_L task.
	 * Processes the information received from e4jConnect.
	 * Returns the response for e4jConnect.
	 * 
	 * @return 	string
	 */
	public function processNewBookings()
	{
		if ((int)$this->config['vikbookingsynch'] < 1) {
			// VCM sync is disabled, so no bookings can come in
			$this->response = 'e4j.ok.vcmsynchdisabled';
			// store error notification so that the admin can see it
			$this->saveNotify(0, 'VCM', 'e4j.error.Channels.Auto-sync disabled');

			// return erroneous response
			return $this->response;
		}
		
		foreach ($this->arrbookings['orders'] as $order) {
			if (!$this->checkOrderIntegrity($order)) {
				// could not validate the booking structure
				$this->saveNotify(0, ucwords($this->config['channel']['name']), "e4j.error.Channels.InvalidBooking\n" . $this->getError() . "\n" . print_r($order, true));
				// unset errors for next booking processing
				$this->errorString = '';

				// go to next booking
				continue;
			}

			/**
			 * We now support a flag to consider the booking status as pending.
			 * We also reset all flags indicating the particular type of booking.
			 * 
			 * @since 	1.8.0
			 */
			$this->pending_booking 	 = false;
			$this->booking_type 	 = null;
			$this->booking_type_data = null;
			//

			switch ($order['info']['ordertype']) {
				case 'Book':
					/**
					 * In order to support accepted Inquiries (Special Offers) that are currently
					 * saved in VBO with a pending status, we check if an OTA booking with the same
					 * thread id already exists, to rather perform a booking modification request.
					 * 
					 * @since 	1.8.0
					 */
					$inquiry_booking = false;
					if (!empty($order['info']['thread_id'])) {
						if ($order['info']['idorderota'] == $order['info']['thread_id']) {
							// look for an ota booking with id equal to the thread id
							$inquiry_booking = self::otaBookingExists($order['info']['thread_id'], true);
						} else {
							/**
							 * In order to fully support Webhook notifications for Request to Book reservations
							 * becoming "confirmed" from "standby", we need to check if the OTA booking exists
							 * by using the confirmation code, something available for the "RtB" reservations,
							 * but not for the Booking Inquiries. In short, "RtB" reservations of Airbnb API
							 * have got identical payloads to regular "Instant Book" reservations. Therefore,
							 * we need to check the booking exists, so that we can update it rather than create it.
							 * Request to Book reservations are better to be confirmed through VBO rather than on Airbnb.
							 * Try to look for a "Booking Request" reservation previously stored with "standby" status,
							 * in this case the OTA reservation ID must be different than the Thread ID for the messaging.
							 * 
							 * @since 	1.8.2
							 */
							$inquiry_booking = self::otaBookingExists($order['info']['idorderota'], true);
						}
					}

					if ($inquiry_booking !== false && $inquiry_booking['status'] == 'standby') {
						// previous prending inquiry found, update it to a confirmed booking
						$result = $this->modifyBooking($order, $inquiry_booking);
					} else {
						// regular processing of a new booking
						$result = $this->saveBooking($order);
					}
					break;
				case 'Request':
					/**
					 * Request to Book reservation should create a new booking with pending status.
					 * 
					 * @since 	1.8.0
					 */
					$this->pending_booking = true;
					$this->booking_type = 'request';
					//
					$result = $this->saveBooking($order);
					break;
				case 'Inquiry':
					/**
					 * Booking Inquiry reservation should create a new booking with pending status.
					 * 
					 * @since 	1.8.0
					 */
					$this->pending_booking = true;
					$this->booking_type = 'inquiry';
					//
					$result = $this->saveBooking($order);
					break;
				case 'Modify':
					$result = $this->modifyBooking($order);
					break;
				case 'Cancel':
					$result = $this->cancelBooking($order);
					break;
				case 'CancelRequest':
					/**
					 * Cancel Request to Book should cancel a booking with pending status.
					 * 
					 * @since 	1.8.0
					 */
					$this->pending_booking = true;
					$result = $this->cancelBooking($order);
					break;
				case 'CancelInquiry':
					/**
					 * Cancel Inquiry request should cancel a booking with pending status.
					 * 
					 * @since 	1.8.0
					 */
					$this->pending_booking = true;
					$result = $this->cancelBooking($order);
					break;
				case 'Download':
					$result = $this->downloadedBooking($order);
					break;
				default:
					break;
			}

			if ($result === true) {
				// increase number of bookings saved
				$this->savedbookings++;
			}

			if (strlen($this->getError()) > 0) {
				/**
				 * Unset errors for next booking processing, do not store any error 
				 * notification as they must have been set already in case of errors.
				 */
				$this->errorString = '';
			}
		}
		
		// set the response for e4jConnect to the serialized array with the confirmation numbers or to an ok string
		if (count($this->arrconfirmnumbers) > 0) {
			$this->arrconfirmnumbers['auth'] = md5($this->config['apikey'] . 'rs_e4j');
			$this->response = serialize($this->arrconfirmnumbers);
		} else {
			$this->response = 'e4j.ok.savedbookingsindb:0.savedbookings:' . $this->savedbookings;
		}

		/**
		 * Check if some iCal bookings were downloaded so that we can check for cancellations.
		 * No need to do it if some iCal bookings were saved into $this->ical_signature_map as
		 * it's safe to call this method also in case of API channels (nothing would happen).
		 * 
		 * @since 	1.8.9
		 */
		$this->iCalCheckNewCancellations();
		
		return $this->response;
	}
	
	/**
	 * Checks that the single booking is valid and not
	 * missing some required values to be processed and saved.
	 * 
	 * @param 	array 	$order
	 * 
	 * @return 	bool
	 */
	public function checkOrderIntegrity($order)
	{
		$otype = '';
		switch ($order['info']['ordertype']) {
			case 'Book':
			case 'Request':
			case 'Inquiry':
				$otype = 'Book';
				break;
			case 'Modify':
				$otype = 'Modify';
				break;
			case 'Cancel':
			case 'CancelRequest':
			case 'CancelInquiry':
				$otype = 'Cancel';
				break;
			case 'Download':
				$otype = 'Download';
				break;
			default:
				$this->setError("1) checkOrderIntegrity: empty oType");
				return false;
		}
		
		// required fields: booking id, booking type, check-in, check-out
		$validate = array(
			$order['info']['idorderota'], 
			$order['info']['ordertype'], 
			(isset($order['info']['checkin']) ? $order['info']['checkin'] : ''), 
			(isset($order['info']['checkout']) ? $order['info']['checkout'] : '')
		);
		foreach ($validate as $k => $elem) {
			if (strlen($elem) < 1) {
				if ($otype != 'Cancel') {
					$this->setError("2) checkOrderIntegrity: empty index ".$k);
					return false;
				} else {
					// booking cancellations may return empty checkin and checkout for some channels
					if ((int)$k < 2) {
						$this->setError("3) checkOrderIntegrity: empty index ".$k);
						return false;
					}
				}
			}
		}
		
		// make sure at least one room was passed
		$roomfound = false;
		if (isset($order['roominfo'])) {
			if (isset($order['roominfo']['idroomota']) && !empty($order['roominfo']['idroomota'])) {
				// array with single room structure
				$roomfound = true;
			} else {
				foreach ($order['roominfo'] as $elem) {
					if (isset($elem['idroomota']) && !empty($elem['idroomota'])) {
						// array with possible multiple rooms structure
						$roomfound = true;
						break;
					}
				}
			}
		}
		if ($otype != 'Cancel' && !$roomfound) {
			$this->setError("4) checkOrderIntegrity: empty IDRoomOTA");
			return false;
		}

		return true;
	}
	
	/**
	 * Saves a new booking into VikBooking.
	 * 
	 * @param 	array 	$order
	 */
	public function saveBooking($order)
	{
		if (!self::otaBookingExists($order['info']['idorderota'])) {
			//idroomvb mapping the idroomota
			//check whether the room is one or more
			if (array_key_exists(0, $order['roominfo'])) {
				if (count($order['roominfo']) > 1) {
					//multiple rooms
					$check_idroomota = array();
					foreach ($order['roominfo'] as $rk => $ordr) {
						$check_idroomota[] = $ordr['idroomota'];
					}
				} else {
					//single room
					$check_idroomota = $order['roominfo'][0]['idroomota'];
				}
			} else {
				//single room
				$check_idroomota = $order['roominfo']['idroomota'];
			}
			//
			$idroomvb = $this->mapIdroomVbFromOtaId($check_idroomota);
			if (((!is_array($idroomvb) && intval($idroomvb) > 0) || (is_array($idroomvb) && count($idroomvb) > 0)) && $idroomvb !== false) {
				//check-in and check-out timestamps, num of nights for VikBooking
				$checkints = $this->getCheckinTimestamp($order['info']['checkin']);
				$checkoutts = $this->getCheckoutTimestamp($order['info']['checkout']);
				$numnights = $this->countNumberOfNights($checkints, $checkoutts);
				if ($checkints > 0 && $checkoutts > 0 && $numnights > 0) {
					//count num people, total order, compose customer info, purchaser email, special request
					$adults = 0;
					$children = 0;
					if (strlen($order['info']['adults']) > 0) {
						$adults = $order['info']['adults'];
					}
					if (strlen($order['info']['children']) > 0) {
						$children = $order['info']['children'];
					}
					$total = 0;
					if (strlen($order['info']['total']) > 0) {
						$total = (float)$order['info']['total'];
					}
					$customerinfo = '';
					$purchaseremail = '';
					if (array_key_exists('customerinfo', $order)) {
						foreach ($order['customerinfo'] as $what => $cinfo) {
							if ($what == 'pic') {
								// the customer profile picture will be saved onto the database
								continue;
							}
							$customerinfo .= ucwords($what).": ".$cinfo."\n";
						}
						if (array_key_exists('email', $order['customerinfo'])) {
							$purchaseremail = $order['customerinfo']['email'];
						}
					}
					//add information about Breakfast, Extra-bed, IATA, Promotion and such
					if (array_key_exists('breakfast_included', $order['info'])) {
						$customerinfo .= 'Breakfast Included: '.$order['info']['breakfast_included']."\n";
					}
					if (array_key_exists('extrabed', $order['info'])) {
						$customerinfo .= 'Extra Bed: '.$order['info']['extrabed']."\n";
					}
					if (array_key_exists('IATA', $order['info'])) {
						$customerinfo .= 'IATA ID: '.$order['info']['IATA']."\n";
					}
					if (array_key_exists('promotion', $order['info'])) {
						$customerinfo .= 'Promotion: '.$order['info']['promotion']."\n";
					}
					if (array_key_exists('loyalty_id', $order['info'])) {
						$customerinfo .= 'Loyalty ID: '.$order['info']['loyalty_id']."\n";
					}
					//
					$customerinfo = rtrim($customerinfo, "\n");

					//check if the room is available
					$room_available = false;
					if (is_array($idroomvb)) {
						$room_available = $this->roomsAreAvailableInVb($idroomvb, $order, $checkints, $checkoutts, $numnights);
						// TODO: if $room_available is an array it means that some rooms were not available:
						// administrator should be notified because one or more rooms, but not all, may be overbooked. Rare case.
					} else {
						$check_idroomota_key = array_key_exists(0, $order['roominfo']) ? $order['roominfo'][0]['idroomota'] : $order['roominfo']['idroomota'];
						$room_available = $this->roomIsAvailableInVb($idroomvb, $this->roomsinfomap[$check_idroomota_key]['totunits'], $checkints, $checkoutts, $numnights);
					}
					//
					if ($room_available === true || is_array($room_available) || ($this->pending_booking === true && in_array($this->booking_type, array('request', 'inquiry')))) {
						//decode credit card details
						$order['info']['credit_card'] = $this->processCreditCardDetails($order);

						//Save the new order, set confirmnumber for the booking id in the class array arrconfirmnumbers and save notification in VCM
						$newdata = $this->saveNewVikBookingOrder($order, $idroomvb, $checkints, $checkoutts, $numnights, $adults, $children, $total, $customerinfo, $purchaseremail);

						/**
						 * Save an extra notification in the booking history in case this was saved
						 * as a pending request/inquiry reservation for dates with no availability.
						 * This way, VBO could check the event to display an additional alert in the
						 * booking details page.
						 * 
						 * @since 	1.8.3
						 */
						if (is_array($newdata) && !empty($newdata['newvborderid']) && $this->pending_booking && (!$room_available || is_array($room_available))) {
							// prepare extra data object for the event record
							$ev_data = new stdClass;
							$ev_data->pending_booking = 1;
							$ev_data->booking_type = $this->booking_type;
							$ev_data->no_availability = 1;
							$ev_data->unavailable_rooms = !$room_available ? array($idroomvb) : $room_available;

							// Booking History
							if (method_exists('VikBooking', 'getBookingHistoryInstance')) {
								// store alert event to inform that there is no availability
								VikBooking::getBookingHistoryInstance()->setBid($newdata['newvborderid'])->setExtraData($ev_data)->store('CM', JText::_('VCM_EVALERT_PEND_NO_AV'));
							}
						}

						/**
						 * For the iCal bookings downloaded, we update the signature map.
						 * One iCal booking is always for one room only.
						 * 
						 * @since 	1.8.9
						 */
						if (is_array($newdata) && !empty($newdata['newvborderid']) && !empty($order['info']['ical_sign']) && !is_array($idroomvb)) {
							$this->ical_signature_map[$newdata['newvborderid']] = [
								'room_id' 	 => (int)$idroomvb,
								'channel_id' => $this->config['channel']['uniquekey'],
								'ota_bid' 	 => $order['info']['idorderota'],
								'signature'  => $order['info']['ical_sign'],
							];
						}

						//Compose information about the RatePlan Name and the Payment
						$rateplan_info = $this->mapPriceVbFromRatePlanId($order);
						$notification_extra = '';
						if (!empty($rateplan_info)) {
							$notification_extra .= "\n".$rateplan_info;
						}
						if (isset($order['info']['price_breakdown']) && count($order['info']['price_breakdown'])) {
							$notification_extra .= "\nPrice Breakdown:\n";
							foreach ($order['info']['price_breakdown'] as $day => $cost) {
								$notification_extra .= $day." - ".$order['info']['currency'].' '.$cost."\n";
							}
							$notification_extra = rtrim($notification_extra, "\n");
						}
						if (count($order['info']['credit_card']) > 0) {
							$notification_extra .= "\nCredit Card:\n";
							foreach ($order['info']['credit_card'] as $card_info => $card_data) {
								if ($card_info == 'card_number_pci') {
									//do not touch this part or you will lose any PCI-compliant function
									continue;
								}
								if (is_array($card_data)) {
									$notification_extra .= ucwords(str_replace('_', ' ', $card_info)).":\n";
									foreach ($card_data as $card_info_in => $card_data_in) {
										$notification_extra .= ucwords(str_replace('_', ' ', $card_info_in)).": ".$card_data_in."\n";
									}
								} else {
									$notification_extra .= ucwords(str_replace('_', ' ', $card_info)).": ".$card_data."\n";
								}
							}
							$notification_extra = rtrim($notification_extra, "\n");
						}
						//
						$this->saveNotify('1', ucwords($this->config['channel']['name']), "e4j.OK.Channels.NewBookingDownloaded".$notification_extra, $newdata['newvborderid']);

						// add values to be returned as serialized to e4jConnect as response
						if (!isset($this->arrconfirmnumbers[$order['info']['idorderota']])) {
							$this->arrconfirmnumbers[$order['info']['idorderota']] = [];
						}
						$this->arrconfirmnumbers[$order['info']['idorderota']]['ordertype'] = 'Book';
						$this->arrconfirmnumbers[$order['info']['idorderota']]['confirmnumber'] = $newdata['confirmnumber'];
						$this->arrconfirmnumbers[$order['info']['idorderota']]['vborderid'] = $newdata['newvborderid'];
						$this->arrconfirmnumbers[$order['info']['idorderota']]['nkey'] = $this->generateNKey($newdata['newvborderid']);

						// Notify AV=1-Channels for the new booking
						$vcm = new SynchVikBooking($newdata['newvborderid'], array($this->config['channel']['uniquekey']));
						$vcm->sendRequest();

						// SMS
						VikBooking::sendBookingSMS($newdata['newvborderid']);

						return true;
					} else {
						//The room results not available in VikBooking, notify Administrator but return true anyways for e4jConnect
						//a notification will be saved also inside VCM. All of this only if booking does not come from iCal Download
						if (!array_key_exists('iCal', $order)) {
							$errmsg = $this->notifyAdministratorRoomNotAvailable($order);
							$this->saveNotify('0', ucwords($this->config['channel']['name']), "e4j.error.Channels.BookingDownload\n".$errmsg);
							// VCM 1.6.8 - ReservationsLogger should run even when the SynchVikBooking Class is not called
							VikChannelManager::getResLoggerInstance()
								->typeFromChannels(array($this->config['channel']['uniquekey']))
								->trackLog($order);
							//
						}

						return true;
					}
				} else {
					$this->setError("2) saveBooking: OTAid: ".$order['info']['idorderota']." empty or invalid stay dates (".$order['info']['checkin']." - ".$order['info']['checkout'].")");
				}
			} else {
				$this->setError("1) saveBooking: OTAid: ".$order['info']['idorderota']." - OTARoom ".$order['roominfo']['idroomota'].", not mapped");
			}
		}
		return false;
	}
	
	/**
	 * Modifies an existing booking in VikBooking.
	 * 
	 * @param 	array 	$order 			the ota reservation array.
	 * @param 	array 	$prev_booking 	optional previous booking to modify.
	 * 
	 * @since 	1.8.0 	$prev_booking was added to modify a precise and existing reservation.
	 */
	public function modifyBooking($order, $prev_booking = null)
	{
		$dbo = JFactory::getDbo();

		if (is_array($prev_booking)) {
			$vbo_order_info = $prev_booking;
		} else {
			$vbo_order_info = self::otaBookingExists($order['info']['idorderota'], true);
		}

		if ($vbo_order_info) {
			// idroomvb mapping the idroomota
			// check whether the room is one or more
			if (array_key_exists(0, $order['roominfo'])) {
				if (count($order['roominfo']) > 1) {
					// multiple rooms
					$check_idroomota = array();
					foreach ($order['roominfo'] as $rk => $ordr) {
						$check_idroomota[] = $ordr['idroomota'];
					}
				} else {
					// single room
					$check_idroomota = $order['roominfo'][0]['idroomota'];
				}
			} else {
				// single room
				$check_idroomota = $order['roominfo']['idroomota'];
			}
			//
			$idroomvb = $this->mapIdroomVbFromOtaId($check_idroomota);
			if (((!is_array($idroomvb) && intval($idroomvb) > 0) || (is_array($idroomvb) && count($idroomvb) > 0)) && $idroomvb !== false) {
				// check-in and check-out timestamps, num of nights for VikBooking
				$checkints = $this->getCheckinTimestamp($order['info']['checkin']);
				$checkoutts = $this->getCheckoutTimestamp($order['info']['checkout']);
				$numnights = $this->countNumberOfNights($checkints, $checkoutts);
				if ($checkints > 0 && $checkoutts > 0 && $numnights > 0) {
					//c ount num people, total order, compose customer info, purchaser email, special request
					$adults = 0;
					$children = 0;
					if (strlen($order['info']['adults']) > 0) {
						$adults = $order['info']['adults'];
					}
					if (strlen($order['info']['children']) > 0) {
						$children = $order['info']['children'];
					}
					$total = 0;
					if (strlen($order['info']['total']) > 0) {
						$total = (float)$order['info']['total'];
					}
					$tot_taxes = 0;
					if (isset($order['info']['tax']) && floatval($order['info']['tax']) > 0) {
						$tot_taxes = floatval($order['info']['tax']);
					}
					/**
					 * Total city taxes can be collected from booking information.
					 * 
					 * @since 	1.8.0
					 */
					$tot_city_taxes = 0;
					if (isset($order['info']['city_tax']) && floatval($order['info']['city_tax']) > 0) {
						$tot_city_taxes = floatval($order['info']['city_tax']);
					}
					$customerinfo = '';
					$purchaseremail = '';
					if (array_key_exists('customerinfo', $order)) {
						foreach ($order['customerinfo'] as $what => $cinfo) {
							if ($what == 'pic') {
								// the customer profile picture will be saved onto the database
								continue;
							}
							$customerinfo .= ucwords($what).": ".$cinfo."\n";
						}
						if (array_key_exists('email', $order['customerinfo'])) {
							$purchaseremail = $order['customerinfo']['email'];
						}
					}
					// add information about Breakfast, Extra-bed, IATA, Promotion and such
					if (array_key_exists('breakfast_included', $order['info'])) {
						$customerinfo .= 'Breakfast Included: '.$order['info']['breakfast_included']."\n";
					}
					if (array_key_exists('extrabed', $order['info'])) {
						$customerinfo .= 'Extra Bed: '.$order['info']['extrabed']."\n";
					}
					if (array_key_exists('IATA', $order['info'])) {
						$customerinfo .= 'IATA ID: '.$order['info']['IATA']."\n";
					}
					if (array_key_exists('promotion', $order['info'])) {
						$customerinfo .= 'Promotion: '.$order['info']['promotion']."\n";
					}
					if (array_key_exists('loyalty_id', $order['info'])) {
						$customerinfo .= 'Loyalty ID: '.$order['info']['loyalty_id']."\n";
					}
					//
					$customerinfo = rtrim($customerinfo, "\n");

					// check if the room is available
					// get the busy ids for the order
					$excludebusyids = array();
					$q = "SELECT * FROM `#__vikbooking_ordersbusy` WHERE `idorder`=".(int)$vbo_order_info['id'].";";
					$dbo->setQuery($q);
					$dbo->execute();
					if ($dbo->getNumRows() > 0) {
						$ordbusy = $dbo->loadAssocList();
						foreach ($ordbusy as $ob) {
							$excludebusyids[] = $ob['idbusy'];
						}
					}
					$room_available = false;
					if (is_array($idroomvb)) {
						$room_available = $this->roomsAreAvailableInVbModification($idroomvb, $order, $checkints, $checkoutts, $numnights, $excludebusyids);
						// TODO: if $room_available is an array it means that some rooms were not available:
						// administrator should be notified because one or more rooms, but not all, may be overbooked. Rare case.
					} else {
						$check_idroomota_key = array_key_exists(0, $order['roominfo']) ? $order['roominfo'][0]['idroomota'] : $order['roominfo']['idroomota'];
						$room_available = $this->roomIsAvailableInVbModification($idroomvb, $this->roomsinfomap[$check_idroomota_key]['totunits'], $checkints, $checkoutts, $numnights, $excludebusyids);
					}
					//
					if ($room_available === true || is_array($room_available)) {
						// delete old busy ids
						if (count($excludebusyids) > 0) {
							$q = "DELETE FROM `#__vikbooking_busy` WHERE `id` IN (".implode(", ", $excludebusyids).");";
							$dbo->setQuery($q);
							$dbo->execute();
						}
						$q = "DELETE FROM `#__vikbooking_ordersrooms` WHERE `idorder`=".(int)$vbo_order_info['id'].";";
						$dbo->setQuery($q);
						$dbo->execute();
						$q = "DELETE FROM `#__vikbooking_ordersbusy` WHERE `idorder`=".(int)$vbo_order_info['id'].";";
						$dbo->setQuery($q);
						$dbo->execute();
						// always set $idroomvb to an array even if it is just a string
						$orig_idroomvb = $idroomvb;
						unset($idroomvb);
						if (is_array($orig_idroomvb)) {
							$idroomvb = array_values($orig_idroomvb);
						} else {
							$idroomvb = array($orig_idroomvb);
						}
						// insert new busy and room data
						// Number of Rooms
						$num_rooms = 1;
						if (array_key_exists('num_rooms', $order['info']) && intval($order['info']['num_rooms']) > 1) {
							$num_rooms = intval($order['info']['num_rooms']);
						}
						//
						$busy_ids = array();
						for ($i = 1; $i <= $num_rooms; $i++) {
							$room_checkints = $checkints;
							$room_checkoutts = $checkoutts;
							// set checkin and check out dates for each room if they are different than the check-in or check-out date of the booking (Booking.com)
							if (array_key_exists(($i - 1), $order['roominfo']) && array_key_exists('checkin', $order['roominfo'][($i - 1)]) && array_key_exists('checkout', $order['roominfo'][($i - 1)])) {
								if ($order['roominfo'][($i - 1)]['checkin'] != $order['info']['checkin'] || $order['roominfo'][($i - 1)]['checkout'] != $order['info']['checkout']) {
									$room_checkints = $this->getCheckinTimestamp($order['roominfo'][($i - 1)]['checkin']);
									$room_checkoutts = $this->getCheckinTimestamp($order['roominfo'][($i - 1)]['checkout']);
								}
							}
							//
							$q = "INSERT INTO `#__vikbooking_busy` (`idroom`,`checkin`,`checkout`,`realback`) VALUES('" . $idroomvb[($i - 1)] . "', '" . $room_checkints . "', '" . $room_checkoutts . "','" . $room_checkoutts . "');";
							$dbo->setQuery($q);
							$dbo->execute();
							$busyid = $dbo->insertid();
							$busy_ids[$i] = $busyid;
						}
						// Adults and Children are returned as total by the OTA. If multiple rooms, dispose the Adults and Children accordingly
						$rooms_aduchild = array();
						if ($num_rooms > 1) {
							$adults_per_room = floor($adults / $num_rooms);
							$adults_per_room = $adults_per_room < 0 ? 0 : $adults_per_room;
							$spare_adults = ($adults - ($adults_per_room * $num_rooms));
							$children_per_room = floor($children / $num_rooms);
							$children_per_room = $children_per_room < 0 ? 0 : $children_per_room;
							$spare_children = ($children - ($children_per_room * $num_rooms));
							for ($i = 1; $i <= $num_rooms; $i++) {
								$adults_occupancy = $adults_per_room;
								$children_occupancy = $children_per_room;
								if ($i == 1 && ($spare_adults > 0 || $spare_children > 0)) {
									$adults_occupancy += $spare_adults;
									$children_occupancy += $spare_children;
								}
								$rooms_aduchild[$i]['adults'] = $adults_occupancy;
								$rooms_aduchild[$i]['children'] = $children_occupancy;
							}
						} else {
							$rooms_aduchild[$num_rooms]['adults'] = $adults;
							$rooms_aduchild[$num_rooms]['children'] = $children;
						}
						//
						$has_different_checkins_notif = false;

						// Phone Number and Customers Management (VikBooking 1.6 or higher, check if cpin.php exists - since v1.6)
						$phone = '';
						if (isset($order['customerinfo']) && !empty($order['customerinfo']['telephone'])) {
							$phone = $order['customerinfo']['telephone'];
						}

						// country
						$country = '';
						if (isset($order['customerinfo']) && !empty($order['customerinfo']['country'])) {
							if (strlen($order['customerinfo']['country']) == 3) {
								$country = $order['customerinfo']['country'];
							} elseif (strlen($order['customerinfo']['country']) == 2) {
								$q = "SELECT `country_3_code` FROM `#__vikbooking_countries` WHERE `country_2_code`=".$dbo->quote($order['customerinfo']['country']).";";
								$dbo->setQuery($q);
								$dbo->execute();
								if ($dbo->getNumRows() == 1) {
									$country = $dbo->loadResult();
								}
							} elseif (strlen($order['customerinfo']['country']) > 3) {
								$q = "SELECT `country_3_code` FROM `#__vikbooking_countries` WHERE `country_name` LIKE ".$dbo->quote('%'.$order['customerinfo']['country'].'%').";";
								$dbo->setQuery($q);
								$dbo->execute();
								if ($dbo->getNumRows() > 0) {
									$country = $dbo->loadResult();
								}
							}
						}

						/**
						 * We need to format the phone number by prepending the country prefix if this is missing.
						 * 
						 * @since 	1.6.18
						 */
						if (!empty($phone) && !empty($country)) {
							// do not trim completely as the plus symbol may be a leading white-space
							$phone = rtrim($phone);

							if (substr($phone, 0, 1) == ' ' && strlen($phone) > 5) {
								/**
								 * Phone numbers inclusive of prefix with the plus symbol may be delivered by e4jConnect as a leading white space.
								 * The plus symbol gets printed as a white-space, and so this is what VCM gets. We should only right-trim until now.
								 * In these cases we apply the left trim to complete the trimming, then we prepend the plus symbol so that the phone
								 * number returned by the OTAs won't be touched as it's probably complete and inclusive of country prefix.
								 * 
								 * @since 	1.7.2
								 */
								$phone = ltrim($phone);
								$phone = '+' . $phone;
							}

							if (substr($phone, 0, 1) != '+' && substr($phone, 0, 2) != '00') {
								// try to find the country phone prefix since it's missing in the number
								$q = "SELECT `phone_prefix` FROM `#__vikbooking_countries` WHERE `country_" . (strlen($country) == 2 ? '2' : '3') . "_code`=" . $dbo->quote($country) . ";";
								$dbo->setQuery($q);
								$dbo->execute();
								if ($dbo->getNumRows()) {
									$country_prefix = str_replace(' ', '', $dbo->loadResult());
									$num_prefix = str_replace('+', '', $country_prefix);
									if (substr($phone, 0, strlen($num_prefix)) != $num_prefix) {
										// country prefix is completely missing
										$phone = $country_prefix . $phone;
									} else {
										// try to prepend the plus symbol because the phone number starts with the country prefix
										$phone = '+' . $phone;
									}
								}
							}
						}

						/**
						 * Customer Extra Info such as address, city, zip, company, vat are
						 * stored into an array that will be passed onto VikBookingCustomersPin.
						 * 
						 * @since 	1.6.12
						 * @since 	1.8.6 	added support for customer profile picture (avatar).
						 */
						$extra_info_keys = [
							'address',
							'city',
							'zip',
							'company',
							'vat',
							'pic',
						];
						$customer_extra_info = [];
						foreach ($extra_info_keys as $extra_key) {
							if (isset($order['customerinfo']) && !empty($order['customerinfo'][$extra_key])) {
								$customer_extra_info[$extra_key] = $order['customerinfo'][$extra_key];
							}
						}
						
						$traveler_first_name = array_key_exists('traveler_first_name', $order['info']) ? $order['info']['traveler_first_name'] : '';
						$traveler_last_name = array_key_exists('traveler_last_name', $order['info']) ? $order['info']['traveler_last_name'] : '';
						
						// default Tax Rate (VCM 1.6.3)
						$default_tax_rate = 0;
						$q = "SELECT `id` FROM `#__vikbooking_iva` ORDER BY `id` ASC LIMIT 1;";
						$dbo->setQuery($q);
						$dbo->execute();
						if ($dbo->getNumRows() > 0) {
							$default_tax_rate = (int)$dbo->loadResult();
						}

						// assign room specific unit
						$set_room_indexes = $this->autoRoomUnit();
						$room_indexes_usemap = array();

						foreach ($busy_ids as $num_room => $id_busy) {
							$q = "INSERT INTO `#__vikbooking_ordersbusy` (`idorder`,`idbusy`) VALUES(".(int)$vbo_order_info['id'].", ".(int)$id_busy.");";
							$dbo->setQuery($q);
							$dbo->execute();
							// traveler name for each room if available
							$room_t_first_name = $traveler_first_name;
							$room_t_last_name = $traveler_last_name;
							if (array_key_exists(($num_room - 1), $order['roominfo'])) {
								if (strlen($order['roominfo'][($num_room - 1)]['traveler_first_name'])) {
									$room_t_first_name = $order['roominfo'][($num_room - 1)]['traveler_first_name'];
									$room_t_last_name = $order['roominfo'][($num_room - 1)]['traveler_last_name'];
								}
							}

							// set checkin and check out dates next to traveler name if they are different than the check-in or check-out (Booking.com)
							if (array_key_exists(($num_room - 1), $order['roominfo']) && array_key_exists('checkin', $order['roominfo'][($num_room - 1)]) && array_key_exists('checkout', $order['roominfo'][($num_room - 1)])) {
								if ($order['roominfo'][($num_room - 1)]['checkin'] != $order['info']['checkin'] || $order['roominfo'][($num_room - 1)]['checkout'] != $order['info']['checkout']) {
									$room_t_last_name .= ' ('.$order['roominfo'][($num_room - 1)]['checkin'].' - '.$order['roominfo'][($num_room - 1)]['checkout'].')';
									// notification details (Booking.com) with guests, check-in and check-out dates for this room
									if (!is_array($has_different_checkins_notif)) {
										unset($has_different_checkins_notif);
										$has_different_checkins_notif = array();
									}
									$has_different_checkins_notif[] = $this->roomsinfomap[$order['roominfo'][($num_room - 1)]['idroomota']]['roomnamevb'].' - Check-in: '.$order['roominfo'][($num_room - 1)]['checkin'].' - Check-out: '.$order['roominfo'][($num_room - 1)]['checkout'].' - Guests: '.$order['roominfo'][($num_room - 1)]['guests'];
									//
								} else {
									// Maybe the check-in and check-out dates for the whole booking have now been set to the same ones as for this room, compare it with the old order with the date format Y-m-d
									$booking_prev_checkin = date('Y-m-d', $vbo_order_info['checkin']);
									$booking_prev_checkout = date('Y-m-d', $vbo_order_info['checkout']);
									if ($order['roominfo'][($num_room - 1)]['checkin'] != $booking_prev_checkin || $order['roominfo'][($num_room - 1)]['checkout'] != $booking_prev_checkout) {
										//notification details (Booking.com) with guests, check-in and check-out dates for this room
										if (!is_array($has_different_checkins_notif)) {
											unset($has_different_checkins_notif);
											$has_different_checkins_notif = array();
										}
										$has_different_checkins_notif[] = $this->roomsinfomap[$order['roominfo'][($num_room - 1)]['idroomota']]['roomnamevb'].' - Check-in: '.$order['roominfo'][($num_room - 1)]['checkin'].' - Check-out: '.$order['roominfo'][($num_room - 1)]['checkout'].' - Guests: '.$order['roominfo'][($num_room - 1)]['guests'];
										//
									}
								}
							}

							// assign room specific unit
							$room_indexes = $set_room_indexes === true ? $this->getRoomUnitNumsAvailable(array('id' => $vbo_order_info['id'], 'checkin' => $checkints, 'checkout' => $checkoutts), $idroomvb[($num_room - 1)]) : array();
							$use_ind_key = 0;
							if (count($room_indexes)) {
								if (!array_key_exists($idroomvb[($num_room - 1)], $room_indexes_usemap)) {
									$room_indexes_usemap[$idroomvb[($num_room - 1)]] = $use_ind_key;
								} else {
									$use_ind_key = $room_indexes_usemap[$idroomvb[($num_room - 1)]];
								}
								$rooms[$num]['roomindex'] = (int)$room_indexes[$use_ind_key];
							}

							// OTA Rate Plan for this room booked
							$otarplan_supported = $this->otaRplanSupported();
							$room_otarplan = '';
							if (isset($order['roominfo'][($num_room - 1)]) && isset($order['roominfo'][($num_room - 1)]['rateplanid'])) {
								$room_otarplan = $order['roominfo'][($num_room - 1)]['rateplanid'];
							} elseif (isset($order['roominfo']['rateplanid'])) {
								$room_otarplan = $order['roominfo']['rateplanid'];
							}
							$room_otarplan = $this->getOtaRplanNameFromId($room_otarplan, (int)$idroomvb[($num_room - 1)]);

							/**
							 * Determine whether the application of taxes should be forced for some channels.
							 * 
							 * @since 	1.8.4
							 */
							$force_taxes = false;

							/**
							 * Set room exact cost (if available). Useful to print
							 * the cost of this room in case of multiple rooms booked.
							 * 
							 * @since 	1.6.13
							 */
							$now_room_cost = round(($total / $num_rooms), 2);
							if (isset($order['roominfo'][($num_room - 1)]) && isset($order['roominfo'][($num_room - 1)]['room_cost']) && floatval($order['roominfo'][($num_room - 1)]['room_cost']) > 0) {
								$now_room_cost = (float)$order['roominfo'][($num_room - 1)]['room_cost'];
								/**
								 * Hosts eligible for taxes working with Airbnb and using prices inclusive of tax
								 * in VBO may need to have the listing base cost inclusive of tax, as it's returned
								 * by the e4jConnect servers before taxes, due to the missing tax rate information
								 * and by other extra services that may still be subjected to tax.
								 * 
								 * @since 	1.8.4
								 */
								if (VikBooking::ivaInclusa() && $tot_taxes > 0 && $total > $now_room_cost && $this->config['channel']['uniquekey'] == VikChannelManagerConfig::AIRBNBAPI) {
									// room exact cost should be inclusive of taxes (force taxes to be applied)
									$now_room_cost = VikBooking::sayPackagePlusIva($now_room_cost, $default_tax_rate, true);
									// turn flag on
									$force_taxes = true;
								}
							}

							/**
							 * We try to get the exact number of adults and children from 'roominfo'
							 * because some channels may support this obvious information.
							 * 
							 * @since 	1.6.22
							 */
							if (isset($order['roominfo'][($num_room - 1)]) && isset($order['roominfo'][($num_room - 1)]['adults'])) {
								$rooms_aduchild[$num_room]['adults'] = $order['roominfo'][($num_room - 1)]['adults'];
							}
							if (isset($order['roominfo'][($num_room - 1)]) && isset($order['roominfo'][($num_room - 1)]['children'])) {
								$rooms_aduchild[$num_room]['children'] = $order['roominfo'][($num_room - 1)]['children'];
							}

							/**
							 * Extracosts for the reservation (AddOns) like Parking or Breakfast.
							 * VCM may get this information from some OTAs.
							 * 
							 * @since 	1.7.0
							 */
							$extracosts = array();
							if (isset($order['roominfo'][($num_room - 1)]) && isset($order['roominfo'][($num_room - 1)]['extracosts']) && is_array($order['roominfo'][($num_room - 1)]['extracosts'])) {
								foreach ($order['roominfo'][($num_room - 1)]['extracosts'] as $ec) {
									$ecdata = new stdClass;
									$ecdata->name = $ec['name'];
									$ecdata->cost = (float)$ec['cost'];
									$ecdata->idtax = '';
									if ($force_taxes) {
										$ecdata->cost = VikBooking::sayOptionalsPlusIva($ecdata->cost, $default_tax_rate, true);
										$ecdata->idtax = !empty($default_tax_rate) ? $default_tax_rate : $ecdata->idtax;
									}
									array_push($extracosts, $ecdata);
								}
							}

							// insert object in "ordersrooms"
							$or_data = new stdClass;
							$or_data->idorder = (int)$vbo_order_info['id'];
							$or_data->idroom = (int)$idroomvb[($num_room - 1)];
							$or_data->adults = (int)$rooms_aduchild[$num_room]['adults'];
							$or_data->children = (int)$rooms_aduchild[$num_room]['children'];
							$or_data->t_first_name = $room_t_first_name;
							$or_data->t_last_name = $room_t_last_name;
							if (count($room_indexes)) {
								$or_data->roomindex = (int)$room_indexes[$use_ind_key];
							}
							if ($this->setCommissions()) {
								$or_data->cust_cost = $now_room_cost;
								$or_data->cust_idiva = $default_tax_rate;
							}
							if (count($extracosts) && $otarplan_supported) {
								$or_data->extracosts = json_encode($extracosts);
							}
							if ($otarplan_supported) {
								$or_data->otarplan = $room_otarplan;
							}
							$dbo->insertObject('#__vikbooking_ordersrooms', $or_data, 'id');

							// assign room specific unit
							if (count($room_indexes)) {
								$room_indexes_usemap[$idroomvb[($num_room - 1)]]++;
							}
						}

						/**
						 * Check if the ota type data should be updated as well.
						 * 
						 * @since 	1.8.0
						 */
						$ota_type_data = null;
						if ($this->isBookingTypeSupported() && !empty($order['info']['thread_id'])) {
							$ota_type_data = !empty($vbo_order_info['ota_type_data']) ? json_decode($vbo_order_info['ota_type_data'], true) : array();
							$ota_type_data = !is_array($ota_type_data) ? array() : $ota_type_data;
							$ota_type_data['thread_id'] = $order['info']['thread_id'];
							$ota_type_data = json_encode($ota_type_data);
						}

						// update booking record
						$q = "UPDATE `#__vikbooking_orders` SET " .
							"`custdata`=".$dbo->quote($customerinfo)."," .
							"`ts`='".time()."'," .
							"`status`='confirmed'," .
							"`days`='".$numnights."'," .
							"`checkin`='".$checkints."'," .
							"`checkout`='".$checkoutts."'," .
							"`custmail`=".$dbo->quote($purchaseremail)."," .
							"`roomsnum`=".$num_rooms."," .
							"`total`=".$dbo->quote($total)."," .
							($tot_taxes > 0 ? "`tot_taxes`=".$dbo->quote($tot_taxes)."," : '') .
							($tot_city_taxes > 0 ? "`tot_city_taxes`=".$dbo->quote($tot_city_taxes)."," : '') .
							"`idorderota`=".$dbo->quote($order['info']['idorderota'])."," .
							(!empty($ota_type_data) ? "`ota_type_data`=" . $dbo->quote($ota_type_data) . "," : '') .
							"`channel`=".$dbo->quote($this->config['channel']['name'].'_'.$order['info']['source'])."," .
							"`chcurrency`=".$dbo->quote($order['info']['currency'])." " .
							"WHERE `id`=".(int)$vbo_order_info['id'].";";

						/**
						 * Fallback to avoid queries to fail because of unsupported encoding of $customerinfo.
						 * It has happened that some bookings could not be saved because of Emoji characters causing
						 * an SQL error 1366 (Incorrect string value \xF0\x9F\x99\x82.\xE2). The database character set
						 * and collate should be set to utf8mb4 in order to support special characters such as Emoji.
						 * 
						 * @since 1.6.13
						 */
						$upd_affect = 0;
						try {
							$dbo->setQuery($q);
							$dbo->execute();
							$upd_affect = $dbo->getAffectedRows();
						} catch (Exception $e) {
							$upd_affect = 0;
						}
						if (empty($upd_affect)) {
							// we try to update the booking with no customer information, as for sure that's the value that caused the error
							$q = "UPDATE `#__vikbooking_orders` SET " .
								"`ts`='".time()."'," .
								"`status`='confirmed'," .
								"`days`='".$numnights."'," .
								"`checkin`='".$checkints."'," .
								"`checkout`='".$checkoutts."'," .
								"`custmail`=".$dbo->quote($purchaseremail)."," .
								"`roomsnum`=".$num_rooms."," .
								"`total`=".$dbo->quote($total)."," .
								($tot_taxes > 0 ? "`tot_taxes`=".$dbo->quote($tot_taxes)."," : '') .
								($tot_city_taxes > 0 ? "`tot_city_taxes`=".$dbo->quote($tot_city_taxes)."," : '') .
								"`idorderota`=".$dbo->quote($order['info']['idorderota'])."," .
								(!empty($ota_type_data) ? "`ota_type_data`=" . $dbo->quote($ota_type_data) . "," : '') .
								"`channel`=".$dbo->quote($this->config['channel']['name'].'_'.$order['info']['source'])."," .
								"`chcurrency`=".$dbo->quote($order['info']['currency'])." " .
								"WHERE `id`=".(int)$vbo_order_info['id'].";";
							$dbo->setQuery($q);
							$dbo->execute();
							$upd_affect = $dbo->getAffectedRows();
						}

						// save/update customer (VikBooking 1.6 or higher)
						if (!empty($traveler_first_name) && !empty($traveler_last_name) && !empty($purchaseremail)) {
							try {
								if (!class_exists('VikBookingCustomersPin')) {
									require_once(VBO_SITE_PATH . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "cpin.php");
								}
								$cpin = new VikBookingCustomersPin();
								/**
								 * Customer Extra Info such as address, city, zip, company, vat.
								 * 
								 * @since 	1.6.12
								 * @since 	1.8.6 	added detection for VBO to support customer profile picture (avatar).
								 */
								if (!method_exists($cpin, 'supportsProfileAvatar') && isset($customer_extra_info['pic'])) {
									unset($customer_extra_info['pic']);
								}
								$cpin->setCustomerExtraInfo($customer_extra_info);
								//
								$cpin->saveCustomerDetails($traveler_first_name, $traveler_last_name, $purchaseremail, $phone, $country, array());
								$cpin->saveCustomerBooking($vbo_order_info['id']);
							} catch (Exception $e) {
								// do nothing
							}
						}

						/**
						 * Take care of eventually shared calendars for the rooms involved.
						 * 
						 * @since 	1.7.1
						 */
						$this->updateSharedCalendars($vbo_order_info['id'], true);
						//

						// compose notification detail message
						$notifymess = "OTA Booking ID: ".$order['info']['idorderota']."\n";
						if ($has_different_checkins_notif === false) {
							// only if the check-in and check-out are the same for each room
							$notifymess .= "Check-in: ".$order['info']['checkin']." (Before Modification: ".date('Y-m-d', $vbo_order_info['checkin']).")\n";
							$notifymess .= "Check-out: ".$order['info']['checkout']." (Before Modification: ".date('Y-m-d', $vbo_order_info['checkout']).")\n";
						}
						$oldroomdata = "";
						if (array_key_exists('rooms_info', $vbo_order_info) && count($vbo_order_info['rooms_info']) > 0) {
							$prev_adults = 0;
							$prev_children = 0;
							$prev_rooms = array();
							foreach ($vbo_order_info['rooms_info'] as $room_info) {
								$prev_adults += $room_info['adults'];
								$prev_children += $room_info['children'];
								$prev_rooms[] = $room_info['roomnamevb'];
							}
							$oldroomdata = " (Before Modification: ".implode(", ", $prev_rooms)." - Adults: ".$prev_adults.($prev_children > 0 ? " - Children: ".$prev_children : "").")";
						}
						$all_vb_room_names = array();
						foreach ($this->roomsinfomap as $idrota => $room_det) {
							$all_vb_room_names[] = $room_det['roomnamevb'];
						}
						if ($has_different_checkins_notif === false) {
							$notifymess .= "Room: ".implode(', ', $all_vb_room_names)." - Adults: ".$adults.($children > 0 ? " - Children: ".$children : "").$oldroomdata."\n";
						} else {
							// only if the check-in and check-out are different for some rooms (Booking.com)
							$notifymess .= "Rooms:\n".implode("\n", $has_different_checkins_notif)."\n".ltrim($oldroomdata);
						}
						// decode credit card details
						$order['info']['credit_card'] = $this->processCreditCardDetails($order);
						$notification_extra = '';
						$price_breakdown = '';
						// price breakdown
						if (isset($order['info']['price_breakdown']) && count($order['info']['price_breakdown'])) {
							$price_breakdown .= "\nPrice Breakdown:\n";
							foreach ($order['info']['price_breakdown'] as $day => $cost) {
								$price_breakdown .= $day." - ".$order['info']['currency'].' '.$cost."\n";
							}
							$price_breakdown = rtrim($price_breakdown, "\n");
						}

						$payment_log = '';
						if (count($order['info']['credit_card']) > 0) {
							$notification_extra .= "\nCredit Card:\n";
							foreach ($order['info']['credit_card'] as $card_info => $card_data) {
								if ($card_info == 'card_number_pci') {
									//do not touch this part or you will lose any PCI-compliant function
									continue;
								}
								if (is_array($card_data)) {
									$notification_extra .= ucwords(str_replace('_', ' ', $card_info)).":\n";
									foreach ($card_data as $card_info_in => $card_data_in) {
										$notification_extra .= ucwords(str_replace('_', ' ', $card_info_in)).": ".$card_data_in."\n";
									}
								} else {
									$notification_extra .= ucwords(str_replace('_', ' ', $card_info)).": ".$card_data."\n";
								}
							}
							$payment_log = $notification_extra."\n\n";
						}

						// update payment log with credit card details
						if (!empty($payment_log)) {
							$q = "UPDATE `#__vikbooking_orders` SET `paymentlog`=CONCAT(".$dbo->quote($payment_log).", `paymentlog`) WHERE `id`=".(int)$vbo_order_info['id'].";";
							$dbo->setQuery($q);
							$dbo->execute();
							$this->sendCreditCardDetails($order);
						}
						
						// VBO 1.10 or higher - Booking History
						if (method_exists('VikBooking', 'getBookingHistoryInstance')) {
							/**
							 * Before saving the regular history event about the booking modification, by letting VBO calculate the
							 * differences between the current and previous data, we store another event in the history in case the
							 * previous status of the reservation was "standby", because this was actually a booking confirmation.
							 * This is useful to understand when Webhook notifications come for acceptance of "RtB" or "Inquiries".
							 * 
							 * @since 	1.8.2
							 */
							if (!empty($vbo_order_info['status']) && $vbo_order_info['status'] == 'standby') {
								// store an extra event first
								VikBooking::getBookingHistoryInstance()->setBid($vbo_order_info['id'])->store('MC', JText::_('VCM_OTACONFRES_FROM_PENDING'));
							}

							VikBooking::getBookingHistoryInstance()->setBid($vbo_order_info['id'])->setPrevBooking($vbo_order_info)->store('MC');
						}

						$notifymess .= $price_breakdown.$notification_extra;

						$this->saveNotify('1', ucwords($this->config['channel']['name']), "e4j.OK.Channels.BookingModified\n".$notifymess, $vbo_order_info['id']);
						// add values to be returned as serialized to e4jConnect as response
						if (!isset($this->arrconfirmnumbers[$order['info']['idorderota']])) {
							$this->arrconfirmnumbers[$order['info']['idorderota']] = [];
						}
						$this->arrconfirmnumbers[$order['info']['idorderota']]['ordertype'] = 'Modify';
						$this->arrconfirmnumbers[$order['info']['idorderota']]['confirmnumber'] = $vbo_order_info['confirmnumber'].'mod';
						$this->arrconfirmnumbers[$order['info']['idorderota']]['vborderid'] = $vbo_order_info['id'];
						$this->arrconfirmnumbers[$order['info']['idorderota']]['nkey'] = $this->generateNKey($vbo_order_info['id']);
						
						// Notify AV=1-Channels for the booking modification
						$vcm = new SynchVikBooking($vbo_order_info['id'], array($this->config['channel']['uniquekey']));
						$vcm->setFromModification($vbo_order_info);
						$vcm->sendRequest();
						
						// SMS
						VikBooking::sendBookingSMS($vbo_order_info['id']);

						return true;
					} else {
						// the room results not available for modification in VikBooking, notify Administrator but return true anyways for e4jConnect
						// a notification will be saved also inside VCM
						$errmsg = $this->notifyAdministratorRoomNotAvailableModification($order, $vbo_order_info['id']);
						// VBO 1.10 or higher - Booking History
						if (method_exists('VikBooking', 'getBookingHistoryInstance')) {
							VikBooking::getBookingHistoryInstance()->setBid($vbo_order_info['id'])->store('MC', $errmsg);
						}
						$this->saveNotify('0', ucwords($this->config['channel']['name']), "e4j.error.Channels.BookingModification\n".$errmsg);
						// VCM 1.6.8 - ReservationsLogger should run even when the SynchVikBooking Class is not called
						VikChannelManager::getResLoggerInstance()
							->typeModification(true)
							->typeFromChannels(array($this->config['channel']['uniquekey']))
							->trackLog($order);
						//

						return true;
					}
				} else {
					$this->setError("2) modifyBooking: OTAid: ".$order['info']['idorderota']." empty stay dates");
				}
			} else {
				$this->setError("1) modifyBooking: OTAid: ".$order['info']['idorderota']." - OTARoom ".(is_array($check_idroomota) ? $check_idroomota[0] : $check_idroomota).", not mapped");
			}
		} else {
			// the booking to modify does not exist in VikBooking or was cancelled before, notify VCM administrator (only if not iCal/ICS)
			if ($order['info']['ordertype'] != 'Download') {
				$message = JText::sprintf('VCMOTAMODORDERNOTFOUND', ucwords($this->config['channel']['name']), $order['info']['idorderota'], (is_array($check_idroomota) ? $check_idroomota[0] : $check_idroomota));
				$vik = new VikApplication(VersionListener::getID());
				$admail = $this->config['emailadmin'];
				$adsendermail = VikChannelManager::getSenderMail();
				$vik->sendMail(
					$adsendermail,
					$adsendermail,
					$admail,
					$admail,
					JText::_('VCMOTAMODORDERNOTFOUNDSUBJ'),
					$message,
					false
				);
				// VCM 1.6.8 - ReservationsLogger should run even when the SynchVikBooking Class is not called
				VikChannelManager::getResLoggerInstance()
					->typeModification(true)
					->typeFromChannels(array($this->config['channel']['uniquekey']))
					->trackLog($order);
				//
			}
		}
		return false;
	}
	
	/**
	 * Cancels an OTA booking from VikBooking
	 * 
	 * @param 	array 	$order
	 * 
	 * @return 	boolean
	 */
	public function cancelBooking($order)
	{
		$dbo = JFactory::getDbo();
		if ($vbo_order_info = self::otaBookingExists($order['info']['idorderota'], true)) {
			$notifymess = "OTA Booking ID: ".$order['info']['idorderota']."\n";
			if (!empty($order['info']['checkin']) && !empty($order['info']['checkout'])) {
				$notifymess .= "Check-in: ".$order['info']['checkin']."\n";
				$notifymess .= "Check-out: ".$order['info']['checkout']."\n";
			}
			$q = "SELECT * FROM `#__vikbooking_ordersbusy` WHERE `idorder`=".(int)$vbo_order_info['id'].";";
			$dbo->setQuery($q);
			$dbo->execute();
			if ($dbo->getNumRows() > 0) {
				$ordbusy = $dbo->loadAssocList();
				foreach ($ordbusy as $ob) {
					$q = "DELETE FROM `#__vikbooking_busy` WHERE `id`=" . (int)$ob['idbusy'] . ";";
					$dbo->setQuery($q);
					$dbo->execute();
				}
			}
			// load room details
			$q = "SELECT `or`.`idroom`,`or`.`adults`,`or`.`children`,`r`.`name` FROM `#__vikbooking_ordersrooms` AS `or` LEFT JOIN `#__vikbooking_rooms` `r` ON `or`.`idroom`=`r`.`id` WHERE `or`.`idorder`=".(int)$vbo_order_info['id'].";";
			$dbo->setQuery($q);
			$dbo->execute();
			if ($dbo->getNumRows() > 0) {
				$orderrooms = $dbo->loadAssocList();
				foreach ($orderrooms as $or) {
					$notifymess .= "Room: ".$or['name']." - Adults: ".$or['adults'].($or['children'] > 0 ? " - Children: ".$or['children'] : "")."\n";
				}
			}
			$notifymess .= $vbo_order_info['custdata']."\n";
			$notifymess .= $vbo_order_info['custmail'];
			$q = "DELETE FROM `#__vikbooking_ordersbusy` WHERE `idorder`=".(int)$vbo_order_info['id'].";";
			$dbo->setQuery($q);
			$dbo->execute();
			$q = "UPDATE `#__vikbooking_orders` SET `status`='cancelled' WHERE `id`=".(int)$vbo_order_info['id'].";";
			$dbo->setQuery($q);
			$dbo->execute();

			// VBO 1.10 or higher - Booking History
			if (method_exists('VikBooking', 'getBookingHistoryInstance')) {
				/**
				 * Some channels may pass the cancellation reason that we use it as a description of the event.
				 * 
				 * @since 	1.6.22
				 */
				$canc_reason = isset($order['info']['canc_reason']) ? $order['info']['canc_reason'] : '';

				VikBooking::getBookingHistoryInstance()->setBid($vbo_order_info['id'])->store('CC', $canc_reason);
			}
			//

			/**
			 * Even the notifications of type booking cancellation should store the original VBO ID
			 * that was cancelled, to permit the search functions to find also such notifications.
			 * 
			 * @since 	1.8.1 	the fourth argument is being passed to the method.
			 */
			$this->saveNotify('1', ucwords($this->config['channel']['name']), "e4j.OK.Channels.BookingCancelled\n".$notifymess, $vbo_order_info['id']);

			// add values to be returned as serialized to e4jConnect as response
			if (!isset($this->arrconfirmnumbers[$order['info']['idorderota']])) {
				$this->arrconfirmnumbers[$order['info']['idorderota']] = [];
			}
			$this->arrconfirmnumbers[$order['info']['idorderota']]['ordertype'] = 'Cancel';
			$this->arrconfirmnumbers[$order['info']['idorderota']]['confirmnumber'] = $vbo_order_info['confirmnumber'].'canc';
			$this->arrconfirmnumbers[$order['info']['idorderota']]['vborderid'] = $vbo_order_info['id'];
			$this->arrconfirmnumbers[$order['info']['idorderota']]['nkey'] = $this->generateNKey($vbo_order_info['id']);
			//

			/**
			 * Even if this was a CancelRequest reservation for a pending booking, we can still trigger the sync of the availability.
			 * 
			 * @since 	1.8.0
			 */

			// notify av=1-channels for the booking cancellation
			$vcm = new SynchVikBooking($vbo_order_info['id'], array($this->config['channel']['uniquekey']));
			$vcm->setFromCancellation($vbo_order_info);
			$vcm->sendRequest();
			//

			// SMS
			VikBooking::sendBookingSMS($vbo_order_info['id']);
			//

			return true;
		} else {
			// the booking to cancel does not exist in VikBooking or was cancelled before, notify VCM administrator
			// do not notify admin if the status is already cancelled. This can happen in case of double booking cancel transmissions by e4jConnect
			$q = "SELECT * FROM `#__vikbooking_orders` WHERE `status`='cancelled' AND `idorderota`=" . $dbo->quote($order['info']['idorderota']) . " AND `channel` LIKE " . $dbo->quote($this->config['channel']['name'] . '%');
			$dbo->setQuery($q, 0, 1);
			$dbo->execute();
			if ($dbo->getNumRows() > 0) {
				$current_canc_booking = $dbo->loadAssoc();
				// attach rooms booked details
				$current_canc_booking['rooms_info'] = self::loadBookingRoomsData($current_canc_booking['id']);

				/**
				 * Rather than notifying the admin, we should make sure a record in
				 * the booking history section is present, so that we can later keep
				 * track of this OTA cancellation, which could have come after a manual
				 * cancellation made by the admin. These scenarios may cause later cases
				 * of overbooking, due to auto-replenishments by some channels.
				 * 
				 * @since 	1.8.3
				 */
				try {
					// whether this cancellation has triggered an availability sync
					$has_synced = false;
					// get history object
					$history_obj = VikBooking::getBookingHistoryInstance()->setBid($current_canc_booking['id']);
					if ($history_obj->hasEvent('CC') === false) {
						// store history record because the channel never cancelled this booking before
						$history_obj->store('CC');
						if (stripos($this->config['channel']['name'], 'booking') !== false) {
							// try to prevent a possible (future) overbooking by triggering a synchronization of the availability
							$vcm = new SynchVikBooking($current_canc_booking['id'], array($this->config['channel']['uniquekey']));
							$vcm->setFromCancellation($current_canc_booking);
							$vcm->sendRequest();
							// turn flag on
							$has_synced = true;
						}
					}
					if (!$has_synced) {
						/**
						 * Call the reservations logger no matter what, as this event
						 * must be tracked for logging purposes. If this cancellation
						 * had triggered a sync, then the class SynchVikBooking would
						 * have invoked the class VcmReservationsLogger.
						 */
						VikChannelManager::getResLoggerInstance()
							->typeCancellation(true)
							->typeFromChannels(array($this->config['channel']['uniquekey']))
							->trackLog($order);
					}
				} catch (Exception $e) {
					// do nothing
				}

				// return false and do not notify the admin as the booking is already cancelled
				return false;
			}
			
			// prepare message for the VCM administrator
			$all_ota_room_ids = array();
			if (isset($order['roominfo'])) {
				if (array_key_exists(0, $order['roominfo'])) {
					foreach ($order['roominfo'] as $rinfo) {
						$all_ota_room_ids[] = $rinfo['idroomota'];
					}
				} else {
					$all_ota_room_ids[] = $order['roominfo']['idroomota'];
				}
			}
			$message = JText::sprintf('VCMOTACANCORDERNOTFOUND', ucwords($this->config['channel']['name']), $order['info']['idorderota'], implode(', ', $all_ota_room_ids));
			$vik = new VikApplication(VersionListener::getID());
			$admail = $this->config['emailadmin'];
			$adsendermail = VikChannelManager::getSenderMail();
			$vik->sendMail(
				$adsendermail,
				$adsendermail,
				$admail,
				$admail,
				JText::_('VCMOTACANCORDERNOTFOUNDSUBJ'),
				$message,
				false
			);
			
			// VCM 1.6.8 - ReservationsLogger should run even when the SynchVikBooking Class is not called
			VikChannelManager::getResLoggerInstance()
				->typeCancellation(true)
				->typeFromChannels(array($this->config['channel']['uniquekey']))
				->trackLog($order);
		}
		
		return false;
	}
	
	/**
	 * Checks whether the downloaded booking was already processed.
	 * This function is used for parsing bookings that were originally in ICS format
	 * so a lot of them may have been downloaded already.
	 * 
	 * @param 	array 	$order 	the current booking information array
	 * 
	 * @return 	mixed 			false on error, storing result otherwise.
	 */
	public function downloadedBooking($order)
	{
		/**
		 * ICS bookings for rooms with multiple units.
		 * Check if the customer information is identical because the 'idorderota'
		 * may be a random value in AirBnB or similar channels for the same booking.
		 * 
		 * @since 	1.6.3
		 */
		$customer_data = '';
		foreach ($order['customerinfo'] as $what => $cinfo) {
			if ($what == 'pic') {
				// the customer profile picture will be saved onto the database
				continue;
			}
			$customer_data .= ucwords($what).": ".$cinfo."\n";
		}
		$customer_data = rtrim($customer_data, "\n");
		//

		if ($vbo_order_info = self::otaBookingExists($order['info']['idorderota'], true, true, $customer_data)) {
			/**
			 * We do not allow certain channels to modify iCal reservations, where Vik Booking is the "Master".
			 * 
			 * @since 	1.7.5
			 */
			$ical_deny_mod_list = array(VikChannelManagerConfig::CAMPSITESCOUK);
			foreach ($ical_deny_mod_list as $deny_ukey) {
				$ical_cur_info = VikChannelManager::getChannel($deny_ukey);
				if (!$ical_cur_info || !count($ical_cur_info)) {
					continue;
				}
				if (stripos($ical_cur_info['name'], $order['info']['source']) !== false) {
					// no booking modification allowed for this iCal channel
					return false;
				}
			}
			//
			
			// booking previously downloaded, yet allowed to come in: check if the dates have changed
			if ($vbo_order_info['status'] != 'cancelled' && (date('Y-m-d', $vbo_order_info['checkin']) != $order['info']['checkin'] || date('Y-m-d', $vbo_order_info['checkout']) != $order['info']['checkout'])) {
				// perform the booking modification
				return $this->modifyBooking($order);
			}
		} else {
			// the booking was never downloaded, save it onto VikBooking
			return $this->saveBooking($order);
		}
		
		return false;
	}

	/**
	 * Updates the internal iCal signature map for cancellations.
	 * The method may be called externally, so it has to be public.
	 * 
	 * @param 	array 	$map 	the ical signature map value.
	 * 
	 * @return 	mixed 	the new ical signature map property.
	 * 
	 * @since 	1.8.9
	 */
	public function setiCalSignatureMap($map = null)
	{
		if ($map) {
			$this->ical_signature_map = $map;
		}

		return $this->ical_signature_map;
	}

	/**
	 * Stores the newly downloaded iCal bookings (if any) and checks
	 * if some were cancelled, because no longer present in the list.
	 * The apposite configuration setting must be enabled. It is safe
	 * to call this method even for API channels, and nothing will happen.
	 * 
	 * @see 	the visibility of this method must be public as the main
	 * 			site controller may call it for a ping with no iCal bookings.
	 * 
	 * @return 	int
	 * 
	 * @since 	1.8.9
	 */
	public function iCalCheckNewCancellations()
	{
		if (!$this->iCalCancellationsAllowed()) {
			// iCal booking cancellations is disabled through the Configuration settings
			return 0;
		}

		$dbo = JFactory::getDbo();

		if (is_array($this->ical_signature_map)) {
			// store new iCal bookings just downloaded
			foreach ($this->ical_signature_map as $vbo_bid => $bid_data) {
				// build record object
				$record = new stdClass;
				$record->bid = (int)$vbo_bid;
				$record->rid = (int)$bid_data['room_id'];
				$record->uniquekey = (string)$bid_data['channel_id'];
				$record->ota_bid   = (string)$bid_data['ota_bid'];
				$record->signature = (string)$bid_data['signature'];

				try {
					$dbo->insertObject('#__vikchannelmanager_ical_bookings', $record, 'id');
				} catch (Exception $e) {
					// do nothing
				}
			}
		}

		// check calendars from the delivered bookings
		$cal_active_bookings = [];
		foreach ($this->arrbookings['orders'] as $order) {
			if (!is_array($order) || empty($order['info']) || empty($order['info']['ical_sign'])) {
				continue;
			}

			// the calendar "signature" always includes the room ID, and also the calendar ID for some channels
			$cal_identifier = $order['info']['ical_sign'];

			if (!isset($cal_active_bookings[$cal_identifier])) {
				// whenever we have a calendar/booking signature, start the container
				$cal_active_bookings[$cal_identifier] = [];
			}

			if (!empty($order['info']['idorderota'])) {
				// push the active booking re-transmitted for this calendar identifier
				$cal_active_bookings[$cal_identifier][] = $order['info']['idorderota'];
			}
		}

		if (!count($cal_active_bookings)) {
			// nothing to cancel or to compare against, unable to proceed
			return 0;
		}

		$bookings_cancelled = [];

		// get all iCal bookings previously stored from each calendar identifier (signature)
		foreach ($cal_active_bookings as $cal_identifier => $ota_bids) {
			// query the database to find all iCal bookings previously downloaded from this room-calendar
			$q = "SELECT `ib`.*, `o`.`status` FROM `#__vikchannelmanager_ical_bookings` AS `ib` 
				LEFT JOIN `#__vikbooking_orders` AS `o` ON `ib`.`bid`=`o`.`id` 
				WHERE `ib`.`uniquekey`=" . $dbo->quote($this->config['channel']['uniquekey']) . " 
				AND `ib`.`signature`=" . $dbo->quote($cal_identifier) . " 
				AND `o`.`status`='confirmed' 
				AND `o`.`checkout`>=" . time() . ";";
			$dbo->setQuery($q);
			$dbo->execute();
			if (!$dbo->getNumRows()) {
				// no iCal bookings ever downloaded from this calendar
				continue;
			}

			$prev_cal_bookings = $dbo->loadAssocList();

			// parse all iCal bookings previously downloaded from this calendar
			foreach ($prev_cal_bookings as $prev_booking) {
				if (in_array($prev_booking['ota_bid'], $ota_bids)) {
					// this booking is still in the calendar
					continue;
				}
				if ($this->deleteMissingiCalBooking($prev_booking)) {
					// cancellation was successful
					$bookings_cancelled[] = $prev_booking;
				}
			}
		}

		return count($bookings_cancelled);
	}

	/**
	 * Cancels a specific reservation that was previously downloaded via iCal.
	 * Triggers all the necessary update requests for all the other channels, if any.
	 * 
	 * @param 	array 	$booking 	the iCal booking record to cancel.
	 * 
	 * @return 	bool 				true on success, false otherwise.
	 * 
	 * @since 	1.8.9
	 */
	protected function deleteMissingiCalBooking($booking)
	{
		if (!is_array($booking) || empty($booking['bid'])) {
			return false;
		}

		$dbo = JFactory::getDbo();

		$notifymess = '';
		$notifymess .= "OTA Booking ID: " . $booking['ota_bid'] . "\n";
		$notifymess .= "iCal signature: " . $booking['signature'] . "\n";
		$notifymess .= "Reservation no longer present in iCal calendar for: " . $this->config['channel']['name'] . "\n";

		$q = "SELECT * FROM `#__vikbooking_ordersbusy` WHERE `idorder`=" . (int)$booking['bid'] . ";";
		$dbo->setQuery($q);
		$dbo->execute();
		if (!$dbo->getNumRows()) {
			return false;
		}
		$ordbusy = $dbo->loadAssocList();
		// free room up
		foreach ($ordbusy as $ob) {
			$q = "DELETE FROM `#__vikbooking_busy` WHERE `id`=" . (int)$ob['idbusy'] . ";";
			$dbo->setQuery($q);
			$dbo->execute();
		}

		// delete occupied record relations and update booking status
		$q = "DELETE FROM `#__vikbooking_ordersbusy` WHERE `idorder`=" . (int)$booking['bid'] . ";";
		$dbo->setQuery($q);
		$dbo->execute();
		$q = "UPDATE `#__vikbooking_orders` SET `status`='cancelled' WHERE `id`=" . (int)$booking['bid'] . ";";
		$dbo->setQuery($q);
		$dbo->execute();

		// booking history
		if (method_exists('VikBooking', 'getBookingHistoryInstance')) {
			VikBooking::getBookingHistoryInstance()->setBid($booking['bid'])->store('CC', 'iCal ' . $booking['signature']);
		}

		/**
		 * Even the notifications of type booking cancellation should store the original VBO ID
		 * that was cancelled, to permit the search functions to find also such notifications.
		 */
		$this->saveNotify('1', ucwords($this->config['channel']['name']), "e4j.OK.Channels.BookingCancelled\n" . $notifymess, $booking['bid']);

		// notify av=1-channels for the booking cancellation
		$booking['id'] = $booking['bid'];
		$vcm = new SynchVikBooking($booking['bid'], array($this->config['channel']['uniquekey']));
		$vcm->setFromCancellation($booking);
		$vcm->sendRequest();

		return true;
	}

	/**
	 * Tells whether the iCal cancellations are enabled.
	 * 
	 * @return 	bool 	false by default.
	 * 
	 * @since 	1.8.9
	 */
	protected function iCalCancellationsAllowed()
	{
		$dbo = JFactory::getDbo();

		$dbo->setQuery("SELECT `setting` FROM `#__vikchannelmanager_config` WHERE `param`='ical_cancellations'", 0, 1);
		$dbo->execute();

		if (!$dbo->getNumRows()) {
			// store the record, but return false as default setting
			$dbo->setQuery("INSERT INTO `#__vikchannelmanager_config` (`param`, `setting`) VALUES ('ical_cancellations', '0');");
			$dbo->execute();
			return false;
		}

		$config_val = (int)$dbo->loadResult();

		return (bool)($config_val > 0);
	}

	/**
	 * Loads a list of records for the rooms booked and assigned to this
	 * booking. This is to have a unique method to obtain the necessary
	 * records and values.
	 * 
	 * @param 	int 	$bid 	the ID of the VBO reservation record.
	 * 
	 * @return 	array 	list of associative array records or empty array.
	 * 
	 * @since 	1.8.3
	 * 
	 * @see 	this is a static method because it can be accessed also
	 * 			from a static context by otaBookingExists().
	 */
	protected static function loadBookingRoomsData($bid)
	{
		$dbo = JFactory::getDbo();

		$q = "SELECT `or`.`idroom`,`or`.`adults`,`or`.`children`,`or`.`idtar`,`or`.`optionals`,`or`.`childrenage`,`or`.`t_first_name`,`or`.`t_last_name`,`r`.`name` AS `roomnamevb`,`r`.`units` FROM `#__vikbooking_ordersrooms` AS `or` LEFT JOIN `#__vikbooking_rooms` `r` ON `or`.`idroom`=`r`.`id` WHERE `or`.`idorder`=" . (int)$bid . ";";

		$dbo->setQuery($q);
		$dbo->execute();
		if (!$dbo->getNumRows()) {
			return array();
		}

		return $dbo->loadAssocList();
	}

	/**
	 * Decodes the credit card details and returns an array with
	 * PCI-compliant values that can be stored in the database
	 * 
	 * @param 	array	$order
	 * 
	 * @return 	array
	 */
	private function processCreditCardDetails($order)
	{
		$credit_card = array();
		if (!empty($order['info']['credit_card'])) {
			$decoded_card = $this->cypher->decrypt($order['info']['credit_card']);
			$decoded_card = unserialize($decoded_card);

			/**
			 * VCM 1.6.9 - attempt to urldecode the encrypted data
			 */
			if ($decoded_card === false) {
				$decoded_card = $this->cypher->decrypt(urldecode($order['info']['credit_card']));
				$decoded_card = unserialize($decoded_card);
			}
			//

			if ($decoded_card !== false && is_array($decoded_card)) {
				if (strpos($decoded_card['card_number'], '*') === false) {
					//Mask credit card if not masked already
					$cc = str_replace(' ', '', trim($decoded_card['card_number']));
					$cc_num_len = strlen($cc);
					$cc_hidden = '';
					$cc_pci = '';
					if ($cc_num_len == 14) {
						// Diners Club
						$cc_hidden .= substr($cc, 0, 4)." **** **** **";
						$app = "****".substr($cc, 4, 10);
						for ($i = 1; $i <= $cc_num_len; $i++) {
							$cc_pci .= $app[$i-1].($i%4 == 0 ? ' ':'');
						}
					} elseif ($cc_num_len == 15) {
						// American Express
						$cc_hidden .= "**** ****** ".substr($cc, 10, 5);
						$app = substr($cc, 0, 10)."*****";
						for ($i = 1; $i <= $cc_num_len; $i++) {
							$cc_pci .= $app[$i-1].($i==4 || $i==10 ? ' ':'');
						}
					} else {
						// Master Card, Visa, Discover, JCB
						$cc_hidden .= "**** **** **** ".substr($cc, 12, 4);
						$app = substr($cc, 0, 12)."****";
						for ($i = 1; $i <= $cc_num_len; $i++) {
							$cc_pci .= $app[$i-1].($i%4 == 0 ? ' ':'');
						}
					}
					$decoded_card['card_number'] = $cc_hidden;
					$decoded_card['card_number_pci'] = $cc_pci;
					//
				}
				$credit_card = $decoded_card;
			}
		}
		
		return $credit_card;
	}
	
	/**
	 * Sends via email to the administrator email address
	 * the PCI-compliant and remaining number of the 
	 * credit card returned by the channel
	 * 
	 * @param 	array	$order
	 */
	private function sendCreditCardDetails($order)
	{
		if (!array_key_exists('card_number_pci', $order['info']['credit_card'])) {
			return false;
		}
		$vik = new VikApplication(VersionListener::getID());
		$admail = $this->config['emailadmin'];
		$adsendermail = VikChannelManager::getSenderMail();
		$vik->sendMail(
			$adsendermail,
			$adsendermail,
			$admail,
			$admail,
			JText::_('VCMCHANNELNEWORDERMAILSUBJECT'),
			JText::sprintf('VCMCHANNELNEWORDERMAILCONTENT', $order['info']['idorderota'], ucwords($this->config['channel']['name']), $order['info']['credit_card']['card_number_pci'], (isset($order['order_link']) ? $order['order_link'] : '')),
			false
		);
		return true;
	}
	
	/**
	 * Saves the new order from the OTA in the DB tables of VikBooking.
	 * 
	 * @param 	array 	$order
	 * @param 	mixed	$idroomvb
	 * @param 	int		$checkints
	 * @param 	int		$checkoutts
	 * @param 	int		$numnights
	 * @param 	int		$adults
	 * @param 	int		$children
	 * @param 	float	$total
	 * @param 	string	$customerinfo
	 * @param 	string	$purchaseremail
	 * 
	 * @return 	array
	 */
	public function saveNewVikBookingOrder($order, $idroomvb, $checkints, $checkoutts, $numnights, $adults, $children, $total, $customerinfo, $purchaseremail)
	{
		$dbo = JFactory::getDbo();
		
		// default number of adults
		if ((int)$adults == 0 && (int)$children == 0 && !is_array($idroomvb)) {
			$q = "SELECT `fromadult` FROM `#__vikbooking_rooms` WHERE `id`=".(int)$idroomvb.";";
			$dbo->setQuery($q);
			$dbo->execute();
			if ($dbo->getNumRows() == 1) {
				$num_adults = $dbo->loadResult();
				if (intval($num_adults) > 0) {
					$adults = (int)$num_adults;
				}
			}
		}

		$orderinfo = $order['info'];
		$tot_taxes = 0;
		if (!empty($orderinfo['tax']) && floatval($orderinfo['tax']) > 0) {
			$tot_taxes = floatval($orderinfo['tax']);
		}
		
		/**
		 * Total city taxes can be collected from booking information.
		 * 
		 * @since 	1.8.0
		 */
		$tot_city_taxes = 0;
		if (!empty($orderinfo['city_tax']) && floatval($orderinfo['city_tax']) > 0) {
			$tot_city_taxes = floatval($orderinfo['city_tax']);
		}
		
		// compose payment log
		$payment_log = '';
		if (count($order['info']['credit_card']) > 0) {
			$payment_log .= "Credit Card Details:\n";
			foreach ($order['info']['credit_card'] as $card_info => $card_data) {
				if ($card_info == 'card_number_pci') {
					//do not touch this part or you will lose any PCI-compliance function
					continue;
				}
				if (is_array($card_data)) {
					$payment_log .= ucwords(str_replace('_', ' ', $card_info)).":\n";
					foreach ($card_data as $card_info_in => $card_data_in) {
						$payment_log .= ucwords(str_replace('_', ' ', $card_info_in)).": ".$card_data_in."\n";
					}
				} else {
					$payment_log .= ucwords(str_replace('_', ' ', $card_info)).": ".$card_data."\n";
				}
			}
			$payment_log = rtrim($payment_log, "\n");
		}
		
		// always set $idroomvb to an array even if it is just a string
		$orig_idroomvb = $idroomvb;
		unset($idroomvb);
		if (is_array($orig_idroomvb)) {
			$idroomvb = array_values($orig_idroomvb);
		} else {
			$idroomvb = array($orig_idroomvb);
		}

		// Phone Number and Customers Management (VikBooking 1.6 or higher, check if cpin.php exists - since v1.6)
		$phone = '';
		if (isset($order['customerinfo']) && !empty($order['customerinfo']['telephone'])) {
			$phone = $order['customerinfo']['telephone'];
		}

		// country
		$country = '';
		if (isset($order['customerinfo']) && !empty($order['customerinfo']['country'])) {
			if (strlen($order['customerinfo']['country']) == 3) {
				$country = $order['customerinfo']['country'];
			} elseif (strlen($order['customerinfo']['country']) == 2) {
				$q = "SELECT `country_3_code` FROM `#__vikbooking_countries` WHERE `country_2_code`=".$dbo->quote($order['customerinfo']['country']).";";
				$dbo->setQuery($q);
				$dbo->execute();
				if ($dbo->getNumRows() == 1) {
					$country = $dbo->loadResult();
				}
			} elseif (strlen($order['customerinfo']['country']) > 3) {
				$q = "SELECT `country_3_code` FROM `#__vikbooking_countries` WHERE `country_name` LIKE ".$dbo->quote('%'.$order['customerinfo']['country'].'%').";";
				$dbo->setQuery($q);
				$dbo->execute();
				if ($dbo->getNumRows() > 0) {
					$country = $dbo->loadResult();
				}
			}
		}

		/**
		 * We need to format the phone number by prepending the country prefix if this is missing.
		 * 
		 * @since 	1.6.18
		 */
		if (!empty($phone) && !empty($country)) {
			// do not trim completely as the plus symbol may be a leading white-space
			$phone = rtrim($phone);

			if (substr($phone, 0, 1) == ' ' && strlen($phone) > 5) {
				/**
				 * Phone numbers inclusive of prefix with the plus symbol may be delivered by e4jConnect as a leading white space.
				 * The plus symbol gets printed as a white-space, and so this is what VCM gets. We should only right-trim until now.
				 * In these cases we apply the left trim to complete the trimming, then we prepend the plus symbol so that the phone
				 * number returned by the OTAs won't be touched as it's probably complete and inclusive of country prefix.
				 * 
				 * @since 	1.7.2
				 */
				$phone = ltrim($phone);
				$phone = '+' . $phone;
			}

			if (substr($phone, 0, 1) != '+' && substr($phone, 0, 2) != '00') {
				// try to find the country phone prefix since it's missing in the number
				$q = "SELECT `phone_prefix` FROM `#__vikbooking_countries` WHERE `country_" . (strlen($country) == 2 ? '2' : '3') . "_code`=" . $dbo->quote($country) . ";";
				$dbo->setQuery($q);
				$dbo->execute();
				if ($dbo->getNumRows()) {
					$country_prefix = str_replace(' ', '', $dbo->loadResult());
					$num_prefix = str_replace('+', '', $country_prefix);
					if (substr($phone, 0, strlen($num_prefix)) != $num_prefix) {
						// country prefix is completely missing
						$phone = $country_prefix . $phone;
					} else {
						// try to prepend the plus symbol because the phone number starts with the country prefix
						$phone = '+' . $phone;
					}
				}
			}
		}

		/**
		 * The status of new bookings can be pending.
		 * 
		 * @since 	1.8.0
		 */
		$new_book_status = $this->pending_booking ? 'standby' : 'confirmed';
		//
		
		/**
		 * Customer Extra Info such as address, city, zip, company, vat are
		 * stored into an array that will be passed onto VikBookingCustomersPin.
		 * 
		 * @since 	1.6.12
		 * @since 	1.8.6 	added support for customer profile picture (avatar).
		 */
		$extra_info_keys = [
			'address',
			'city',
			'zip',
			'company',
			'vat',
			'pic',
		];
		$customer_extra_info = [];
		foreach ($extra_info_keys as $extra_key) {
			if (isset($order['customerinfo']) && !empty($order['customerinfo'][$extra_key])) {
				$customer_extra_info[$extra_key] = $order['customerinfo'][$extra_key];
			}
		}

		// nominative
		$traveler_first_name = array_key_exists('traveler_first_name', $orderinfo) ? $orderinfo['traveler_first_name'] : '';
		$traveler_last_name = array_key_exists('traveler_last_name', $orderinfo) ? $orderinfo['traveler_last_name'] : '';

		// number of rooms
		$num_rooms = 1;
		if (array_key_exists('num_rooms', $order['info']) && intval($order['info']['num_rooms']) > 1) {
			$num_rooms = intval($order['info']['num_rooms']);
		}

		// store busy records, unless the booking is pending
		$busy_ids = array();
		for ($i = 1; $i <= $num_rooms; $i++) {
			if ($this->pending_booking) {
				// assign an empty value for the busy record which does not need to be created
				$busy_ids[$i] = 0;
			} else {
				$q = "INSERT INTO `#__vikbooking_busy` (`idroom`,`checkin`,`checkout`,`realback`) VALUES('" . $idroomvb[($i - 1)] . "', '" . $checkints . "', '" . $checkoutts . "','" . $checkoutts . "');";
				$dbo->setQuery($q);
				$dbo->execute();
				$busyid = $dbo->insertid();
				$busy_ids[$i] = $busyid;
			}
		}

		/**
		 * Default language for the reservations. Useful for cron jobs and later communications.
		 * This avoids to rely on the website's main language for back-end or front-end.
		 * 
		 * @since 	1.6.8
		 */
		$default_lang = VikChannelManager::getDefaultLanguage();

		/**
		 * We now accept the language to assign to the booking (if available), or we use a
		 * technique to determine the best available language to use according to the country.
		 * 
		 * @since 	1.8.3
		 */
		$guest_locale  = isset($order['customerinfo']) && !empty($order['customerinfo']['locale']) ? $order['customerinfo']['locale'] : null;
		$best_language = VikChannelManager::guessBookingLangFromCountry($country, $guest_locale);
		if (!empty($best_language)) {
			// override the default language to assign to the booking
			$default_lang = $best_language;
		}

		// build reservation record
		$res_record = new stdClass;
		$res_record->custdata = $customerinfo;
		$res_record->ts = time();
		$res_record->status = $new_book_status;
		$res_record->days = $numnights;
		$res_record->checkin = $checkints;
		$res_record->checkout = $checkoutts;
		$res_record->custmail = $purchaseremail;
		$res_record->sid = '';
		$res_record->totpaid = (isset($orderinfo['total_paid']) ? (float)$orderinfo['total_paid'] : 0);
		$res_record->ujid = 0;
		$res_record->coupon = '';
		$res_record->roomsnum = $num_rooms;
		$res_record->total = $total;
		$res_record->idorderota = strlen($orderinfo['idorderota']) > 128 ? substr($orderinfo['idorderota'], 0, 128) : $orderinfo['idorderota'];
		$res_record->channel = $this->config['channel']['name'] . '_' . $orderinfo['source'];
		$res_record->chcurrency = (!empty($orderinfo['currency']) ? $orderinfo['currency'] : null);
		$res_record->paymentlog = $payment_log;
		$res_record->lang = (!empty($default_lang) ? $default_lang : null);
		$res_record->country = (!empty($country) ? $country : null);
		$res_record->tot_taxes = $tot_taxes;
		$res_record->tot_city_taxes = $tot_city_taxes;
		if (!empty($phone)) {
			$res_record->phone = $phone;
		}
		if ($this->setCommissions()) {
			$res_record->cmms = isset($order['info']['commission_amount']) ? $order['info']['commission_amount'] : null;
		}
		if ($this->isBookingTypeSupported() && !empty($this->booking_type)) {
			$res_record->type = $this->booking_type;
			if (!empty($orderinfo['thread_id'])) {
				$ota_type_data = array(
					'thread_id' => $orderinfo['thread_id'],
				);
				$res_record->ota_type_data = json_encode($ota_type_data);
			}
		}

		/**
		 * Fallback to avoid queries to fail because of unsupported encoding of $customerinfo.
		 * It has happened that some bookings could not be saved because of Emoji characters causing
		 * an SQL error 1366 (Incorrect string value \xF0\x9F\x99\x82.\xE2). The database character set
		 * and collate should be set to utf8mb4 in order to support special characters such as Emoji.
		 * 
		 * @since 	1.6.13
		 * @since 	1.8.0 	we build an object-query rather than a string-query.
		 */
		$neworderid = 0;
		try {
			if (!$dbo->insertObject('#__vikbooking_orders', $res_record, 'id')) {
				$neworderid = 0;
			} else {
				$neworderid = $res_record->id;
			}
		} catch (Exception $e) {
			// make sure the process is not broken by a possible uncaught exception
			$neworderid = 0;
		}
		if (empty($neworderid)) {
			// we try to store the booking with no customer information, as for sure that's the value that caused the error
			$res_record->custdata = '...';
			if (!$dbo->insertObject('#__vikbooking_orders', $res_record, 'id')) {
				$neworderid = 0;
			} else {
				$neworderid = $res_record->id;
			}
		}

		// in case of pending reservation, room(s) should be set as temporarily locked
		if ($this->pending_booking) {
			for ($i = 1; $i <= $num_rooms; $i++) {
				$q = "INSERT INTO `#__vikbooking_tmplock` (`idroom`,`checkin`,`checkout`,`until`,`realback`,`idorder`) VALUES('" . $idroomvb[($i - 1)] . "'," . $dbo->quote($checkints) . "," . $dbo->quote($checkoutts) . ",'" . VikBooking::getMinutesLock(true) . "'," . $dbo->quote($checkoutts) . ", ".(int)$neworderid.");";
				$dbo->setQuery($q);
				$dbo->execute();
			}
		}

		/**
		 * Notify the administrator with the credit credit card details for PCI-compliance.
		 * The back-end link for the payments log differs between platforms
		 */
		if (defined('ABSPATH')) {
			$order['order_link'] = admin_url('admin.php?option=com_vikbooking&task=editorder&cid[]=' . $neworderid . '#paymentlog');
		} else {
			$order['order_link'] = JUri::root() . 'administrator/index.php?option=com_vikbooking&task=editorder&cid[]=' . $neworderid . '#paymentlog';
		}
		$this->sendCreditCardDetails($order);

		$confirmnumber = !$this->pending_booking ? $this->generateConfirmNumber($neworderid, true) : $neworderid;
		$rooms_aduchild = array();
		// Adults and Children are returned as total by the OTA. If multiple rooms, dispose the Adults and Children accordingly
		if ($num_rooms > 1) {
			$adults_per_room = floor($adults / $num_rooms);
			$adults_per_room = $adults_per_room < 0 ? 0 : $adults_per_room;
			$spare_adults = ($adults - ($adults_per_room * $num_rooms));
			$children_per_room = floor($children / $num_rooms);
			$children_per_room = $children_per_room < 0 ? 0 : $children_per_room;
			$spare_children = ($children - ($children_per_room * $num_rooms));
			for ($i = 1; $i <= $num_rooms; $i++) {
				$adults_occupancy = $adults_per_room;
				$children_occupancy = $children_per_room;
				if ($i == 1 && ($spare_adults > 0 || $spare_children > 0)) {
					$adults_occupancy += $spare_adults;
					$children_occupancy += $spare_children;
				}
				$rooms_aduchild[$i]['adults'] = $adults_occupancy;
				$rooms_aduchild[$i]['children'] = $children_occupancy;
			}
		} else {
			$rooms_aduchild[$num_rooms]['adults'] = $adults;
			$rooms_aduchild[$num_rooms]['children'] = $children;
		}

		// default Tax Rate (VCM 1.6.3)
		$default_tax_rate = 0;
		$q = "SELECT `id` FROM `#__vikbooking_iva` ORDER BY `id` ASC LIMIT 1;";
		$dbo->setQuery($q);
		$dbo->execute();
		if ($dbo->getNumRows() > 0) {
			$default_tax_rate = (int)$dbo->loadResult();
		}

		// assign room specific unit
		$set_room_indexes = (!$this->pending_booking && $this->autoRoomUnit());
		$room_indexes_usemap = array();

		foreach ($busy_ids as $num_room => $id_busy) {
			if (!empty($id_busy)) {
				$q = "INSERT INTO `#__vikbooking_ordersbusy` (`idorder`,`idbusy`) VALUES(".(int)$neworderid.", ".(int)$id_busy.");";
				$dbo->setQuery($q);
				$dbo->execute();
			}
			// traveler name for each room if available
			$room_t_first_name = $traveler_first_name;
			$room_t_last_name = $traveler_last_name;
			if (array_key_exists(($num_room - 1), $order['roominfo'])) {
				if (strlen($order['roominfo'][($num_room - 1)]['traveler_first_name'])) {
					$room_t_first_name = $order['roominfo'][($num_room - 1)]['traveler_first_name'];
					$room_t_last_name = $order['roominfo'][($num_room - 1)]['traveler_last_name'];
				}
			}

			// assign room specific unit
			$room_indexes = $set_room_indexes === true ? $this->getRoomUnitNumsAvailable(array('id' => $neworderid, 'checkin' => $checkints, 'checkout' => $checkoutts), $idroomvb[($num_room - 1)]) : array();
			$use_ind_key = 0;
			if (count($room_indexes)) {
				if (!array_key_exists($idroomvb[($num_room - 1)], $room_indexes_usemap)) {
					$room_indexes_usemap[$idroomvb[($num_room - 1)]] = $use_ind_key;
				} else {
					$use_ind_key = $room_indexes_usemap[$idroomvb[($num_room - 1)]];
				}
				$rooms[$num]['roomindex'] = (int)$room_indexes[$use_ind_key];
			}

			// children age
			$children_ages = array();
			if ($num_room <= 1 && array_key_exists('children_ages', $orderinfo) && is_array($orderinfo['children_ages'])) {
				$children_ages = array('age' => $orderinfo['children_ages']);
			}

			// OTA Rate Plan for this room booked
			$otarplan_supported = $this->otaRplanSupported();
			$room_otarplan = '';
			if (isset($order['roominfo'][($num_room - 1)]) && isset($order['roominfo'][($num_room - 1)]['rateplanid'])) {
				$room_otarplan = $order['roominfo'][($num_room - 1)]['rateplanid'];
			} elseif (isset($order['roominfo']['rateplanid'])) {
				$room_otarplan = $order['roominfo']['rateplanid'];
			}
			$room_otarplan = $this->getOtaRplanNameFromId($room_otarplan, (int)$idroomvb[($num_room - 1)]);

			/**
			 * Determine whether the application of taxes should be forced for some channels.
			 * 
			 * @since 	1.8.4
			 */
			$force_taxes = false;

			/**
			 * Set room exact cost (if available). Useful to print
			 * the cost of this room in case of multiple rooms booked.
			 * 
			 * @since 	1.6.13
			 */
			$now_room_cost = round(($total / $num_rooms), 2);
			if (isset($order['roominfo'][($num_room - 1)]) && isset($order['roominfo'][($num_room - 1)]['room_cost']) && floatval($order['roominfo'][($num_room - 1)]['room_cost']) > 0) {
				$now_room_cost = (float)$order['roominfo'][($num_room - 1)]['room_cost'];
				/**
				 * Hosts eligible for taxes working with Airbnb and using prices inclusive of tax
				 * in VBO may need to have the listing base cost inclusive of tax, as it's returned
				 * by the e4jConnect servers before taxes, due to the missing tax rate information
				 * and by other extra services that may still be subjected to tax.
				 * 
				 * @since 	1.8.4
				 */
				if (VikBooking::ivaInclusa() && $tot_taxes > 0 && $total > $now_room_cost && $this->config['channel']['uniquekey'] == VikChannelManagerConfig::AIRBNBAPI) {
					// room exact cost should be inclusive of taxes (force taxes to be applied)
					$now_room_cost = VikBooking::sayPackagePlusIva($now_room_cost, $default_tax_rate, true);
					// turn flag on
					$force_taxes = true;
				}
			}

			/**
			 * We try to get the exact number of adults and children from 'roominfo'
			 * because some channels may support this obvious information.
			 * 
			 * @since 	1.6.22
			 */
			if (isset($order['roominfo'][($num_room - 1)]) && isset($order['roominfo'][($num_room - 1)]['adults'])) {
				$rooms_aduchild[$num_room]['adults'] = $order['roominfo'][($num_room - 1)]['adults'];
			}
			if (isset($order['roominfo'][($num_room - 1)]) && isset($order['roominfo'][($num_room - 1)]['children'])) {
				$rooms_aduchild[$num_room]['children'] = $order['roominfo'][($num_room - 1)]['children'];
			}
			
			/**
			 * Extracosts for the reservation (AddOns) like Parking or Breakfast.
			 * VCM may get this information from some OTAs.
			 * 
			 * @since 	1.7.0
			 */
			$extracosts = array();
			if (isset($order['roominfo'][($num_room - 1)]) && isset($order['roominfo'][($num_room - 1)]['extracosts']) && is_array($order['roominfo'][($num_room - 1)]['extracosts'])) {
				foreach ($order['roominfo'][($num_room - 1)]['extracosts'] as $ec) {
					$ecdata = new stdClass;
					$ecdata->name = $ec['name'];
					$ecdata->cost = (float)$ec['cost'];
					$ecdata->idtax = '';
					if ($force_taxes) {
						$ecdata->cost = VikBooking::sayOptionalsPlusIva($ecdata->cost, $default_tax_rate, true);
						$ecdata->idtax = !empty($default_tax_rate) ? $default_tax_rate : $ecdata->idtax;
					}
					array_push($extracosts, $ecdata);
				}
			}

			// insert object in "ordersrooms"
			$or_data = new stdClass;
			$or_data->idorder = (int)$neworderid;
			$or_data->idroom = (int)$idroomvb[($num_room - 1)];
			$or_data->adults = (int)$rooms_aduchild[$num_room]['adults'];
			$or_data->children = (int)$rooms_aduchild[$num_room]['children'];
			$or_data->childrenage = (count($children_ages) ? json_encode($children_ages) : null);
			$or_data->t_first_name = $room_t_first_name;
			$or_data->t_last_name = $room_t_last_name;
			if (count($room_indexes)) {
				$or_data->roomindex = (int)$room_indexes[$use_ind_key];
			}
			if ($this->setCommissions()) {
				$or_data->cust_cost = $now_room_cost;
				$or_data->cust_idiva = $default_tax_rate;
			}
			if (count($extracosts) && $otarplan_supported) {
				$or_data->extracosts = json_encode($extracosts);
			}
			if ($otarplan_supported) {
				$or_data->otarplan = $room_otarplan;
			}
			$dbo->insertObject('#__vikbooking_ordersrooms', $or_data, 'id');
			
			// assign room specific unit
			if (count($room_indexes)) {
				$room_indexes_usemap[$idroomvb[($num_room - 1)]]++;
			}
		}

		$insertdata = array(
			'newvborderid' => $neworderid,
			'confirmnumber' => $confirmnumber,
		);

		// save customer (VikBooking 1.6 or higher)
		if (!empty($traveler_first_name) && !empty($traveler_last_name) && !empty($purchaseremail)) {
			try {
				if (!class_exists('VikBookingCustomersPin')) {
					require_once(VBO_SITE_PATH . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "cpin.php");
				}
				$cpin = new VikBookingCustomersPin();
				/**
				 * Customer Extra Info such as address, city, zip, company, vat.
				 * 
				 * @since 	1.6.12
				 * @since 	1.8.6 	added detection for VBO to support customer profile picture (avatar).
				 */
				if (!method_exists($cpin, 'supportsProfileAvatar') && isset($customer_extra_info['pic'])) {
					unset($customer_extra_info['pic']);
				}
				$cpin->setCustomerExtraInfo($customer_extra_info);
				//
				$cpin->saveCustomerDetails($traveler_first_name, $traveler_last_name, $purchaseremail, $phone, $country, array());
				$cpin->saveCustomerBooking($neworderid);
			} catch (Exception $e) {
				// do nothing
			}
		}

		/**
		 * Take care of eventually shared calendars for the rooms involved.
		 * 
		 * @since 	1.7.1
		 */
		if (!$this->pending_booking) {
			$this->updateSharedCalendars($neworderid);
		}

		// Booking History
		if (method_exists('VikBooking', 'getBookingHistoryInstance')) {
			VikBooking::getBookingHistoryInstance()->setBid($neworderid)->store('NO');
		}
		
		return $insertdata;
	}
	
	/**
	 * VikBooking v1.7 or higher.
	 * If the method exists, check whether the room specific unit should be assigned to the booking.
	 * 
	 * @return 	bool
	 */	
	private function autoRoomUnit()
	{
		if (method_exists('VikBooking', 'autoRoomUnit')) {
			return VikBooking::autoRoomUnit();
		}
		return false;
	}

	/**
	 * VikBooking v1.7 or higher.
	 * If the method exists, return the specific indexes available.
	 * 
	 * @param 	array	$order
	 * @param 	int 	$roomid
	 * 
	 * @return 	array
	 */	
	private function getRoomUnitNumsAvailable($order, $roomid)
	{
		if (method_exists('VikBooking', 'getRoomUnitNumsAvailable')) {
			return VikBooking::getRoomUnitNumsAvailable($order, $roomid);
		}
		return array();
	}

	/**
	 * VikBooking v1.7 or higher.
	 * The commissions amount is only supported by the v1.7 or higher.
	 * Check if a method of that version exists.
	 * 
	 * @return 	bool
	 */
	private function setCommissions()
	{
		return method_exists('VikBooking', 'autoRoomUnit');
	}

	/**
	 * VikBooking v1.10 or higher.
	 * The field 'otarplan' in '_ordersrooms' is only supported by the v1.10 or higher.
	 * Check if a method of that version exists.
	 * 
	 * @return 	bool
	 */
	private function otaRplanSupported()
	{
		return method_exists('VikBooking', 'getVcmChannelsLogo');
	}

	/**
	 * Generates a confirmation number for the order and returns it.
	 * It can also update the order record with it.
	 * 
	 * @param 	int 	$oid
	 * @param 	bool 	$update
	 * 
	 * @return 	string
	 */
	public function generateConfirmNumber($oid, $update = true)
	{
		$confirmnumb = date('ym');
		$confirmnumb .= (string)rand(100, 999);
		$confirmnumb .= (string)rand(10, 99);
		$confirmnumb .= (string)$oid;
		if ($update) {
			$dbo = JFactory::getDbo();
			$q = "UPDATE `#__vikbooking_orders` SET `confirmnumber`=".$dbo->quote($confirmnumb)." WHERE `id`=".(int)$oid.";";
			$dbo->setQuery($q);
			$dbo->execute();
		}
		return $confirmnumb;
	}
	
	/**
	 * Checks if the given OTA booking ID exists in VikBooking.
	 * Since VCM 1.6.8, this method is accessible in a static context
	 * because it's used also by the ReservationsLogger Class.
	 * The static var $channelName is only defined when accessing the
	 * method in object context, so during a BR_L event.
	 * If the method is called statically by another class, then the
	 * static var $channelName will be empty, but it's okay.
	 * 
	 * @param 	string 	$idorderota 	the OTA Booking ID.
	 * @param 	bool 	$retvbid 		return the existing ID or boolean.
	 * @param 	bool 	$cancelled 	 	exclude/include cancelled bookings.
	 * @param 	string 	$customer_data 	customer information string for iCal channels duplicates when multiple units.
	 * 
	 * @return 	mixed 					boolean/array
	 */
	public static function otaBookingExists($idorderota, $retvbid = false, $cancelled = false, $customer_data = null)
	{
		$dbo = JFactory::getDbo();
		if (!strlen($idorderota)) {
			return false;
		}

		/**
		 * For iCal bookings we need to make sure to properly check the exact
		 * customer name, in order to avoid detecting bookings made by guests
		 * with similar names. In this case, we expect to have "Reserved - Name".
		 * 
		 * @since 	1.8.7
		 */
		$customer_q_operator = 'LIKE';
		if (!empty($customer_data) && stripos($customer_data, 'Reserved - ')) {
			$customer_q_operator = '=';
		}

		$q = "SELECT * FROM `#__vikbooking_orders` WHERE ".(!$cancelled ? "`status`!='cancelled' AND " : "").($customer_data !== null ? "(`custdata` " . $customer_q_operator . " ".$dbo->quote('%'.$customer_data.'%')." OR `idorderota`=".$dbo->quote($idorderota).")" : "`idorderota`=".$dbo->quote($idorderota)).(!empty(self::$channelName) ? " AND `channel` LIKE '%".self::$channelName."%'" : "")." ORDER BY `id` DESC;";
		$dbo->setQuery($q);
		$dbo->execute();
		if (!$dbo->getNumRows()) {
			return false;
		}
		$fetch = $dbo->loadAssocList();
		if (!$retvbid) {
			return true;
		}
		// attach rooms booked data
		$fetch[0]['rooms_info'] = self::loadBookingRoomsData($fetch[0]['id']);

		return $fetch[0];
	}
	
	/**
	 * Maps the corresponding IdRoom in VikBooking to the IdRoomOta
	 * In case the room belongs to more than one room of VikBooking
	 * only the first active one is returned.
	 * It also stores some values in the class array roomsinfomap
	 * for later actions like room name, room total units.
	 * If the ID is negative then it's because the downloaded booking
	 * in ICS format is generic for the entire property. The absolute
	 * value of the number will be taken in that case.
	 * $idroomota could also be an array of room type id because some
	 * channels allow bookings of multiple rooms, different ones (Booking.com)
	 * 
	 * @param 	mixed 	$idroomota 		string idroomota, or array of strings idroomota
	 * 
	 * @return 	mixed 	string 			idroomvb or array idroomvb
	 */
	public function mapIdroomVbFromOtaId($idroomota)
	{
		$dbo = JFactory::getDbo();
		if (!is_array($idroomota) && intval($idroomota) < 0) {
			$pos_id = (int)abs((float)$idroomota);
			$q = "SELECT `id`,`name`,`units` FROM `#__vikbooking_rooms` WHERE `id`=".$pos_id.";";
			$dbo->setQuery($q);
			$dbo->execute();
			if ($dbo->getNumRows() > 0) {
				$assocs = $dbo->loadAssocList();
				$this->roomsinfomap[$idroomota]['idroomvb'] = $assocs[0]['id'];
				$this->roomsinfomap[$idroomota]['roomnamevb'] = $assocs[0]['name'];
				$this->roomsinfomap[$idroomota]['totunits'] = $assocs[0]['units'];
				return $assocs[0]['id'];
			}
		}
		if (!is_array($idroomota)) {
			$q = "SELECT `x`.`idroomvb`,`vbr`.`name`,`vbr`.`units` FROM `#__vikchannelmanager_roomsxref` AS `x` " .
				"LEFT JOIN `#__vikbooking_rooms` `vbr` ON `x`.`idroomvb`=`vbr`.`id` " .
				"WHERE `x`.`idroomota`=".$dbo->quote($idroomota)." AND `x`.`idchannel`='".$this->config['channel']['uniquekey']."' " .
				"ORDER BY `x`.`id` ASC;";
			$dbo->setQuery($q);
			$dbo->execute();
			if ($dbo->getNumRows() > 0) {
				$assocs = $dbo->loadAssocList();
				$this->roomsinfomap[$idroomota]['idroomvb'] = $assocs[0]['idroomvb'];
				$this->roomsinfomap[$idroomota]['roomnamevb'] = $assocs[0]['name'];
				$this->roomsinfomap[$idroomota]['totunits'] = $assocs[0]['units'];
				return $assocs[0]['idroomvb'];
			}
		} else {
			if (!(count($idroomota) > 0)) {
				return false;
			}
			$roomsota_count_map = array();
			$in_clause = array();
			foreach ($idroomota as $k => $v) {
				$in_clause[$k] = $dbo->quote($v);
				$roomsota_count_map[$v] = empty($roomsota_count_map[$v]) ? 1 : ($roomsota_count_map[$v] + 1);
			}
			//the old query was modified to be compatible with the sql strict mode
			/*
			$q = "SELECT `x`.`idroomvb`,`x`.`idroomota`,`vbr`.`name`,`vbr`.`units` FROM `#__vikchannelmanager_roomsxref` AS `x` " .
				"LEFT JOIN `#__vikbooking_rooms` `vbr` ON `x`.`idroomvb`=`vbr`.`id` " .
				"WHERE `x`.`idroomota` IN (".implode(', ', array_unique($in_clause)).") AND `x`.`idchannel`='".$this->config['channel']['uniquekey']."' " .
				"GROUP BY `x`.`idroomota` ORDER BY `x`.`id` ASC LIMIT ".count($in_clause).";";
			*/
			$q = "SELECT DISTINCT `x`.`idroomvb`,`x`.`idroomota`,`vbr`.`name`,`vbr`.`units` FROM `#__vikchannelmanager_roomsxref` AS `x` " .
				"LEFT JOIN `#__vikbooking_rooms` `vbr` ON `x`.`idroomvb`=`vbr`.`id` " .
				"WHERE `x`.`idroomota` IN (".implode(', ', array_unique($in_clause)).") AND `x`.`idchannel`='".$this->config['channel']['uniquekey']."' " .
				"ORDER BY `x`.`id` ASC LIMIT ".count($in_clause).";";
			$dbo->setQuery($q);
			$dbo->execute();
			if ($dbo->getNumRows() > 0) {
				$idroomvb = array();
				$assocs = $dbo->loadAssocList();
				//VCM 1.6.5 - Bookings for two or more equal Room IDs get an invalid count of the returned $idroomvb if we do not make the array unique
				$idroomota = array_unique($idroomota);
				//
				//VCM 1.6.4 - do not rely on the SQL result ordering (or some dates could be assigned to the invalid room), so compose the array with the right ordering as returned by e4jConnect ($idroomota)
				foreach ($idroomota as $k => $v) {
					foreach ($assocs as $rass) {
						if ($rass['idroomota'] != $v) {
							continue;
						}
						$idroomvb[] = $rass['idroomvb'];
						if ($roomsota_count_map[$rass['idroomota']] > 1) {
							for ($i = 1; $i < $roomsota_count_map[$rass['idroomota']]; $i++) {
								$idroomvb[] = $rass['idroomvb'];
							}
						}
						$this->roomsinfomap[$rass['idroomota']]['idroomvb'] = $rass['idroomvb'];
						$this->roomsinfomap[$rass['idroomota']]['roomnamevb'] = $rass['name'];
						$this->roomsinfomap[$rass['idroomota']]['totunits'] = $rass['units'];
					}
				}
				//
				return count($idroomvb) > 0 ? $idroomvb : false;
			}
		}

		return false;
	}

	/**
	 * Finds the mapping relations between the Room ID in VBO
	 * and the OTA Rate Plan ID stored in the channel manager.
	 * Returns the name of the corresponding Rate Plan.
	 * Needed by VBO 1.10 (or higher) to store this information.
	 * 
	 * @param 	string	$rplan_id
	 * @param 	int		$room_id
	 * 
	 * @return 	string
	 */
	private function getOtaRplanNameFromId($rplan_id, $room_id)
	{
		$dbo = JFactory::getDbo();
		$rplan_name = '';

		if (empty($rplan_id) || empty($room_id)) {
			return $rplan_name;
		}

		$q = "SELECT `x`.`idroomvb`,`x`.`otapricing` FROM `#__vikchannelmanager_roomsxref` AS `x` " .
				"WHERE `x`.`idroomvb`=".(int)$room_id." AND `x`.`idchannel`='".$this->config['channel']['uniquekey']."';";
		$dbo->setQuery($q);
		$dbo->execute();
		if ($dbo->getNumRows() > 0) {
			$rels = $dbo->loadAssocList();
			foreach ($rels as $k => $rp) {
				if (empty($rp['otapricing'])) {
					continue;
				}
				$otapricing = json_decode($rp['otapricing'], true);
				if (!is_array($otapricing) || !isset($otapricing['RatePlan'])) {
					continue;
				}
				foreach ($otapricing['RatePlan'] as $rpid => $orp) {
					if ((string)$rpid == (string)$rplan_id) {
						$rplan_name = $orp['name'];
						break 2;
					}
				}
			}
		}

		return $rplan_name;
	}
	
	/**
	 * Maps the corresponding Price in VikBooking to the OTA RatePlanID.
	 * 
	 * @param 	array	$order
	 * 
	 * @return 	mixed 	false or string
	 */
	public function mapPriceVbFromRatePlanId($order)
	{
		$dbo = JFactory::getDbo();
		if (array_key_exists(0, $order['roominfo'])) {
			//multiple rooms or channel supporting multiple rooms
			$idroomota = array();
			$idroomota_plain = array();
			$otarateplanid = array();
			foreach ($order['roominfo'] as $rk => $rinfo) {
				$idroomota[$rk] = $dbo->quote($rinfo['idroomota']);
				$idroomota_plain[$rk] = (string)$rinfo['idroomota'];
				$otarateplanid[$rk] = $rinfo['rateplanid'];
			}
			//the old query was modified to be compatible with the sql strict mode
			/*
			$q = "SELECT `x`.`idroomota`,`x`.`otapricing`,`vbr`.`name` FROM `#__vikchannelmanager_roomsxref` AS `x` " .
				"LEFT JOIN `#__vikbooking_rooms` `vbr` ON `x`.`idroomvb`=`vbr`.`id` " .
				"WHERE `x`.`idroomota` IN (".implode(',', $idroomota).") AND `x`.`idchannel`='".$this->config['channel']['uniquekey']."' " .
				"GROUP BY `x`.`idroomota` ORDER BY `x`.`id` ASC;";
			*/
			//we don't actually need to group anything here because the foreach loop has a break-state when the idroomota is found
			$q = "SELECT `x`.`idroomota`,`x`.`otapricing`,`vbr`.`name` FROM `#__vikchannelmanager_roomsxref` AS `x` " .
				"LEFT JOIN `#__vikbooking_rooms` `vbr` ON `x`.`idroomvb`=`vbr`.`id` " .
				"WHERE `x`.`idroomota` IN (".implode(',', $idroomota).") AND `x`.`idchannel`='".$this->config['channel']['uniquekey']."' " .
				"ORDER BY `x`.`id` ASC;";
			$dbo->setQuery($q);
			$dbo->execute();
			if ($dbo->getNumRows() > 0) {
				$assocs = $dbo->loadAssocList();
				$rateplan_info = array();
				foreach ($idroomota_plain as $kk => $rota_id) {
					foreach ($assocs as $k => $rp) {
						if ($rota_id == (string)$rp['idroomota']) {
							if (!empty($rp['otapricing'])) {
								$otapricing = json_decode($rp['otapricing'], true);
								if (!is_null($otapricing) && @count($otapricing) > 0 && @count($otapricing['RatePlan']) > 0) {
									foreach ($otapricing['RatePlan'] as $rpid => $orp) {
										if ((string)$rpid == (string)$otarateplanid[$kk]) {
											$rateplan_info[] = $orp['name'];
											break;
										}
									}
								}
							}
							break;
						}
					}
				}
				if (count($rateplan_info) > 0) {
					return 'RatePlan: '.implode(', ', $rateplan_info);
				}
			}
		} else {
			//single room
			$idroomota = $order['roominfo']['idroomota'];
			$otarateplanid = $order['roominfo']['rateplanid'];
			$q = "SELECT `x`.`otapricing`,`vbr`.`name` FROM `#__vikchannelmanager_roomsxref` AS `x` " .
				"LEFT JOIN `#__vikbooking_rooms` `vbr` ON `x`.`idroomvb`=`vbr`.`id` " .
				"WHERE `x`.`idroomota`=".$dbo->quote($idroomota)." AND `x`.`idchannel`='".$this->config['channel']['uniquekey']."' " .
				"ORDER BY `x`.`id` ASC;";
			$dbo->setQuery($q);
			$dbo->execute();
			if ($dbo->getNumRows() > 0) {
				$assocs = $dbo->loadAssocList();
				if (!empty($assocs[0]['otapricing'])) {
					$otapricing = json_decode($assocs[0]['otapricing'], true);
					if (!is_null($otapricing) && @count($otapricing) > 0 && @count($otapricing['RatePlan']) > 0) {
						foreach ($otapricing['RatePlan'] as $rpid => $rp) {
							if ((string)$rpid == (string)$otarateplanid) {
								return 'RatePlan: '.$rp['name'];
							}
						}
					}
				}
			}
		}
		return false;
	}
	
	/**
	 * Calculates and Returns the timestamp for the Checkin
	 * Adding the hours and minutes of the VikBooking
	 * Checkin time to the OTA Arrival Date.
	 * The method also sets the class variables
	 * vbCheckinSeconds and vbCheckoutSeconds.
	 * 
	 * @param 	string	$checkindate 	Y-m-d date string.
	 * 
	 * @return 	int
	 */
	public function getCheckinTimestamp($checkindate)
	{
		$timestamp = 0;
		$basets = strtotime($checkindate);
		$timestamp += $basets;
		if (strlen($this->vbCheckinSeconds) > 0) {
			$timestamp += $this->vbCheckinSeconds;
		} else {
			$timeopst = $this->getTimeOpenStore();
			if (is_array($timeopst)) {
				$opent = $timeopst[0];
				$closet = $timeopst[1];
			} else {
				$opent = 0;
				$closet = 0;
			}
			$timestamp += $opent;
			$this->vbCheckinSeconds = $opent;
			$this->vbCheckoutSeconds = $closet;
		}
		return $timestamp;
	}
	
	/**
	 * Calculates and Returns the timestamp for the Checkout
	 * Adding the hours and minutes of the VikBooking
	 * Checkout time to the OTA Arrival Date.
	 * The method also sets the class variables
	 * vbCheckinSeconds and vbCheckoutSeconds.
	 * 
	 * @param 	string	$checkoutdate 	Y-m-d date string.
	 * 
	 * @return 	int
	 */
	public function getCheckoutTimestamp($checkoutdate)
	{
		$timestamp = 0;
		$basets = strtotime($checkoutdate);
		$timestamp += $basets;
		if (strlen($this->vbCheckoutSeconds) > 0) {
			$timestamp += $this->vbCheckoutSeconds;
		} else {
			$timeopst = $this->getTimeOpenStore();
			if (is_array($timeopst)) {
				$opent = $timeopst[0];
				$closet = $timeopst[1];
			} else {
				$opent = 0;
				$closet = 0;
			}
			$timestamp += $closet;
			$this->vbCheckinSeconds = $opent;
			$this->vbCheckoutSeconds = $closet;
		}
		return $timestamp;
	}
	
	/**
	 * Gets the configuration value of VikBooking for the
	 * opening time used by the check-in and the check-out
	 * Returns the values or false.
	 */
	public function getTimeOpenStore()
	{
		$dbo = JFactory::getDbo();
		$q = "SELECT `setting` FROM `#__vikbooking_config` WHERE `param`='timeopenstore';";
		$dbo->setQuery($q);
		$dbo->execute();
		$n = $dbo->loadAssocList();
		if (empty ($n[0]['setting']) && $n[0]['setting'] != "0") {
			return false;
		} else {
			$x = explode("-", $n[0]['setting']);
			if (!empty ($x[1]) && $x[1] != "0") {
				return $x;
			}
		}
		return false;
	}
	
	/**
	 * Counts and Returns the number of nights with the given
	 * Arrival and Departure timestamps previously calculated
	 * 
	 * @param 	int		$checkints
	 * @param 	int		$checkoutts
	 * 
	 * @return 	int
	 */
	public function countNumberOfNights($checkints, $checkoutts)
	{
		if (empty($checkints) || empty($checkoutts)) {
			return 0;
		}
		$secdiff = $checkoutts - $checkints;
		$daysdiff = $secdiff / 86400;
		if (is_int($daysdiff)) {
			if ($daysdiff < 1) {
				$daysdiff = 1;
			}
		} else {
			if ($daysdiff < 1) {
				$daysdiff = 1;
			} else {
				$sum = floor($daysdiff) * 86400;
				$newdiff = $secdiff - $sum;
				$maxhmore = $this->getHoursMoreRb() * 3600;
				if ($maxhmore >= $newdiff) {
					$daysdiff = floor($daysdiff);
				} else {
					$daysdiff = ceil($daysdiff);
				}
			}
		}
		return $daysdiff;
	}
	
	/**
	 * Gets and Returns the setting in the configuration of
	 * VikBooking hoursmorebookingback. Sets the class variable
	 * vbhoursmorebookingback for later cycles.
	 */
	public function getHoursMoreRb()
	{
		if (strlen($this->vbhoursmorebookingback) > 0) {
			return $this->vbhoursmorebookingback;
		}
		$dbo = JFactory::getDbo();
		$q = "SELECT `setting` FROM `#__vikbooking_config` WHERE `param`='hoursmorebookingback';";
		$dbo->setQuery($q);
		$dbo->execute();
		$s = $dbo->loadAssocList();
		$this->vbhoursmorebookingback = $s[0]['setting'];
		return $s[0]['setting'];
	}
	
	/**
	 * Checks if at least one unit of the given room is available
	 * for the given checkin and checkout dates.
	 * 
	 * @param 	int		$idroomvb
	 * @param 	int 	$totunits
	 * @param 	int 	$checkin
	 * @param 	int 	$checkout
	 * @param 	int 	$numnights
	 * 
	 * @return 	bool
	 */
	public function roomIsAvailableInVb($idroomvb, $totunits, $checkin, $checkout, $numnights)
	{
		$dbo = JFactory::getDbo();
		$groupdays = $this->getGroupDays($checkin, $checkout, $numnights);
		$q = "SELECT `id`,`checkin`,`realback` FROM `#__vikbooking_busy` WHERE `idroom`=" . (int)$idroomvb . " AND `realback` > ".(int)$checkin.";";
		$dbo->setQuery($q);
		$dbo->execute();
		if ($dbo->getNumRows() > 0) {
			$busy = $dbo->loadAssocList();
			foreach ($groupdays as $gday) {
				$bfound = 0;
				foreach ($busy as $bu) {
					if ($gday >= $bu['checkin'] && $gday <= $bu['realback']) {
						$bfound++;
					}
				}
				if ($bfound >= $totunits) {
					return false;
				}
			}
		}
		return true;
	}
	
	/**
	 * Checks if at least one unit of the given room is available
	 * for the given checkin and checkout dates excluding the 
	 * busy ids for the old VikBooking order.
	 * 
	 * @param 	int		$idroomvb
	 * @param 	int		$totunits
	 * @param 	int		$checkin
	 * @param 	int		$checkout
	 * @param 	int		$numnights
	 * @param 	array	$excludebusyids
	 * 
	 * @return 	bool
	 */
	public function roomIsAvailableInVbModification($idroomvb, $totunits, $checkin, $checkout, $numnights, $excludebusyids)
	{
		$dbo = JFactory::getDbo();
		$groupdays = $this->getGroupDays($checkin, $checkout, $numnights);
		$q = "SELECT `id`,`checkin`,`realback` FROM `#__vikbooking_busy` WHERE `idroom`=" . (int)$idroomvb . "".(count($excludebusyids) > 0 ? " AND `id` NOT IN (".implode(", ", $excludebusyids).")" : "")." AND `realback` > ".(int)$checkin.";";
		$dbo->setQuery($q);
		$dbo->execute();
		if ($dbo->getNumRows() > 0) {
			$busy = $dbo->loadAssocList();
			foreach ($groupdays as $gday) {
				$bfound = 0;
				foreach ($busy as $bu) {
					if ($gday >= $bu['checkin'] && $gday <= $bu['realback']) {
						$bfound++;
					}
				}
				if ($bfound >= $totunits) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	* Checks if all the rooms booked (more than one) are available
	* for the given checkin and checkout dates.
	* 
	* @param 	array	$idroomsvb
	* @param 	array	$order
	* @param 	int 	$checkin
	* @param 	int 	$checkout
	* @param 	int 	$numnights
	* 
	* @return 	mixed bool true, false or array in case some of the rooms are not available but not all
	*/
	public function roomsAreAvailableInVb($idroomsvb, $order, $checkin, $checkout, $numnights)
	{
		if (!is_array($idroomsvb) || !(count($idroomsvb) > 0)) {
			return false;
		}
		$dbo = JFactory::getDbo();
		$groupdays = $this->getGroupDays($checkin, $checkout, $numnights);
		$q = "SELECT `b`.*,`r`.`units` AS `room_tot_units` FROM `#__vikbooking_busy` AS `b` LEFT JOIN `#__vikbooking_rooms` `r` ON `r`.`id`=`b`.`idroom` WHERE `b`.`idroom` IN (" . implode(',', array_unique($idroomsvb)) . ") AND `b`.`realback` > ".(int)$checkin.";";
		$dbo->setQuery($q);
		$dbo->execute();
		if ($dbo->getNumRows() > 0) {
			$busy = $dbo->loadAssocList();
			$busy_rooms = array();
			foreach ($busy as $bu) {
				$busy_rooms[$bu['idroom']][] = $bu;
			}
			//check if multiple units of the same room were booked
			$rooms_count_map = array();
			$tot_rooms_booked = 0;
			foreach ($idroomsvb as $idr) {
				$rooms_count_map[(int)$idr] = empty($rooms_count_map[(int)$idr]) ? 1 : ($rooms_count_map[(int)$idr] + 1);
				$tot_rooms_booked++;
			}
			//now the array can be unique
			$idroomsvb = array_unique($idroomsvb);
			//rooms that are not available
			$rooms_not_available = array();
			//
			foreach ($idroomsvb as $kr => $idr) {
				if (array_key_exists((int)$idr, $busy_rooms)) {
					foreach ($groupdays as $gday) {
						$bfound = 0;
						$totunits = 1;
						foreach ($busy_rooms[(int)$idr] as $bu) {
							$totunits = $bu['room_tot_units'];
							if ($gday >= $bu['checkin'] && $gday <= $bu['realback']) {
								$bfound++;
							}
						}
						if (($bfound + intval($rooms_count_map[$idr]) - 1) >= $totunits) {
							$rooms_not_available[] = (int)$idr;
						}
					}
				}
			}
			if (count($rooms_not_available) > 0) {
				//some rooms are not available
				if (count($rooms_not_available) < $tot_rooms_booked) {
					//some rooms may still be available but not all, return the array in this case
					return $rooms_not_available;
				} else {
					//none of the rooms booked is available
					return false;
				}
			} else {
				return true;
			}
		}
		return true;
	}

	/**
	 * Checks if all the rooms booked (more than one) are available
	 * for the given checkin and checkout dates excluding the 
	 * busy ids for the old VikBooking order.
	 * 
	 * @param 	array	$idroomsvb
	 * @param 	array	$order
	 * @param 	int		$checkin
	 * @param 	int		$checkout
	 * @param 	int		$numnights
	 * @param 	array	$excludebusyids
	 * 
	 * @return mixed 	boolean true, false or array in case some of the rooms are not available, but not all.
	 */
	public function roomsAreAvailableInVbModification($idroomsvb, $order, $checkin, $checkout, $numnights, $excludebusyids)
	{
		if (!is_array($idroomsvb) || !(count($idroomsvb) > 0)) {
			return false;
		}
		$dbo = JFactory::getDbo();
		$groupdays = $this->getGroupDays($checkin, $checkout, $numnights);
		$q = "SELECT `b`.*,`r`.`units` AS `room_tot_units` FROM `#__vikbooking_busy` AS `b` LEFT JOIN `#__vikbooking_rooms` `r` ON `r`.`id`=`b`.`idroom` WHERE `b`.`idroom` IN (" . implode(',', array_unique($idroomsvb)) . ")".(count($excludebusyids) > 0 ? " AND `b`.`id` NOT IN (".implode(", ", $excludebusyids).")" : "")." AND `b`.`realback` > ".(int)$checkin.";";
		$dbo->setQuery($q);
		$dbo->execute();
		if ($dbo->getNumRows() > 0) {
			$busy = $dbo->loadAssocList();
			$busy_rooms = array();
			foreach ($busy as $bu) {
				$busy_rooms[$bu['idroom']][] = $bu;
			}
			//check if multiple units of the same room were booked
			$rooms_count_map = array();
			$tot_rooms_booked = 0;
			foreach ($idroomsvb as $idr) {
				$rooms_count_map[(int)$idr] = empty($rooms_count_map[(int)$idr]) ? 1 : ($rooms_count_map[(int)$idr] + 1);
				$tot_rooms_booked++;
			}
			//now the array can be unique
			$idroomsvb = array_unique($idroomsvb);
			//rooms that are not available
			$rooms_not_available = array();
			//
			foreach ($idroomsvb as $kr => $idr) {
				if (array_key_exists((int)$idr, $busy_rooms)) {
					$use_groupdays = $groupdays;
					//Check if some rooms have a different check-in or check-out date than the booking information (Booking.com)
					foreach ($order['roominfo'] as $rcount => $ota_room) {
						if (array_key_exists($ota_room['idroomota'], $this->roomsinfomap)) {
							//Room has been mapped, check if it is this one
							if ($this->roomsinfomap[$ota_room['idroomota']]['idroomvb'] == $idr) {
								if (array_key_exists('checkin', $ota_room) && array_key_exists('checkout', $ota_room)) {
									if ($ota_room['checkin'] != $order['info']['checkin'] || $ota_room['checkout'] != $order['info']['checkout']) {
										$use_checkints = $this->getCheckinTimestamp($ota_room['checkin']);
										$use_checkoutts = $this->getCheckoutTimestamp($ota_room['checkout']);
										$use_numnights = $this->countNumberOfNights($use_checkints, $use_checkoutts);
										$use_groupdays = $this->getGroupDays($use_checkints, $use_checkoutts, $use_numnights);
									}
								}
							}
						}
					}
					//
					foreach ($use_groupdays as $gday) {
						$bfound = 0;
						$totunits = 1;
						foreach ($busy_rooms[(int)$idr] as $bu) {
							$totunits = $bu['room_tot_units'];
							if ($gday >= $bu['checkin'] && $gday <= $bu['realback']) {
								$bfound++;
							}
						}
						if (($bfound + intval($rooms_count_map[$idr]) - 1) >= $totunits) {
							$rooms_not_available[] = (int)$idr;
						}
					}
				}
			}
			if (count($rooms_not_available) > 0) {
				//some rooms are not available
				if (count($rooms_not_available) < $tot_rooms_booked) {
					//some rooms may still be available but not all, return the array in this case
					return $rooms_not_available;
				} else {
					//none of the rooms booked is available
					return false;
				}
			} else {
				return true;
			}
		}
		return true;
	}
	
	/**
	 * Gets all the days between the checkin and the checkout.
	 * Here the last day so the departure must be considered
	 * to see if the room is available in VikBooking.
	 * 
	 * @param 	int		$checkin 	checkin timestamp.
	 * @param 	int 	$checkout 	checkout timestamp.
	 * @param 	int 	$numnights 	number of nights of stay.
	 * 
	 * @return 	array 				list of timestamps involved.
	 */
	function getGroupDays($checkin, $checkout, $numnights)
	{
		$ret = array();
		$ret[] = $checkin;
		if ($numnights > 1) {
			$start = getdate($checkin);
			$end = getdate($checkout);
			$endcheck = mktime(0, 0, 0, $end['mon'], $end['mday'], $end['year']);
			for ($i = 1; $i < $numnights; $i++) {
				$checkday = $start['mday'] + $i;
				$dayts = mktime(0, 0, 0, $start['mon'], $checkday, $start['year']);
				if ($dayts != $endcheck) {				
					$ret[] = $dayts;
				}
			}
		}
		$ret[] = $checkout;
		return $ret;
	}
	
	/**
	 * Sends an email to the Administrator saying that the room was not
	 * available for the dates requested in the order received from the OTA.
	 * Returns the error message composed to be stored inside the VCM notifications.
	 * 
	 * @param 	array 	$order
	 * 
	 * @return 	string
	 */
	public function notifyAdministratorRoomNotAvailable($order)
	{
		$idroomota = '';
		$roomnamevb = '';
		if (array_key_exists(0, $order['roominfo'])) {
			//Multiple Rooms Booked or channel supporting multiple rooms
			foreach ($order['roominfo'] as $rinfo) {
				$idroomota .= $rinfo['idroomota'].', ';
				$roomnamevb .= !empty($this->roomsinfomap[$rinfo['idroomota']]['roomnamevb']) ? $this->roomsinfomap[$rinfo['idroomota']]['roomnamevb'].', ' : '';
			}
			$idroomota = rtrim($idroomota, ', ');
			$roomnamevb = rtrim($roomnamevb, ', ');
		} else {
			$idroomota = $order['roominfo']['idroomota'];
			$roomnamevb = $this->roomsinfomap[$order['roominfo']['idroomota']]['roomnamevb'];
		}
		$message = JText::sprintf('VCMOTANEWORDERROOMNOTAVAIL', ucwords($this->config['channel']['name']), $order['info']['idorderota'], $idroomota, $roomnamevb, $order['info']['checkin'], $order['info']['checkout']);
		
		$vik = new VikApplication(VersionListener::getID());
		$admail = $this->config['emailadmin'];
		$adsendermail = VikChannelManager::getSenderMail();
		$vik->sendMail(
			$adsendermail,
			$adsendermail,
			$admail,
			$admail,
			JText::_('VCMOTANEWORDERROOMNOTAVAILSUBJ'),
			$message,
			false
		);
		
		return $message;
	}
	
	/**
	 * Sends an email to the Administrator saying that the room was not
	 * available for the dates requested in the order received from the OTA.
	 * Method used when the booking type is Modify.
	 * Returns the error message composed to be stored inside the VCM notifications.
	 * 
	 * @param 	array 	$order
	 * @param 	int 	$idordervb
	 * 
	 * @return 	string
	 */
	public function notifyAdministratorRoomNotAvailableModification($order, $idordervb)
	{
		$idroomota = '';
		$roomnamevb = '';
		if (array_key_exists(0, $order['roominfo'])) {
			//Multiple Rooms Booked or channel supporting multiple rooms
			foreach ($order['roominfo'] as $rinfo) {
				$idroomota .= $rinfo['idroomota'].', ';
				$roomnamevb .= !empty($this->roomsinfomap[$rinfo['idroomota']]['roomnamevb']) ? $this->roomsinfomap[$rinfo['idroomota']]['roomnamevb'].', ' : '';
			}
			$idroomota = rtrim($idroomota, ', ');
			$roomnamevb = rtrim($roomnamevb, ', ');
		} else {
			$idroomota = $order['roominfo']['idroomota'];
			$roomnamevb = $this->roomsinfomap[$order['roominfo']['idroomota']]['roomnamevb'];
		}
		$message = JText::sprintf('VCMOTAMODORDERROOMNOTAVAIL', ucwords($this->config['channel']['name']), $order['info']['idorderota'], $idroomota, $roomnamevb, $order['info']['checkin'], $order['info']['checkout'], $idordervb);
		
		$vik = new VikApplication(VersionListener::getID());
		$admail = $this->config['emailadmin'];
		$adsendermail = VikChannelManager::getSenderMail();
		$vik->sendMail(
			$adsendermail,
			$adsendermail,
			$admail,
			$admail,
			JText::_('VCMOTAMODORDERROOMNOTAVAILSUBJ'),
			$message,
			false
		);
		
		return $message;
	}
	
	/**
	 * Sets errors.
	 * 
	 * @param 	string 	$error
	 */
	public function setError($error)
	{
		$this->errorString .= $error;
	}
	
	/**
	 * Gets active errors.
	 * 
	 * @return 	string
	 */
	public function getError()
	{
		return $this->errorString;
	}
	
	/**
	 * Stores a notification in the db for VikChannelManager.
	 * Type can be: 0 (Error), 1 (Success), 2 (Warning).
	 * 
	 * @param 	int 	$type 		integer type of the notification.
	 * @param 	string 	$from 		the source of the notification.
	 * @param 	string 	$cont 		the content of the notification.
	 * @param 	int 	$idordervb 	the optional VBO booking ID.
	 * 
	 * @return 	void
	 */
	public function saveNotify($type, $from, $cont, $idordervb = 0)
	{
		$dbo = JFactory::getDbo();
		$from = empty($from) ? 'VCM' : $from;
		$q = "INSERT INTO `#__vikchannelmanager_notifications` (`ts`,`type`,`from`,`cont`,`idordervb`,`read`) VALUES('" . time() . "', " . (int)$type . ", " . $dbo->quote($from) . ", " . $dbo->quote($cont) . ", " . (!empty($idordervb) ? (int)$idordervb : 'NULL') . ", 0);";
		$dbo->setQuery($q);
		$dbo->execute();

		return;
	}
	
	/**
	 * Generates and saves a notification key for e4jConnect and VikChannelManager.
	 * 
	 * @param 	int 	$idordervb 	the VBO booking ID.
	 * 
	 * @return 	int 				a random notification key for follow-up/nested notifications.
	 */
	public function generateNKey($idordervb)
	{
		$nkey = rand(1000, 9999);
		$dbo = JFactory::getDbo();
		$q = "INSERT INTO `#__vikchannelmanager_keys` (`idordervb`,`key`) VALUES(" . (int)$idordervb . ", " . (int)$nkey . ");";
		$dbo->setQuery($q);
		$dbo->execute();
		
		return $nkey;
	}

	/**
	 * Checks whether a new or a modified booking should trigger Vik Booking
	 * to update the shared availability calendars for the rooms involed.
	 * 
	 * @param 		int 	$bid 	the newly created or modified booking ID.
	 * @param 		boolean $clean 	whether to run cleanSharedCalendarsBusy().
	 * 
	 * @return 		bool 	true if some other cals were occupied, false otherwise.
	 * 
	 * @since 		VCM 1.7.1 (February 2020) - VBO (J)1.13/(WP)1.3.0 (February 2020)
	 *
	 * @requires 	VCM 1.7.1 - VBO (J)1.13/(WP)1.3.0
	 * 
	 * @uses 		VikBooking::updateSharedCalendars()
	 * @uses 		VikBooking::cleanSharedCalendarsBusy()
	 */
	private function updateSharedCalendars($bid, $clean = false)
	{
		if (!class_exists('VikBooking')) {
			require_once VBO_SITE_PATH . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'lib.vikbooking.php';
		}
		if (!method_exists('VikBooking', 'updateSharedCalendars')) {
			// VBO >= 1.13 (Joomla) - 1.3.0 (WordPress) is required.
			return false;
		}

		if ($clean) {
			// useful when modifying a booking to clean up the previously occupied shared cals.
			VikBooking::cleanSharedCalendarsBusy((int)$bid);
		}

		// let Vik Booking handle the involved calendars
		return VikBooking::updateSharedCalendars((int)$bid);
	}

	/**
	 * Tells whether VBO is updated enough to support the booking-type features.
	 * 
	 * @return 	bool
	 * 
	 * @since 	1.8.0
	 */
	private function isBookingTypeSupported()
	{
		if (!method_exists('VikBooking', 'isBookingTypeSupported')) {
			// VBO >= 1.14 (Joomla) - 1.4.0 (WordPress) is required.
			return false;
		}

		return VikBooking::isBookingTypeSupported();
	}
	
}
