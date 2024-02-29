<?php
/**
 * @package     VikChannelManager
 * @subpackage  com_vikchannelmanager
 * @author      e4j - Extensionsforjoomla.com
 * @copyright   Copyright (C) 2018 e4j - Extensionsforjoomla.com. All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @link        https://e4jconnect.com - https://vikwp.com
 */

// No direct access to this file
defined('ABSPATH') or die('No script kiddies please!');

// VCM Application for popOver
$vcm_app = new VikApplication(VersionListener::getID());

// session handler for previously stored values
$session = JFactory::getSession();

/**
 * Score-card is only available for a few channels.
 * 
 * @since 	1.8.0 	added support for Airbnb API
 * @since 	1.8.4 	added support for Google Hotel
 */
$eligible_channels = array(
	VikChannelManagerConfig::BOOKING,
	VikChannelManagerConfig::AIRBNBAPI,
	VikChannelManagerConfig::GOOGLEHOTEL,
);

$module = VikChannelManager::getActiveModule(true);
$module['params'] = !empty($module['params']) ? json_decode($module['params'], true) : array();
$module['params'] = !is_array($module['params']) ? array() : $module['params'];
$hotelid = null;
$req_channel = 0;

if (in_array($module['uniquekey'], $eligible_channels)) {
	$req_channel = $module['uniquekey'];
	foreach ($module['params'] as $param_name => $param_value) {
		// grab the first channel parameter
		$hotelid = $param_value;
		break;
	}
}

// get the scorecard from the session (if previously set)
$scorecard = $session->get("scorecard_{$req_channel}_{$hotelid}", '', 'vcm-scorecard');

$get_scorecard = 0;

if (!empty($hotelid) && empty($scorecard)) {
	// retrieve the scorecard with an AJAX request
	$get_scorecard = 1;
}

if (!empty($hotelid)) {
	// we can display the content because this channel supports scorecards

	// adjust channel name, if necessary
	if ($module['uniquekey'] == VikChannelManagerConfig::AIRBNBAPI) {
		$module['name'] = 'airbnb';
	} elseif ($module['uniquekey'] == VikChannelManagerConfig::GOOGLEHOTEL) {
		$module['name'] = 'google hotel';
	}

	// get the channel logo
	$channel_info = VikChannelManager::getChannel($req_channel);
	$logo = VikChannelManager::getLogosInstance($channel_info['name']);
	$ch_logo_url = $logo->getLogoURL();

	// get property name
	$prop_name = (string)VikChannelManager::getChannelPropertyName($req_channel, $hotelid);

	// add language definitions for JS
	JText::script('VCMSCORECARD_REVIEW_SCORE');
	JText::script('VCMSCORECARD_REPLY_SCORE');
	JText::script('VCMSCORECARD_CONTENT_SCORE');
	JText::script('VCMSCORECARD_AREA_AVERAGE_SCORE');
	JText::script('VCMSHOWMORE');
	JText::script('VCMSCORECARD_OVERALL_RATING');
	JText::script('VCMSCORECARD_TOT_REVIEWS');
	JText::script('VCMSCORECARD_HOTEL_STATUS');
	JText::script('VCM_GOOGLEHOTEL_ONFEED');
	JText::script('VCM_GOOGLEHOTEL_MATCHMAPS');
	JText::script('VCM_GOOGLEHOTEL_LIVEOG');
	JText::script('VCM_GOOGLEHOTEL_LIVEOG_TOGGLE');
	JText::script('VCM_GOOGLEHOTEL_FBLR_CLICKS');
	JText::script('VCM_DEVICE');
	JText::script('VCM_REGION');
	JText::script('VCM_GOOGLEHOTEL_NOSCORECARD_HELP');
	?>

<div class="vcmdashdivleft-row vcm-dashboard-scorecard vcm-dashboard-scorecard-<?php echo preg_replace("/[^a-zA-Z0-9]+/", '', $channel_info['name']); ?>" id="vcm-dashboard-scorecard" style="<?php echo $get_scorecard ? 'display: none;' : ''; ?>">
	<h3 class="vcmdashdivlefthead">
		<span>
		<?php
		echo JText::sprintf(
			'VCMDASHCHSCORECARD', 
			(!empty($ch_logo_url) ? '<img class="vcm-scorecard-logo" src="' . $ch_logo_url . '" title="' . htmlspecialchars(ucwords($module['name'])) . '" />' : ucwords($module['name'])), 
			'<span id="vcm-dashboard-scorecard-propname">' . $prop_name . '</span>'
		);
		?>
		</span>
		<?php
		echo $vcm_app->createPopover(array(
			'title'     => JText::sprintf('VCMDASHCHSCORECARD', ucwords($module['name']), ''),
			'content'   => JText::_('VCMDASHCHSCORECARDHELP'),
			'placement' => 'right',
		));
		?>
	</h3>
	<div class="vcm-dashboard-cont-wrap">
	<?php
	// multiple accounts drop down
	$multi_accounts = VikChannelManager::getChannelAccountsMapped($req_channel);
	if (count($multi_accounts) > 1) {
		// VCM has mapped multiple accounts, build the drop down
		?>
		<div class="vcm-dashboard-scorecard-multiaccounts">
			<div class="vcm-dashboard-scorecard-multiaccounts-sel">
				<select id="vcm-scorecard-selaccount">
				<?php
				foreach ($multi_accounts as $hid => $pname) {
					?>
					<option value="<?php echo $hid; ?>"<?php echo $hid == $hotelid ? ' selected="selected"' : ''; ?>><?php echo $pname; ?></option>
					<?php
				}
				?>
				</select>
			</div>
		</div>
		<?php
	}
	?>
		<div class="vcmdashdivleft-inner vcm-dashboard-scorecard-inner"></div>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function() {
	vcmDisplayScorecard(<?php echo $get_scorecard; ?>);
	jQuery('#vcm-scorecard-selaccount').change(function() {
		jQuery('.vcm-dashboard-scorecard-inner').html('');
		var req_hid = jQuery(this).val();
		var req_pname = jQuery(this).find('option:selected').text();
		if (!req_hid.length || !req_pname.length) {
			return false;
		}
		jQuery('#vcm-dashboard-scorecard-propname').text(req_pname);
		// load the new scorecard
		vcmGetScorecard(req_hid);
	});
});

var vcm_score_icn_enabled = '<?php (class_exists('VikBookingIcons') ? VikBookingIcons::e('far fa-check-circle') : ''); ?>';
var vcm_score_icn_disabled = '<?php (class_exists('VikBookingIcons') ? VikBookingIcons::e('far fa-times-circle') : ''); ?>';
var vcm_score_icn_star = '<?php (class_exists('VikBookingIcons') ? VikBookingIcons::e('star') : ''); ?>';
var vcm_enabled_icn = '<?php (class_exists('VikBookingIcons') ? VikBookingIcons::e('check-circle') : ''); ?>';
var vcm_disabled_icn = '<?php (class_exists('VikBookingIcons') ? VikBookingIcons::e('times-circle') : ''); ?>';

function vcmGetScoreCardTn(key) {
	var base_tn   = 'VCMSCORECARD_';
	var check_key = (key + '').toUpperCase();
	var final_key = base_tn + check_key;
	var get_tn = Joomla.JText._(final_key);
	if (!get_tn || !get_tn.length || get_tn == final_key) {
		// no valid translation found, return the original key
		return key;
	}

	// return the translated string
	return get_tn;
}

function vcmScorecardShowMetrics() {
	jQuery('#vcm-dashboard-scorecard-showmetrics').remove();
	jQuery('.vcm-dashboard-scorecard-score-summary-metrics-metric').show();
}

function vcmToggleLiveOnGoogle() {
	if (!confirm(Joomla.JText._('VCM_GOOGLEHOTEL_LIVEOG_TOGGLE'))) {
		return false;
	} else {
		document.location.href = 'index.php?option=com_vikchannelmanager&task=ghotel.toggle_live_on_google';
	}
}

function vcmDisplayScorecard(action) {
	if (action) {
		vcmGetScorecard('<?php echo (string)$hotelid; ?>');
	} else {
		var scorecard = <?php echo json_encode((!empty($scorecard) && is_object($scorecard) ? $scorecard : (new stdClass))); ?>;
		vcmRenderScorecard(scorecard);
	}
}

function vcmGetScorecard(hotelid) {
	// make the AJAX request because this channel seems to support scorecards
	jQuery.ajax({
		type: "POST",
		url: "index.php",
		data: {
			option: "com_vikchannelmanager",
			task: "get_property_score",
			hotelid: hotelid,
			uniquekey: "<?php echo $channel_info['uniquekey']; ?>",
			tmpl: "component"
		}
	}).done(function(resp) {
		// parse the response
		try {
			resp = JSON.parse(resp);
		} catch (e) {
			console.error('Could not decode scorecard response', e, resp);
			resp = null;
		}
		if (!resp || !resp.hasOwnProperty('data')) {
			// silently exit the process
			console.error('Invalid scorecard response', resp);
			return;
		}

		// render the scorecard by using the response obtained
		vcmRenderScorecard(resp);
	}).fail(function(err) {
		// this is a silent request
		console.log(err.responseText);
		console.info('Channel Scorecard could not be displayed');
	<?php
	if ($channel_info['uniquekey'] == VikChannelManagerConfig::GOOGLEHOTEL) {
		// we need to display a message that says the property must be approved and rooms must be mapped
		?>
		var htmlscore = '<p class="err">' + err.responseText + '</p>' + "\n";
		htmlscore += '<p class="warn">' + Joomla.JText._('VCM_GOOGLEHOTEL_NOSCORECARD_HELP') + '</p>';
		jQuery('#vcm-dashboard-scorecard').find('.vcm-dashboard-scorecard-inner').html(htmlscore);
		if (!jQuery('#vcm-dashboard-scorecard').is(':visible')) {
			jQuery('#vcm-dashboard-scorecard').fadeIn();
		}
		<?php
	}
	?>
	});
}

function vcmLoadScorecardLogo(remote_uri, success_callback, error_callback) {
	var remote_img = new Image();
	remote_img.onload = success_callback;
	remote_img.onerror = error_callback;
	remote_img.src = remote_uri;

	return;
}

function vcmRenderScorecard(scorecard) {
	if (!scorecard || !scorecard.hasOwnProperty('data')) {
		// empty data, quit process
		jQuery('#vcm-dashboard-scorecard').hide();
		return;
	}

	// check if a logo/profile pic should be loaded remotely
	var load_logo = null;

	// build the HTML score
	var htmlscore = '';
	for (var scoretype in scorecard.data) {
		if (!scorecard.data.hasOwnProperty(scoretype) || !scorecard.data[scoretype].hasOwnProperty('summary')) {
			// score summary is mandatory
			continue;
		}
		htmlscore += '<div class="vcmdashdivitem vcm-dashboard-scorecard-score">' + "\n";
		htmlscore += '	<span class="vcmdashdivitem-lbl">' + vcmGetScoreCardTn(scoretype) + '</span>' + "\n";
		htmlscore += '	<div class="vcm-dashboard-scorecard-score-summary">' + "\n";
		htmlscore += '		<div class="vcm-dashboard-scorecard-score-summary-score">' + "\n";
		if (scorecard.data[scoretype]['summary'].hasOwnProperty('logo_url')) {
			htmlscore += '		<div class="vcm-dashboard-scorecard-score-summary-score-left">' + "\n";
			htmlscore += '			<span class="vcm-dashboard-scorecard-score-summary-score-logo" style="display: none;"></span>' + "\n";
			htmlscore += '		</div>' + "\n";
			// set flag for loading the logo
			load_logo = scorecard.data[scoretype]['summary']['logo_url'];
		}
		if (scorecard.data[scoretype]['summary'].hasOwnProperty('score')) {
			htmlscore += '		<div class="vcm-dashboard-scorecard-score-summary-score-top">' + "\n";
			htmlscore += '			<span class="vcm-dashboard-scorecard-score-summary-score-current">' + scorecard.data[scoretype]['summary']['score'] + '</span>' + "\n";
			htmlscore += '			<span class="vcm-dashboard-scorecard-score-summary-score-sep">/</span>' + "\n";
			htmlscore += '			<span class="vcm-dashboard-scorecard-score-summary-score-max">' + scorecard.data[scoretype]['summary']['max_score'] + '</span>' + "\n";
			if (scorecard.data[scoretype]['summary'].hasOwnProperty('star')) {
				htmlscore += '		<span class="vcm-dashboard-scorecard-score-summary-score-star">' + vcm_score_icn_star + '</span>' + "\n";
			}
			htmlscore += '		</div>' + "\n";
		}
		if (scorecard.data[scoretype]['summary'].hasOwnProperty('on_feed')) {
			var extra_class_status = '';
			var is_feed_active = (scorecard.data[scoretype]['summary']['on_feed'] > 0);
			extra_class_status = is_feed_active ? 'vcm-dashboard-scorecard-score-summary-score-status' : 'vcm-dashboard-scorecard-score-summary-score-status-warn';
			htmlscore += '		<div class="vcm-dashboard-scorecard-score-summary-score-top ' + extra_class_status + '">' + "\n";
			htmlscore += '			<span class="vcm-dashboard-scorecard-status-icn">' + (is_feed_active ? vcm_enabled_icn : vcm_disabled_icn) + '</span>' + "\n";
			htmlscore += '			<span class="vcm-dashboard-scorecard-status-val">' + Joomla.JText._('VCM_GOOGLEHOTEL_ONFEED') + '</span>' + "\n";
			htmlscore += '		</div>' + "\n";
			var has_matched = (scorecard.data[scoretype]['summary']['matched'] > 0);
			extra_class_status = has_matched ? 'vcm-dashboard-scorecard-score-summary-score-status' : 'vcm-dashboard-scorecard-score-summary-score-status-warn';
			htmlscore += '		<div class="vcm-dashboard-scorecard-score-summary-score-top ' + extra_class_status + '">' + "\n";
			htmlscore += '			<span class="vcm-dashboard-scorecard-status-icn">' + (has_matched ? vcm_enabled_icn : vcm_disabled_icn) + '</span>' + "\n";
			htmlscore += '			<span class="vcm-dashboard-scorecard-status-val">' + Joomla.JText._('VCM_GOOGLEHOTEL_MATCHMAPS') + '</span>' + "\n";
			htmlscore += '		</div>' + "\n";
			var is_serv_live = (scorecard.data[scoretype]['summary']['is_live'] > 0);
			extra_class_status = is_serv_live ? 'vcm-dashboard-scorecard-score-summary-score-status' : 'vcm-dashboard-scorecard-score-summary-score-status-warn';
			htmlscore += '		<div class="vcm-dashboard-scorecard-score-summary-score-top vcm-dashboard-scorecard-score-btn ' + extra_class_status + '" onclick="vcmToggleLiveOnGoogle();">' + "\n";
			htmlscore += '			<span class="vcm-dashboard-scorecard-status-icn">' + (is_serv_live ? vcm_enabled_icn : vcm_disabled_icn) + '</span>' + "\n";
			htmlscore += '			<span class="vcm-dashboard-scorecard-status-val">' + Joomla.JText._('VCM_GOOGLEHOTEL_LIVEOG') + '</span>' + "\n";
			htmlscore += '		</div>' + "\n";
		}
		if (scorecard.data[scoretype].hasOwnProperty('report') && scorecard.data[scoretype]['report'].hasOwnProperty('stats')) {
			// parse and display "report" property values, common with GoogleHotel
			if (Array.isArray(scorecard.data[scoretype]['report']['stats']) && scorecard.data[scoretype]['report']['stats'].length) {
				htmlscore += '<div class="vcm-dashboard-scorecard-score-summary-score-bottom vcm-dashboard-scorecard-ghblinks-report">' + "\n";
				for (var ghi = 0; ghi < scorecard.data[scoretype]['report']['stats'].length; ghi++) {
					if (ghi >= 8) {
						// we do not display so many information
						break;
					}
					var clicks_report_info = scorecard.data[scoretype]['report']['stats'][ghi];
					if (!clicks_report_info.hasOwnProperty('date') || !clicks_report_info.hasOwnProperty('clickCount')) {
						continue;
					}
					htmlscore += '<div class="vcm-dashboard-scorecard-ghblink-details">' + "\n";
					htmlscore += '	<div class="vcm-ghblink-main">' + "\n";
					htmlscore += '		<span class="vcm-ghblink-date">' + clicks_report_info['date'] + '</span>' + "\n";
					htmlscore += '		<div class="vcm-ghblink-data">' + "\n";
					htmlscore += '			<span class="vcm-ghblink-lbl">' + Joomla.JText._('VCM_GOOGLEHOTEL_FBLR_CLICKS') + '</span>' + "\n";
					htmlscore += '			<span class="vcm-ghblink-val vcm-ghblink-clicks">' + clicks_report_info['clickCount'] + '</span>' + "\n";
					htmlscore += '		</div>' + "\n";
					htmlscore += '	</div>' + "\n";
					htmlscore += '	<div class="vcm-ghblink-info">' + "\n";
					if (clicks_report_info.hasOwnProperty('deviceType')) {
						htmlscore += '		<div class="vcm-ghblink-data">' + "\n";
						htmlscore += '			<span class="vcm-ghblink-lbl">' + Joomla.JText._('VCM_DEVICE') + '</span>' + "\n";
						htmlscore += '			<span class="vcm-ghblink-val vcm-ghblink-device">' + clicks_report_info['deviceType'] + '</span>' + "\n";
						htmlscore += '		</div>' + "\n";
					}
					if (clicks_report_info.hasOwnProperty('userRegionCode')) {
						htmlscore += '		<div class="vcm-ghblink-data">' + "\n";
						htmlscore += '			<span class="vcm-ghblink-lbl">' + Joomla.JText._('VCM_REGION') + '</span>' + "\n";
						htmlscore += '			<span class="vcm-ghblink-val vcm-ghblink-region">' + clicks_report_info['userRegionCode'] + '</span>' + "\n";
						htmlscore += '		</div>' + "\n";
					}
					htmlscore += '	</div>' + "\n";
					htmlscore += '</div>' + "\n";
				}
				htmlscore += '</div>' + "\n";
			}
		}
		if (scorecard.data[scoretype]['summary'].hasOwnProperty('reviews')) {
			htmlscore += '		<div class="vcm-dashboard-scorecard-score-summary-score-bottom">' + "\n";
			htmlscore += '			<span class="vcm-dashboard-scorecard-score-summary-tot-reviews">' + "\n";
			htmlscore += '				<span>' + Joomla.JText._('VCMSCORECARD_TOT_REVIEWS') + '</span>' + "\n";
			htmlscore += '				<strong>' + scorecard.data[scoretype]['summary']['reviews'] + '</strong>' + "\n";
			htmlscore += '			</span>' + "\n";
			htmlscore += '		</div>' + "\n";
			// set flag for loading the logo
			load_logo = scorecard.data[scoretype]['summary']['logo_url'];
		}
		if (scorecard.data[scoretype]['summary'].hasOwnProperty('area_average_score') && scorecard.data[scoretype]['summary']['area_average_score'] !== null) {
			htmlscore += '		<div class="vcm-dashboard-scorecard-score-summary-score-avgarea">' + "\n";
			htmlscore += '			<span class="vcm-dashboard-scorecard-score-summary-score-avgarea-lbl">' + Joomla.JText._('VCMSCORECARD_AREA_AVERAGE_SCORE') + '</span>' + "\n";
			htmlscore += '			<span class="vcm-dashboard-scorecard-score-summary-score-avgarea-val">' + scorecard.data[scoretype]['summary']['area_average_score'] + '</span>' + "\n";
			htmlscore += '		</div>' + "\n";
		}
		htmlscore += '		</div>' + "\n";
		if (scorecard.data[scoretype].hasOwnProperty('metrics') && scorecard.data[scoretype]['metrics'].length) {
			// scores can have metrics
			htmlscore += '	<div class="vcm-dashboard-scorecard-score-summary-metrics">' + "\n";
			var metrics_counter = 0;
			var metrics_limit   = 5;
			for (var mind in scorecard.data[scoretype]['metrics']) {
				if (!scorecard.data[scoretype]['metrics'].hasOwnProperty(mind) || !scorecard.data[scoretype]['metrics'][mind].hasOwnProperty('action') || !scorecard.data[scoretype]['metrics'][mind].hasOwnProperty('done')) {
					// invalid metric structure
					continue;
				}
				metrics_counter++;
				var metric_visibility = metrics_counter > metrics_limit ? 'style="display: none;" ' : '';
				var metric_done = (parseInt(scorecard.data[scoretype]['metrics'][mind]['done']) > 0);
				htmlscore += '	<div ' + metric_visibility + 'class="vcm-dashboard-scorecard-score-summary-metrics-metric ' + (metric_done ? 'vcm-dashboard-scorecard-metric-done' : 'vcm-dashboard-scorecard-metric-undone') + '">' + "\n";
				htmlscore += '		<span>' + (metric_done ? vcm_score_icn_enabled : vcm_score_icn_disabled) + ' ' + scorecard.data[scoretype]['metrics'][mind]['action'] + '</span>' + "\n";
				htmlscore += '	</div>' + "\n";
			}
			if (metrics_counter > metrics_limit) {
				// display button to show all metrics
				htmlscore += '	<button type="button" class="btn" id="vcm-dashboard-scorecard-showmetrics" onclick="vcmScorecardShowMetrics();">' + Joomla.JText._('VCMSHOWMORE') + '</button>' + "\n";
			}
			htmlscore += '	</div>' + "\n";
		}
		htmlscore += '	</div>' + "\n";
		htmlscore += '</div>' + "\n";
	}

	// load logo, if necessary (to avoid displaying a broken image)
	if (load_logo) {
		vcmLoadScorecardLogo(load_logo, function() {
			var logo_target = jQuery('.vcm-dashboard-scorecard-score-summary-score-logo');
			jQuery(this).addClass('vcm-dashboard-scorecard-profilepic').appendTo(logo_target);
			logo_target.fadeIn();
		}, function() {
			console.error('Could not load profile picture', load_logo);
		});
	}

	// append scorecard HTML score and display container
	jQuery('#vcm-dashboard-scorecard').find('.vcm-dashboard-scorecard-inner').html(htmlscore);
	if (!jQuery('#vcm-dashboard-scorecard').is(':visible')) {
		jQuery('#vcm-dashboard-scorecard').fadeIn();
	}

}
</script>

<?php
} else {
	// do nothing as the currently active channel does not support any scorecard
}
