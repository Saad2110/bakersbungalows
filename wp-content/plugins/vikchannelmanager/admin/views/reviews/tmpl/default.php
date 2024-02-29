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

$vik = new VikApplication(VersionListener::getID());

// the ID of the review set by VBO
$revid = VikRequest::getInt('revid', '', 'request');

// container of plain reviews content
$revs_raw_cont = new stdClass;
$revs_raw_dets = new stdClass;

// score ranges for the CSS classes
$score_ranges = array(
	// red
	3 => 'vcm-rev-score-one-fourth',
	// orange
	6 => 'vcm-rev-score-two-fourth',
	// yellow
	8 => 'vcm-rev-score-three-fourth',
	// green
	10 => 'vcm-rev-score-four-fourth',
);

// reviews translated keywords
$revs_prop_tx = array(
	'created_timestamp' => 'VCMPVIEWORDERSVBONE',
	'reply' 			=> 'VCMREVREPLYREV',
	'reviewee_response' => 'VCMREVREPLYREV',
	'reviewer' 			=> 'VCMPVIEWORDERSVBTWO',
	'country_code' 		=> 'VCMTACHOTELCOUNTRY',
	'name' 				=> 'VCMBCAHFIRSTNAME',
	'reservation_id' 	=> 'VCMSMARTBALBID',
	'review_id' 		=> 'VCMREVIEWID',
	'scoring' 			=> 'VCMREVIEWSCORE',
	'value' 			=> 'VCMGREVVALUE',
	'value_for_money' 	=> 'VCMGREVVALUE',
	'clean' 			=> 'VCMGREVCLEAN',
	'cleanliness' 		=> 'VCMGREVCLEAN',
	'comfort' 			=> 'VCMGREVCOMFORT',
	'location' 			=> 'VCMGREVLOCATION',
	'facilities' 		=> 'VCMGREVFACILITIES',
	'staff' 			=> 'VCMGREVSTAFF',
	'review_score' 		=> 'VCMTOTALSCORE',
	'content' 			=> 'VCMGREVCONTENT',
	'message' 			=> 'VCMGREVMESSAGE',
	'text' 				=> 'VCMGREVMESSAGE',
	'headline' 			=> 'VCMGREVMESSAGE',
	'language_code' 	=> 'VCMBCAHLANGUAGE',
	'negative' 			=> 'VCMGREVNEGATIVE',
	'positive' 			=> 'VCMGREVPOSITIVE',
	'public_review' 	=> 'VCMGREVPOSITIVE',
);
foreach ($revs_prop_tx as $prop_tx) {
	JText::script($prop_tx);
}

// filters
JHTML::_('behavior.calendar');
?>
<div class="vcm-info-overlay-block">
	<div class="vcm-info-overlay-content vcm-info-overlay-review-content">
		<pre style="display: none;"><code id="vcm-rev-rawcont"></code></pre>
		<div id="vcm-rev-readable-content"></div>
		<div class="vcm-review-reply-content" style="display: none;">
			<div class="vcm-review-reply-inner">
				<form action="index.php?option=com_vikchannelmanager" method="post">
					<label for="reply_text"><?php echo JText::_('VCMREVREPLYREV'); ?></label>
					<textarea name="reply_text" id="reply_text"></textarea>
					<button type="submit" class="btn btn-primary"><?php echo JText::_('VCMBCAHSUBMIT'); ?></button>
					<input type="hidden" name="task" value="review_reply" />
					<input type="hidden" name="review_id" id="review_id" value="" />
					<input type="hidden" name="ota_review_id" id="ota_review_id" value="" />
				</form>
			</div>
		</div>
	</div>
</div>

<div class="vcm-list-form-filters vcm-btn-toolbar">
	<form action="index.php?option=com_vikchannelmanager&task=reviews" method="post" id="vcm-filters-form">
		<div id="filter-bar" class="btn-toolbar" style="width: 100%; display: inline-block;">
			<div class="btn-group pull-left">
				<?php echo JHTML::_('calendar', '', 'fromdate', 'fromdate', '%Y-%m-%d', array('class'=>'', 'size'=>'10',  'maxlength'=>'19', 'todayBtn' => 'true', 'placeholder' => JText::_('VCMFROMDATE'))); ?>
			</div>
			<div class="btn-group pull-left">
				<?php echo JHTML::_('calendar', '', 'todate', 'todate', '%Y-%m-%d', array('class'=>'', 'size'=>'10',  'maxlength'=>'19', 'todayBtn' => 'true', 'placeholder' => JText::_('VCMTODATE'))); ?>
			</div>
			<div class="btn-group pull-left">
				<select name="prop_name" id="propname-filt">
					<option></option>
				<?php
				foreach ($this->propnames as $v) {
					?>
					<option value="<?php echo $v; ?>"<?php echo $v == $this->filters['prop_name'] ? ' selected="selected"' : ''; ?>><?php echo $v; ?></option>
					<?php
				}
				?>
				</select>
			</div>
			<div class="btn-group pull-left">
				<select name="channel" id="channel-filt">
					<option></option>
				<?php
				foreach ($this->channels as $v) {
					?>
					<option value="<?php echo $v; ?>"<?php echo $v == $this->filters['channel'] ? ' selected="selected"' : ''; ?>><?php echo $v; ?></option>
					<?php
				}
				?>
				</select>
			</div>
			<div class="btn-group pull-left">
				<select name="lang" id="lang-filt">
					<option></option>
				<?php
				foreach ($this->langs as $v) {
					?>
					<option value="<?php echo $v; ?>"<?php echo $v == $this->filters['lang'] ? ' selected="selected"' : ''; ?>><?php echo strtoupper($v); ?></option>
					<?php
				}
				?>
				</select>
			</div>
			<div class="btn-group pull-left">
				<select name="country" id="country-filt">
					<option></option>
				<?php
				foreach ($this->countries as $v) {
					?>
					<option value="<?php echo $v; ?>"<?php echo $v == $this->filters['country'] ? ' selected="selected"' : ''; ?>><?php echo strtoupper($v); ?></option>
					<?php
				}
				?>
				</select>
			</div>
		<?php
		if (!empty($revid)) {
			?>
			<div class="btn-group pull-left">
				<a href="index.php?option=com_vikchannelmanager&task=reviews" class="btn btn-danger"><i class="vboicn-cross"></i> <?php echo JText::_('VCMREVIEWID') . ' ' . $revid; ?></a>
			</div>
			<?php
		}
		?>
			<div class="btn-group pull-left">
				&nbsp;&nbsp;&nbsp;
			</div>
			<div class="btn-group pull-left">
				<button type="submit" class="btn btn-secondary"><i class="vboicn-search"></i> <?php echo JText::_('VCMBCAHSUBMIT'); ?></button>
			</div>
			<div class="btn-group pull-left">
				<button type="button" class="btn" onclick="vcmClearFilters();"><?php echo JText::_('JSEARCH_FILTER_CLEAR'); ?></button>
			</div>
		<?php
		if (in_array($this->channel['uniquekey'], array(VikChannelManagerConfig::BOOKING, VikChannelManagerConfig::AIRBNBAPI))) {
			// print the button to download the reviews from this channel
			$hotel_id = '';
			if (!empty($this->channel['params'])) {
				// prepend the property name if available
				if (!empty($this->channel['prop_name'])) {
					$hotel_id .= $this->channel['prop_name'] . ' ';
				}
				// grab the first channel param, usually 'hotelid' or 'user_id'
				foreach ($this->channel['params'] as $firstv) {
					$hotel_id .= $firstv;
					break;
				}
			}
			?>
			<div class="btn-group pull-right">
				<button type="button" id="vcm-download-reviews" class="btn btn-large vcm-config-btn" onclick="vcmDownloadReviews();"<?php echo !empty($hotel_id) ? ' title="' . addslashes($hotel_id) . '"' : ''; ?>><i class="vboicn-cloud-download"></i> <span><?php echo JText::_('VCMREVIEWDOWNLOAD'); ?></span></button>
			</div>
			<?php
		}
		?>
		</div>
	</form>
</div>
<?php
//
?>
<div class="vcm-tabs-selector-container">
	<div class="vcm-tab-selector vcm-tab-selector-active" data-tabname="vcm-tab-reviews"><?php echo JText::_('VCMMENUREVIEWS'); ?></div>
	<div class="vcm-tab-selector" data-tabname="vcm-tab-scores"><?php echo JText::_('VCMREVGLOBSCORES'); ?></div>
</div>

<div class="vcm-tab-content vcm-tab-content-reviews" id="vcm-tab-reviews" style="display: block;">
<?php
if (count($this->rows)) {
	?>
	<form action="index.php?option=com_vikchannelmanager&task=reviews" method="post" name="adminForm" id="adminForm" class="vcm-list-form">
		<div class="table-responsive">
			<table cellpadding="4" cellspacing="0" border="0" width="100%" class="<?php echo $vik->getAdminTableClass(); ?> vcm-list-table">
			<?php echo $vik->openTableHead(); ?>
				<tr>
					<th width="20">
						<?php echo $vik->getAdminToggle(count($this->rows)); ?>
					</th>
					<th class="title center" width="50"><?php echo JHtml::_('grid.sort', 'JGRID_HEADING_ID', 'id', $this->orderingDir, $this->ordering); ?></th>
					<th class="title" width="150"><?php echo JHtml::_('grid.sort', 'VCMREVIEWID', 'review_id', $this->orderingDir, $this->ordering); ?></th>
					<th class="title" width="50"><?php echo JHtml::_('grid.sort', 'VCMSMARTBALBID', 'idorder', $this->orderingDir, $this->ordering); ?></th>
					<th class="title" width="150"><?php echo JHtml::_('grid.sort', 'VCMRESLOGSDT', 'dt', $this->orderingDir, $this->ordering); ?></th>
					<th class="title" width="150"><?php echo JHtml::_('grid.sort', 'VCMPROPNAME', 'prop_name', $this->orderingDir, $this->ordering); ?></th>
					<th class="title" width="150"><?php echo JHtml::_('grid.sort', 'VCMCHANNEL', 'channel', $this->orderingDir, $this->ordering); ?></th>
					<th class="title" width="150"><?php echo JHtml::_('grid.sort', 'VCMPVIEWORDERSVBTWO', 'customer_name', $this->orderingDir, $this->ordering); ?></th>
					<th class="title center" width="50"><?php echo JHtml::_('grid.sort', 'VCMBCAHLANGUAGE', 'lang', $this->orderingDir, $this->ordering); ?></th>
					<th class="title center" width="75"><?php echo JHtml::_('grid.sort', 'VCMBCAHCOUNTRY', 'country', $this->orderingDir, $this->ordering); ?></th>
					<th class="title center" width="75">&nbsp;</th>
					<th class="title center" width="50"><?php echo JHtml::_('grid.sort', 'VCMREVIEWSCORE', 'score', $this->orderingDir, $this->ordering); ?></th>
					<th class="title center" width="50"><?php echo JText::_('VCMRESLOGSDESCR'); ?></th>
					<th class="title center" width="50"><?php echo JHtml::_('grid.sort', 'VCMTACROOMPUBLISHED', 'published', $this->orderingDir, $this->ordering); ?></th>
				</tr>
			<?php echo $vik->closeTableHead(); ?>
			<?php
			$k = 0;
			$i = 0;
			$ch_reply_rev_enabled = array(
				VikChannelManagerConfig::BOOKING,
				VikChannelManagerConfig::AIRBNBAPI,
			);
			for ($i = 0, $n = count($this->rows); $i < $n; $i++) {
				// record row
				$row = $this->rows[$i];
				
				// push raw content object
				$revs_raw_cont->{$row['id']} = !empty($row['content']) ? json_decode($row['content']) : (new stdClass);
				$revs_raw_dets->{$row['id']} = array(
					'channel' 	=> $row['channel'],
					'uniquekey' => $row['uniquekey'],
				);
				// for Booking.com we unset the property URL because we don't want customers to visit an endpoint just for our servers
				if ($row['uniquekey'] == VikChannelManagerConfig::BOOKING && is_object($revs_raw_cont->{$row['id']}) && isset($revs_raw_cont->{$row['id']}->url)) {
					unset($revs_raw_cont->{$row['id']}->url);
				}

				// reply to review: only some channels support it, and only if a reply is not already set (channel must be active, we don't take it from $row['uniquekey'])
				if ($row['uniquekey'] == VikChannelManagerConfig::BOOKING && in_array($this->channel['uniquekey'], $ch_reply_rev_enabled) && (!isset($revs_raw_cont->{$row['id']}->reply) || !isset($revs_raw_cont->{$row['id']}->reply->text) || empty($revs_raw_cont->{$row['id']}->reply->text))) {
					// we set a flag that let us understand the reply is allowed for Booking.com
					$revs_raw_cont->{$row['id']}->can_reply = 1;
				} elseif ($row['uniquekey'] == VikChannelManagerConfig::AIRBNBAPI && in_array($this->channel['uniquekey'], $ch_reply_rev_enabled) && empty($revs_raw_cont->{$row['id']}->reviewee_response)) {
					// we set a flag that let us understand the reply is allowed for Airbnb
					$revs_raw_cont->{$row['id']}->can_reply = 1;
				} elseif (empty($row['channel']) && empty($row['uniquekey']) && is_object($revs_raw_cont->{$row['id']}) && property_exists($revs_raw_cont->{$row['id']}, 'reply') && empty($revs_raw_cont->{$row['id']}->reply)) {
					// website review with no reply
					$revs_raw_cont->{$row['id']}->can_reply = 1;
				}

				// check whether the review has a reply
				$has_reply = false;
				if (is_object($revs_raw_cont->{$row['id']}) && property_exists($revs_raw_cont->{$row['id']}, 'reply')) {
					if (!is_null($revs_raw_cont->{$row['id']}->reply)) {
						if (is_string($revs_raw_cont->{$row['id']}->reply) && !empty($revs_raw_cont->{$row['id']}->reply)) {
							// probably a website review
							$has_reply = true;
						} elseif (is_object($revs_raw_cont->{$row['id']}->reply) && property_exists($revs_raw_cont->{$row['id']}->reply, 'text') && !empty($revs_raw_cont->{$row['id']}->reply->text)) {
							// review with reply downloaded through Booking.com has got a "text" property
							$has_reply = true;
						}
					}
				} elseif (is_object($revs_raw_cont->{$row['id']}) && !empty($revs_raw_cont->{$row['id']}->reviewee_response)) {
					// this is a review from Airbnb API
					$has_reply = true;
				}
				
				// channel logo
				$channel_logo = '';
				if (!empty($row['uniquekey'])) {
					$channel_info = VikChannelManager::getChannel($row['uniquekey']);
					if (count($channel_info)) {
						$channel_logo = VikChannelManager::getLogosInstance($channel_info['name'])->getLogoURL();
					}
				}
				
				// CSS class for this score
				$score_css = '';
				foreach ($score_ranges as $lim => $ccss) {
					if ($row['score'] <= $lim) {
						// this is the appropriate CSS class to use for this score
						$score_css = $ccss;
						break;
					}
				}
				?>
				<tr class="row<?php echo $k; ?>">
					<td><input type="checkbox" id="cb<?php echo $i;?>" name="cid[]" value="<?php echo $row['id']; ?>" onClick="<?php echo $vik->checkboxOnClick(); ?>"></td>
					<td class="center">
						<a class="vcm-recordid" href="JavaScript: void(0);" onclick="vcmShowRevContent(<?php echo $row['id']; ?>);"><?php echo $row['id']; ?></a>
					</td>
					<td><?php echo intval($row['review_id']) != -1 ? $row['review_id'] : ''; ?></td>
					<td><?php echo !empty($row['idorder']) ? '<a href="index.php?option=com_vikbooking&task=editorder&cid[]='.$row['idorder'].'" target="_blank">'.$row['idorder'].'</a>' : '----'; ?></td>
					<td><?php echo $row['dt']; ?></td>
					<td><?php echo $row['prop_name']; ?></td>
					<td>
					<?php
					if (!empty($channel_logo)) {
						?>
						<img src="<?php echo $channel_logo; ?>" style="max-width: 100px;"/>
						<?php
					} elseif (!empty($row['channel'])) {
						echo $row['channel'];
					} else {
						?>
						<span class="vcm-review-website-badge"><?php echo JText::_('VCMWEBSITE'); ?></span>
						<?php
					}
					?>
					</td>
					<td><?php echo $row['customer_name']; ?></td>
					<td class="center"><?php echo $row['lang']; ?></td>
					<td class="center"><?php echo $row['country']; ?></td>
					<td class="center">
					<?php
					if ($has_reply === true) {
						?>
						<span class="vcm-review-reply-badge vcm-review-withreply-badge"><?php echo JText::_('VCMREVIEWHASREPLY'); ?></span>
						<?php
					} else {
						?>
						<span class="vcm-review-reply-badge" onclick="vcmShowRevContent(<?php echo $row['id']; ?>);"><?php echo JText::_('VCMREVIEWNOREPLY'); ?></span>
						<?php
					}
					?>
					</td>
					<td class="center">
						<span class="vcm-review-score-badge <?php echo $score_css; ?>"><?php echo $row['score']; ?></span>
					</td>
					<td class="center">
						<span class="vcm-review-viewdet" onclick="vcmShowRevContent(<?php echo $row['id']; ?>);"><i class="vboicn-eye"></i></span>
					</td>
					<td class="center">
				<?php
				if ($row['uniquekey'] == VikChannelManagerConfig::AIRBNBAPI) {
					// reviews from Airbnb API should not be published according to their terms of service
					?>
						<a href="javascript: void(0);" class="vcm-icn-link-toggle" title="Airbnb reviews cannot be published"><i class="<?php echo class_exists('VikBookingIcons') ? VikBookingIcons::i('ban') : 'vboicn-cross'; ?>" style="color: #D9534F;"></i></a>
					<?php
				} else {
					if ($row['published'] > 0) {
						?>
						<a href="index.php?option=com_vikchannelmanager&task=toggle_review_status&cid[]=<?php echo $row['id']; ?>" class="vcm-icn-link-toggle"><i class="<?php echo class_exists('VikBookingIcons') ? VikBookingIcons::i('check-circle') : 'vboicn-checkmark'; ?>" style="color: green;"></i></a>
						<?php
					} else {
						?>
						<a href="index.php?option=com_vikchannelmanager&task=toggle_review_status&cid[]=<?php echo $row['id']; ?>" class="vcm-icn-link-toggle"><i class="<?php echo class_exists('VikBookingIcons') ? VikBookingIcons::i('times-circle') : 'vboicn-cross'; ?>" style="color: #D9534F;"></i></a>
						<?php
					}
				}
				?>
					</td>
				</tr>
				<?php
				$k = 1 - $k;
			}
			?>
			</table>
		</div>
		<input type="hidden" name="filter_order" value="<?php echo $this->ordering; ?>" />
		<input type="hidden" name="filter_order_Dir" value="<?php echo $this->orderingDir; ?>" />
		<input type="hidden" name="option" value="com_vikchannelmanager" />
		<input type="hidden" name="task" value="reviews" />
		<input type="hidden" name="boxchecked" value="0" />
		<?php
		foreach ($this->filters as $kf => $vf) {
			if (is_scalar($vf)) {
				?>
		<input type="hidden" name="<?php echo $kf; ?>" value="<?php echo $vf; ?>" />
				<?php
			} else {
				foreach ($vf as $subvf) {
					?>
		<input type="hidden" name="<?php echo $kf; ?>[]" value="<?php echo $subvf; ?>" />
					<?php
				}
			}
		}
		?>
		<?php echo JHTML::_( 'form.token' ); ?>
		<?php echo '<br/>'.$this->navbut; ?>
	</form>
<?php
} else {
	?>
	<p class="warn"><?php echo JText::_('VCMNOREVIEWSFOUND'); ?></p>
	<form action="index.php?option=com_vikchannelmanager" method="post" name="adminForm" id="adminForm">
		<input type="hidden" name="option" value="com_vikchannelmanager" />
		<input type="hidden" name="task" value="" />
	</form>
	<?php
}
?>
</div>

<div class="vcm-tab-content vcm-tab-content-scores" id="vcm-tab-scores" style="display: none;">
<?php
if (count($this->global_scores)) {
	foreach ($this->global_scores as $glob_score) {
		// channel logo
		$channel_logo = '';
		if (!empty($glob_score['uniquekey'])) {
			$channel_info = VikChannelManager::getChannel($glob_score['uniquekey']);
			if (count($channel_info)) {
				$channel_logo = VikChannelManager::getLogosInstance($channel_info['name'])->getLogoURL();
			}
		}
		//
		if (empty($glob_score['channel'])) {
			$tmpl_name = 'website';
		} else {
			$tmpl_name = preg_replace("/[^a-z0-9]/", '', strtolower($glob_score['channel']));
		}
		if (!is_file(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'default_' . $tmpl_name . '.php')) {
			$tmpl_name = 'generic';
		}
		// prepare score variables for the template
		$this->glob_score = $glob_score;
		$this->channel_logo = $channel_logo;
		echo $this->loadTemplate($tmpl_name);
	}
} else {
	?>
	<p class="warn"><?php echo JText::_('VCMREVNOGLOBSCORES'); ?></p>
	<?php
}
?>
</div>

<script type="text/javascript">
function vcmClearFilters() {
	jQuery('#vcm-filters-form').find('input, select').val('');
	jQuery('#vcm-filters-form').append('<input type=\'hidden\' name=\'limitstart\' value=\'0\' />');
	document.getElementById('vcm-filters-form').submit();
}
var vcm_overlay_on = false;
var vcm_revs_rawcont = <?php echo json_encode($revs_raw_cont); ?>;
var vcm_revs_rawdets = <?php echo json_encode($revs_raw_dets); ?>;
var vcm_revs_prop_tx = <?php echo json_encode($revs_prop_tx); ?>;
var vcm_full_staricn = '<?php echo class_exists('VikBookingIcons') ? '<i class="' . VikBookingIcons::i('star', 'vcm-review-star vcm-review-star-full') . '"></i>' : '<i class="vboicn-star-full vcm-review-star vcm-review-star-full"></i>'; ?>';
var vcm_void_staricn = '<?php echo class_exists('VikBookingIcons') ? '<i class="' . VikBookingIcons::i('star', 'vcm-review-star') . '"></i>' : '<i class="vboicn-star-full vcm-review-star"></i>'; ?>';
var vcm_json_macrogr = '';
var vcm_servicescore = false;
function vcmIsObject(obj) {
	if (typeof obj != 'object') {
		return false;
	}
	for (var jk in obj) {
		return obj.hasOwnProperty(jk);
	}
}
function vcmUcWords(str) {
	return (str + '').replace(/^(.)|\s+(.)/g, function ($1) {
		return $1.toUpperCase();
	});
}
var vcmStringifyObject = function(obj, idrev) {
	var stringified = '';
	var otareview 	= (vcm_revs_rawdets.hasOwnProperty(idrev) && vcm_revs_rawdets[idrev].hasOwnProperty('channel') && vcm_revs_rawdets[idrev]['channel'] === null ? false : true);
	for (var jk in obj) {
		if (!obj.hasOwnProperty(jk) || obj[jk] === null || jk == 'can_reply') {
			continue;
		}
		var usekey = jk.replace(' ', '_').toLowerCase();
		var prop_name = vcm_revs_prop_tx.hasOwnProperty(usekey) ? Joomla.JText._(vcm_revs_prop_tx[usekey]) : vcmUcWords(jk.replace(/_/g, ' '));
		if (vcmIsObject(obj[jk])) {
			// update macrogroup
			vcm_json_macrogr = jk.replace(' ', '-').replace('_', '-').toLowerCase();
			// use this property container as a group-label
			stringified += '<div class="vcm-review-json-entry vcm-review-json-entry-group vcm-review-json-' + vcm_json_macrogr + '"><span class="vcm-review-json-key">' + prop_name + '</span></div>';
			// recursive call for inner object
			stringified += vcmStringifyObject(obj[jk], idrev);
		} else {
			stringified += '<div class="vcm-review-json-entry vcm-review-json-' + vcm_json_macrogr + '">';
			stringified += '<span class="vcm-review-json-key">' + prop_name + '</span>';
			if (!otareview && vcm_json_macrogr == 'scoring' && (jk != 'review_score' || (jk == 'review_score' && !vcm_servicescore))) {
				// star rating for website review to a service or global review
				vcm_servicescore = true;
				stringified += '<span class="vcm-review-json-value">';
				var starscount = Math.floor((parseInt(obj[jk]) / 2));
				for (var s = 1; s <= starscount; s++) {
					stringified += vcm_full_staricn;
				}
				for (var s = (starscount + 1); s <= 5; s++) {
					stringified += vcm_void_staricn;
				}
				stringified += '</span>';
			} else {
				vcm_servicescore = false;
				// check if the value is an URL of the photo
				if (jk == 'photo' && obj[jk].indexOf('http') >= 0) {
					obj[jk] = '<span class="vcm-review-guest-avatar-wrap"><img class="vcm-review-guest-avatar" src="' + obj[jk] + '" /></span>';
				}
				stringified += '<span class="vcm-review-json-value' + (jk == 'review_score' ? ' vcm-review-json-totscore' : '') + (vcm_json_macrogr == 'scoring' && !isNaN(obj[jk]) ? ' vcm-review-json-servscore' : '') + '">' + obj[jk] + '</span>';
			}
			stringified += '</div>';
		}
	}
	return stringified;
}
function vcmCloseModal() {
	jQuery(".vcm-info-overlay-block").fadeOut(400, function() {
		jQuery(this).attr("class", "vcm-info-overlay-block");
	});
	vcm_overlay_on = false;
}
function vcmDownloadReviews() {
	var fromd = document.getElementById('fromdate').value;
	var confstr = '<?php echo addslashes(JText::_('VCMREVIEWDOWNLOADFROMD')); ?>';
	if (fromd.length) {
		if (!confirm(confstr.replace('%s', fromd))) {
			return;
		}
	}
	document.location.href = 'index.php?option=com_vikchannelmanager&task=reviews_download&uniquekey=<?php echo $this->channel['uniquekey']; ?>&fromd=' + fromd;
}
function vcmShowRevContent(idrev) {
	if (!vcm_revs_rawcont.hasOwnProperty(idrev)) {
		alert('No content available');
		return;
	}
	// check if reply is allowed
	if (vcm_revs_rawcont[idrev].hasOwnProperty('can_reply') && vcm_revs_rawcont[idrev]['can_reply'] > 0) {
		jQuery('.vcm-review-reply-content').show();
		// populate form fields
		jQuery('#review_id').val(idrev);
		jQuery('#ota_review_id').val(vcm_revs_rawcont[idrev]['review_id']);
	} else {
		jQuery('.vcm-review-reply-content').hide();
	}
	//
	jQuery('#vcm-rev-rawcont').html(JSON.stringify(vcm_revs_rawcont[idrev], null, 4));
	jQuery('#vcm-rev-readable-content').html(vcmStringifyObject(vcm_revs_rawcont[idrev], idrev));
	jQuery(".vcm-info-overlay-block").fadeIn();
	vcm_overlay_on = true;
}
jQuery(document).ready(function() {
	jQuery('#propname-filt').select2({
		placeholder: '<?php echo addslashes(JText::_('VCMREVFILTBYPROPNAME')); ?>',
		allowClear: false,
		width: 150
	});
	jQuery('#channel-filt').select2({
		placeholder: '<?php echo addslashes(JText::_('VCMREVFILTBYCH')); ?>',
		allowClear: false,
		width: 150
	});
	jQuery('#lang-filt').select2({
		placeholder: '<?php echo addslashes(JText::_('VCMREVFILTBYLANG')); ?>',
		allowClear: false,
		width: 150
	});
	jQuery('#country-filt').select2({
		placeholder: '<?php echo addslashes(JText::_('VCMREVFILTBYCOUNTRY')); ?>',
		allowClear: false,
		width: 150
	});
	jQuery('#fromdate').val('<?php echo $this->filters['fromdate'] ?>').attr('data-alt-value', '<?php echo $this->filters['fromdate'] ?>');
	jQuery('#todate').val('<?php echo $this->filters['todate'] ?>').attr('data-alt-value', '<?php echo $this->filters['todate'] ?>');
	jQuery(document).mouseup(function(e) {
		if (!vcm_overlay_on) {
			return false;
		}
		var vcm_overlay_cont = jQuery(".vcm-info-overlay-content");
		if (!vcm_overlay_cont.is(e.target) && vcm_overlay_cont.has(e.target).length === 0) {
			vcmCloseModal();
		}
	});
	jQuery(document).keyup(function(e) {
		if (e.keyCode == 27 && vcm_overlay_on) {
			vcmCloseModal();
		}
	});
	// tabs
	jQuery('.vcm-tab-selector').click(function() {
		var tabname = jQuery(this).attr('data-tabname');
		jQuery('.vcm-tab-content').hide();
		jQuery('#'+tabname).show();
		jQuery('.vcm-tab-selector').removeClass('vcm-tab-selector-active');
		jQuery(this).addClass('vcm-tab-selector-active');
	});
	//
<?php
if (!empty($revid)) {
	echo "\t".'vcmShowRevContent('.$revid.');';
}
?>
});
</script>
