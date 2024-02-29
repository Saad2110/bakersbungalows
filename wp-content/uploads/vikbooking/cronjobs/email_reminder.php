<?php
/**
 * @package     VikBooking
 * @subpackage  com_vikbooking
 * @author       Alessio Gaggii - E4J srl
 * @copyright   Copyright (C) 2022 E4J srl. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://vikwp.com
 */

defined('ABSPATH') or die('No script kiddies please!');

class VikCronJob
{
	public $cron_id;
	public $params;
	public $debug;
	private $checktype;
	private $use_date;
	private $cron_data;
	private $flag_char;
	private $exec_flag_char;
	public $log;
	
	/**
	 * Defines the parameters of the cron job.
	 * 
	 * @return 	array
	 */
	public static function getAdminParameters()
	{
		$checktype = array(
			'checkin'  => JText::_('VBOCRONSMSREMPARAMCTYPEA'),
			'payment'  => JText::_('VBOCRONSMSREMPARAMCTYPEB'),
			'checkout' => JText::_('VBOCRONSMSREMPARAMCTYPEC'),
		);
		/**
		 * If VCM is installed, we allow the type "leave a review"
		 * 
		 * @since 	1.13 (J) - 1.3.0 (WP)
		 */
		if (is_file(VCM_SITE_PATH . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'lib.vikchannelmanager.php')) {
			$checktype['review'] = JText::_('VBOCRONREMTYPEREVIEW');
		}

		/**
		 * Load all conditional text special tags.
		 * 
		 * @since 	1.14 (J) - 1.4.0 (WP)
		 */
		$extra_btns = array();
		$condtext_tags = VikBooking::getConditionalRulesInstance()->getSpecialTags();
		if (count($condtext_tags)) {
			$condtext_tags = array_keys($condtext_tags);
			foreach ($condtext_tags as $tag) {
				array_push($extra_btns, '<button type="button" class="btn vbo-condtext-specialtag-btn" onclick="setCronTplTag(\'tpl_text\', \'' . $tag . '\');">' . $tag . '</button>');
			}
		}

		/**
		 * Build a list of all special tags for the visual editor.
		 * 
		 * @since 	1.15.0 (J) - 1.5.0 (WP)
		 */
		$special_tags_base = array(
			'{customer_name}',
			'{customer_pin}',
			'{booking_id}',
			'{checkin_date}',
			'{checkout_date}',
			'{num_nights}',
			'{rooms_booked}',
			'{tot_adults}',
			'{tot_children}',
			'{tot_guests}',
			'{total}',
			'{total_paid}',
			'{remaining_balance}',
			'{booking_link}',
		);

		$special_tags_base_html = '';
		foreach ($special_tags_base as $sp_tag) {
			$special_tags_base_html .= '<button type="button" class="btn" onclick="setCronTplTag(\'tpl_text\', \'' . $sp_tag . '\');">' . $sp_tag . '</button>' . "\n";
		}

		$editor_btns = $special_tags_base;
		if (count($condtext_tags)) {
			$editor_btns = array_merge($editor_btns, $condtext_tags);
		}

		// build default HTML text
		$def_html_txt = '';
		$sitelogo 	  = VikBooking::getSiteLogo();
		$company_name = VikBooking::getFrontTitle();
		$def_html_txt .= '<h1 style="text-align: center; font-family: inconsolata;">' . $company_name . '</h1>' . "\n";
		if (!empty($sitelogo) && is_file(VBO_ADMIN_PATH . DIRECTORY_SEPARATOR . 'resources'. DIRECTORY_SEPARATOR . $sitelogo)) {
			$def_html_txt .= '<p style="text-align: center;"><img src="' . VBO_ADMIN_URI . 'resources/' . $sitelogo . '" alt="' . htmlentities($company_name) . '" /></p>' . "\n";
		}
		$def_html_txt .= '<h4>Dear {customer_name},</h4>' . "\n";
		$def_html_txt .= '<p><br></p>' . "\n";
		$def_html_txt .= '<p>This is an automated message to remind you the check-in time for your stay: {checkin_date} at 14:00.</p>' . "\n";
		$def_html_txt .= '<p>You can always drop your luggage at the Hotel, should you arrive earlier.</p>' . "\n";
		$def_html_txt .= '<p><br></p>' . "\n";
		$def_html_txt .= '<p><br></p>' . "\n";
		$def_html_txt .= '<p>Thank you.</p>' . "\n";
		$def_html_txt .= '<p>' . $company_name . '</p>' . "\n";

		return array(
			'cron_lbl' => array(
				'type' => 'custom',
				'label' => '',
				'html' => '<h4><i class="vboicn-mail4"></i><i class="vboicn-alarm"></i>eMail Reminder</h4>'
			),
			'checktype' => array(
				'type' => 'select',
				'label' => JText::_('VBOCRONSMSREMPARAMCTYPE'),
				'options' => $checktype
			),
			'remindbefored' => array(
				'type' => 'number',
				'label' => JText::_('VBOCRONSMSREMPARAMBEFD').'//'.JText::_('VBOCRONSMSREMPARAMCTYPECHELP'),
				'default' => '2',
				'attributes' => array('style' => 'width: 60px !important;')
			),
			/**
			 * This parameter serves to include bookings generated less days in advance
			 * than the above parameter "remindbefored" from the check-in date. In short,
			 * if days in advance is set to 7 and a booking is made 3 days before the
			 * check-in date, with this new setting it is possible to choose if this
			 * booking should be notified or not. We keep this enabled by default.
			 * 
			 * @since 	1.15.0 (J) - 1.5.0 (WP)
			 */
			'less_days_advance' => array(
				'type' => 'select',
				'label' => JText::_('VBO_CRON_DADV_LOWER'),
				'help' => JText::_('VBO_CRON_DADV_LOWER_HELP'),
				'options' => array(
					1 => JText::_('VBYES'),
					0 => JText::_('VBNO'),
				),
				'default' => 1,
			),
			/**
			 * This param serves to include, exclude or consider-only OTA bookings.
			 * 
			 * @since 	1.15.0 (J) - 1.5.0 (WP)
			 */
			'ota_res' => array(
				'type' => 'select',
				'label' => JText::_('VBO_CRON_OTA_RES'),
				'help' => JText::_('VBO_CRON_OTA_RES_HELP'),
				'options' => array(
					1 => JText::_('VBO_INCLUDED'),
					2 => JText::_('VBO_INCLUDE_ONLY'),
					0 => JText::_('VBO_EXCLUDED'),
				),
				'default' => 1,
			),
			'test' => array(
				'type' => 'select',
				'label' => JText::_('VBOCRONSMSREMPARAMTEST').'//'.JText::_('VBOCRONEMAILREMPARAMTESTHELP'),
				'options' => array('OFF', 'ON')
			),
			'subject' => array(
				'type' => 'text',
				'label' => JText::_('VBOCRONEMAILREMPARAMSUBJECT'),
				'default' => JText::_('VBOCRONEMAILREMPARAMSUBJECT'),
				'attributes' => array('style' => '')
			),
			'tpl_text' => array(
				'type' => 'visual_html',
				'label' => JText::_('VBOCRONSMSREMPARAMTEXT'),
				'default' => $def_html_txt,
				'attributes' => array(
					'id' => 'tpl_text',
					'style' => 'width: 70%; height: 150px;'
				),
				'editor_opts' => array(
					'modes' => array(
						'text',
						'modal-visual',
					),
				),
				'editor_btns' => $editor_btns,
			),
			'buttons' => array(
				'type' => 'custom',
				'label' => '',
				'html' => '<div class="btn-toolbar vbo-smstpl-toolbar vbo-cronparam-cbar" style="margin-top: -10px;">
					<div class="btn-group pull-left vbo-smstpl-bgroup vik-contentbuilder-textmode-sptags">
						' . $special_tags_base_html . '
						' . implode("\n", $extra_btns) . '
					</div>
				</div>
				<script type="text/javascript">
				function setCronTplTag(taid, tpltag) {
					var tplobj = document.getElementById(taid);
					if (tplobj != null) {
						var start = tplobj.selectionStart;
						var end = tplobj.selectionEnd;
						tplobj.value = tplobj.value.substring(0, start) + tpltag + tplobj.value.substring(end);
						tplobj.selectionStart = tplobj.selectionEnd = start + tpltag.length;
						tplobj.focus();
						jQuery("#" + taid).trigger("change");
					}
				}
				</script>'
			),
			'help' => array(
				'type' => 'custom',
				'label' => '',
				'html' => '<p class="vbo-cronparam-suggestion"><i class="vboicn-lifebuoy"></i>'.JText::_('VBOCRONSMSREMHELP').'</p>'
			),
		);
	}
	
	public function __construct($cron_id, $params = array())
	{
		$this->cron_id = $cron_id;
		$this->params = $params;
		$this->params['test'] = $params['test'] == 'ON' ? true : false;
		$this->params['ota_res'] = !isset($this->params['ota_res']) ? 1 : (int)$this->params['ota_res'];
		$this->debug = false; //debug is set to true by the back-end manual execution to print the debug messages
		$this->checktype = empty($params['checktype']) ? 'checkin' : $params['checktype'];
		$this->use_date = time();
		$this->cron_data = array();
		$this->flag_char = array();
		$this->exec_flag_char = array();
		$this->params['remindbefored'] = intval($this->params['remindbefored']);
	}
	
	public function run()
	{
		$dbo = JFactory::getDbo();
		$this->getCronData();

		$start_ts = $this->use_date = mktime(0, 0, 0, date('n'), ($this->checktype == 'checkout' || $this->checktype == 'review' ? ((int)date('j') - $this->params['remindbefored']) : ((int)date('j') + $this->params['remindbefored'])), date('Y'));
		$end_ts = mktime(23, 59, 59, date('n'), ($this->checktype == 'checkout' || $this->checktype == 'review' ? ((int)date('j') - $this->params['remindbefored']) : ((int)date('j') + $this->params['remindbefored'])), date('Y'));

		/**
		 * In case of check-in reminder with like 3 days in advance, we cannot allow to skip all
		 * bookings with a lower last-minute advance period, like a reservation made "today" for
		 * "tomorrow" would never be notified. For this reason, the flag for checking the bookings
		 * notified should be set to today, and so should the start date be set.
		 * 
		 * @since 	1.15.0 (J) - 1.5.0 (WP)
		 */
		$multi_flags_check = array();
		if ($this->checktype == 'checkin' && $this->params['remindbefored'] > 1 && !empty($this->params['less_days_advance'])) {
			$start_ts = $this->use_date = mktime(0, 0, 0, date('n'), date('j'), date('Y'));
			// push all date-flags that could contain the notification of certain bookings
			for ($i = 1; $i <= $this->params['remindbefored']; $i++) {
				array_push($multi_flags_check, mktime(0, 0, 0, date('n'), ((int)date('j') - $i), date('Y')));
			}
		}

		if ($this->debug) {
			echo '<p>Reading bookings with '.($this->checktype == 'checkout' || $this->checktype == 'review' ? 'check-out' : 'check-in').' datetime between: '.date('c', $this->use_date).' - '.date('c', $end_ts).'</p>';
			if ($this->params['ota_res'] === 2) {
				echo '<p>Including only OTA reservations</p>';
			} elseif ($this->params['ota_res'] === 0) {
				echo '<p>Excluding OTA reservations</p>';
			}
		}
		$q = "SELECT `o`.*,`co`.`idcustomer`,CONCAT_WS(' ',`c`.`first_name`,`c`.`last_name`) AS `customer_name`,`c`.`pin` AS `customer_pin`,`nat`.`country_name` FROM `#__vikbooking_orders` AS `o` LEFT JOIN `#__vikbooking_customers_orders` `co` ON `co`.`idorder`=`o`.`id` LEFT JOIN `#__vikbooking_customers` `c` ON `c`.`id`=`co`.`idcustomer` LEFT JOIN `#__vikbooking_countries` `nat` ON `nat`.`country_3_code`=`o`.`country` WHERE `o`.`".($this->checktype == 'checkout' || $this->checktype == 'review' ? 'checkout' : 'checkin')."`>=".(int)$start_ts." AND `o`.`".($this->checktype == 'checkout' || $this->checktype == 'review' ? 'checkout' : 'checkin')."`<=".(int)$end_ts." ".($this->checktype == 'checkin' || $this->checktype == 'checkout' || $this->checktype == 'review' ? "AND `o`.`status`='confirmed'" : "AND `o`.`status`!='cancelled' AND `o`.`total` > 0 AND `o`.`totpaid` > 0 AND `o`.`totpaid`<`o`.`total` AND `o`.`idorderota` IS NULL").";";
		$dbo->setQuery($q);
		$dbo->execute();
		if (!$dbo->getNumRows()) {
			if ($this->debug) {
				echo '<span>No bookings to notify.</span>';
			}

			return true;
		}
		$bookings = $dbo->loadAssocList();

		/**
		 * Apply filter for OTA bookings.
		 * 
		 * @since 	1.15.0 (J) - 1.5.0 (WP)
		 */
		if ($this->params['ota_res'] !== 1) {
			foreach ($bookings as $k => $booking) {
				$is_ota_res = (!empty($booking['channel']) && !empty($booking['idorderota']));
				if ($this->params['ota_res'] === 2 && !$is_ota_res) {
					// not an OTA reservation, and only OTA reservations should be included
					unset($bookings[$k]);
					continue;
				}
				if ($this->params['ota_res'] === 0 && $is_ota_res) {
					// it's an OTA reservation, and only website reservations should be included
					unset($bookings[$k]);
					continue;
				}
			}
			// check if we still got bookings left
			if (!count($bookings)) {
				if ($this->debug) {
					echo '<span>No more bookings to notify.</span>';
				}
				return true;
			}
		}

		$this->exec_flag_char[$this->use_date] = array();
		if ($this->debug) {
			echo '<p>Bookings to be notified: '.count($bookings).'</p>';
		}
		$log_str = '';
		$def_subject = $this->params['subject'];
		foreach ($bookings as $booking) {
			if (array_key_exists($this->use_date, $this->flag_char) && array_key_exists($booking['id'], $this->flag_char[$this->use_date])) {
				if ($this->debug) {
					echo '<span>Booking ID '.$booking['id'].' ('.$booking['customer_name'].') was already notified. Skipped.</span>';
				}
				continue;
			}
			// make sure this booking was not notified days ago for their last-minute check-in
			$should_skip = false;
			foreach ($multi_flags_check as $check_date) {
				if (isset($this->flag_char[$check_date]) && isset($this->flag_char[$check_date][$booking['id']])) {
					$should_skip = true;
					if ($this->debug) {
						echo '<span>Booking ID '.$booking['id'].' ('.$booking['customer_name'].') was already notified on another day. Skipped.</span>';
					}
					break;
				}
			}
			if ($should_skip === true) {
				continue;
			}
			// make sure a review for this booking does not exist already
			if ($this->checktype == 'review' && $this->hasReview($booking['id'])) {
				if ($this->debug) {
					echo '<span>Review found for Booking ID '.$booking['id'].' ('.$booking['customer_name'].'). Skipped.</span>';
				}
				continue;
			}
			//
			$message = $this->params['tpl_text'];
			$this->params['subject'] = $def_subject;
			//language translation
			if (!empty($booking['lang'])) {
				$vbo_tn = VikBooking::getTranslator();
				$vbo_tn::$force_tolang = null;
				$lang = JFactory::getLanguage();
				if ($lang->getTag() != $booking['lang']) {
					if (defined('ABSPATH')) {
						// wp
						$lang->load('com_vikbooking', VIKBOOKING_SITE_LANG, $booking['lang'], true);
						$lang->load('com_vikbooking', VIKBOOKING_ADMIN_LANG, $booking['lang'], true);
					} else {
						// J
						$lang->load('com_vikbooking', JPATH_SITE, $booking['lang'], true);
						$lang->load('com_vikbooking', JPATH_ADMINISTRATOR, $booking['lang'], true);
					}
				}
				if ($vbo_tn->getDefaultLang() != $booking['lang']) {
					// force the translation to start because contents should be translated
					$vbo_tn::$force_tolang = $booking['lang'];
				}
				$cron_tn = $this->cron_data;
				$vbo_tn->translateContents($cron_tn, '#__vikbooking_cronjobs', array(), array(), $booking['lang']);
				$params_tn = json_decode($cron_tn['params'], true);
				if (is_array($params_tn) && array_key_exists('tpl_text', $params_tn)) {
					$message = $params_tn['tpl_text'];
				}
				if (is_array($params_tn) && array_key_exists('subject', $params_tn)) {
					$this->params['subject'] = $params_tn['subject'];
				}
			}
			//
			$send_res = $this->params['test'] === true ? false : $this->sendEmailReminder($booking, $message);
			if ($this->debug) {
				echo '<span>Result for sending eMail to '.$booking['custmail'].' - Booking ID '.$booking['id'].' ('.$booking['customer_name'].(!empty($booking['lang']) ? ' '.$booking['lang'] : '').'): '.($send_res !== false ? '<i class="vboicn-checkmark"></i>Success' : '<i class="vboicn-cancel-circle"></i>Failure').($this->params['test'] === true ? ' (Test Mode ON)' : '').'</span>';
			}
			if ($send_res !== false) {
				$log_str .= 'eMail sent to '.$booking['custmail'].' - Booking ID '.$booking['id'].' ('.$booking['customer_name'].(!empty($booking['lang']) ? ' '.$booking['lang'] : '').')'."\n";
				//store in execution flag that this booking ID was notified
				$this->exec_flag_char[$this->use_date][$booking['id']] = (int)$send_res;
			}
		}

		if (!empty($log_str)) {
			$this->log = $log_str;
		}

		return true;
	}
	
	/**
	 * This function is called after the cron has been executed.
	 */
	public function afterRun($extra = array())
	{
		$dbo = JFactory::getDbo();
		$log_str = '';
		if (strlen($this->log) && count($this->cron_data) > 0) {
			$log_str = date('c')."\n".$this->log."\n----------\n".$this->cron_data['logs'];
		}
		$new_flag_str = '';
		if (count($this->exec_flag_char) && count($this->exec_flag_char[$this->use_date])) {
			// array_merge does not preserve numeric keys. The union (+) operator does
			$this->flag_char = !is_array($this->flag_char) ? array() : $this->flag_char;
			$new_flag_arr = $this->exec_flag_char + $this->flag_char;
			if (count($new_flag_arr) > 3) {
				// keep max 3 days
				$tot_dates = 1;
				foreach ($new_flag_arr as $flag_date => $flag) {
					if ($tot_dates > 3) {
						unset($new_flag_arr[$flag_date]);
					}
					$tot_dates++;
				}
			}
			$new_flag_str = json_encode($new_flag_arr);
		}
		// update cron record
		$q = "UPDATE `#__vikbooking_cronjobs` SET `last_exec`=".time().(!empty($log_str) ? ", `logs`=".$dbo->quote($log_str) : "").(!empty($new_flag_str) ? ", `flag_char`=".$dbo->quote($new_flag_str) : "")." WHERE `id`=".(int)$this->cron_id.";";
		$dbo->setQuery($q);
		$dbo->execute();
	}

	private function getCronData()
	{
		$dbo = JFactory::getDbo();
		$q = "SELECT * FROM `#__vikbooking_cronjobs` WHERE `id`=".(int)$this->cron_id.";";
		$dbo->setQuery($q);
		$dbo->execute();
		if ($dbo->getNumRows() == 1) {
			$this->cron_data = $dbo->loadAssoc();
			if (!empty($this->cron_data['flag_char'])) {
				$this->flag_char = json_decode($this->cron_data['flag_char'], true);
				$this->flag_char = !is_array($this->flag_char) ? array() : $this->flag_char;
			}
		}
	}

	private function sendEmailReminder($booking, $message)
	{
		if (!class_exists('VboApplication')) {
			require_once(VBO_ADMIN_PATH . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'jv_helper.php');
		}
		$vbo_app = new VboApplication;
		if (empty($booking['id']) || empty($booking['custmail'])) {
			return false;
		}
		$dbo = JFactory::getDbo();
		$booking_rooms = array();
		$q = "SELECT `or`.*,`r`.`name` AS `room_name`,`r`.`params` AS `room_params` FROM `#__vikbooking_ordersrooms` AS `or` LEFT JOIN `#__vikbooking_rooms` `r` ON `r`.`id`=`or`.`idroom` WHERE `or`.`idorder`=".(int)$booking['id'].";";
		$dbo->setQuery($q);
		$dbo->execute();
		if ($dbo->getNumRows() > 0) {
			$booking_rooms = $dbo->loadAssocList();
		}
		$admin_sendermail = VikBooking::getSenderMail();
		$vbo_tn = VikBooking::getTranslator();
		$vbo_tn->translateContents($booking_rooms, '#__vikbooking_rooms', array('id' => 'idroom', 'name' => 'room_name'));
		$message = $this->parseCustomerEmailTemplate($message, $booking, $booking_rooms, $vbo_tn);
		if (empty($message)) {
			return false;
		}
		$is_html = (strpos($message, '<') !== false || strpos($message, '</') !== false);
		if ($is_html && !preg_match("/(<\/?br\/?>)+/", $message)) {
			// when no br tags found, apply nl2br
			$message = nl2br($message);
		}
		$vbo_app->sendMail($admin_sendermail, $admin_sendermail, $booking['custmail'], $admin_sendermail, $this->params['subject'], $message, $is_html);

		return true;
	}

	private function parseCustomerEmailTemplate($message, $booking, $booking_rooms, $vbo_tn = null)
	{
		$tpl = $message;

		/**
		 * Parse all conditional text rules.
		 * 
		 * @since 	1.14 (J) - 1.4.0 (WP)
		 */
		VikBooking::getConditionalRulesInstance()
			->set(array('booking', 'rooms'), array($booking, $booking_rooms))
			->parseTokens($tpl);
		//

		$vbo_df = VikBooking::getDateFormat();
		$df = $vbo_df == "%d/%m/%Y" ? 'd/m/Y' : ($vbo_df == "%m/%d/%Y" ? 'm/d/Y' : 'Y-m-d');
		$tpl = str_replace('{customer_name}', $booking['customer_name'], $tpl);
		$tpl = str_replace('{booking_id}', $booking['id'], $tpl);
		$tpl = str_replace('{checkin_date}', date($df, $booking['checkin']), $tpl);
		$tpl = str_replace('{checkout_date}', date($df, $booking['checkout']), $tpl);
		$tpl = str_replace('{num_nights}', $booking['days'], $tpl);
		$rooms_booked = array();
		$tot_adults = 0;
		$tot_children = 0;
		$tot_guests = 0;
		foreach ($booking_rooms as $broom) {
			if (array_key_exists($broom['room_name'], $rooms_booked)) {
				$rooms_booked[$broom['room_name']] += 1;
			} else {
				$rooms_booked[$broom['room_name']] = 1;
			}
			$tot_adults += (int)$broom['adults'];
			$tot_children += (int)$broom['children'];
			$tot_guests += ((int)$broom['adults'] + (int)$broom['children']);
		}
		$tpl = str_replace('{tot_adults}', $tot_adults, $tpl);
		$tpl = str_replace('{tot_children}', $tot_children, $tpl);
		$tpl = str_replace('{tot_guests}', $tot_guests, $tpl);
		$rooms_booked_quant = array();
		foreach ($rooms_booked as $rname => $quant) {
			$rooms_booked_quant[] = ($quant > 1 ? $quant.' ' : '').$rname;
		}
		$tpl = str_replace('{rooms_booked}', implode(', ', $rooms_booked_quant), $tpl);
		$tpl = str_replace('{total}', VikBooking::numberFormat($booking['total']), $tpl);
		$tpl = str_replace('{total_paid}', VikBooking::numberFormat($booking['totpaid']), $tpl);
		$remaining_bal = $booking['total'] - $booking['totpaid'];
		$tpl = str_replace('{remaining_balance}', VikBooking::numberFormat($remaining_bal), $tpl);
		$tpl = str_replace('{customer_pin}', $booking['customer_pin'], $tpl);

		$use_sid = empty($booking['sid']) && !empty($booking['idorderota']) ? $booking['idorderota'] : $booking['sid'];
		
		$book_link 	= '';
		if (defined('ABSPATH')) {
			/**
			 * @wponly 	Rewrite order view URI
			 */
			$model 		= JModel::getInstance('vikbooking', 'shortcodes', 'admin');
			$itemid 	= $model->best('booking');
			if ($itemid) {
				$book_link = JRoute::_("index.php?option=com_vikbooking&Itemid={$itemid}&view=booking&sid={$use_sid}&ts={$booking['ts']}", false);
			}
		} else {
			// J
			$bestitemid = VikBooking::findProperItemIdType(array('booking'));
			$book_link = VikBooking::externalroute("index.php?option=com_vikbooking&view=booking&sid=" . $use_sid . "&ts=" . $booking['ts'], false, (!empty($bestitemid) ? $bestitemid : null));
		}
		$tpl = str_replace('{booking_link}', $book_link, $tpl);

		/**
		 * Rooms Distinctive Features parsing
		 * 
		 * @since 	1.10 - Patch August 29th 2018
		 */
		preg_match_all('/\{roomfeature ([a-zA-Z0-9 ]+)\}/U', $tpl, $matches);
		if (isset($matches[1]) && is_array($matches[1]) && @count($matches[1]) > 0) {
			foreach ($matches[1] as $reqf) {
				$rooms_features = array();
				foreach ($booking_rooms as $broom) {
					$distinctive_features = array();
					$rparams = json_decode($broom['room_params'], true);
					if (array_key_exists('features', $rparams) && count($rparams['features']) > 0 && array_key_exists('roomindex', $broom) && !empty($broom['roomindex']) && array_key_exists($broom['roomindex'], $rparams['features'])) {
						$distinctive_features = $rparams['features'][$broom['roomindex']];
					}
					if (!count($distinctive_features)) {
						continue;
					}
					$feature_found = false;
					foreach ($distinctive_features as $dfk => $dfv) {
						if (stripos($dfk, $reqf) !== false) {
							$feature_found = $dfk;
							if (strlen(trim($dfk)) == strlen(trim($reqf))) {
								break;
							}
						}
					}
					if ($feature_found !== false && @strlen($distinctive_features[$feature_found])) {
						$rooms_features[] = $distinctive_features[$feature_found];
					}
				}
				if (count($rooms_features)) {
					$rpval = implode(', ', $rooms_features);
				} else {
					$rpval = '';
				}
				$tpl = str_replace("{roomfeature ".$reqf."}", $rpval, $tpl);
			}
		}
		//

		return $tpl;
	}

	/**
	 * Checks whether a booking has a review.
	 * 
	 * @param 	int 	$bid 	the VBO reservation ID.
	 * 
	 * @return 	boolean 		true if exists (stop reminder), false otherwise.
	 * 
	 * @since 	1.13 (J) - 1.3.0 (WP)
	 */
	private function hasReview($bid)
	{
		if (empty($bid)) {
			// do not send a reminder
			return true;
		}

		if (!is_file(VCM_SITE_PATH . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'lib.vikchannelmanager.php')) {
			// do not send a reminder
			return true;
		}

		$dbo = JFactory::getDbo();
		$exists = false;
		try {
			$q = "SELECT `id` FROM `#__vikchannelmanager_otareviews` WHERE `idorder`=" . (int)$bid . ";";
			$dbo->setQuery($q);
			$dbo->execute();
			if ($dbo->getNumRows()) {
				$exists = true;
			}
		} catch (Exception $e) {
			// something went wrong, do not send a reminder
			$exists = true;
		}

		return $exists;
	}	
}
