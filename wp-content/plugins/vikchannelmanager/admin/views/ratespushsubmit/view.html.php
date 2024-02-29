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

jimport('joomla.application.component.view');

class VikChannelManagerViewRatespushsubmit extends JViewUI {
	function display($tpl = null) {
		require_once(VBO_SITE_PATH . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "lib.vikbooking.php");
				
		$this->addToolBar();
		
		VCM::load_css_js();
		VCM::loadDatePicker();

		$dbo = JFactory::getDbo();
		$mainframe = JFactory::getApplication();
		$session = JFactory::getSession();

		//True if coming from the oversight, coming from ratespush otherwise
		$multi = VikRequest::getInt('multi', '', 'request');
		$multi = $multi > 0 ? true : false;
		$multi_intervals = array();
		//

		$rooms = VikRequest::getVar('rooms', array());
		$from = VikRequest::getVar('from', array());
		$to = VikRequest::getVar('to', array());
		$pricetypes = VikRequest::getVar('pricetypes', array());
		$defrates = VikRequest::getVar('defrates', array());
		$rmods = VikRequest::getVar('rmods', array());
		$rmodsop = VikRequest::getVar('rmodsop', array());
		$rmodsamount = VikRequest::getVar('rmodsamount', array());
		$rmodsval = VikRequest::getVar('rmodsval', array());
		$rplans = VikRequest::getVar('rplans', array());
		$cur_rplans = VikRequest::getVar('cur_rplans', array());
		$rplanarimode = VikRequest::getVar('rplanarimode', array());
		$channels = VikRequest::getVar('channels', array());

		/**
		 * Rate alterations can be defined at channel level.
		 * 
		 * @since 	1.8.3
		 */
		$rmod_channels = VikRequest::getVar('rmod_channels', array());

		$err_goto = $multi === true ? 'oversight' : 'ratespush';
		
		$max_nodes = VikRequest::getInt('max_nodes', '', 'request');
		$max_nodes = empty($max_nodes) || $max_nodes <= 0 ? 10 : $max_nodes;
		$max_channels = 1;

		// Bulk Rates Advanced Parameters (Booking.com Derived prices for occupancy rules)
		$bcom_derocc = VikRequest::getInt('bcom_derocc', '', 'request');
		// Bulk Rates Advanced Parameters for altering the occupancy pricing rules
		$alter_occrules = VikRequest::getInt('alter_occrules', 0, 'request');
		// Bulk Rates Advanced Parameters for sending min/max advance reservation time
		$min_max_adv_res = VikRequest::getInt('min_max_adv_res', 0, 'request');
		// Bulk Rates Advanced Parameters to ignore LOS records with Airbnb
		$airbnb_no_los = VikRequest::getInt('airbnb_no_los', 0, 'request');
		//
		$bulk_rates_adv_params = VikChannelManager::getBulkRatesAdvParams();
		$bulk_rates_adv_params['bcom_derocc'] 	  = $bcom_derocc;
		$bulk_rates_adv_params['alter_occrules']  = $alter_occrules;
		$bulk_rates_adv_params['min_max_adv_res'] = $min_max_adv_res;
		$bulk_rates_adv_params['airbnb_no_los']   = $airbnb_no_los;
		VikChannelManager::updateBulkRatesAdvParams($bulk_rates_adv_params);
		//

		$tot_rooms = count($rooms);
		if (!$multi) {
			if (!($tot_rooms > 0) || !(count($from) > 0) || !(count($to) > 0) || !(count($channels) > 0) || $tot_rooms != count($from) || $tot_rooms != count($to) || $tot_rooms != count($pricetypes) || $tot_rooms != count($defrates) || $tot_rooms != count($rmods) || $tot_rooms != count($rplans)) {
				VikError::raiseWarning('', JText::_('VCMRATESPUSHERRNODATA'));
				$mainframe->redirect("index.php?option=com_vikchannelmanager&task=".$err_goto);
				exit;
			}

			/**
			 * Update max date in the future for rates inventory.
			 * 
			 * @since 	1.7.1
			 */
			if (!VikRequest::getInt('e4j_debug', 0)) {
				$maxts = strtotime(max($to));
				$currentdates = VikChannelManager::getInventoryMaxFutureDates();
				$currentdates['rates'] = $maxts;
				VikChannelManager::setInventoryMaxFutureDates($currentdates);
			}
			//
		} else {
			foreach ($rooms as $idroom => $room) {
				if (!(count($room['details']) > 0) || empty($room['pricetype']) || empty($room['defrates']) || !(count($room['channels']) > 0) || !(count($room['rplans']) > 0)) {
					unset($rooms[$idroom]);
					continue;
				}
			}
			if (!(count($rooms) > 0)) {
				VikError::raiseWarning('', JText::_('VCMRATESPUSHERRNODATA'));
				$mainframe->redirect("index.php?option=com_vikchannelmanager&task=".$err_goto);
				exit;
			}
			//Compose arrays as if it was coming from ratespush
			$multi_intervals = $rooms;
			$rooms = array_keys($multi_intervals);
			foreach ($multi_intervals as $idroom => $room) {
				$pricetypes[] = $room['pricetype'];
				$defrates[] = $room['defrates'];
				$vbo_channel_key = array_search('vbo', $room['channels']);
				if ($vbo_channel_key !== false) {
					//Cannot use "vbo" as a channel ID for the query (oversight)
					unset($room['channels'][$vbo_channel_key]);
				}
				$channels[$idroom] = $room['channels'];
				$rplans[$idroom] = $room['rplans'];
				if (array_key_exists('cur_rplans', $room)) {
					$cur_rplans[$idroom] = $room['cur_rplans'];
				}
				if (array_key_exists('rplanarimode', $room)) {
					$rplanarimode[$idroom] = $room['rplanarimode'];
				}
			}
			//
		}
		
		$channels_mapped = false;
		$q = "SELECT `id`,`name`,`units` FROM `#__vikbooking_rooms` WHERE `id` IN (".implode(',', $rooms).") ORDER BY `#__vikbooking_rooms`.`name` ASC;";
		$dbo->setQuery($q);
		$dbo->execute();
		if ( $dbo->getNumRows() > 0 ) {
			$rows = $dbo->loadAssocList();
			foreach( $rows as $k => $r ) {
				if (!array_key_exists($r['id'], $channels) || !(count($channels[$r['id']]) > 0)) {
					foreach ($rooms as $rk => $rv) {
						if ($rv == $r['id']) {
							unset($from[$rk]);
							unset($to[$rk]);
							break;
						}
					}
					unset($rows[$k]);
					continue;
				}
				$rows[$k]['channels'] = array();
				if (!(count($channels[$r['id']]) > 0)) {
					//Only the channel "Website/IBE" was selected for this room
					foreach ($rooms as $rk => $rv) {
						if ($rv == $r['id']) {
							unset($from[$rk]);
							unset($to[$rk]);
							break;
						}
					}
					unset($rows[$k]);
					continue;
				}
				$q = "SELECT * FROM `#__vikchannelmanager_roomsxref` WHERE `idroomvb`='".$r['id']."' AND `idchannel` IN (".implode(',', $channels[$r['id']]).");";
				$dbo->setQuery($q);
				$dbo->execute();
				if ($dbo->getNumRows() > 0) {
					$channels_data = $dbo->loadAssocList();
					$max_channels = count($channels_data) > $max_channels ? count($channels_data) : $max_channels;
					foreach ($channels_data as $ch_data) {
						$rows[$k]['channels'][$ch_data['idchannel']] = $ch_data;
					}
					$channels_mapped = true;
				} else {
					foreach ($rooms as $rk => $rv) {
						if ($rv == $r['id']) {
							unset($from[$rk]);
							unset($to[$rk]);
							break;
						}
					}
					unset($rows[$k]);
				}
			}
		} else {
			$rows = array();
		}

		if (!$multi) {
			foreach ($from as $kf => $vf) {
				$fromdate = strtotime($vf);
				$todate = strtotime($to[$kf]);
				if (empty($fromdate) || empty($todate) || $todate < $fromdate) {
					unset($from[$kf]);
					unset($to[$kf]);
					foreach ($rows as $k => $r) {
						if ($rooms[$kf] == $r['id']) {
							unset($rows[$k]);
							break;
						}
					}
				}
			}
		}

		if ($channels_mapped !== true || !(count($rows) > 0)) {
			VikError::raiseWarning('', JText::_('VCMRATESPUSHERRNODATA').'.');
			$mainframe->redirect("index.php?option=com_vikchannelmanager&task=".$err_goto);
			exit;
		}

		$ratesinventory = array();
		$pushdata = array();
		$from_to = array();
		$glob_minlos = VikBooking::getDefaultNightsCalendar();
		$glob_minlos = $glob_minlos < 1 ? 1 : $glob_minlos;
		$price_glob_minlos = $glob_minlos;
		
		if (!$multi) {
			//RatesPush must calculate the nodes
			foreach ($rooms as $rk => $roomid) {
				if (!isset($channels[$roomid])) {
					continue;
				}
				// allows Min LOS at rate-plan level. Check if this rate plan has a Min LOS defined
				$glob_minlos = $price_glob_minlos;
				$pricetype_info = VikBooking::getPriceInfo($pricetypes[$rk]);
				if (is_array($pricetype_info) && isset($pricetype_info['minlos']) && (int)$pricetype_info['minlos'] >= 1) {
					/**
					 * Rate plans can define minimum stays equal to 0, in this case we use the global min los.
					 * Instead, if the rate plan has got 1 night or more as minimum stay, the rate plan restriction
					 * will be used. That's why we compare the rate plan min los as >= 1.
					 * 
					 * @since 	VBO 1.15.0 (J) - 1.5.0 (WP)
					 */
					$glob_minlos = (int)$pricetype_info['minlos'];
				}

				$ratesinventory[$roomid] = array();
				$pushdata[$roomid] = array(
					'pricetype' => $pricetypes[$rk],
					'defrate' => $defrates[$rk],
					'rmod' => $rmods[$rk],
					'rmodop' => $rmodsop[$rk],
					'rmodamount' => $rmodsamount[$rk],
					'rmodval' => $rmodsval[$rk],
					'rplans' => array(),
					'cur_rplans' => array(),
					'rplanarimode' => array(),
				);

				/**
				 * We need to check if rate alterations were defined at channel level
				 * 
				 * @since 	1.8.3
				 */
				if ((int)$rmods[$rk] > 0 && isset($rmod_channels[$roomid]) && isset($rmod_channels[$roomid][$pricetypes[$rk]])) {
					if (is_array($rmod_channels[$roomid][$pricetypes[$rk]]) && count($rmod_channels[$roomid][$pricetypes[$rk]])) {
						// set single-channel alteration rules for bulk rates cache and for the actual rates upload
						$pushdata[$roomid]['rmod_channels'] = $rmod_channels[$roomid][$pricetypes[$rk]];
					}
				}

				foreach ($channels[$roomid] as $ck => $cv) {
					if (array_key_exists($roomid, $rplans) && array_key_exists($cv, $rplans[$roomid])) {
						$pushdata[$roomid]['rplans'][$cv] = $rplans[$roomid][$cv];
					}
					if (array_key_exists($roomid, $cur_rplans) && array_key_exists($cv, $cur_rplans[$roomid])) {
						$pushdata[$roomid]['cur_rplans'][$cv] = str_replace(':', '', $cur_rplans[$roomid][$cv]);
					}
					if (array_key_exists($roomid, $rplanarimode) && array_key_exists($cv, $rplanarimode[$roomid])) {
						$pushdata[$roomid]['rplanarimode'][$cv] = $rplanarimode[$roomid][$cv];
					}
				}
				$start_ts = strtotime($from[$rk]);
				$end_ts_base = strtotime($to[$rk]);
				$end_ts_info = getdate($end_ts_base);
				$end_ts = mktime(23, 59, 59, date('n', $end_ts_base), date('j', $end_ts_base), date('Y', $end_ts_base));
				$cur_year = date('Y', $start_ts);
				$from_to[$roomid] = date('Y-m-d', $start_ts).'_'.date('Y-m-d', $end_ts);
				//get seasonal prices and restrictions to build the nodes (dates intervals)
				$all_seasons = array();
				$all_restrictions = VikBooking::loadRestrictions(true, array($roomid));
				$q = "SELECT * FROM `#__vikbooking_seasons` WHERE `idrooms` LIKE '%-".$roomid."-%' AND (`idprices` LIKE '%-".$pricetypes[$rk]."-%' OR `idprices`='' OR `idprices` IS NULL);";
				$dbo->setQuery($q);
				$dbo->execute();
				if ($dbo->getNumRows() > 0) {
					$seasons = $dbo->loadAssocList();
					foreach ($seasons as $sk => $s) {
						$now_year = !empty($s['year']) ? $s['year'] : $cur_year;
						if (!empty($s['from']) || !empty($s['to'])) {
							list($sfrom, $sto) = VikBooking::getSeasonRangeTs($s['from'], $s['to'], $now_year);
						} else {
							//VCM 1.6.5 - only weekdays and no dates filter
							list($sfrom, $sto) = array($start_ts, $end_ts);
						}
						$info_sfrom = getdate($sfrom);
						$info_sto = getdate($sto);
						$sfrom = mktime(0, 0, 0, $info_sfrom['mon'], $info_sfrom['mday'], $info_sfrom['year']);
						$sto = mktime(0, 0, 0, $info_sto['mon'], $info_sto['mday'], $info_sto['year']);
						if ($start_ts > $sfrom && $start_ts > $sto && $end_ts > $sto && empty($s['year'])) {
							$now_year += 1;
							list($sfrom, $sto) = VikBooking::getSeasonRangeTs($s['from'], $s['to'], $now_year);
							$info_sfrom = getdate($sfrom);
							$info_sto = getdate($sto);
							$sfrom = mktime(0, 0, 0, $info_sfrom['mon'], $info_sfrom['mday'], $info_sfrom['year']);
							$sto = mktime(0, 0, 0, $info_sto['mon'], $info_sto['mday'], $info_sto['year']);
						}
						if (($start_ts >= $sfrom && $start_ts <= $sto) || ($end_ts >= $sfrom && $end_ts <= $sto) || ($start_ts < $sfrom && $end_ts > $sto)) {
							$s['info_from_ts'] = $info_sfrom;
							$s['from_ts'] = $sfrom;
							$s['to_ts'] = $sto;
							$all_seasons[] = $s;
						}
					}
				}
				if (!(count($all_seasons) > 0) && count($all_restrictions) > 0) {
					//When no valid special prices but only restrictions, add a fake node to the empty seasons array
					$fake_season = array();
					//the ID of this fake season must be negative for identification
					$fake_season['id'] = -2;
					$fake_season['diffcost'] = 0;
					$fake_season['spname'] = 'Restrictions Placeholder';
					$fake_season['wdays'] = '';
					$fake_season['losoverride'] = '';
					$fake_season['info_from_ts'] = getdate($start_ts);
					$fake_season_start_ts = $start_ts;
					$fake_season_end_ts = $end_ts_base;
					//if one restriction only of type range, take its start/end dates rather than the ones of the update for the bulk action to avoid problems with shorter restrictions
					$full_season_restr = VikBooking::parseSeasonRestrictions($start_ts, $end_ts_base, 1, $all_restrictions);
					if (count($full_season_restr) > 0 && array_key_exists('range', $all_restrictions)) {
						foreach ($all_restrictions['range'] as $restrs) {
							if ($restrs['id'] == $full_season_restr['id']) {
								if ($restrs['dfrom'] >= $start_ts && $restrs['dto'] <= $end_ts_base) {
									$fake_season_start_ts = $restrs['dfrom'];
									$fake_season_end_ts = $restrs['dto'];
								}
								break;
							}
						}
					}
					//
					$fake_season['from_ts'] = $fake_season_start_ts;
					$fake_season['to_ts'] = $fake_season_end_ts;
					//
					$all_seasons[] = $fake_season;
				}
				//
				//VCM 1.6.5 - the Full Scan will parse every day from the start date to the end date, so day by day, to avoid problems with the dates interval calculated automatically
				$use_full_scan = (VikChannelManager::getProLevel() >= 15);
				//the standard method is used if the Full Scan is disabled, and if there are seasons or restrictions
				if (count($all_seasons) > 0 && !$use_full_scan) {
					//Standard method with less performance usage to automatically calculate the nodes depending on the seasons and restrictions
					$all_seasons = VikBooking::sortSeasonsRangeTs($all_seasons);
					//Check WeekDays filter and split season dates
					foreach ($all_seasons as $sk => $s) {
						if (empty($s['wdays']) || empty($s['info_from_ts']) || ($s['to_ts'] - $s['from_ts']) < 90001) {
							//skip special prices for any day of the week or those with a week-day filter but a duration lower than 1 day (90001 = 86400 + 3600 + 1 to avoid DST issues)
							continue;
						}
						$split_count = 0;
						$valid_week_days = explode(';', rtrim($s['wdays'], ';'));
						$now_wday = $s['info_from_ts'];
						$wday_start_node = 0;
						$wday_prev_node = $now_wday[0];
						while ($now_wday[0] <= $s['to_ts']) {
							if (!in_array((string)$now_wday['wday'], $valid_week_days)) {
								if (!empty($wday_start_node)) {
									//Do split-season
									$split_season = $s;
									$split_season['info_from_ts'] = getdate($wday_start_node);
									$split_season['from_ts'] = $wday_start_node;
									$split_season['to_ts'] = $wday_prev_node;
									$split_season['spname'] = 'Split '.$split_season['info_from_ts']['weekday'].' ('.date('Y-m-d', $wday_start_node).' - '.date('Y-m-d', $wday_prev_node).') '.$split_season['spname'];
									$all_seasons[] = $split_season;
									$split_count++;
									$wday_start_node = 0;
								}
							} else {
								if (empty($wday_start_node)) {
									$wday_start_node = $now_wday[0];
								}
							}
							$wday_prev_node = $now_wday[0];
							$nextdayts = mktime(0, 0, 0, $now_wday['mon'], ($now_wday['mday'] + 1), $now_wday['year']);
							$now_wday = getdate($nextdayts);
						}
						if ($split_count > 0) {
							if (!empty($wday_start_node)) {
								//VCM 1.6.0 re-attach the remaining days from the splitted season
								$split_season = $s;
								$split_season['info_from_ts'] = getdate($wday_start_node);
								$split_season['from_ts'] = $wday_start_node;
								$split_season['to_ts'] = $s['to_ts'];
								$split_season['spname'] = 'Split-attach '.$split_season['info_from_ts']['weekday'].' ('.date('Y-m-d', $wday_start_node).' - '.date('Y-m-d', $s['to_ts']).') '.$split_season['spname'];
								$all_seasons[] = $split_season;
							}
							unset($all_seasons[$sk]);
						}
					}
					//end Check WeekDays filter and split season dates
					//check restrictions
					$intervals_parsed = array();
					foreach ($all_seasons as $sk => $s) {
						$all_seasons[$sk]['restrictions'] = array();
						if ($glob_minlos > 1) {
							$all_seasons[$sk]['restrictions'] = array('minlos' => $glob_minlos, 'maxlos' => 0);
						}
						if (count($all_restrictions) > 0) {
							$intervals_parsed[] = array('from_ts' => $s['from_ts'], 'to_ts' => $s['to_ts']);
							$checkin_base_ts = $s['from_ts'];
							$checkout_base_ts = mktime(0, 0, 0, $s['info_from_ts']['mon'], ($s['info_from_ts']['mday'] + 1), $s['info_from_ts']['year']);
							$season_restr = VikBooking::parseSeasonRestrictions($checkin_base_ts, $checkout_base_ts, 1, $all_restrictions);
							if (count($season_restr) > 0) {
								$all_seasons[$sk]['restrictions'] = $season_restr;
							}
						}
					}
					//check if there are restrictions outside seasons - add fake seasons to the array in this case
					if (count($all_restrictions) > 0) {
						$fake_season = array();
						if (array_key_exists('range', $all_restrictions) && count($all_restrictions['range']) > 0) {
							foreach ($all_restrictions['range'] as $resk => $rv) {
								if ($rv['dfrom'] > $end_ts || $rv['dto'] < $start_ts) {
									//not for the requested dates
									continue;
								}
								foreach ($intervals_parsed as $intv_parsed) {
									if (($rv['dfrom'] >= $intv_parsed['from_ts'] && $rv['dfrom'] <= $intv_parsed['to_ts']) || ($rv['dto'] >= $intv_parsed['from_ts'] && $rv['dto'] <= $intv_parsed['to_ts']) || ($rv['dfrom'] < $intv_parsed['from_ts'] && $rv['dto'] > $intv_parsed['to_ts'])) {
										//There is already a season for these dates and the restrictions may have been taken already
										//However, skip this restriction only if it is for the same dates as the season
										if ($rv['dfrom'] == $intv_parsed['from_ts'] && $rv['dto'] == $intv_parsed['to_ts']) {
											continue 2;
										}
									}
								}
								//build fake season from this restriction by copying the first season found
								$fake_season = $all_seasons[0];
								//the ID of this fake season must be negative for identification
								$fake_season['id'] = -1;
								$fake_season['diffcost'] = 0;
								$fake_season['spname'] = 'Restriction: '.$rv['name'].' (ID: '.$rv['id'].')';
								$fake_season['wdays'] = '';
								$fake_season['losoverride'] = '';
								$fake_season['info_from_ts'] = getdate($rv['dfrom']);
								$fake_season['from_ts'] = $rv['dfrom'];
								$fake_season['to_ts'] = $rv['dto'];
								$fake_season['restrictions'] = array(
									'minlos' => $rv['minlos'],
									'maxlos' => $rv['maxlos']
								);
								if (!empty($rv['ctad'])) {
									$fake_season['restrictions']['cta'] = explode(',', $rv['ctad']);
								}
								if (!empty($rv['ctdd'])) {
									$fake_season['restrictions']['ctd'] = explode(',', $rv['ctdd']);
								}
								//
								$all_seasons[] = $fake_season;
							}
						}
						//check restrictions by month
						$nowlim = time();
						foreach ($all_restrictions as $resk => $rv) {
							if ($resk == 'range') {
								continue;
							}
							$try_year = (int)date('Y');
							$restr_start = mktime(0, 0, 0, $rv['month'], 1, $try_year);
							$restr_end = mktime(0, 0, 0, $rv['month'], date('t', $restr_start), $try_year);
							if ($restr_end < $nowlim) {
								$try_year++;
								$restr_start = mktime(0, 0, 0, $rv['month'], 1, $try_year);
								$restr_end = mktime(0, 0, 0, $rv['month'], date('t', $restr_start), $try_year);
							}
							if ($restr_start > $end_ts || $restr_end < $start_ts) {
								//not for the requested dates
								continue;
							}
							foreach ($intervals_parsed as $intv_parsed) {
								if (($restr_start >= $intv_parsed['from_ts'] && $restr_start <= $intv_parsed['to_ts']) || ($restr_end >= $intv_parsed['from_ts'] && $restr_end <= $intv_parsed['to_ts']) || ($restr_start < $intv_parsed['from_ts'] && $restr_end > $intv_parsed['to_ts'])) {
									//There is already a season for these dates and the restrictions may have been taken already
									//However, skip this restriction only if it is for the same dates as the season
									if ($restr_start == $intv_parsed['from_ts'] && $restr_end == $intv_parsed['to_ts']) {
										continue 2;
									}
								}
							}
							//build fake season from this restriction by copying the first season found
							$fake_season = $all_seasons[0];
							//the ID of this fake season must be negative for identification
							$fake_season['id'] = -1;
							$fake_season['diffcost'] = 0;
							$fake_season['spname'] = 'Restriction: '.$rv['name'].' (ID '.$rv['id'].')';
							$fake_season['wdays'] = '';
							$fake_season['losoverride'] = '';
							$fake_season['info_from_ts'] = getdate($restr_start);
							$fake_season['from_ts'] = $restr_start;
							$fake_season['to_ts'] = $restr_end;
							$fake_season['restrictions'] = array(
								'minlos' => $rv['minlos'],
								'maxlos' => $rv['maxlos']
							);
							if (!empty($rv['ctad'])) {
								$fake_season['restrictions']['cta'] = explode(',', $rv['ctad']);
							}
							if (!empty($rv['ctdd'])) {
								$fake_season['restrictions']['ctd'] = explode(',', $rv['ctdd']);
							}
							//
							$all_seasons[] = $fake_season;
						}
						if (count($fake_season) > 0) {
							$all_seasons = VikBooking::sortSeasonsRangeTs($all_seasons);
						}
					}
					//end check if there are restrictions outside seasons
					//CTA and CTD - Check whether the week days are consecutive and equal to the dates of this season interval. If not, split this season interval to transmit CTA/CTD.
					$ct_exists = false;
					$ct_exists_map = array();
					foreach ($all_seasons as $sk => $s) {
						if (array_key_exists('restrictions', $s) && ( (array_key_exists('cta', $s['restrictions']) && count($s['restrictions']['cta']) > 0) || (array_key_exists('ctd', $s['restrictions']) && count($s['restrictions']['ctd']) > 0)) ) {
							$ct_exists = true;
							$ct_exists_map[] = $sk;
						}
					}
					if ($ct_exists === true) {
						foreach ($all_seasons as $sk => $s) {
							if (!in_array($sk, $ct_exists_map)) {
								continue;
							}
							$ct_needs_split = false;
							$all_ct_wdays = array();
							if (array_key_exists('cta', $s['restrictions'])) {
								$s['restrictions']['cta'] = VikChannelManager::parseRestrictionsCtad($s['restrictions']['cta']);
								$all_ct_wdays = array_merge($all_ct_wdays, $s['restrictions']['cta']);
								$ct_start_ts = $s['from_ts'];
								$ct_loop = 0;
								while ($ct_needs_split === false && $ct_start_ts <= $s['to_ts']) {
									$ct_info = getdate($ct_start_ts);
									if (!array_key_exists($ct_loop, $s['restrictions']['cta']) || !in_array((int)$ct_info['wday'], $s['restrictions']['cta'])) {
										$ct_needs_split = true;
									}
									$ct_start_ts = mktime(0, 0, 0, $ct_info['mon'], ($ct_info['mday'] + 1), $ct_info['year']);
									$ct_loop++;
								}
							}
							if (array_key_exists('ctd', $s['restrictions'])) {
								$s['restrictions']['ctd'] = VikChannelManager::parseRestrictionsCtad($s['restrictions']['ctd']);
								$all_ct_wdays = array_merge($all_ct_wdays, $s['restrictions']['ctd']);
								$ct_start_ts = $s['from_ts'];
								$ct_loop = 0;
								while ($ct_needs_split === false && $ct_start_ts <= $s['to_ts']) {
									$ct_info = getdate($ct_start_ts);
									if (!array_key_exists($ct_loop, $s['restrictions']['ctd']) || !in_array((int)$ct_info['wday'], $s['restrictions']['ctd'])) {
										$ct_needs_split = true;
									}
									$ct_start_ts = mktime(0, 0, 0, $ct_info['mon'], ($ct_info['mday'] + 1), $ct_info['year']);
									$ct_loop++;
								}
							}
							if ($ct_needs_split === true && ($s['to_ts'] - $s['from_ts']) >= 86400) {
								//TODO: check behavior with long lasting restrictions of like two/four weeks and 6 wdays closed to arrival
								//No consecutive week-days found for these dates (min 1 night for split) and the selected CTA/CTD. Perform season split
								$all_ct_wdays = array_unique($all_ct_wdays);
								sort($all_ct_wdays);
								$ct_start_ts = $s['from_ts'];
								$ct_included = array();
								$ct_included_map = array();
								$ct_excluded = array();
								//VCM 1.6.3 - some days CTA (Sunday, Monday, Tuesday, Wednesday, Thursday) and some others CTD (Monday, Tuesday, Wednesday, Thursday, Friday)
								$ct_mixed = array();
								$ct_mixed_map = array();
								//
								$cta_wdays = array();
								$ctd_wdays = array();
								while ($ct_start_ts <= $s['to_ts']) {
									$ct_info = getdate($ct_start_ts);
									if (!in_array((int)$ct_info['wday'], $all_ct_wdays)) {
										//Reset variables
										$ct_excluded[] = $ct_info[0];
										$cta_wdays = array();
										$ctd_wdays = array();
									} elseif ( 
										(isset($s['restrictions']['cta']) && in_array((int)$ct_info['wday'], $s['restrictions']['cta']) && (!isset($s['restrictions']['ctd']) || !in_array((int)$ct_info['wday'], $s['restrictions']['ctd']))) 
										|| 
										(isset($s['restrictions']['ctd']) && in_array((int)$ct_info['wday'], $s['restrictions']['ctd']) && (!isset($s['restrictions']['cta']) || !in_array((int)$ct_info['wday'], $s['restrictions']['cta']))) 
									) {
										//VCM 1.6.3 - some days CTA (Sunday, Monday, Tuesday, Wednesday, Thursday) and some others CTD (Monday, Tuesday, Wednesday, Thursday, Friday)
										$ct_mixed[] = $ct_info[0];
										$cta_wdays = array();
										$ctd_wdays = array();
										if (array_key_exists('cta', $s['restrictions']) && in_array((int)$ct_info['wday'], $s['restrictions']['cta'])) {
											$ct_mixed_map[$ct_info[0]]['cta'] = array((int)$ct_info['wday']);
										}
										if (array_key_exists('ctd', $s['restrictions']) && in_array((int)$ct_info['wday'], $s['restrictions']['ctd'])) {
											$ct_mixed_map[$ct_info[0]]['ctd'] = array((int)$ct_info['wday']);
										}
									} else {
										$ct_included[] = $ct_info[0];
										if (array_key_exists('cta', $s['restrictions']) && in_array((int)$ct_info['wday'], $s['restrictions']['cta'])) {
											$cta_wdays[] = (int)$ct_info['wday'];
											$ct_included_map[$ct_info[0]]['cta'] = $cta_wdays;
										}
										if (array_key_exists('ctd', $s['restrictions']) && in_array((int)$ct_info['wday'], $s['restrictions']['ctd'])) {
											$ctd_wdays[] = (int)$ct_info['wday'];
											$ct_included_map[$ct_info[0]]['ctd'] = $ctd_wdays;
										}
									}
									$ct_start_ts = mktime(0, 0, 0, $ct_info['mon'], ($ct_info['mday'] + 1), $ct_info['year']);
								}
								//Perform Split for dates excluded by the CTA/CTD Week days
								$ct_excl_tot = count($ct_excluded);
								$ct_loop = 0;
								for ($ct = 1; $ct <= $ct_excl_tot; $ct++) {
									if ( $ct == $ct_excl_tot || !(abs($ct_excluded[$ct] - $ct_excluded[$ct-1]) <= (86400+7200)) ) {
										//Clone season, split it and append the new one by keeping Min LOS and Max LOS.
										$cloned_season_ct = $s;
										$cloned_season_ct['spname'] = 'CTA/CTD Clone '.$cloned_season_ct['spname'];
										$cloned_season_ct['info_from_ts'] = getdate($ct_excluded[$ct_loop]);
										$cloned_season_ct['from_ts'] = $ct_excluded[$ct_loop];
										$cloned_season_ct['to_ts'] = $ct_excluded[$ct-1];
										if (array_key_exists('cta', $cloned_season_ct['restrictions'])) {
											unset($cloned_season_ct['restrictions']['cta']);
										}
										if (array_key_exists('ctd', $cloned_season_ct['restrictions'])) {
											unset($cloned_season_ct['restrictions']['ctd']);
										}
										$all_seasons[] = $cloned_season_ct;
										$ct_loop = $ct;
									}
								}
								//Perform Split for mixed dates with some CTA days and some other days CTD
								$ct_mix_tot = count($ct_mixed);
								$ct_loop = 0;
								for ($ct = 1; $ct <= $ct_mix_tot; $ct++) {
									if ( $ct == $ct_mix_tot || !(abs($ct_mixed[$ct] - $ct_mixed[$ct-1]) <= (86400+7200)) ) {
										//Clone season, split it and append the new one by keeping Min LOS and Max LOS.
										$cloned_season_ct = $s;
										$cloned_season_ct['spname'] = 'CTA/CTD Clone '.$cloned_season_ct['spname'].' (Mixed -1='.date('Y-m-d', $ct_mixed[$ct-1]).' - 0='.date('Y-m-d', $ct_mixed[$ct]).')';
										$cloned_season_ct['info_from_ts'] = getdate($ct_mixed[$ct_loop]);
										$cloned_season_ct['from_ts'] = $ct_mixed[$ct_loop];
										$cloned_season_ct['to_ts'] = $ct_mixed[$ct-1];
										if (array_key_exists('cta', $cloned_season_ct['restrictions'])) {
											unset($cloned_season_ct['restrictions']['cta']);
										}
										if (array_key_exists('ctd', $cloned_season_ct['restrictions'])) {
											unset($cloned_season_ct['restrictions']['ctd']);
										}
										if (array_key_exists($ct_mixed[$ct-1], $ct_mixed_map)) {
											if (array_key_exists('cta', $ct_mixed_map[$ct_mixed[$ct-1]])) {
												$cloned_season_ct['restrictions']['cta'] = $ct_mixed_map[$ct_mixed[$ct-1]]['cta'];
											}
											if (array_key_exists('ctd', $ct_mixed_map[$ct_mixed[$ct-1]])) {
												$cloned_season_ct['restrictions']['ctd'] = $ct_mixed_map[$ct_mixed[$ct-1]]['ctd'];
											}
										}
										$all_seasons[] = $cloned_season_ct;
										$ct_loop = $ct;
									}
								}
								//Perform Split for dates included by the CTA/CTD Week days
								$ct_incl_tot = count($ct_included);
								$ct_loop = 0;
								for ($ct = 1; $ct <= $ct_incl_tot; $ct++) {
									if ( $ct == $ct_incl_tot || !(abs($ct_included[$ct] - $ct_included[$ct-1]) <= (86400+7200)) ) {
										//Clone season, split it and append the new one by keeping Min LOS and Max LOS.
										$cloned_season_ct = $s;
										$cloned_season_ct['spname'] = 'CTA/CTD Clone '.$cloned_season_ct['spname'];
										$cloned_season_ct['info_from_ts'] = getdate($ct_included[$ct_loop]);
										$cloned_season_ct['from_ts'] = $ct_included[$ct_loop];
										$cloned_season_ct['to_ts'] = $ct_included[$ct-1];
										if (array_key_exists('cta', $cloned_season_ct['restrictions'])) {
											unset($cloned_season_ct['restrictions']['cta']);
										}
										if (array_key_exists('ctd', $cloned_season_ct['restrictions'])) {
											unset($cloned_season_ct['restrictions']['ctd']);
										}
										if (array_key_exists($ct_included[$ct-1], $ct_included_map)) {
											if (array_key_exists('cta', $ct_included_map[$ct_included[$ct-1]])) {
												$cloned_season_ct['restrictions']['cta'] = $ct_included_map[$ct_included[$ct-1]]['cta'];
											}
											if (array_key_exists('ctd', $ct_included_map[$ct_included[$ct-1]])) {
												$cloned_season_ct['restrictions']['ctd'] = $ct_included_map[$ct_included[$ct-1]]['ctd'];
											}
										}
										$all_seasons[] = $cloned_season_ct;
										$ct_loop = $ct;
									}
								}
								//unset the splitted season
								unset($all_seasons[$sk]);
							} else {
								//Clean the 'cta' and 'ctd' arrays in any case for an easier usage in the default.php
								if (array_key_exists('cta', $s['restrictions'])) {
									$all_seasons[$sk]['restrictions']['cta'] = VikChannelManager::parseRestrictionsCtad($s['restrictions']['cta']);
								}
								if (array_key_exists('ctd', $s['restrictions'])) {
									$all_seasons[$sk]['restrictions']['ctd'] = VikChannelManager::parseRestrictionsCtad($s['restrictions']['ctd']);
								}
							}
						}
					}
					//end CTA and CTD
					//Debug
					/*
					echo 'Debug:<pre>'.print_r($all_seasons, true).'</pre><br/>';
					foreach($all_seasons as $s) {
						echo $s['id'].' '.$s['spname'].': '.date('Y-m-d H:i:s', $s['from_ts']).' - '.date('Y-m-d H:i:s', $s['to_ts']).'<br/>';
					}
					*/
					//End Debug
					//Check seasons with a large date range that may contain shorter periods necessary for the nodes
					$seasons_buf = $all_seasons;
					foreach ($all_seasons as $sk => $s) {
						$middle_seasons = array();
						foreach ($seasons_buf as $sbk => $sv) {
							if ($sbk <= $sk) {
								continue;
							}
							//VCM 1.6.0 prev rule was just $sv['from_ts'] >= $s['from_ts'] && $sv['from_ts'] < $s['to_ts'] && $sv['to_ts'] >= $s['from_ts'] && $sv['to_ts'] < $s['to_ts']
							if ($sv['from_ts'] >= $s['from_ts'] && $sv['from_ts'] <= $s['to_ts'] && $sv['to_ts'] >= $s['from_ts'] && $sv['to_ts'] <= $s['to_ts'] && ($sv['from_ts'] != $s['from_ts'] || $sv['to_ts'] != $s['to_ts'])) {
								if (count($all_seasons) > 1) {
									$middle_seasons[] = $sv;
								}
							}
						}
						if (count($middle_seasons) > 0) {
							$inner_s_from = getdate($middle_seasons[0]['from_ts']);
							$outer_s_to = mktime(0, 0, 0, $inner_s_from['mon'], ($inner_s_from['mday'] - 1), $inner_s_from['year']);
							$orig_to_ts = $s['to_ts'];
							$s['to_ts'] = $outer_s_to;
							$all_seasons[] = $s;
							$inner_s_to = getdate($middle_seasons[(count($middle_seasons) - 1)]['to_ts']);
							$outer_s_from = mktime(0, 0, 0, $inner_s_to['mon'], ($inner_s_to['mday'] + 1), $inner_s_to['year']);
							$s['from_ts'] = $outer_s_from;
							$s['to_ts'] = $orig_to_ts;
							//VCM 1.6.0 if coming from a restriction, re-calculate the MinLOS e MaxLOS for this new interval
							if ($middle_seasons[(count($middle_seasons) - 1)]['id'] < 0 && count($all_restrictions) > 0 && $s['to_ts'] >= $s['from_ts']) {
								$middle_restr = VikBooking::parseSeasonRestrictions($s['from_ts'], $s['to_ts'], 1, $all_restrictions);
								if (count($middle_restr) > 0) {
									$s['restrictions'] = $middle_restr;
								} else {
									//set the restrictions to global values
									$s['restrictions'] = array('minlos' => $glob_minlos, 'maxlos' => 0);
								}
							}
							//
							$all_seasons[] = $s;
							unset($all_seasons[$sk]);
						}
					}
					$all_seasons = VikBooking::sortSeasonsRangeTs($all_seasons);
					//
					//VCM 1.6.3 - Check seasons overlapping some dates
					//like node 2017-06-05 00:00:00 - 2017-06-10 00:00:00 and node after 2017-06-09 00:00:00 - 2017-06-10 00:00:00, must be changed to 2017-06-05 00:00:00 - 2017-06-08 00:00:00
					$tot_seasons = count($all_seasons);
					if ($tot_seasons > 1) {
						foreach ($all_seasons as $k => $v) {
							if ($k >= ($tot_seasons - 1)) {
								continue;
							}
							if ($v['to_ts'] > $all_seasons[($k + 1)]['from_ts'] && $v['from_ts'] < $all_seasons[($k + 1)]['from_ts'] && $v['to_ts'] <= $all_seasons[($k + 1)]['to_ts']) {
								$next_from = getdate($all_seasons[($k + 1)]['from_ts']);
								$all_seasons[$k]['to_ts'] = mktime(0, 0, 0, $next_from['mon'], ($next_from['mday'] - 1), $next_from['year']);
							}
						}
					}
					//end Check seasons overlapping some dates

					//VCM 1.6.4 - Long lasting multiple Special Prices that could not be split into smaller intervals, should be removed from the array before composing the nodes
					if ($tot_seasons > 1) {
						$seasons_buf = array();
						//get all the seasons with dates that touch the requested dates for update (exclude what was split)
						foreach ($all_seasons as $ks => $timeseason) {
							if (($timeseason['from_ts'] <= $start_ts && $timeseason['to_ts'] >= $end_ts) || ($timeseason['from_ts'] >= $start_ts && $timeseason['from_ts'] < $end_ts && $timeseason['to_ts'] >= $end_ts) || ($timeseason['from_ts'] <= $start_ts && $timeseason['to_ts'] > $start_ts && $timeseason['to_ts'] <= $end_ts) || ($timeseason['from_ts'] >= $start_ts && $timeseason['to_ts'] >= $start_ts && $timeseason['to_ts'] <= $end_ts)) {
								$seasons_buf[$ks] = $timeseason;
							}
						}
						//sort values and store "key_index" association for $all_seasons and "duration_ts" for sorting
						$sorted = array();
						$map = array();
						foreach ($seasons_buf as $key => $s) {
							$map[$key] = array(
								'from_ts' => $s['from_ts'],
								'duration_ts' => ($s['to_ts'] - $s['from_ts'])
							);
						}
						uasort($map, array('VikChannelManager', 'compareSeasonsDatesDurations'));
						foreach ($map as $key => $s) {
							$seasons_buf[$key]['key_index'] = $key;
							$sorted[] = $seasons_buf[$key];
						}
						//keys of $seasons_buf must be numeric for fetching the value after (k+1)
						$seasons_buf = $sorted;
						unset($sorted);
						//check if some seasons are larger than the ones after them
						foreach ($seasons_buf as $key => $s) {
							if (!isset($seasons_buf[($key + 1)])) {
								continue;
							}
							if ($s['from_ts'] != $s['to_ts'] && $s['from_ts'] <= $seasons_buf[($key + 1)]['from_ts'] && $s['to_ts'] >= $seasons_buf[($key + 1)]['to_ts']) {
								//this season interval is larger than the one below because it contains it, we should unset it
								if ($s['from_ts'].$s['to_ts'] == $seasons_buf[($key + 1)]['from_ts'].$seasons_buf[($key + 1)]['to_ts']) {
									//there are two equal nodes, if one has restrictions, unset the other
									if (count($all_seasons[$s['key_index']]['restrictions']) > 0 && !(count($all_seasons[$seasons_buf[($key + 1)]['key_index']]['restrictions']) > 0)) {
										unset($all_seasons[$seasons_buf[($key + 1)]['key_index']]);
									} else {
										unset($all_seasons[$s['key_index']]);
									}
								} else {
									//this season is just bigger than the one below, it contains it so we unset it
									unset($all_seasons[$s['key_index']]);
								}
							}
						}
						
					}
					//
					//Debug
					/*
					echo 'Debug before calculating the nodes: ('.count($all_seasons).')<br/>';
					foreach($all_seasons as $s) {
						if (!($start_ts >= $s['from_ts'] && $start_ts <= $s['to_ts']) && !($end_ts >= $s['from_ts'] && $end_ts <= $s['to_ts']) && !($s['from_ts'] >= $start_ts && $s['from_ts'] <= $end_ts) && !($s['to_ts'] >= $start_ts && $s['to_ts'] <= $end_ts)) {
							continue;
						}
						echo $s['id'].' '.$s['spname'].': '.date('Y-m-d H:i:s', $s['from_ts']).' - '.date('Y-m-d H:i:s', $s['to_ts']).'<br/>';
					}
					echo '<br/><hr/><br/>';
					*/
					//End Debug
					$nowts = getdate($start_ts);
					$node_from = date('Y-m-d', $nowts[0]);
					$node_to = '';
					$last_node_to = '';
					while ($nowts[0] < $end_ts) {
						$alter_found = false;
						if (count($all_seasons) > 0) {
							foreach ($all_seasons as $ks => $timeseason) {
								$cta_ctd_op = '';
								if (array_key_exists('restrictions', $timeseason)) {
									//Information about CTA and CTD will be transmitted next to Min LOS
									if (array_key_exists('cta', $timeseason['restrictions']) && count($timeseason['restrictions']['cta']) > 0) {
										$cta_ctd_op .= 'CTA['.implode(',', $timeseason['restrictions']['cta']).']';
									}
									if (array_key_exists('ctd', $timeseason['restrictions']) && count($timeseason['restrictions']['ctd']) > 0) {
										$cta_ctd_op .= 'CTD['.implode(',', $timeseason['restrictions']['ctd']).']';
									}
								}
								if ($timeseason['from_ts'] <= $nowts[0] && $timeseason['to_ts'] >= $nowts[0]) {
									//close previous node
									$prevdayts = mktime(0, 0, 0, $nowts['mon'], ($nowts['mday'] - 1), $nowts['year']);
									if ($prevdayts >= strtotime($node_from)) {
										$use_minlos = $glob_minlos;
										$use_maxlos = 0;
										//VCM 1.5.6 January 2017 - when closing previous nodes, do not use the global min los but calculate it
										if (count($all_restrictions)) {
											$node_restr = VikBooking::parseSeasonRestrictions(strtotime($node_from), $prevdayts, 1, $all_restrictions);
											if (count($node_restr)) {
												if (array_key_exists('minlos', $node_restr) && strlen($node_restr['minlos']) > 0) {
													$use_minlos = $node_restr['minlos'];
												}
												if (array_key_exists('maxlos', $node_restr) && strlen($node_restr['maxlos']) > 0) {
													$use_maxlos = $node_restr['maxlos'];
												}
											}
										}
										//
										$ratesinventory[$roomid][] = $node_from.'_'.date('Y-m-d', $prevdayts).'_'.$use_minlos.'_'.$use_maxlos.'_'.$rmods[$rk].'_'.$rmodsop[$rk].'_'.$rmodsamount[$rk].'_'.$rmodsval[$rk];
									}
									$node_from = date('Y-m-d', $nowts[0]);
									//close current node
									$node_to = $end_ts > $timeseason['to_ts'] ? date('Y-m-d', $timeseason['to_ts']) : date('Y-m-d', $end_ts);
									$ratesinventory[$roomid][] = $node_from.'_'.$node_to.'_'.(count($timeseason['restrictions']) ? (!strlen($timeseason['restrictions']['minlos']) > 0 ? $glob_minlos : $timeseason['restrictions']['minlos']).$cta_ctd_op.'_'.(!strlen($timeseason['restrictions']['maxlos']) > 0 ? '0' : $timeseason['restrictions']['maxlos']) : $glob_minlos.$cta_ctd_op.'_0').'_'.$rmods[$rk].'_'.$rmodsop[$rk].'_'.$rmodsamount[$rk].'_'.$rmodsval[$rk];
									//update next loop
									$last_node_to = $node_to;
									$use_node_from = getdate(($end_ts > $timeseason['to_ts'] ? $timeseason['to_ts'] : $end_ts));
									$nextdayts = mktime(0, 0, 0, $use_node_from['mon'], ($use_node_from['mday'] + 1), $use_node_from['year']);
									$node_from = date('Y-m-d', $nextdayts);
									$nowts = getdate($nextdayts);
									$alter_found = true;
									unset($all_seasons[$ks]);
									break;
								}
							}
						}
						if ($alter_found !== true) {
							$nextdayts = mktime(0, 0, 0, $nowts['mon'], ($nowts['mday'] + 1), $nowts['year']);
							$node_to = date('Y-m-d', $nowts[0]);
							$nowts = getdate($nextdayts);
						}
					}
					if (($node_from != $node_to || strtotime($node_from) > strtotime($last_node_to)) && strtotime($node_from) <= strtotime($node_to) && strtotime($node_to) <= $end_ts) {
						$ratesinventory[$roomid][] = $node_from.'_'.$node_to.'_'.$glob_minlos.'_0_'.$rmods[$rk].'_'.$rmodsop[$rk].'_'.$rmodsamount[$rk].'_'.$rmodsval[$rk];
					}
				} else {
					//Rate Plan Closing Dates (VCM 1.6.5 - only with Full Scan method or no seasons/restrictions)
					$room_rplan_closingd = array();
					if (method_exists('VikBooking', 'getRoomRplansClosingDates')) {
						$room_rplan_closingd = VikBooking::getRoomRplansClosingDates($roomid);
					}
					//
					if (!$use_full_scan && !count($room_rplan_closingd)) {
						//no seasonal rates found (count($all_seasons) = 0), no rate plan closing dates, and no Full Scan
						$ratesinventory[$roomid][] = date('Y-m-d', $start_ts).'_'.date('Y-m-d', $end_ts).'_'.$glob_minlos.'_0_'.$rmods[$rk].'_'.$rmodsop[$rk].'_'.$rmodsamount[$rk].'_'.$rmodsval[$rk];
					} else {
						//VCM 1.6.5 - (New Method) Full Scan of rates and restrictions, day by day from the start date to the end date
						//this method is used if the pro-level is sufficient, or if it's disabled but there are some rate plan closing days and no seasons/restrictions
						$max_nodes = 50; //increase the nodes per request to 50
						$roomrate = array(
							'idroom' => $roomid,
							'days' => 1,
							'idprice' => $pushdata[$roomid]['pricetype'],
							'cost' => $pushdata[$roomid]['defrate'],
							'attrdata' => '',
							'name' => $pushdata[$roomid]['pricetype']
						);
						$nowts = getdate($start_ts);
						$node_from = date('Y-m-d', $nowts[0]);
						$last_node_to = '';
						$datecost_pool = array();
						// VCM 1.6.7 Restrictions fix for inclusive end day of restriction, which requires check-in time to be at midnight for parseSeasonRestrictions()
						$hours_in = 12;
						$hours_out = 10;
						$secs_in = $hours_in * 3600; // fake checkin time set at 12:00:00 for seasonal rates, not for restrictions
						$secs_out = $hours_out * 3600; // fake checkout time set at 10:00:00 for seasonal rates, not for restrictions
						//
						//compose the rates inventory nodes by calculating the cost and restriction for each day
						while ($nowts[0] <= $end_ts) {
							$datekey = date('Y-m-d', $nowts[0]);
							$prevdatekey = date('Y-m-d', mktime(0, 0, 0, $nowts['mon'], ($nowts['mday'] - 1), $nowts['year']));

							$today_tsin = mktime($hours_in, 0, 0, $nowts['mon'], $nowts['mday'], $nowts['year']);
							$today_tsout = mktime($hours_out, 0, 0, $nowts['mon'], ($nowts['mday'] + 1), $nowts['year']);
							//if we only pass 3 arguments to the method applySeasonsRoom(), 2 queries per request will be made.
							//$tars = VikBooking::applySeasonsRoom(array($roomrate), $today_tsin, $today_tsout);
							//by passing 6 arguments to the method (VBO 1.10), the 4th is not needed and must be empty, no queries will be made: 4x faster.
							$tars = VikBooking::applySeasonsRoom(array($roomrate), $today_tsin, $today_tsout, array(), $all_seasons, $all_seasons);
							//
							$day_restr = VikBooking::parseSeasonRestrictions(($today_tsin - $secs_in), ($today_tsout - $secs_out), 1, $all_restrictions);
							$setminlos = $glob_minlos;
							$setmaxlos = 0;
							$cta_ctd_op = '';
							if (count($day_restr)) {
								if (strlen($day_restr['minlos']) > 0) {
									$setminlos = $day_restr['minlos'];
								}
								if (isset($day_restr['maxlos']) && strlen($day_restr['maxlos']) > 0) {
									$setmaxlos = $day_restr['maxlos'];
								}
								//Information about CTA and CTD will be transmitted next to Min LOS
								if (isset($day_restr['cta']) && count($day_restr['cta']) > 0) {
									if (in_array("-{$nowts['wday']}-", $day_restr['cta'])) {
										//VCM 1.6.6 - use CTA only if the current weekday is affected
										$cta_ctd_op .= 'CTA['.implode(',', $day_restr['cta']).']';
									}
								}
								if (isset($day_restr['ctd']) && count($day_restr['ctd']) > 0) {
									if (in_array("-{$nowts['wday']}-", $day_restr['ctd'])) {
										//VCM 1.6.6 - use CTD only if the current weekday is affected
										$cta_ctd_op .= 'CTD['.implode(',', $day_restr['ctd']).']';
									}
								}
								$setminlos .= $cta_ctd_op;
							}
							//For array-comparison, both $setminlos and $setmaxlos should always be strings, never integers or a different type may not compare them correctly
							$setminlos = (string)$setminlos;
							$setmaxlos = (string)$setmaxlos;
							//
							//VCM 1.6.5 - if the rate plan is closed on this day, we use the maxlos to transmit this information, and to compare the node with the other days
							if (isset($room_rplan_closingd[$pushdata[$roomid]['pricetype']]) && in_array($datekey, $room_rplan_closingd[$pushdata[$roomid]['pricetype']])) {
								$setmaxlos .= 'closed';
							}
							//
							//memorize restrictions values for this day even if the array was empty
							$day_restr['scan'] = array(
								$setminlos,
								$setmaxlos
							);
							
							$datecost_pool[$datekey] = array(
								'c' => $tars[0]['cost'],
								'r' => $day_restr
							);
							if (isset($datecost_pool[$prevdatekey])) {
								if ($datecost_pool[$prevdatekey]['c'] != $datecost_pool[$datekey]['c'] || $datecost_pool[$prevdatekey]['r'] != $datecost_pool[$datekey]['r']) {
									//cost or restriction has changed, so close previous node
									$ratesinventory[$roomid][] = $node_from.'_'.$prevdatekey.'_'.$datecost_pool[$prevdatekey]['r']['scan'][0].'_'.$datecost_pool[$prevdatekey]['r']['scan'][1].'_'.$rmods[$rk].'_'.$rmodsop[$rk].'_'.$rmodsamount[$rk].'_'.$rmodsval[$rk];
									//update variables for next loop
									$node_from = $datekey;
									$last_node_to = $prevdatekey;
								}
							}
							//go to next loop
							$nowts = getdate(mktime(0, 0, 0, $nowts['mon'], ($nowts['mday'] + 1), $nowts['year']));
						}
						//finalize loop
						$datekeyend = date('Y-m-d', $end_ts);
						if ($node_from != $datekeyend || $last_node_to != $datekeyend) {
							$ratesinventory[$roomid][] = $node_from.'_'.$datekeyend.'_'.$datecost_pool[$node_from]['r']['scan'][0].'_'.$datecost_pool[$node_from]['r']['scan'][1].'_'.$rmods[$rk].'_'.$rmodsop[$rk].'_'.$rmodsamount[$rk].'_'.$rmodsval[$rk];
						}
					}
				}
			}
			// when coming from the ratespush View, build and store the last channels pricing rules for later use and auto-population
			$bulk_rates_cache = $pushdata;
			foreach ($bulk_rates_cache as $roomid => $pushvals) {
				$mem_cache = array(
					$pushvals['pricetype'] => $pushvals
				);
				$mem_cache[$pushvals['pricetype']]['channels'] = !empty($pushvals['rplans']) && is_array($pushvals['rplans']) ? array_keys($pushvals['rplans']) : array();
				// nested matrix is dimensioned as: #1 room ID - #2 price ID - #3 pushdata info + channels array for easier reading via JS
				$bulk_rates_cache[$roomid] = $mem_cache;
			}
			if (count($bulk_rates_cache) > 0) {
				$q = "SELECT `setting` FROM `#__vikchannelmanager_config` WHERE `param`='bulkratescache'";
				$dbo->setQuery($q, 0, 1);
				$dbo->execute();
				if ($dbo->getNumRows() > 0) {
					$cur_cache = $dbo->loadResult();
					$cur_bulk_rates_cache = json_decode($cur_cache, true);
					if (is_array($cur_bulk_rates_cache) && count($cur_bulk_rates_cache)) {
						foreach ($cur_bulk_rates_cache as $ck => $cv) {
							if (isset($bulk_rates_cache[$ck])) {
								// previous settings for this room are about to be updated
								foreach ($cv as $rplan_id => $brc_data) {
									if (!isset($bulk_rates_cache[$ck][$rplan_id])) {
										// this rate plan key will be summed later
										continue;
									}
									if (empty($brc_data['rplans']) || empty($brc_data['cur_rplans']) || empty($brc_data['rplanarimode']) || empty($brc_data['channels'])) {
										// missing data in current cache
										continue;
									}
									/**
									 * Make sure to preserve settings for channels that were not selected
									 * during this submit, but that had been selected before. This is useful
									 * to not lose information when you only need to update one channel among 3.
									 * 
									 * @since 	1.8.4
									 */
									if (isset($bulk_rates_cache[$ck][$rplan_id]['rplans'])) {
										// sum new associative data to old data to update values with new settings and keep old channel params
										$bulk_rates_cache[$ck][$rplan_id]['rplans'] = $bulk_rates_cache[$ck][$rplan_id]['rplans'] + $brc_data['rplans'];
									}
									if (isset($bulk_rates_cache[$ck][$rplan_id]['cur_rplans'])) {
										// same as above, sum new channel params to old params to keep the associative channel keys
										$bulk_rates_cache[$ck][$rplan_id]['cur_rplans'] = $bulk_rates_cache[$ck][$rplan_id]['cur_rplans'] + $brc_data['cur_rplans'];
									}
									if (isset($bulk_rates_cache[$ck][$rplan_id]['rplanarimode'])) {
										// same as above, sum new channel params to old params to keep the associative channel keys
										$bulk_rates_cache[$ck][$rplan_id]['rplanarimode'] = $bulk_rates_cache[$ck][$rplan_id]['rplanarimode'] + $brc_data['rplanarimode'];
									}
									// the key "channels" is the only numeric (0th indexed) array, it is not an associative array
									if (isset($bulk_rates_cache[$ck][$rplan_id]['channels']) && $bulk_rates_cache[$ck][$rplan_id]['channels'] != $brc_data['channels']) {
										// get channel differences from old values to new channels
										$ch_diffs = array_diff($brc_data['channels'], $bulk_rates_cache[$ck][$rplan_id]['channels']);
										// re-push old missing channels (if any)
										foreach ($ch_diffs as $old_missing_ch) {
											$bulk_rates_cache[$ck][$rplan_id]['channels'][] = $old_missing_ch;
										}
									}
								}
								// use array sum operator rather than merge to keep associative keys for rate plans
								$cur_bulk_rates_cache[$ck] = $bulk_rates_cache[$ck] + $cur_bulk_rates_cache[$ck];
							}
						}
						foreach ($bulk_rates_cache as $ck => $cv) {
							if (!isset($cur_bulk_rates_cache[$ck])) {
								// set settings for this new room
								$cur_bulk_rates_cache[$ck] = $bulk_rates_cache[$ck];
							}
						}
						// set new bulk rates cache matrix to be updated on db
						$bulk_rates_cache = $cur_bulk_rates_cache;
					}
					$q = "UPDATE `#__vikchannelmanager_config` SET `setting`=".$dbo->quote(json_encode($bulk_rates_cache))." WHERE `param`='bulkratescache';";
					$dbo->setQuery($q);
					$dbo->execute();
				} else {
					$q = "INSERT INTO `#__vikchannelmanager_config` (`param`,`setting`) VALUES('bulkratescache', ".$dbo->quote(json_encode($bulk_rates_cache)).");";
					$dbo->setQuery($q);
					$dbo->execute();
				}
			}
			//
		} else {
			//Custom Rates Modification from page Overview
			$mod_vbo_rooms = array();
			foreach ($multi_intervals as $roomid => $room) {
				$ratesinventory[$roomid] = array();
				$pushdata[$roomid] = array(
					'pricetype' => $room['pricetype'],
					'defrate' => $room['defrates'],
					'rplans' => array(),
					'cur_rplans' => array(),
					'rplanarimode' => array()
				);
				$real_channels = false;
				foreach ($room['channels'] as $ch_id) {
					if ($ch_id == 'vbo') {
						//The channel "Website/IBE" has been selected to set an exact rate and/or restrictions
						$mod_vbo_rooms[] = $roomid;
						continue;
					}
					$real_channels = true;
					if (array_key_exists($ch_id, $room['rplans'])) {
						$pushdata[$roomid]['rplans'][$ch_id] = $room['rplans'][$ch_id];
					}
					if (array_key_exists('cur_rplans', $room) && array_key_exists($ch_id, $room['cur_rplans'])) {
						$pushdata[$roomid]['cur_rplans'][$ch_id] = $room['cur_rplans'][$ch_id];
					}
					if (array_key_exists('rplanarimode', $room) && array_key_exists($ch_id, $room['rplanarimode'])) {
						$pushdata[$roomid]['rplanarimode'][$ch_id] = $room['rplanarimode'][$ch_id];
					}
				}
				if ($real_channels !== true) {
					//Terminate the request because one room has no real channels selected, just the "Website/IBE". Do not unset anything or the count could still be at zero. Just raise an error and redirect
					VikError::raiseWarning('', JText::_('VCMRATESPUSHERRNODATA').' (0)');
					$mainframe->redirect("index.php?option=com_vikchannelmanager&task=".$err_goto);
					exit;
				}
				foreach ($room['details'] as $upd_details) {
					//No Restrictions to be set for the Overview Rates Modification (unless specified - since VCM 1.6.0)
					$restr_minlos = '';
					$restr_maxlos = '';
					$restr_cta_ctd = '';
					$rmod = intval($upd_details['rate']) != 0 ? '1' : '0';
					$rmodop = intval($upd_details['rate']) > 0 ? '1' : '0';
					$rmodamount = abs($upd_details['rate']);
					$rmodval = intval($upd_details['percentot']) > 1 ? '0' : '1';
					if (array_key_exists('exactcost', $upd_details) && intval($upd_details['exactcost']) > 0) {
						//this is an exact rate, no increase or decrease
						$rmodop = '2';
					}
					$to_node = empty($upd_details['to']) ? $upd_details['from'] : $upd_details['to'];
					if (array_key_exists('restrictions', $upd_details)) {
						//At least one type of restriction has been sent
						list($minlos, $maxlos, $cta, $ctd) = explode('-', $upd_details['restrictions']);
						$restr_minlos = strlen($minlos) > 0 && intval($minlos) > 0 ? $minlos : '0';
						$restr_maxlos = strlen($maxlos) > 0 && intval($maxlos) > 0 ? $maxlos : '0';
						if ((strlen($cta) && intval($cta) > 0) || strlen($ctd) && intval($ctd) > 0) {
							$days_diff = floor(($to_node - $upd_details['from']) / 86400);
							if ($days_diff >= 7) {
								$cta_ctd_range = range(0, 6);
							} else {
								$cta_ctd_range = array();
								$start_node = $upd_details['from'];
								while ($start_node <= $to_node) {
									$now_node = getdate($start_node);
									if (!in_array($now_node['wday'], $cta_ctd_range)) {
										$cta_ctd_range[] = $now_node['wday'];
									}
									$start_node += 86400;
								}
							}
							if (strlen($cta) && intval($cta) > 0) {
								$restr_cta_ctd .= 'CTA['.implode(',', $cta_ctd_range).']';
							}
							if (strlen($ctd) && intval($ctd) > 0) {
								$restr_cta_ctd .= 'CTD['.implode(',', $cta_ctd_range).']';
							}
						}
					}
					$ratesinventory[$roomid][] = date('Y-m-d', $upd_details['from']).'_'.date('Y-m-d', $to_node).'_'.$restr_minlos.$restr_cta_ctd.'_'.$restr_maxlos.'_'.$rmod.'_'.$rmodop.'_'.$rmodamount.'_'.$rmodval;
				}
			}
			//Check if rates and/or restrictions should be updated on VikBooking through the Connector Class
			if (count($mod_vbo_rooms) > 0) {
				$vboConnector = VikChannelManager::getVikBookingConnectorInstance();
				foreach ($mod_vbo_rooms as $roomid) {
					foreach ($ratesinventory[$roomid] as $op_info) {
						list($fromd, $tod, $minlos, $maxlos, $rmod, $rmodop, $rmodamount, $rmodval) = explode('_', $op_info);
						$is_restr = (strlen($minlos) > 0 || strlen($maxlos) > 0);
						$is_exactrate = (intval($rmodop) == 2 && intval($rmodamount) > 0);
						if ($is_restr) {
							$vboConnector->createRestriction($fromd, $tod, array($roomid), array($minlos, $maxlos));
						}
						if ($is_exactrate) {
							$vboConnector->setNewRate($fromd, $tod, $roomid, $pushdata[$roomid]['pricetype'], $rmodamount);
						}
					}
				}
				if ($vc_error = $vboConnector->getError(true)) {
					VikError::raiseWarning('', JText::sprintf('VCMRATESPUSHERRCHIBE', nl2br($vc_error)));
				}
			}
			//
		}

		foreach ($rows as $k => $r) {
			$rows[$k]['ratesinventory'] = $ratesinventory[$r['id']];
			$rows[$k]['pushdata'] = $pushdata[$r['id']];
			$rows[$k]['from_to'] = array_key_exists($r['id'], $from_to) ? $from_to[$r['id']] : '';
			$channel_names = array();
			foreach ($r['channels'] as $ck => $cv) {
				$channel_names[$cv['idchannel']] = ucwords($cv['channel']);
			}
		}

		//Unset VBO Session Values for VCM
		$session->set('vbVcmRatesUpd', '');
		//

		//Check limit of max nodes based on max channels
		if ($max_nodes >= 10 && $max_channels > 2) {
			$max_nodes -= (2 * $max_channels);
			$max_nodes = $max_nodes < 2 ? 2 : $max_nodes;
		}
		//

		$this->rows = $rows;
		$this->max_nodes = $max_nodes;
		
		parent::display($tpl);
	}
	
	/**
	 * Setting the toolbar
	 */
	protected function addToolBar() {
		//Add menu title and some buttons to the page
		JToolBarHelper::title(JText::_('VCMMAINTRATESPUSH'), 'vikchannelmanager');
		
		JToolBarHelper::cancel( 'cancel', JText::_('BACK'));
		
	}

}
