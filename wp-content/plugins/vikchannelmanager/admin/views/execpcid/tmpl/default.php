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

?>

<div class="vcm-pcid-header">
	<h3><?php echo JText::_('VCMPCIDRESPONSETITLE'); ?></h3>
</div>

<div class="vcm-pcid-body">

	<div class="vcm-pcid-body-block" id="vcm-pcid-body-left">
		<pre><?php foreach ($this->creditCardResponse as $key => $val) {
				echo ucwords(str_replace("_", " ", $key)).": ".$val."\n";
		} ?></pre>
	</div>

	<div class="vcm-pcid-body-block off" id="vcm-pcid-body-right">
		<pre><?php echo htmlentities(urldecode((isset($this->order['paymentlog']) ? $this->order['paymentlog'] : ''))); ?></pre>
	</div>

</div>

<?php
if (stripos($this->order['channel'], 'booking.com') !== false) {
	$backend_reporting_link = JUri::root() . 'administrator/index.php?option=com_vikchannelmanager&task=breporting.invalidCreditCard&otaid=' . $this->order['idorderota'];
	if (defined('ABSPATH')) {
		$backend_reporting_link = admin_url('admin.php?option=com_vikchannelmanager&task=breporting.invalidCreditCard&otaid=' . $this->order['idorderota']);
	}
?>
<div style="text-align: center; margin-top: 15px;">
	<button type="button" onclick="if(confirm('<?php echo addslashes(JText::_('VCMBCOMREPORTINVCARDCONF')); ?>')){window.open('<?php echo $backend_reporting_link; ?>', '_blank');}" class="btn btn-danger"><i class="vboicn-blocked"></i> <?php echo JText::_('VCMBCOMREPORTINVCARD'); ?></button>
</div>
<?php
}
?>

<script>

jQuery(document).ready(function() {

	jQuery('.vcm-pcid-body-block').hover(function() {
		if (jQuery(this).hasClass('off')) {
			jQuery('.vcm-pcid-body-block').addClass('off');
			jQuery(this).removeClass('off');
		}
	}, function() {
		// do nothing on exit
	});

});

</script>