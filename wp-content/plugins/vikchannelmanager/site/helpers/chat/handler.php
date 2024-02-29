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

// require necessary classes
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'message.php';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'user.php';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'notification' . DIRECTORY_SEPARATOR . 'mediator.php';

/**
 * Abstract parent class to handle guest messages.
 * We extend JObject to benefit of the errors handling functions.
 *
 * Children classes can use JObject methods to attach their own properties.
 * Here's a list of properties that should be used by the chat client.
 *
 * @property  syncTime 	The interval duration (in seconds) between each synchronization.
 * 						If not specified, the default value will be used (10 seconds).
 * 
 * @since 	1.6.13
 */
abstract class VCMChatHandler extends JObject
{
	/**
	 * VBO booking ID.
	 *
	 * @var int
	 */
	protected $id_order;

	/**
	 * A list of threads messages.
	 *
	 * @var array
	 */
	protected $threads = array();

	/**
	 * The current booking record.
	 *
	 * @var array
	 */
	protected $booking = array();

	/**
	 * The channel name that children classes should inherit.
	 *
	 * @var string
	 */
	protected $channelName = '';

	/**
	 * Class constructor is protected. Use getInstance() to
	 * get an object of the extending class for this channel.
	 * 
	 * @param 	mixed   $oid  	  VBO booking ID or an array containing ID and secret key.
	 * @param   string 	$channel  The booking source channel.
	 * 
	 * @return  mixed 	The handler instance on success, null otherwise.
	 */
	public static function getInstance($oid, $channel = null)
	{
		$channel = preg_replace("/[^a-zA-Z0-9]+/", '', $channel);

		/**
		 * In order to comply with some versions of the e4jConnect App, we need to perform a
		 * verification of the VBO booking ID passed, because it could be an OTA booking ID.
		 * 
		 * @since 	1.8.0
		 */
		if ($channel == 'vikbooking' && is_scalar($oid) && strlen($oid) > 5)
		{
			// check if this booking belongs to a different and supported chat handler
			$real_channel_data = self::getBookingRealChannel($oid);
			if ($real_channel_data !== false)
			{
				// we have found the real channel to which the booking belongs
				list($oid, $channel) = $real_channel_data;
			}
		}

		$ch_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'channels' . DIRECTORY_SEPARATOR . $channel . '.php';
		if (!is_file($ch_file))
		{
			/**
			 * We always allow to use the website chat handler also for OTA
			 * bookings that do not support a dedicated chat handler.
			 * 
			 * @since 	1.8.9
			 */
			$channel = 'vikbooking';
			$ch_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'channels' . DIRECTORY_SEPARATOR . 'vikbooking.php';
		}

		// require channel class file
		require_once $ch_file;

		// channel class name
		$ch_class = 'VCMChatChannel' . ucfirst($channel);

		if (!class_exists($ch_class))
		{
			return null;
		}

		// return channel class instance
		return new $ch_class($oid);
	}

	/**
	 * Attempts to find the true source channel from the given reservation ID.
	 * In order to comply with some versions of the e4jConnect App, if we detect
	 * that a reservation ID may be too long for the website chat handler, then
	 * we look for the proper OTA chat handler to which the booking belongs.
	 * 
	 * @param 	string 	$oid 	the presumed OTA reservation ID.
	 * 
	 * @return 	mixed 			false on failure, real channel data array otherwise.
	 * 
	 * @since 	1.8.0
	 */
	protected static function getBookingRealChannel($oid)
	{
		$dbo = JFactory::getDbo();

		$q = $dbo->getQuery(true)
			->select($dbo->qn(array(
				'o.id',
				'o.channel',
			)))
			->from($dbo->qn('#__vikbooking_orders', 'o'))
			->where($dbo->qn('o.idorderota') . ' = ' . $dbo->q($oid));

		$dbo->setQuery($q, 0, 1);
		$dbo->execute();

		if (!$dbo->getNumRows())
		{
			return false;
		}

		$record = $dbo->loadObject();
		if (empty($record->channel))
		{
			return false;
		}

		// make sure the chat handler exists for this channel
		$channel_parts   = explode('_', $record->channel);
		$record->channel = preg_replace("/[^a-zA-Z0-9]+/", '', $channel_parts[0]);
		if (is_file(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'channels' . DIRECTORY_SEPARATOR . $record->channel . '.php'))
		{
			// proper channel chat handler found
			return array(
				$record->id,
				$record->channel,
			);
		}

		return false;
	}

	/**
	 * Counts the total number of unread messages for a specific
	 * reservation ID, depending on the client and sender type.
	 * 
	 * @param   mixed    $oid 	  	 The VBO booking ID or a list of identifiers.
	 * @param 	boolean  $recipient  True to get an instance of the recipient user.
	 * 
	 * @return  mixed    The number of unread messages in case of single ID, otherwise
	 * 					 an associative array where the key is the ID and the value
	 * 					 if the count of unread messages.
	 */
	public static function countUnreadMessages($oid, $recipient = false)
	{
		if (!$oid)
		{
			// return false in case of invalid identifier(s)
			return false;
		}

		$app = JFactory::getApplication();
		if (method_exists($app, 'isClient'))
		{
			$client = $app->isClient('administrator') ? 1 : 0;
		}
		else
		{
			$client = $app->isAdmin() ? 1 : 0;
		}

		if ($recipient)
		{
			// negate the value of the client (0: admin, 1: site)
			$client ^= 1;
		}

		$dbo   = JFactory::getDbo();
		$oper  = $client == 1 ? ' <> ' : ' = ';

		$q = $dbo->getQuery(true);

		$q->select('COUNT(1) ' . $dbo->qn('count'));
		$q->from($dbo->qn('#__vikchannelmanager_threads_messages', 'm'));
		$q->leftjoin($dbo->qn('#__vikchannelmanager_threads', 't') . ' ON ' . $dbo->qn('m.idthread') . ' = ' . $dbo->qn('t.id'));
		$q->where('(' . $dbo->qn('m.sender_type') . $oper . $dbo->q('hotel') . ' AND ' . $dbo->qn('m.sender_type') . $oper . $dbo->q('host') . ')');
		$q->where($dbo->qn('m.read_dt') . ' IS NULL');

		if (is_array($oid))
		{
			// take order ID in query
			$q->select($dbo->qn('t.idorder'));
			// retrieve all specified orders
			$q->where($dbo->qn('t.idorder') . ' IN (' . implode(',', array_map('intval', $oid)) . ')');
			// group counts by order ID
			$q->group($dbo->qn('t.idorder'));
		}
		else
		{
			// retrieve single order
			$q->where($dbo->qn('t.idorder') . ' = ' . (int) $oid);
		}

		$dbo->setQuery($q);
		$dbo->execute();

		if (is_scalar($oid))
		{
			// return count directly in case of integer
			return (int) $dbo->loadResult();
		}

		$map = array();

		if ($dbo->getNumRows())
		{
			// iterate response and build $map assoc
			foreach ($dbo->loadObjectList() as $obj)
			{
				$map[$obj->idorder] = (int) $obj->count;
			}
		}

		return $map;
	}

	/**
	 * Returns all the unread messages grouped by reservation ID,
	 * depending on the client and sender type.
	 * 
	 * @param 	boolean  $recipient  True to get an instance of the recipient user.
	 * 
	 * @return  array    An associative array where the key is the booking ID and
	 * 					 the value is an objects list (messages).
	 */
	public static function getAllUnreadMessages($recipient = false)
	{
		$app = JFactory::getApplication();
		if (method_exists($app, 'isClient'))
		{
			$client = $app->isClient('administrator') ? 1 : 0;
		}
		else
		{
			$client = $app->isAdmin() ? 1 : 0;
		}

		if ($recipient)
		{
			// negate the value of the client (0: admin, 1: site)
			$client ^= 1;
		}

		$dbo   = JFactory::getDbo();
		$oper  = $client == 1 ? ' <> ' : ' = ';

		$map = array();

		$q = $dbo->getQuery(true);

		$q->select($dbo->qn(array(
			't.idorder',
			't.subject',
			'm.content',
			'm.dt',
		)));
		$q->from($dbo->qn('#__vikchannelmanager_threads_messages', 'm'));
		$q->leftjoin($dbo->qn('#__vikchannelmanager_threads', 't') . ' ON ' . $dbo->qn('m.idthread') . ' = ' . $dbo->qn('t.id'));
		$q->where('(' . $dbo->qn('m.sender_type') . $oper . $dbo->q('hotel') . ' AND ' . $dbo->qn('m.sender_type') . $oper . $dbo->q('host') . ')');
		$q->where($dbo->qn('m.read_dt') . ' IS NULL');
		$q->order($dbo->qn('m.dt') . ' DESC');

		$dbo->setQuery($q);
		$dbo->execute();

		if ($dbo->getNumRows())
		{
			// iterate response and build $map assoc
			foreach ($dbo->loadObjectList() as $obj)
			{
				if (!isset($map[$obj->idorder]))
				{
					$map[$obj->idorder] = array();
				}

				$map[$obj->idorder][] = $obj;
			}

			// sort first level by order ID
			ksort($map);
		}

		return $map;
	}

	/**
	 * Loads all the latest threads and the related (latest) messages.
	 * The messages are loaded from an optional start index and limit.
	 * 
	 * @param 	integer  $start 	  The start index for the messages query.
	 * @param 	integer  $limit 	  The limit for the messages query.
	 * @param 	mixed 	 $sender 	  Sender type, string or array.
	 * @param 	bool 	 $join_sender True to join only the guest thread messages.
	 * 
	 * @return 	array 	 The list of thread-message objects loaded.
	 *
	 * @since 	1.7.4
	 * @since 	1.8.0 	 Argument $sender was added to allow filtering.
	 * @since 	1.8.9 	 Added new clauses to sub-query join for sender type in
	 * 					 order to properly sort the latest guest thread messages.
	 * @since 	1.8.9 	 New arg $join_sender to fetch all latest guest messages,
	 * 					 not only the threads where the last message is from the guest.
	 * @since 	1.8.11 	 Customer profile picture and other booking details are fetched.
	 * 					 If $join_sender, threads with no_reply_needed will be ignored.
	 */
	public static function getLatestThreads($start = 0, $limit = 20, $sender = null, $join_sender = false)
	{
		$threads = [];

		$dbo = JFactory::getDbo();

		if (is_array($sender))
		{
			$sender = array_map([$dbo, 'q'], $sender);
		}

		////////////////////////////
		/// LOAD LATEST MESSAGES ///
		////////////////////////////

		$m = $dbo->getQuery(true);

		$m->select('m1.*');

		// load messages tables
		$m->from($dbo->qn('#__vikchannelmanager_threads_messages', 'm1'));

		// self join to take only the latest messages
		$m->leftjoin(
			$dbo->qn('#__vikchannelmanager_threads_messages', 'm2')
			. ' ON ' . $dbo->qn('m1.idthread') . ' = ' . $dbo->qn('m2.idthread')
			. ' AND ' . $dbo->qn('m1.dt') . ' < ' . $dbo->qn('m2.dt')
			. ($join_sender && is_array($sender) ? ' AND ' . $dbo->qn('m1.sender_type') . ' IN (' . implode(', ', $sender) . ') AND ' . $dbo->qn('m2.sender_type') . ' IN (' . implode(', ', $sender) . ')' : '')
			. ($join_sender && !is_array($sender) && !empty($sender) ? ' AND ' . $dbo->qn('m1.sender_type') . ' = ' . $dbo->q($sender) . ' AND ' . $dbo->qn('m2.sender_type') . ' = ' . $dbo->q($sender) : '')
		);

		// go ahead as long as there are no more messages to join
		$m->where($dbo->qn('m2.id') . ' IS NULL');

		///////////////////////////
		/// LOAD LATEST THREADS ///
		///////////////////////////

		$q = $dbo->getQuery(true);

		$q->select($dbo->qn(['t1.idorder', 't1.channel', 't1.subject', 't1.last_updated', 't1.no_reply_needed']));
		$q->select($dbo->qn('m.id', 'id_message'));
		$q->select($dbo->qn('m.idthread', 'id_thread'));
		$q->select($dbo->qn(['m.sender_type', 'm.dt', 'm.content', 'm.attachments', 'm.read_dt']));
		$q->select($dbo->qn('o.status', 'b_status'));
		$q->select($dbo->qn('o.days', 'b_nights'));
		$q->select($dbo->qn('o.checkin', 'b_checkin'));
		$q->select($dbo->qn('o.checkout', 'b_checkout'));
		$q->select($dbo->qn('c.id', 'id_customer'));
		$q->select($dbo->qn(['c.first_name', 'c.last_name', 'c.pic']));

		// load threads tables
		$q->from($dbo->qn('#__vikchannelmanager_threads', 't1'));

		// self join to take only the latest thread that belongs to the same order
		$q->leftjoin(
			$dbo->qn('#__vikchannelmanager_threads', 't2')
			. ' ON ' . $dbo->qn('t1.idorder') . ' = ' . $dbo->qn('t2.idorder')
			. ' AND ' . $dbo->qn('t1.last_updated') . ' < ' . $dbo->qn('t2.last_updated')
		);

		// join threads with latest messages
		$q->leftjoin(
			'(' . $m . ') AS ' . $dbo->qn('m')
			. ' ON ' . $dbo->qn('t1.id') . ' = ' . $dbo->qn('m.idthread')
		);

		// recover customer and booking basic details
		$q->leftjoin($dbo->qn('#__vikbooking_orders', 'o') . ' ON ' . $dbo->qn('t1.idorder') . ' = ' . $dbo->qn('o.id'));
		$q->leftjoin($dbo->qn('#__vikbooking_customers_orders', 'co') . ' ON ' . $dbo->qn('t1.idorder') . ' = ' . $dbo->qn('co.idorder'));
		$q->leftjoin($dbo->qn('#__vikbooking_customers', 'c') . ' ON ' . $dbo->qn('co.idcustomer') . ' = ' . $dbo->qn('c.id'));

		// go ahead as long as there are no more threads to join
		$q->where($dbo->qn('t2.id') . ' IS NULL');

		// optional filter by sender type
		if (is_array($sender))
		{
			$q->where($dbo->qn('m.sender_type') . ' IN (' . implode(', ', $sender) . ')');
		}
		elseif (!empty($sender))
		{
			$q->where($dbo->qn('m.sender_type') . ' = ' . $dbo->q($sender));
		}

		// when getting all guest messages in threads, ignore the ones with no reply needed
		if ($join_sender)
		{
			$q->where($dbo->qn('t1.no_reply_needed') . ' = 0');
		}

		// order by descending received date
		$q->order($dbo->qn('t1.last_updated') . ' DESC');

		$dbo->setQuery($q, $start, $limit);
		$dbo->execute();

		if ($dbo->getNumRows())
		{
			foreach ($dbo->loadObjectList() as $thread)
			{
				// JSON decode attachments
				$thread->attachments = $thread->attachments ? (array) json_decode($thread->attachments) : [];

				$threads[] = $thread;
			}
		}

		return $threads;
	}

	/**
	 * Statically loads the necessary JS/CSS assets for rendering the chat.
	 * 
	 * @return  void
	 * 
	 * @since 	1.8.11
	 */
	public static function loadChatAssets()
	{
		static $chat_assets_loaded = null;

		if ($chat_assets_loaded) {
			return;
		}

		// make translations available also for JS scripts
		JText::script('VCM_CHAT_TODAY');
		JText::script('VCM_CHAT_YESTERDAY');
		JText::script('VCM_CHAT_SENDING_ERR');
		JText::script('VCM_CHAT_THREAD_TOPIC');
		JText::script('VCM_CHAT_TEXTAREA_PLACEHOLDER');
		JText::script('VCM_CHAT_THREAD_SUBJECT_DEFAULT');

		$document = JFactory::getDocument();
		$document->addScript(VCM_SITE_URI . 'assets/js/chat.js');
		$document->addStyleSheet(VCM_SITE_URI . 'assets/css/chat.css');

		try
		{
			// try to include chosen plugin
			JHtml::_('formbehavior.chosen');
		}
		catch (Exception $e)
		{
			// CHOSEN is not supported, just catch the error
		}

		// turn flag on for assets loaded
		$chat_assets_loaded = 1;
	}

	/**
	 * Class constructor.
	 * 
	 * @param 	mixed  $oid  VBO booking ID or an array containing ID and secret key.
	 * 
	 * @uses 	prepareOptions()
	 * @uses 	loadBookingDetails()
	 * @uses 	loadThreadsMessages()
	 */
	public function __construct($oid)
	{
		// make sure the main library of Vik Channel Manager is available
		if (!class_exists('VikChannelManager'))
		{
			require_once VCM_SITE_PATH . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'lib.vikchannelmanager.php';
		}

		if (defined('ABSPATH') && !defined('_JEXEC'))
		{
			// load VCM site language
			$lang = JFactory::getLanguage();
			$lang->load('com_vikchannelmanager', VIKCHANNELMANAGER_SITE_LANG);
			// load language site handler too
			$lang->attachHandler(VIKCHANNELMANAGER_LIBRARIES . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR . 'site.php', 'vikchannelmanager');
		}
		else
		{
			// load VCM site language
			JFactory::getLanguage()->load('com_vikchannelmanager', JPATH_SITE);
		}

		if (is_array($oid))
		{
			// extract oid and secret key from array
			list($oid, $key) = $oid;
			// always cast to string and we cannot have a NULL value here
			$key = (string) $key;
		}
		else
		{
			$key = null;
		}

		// current VBO booking ID
		$this->id_order = (int) $oid;

		// prepare options, to allow child classes to set some options
		$this->prepareOptions();

		// load the current booking record
		$this->loadBookingDetails($key);

		// load all threads and related messages for this booking
		$this->loadThreadsMessages();
	}

	/**
	 * Main method called by Vik Booking to render the messaging chat.
	 * Declares CSS and JS assets to prepare the output and controls.
	 * 
	 * @param 	array 	$extra_opts 	custom environment options for the Chat handler.
	 * @param 	bool 	$load_assets 	whether to load the JS/CSS assets.
	 * 
	 * @return  string 	the HTML content to be displayed by Vik Booking.
	 * 
	 * @since 	1.8.11 	added arguments to pass extra options or load the JS/CSS assets.
	 */
	public function renderChat(array $extra_opts = [], $load_assets = true)
	{
		// fill channel options array
		$options = [
			'syncTime' => $this->get('syncTime', null),
		];

		// merge channel options with extra options
		$options = array_merge($options, $extra_opts);

		$app = JFactory::getApplication();
		if (method_exists($app, 'isClient'))
		{
			$client = $app->isClient('administrator') ? 'admin' : 'site';
		}
		else
		{
			$client = $app->isAdmin() ? 'admin' : 'site';
		}

		/**
		 * Detect AJAX base URI environment depending on the platform.
		 * 
		 * @since 	1.7.6
		 */
		if (defined('ABSPATH'))
		{
			$ajax_uri = admin_url('admin-ajax.php') . '?action=vikchannelmanager' . ($client == 'site' ? '&vik_ajax_client=site' : '');
		}
		else
		{
			$ajax_uri = 'index.php?option=com_vikchannelmanager&tmpl=component';
		}

		$threads_json = json_encode($this->threads);
		$channel_json = json_encode($options);

		$hide_threads_style = '';
		if (!empty($options['hideThreads']))
		{
			$hide_threads_style = 'display: none;';
		}

		$message_tmpl  = '<div class="chat-message" id="{id}">\n<div class="speech-bubble {class}">\n{message}\n</div>\n</div>\n';
		$thread_tmpl   = '<div class="thread-details">\n<div class="thread-heading">\n<div class="thread-recipient">{recipient}</div>\n<div class="thread-datetime">{datetime}</div>\n</div>\n<div class="thread-message"><div class="thread-content">{message}</div><div class="thread-notif">{notifications}</div></div>\n</div>';
		$datetime_tmpl = '<div class="chat-datetime-separator {class}" data-datetime="{utc}">{datetime}</div>';

		// load the JS/CSS assets
		if ($load_assets)
		{
			static::loadChatAssets();
		}

		// define translations to be used within the HTML template
		$translations = [
			'no_threads'  => JText::_('VCM_CHAT_NO_THREADS'),
			'placeholder' => JText::_('VCM_CHAT_TEXTAREA_PLACEHOLDER'),
		];

		$js_decl = 
<<<JS
(function($) {
	'use strict';

	$(function() {
		VCMChat.getInstance({
			environment: {
				url: 	 '$ajax_uri',
				threads: $threads_json,
				idOrder: {$this->id_order},
				channel: '{$this->channelName}',
				secret:  '{$this->booking['ts']}',
				client:  '$client',
				options: $channel_json,
			},
			element: {
				conversation: '#chat-conversation',
				threadsList:  '#chat-threads',
				noThreads: 	  '#no-threads',
				uploadsBar:   '#chat-uploads-tab',
				progressBar:  '#chat-progress-wrap',
				inputBox: 	  '#chat-input-box',
			},
			template: {
				message:  '$message_tmpl',
				thread:   '$thread_tmpl',
				datetime: '$datetime_tmpl',
			},
			lang: {
				today: 	   'VCM_CHAT_TODAY',
				yesterday: 'VCM_CHAT_YESTERDAY',
				senderr:   'VCM_CHAT_SENDING_ERR',
				newthread: 'VCM_CHAT_THREAD_TOPIC',
				texthint:  'VCM_CHAT_TEXTAREA_PLACEHOLDER',
				defthread: 'VCM_CHAT_THREAD_SUBJECT_DEFAULT',
			}
		}).prepare();
	});
})(jQuery);
JS;

		$html = 
<<<HTML
<div class="chat-border-layout">
	
	<div class="chat-threads-panel" style="$hide_threads_style">

		<div class="no-threads-box" id="no-threads">
			{$translations['no_threads']}
		</div>

		<ul class="chat-threads-list" id="chat-threads" style="display:none;">

		</ul>
	</div>

	<div class="chat-messages-panel">

		<div class="chat-conversation" id="chat-conversation">

		</div>

		<div class="chat-input-footer">
			<div class="textarea-input" id="chat-input-box"></div>

			<div class="chat-uploads-bar" style="display:none;">
				<div class="chat-progress-wrap" id="chat-progress-wrap"></div>
				<div class="chat-uploads-tab" id="chat-uploads-tab"></div>
			</div>
		</div>

	</div>

</div>

<script type="text/javascript">
	{$js_decl}
</script>
HTML;

		return $html;
	}

	/**
	 * Loads the messages of a specific VCM Thread ID, or from all Threads.
	 * The messages are loaded from an optional start index and limit.
	 * 
	 * @param 	integer  $start 	 The start index for the messages query.
	 * @param 	integer  $limit 	 The limit for the messages query.
	 * @param 	integer  $thread_id  The VCM thread id for the messages to read.
	 * @param 	string 	 $datetime 	 An optional datetime to exclude all the newer messages.
	 * @param 	integer  $min_id 	 The threshold identifier. Messages with equals or
	 * 								 lower ID won't be taken. Use NULL to ignore this filter.
	 * @param 	boolean  $unread 	 True to retrieve only unread messages, false for read messages only.
	 * 								 Use null to ignore this filter. 
	 * 
	 * @return 	array 	 The list of thread-message objects loaded.
	 * 
	 * @see 			 Some chat handlers may override this method.
	 */
	public function loadThreadsMessages($start = 0, $limit = 20, $thread_id = null, $datetime = null, $min_id = null, $unread = null)
	{
		$dbo = JFactory::getDbo();

		$q = $dbo->getQuery(true)
			->select(array(
				$dbo->qn('t.id'),
				$dbo->qn('t.idorder'),
				$dbo->qn('t.idorderota'),
				$dbo->qn('t.channel'),
				$dbo->qn('t.ota_thread_id'),
				$dbo->qn('t.subject'),
				$dbo->qn('t.type'),
				$dbo->qn('t.last_updated'),
			))
			->select('COUNT(1) AS `tot_messages`')
			->from($dbo->qn('#__vikchannelmanager_threads', 't'))
			->leftjoin($dbo->qn('#__vikchannelmanager_threads_messages', 'm') . ' ON ' . $dbo->qn('t.id') . ' = ' . $dbo->qn('m.idthread'))
			->where($dbo->qn('t.idorder') . ' = ' . $this->id_order);

		if ($thread_id)
		{
			$q->where($dbo->qn('t.id') . ' = ' . (int) $thread_id);
		}

		$q->group($dbo->qn('t.id'))
			->order($dbo->qn('t.last_updated') . ' DESC');

		$dbo->setQuery($q);
		$dbo->execute();

		$threads = array();

		if ($dbo->getNumRows())
		{
			$threads = $dbo->loadObjectList();

			$q = $dbo->getQuery(true)
				->select('*')
				->from($dbo->qn('#__vikchannelmanager_threads_messages'))
				// @NOTE: do not place WHERE statement here as it is cleared within the FOREACH
				->order($dbo->qn('dt') . ' DESC');

			// read messages for each thread of this booking
			foreach ($threads as &$thread)
			{
				$thread->messages = array();

				$q->clear('where')->where($dbo->qn('idthread') . ' = ' . (int) $thread->id);

				if ($datetime)
				{
					// exclude all the messages newer than the specified date time
					$q->where($dbo->qn('dt') . ' <= ' . $dbo->q(JDate::getInstance($datetime)->toSql()));
				}

				if (!is_null($unread))
				{
					$q->where($dbo->qn('read_dt') . ' IS ' . ($unread ? 'NULL' : 'NOT NULL'));
				}

				if (!is_null($min_id))
				{
					// exclude all the messages older than the specified one (included)
					$q->where($dbo->qn('id') . ' > ' . (int) $min_id);
				}

				$dbo->setQuery($q, $start, $limit ? $limit : null);
				$dbo->execute();

				if ($dbo->getNumRows())
				{
					// assign $this within a temporary variable as I fear
					// PHP 5.3 or lower doesn't support $this context
					// inside an anonymous function
					$chat = $this;

					// push messages for this thread
					$thread->messages = array_map(function($message) use($chat)
					{
						// decode attachments list
						$message->attachments = (array) json_decode($message->attachments);

						// fetch payload
						$message->payload = $chat->fetchPayload($message->payload);

						return $message;
					}, $dbo->loadObjectList());
				}
			}

			// set threads messages
			// @update 	we cannot merge arrays because we would have duplicated entries
			// $this->threads = array_merge($this->threads, $threads);
			$this->threads = $threads;
		}

		return $threads;
	}

	/**
	 * Loads the UNREAD messages of a specific VCM Thread ID, or from all Threads.
	 * 
	 * @param 	integer  $thread_id  The VCM thread id for the messages to read.
	 * 
	 * @return 	array 	 The list of thread-message objects loaded.
	 */
	public function loadUnreadThreadsMessages($thread_id = null)
	{
		// get unread thread messages without limits
		$threads = $this->loadThreadsMessages($start = 0, $limit = null, $thread_id, $datetime = null, $min_id = null, $unread = true);

		// exclude all threads without unread messages
		return array_filter($threads, function($thread)
		{
			return (bool) count($thread->messages);
		});
	}

	/**
	 * Loads the MOST RECENTE messages of a specific VCM Thread ID, or from all Threads.
	 * 
	 * @param 	integer  $thread_id  The VCM thread id for the messages to read.
	 * @param 	integer  $min_id 	 The threshold identifier. Messages with equals or
	 * 								 lower ID won't be taken.
	 * 
	 * @return 	array 	 The list of thread-message objects loaded.
	 */
	public function loadRecentThreadsMessages($min_id = null, $thread_id = null)
	{
		// get unread thread messages without limits
		$threads = $this->loadThreadsMessages($start = 0, $limit = null, $thread_id, $datetime = null, $min_id);

		// exclude all threads without unread messages
		return array_filter($threads, function($thread)
		{
			return (bool) count($thread->messages);
		});
	}

	/**
	 * Gets the message record that matches the specified ID.
	 *
	 * @param 	integer  $id_message 	The message to get.
	 *
	 * @return 	mixed 	 The message found, otherwise null.
	 */
	public function getMessage($id_message)
	{
		$dbo = JFactory::getDbo();

		$q = $dbo->getQuery(true)
			->select('*')
			->from($dbo->qn('#__vikchannelmanager_threads_messages'))
			->where($dbo->qn('id') . ' = ' . (int) $id_message);

		$dbo->setQuery($q, 0, 1);
		$dbo->execute();

		if ($dbo->getNumRows())
		{
			$msg = $dbo->loadObject();

			$q = $dbo->getQuery(true)
				->select('*')
				->from($dbo->qn('#__vikchannelmanager_threads'))
				->where($dbo->qn('id') . ' = ' . (int) $msg->idthread)
				// make sure the thread belong to this order
				->where($dbo->qn('idorder') . ' = ' . $this->id_order);

			$dbo->setQuery($q, 0, 1);
			$dbo->execute();

			// assign thread details to message object
			if (!$dbo->getNumRows())
			{
				// we are probably trying to access a thread of
				// a different order
				return null;
			}

			$msg->thread = $dbo->loadObject();
		}

		return $msg;
	}

	/**
	 * Allow children classes to override this method
	 * and set some properties (like syncTime).
	 * 
	 * @return 	void
	 * 
	 * @since 	1.8.0
	 */
	protected function prepareOptions()
	{
		return;
	}

	/**
	 * Loads the details of the current VBO booking ID.
	 *
	 * @param 	mixed 	$key 	The secret key to check.
	 * 							If not provided, it won't be validated.
	 * 
	 * @return  void
	 *
	 * @throws 	Exception 	In case the order doesn't exist/match the key.
	 */
	protected function loadBookingDetails($key = null)
	{
		$dbo = JFactory::getDbo();

		$q = $dbo->getQuery(true)
			->select('`o`.*')
			->select($dbo->qn('m.last_check'))
			->from($dbo->qn('#__vikbooking_orders', 'o'))
			->leftjoin($dbo->qn('#__vikchannelmanager_order_messaging_data', 'm') . ' ON ' . $dbo->qn('o.id') . ' = ' . $dbo->qn('m.idorder'))
			->where($dbo->qn('o.id') . ' = ' . $this->id_order);

		if (!is_null($key))
		{
			$q->where($dbo->qn('o.ts') . ' = ' . $dbo->q($key));
		}
		
		$dbo->setQuery($q, 0, 1);
		$dbo->execute();
		
		if (!$dbo->getNumRows())
		{
			throw new Exception(JText::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$this->booking = $dbo->loadAssoc();
	}

	/**
	 * Returns the list of loaded threads and the related messages.
	 *
	 * @return 	array
	 */
	public function getThreads()
	{
		return $this->threads;
	}

	/**
	 * Checks whether a thread exists from the given information.
	 * 
	 * @param 	mixed 	$data 	The record identifier or an object/array
	 * 							containing the query parameters, where the
	 * 							key is the column name and the property is
	 * 							the column value.
	 * 
	 * @return  mixed 	The VCM thread ID if exists, false otherwise.
	 *
	 * @uses 	_exists()
	 */
	public function threadExists($data)
	{
		return $this->_exists('#__vikchannelmanager_threads', 'id', $data);
	}

	/**
	 * Checks whether a thread message exists from the given information.
	 * 
	 * @param 	mixed 	$data 	The record identifier or an object/array
	 * 							containing the query parameters, where the
	 * 							key is the column name and the property is
	 * 							the column value.
	 * 
	 * @return  mixed 	The VCM message ID if exists, false otherwise.
	 *
	 * @uses 	_exists()
	 */
	public function messageExists($data)
	{	
		return $this->_exists('#__vikchannelmanager_threads_messages', 'id', $data);
	}

	/**
	 * Checks whether a table record exists according to the specified arguments.
	 * 
	 * @param 	string 	$table 	The name of the database table (not escaped).
	 * @param 	string 	$pk 	The primary key column name.
	 * @param 	mixed 	$query 	The record identifier or an object/array
	 * 							containing the query parameters, where the
	 * 							key is the column name and the property is
	 * 							the column value.
	 * 
	 * @return  mixed 	The VCM message ID if exists, false otherwise.
	 */
	protected function _exists($table, $pk, $query)
	{
		// make sure we have a valid query
		if (!$query)
		{
			return false;
		}

		$dbo = JFactory::getDbo();

		if (is_scalar($query))
		{
			// check if record exists by ID in VCM
			$query = array($pk => $query);
		}

		$q = $dbo->getQuery(true)
			->select($dbo->qn($pk))
			->from($dbo->qn($table));

		foreach ((array) $query as $k => $v)
		{
			$q->where($dbo->qn($k) . ' = ' . $dbo->q($v));
		}

		$dbo->setQuery($q, 0, 1);
		$dbo->execute();

		if ($dbo->getNumRows())
		{
			return $dbo->loadResult();
		}
		
		return false;
	}

	/**
	 * Creates or updates a thread onto the database.
	 * 
	 * @param 	mixed 	$data 	An object or an associative array containing 
	 * 							the properties to store.
	 * 
	 * @return  mixed 	The VCM thread ID on success, false otherwise.
	 *
	 * @uses 	_save()
	 */
	public function saveThread($data)
	{
		if (is_object($data) && !isset($data->no_reply_needed))
		{
			// make sure to (re-)flag the thread as "reply needed"
			$data->no_reply_needed = 0;
		}
		elseif (is_array($data) && !isset($data['no_reply_needed']))
		{
			// make sure to (re-)flag the thread as "reply needed"
			$data['no_reply_needed'] = 0;
		}

		return $this->_save('#__vikchannelmanager_threads', 'id', $data);
	}

	/**
	 * Inserts or updates a thread message onto the database.
	 * 
	 * @param 	mixed 	$data 	An object or an associative array containing 
	 * 							the properties to store.
	 * 
	 * @return  mixed 	The VCM message ID on success, false otherwise.
	 *
	 * @uses 	_save()
	 */
	public function saveMessage($data)
	{
		return $this->_save('#__vikchannelmanager_threads_messages', 'id', $data);
	}

	/**
	 * Inserts or updates a generic table record onto the database.
	 * 
	 * @param 	string 	$table 	The name of the database table (not escaped).
	 * @param 	string 	$pk 	The primary key column name.
	 * @param 	mixed 	$data 	An object or an associative array containing 
	 * 							the properties to store. 
	 * 
	 * @return  mixed 	The VCM message ID if exists, false otherwise.
	 */
	protected function _save($table, $pk, $data)
	{
		$dbo = JFactory::getDbo();

		// make sure we are handling an object
		$data = (object) $data;

		if (empty($data->{$pk}))
		{
			// insert new record
			if (!$dbo->insertObject($table, $data, $pk))
			{
				return false;
			}
		}
		else
		{
			// update existing record
			if (!$dbo->updateObject($table, $data, $pk))
			{
				return false;
			}
		}

		return $data->{$pk};
	}

	/**
	 * Checks whether new messages should be downloaded. Last download
	 * date and time is compared to the current execution date and time.
	 * If the number of elapsed minutes from the last retrieval date is
	 * greater than or equal to the given limit interval, new threads
	 * or new messages should be downloaded to update the conversation.
	 * 
	 * @param 	int 	 $interval 	The minimum number of minutes elapsed
	 * 
	 * @return 	boolean  True if new messages should be downloaded, false otherwise.
	 */
	public function shouldDownloadNew($interval = 1440)
	{
		if (empty($this->booking['last_check']))
		{
			// last download dateTime not available, download new messages
			return true;
		}

		$last = JDate::getInstance($this->booking['last_check']);
		$now  = JDate::getInstance();
		// calculate the DateInterval object with the difference between the two dates
		$diff = $last->diff($now);

		if (!$diff)
		{
			// could not calculate the difference between the dates
			return false;
		}

		// count the difference between the two dates in minutes
		$elapsed = ($diff->y * 365 * 1440) + ($diff->m * 30 * 1440) + ($diff->d * 1440) + ($diff->h * 60) + $diff->i;

		return $elapsed >= $interval;
	}

	/**
	 * Downloads all threads and their related messages for the current
	 * VBO booking ID.
	 * 
	 * @return 	mixed
	 *
	 * @uses 	downloadThreads()
	 */
	public function sync()
	{
		// download threads
		$res = $this->downloadThreads();

		$dbo = JFactory::getDbo();

		$q = $dbo->getQuery(true)
			->select(1)
			->from($dbo->qn('#__vikchannelmanager_order_messaging_data'))
			->where($dbo->qn('idorder') . ' = ' . $this->id_order);

		$dbo->setQuery($q, 0, 1);
		$dbo->execute();

		if ($dbo->getNumRows())
		{
			// update last synchronization date time
			$q = $dbo->getQuery(true)
				->update($dbo->qn('#__vikchannelmanager_order_messaging_data'))
				->set($dbo->qn('last_check') . ' = ' . $dbo->q(JDate::getInstance()->toSql()))
				->where($dbo->qn('idorder') . ' = ' . $this->id_order);
		}
		else
		{
			// insert last synchronization date time
			$q = $dbo->getQuery(true)
				->insert($dbo->qn('#__vikchannelmanager_order_messaging_data'))
				->columns($dbo->qn(array('last_check', 'idorder', 'idorderota')))
				->values($dbo->q(JDate::getInstance()->toSql()) . ', ' . $this->id_order . ', ' . $dbo->q($this->booking['idorderota']));
		}

		$dbo->setQuery($q);
		$dbo->execute();

		return $res;
	}

	/**
	 * Marks the specified message as read.
	 * All the unread messages, that was posted previously and 
	 * that belong to the same thread, will be read too.
	 *
	 * @param 	integer  $id_message 	The message to read.
	 * @param 	string 	 $datetime 		An optional read datetime.
	 * 									If not provided, the current time will be used.
	 *
	 * @return 	object 	 A resulting object.
	 */
	public function readMessage($id_message, $datetime = 'now')
	{
		$dbo = JFactory::getDbo();

		// get message record
		$message = $this->getMessage($id_message);

		$result = new stdClass;
		$result->count    = 0;
		$result->datetime = JDate::getInstance($datetime)->toSql();

		if (!$message)
		{
			return $result;
		}

		// read all unread messages
		$q = $dbo->getQuery(true)
			->update($dbo->qn('#__vikchannelmanager_threads_messages'))
			// set read datetime as NOW
			->set($dbo->qn('read_dt') . ' = ' . $dbo->q($result->datetime))
			// consider only the messages that belong to the same thread
			->where($dbo->qn('idthread') . ' = ' . (int) $message->idthread)
			// take only the specified message and the previous ones
			->where($dbo->qn('id') . ' <= ' . (int) $message->id)
			// ignore already read  messages
			->where($dbo->qn('read_dt') . ' IS NULL');

		if (!strcasecmp($message->sender_type, 'hotel') || !strcasecmp($message->sender_type, 'host'))
		{
			// make sure to read messages sent by HOTEL
			$q->where('(' . $dbo->qn('sender_type') . ' = ' . $dbo->q('hotel') . ' OR ' . $dbo->qn('sender_type') . ' = ' . $dbo->q('host') . ')');
		}
		else
		{
			// make sure to read messages NOT sent by HOTEL
			$q->where('(' . $dbo->qn('sender_type') . ' <> ' . $dbo->q('hotel') . ' AND ' . $dbo->qn('sender_type') . ' <> ' . $dbo->q('host') . ')');
		}

		$dbo->setQuery($q);
		$dbo->execute();

		// obtain number of updated records
		$result->count = $dbo->getAffectedRows();

		if ($result->count)
		{
			// notify reading point to the channel, if supported
			$result->channel = $this->notifyReadingPoint($message);
		}

		return $result;
	}

	/**
	 * Returns the instance of the related user.
	 *
	 * @param 	boolean  $recipient  True to get an instance of the recipient user.
	 *
	 * @return 	VCMChatUser
	 */
	public function getUser($recipient = false)
	{
		$client = null;

		if ($recipient)
		{
			// negate the value of the client (0: admin, 1: site)
			$app = JFactory::getApplication();
			if (method_exists($app, 'isClient'))
			{
				$client = $app->isClient('administrator') ? 0 : 1;
			}
			else
			{
				$client = $app->isAdmin() ? 0 : 1;
			}
		}

		return VCMChatUser::getInstance($this->id_order, $client);
	}

	/**
	 * Check here if we are uploading a supported attachment.
	 * Children classes might inherit this method to specify
	 * their own supported types.
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

		/**
		 * In case this method is not overwritten, accept only the following types:
		 *
		 * - IMAGE (image/*)
		 * - TXT (text/plain)
		 * - MD (text/markdown)
		 * - PDF (application/pdf)
		 * - ZIP (application/zip)
		 */
		return preg_match("/^image\/.+|application\/(?:pdf|zip)|text\/(?:plain|markdown)$/", $file['type']);
	}

	/**
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
		if (is_string($data))
		{
			// decode JSON string
			$data = (object) json_decode($data);
		}
		else
		{
			// cast data to object as we might have an array
			$data = (object) $data;
		}

		// do nothing here

		return $data;
	}

	/**
	 * Children classes can inherit this method and return true
	 * in case they support CHAT notifications.
	 *
	 * @return 	boolean  Always false.
	 */
	public function supportNotifications()
	{
		return false;
	}

	/**
	 * Downloads all threads and their related messages for the current
	 * VBO booking ID. Children classes must declare this method.
	 * 
	 * @return 	object 	The number of new threads and new messages stored.
	 */
	abstract protected function downloadThreads();

	/**
	 * Abstract method used to inform e4jConnect that the last-read point has changed.
	 *
	 * @param 	object 	 $message 	The message object. Thread details
	 * 								can be accessed through the "thread" property.
	 * 
	 * @return 	boolean  True on success, false otherwise.
	 */
	abstract protected function notifyReadingPoint($message);

	/**
	 * Sends a new message to the recipient by making the request to e4jConnect.
	 * The new thread and message should be immediately stored onto the db.
	 * 
	 * @param 	VCMChatMessage 	$message 	The message object to be sent.
	 * 
	 * @return 	mixed 			The stored thread and message on success, false otherwise.
	 */
	abstract public function send(VCMChatMessage $message);

	/**
	 * Sends a reply to the recipient by making the request to e4jConnect.
	 * The new message should be immediately stored onto the db.
	 * 
	 * @param 	VCMChatMessage 	$message 	The message object to be sent.
	 * 
	 * @return 	mixed 			The stored thread and message on success, false otherwise.
	 */
	abstract public function reply(VCMChatMessage $message);
}
