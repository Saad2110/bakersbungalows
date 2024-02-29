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

// class VikChannelManagerConfig is necessary
require_once VCM_SITE_PATH . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'vcm_config.php';

/**
 * Booking.com class handler for the guest messages.
 * 
 * @since 	1.8.7
 */
class VCMChatChannelBookingcom extends VCMChatHandler
{
	/**
	 * The channel name.
	 *
	 * @var string
	 */
	protected $channelName = 'booking.com';

	/**
	 * Finds the Booking.com Hotel ID for the given booking.
	 * 
	 * @return 	mixed 	false on failure, string otherwise.
	 */
	private function getAccountId()
	{
		$dbo = JFactory::getDbo();
		$q = "SELECT `or`.`idroom`, `x`.`prop_params` FROM `#__vikbooking_ordersrooms` AS `or` LEFT JOIN `#__vikchannelmanager_roomsxref` `x` ON `or`.`idroom`=`x`.`idroomvb` WHERE `or`.`idorder`=" . $this->id_order . " AND `x`.`idchannel`=" . VikChannelManagerConfig::BOOKING . ";";
		$dbo->setQuery($q);
		$dbo->execute();
		if (!$dbo->getNumRows()) {
			return false;
		}
		$rows = $dbo->loadAssocList();
		foreach ($rows as $row) {
			$params = !empty($row['prop_params']) ? json_decode($row['prop_params'], true) : array();
			if (!is_array($params)) {
				continue;
			}
			foreach ($params as $account) {
				if (!empty($account)) {
					/**
					 * We get the first parameter (should be 'hotelid') from the mapped rooms as
					 * multiple rooms booked should still belong to the same Booking.com Hotel ID.
					 */
					return $account;
				}
			}
		}

		// nothing found
		return false;
	}

	/**
	 * Finds the last message in a thread sent by the guest.
	 * Used to reply to a guest message by the Hotel.
	 * 
	 * @param 	int 	$idthread 	the VCM thread ID.
	 * @param 	string 	$reciptype 	the recipient type, defaults to Hotel.
	 * 
	 * @return 	mixed 	false on failure, message object record otherwise.
	 */
	private function getLastGuestMessage($idthread, $reciptype = 'Hotel')
	{
		$dbo = JFactory::getDbo();
		$q = "SELECT `m`.*, `t`.`ota_thread_id` FROM `#__vikchannelmanager_threads_messages` AS `m` LEFT JOIN `#__vikchannelmanager_threads` AS `t` ON `m`.`idthread`=`t`.`id` WHERE `m`.`idthread`=".(int)$idthread." AND `m`.`recip_type`=".$dbo->quote($reciptype)." ORDER BY `m`.`dt` DESC";
		$dbo->setQuery($q, 0, 1);
		$dbo->execute();
		if (!$dbo->getNumRows()) {
			$this->setError('No guest messages found in this thread.');
			return false;
		}

		return $dbo->loadObject();
	}

	/**
	 * Finds the already stored ota thread ids from the given ota booking id.
	 * 
	 * @return 	mixed 	false on failure, array with ota thread ids otherwise.
	 */
	private function getBookingOtaThreads()
	{
		if (empty($this->booking['idorderota'])) {
			return false;
		}

		$dbo = JFactory::getDbo();
		$q = "SELECT `ota_thread_id` FROM `#__vikchannelmanager_threads` WHERE `idorderota`=" . $dbo->quote($this->booking['idorderota']) . " AND `channel`=" . $dbo->quote($this->channelName) . ";";
		$dbo->setQuery($q);
		$dbo->execute();
		if (!$dbo->getNumRows()) {
			return false;
		}
		$threads = $dbo->loadAssocList();

		// build the array of thread ids found, should be just one per reservation
		$ota_thread_ids = array();
		foreach ($threads as $thread) {
			if (!empty($thread['ota_thread_id']) && !in_array($thread['ota_thread_id'], $ota_thread_ids)) {
				array_push($ota_thread_ids, $thread['ota_thread_id']);
			}
		}

		return count($ota_thread_ids) ? $ota_thread_ids : false;
	}

	/**
	 * @override
	 * Check here if we are uploading a supported attachment.
	 * Booking.com allow only images in PNG/JPG and JPEG format.
	 *
	 * @param 	array 	 $file 	The details of the uploaded file.
	 *
	 * @return 	boolean  True if supported, false otherwise.
	 */
	public function checkAttachment(array $file)
	{
		// make sure we have a MIME type
		if (empty($file['type']))
		{
			return false;
		}

		return preg_match("/^image\/(png|jpe?g)$/", $file['type']);
	}

	/**
	 * @override
	 * Makes the MTHDRD request to e4jConnect.com to get all threads
	 * and related messages for the current Booking.com reservation ID.
	 * 
	 * @return 	mixed 	false on failure, stdClass object with stored information otherwise.
	 */
	protected function downloadThreads()
	{
		$apikey = VikChannelManager::getApiKey(true);
		if (empty($apikey)) {
			$this->setError('Missing API Key');
			return false;
		}

		if (empty($this->booking['idorderota'])) {
			$this->setError('Missing OTA Booking ID');
			return false;
		}

		// Booking.com Hotel ID is mandatory for the Messaging API
		$hotelid = $this->getAccountId();
		if (empty($hotelid)) {
			$this->setError('Empty Booking.com Hotel ID');
			return false;
		}

		// check if an ota thread id is available for this booking
		$thread_id = '';
		$all_threads = $this->getBookingOtaThreads();
		if (is_array($all_threads) && count($all_threads) === 1) {
			$thread_id = $all_threads[0];
		}

		$endp_url = "https://hotels.e4jconnect.com/channelmanager/?r=mthdrd&c=" . $this->channelName;

		$xml = '<?xml version="1.0" encoding="UTF-8"?>
<!-- VikChannelManager MTHDRD Request e4jConnect.com - Booking.com -->
<MessagingThreadReadRQ xmlns="http://www.e4jconnect.com/channels/mthdrdrq">
	<Notify client="' . JUri::root() . '"/>
	<Api key="' . $apikey . '"/>
	<ReadThreadsMessages>
		<Fetch hotelid="' . $hotelid . '" resid="' . $this->booking['idorderota'] . '" threadid="' . $thread_id . '"/>
	</ReadThreadsMessages>
</MessagingThreadReadRQ>';
		
		$e4jC = new E4jConnectRequest($endp_url);
		$e4jC->setPostFields($xml);
		$e4jC->slaveEnabled = true;
		$rs = $e4jC->exec();
		if ($e4jC->getErrorNo()) {
			$this->setError('cURL error: '.@curl_error($e4jC->getCurlHeader()));
			return false;
		}
		if (substr($rs, 0, 9) == 'e4j.error' || substr($rs, 0, 11) == 'e4j.warning') {
			$this->setError(VikChannelManager::getErrorFromMap($rs));
			return false;
		}

		$threads = json_decode($rs);
		if (!is_array($threads)) {
			$this->setError('Could not decode JSON response ('.(function_exists('json_last_error') ? json_last_error() : '-').'): '.$rs);
			return false;
		}

		// data stored information
		$stored = new stdClass;
		$stored->newThreads  = 0;
		$stored->newMessages = 0;

		foreach ($threads as $threadmess) {
			// compose thread object with the information to find it
			$check_thread = new stdClass;
			$check_thread->idorder = $this->booking['id'];
			$check_thread->idorderota = $this->booking['idorderota'];
			$check_thread->channel = $this->channelName;
			$check_thread->ota_thread_id = $threadmess->thread->id;
			
			// find thread last_updated date from messages
			$most_recent_ts = 0;
			$most_recent_dt = '';
			foreach ($threadmess->messages as $message) {
				// update most recent date for the thread `last_updated`
				$mess_ts = strtotime($message->created_on);
				if ($most_recent_ts < $mess_ts) {
					$most_recent_ts = $mess_ts;
					$most_recent_dt = $message->created_on;
				}
			}
			
			// check whether this thread exists
			$cur_thread_id = $this->threadExists($check_thread);
			if ($cur_thread_id !== false) {
				// set the ID property to later update the thread found
				$check_thread->id = $cur_thread_id;
			}

			// set last_updated value for thread and other properties returned
			$check_thread->subject = ucwords($threadmess->thread->topic);
			$check_thread->type = $threadmess->thread->type;
			$check_thread->last_updated = $most_recent_dt;

			// always attempt to create/update thread
			$vcm_thread_id = $this->saveThread($check_thread);
			if ($vcm_thread_id === false) {
				// go to next thread
				$this->setError('Could not store thread.');
				continue;
			}

			if ($cur_thread_id === false) {
				// a new thread was stored, increase counter
				$stored->newThreads++;
			}

			// get all new messages from this thread
			$new_messages = array();
			foreach ($threadmess->messages as $message) {
				// compose message object to find it or save it
				$check_message = new stdClass;
				$check_message->idthread = $vcm_thread_id;
				$check_message->ota_message_id = $message->id;

				/**
				 * always save or update the message because when sending
				 * a new message or a reply to the guest, we may be missing
				 * information about the sender/recipient ID/type/name or payload.
				 */
				$cur_mess_id = $this->messageExists($check_message);
				if ($cur_mess_id !== false) {
					// set the ID property for later updating the message
					$check_message->id = $cur_mess_id;
				}

				// set the rest of the properties to this message
				$check_message->in_reply_to = isset($message->payload) && !empty($message->payload->in_reply_to) ? $message->payload->in_reply_to : null;
				$check_message->sender_id = isset($message->sender) && !empty($message->sender->id) ? $message->sender->id : null;
				$check_message->sender_name = isset($message->sender) && !empty($message->sender->name) ? $message->sender->name : null;
				$check_message->sender_type = isset($message->sender) && !empty($message->sender->type) ? $message->sender->type : null;
				$check_message->recip_id = isset($message->recipient) && !empty($message->recipient->id) ? $message->recipient->id : null;
				$check_message->recip_name = isset($message->recipient) && !empty($message->recipient->name) ? $message->recipient->name : null;
				$check_message->recip_type = isset($message->recipient) && !empty($message->recipient->type) ? $message->recipient->type : null;
				$check_message->dt = JDate::getInstance($message->created_on)->toSql();
				$check_message->content = $message->text;
				$check_message->attachments = json_encode($message->attachments);
				$check_message->payload = json_encode($message->payload);

				// store or update the message
				if ($this->saveMessage($check_message) && $cur_mess_id === false) {
					$stored->newMessages++;
				}
			}
		}

		return $stored;
	}

	/**
	 * @override
	 * Sends a new message to the guest by making the request to e4jConnect.
	 * The new thread and message is immediately stored onto the db and returned.
	 * 
	 * @param 	VCMChatMessage 	$message 	the message object to be sent.
	 * 
	 * @return 	mixed 			stdClass object on success, false otherwise.
	 */
	public function send(VCMChatMessage $message)
	{
		$apikey = VikChannelManager::getApiKey(true);
		if (empty($apikey)) {
			$this->setError('Missing API Key');
			return false;
		}

		if (empty($this->booking['idorderota'])) {
			$this->setError('Missing OTA Booking ID');
			return false;
		}

		// Booking.com Hotel ID is mandatory for the Messaging API to request the auth token
		$hotelid = $this->getAccountId();
		if (empty($hotelid)) {
			$this->setError('Empty Booking.com Hotel ID');
			return false;
		}

		// message attachment nodes
		$attach_node = '';
		if (count($message->getAttachments())) {
			$attach_node = "\n".'<Attachments type="' . $message->get('attachmentsType', 'AttachmentImages') . '">' . "\n";
			foreach ($message->getAttachments() as $furi) {
				$attach_node .= '<Attachment>' . $furi . '</Attachment>' . "\n";
			}
			$attach_node .= '</Attachments>';
		}

		$endp_url = "https://hotels.e4jconnect.com/channelmanager/?r=msrep&c=".$this->channelName;

		$xml = '<?xml version="1.0" encoding="UTF-8"?>
<!-- VikChannelManager MSREP Request e4jConnect.com - Booking.com -->
<MessagingSendReplyRQ xmlns="http://www.e4jconnect.com/channels/msreprq">
	<Notify client="'.JUri::root().'"/>
	<Api key="'.$apikey.'"/>
	<SendReply>
		<Fetch hotelid="'.$hotelid.'" resid="'.$this->booking['idorderota'].'"/>
		<Message><![CDATA['.$message->getContent().']]></Message>'.$attach_node.'
	</SendReply>
</MessagingSendReplyRQ>';
		
		$e4jC = new E4jConnectRequest($endp_url);
		$e4jC->setPostFields($xml);
		$e4jC->slaveEnabled = true;
		$rs = $e4jC->exec();
		if ($e4jC->getErrorNo()) {
			$this->setError('cURL error: '.@curl_error($e4jC->getCurlHeader()));
			return false;
		}
		if (substr($rs, 0, 9) == 'e4j.error' || substr($rs, 0, 11) == 'e4j.warning') {
			$this->setError(VikChannelManager::getErrorFromMap($rs));
			return false;
		}

		// we are supposed to receive the new Thread ID and Message ID
		$resp = json_decode($rs);
		if (!$resp) {
			$this->setError('Could not decode JSON response ('.(function_exists('json_last_error') ? json_last_error() : '-').'): '.$rs);
			return false;
		}

		// prepare new thread object to be saved
		$newthread 					= new stdClass;
		$newthread->idorder 		= $this->booking['id'];
		$newthread->idorderota 		= $this->booking['idorderota'];
		$newthread->channel 		= $this->channelName;
		$newthread->ota_thread_id 	= $resp->thread_id;
		// subject and type are usually set to these values at the first message
		$newthread->subject 		= $message->get('subject', 'Email');
		$newthread->type 			= 'Contextual';
		// set last updated to current date time
		$newthread->last_updated 	= JDate::getInstance()->toSql();

		// save new thread
		$vcm_thread_id = $this->saveThread($newthread);
		if ($vcm_thread_id === false) {
			$this->setError('Could not store thread.');
			return false;
		}
		// set the new ID property for the response object
		$newthread->id = $vcm_thread_id;

		// prepare new message object
		$newmessage 				= new stdClass;
		$newmessage->idthread 		= $vcm_thread_id;
		$newmessage->ota_message_id = $resp->message_id;
		$newmessage->sender_type 	= 'Hotel';
		$newmessage->recip_type 	= 'Guest';
		$newmessage->dt 			= JDate::getInstance()->toSql();
		$newmessage->content 		= $message->getContent();
		$newmessage->attachments 	= json_encode($message->getAttachments());

		// save new message
		$vcm_mess_id = $this->saveMessage($newmessage);
		if ($vcm_mess_id === false) {
			$this->setError('Could not store message.');
			return false;
		}
		// set the new ID property for the response object
		$newmessage->id = $vcm_mess_id;

		// return the result object
		$result  		 = new stdClass;
		$result->thread  = $newthread;
		$result->message = $newmessage;

		return $result;
	}

	/**
	 * @override
	 * Sends a reply to the guest by making the request to e4jConnect.
	 * The new message is immediately stored onto the db and returned.
	 * 
	 * @param 	VCMChatMessage 	$message 	the message object to be sent.
	 * 
	 * @return 	mixed 			stdClass object on success, false otherwise.
	 */
	public function reply(VCMChatMessage $message)
	{
		$lastmessage = $this->getLastGuestMessage($message->get('idthread', 0));
		if (!$lastmessage) {
			return false;
		}

		$apikey = VikChannelManager::getApiKey(true);
		if (empty($apikey)) {
			$this->setError('Missing API Key');
			return false;
		}

		if (empty($this->booking['idorderota'])) {
			$this->setError('Missing OTA Booking ID');
			return false;
		}

		// Booking.com Hotel ID is mandatory for the Messaging API to request the auth token
		$hotelid = $this->getAccountId();
		if (empty($hotelid)) {
			$this->setError('Empty Booking.com Hotel ID');
			return false;
		}

		// compose payload from last guest message (not needed with the new implementation of 2022)
		$sendpayload = [];
		$fullpayload = json_decode($lastmessage->payload);
		if (!is_object($fullpayload)) {
			/**
			 * No need to validate the previous payload.
			 * Previous error message:
			 * 
			 * $this->setError('Could not decode previous message payload');
			 * return false;
			 * 
			 * @since 	1.8.9
			 */
		}

		// message attachment nodes
		$attach_node = '';
		if (count($message->getAttachments())) {
			$attach_node = "\n".'<Attachments type="' . $message->get('attachmentsType', 'AttachmentImages') . '">' . "\n";
			foreach ($message->getAttachments() as $furi) {
				$attach_node .= '<Attachment>' . $furi . '</Attachment>' . "\n";
			}
			$attach_node .= '</Attachments>';
		}

		$endp_url = "https://hotels.e4jconnect.com/channelmanager/?r=msrep&c=".$this->channelName;

		$xml = '<?xml version="1.0" encoding="UTF-8"?>
<!-- VikChannelManager MSREP Request e4jConnect.com - Booking.com -->
<MessagingSendReplyRQ xmlns="http://www.e4jconnect.com/channels/msreprq">
	<Notify client="'.JUri::root().'"/>
	<Api key="'.$apikey.'"/>
	<SendReply>
		<Fetch hotelid="'.$hotelid.'" resid="'.$this->booking['idorderota'].'" threadid="'.$lastmessage->ota_thread_id.'" inreplyto="'.$lastmessage->ota_message_id.'"/>
		<Message><![CDATA['.$message->getContent().']]></Message>'.$attach_node.'
		<Payload><![CDATA['.json_encode($sendpayload).']]></Payload>
	</SendReply>
</MessagingSendReplyRQ>';
		
		$e4jC = new E4jConnectRequest($endp_url);
		$e4jC->setPostFields($xml);
		$e4jC->slaveEnabled = true;
		$rs = $e4jC->exec();
		if ($e4jC->getErrorNo()) {
			$this->setError('cURL error: '.@curl_error($e4jC->getCurlHeader()));
			return false;
		}
		if (substr($rs, 0, 9) == 'e4j.error' || substr($rs, 0, 11) == 'e4j.warning') {
			$this->setError(VikChannelManager::getErrorFromMap($rs));
			return false;
		}

		// we are supposed to receive the Thread ID and the new Message ID
		$resp = json_decode($rs);
		if (!$resp) {
			$this->setError('Could not decode JSON response ('.(function_exists('json_last_error') ? json_last_error() : '-').'): '.$rs);
			return false;
		}

		// prepare new message object for saving
		$newmessage  				= new stdClass;
		$newmessage->idthread 		= $lastmessage->idthread;
		$newmessage->ota_message_id = $resp->message_id;
		$newmessage->in_reply_to 	= $lastmessage->ota_message_id;
		$newmessage->sender_id 	 	= null;
		$newmessage->sender_name 	= null;
		$newmessage->sender_type 	= 'Hotel';
		$newmessage->recip_id 	 	= null;
		$newmessage->recip_name  	= null;
		$newmessage->recip_type  	= 'Guest';
		$newmessage->dt 		 	= $message->get('dt', JDate::getInstance()->toSql());
		$newmessage->content 	 	= $message->getContent();
		$newmessage->attachments 	= json_encode($message->getAttachments());
		$newmessage->read_dt 	 	= null;
		$newmessage->payload 	 	= json_encode($sendpayload);

		// save new message
		$vcm_mess_id = $this->saveMessage($newmessage);
		if ($vcm_mess_id === false) {
			$this->setError('Could not store reply message.');
			return false;
		}
		// set the new ID property for the response object
		$newmessage->id = $vcm_mess_id;

		// update thread last_updated
		$thread = array(
			'id' 			=> $lastmessage->idthread,
			'last_updated'  => $newmessage->dt,
		);
		$this->saveThread($thread);

		// fetch message payload
		$newmessage->payload = $this->fetchPayload($newmessage->payload);

		// return the result object
		$result  		 = new stdClass;
		$result->thread  = (object) $thread;
		$result->message = $newmessage;
		
		return $result;
	}

	/**
	 * @override
	 * Abstract method used to inform e4jConnect that the last-read point has changed.
	 *
	 * @param 	object 	 $message 	The message object. Thread details
	 * 								can be accessed through the "thread" property.
	 * 
	 * @return 	boolean  True on success, false otherwise.
	 */
	protected function notifyReadingPoint($message)
	{
		$apikey = VikChannelManager::getApiKey(true);
		if (empty($apikey)) {
			$this->setError('Missing API Key');
			return false;
		}

		if (empty($this->booking['idorderota'])) {
			$this->setError('Missing OTA Booking ID');
			return false;
		}

		// Booking.com Hotel ID is mandatory for the Messaging API to request the auth token
		$hotelid = $this->getAccountId();
		if (empty($hotelid)) {
			$this->setError('Empty Booking.com Hotel ID');
			return false;
		}

		$endp_url = "https://hotels.e4jconnect.com/channelmanager/?r=msrd&c=".$this->channelName;

		$xml = '<?xml version="1.0" encoding="UTF-8"?>
<!-- VikChannelManager MSRD Request e4jConnect.com - Booking.com -->
<MessagingSetReadRQ xmlns="http://www.e4jconnect.com/channels/msrdrq">
	<Notify client="'.JUri::root().'"/>
	<Api key="'.$apikey.'"/>
	<SetRead>
		<Fetch hotelid="' . $hotelid . '" otaresid="' . $this->booking['idorderota'] . '" threadid="' . $message->thread->ota_thread_id . '" lastmessageid="' . $message->ota_message_id . '"/>
	</SetRead>
</MessagingSetReadRQ>';
		
		$e4jC = new E4jConnectRequest($endp_url);
		$e4jC->setPostFields($xml);
		$e4jC->slaveEnabled = true;
		$rs = $e4jC->exec();
		if ($e4jC->getErrorNo()) {
			$this->setError('cURL error: '.@curl_error($e4jC->getCurlHeader()));
			return false;
		}
		if (substr($rs, 0, 9) == 'e4j.error' || substr($rs, 0, 11) == 'e4j.warning') {
			$this->setError(VikChannelManager::getErrorFromMap($rs));
			return false;
		}

		// we are supposed to receive the Thread ID and the Message ID in case of success, but we do not need them.
		$resp = json_decode($rs);
		if (!$resp) {
			$this->setError('Could not decode JSON response ('.(function_exists('json_last_error') ? json_last_error() : '-').'): '.$rs);
			return false;
		}

		return true;
	}

	/**
	 * @override
	 * Fetches the specified payload. The given data must be converted into
	 * a standard form, readable by the system.
	 *
	 * Children classes might inherit this method as every channel can
	 * implement its own "answer-prediction" service.
	 *
	 * @param 	mixed 	$data 	The payload object or a JSON string.
	 *
	 * @return 	object 	The fetched payload.
	 */
	public function fetchPayload($data)
	{
		// invoke parent first to obtain a valid structure
		$data = parent::fetchPayload($data);

		$field = new stdClass;

		/**
		 * @todo 	Look through payload object and convert it into a standard
		 * 			structure that could be used/read by the system.
		 *
		 * 			This might be a valid form for plain text:
		 *			{
		 *				type: "text",
		 * 				hint: "Type something",
		 * 				default: "",
		 *				class: "",
		 * 			}
		 *
		 * 			This is meant for dropdowns, instead:
		 *			{
		 *				type: "list",
		 * 				hint: "Please select something",
		 * 				default: null,
		 * 				multiple: false,
		 *				class: "",
		 * 				options: {
		 *					1: "Yes",
		 *	 				0: "No",
		 * 					2: "Maybe",
		 * 				},
		 * 			}
		 */

		return $field;
	}
}
