<?php
/**
 * @package     VikElavon
 * @subpackage  vikappointments
 * @author      Lorenzo Monsani - E4J s.r.l.
 * @copyright   Copyright (C) 2021 VikWP All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://vikwp.com
 */

defined('ABSPATH') or die('No script kiddies please!');

JLoader::import('elavon', VIKELAVON_DIR);

/**
 * This class is used to collect payments in VikAppointments plugin
 * by using the cybersource gateway.
 *
 * @since 1.0
 */
class VikAppointmentsElavonPayment extends AbstractElavonPayment
{
	/**
	 * @override
	 * Class constructor.
	 *
	 * @param 	string 	$alias 	 The name of the plugin that requested the payment.
	 * @param 	mixed 	$order 	 The order details to start the transaction.
	 * @param 	mixed 	$params  The configuration of the payment.
	 */
	public function __construct($alias, $order, $params = array())
	{
		parent::__construct($alias, $order, $params);

		if (!$this->get('custmail'))
		{
			$details = $this->get('details', array());
			$this->set('custmail', isset($details['purchaser_mail']) ? $details['purchaser_mail'] : '');
		}
	}
}
