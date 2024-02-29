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

// override channel name, if necessary
$channel_name = $this->channel['uniquekey'] == VikChannelManagerConfig::AIRBNBAPI ? 'Airbnb' : $this->channel['name'];

// no template (modal) request
$rq_tmpl = VikRequest::getString('tmpl', '', 'request');

// check if the operation was successful
$success = VikRequest::getInt('success', 0, 'request');

?>
<form name="adminForm" id="adminForm" action="index.php" method="post">
	<div class="vcm-admin-container vcm-admin-container-full">
		
		<div class="vcm-config-maintab-left">
			<fieldset class="adminform">
				<div class="vcm-params-wrap">
					<legend class="adminlegend"><?php echo JText::_('VCM_REVIEW_GUEST_TITLE'); ?> <i class="<?php echo class_exists('VikBookingIcons') ? VikBookingIcons::i('refresh', 'fa-spin fa-fw vcm-hostguestrev-loading-hid') : ''; ?>" style="display: none;"></i></legend>
					<div class="vcm-params-container">
						<div class="vcm-param-container">
							<div class="vcm-param-label"><?php echo JText::_('VCMRESLOGSIDORDOTA'); ?></div>
							<div class="vcm-param-setting">
								<div><?php echo $this->reservation['idorderota']; ?></div>
								<img src="<?php echo VikChannelManager::getLogosInstance($this->channel['name'])->getLogoURL(); ?>" />
							</div>
						</div>
						<div class="vcm-param-container">
							<div class="vcm-param-label"><?php echo JText::_('VCM_HTGREVIEW_PUBLIC'); ?> <sup>*</sup></div>
							<div class="vcm-param-setting">
								<textarea name="public_review" id="vcm-public-review" rows="4" cols="50" onBlur="checkRequiredField('vcm-public-review');"></textarea>
								<span class="vcm-param-setting-comment"><?php echo JText::_('VCM_HTGREVIEW_PUBLIC_HELP'); ?></span>
							</div>
						</div>
						<div class="vcm-param-container">
							<div class="vcm-param-label"><?php echo JText::_('VCM_HTGREVIEW_PRIVATE'); ?></div>
							<div class="vcm-param-setting">
								<textarea name="private_review" id="vcm-private-review" rows="4" cols="50"></textarea>
								<span class="vcm-param-setting-comment"><?php echo JText::_('VCM_HTGREVIEW_PRIVATE_HELP'); ?></span>
							</div>
						</div>
						<div class="vcm-param-container">
							<div class="vcm-param-label"><?php echo JText::_('VCM_HTGREVIEW_CLEAN'); ?> <sup>*</sup></div>
							<div class="vcm-param-setting">
								<span class="vcm-param-setting-comment"><?php echo JText::_('VCM_HTGREVIEW_CLEAN_HELP'); ?></span>
								<div class="vcm-writereview-stars-wrap">
								<?php
								for ($i = 1; $i <= 5; $i++) { 
									?>
									<span class="vcm-writereview-star-cont" data-star-cat="clean" data-star-rating="<?php echo $i; ?>" onclick="vcmSetStarRating(this);"><?php VikBookingIcons::e('star', 'vcm-ota-review-star'); ?></span>
									<?php
								}
								?>
									<input type="hidden" name="review_cat_clean" id="review-cat-clean" value="" />
								</div>
								<div class="vcm-writereview-stars-comment-wrap" id="vcm-writereview-stars-comment-clean" style="display: none;">
									<input type="text" name="review_cat_clean_comment" value="" autocomplete="off" />
									<span class="vcm-param-setting-comment"><?php echo JText::_('VCM_HTGREVIEW_COMMENT'); ?></span>
								</div>
							</div>
						</div>
						<div class="vcm-param-container">
							<div class="vcm-param-label"><?php echo JText::_('VCM_HTGREVIEW_COMM'); ?> <sup>*</sup></div>
							<div class="vcm-param-setting">
								<span class="vcm-param-setting-comment"><?php echo JText::_('VCM_HTGREVIEW_COMM_HELP'); ?></span>
								<div class="vcm-writereview-stars-wrap">
								<?php
								for ($i = 1; $i <= 5; $i++) { 
									?>
									<span class="vcm-writereview-star-cont" data-star-cat="comm" data-star-rating="<?php echo $i; ?>" onclick="vcmSetStarRating(this);"><?php VikBookingIcons::e('star', 'vcm-ota-review-star'); ?></span>
									<?php
								}
								?>
									<input type="hidden" name="review_cat_comm" id="review-cat-comm" data-rating="score" value="" />
								</div>
								<div class="vcm-writereview-stars-comment-wrap" id="vcm-writereview-stars-comment-comm" style="display: none;">
									<input type="text" name="review_cat_comm_comment" value="" autocomplete="off" />
									<span class="vcm-param-setting-comment"><?php echo JText::_('VCM_HTGREVIEW_COMMENT'); ?></span>
								</div>
							</div>
						</div>
						<div class="vcm-param-container">
							<div class="vcm-param-label"><?php echo JText::_('VCM_HTGREVIEW_HRULES'); ?> <sup>*</sup></div>
							<div class="vcm-param-setting">
								<span class="vcm-param-setting-comment"><?php echo JText::_('VCM_HTGREVIEW_HRULES_HELP'); ?></span>
								<div class="vcm-writereview-stars-wrap">
								<?php
								for ($i = 1; $i <= 5; $i++) { 
									?>
									<span class="vcm-writereview-star-cont" data-star-cat="hrules" data-star-rating="<?php echo $i; ?>" onclick="vcmSetStarRating(this);"><?php VikBookingIcons::e('star', 'vcm-ota-review-star'); ?></span>
									<?php
								}
								?>
									<input type="hidden" name="review_cat_hrules" id="review-cat-hrules" value="" />
								</div>
								<div class="vcm-writereview-stars-comment-wrap" id="vcm-writereview-stars-comment-hrules" style="display: none;">
									<input type="text" name="review_cat_hrules_comment" value="" autocomplete="off" />
									<span class="vcm-param-setting-comment"><?php echo JText::_('VCM_HTGREVIEW_COMMENT'); ?></span>
								</div>
							</div>
						</div>
						<div class="vcm-param-container">
							<div class="vcm-param-label"><?php echo JText::_('VCM_HTGREVIEW_WAGAIN'); ?></div>
							<div class="vcm-param-setting">
								<div class="vcm-param-radio-group">
									<span class="vcm-param-radio vcm-param-radio-positive">
										<input type="radio" name="review_host_again" id="review-host-again-yes" value="1" />
										<label for="review-host-again-yes"><?php echo JText::_('VCMYES'); ?></label>
									</span>
									<span class="vcm-param-radio vcm-param-radio-negative">
										<input type="radio" name="review_host_again" id="review-host-again-no" value="0" />
										<label for="review-host-again-no"><?php echo JText::_('VCMNO'); ?></label>
									</span>
								</div>
								<span class="vcm-param-setting-comment"><?php echo JText::_('VCM_HTGREVIEW_WAGAIN_HELP'); ?></span>
							</div>
						</div>
					<?php
					if ($rq_tmpl == 'component') {
						?>
						<div class="vcm-param-container">
							<div class="vcm-param-label"></div>
							<div class="vcm-param-setting">
								<button type="button" class="btn btn-primary" onclick="vcmHandleSubmitOperation(this);"><?php VikBookingIcons::e('smile'); ?> <?php echo JText::_('VCM_REVIEW_GUEST_TITLE'); ?></button>
								<button type="button" class="btn" onclick="vcmHandleCancelOperation(false);"><?php echo JText::_('BACK'); ?></button>
							</div>
						</div>
						<?php
					}
					?>
					</div>
				</div>
			</fieldset>
		</div>

	</div>
	
	<input type="hidden" name="task" value="doHostGuestReview"/>
	<input type="hidden" name="option" value="com_vikchannelmanager" />
	<input type="hidden" name="vbo_oid" value="<?php echo $this->reservation['id']; ?>" />
<?php
if (!empty($rq_tmpl)) {
	?>
	<input type="hidden" name="tmpl" value="<?php echo $rq_tmpl; ?>" />
	<?php
}
?>
</form>

<a href="index.php?option=com_vikbooking&task=editorder&cid[]=<?php echo $this->reservation['id']; ?>" class="vcm-placeholder-backlink" style="display: none;"></a>

<script type="text/javascript">
	
	function checkRequiredField(id) {
		var elem = jQuery('#'+id);
		if (!elem.length) {
			return;
		}
		var lbl = elem.closest('.vcm-param-container').find('.vcm-param-label');
		if (!lbl.length) {
			return;
		}
		if (elem.val().length) {
			lbl.removeClass('vcm-param-label-isrequired');
			return true;
		}
		lbl.addClass('vcm-param-label-isrequired');
		return false;
	}

	/**
	 * If we are inside a modal, the modal will be dismissed, otherwise
	 * we redirect the user to the booking details page in VikBooking.
	 */
	function vcmHandleCancelOperation(refresh) {
		if (refresh) {
			jQuery('.vcm-params-container').hide();
			jQuery('.vcm-hostguestrev-loading-hid').show();
		}

		var nav_fallback = jQuery('.vcm-placeholder-backlink').first().attr('href');
		var modal = jQuery('.modal[id*="vbo"]');
		var needs_parent = false;
		if (!modal.length) {
			// check if we are in a iFrame and so the element we want is inside the parent
			modal = jQuery('.modal[id*="vbo"]', parent.document);
			if (modal.length) {
				needs_parent = true;
			}
		}
		if (!modal.length) {
			// we are probably not inside a modal, so navigate
			window.location.href = nav_fallback;
			return;
		}
		
		// try to dismiss the modal
		try {
			modal.modal('hide');
		} catch(e) {
			// dismissing did not succeed
		}
		
		if (refresh) {
			// navigate to refresh the page
			if (needs_parent) {
				window.parent.location.href = nav_fallback;
			} else {
				window.location.href = nav_fallback;
			}
		}
	}

	function vcmHandleSubmitOperation(elem) {
		if (confirm('<?php echo addslashes(JText::_('VCM_REVIEW_GUEST_TITLE')); ?>')) {
			jQuery(elem).prop('disabled', true);
			jQuery('.vcm-hostguestrev-loading-hid').show();
			jQuery(elem).closest('form').submit();
		}
	}

	function vcmSetStarRating(elem) {
		var star = jQuery(elem);
		var star_cat = star.attr('data-star-cat');
		var star_rating = star.attr('data-star-rating');
		if (!star_rating || isNaN(star_rating)) {
			return false;
		}
		star_rating = parseInt(star_rating);
		// remove full class from the entire category
		jQuery('.vcm-writereview-star-cont[data-star-cat="' + star_cat + '"]').find('i').removeClass('vcm-ota-review-star-full');
		// add full class until this rating is reached
		for (var i = 1; i <= star_rating; i++) {
			jQuery('.vcm-writereview-star-cont[data-star-cat="' + star_cat + '"][data-star-rating="' + i + '"]').find('i').addClass('vcm-ota-review-star-full');
		}
		// toggle optional comment if less than 5 stars
		if (star_rating < 5) {
			jQuery('#vcm-writereview-stars-comment-' + star_cat).show();
		} else {
			jQuery('#vcm-writereview-stars-comment-' + star_cat).hide().val('');
		}
		// populate rating in hidden field
		jQuery('#review-cat-' + star_cat).val(star_rating);
	}

	jQuery(document).ready(function() {
		<?php
		if ($success) {
			// dismiss modal or redirect when page loads, if process completed successfully
			echo 'vcmHandleCancelOperation(true)';
		}
		?>
	});
	
</script>
