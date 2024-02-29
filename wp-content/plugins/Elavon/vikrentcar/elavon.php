<?php
/**
 * @package     VikElavon
 * @subpackage  vikrentcar
 * @author      Lorenzo - E4J s.r.l.
 * @copyright   Copyright (C) 2019 VikWP All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://vikwp.com
 */

defined('ABSPATH') or die('No script kiddies please!');

JLoader::import('elavon', VIKELAVON_DIR);

// Prepend the deposit message before the payment form (only if specified).
// Store the SESSION ID of the transaction for later use.
add_action('payment_after_begin_transaction_vikrentcar', function(&$payment, &$html)
{
	// make sure the driver is elavon
	if (!$payment->isDriver('elavon'))
	{
		return;
	}

	if ($payment->get('leave_deposit'))
	{
		$html = '<p class="vrc-leave-deposit">
			<span>' . JText::_('VRLEAVEDEPOSIT') . '</span>' . 
			$payment->get('currency_symb') . ' ' . number_format($payment->get('total_to_pay'), 2) . 
		'</p><br/>' . $html;
	}
	
	

}, 10, 2);

// vikrentcar doesn't have a return_url to use within the afterValidation method.
// Use this hook to construct it and route it following the shortcodes standards.
add_action('payment_on_after_validation_vikrentcar', function(&$payment, $res)
{
	// make sure the driver is elavon
	if (!$payment->isDriver('elavon'))
	{
		return;
	}


	$url = 'index.php?option=com_vikrentcar&view=order&sid=' . $payment->get('sid') . '&ts=' . $payment->get('ts');

	$model 		= JModel::getInstance('vikrentcar', 'shortcodes', 'admin');
	$itemid 	= $model->best(array('order'));
	
	if ($itemid)
	{
		$url = JRoute::_($url . '&Itemid=' . $itemid, false);
	}

	JFactory::getApplication()->redirect($url);
	exit;
}, 10, 2);

/**
 * This class is used to collect payments in vikrentcar plugin
 * by using the elavon gateway.
 *
 * @since 1.0
 */
class VikRentCarElavonPayment extends AbstractElavonPayment
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

		$details = $this->get('order', array());

		$this->set('oid', $this->get('id', null));
		
		if (!$this->get('oid'))
		{
			$this->set('oid', isset($details['id']) ? $details['id'] : 0);
		}

		if (!$this->get('sid'))
		{
			$this->set('sid', isset($details['sid']) ? $details['sid'] : 0);
		}

		if (!$this->get('ts'))
		{
			$this->set('ts', isset($details['ts']) ? $details['ts'] : 0);
		}

		if (!$this->get('custmail'))
		{
			$this->set('custmail', isset($details['custmail']) ? $details['custmail'] : '');
		}
	}
	

}
