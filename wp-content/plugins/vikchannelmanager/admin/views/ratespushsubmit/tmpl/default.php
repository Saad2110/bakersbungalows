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

$short_wdays_map = array(
	0 => 'Sun',
	1 => 'Mon',
	2 => 'Tue',
	3 => 'Wed',
	4 => 'Thu',
	5 => 'Fri',
	6 => 'Sat'
);

?>
<script type="text/javascript">
/* Loading Overlay */
function vcmShowLoading() {
	jQuery(".vcm-loading-overlay").show();
}
function vcmStopLoading() {
	jQuery(".vcm-loading-overlay").hide();
}
</script>

<div class="vcm-loading-overlay">
	<div class="vcm-loading-processing"><?php echo JText::_('VCMPROCESSING'); ?> <span class="vcm-loading-progress-text"></span></div>
	<div class="vcm-loading-dot vcm-loading-dot1"></div>
	<div class="vcm-loading-dot vcm-loading-dot2"></div>
	<div class="vcm-loading-dot vcm-loading-dot3"></div>
	<div class="vcm-loading-dot vcm-loading-dot4"></div>
	<div class="vcm-loading-dot vcm-loading-dot5"></div>
</div>

<div class="vcm-avpush-info vcm-avpush-info-result" style="display: none;">
	<h3><?php echo JText::_('VCMAVPUSHINFORESULTCOMPL'); ?></h3>
	<div class="vcm-avpush-info-result-btns">
		<a href="index.php?option=com_vikchannelmanager&task=avpush" class="btn vcm-config-btn"><?php VikBookingIcons::e('layer-group'); ?> <?php echo JText::_('VCMMENUBULKACTIONS') . ' - ' . JText::_('VCMMENUAVPUSH'); ?></a>
		<a href="index.php?option=com_vikchannelmanager" class="btn vcm-config-btn"><i class="vboicn-earth"></i> <?php echo JText::_('VCMMENUDASHBOARD'); ?></a>
	</div>
</div>

<div class="vcm-avpush-data vcm-ratespush-data">
	<div class="vcm-avpush-request vcm-ratespush-request">
<?php
$def_currency = VikChannelManager::getCurrencyName();
$upd_req_count = 1;
$nodes_count_req = 0;
$channels_map = array();
$rooms_map = array();
?>
		<h4><?php echo JText::sprintf('VCMRATESPUSHREQNUMB', $upd_req_count); ?> <span><?php echo JText::sprintf('VCMAVPUSHMAXNODES', $this->max_nodes); ?></span></h4>
<?php
foreach ($this->rows as $k => $room) {
	$rooms_map[$room['id']] = $room['name'];
	$all_channels = array();
	foreach ($room['channels'] as $ch_id => $channel) {
		$display_chname = $channel['channel'];
		if ($ch_id == VikChannelManagerConfig::AIRBNBAPI) {
			$display_chname = 'airbnb';
		} elseif ($ch_id == VikChannelManagerConfig::GOOGLEHOTEL) {
			$display_chname = 'google hotel';
		}
		array_push($all_channels, ucwords($display_chname));
		$channels_map[$ch_id] = $channel['channel'];
	}
	$tot_nodes = count($room['ratesinventory']);
	$nodes_count = 0;
	while ($nodes_count < $tot_nodes) {
		$nodes_portion = ($this->max_nodes - $nodes_count_req) >= $tot_nodes ? $tot_nodes : ($this->max_nodes - $nodes_count_req);
		$nodes_arr = array();
		$nodes_verb_arr = array();
		for ($i=$nodes_count; $i < ($nodes_portion + $nodes_count); $i++) { 
			if ($i >= $tot_nodes) {
				break;
			}
			$nodes_arr[] = $room['ratesinventory'][$i];
			$node_rates_parts = explode('_', $room['ratesinventory'][$i]);
			//CTA/CTD
			$cta_wdays = array();
			$ctd_wdays = array();
			if (strpos($node_rates_parts[2], 'CTA') !== false) {
				//CTA is written before CTD in Min LOS so explode and re-attach the left part if CTD exists too
				$minlos_parts = explode('CTA[', $node_rates_parts[2]);
				$minlos_parts_left = explode(']', $minlos_parts[1]);
				$cta_wdays = explode(',', $minlos_parts_left[0]);
				$node_rates_parts[2] = $minlos_parts[0].(array_key_exists(1, $minlos_parts_left) ? $minlos_parts_left[1] : '');
			}
			if (strpos($node_rates_parts[2], 'CTD') !== false) {
				$minlos_parts = explode('CTD[', $node_rates_parts[2]);
				$ctd_wdays = explode(',', str_replace(']', '', $minlos_parts[1]));
				$node_rates_parts[2] = $minlos_parts[0];
			}
			$cta_ctd_verb = '';
			if (count($cta_wdays) > 0) {
				foreach ($cta_wdays as $ctwk => $ctwv) {
					$ctwv = intval(str_replace('-', '', $ctwv));
					$cta_wdays[$ctwk] = $short_wdays_map[$ctwv];
				}
				$cta_ctd_verb .= 'CTA: '.implode(',', $cta_wdays);
			}
			if (count($ctd_wdays) > 0) {
				foreach ($ctd_wdays as $ctwk => $ctwv) {
					$ctwv = intval(str_replace('-', '', $ctwv));
					$ctd_wdays[$ctwk] = $short_wdays_map[$ctwv];
				}
				$cta_ctd_verb .= (!empty($cta_ctd_verb) ? ' ' : '').'CTD: '.implode(',', $ctd_wdays);
			}
			if (!empty($cta_ctd_verb)) {
				$cta_ctd_verb = ' ['.$cta_ctd_verb.']';
			}
			//end CTA/CTD
			$ratesmod_verb = '';
			if (intval($node_rates_parts[4]) > 0 && intval($node_rates_parts[6]) > 0) {
				$ratesmod_verb = ' --&gt; '.(intval($node_rates_parts[5]) > 0 ? (intval($node_rates_parts[5]) < 2 ? '+' : '') : '-').(float)$node_rates_parts[6].' '.(intval($node_rates_parts[7]) > 0 ? '%' : $def_currency);
				if (isset($room['pushdata']['rmod_channels']) && is_array($room['pushdata']['rmod_channels'])) {
					// display the exact alteration for each channel in this room
					$ch_rmods_details = array();
					foreach ($room['channels'] as $ch_id => $channel) {
						if (!isset($room['pushdata']['rmod_channels'][$ch_id])) {
							continue;
						}
						if (!$room['pushdata']['rmod_channels'][$ch_id]['rmod']) {
							$ch_ratesmod_verb = '+0 %';
						} else {
							$ch_rmodop = $room['pushdata']['rmod_channels'][$ch_id]['rmodop'];
							$ch_rmodamount = $room['pushdata']['rmod_channels'][$ch_id]['rmodamount'];
							$ch_rmodval = $room['pushdata']['rmod_channels'][$ch_id]['rmodval'];
							$ch_ratesmod_verb = (intval($ch_rmodop) > 0 ? (intval($ch_rmodop) < 2 ? '+' : '') : '-').(float)$ch_rmodamount.' '.(intval($ch_rmodval) > 0 ? '%' : $def_currency);
						}
						$ch_rmods_details[] = $ch_ratesmod_verb;
					}
					$ratesmod_verb = count($ch_rmods_details) ? (' --&gt; ' . implode(', ', $ch_rmods_details)) : '';
				}
			}
			$nodes_verb_arr[] = $node_rates_parts[0].' - '.$node_rates_parts[1].(strlen($node_rates_parts[2]) && strlen($node_rates_parts[3]) ? ' --&gt; '.JText::_('VCMRARRESTRMINLOS').' '.$node_rates_parts[2].' '.JText::_('VCMRARRESTRMAXLOS').' '.$node_rates_parts[3] : '').$cta_ctd_verb.$ratesmod_verb;
		}
		$first_node = $nodes_arr[0];
		$last_node = $nodes_arr[(count($nodes_arr) - 1)];
		$first_node_parts = explode('_', $first_node);
		$last_node_parts = explode('_', $last_node);
		//prepare request data
		$channels_ids = array_keys($room['channels']);
		$channels_rplans = array();
		foreach ($channels_ids as $ch_id) {
			$ch_rplan = array_key_exists($ch_id, $room['pushdata']['rplans']) ? $room['pushdata']['rplans'][$ch_id] : '';
			$ch_rplan .= array_key_exists($ch_id, $room['pushdata']['rplanarimode']) ? '='.$room['pushdata']['rplanarimode'][$ch_id] : '';
			$ch_rplan .= array_key_exists($ch_id, $room['pushdata']['cur_rplans']) && !empty($room['pushdata']['cur_rplans'][$ch_id]) ? ':'.$room['pushdata']['cur_rplans'][$ch_id] : '';
			$channels_rplans[] = $ch_rplan;
		}
		$pushdata_str = implode(';', array($room['pushdata']['pricetype'], $room['pushdata']['defrate']));
		//
		?>
		<div class="vcm-avpush-request-node vcm-ratespush-request-node" data-roomid="<?php echo $room['id']; ?>" data-roomname="<?php echo $room['name']; ?>" data-channels="<?php echo implode(',', $channels_ids); ?>" data-chrplans="<?php echo implode(',', $channels_rplans); ?>">
			<div class="vcm-avpush-data-room"><?php echo $room['name']; ?></div>
		<?php
		if (!empty($room['from_to'])) {
		?>
			<div class="vcm-avpush-data-fromto"><?php echo str_replace('_', ' - ', $room['from_to']); ?></div>
		<?php
		}
		?>
			<div class="vcm-avpush-data-channels"><?php echo implode(', ', $all_channels); ?></div>
			<div class="vcm-avpush-data-fromtoreq"><span class="vcm-avpush-data-fromtoreq-totnodes"><?php echo JText::_('VCMAVPUSHTOTNODES'); ?> <?php echo count($nodes_arr); ?></span><span class="vcm-avpush-data-fromtoreq-dates"><?php echo $first_node_parts[0].' - '.$last_node_parts[1]; ?></span></div>
			<div class="vcm-avpush-data-nodesdetails">
			<?php
			foreach ($nodes_verb_arr as $node_verb) {
				$node_verb_parts = explode('--', $node_verb);
				$node_verb_dates = explode(' - ', $node_verb_parts[0]);
				?>
				<span data-nodefromto="<?php echo $room['id'].'-'.trim($node_verb_dates[0]).'-'.trim($node_verb_dates[1]); ?>"><?php echo $node_verb; ?></span>
				<?php
			}
			?>
			</div>
			<input type="hidden" class="vcm-ratespush-data-nodes<?php echo $room['id']; ?>" value="<?php echo implode(';', $nodes_arr); ?>" />
			<input type="hidden" class="vcm-ratespush-data-vars<?php echo $room['id']; ?>" value="<?php echo $pushdata_str; ?>" />
		</div>
		<?php
		if (($nodes_count_req + count($nodes_arr)) >= $this->max_nodes) {
			$upd_req_count++;
			echo '<br clear="all" /></div>'."\n".'<div class="vcm-avpush-request">'."\n".'<h4>'.JText::sprintf('VCMRATESPUSHREQNUMB', $upd_req_count).'</h4>'."\n";
			$nodes_count_req = 0;
		} else {
			$nodes_count_req += count($nodes_arr);
		}
		$nodes_count += $nodes_portion;
	}
}
?>
		<br clear="all" />
	</div>
</div>

<form action="index.php?option=com_vikchannelmanager" method="post" name="adminForm" id="adminForm">
	<input type="hidden" name="task" value="ratespushsubmit" />
</form>

<script type="text/javascript">
	var req_count = 0;
	var req_length = 1;
	var exec_delay = 10000;
	var req_finalizable = false;
	var vcmExecutionListener;
	var channels_map = JSON.parse('<?php echo json_encode($channels_map); ?>');
	var rooms_map = JSON.parse('<?php echo addslashes(json_encode($rooms_map)); ?>');

	/**
	 * Checks wether a jQuery XHR response object was due to a connection error.
	 * Property readyState 0 = Network Error (UNSENT), 4 = HTTP error (DONE).
	 * Property responseText may not be set in some browsers.
	 * This is what to check to determine if a connection error occurred.
	 * 
	 * @since 	1.7.5
	 */
	function vcmIsConnectionLostError(err) {
		if (!err || !err.hasOwnProperty('status')) {
			return false;
		}

		return (
			err.statusText == 'error'
			&& err.status == 0
			&& (err.readyState == 0 || err.readyState == 4)
			&& (!err.hasOwnProperty('responseText') || err.responseText == '')
		);
	}

	/**
	 * Ensures AJAX requests that fail due to connection errors are retried automatically.
	 * 
	 * @since 	1.7.5
	 */
	function vcmDoAjax(url, data, success, failure, attempt) {
		var VCM_AJAX_MAX_ATTEMPTS = 3;

		if (attempt === undefined) {
			attempt = 1;
		}

		return jQuery.ajax({
			type: 'POST',
			url: url,
			data: data
		}).done(function(resp) {
			if (success !== undefined) {
				// launch success callback function
				success(resp);
			}
		}).fail(function(err) {
			/**
			 * If the error is caused by a site connection lost, and if the number
			 * of retries is lower than max attempts, retry the same AJAX request.
			 */
			if (attempt < VCM_AJAX_MAX_ATTEMPTS && vcmIsConnectionLostError(err)) {
				// delay the retry by half second
				setTimeout(function() {
					// relaunch same request and increase number of attempts
					console.log('Retrying previous AJAX request');
					vcmDoAjax(url, data, success, failure, (attempt + 1));
				}, 500);
			} else {
				// launch the failure callback otherwise
				if (failure !== undefined) {
					failure(err);
				}
			}

			// always log the error in console
			console.log('AJAX request failed' + (err.status == 500 ? ' (' + err.responseText + ')' : ''), err);
		});
	}

	function vcmCheckRequestExecution() {
		if (req_count > req_length) {
			// complete requests
			jQuery('.vcm-avpush-data-fromtoreq').trigger('click');
			jQuery('.vcm-avpush-info-result').fadeIn();

			// clear loading and interval
			vcmStopLoading();
			clearInterval(vcmExecutionListener);
			vcmExecutionListener = 0;

			// finalize bulk action
			vcmDoAjax(
				"index.php",
				{
					option: "com_vikchannelmanager",
					task: "exec_ratespush_finalize",
					res: (req_finalizable === true ? "1" : "0"),
					tmpl: "component"
				}
			);
		} else {
			jQuery('.vcm-loading-progress-text').text('('+req_count+'/'+req_length+')');
		}
	}

	function vcmDoRequestSubmit(k, rooms_req, channels_req, chrplans_req, nodes_req, vars_req, req_elem) {

		req_elem.fadeIn();

		// make the request using the retry system in case of connection errors
		vcmDoAjax(
			"index.php",
			{
				option: "com_vikchannelmanager",
				task: "exec_ratespush",
				r: (k + 1) + "_" + req_length,
				rooms: rooms_req,
				channels: channels_req,
				chrplans: chrplans_req,
				nodes: nodes_req,
				v: vars_req,
				<?php echo isset($_REQUEST['e4j_debug']) && (int)$_REQUEST['e4j_debug'] == 1 ? 'e4j_debug: 1,' : ''; ?>
				tmpl: "component"
			},
			function(res) {
				if (res.substr(0, 9) == 'e4j.error') {
					req_elem.addClass("vcm-avpush-request-error");
					req_elem.append("<pre class='vcmpreerror'>" + res.replace("e4j.error.", "") + "</pre>");
				} else {
					req_elem.addClass("vcm-avpush-request-success");
					var vcm_push_obj = JSON.parse(res);
					jQuery.each(vcm_push_obj, function(idroomvb, channels) {
						if (channels.hasOwnProperty('breakdown')) {
							jQuery.each(channels.breakdown, function(datenode, daterate) {
								var verb_node_elem = jQuery("span[data-nodefromto='"+idroomvb+'-'+datenode+"']");
								if (verb_node_elem.length) {
									verb_node_elem.html(verb_node_elem.html()+" --&gt; "+daterate);
								}
							});
							delete channels.breakdown;
						}
						jQuery.each(channels, function(idchannel, result) {
							if (result.substr(0, 9) == 'e4j.error') {
								req_elem.append("<div class='vcm-ratespush-request-esitnode vcm-ratespush-request-esitnode-error "+channels_map[idchannel]+"'><div class='vcm-ratespush-request-esitnode-room'>"+rooms_map[idroomvb]+"</div><div class='vcm-ratespush-request-esitnode-text'>" + result.replace("e4j.error.", "") + "</div></div>");
							}
							if (result.substr(0, 11) == 'e4j.warning') {
								req_finalizable = true;
								req_elem.append("<div class='vcm-ratespush-request-esitnode vcm-ratespush-request-esitnode-warning "+channels_map[idchannel]+"'><div class='vcm-ratespush-request-esitnode-room'>"+rooms_map[idroomvb]+"</div><div class='vcm-ratespush-request-esitnode-text'>" + result.replace("e4j.warning.", "") + "</div></div>");
							}
							if (result.substr(0, 6) == 'e4j.OK') {
								req_finalizable = true;
								req_elem.append("<div class='vcm-ratespush-request-esitnode vcm-ratespush-request-esitnode-success "+channels_map[idchannel]+"'><div class='vcm-ratespush-request-esitnode-room'>"+rooms_map[idroomvb]+"</div><div class='vcm-ratespush-request-esitnode-text'>" + result.replace("e4j.OK.", "") + "</div></div>");
							}
						});
					});
				}
				req_count++;
			},
			function(rq_err) {
				req_elem.addClass("vcm-avpush-request-error");
				alert("Error Performing Ajax Request #" + req_count);
				req_count++;
			}
		);
		
		setTimeout(function() {
			req_elem.insertAfter(jQuery('.vcm-avpush-request').last());
		}, ((k+1) == req_length ? 1000 : (exec_delay - 1000)) );
		
	}

	function vcmProcessRequests() {

		req_count = 1;
		req_length = jQuery('.vcm-avpush-request').length;
		vcmExecutionListener = setInterval(vcmCheckRequestExecution, 1500);

		jQuery('.vcm-avpush-request').each(function(k, v) {
			var req_elem = jQuery(this);
			/**
			 * We no longer delay the first request by using
			 * var req_delay = exec_delay + (exec_delay * k);
			 *
			 * @since 	1.7.2
			 */
			var req_delay = (exec_delay * k);
			//

			var rooms_req = new Array;
			var channels_req = new Array;
			var chrplans_req = new Array;
			var nodes_req = new Array;
			var vars_req = new Array;
			req_elem.find('.vcm-avpush-request-node').each(function(nodek, nodev) {
				var roomid = jQuery(this).attr('data-roomid');
				rooms_req.push(roomid);
				channels_req.push(jQuery(this).attr('data-channels'));
				chrplans_req.push(jQuery(this).attr('data-chrplans'));
				nodes_req.push(jQuery(this).find('.vcm-ratespush-data-nodes'+roomid).val());
				vars_req.push(jQuery(this).find('.vcm-ratespush-data-vars'+roomid).val());
			});

			setTimeout(function() {
				vcmDoRequestSubmit(k, rooms_req, channels_req, chrplans_req, nodes_req, vars_req, req_elem);
			}, req_delay);
			
		});

	}

	function vcmSendRequests() {
		
		vcmShowLoading();
		
		setTimeout(function() {
			vcmProcessRequests();
		}, 2500);

		jQuery('.vcm-avpush-request').first().fadeIn();

	}

	jQuery(document).ready(function() {
		jQuery('.vcm-avpush-request').each(function(k, v) {
			if (!jQuery(v).find('.vcm-avpush-request-node').length) {
				jQuery(this).remove();
			}
		});
		jQuery('.vcm-avpush-data-fromtoreq').click(function() {
			jQuery(this).next('.vcm-avpush-data-nodesdetails').slideToggle();
		});
		vcmSendRequests();
	});

	jQuery("body").on("click", ".vcm-result-readmore-btn", function() {
		jQuery(this).next('.vcm-result-readmore-cont').show();
		jQuery(this).remove();
	});
</script>

<?php
if (isset($_REQUEST['e4j_debug']) && (int)$_REQUEST['e4j_debug'] == 1) {
	echo '<br clear="all"/><pre>'.print_r($this->rows, true).'</pre><br/>';
	echo '<pre>'.print_r($_POST, true).'</pre><br/>';
}
?>
