<?php
/**
 * @package     VikElavon
 * @subpackage  Elavon
 * @author      Lorenzo Monsani - E4J s.r.l.
 * @copyright   Copyright (C) 2021 VikWP All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://vikwp.com
 */

defined('ABSPATH') or die('No script kiddies please!');

JLoader::import('adapter.payment.payment');

/**
 * This class is used to collect payments through the Stripe gateway.
 *
 * @since 1.0
 */
abstract class AbstractElavonPayment extends JPayment
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
		
		$this->setParam('currency', strtolower(substr($this->getParam('currency'), 0, 3)));
		$this->setParam('ssl', $this->getParam('ssl') == __('Yes', 'vikstripe') ? 1 : 0);
	}

	/**
	 * @override
	 * Method used to build the associative array 
	 * to allow the plugins to construct a configuration form.
	 *
	 * In case the payment needs an API Key, the array should
	 * be built as follows:
	 *
	 * {"apikey": {"type": "text", "label": "API Key"}}
	 *
	 * @return 	array 	The associative array.
	 */
	protected function buildAdminParameters()
	{
		return array(
			'logo' => array(
				'label' => '',
				'type'  => 'custom',
				'html'  => '<img src="' . VIKELAVON_URI . 'Elavon/logo.png" style="margin-bottom: 15px;"/>',
			),
			'secret' => array(
				'label' => __('Shared Secret Key', 'vikelavon'),
				'type' => 'text'
			),
			'merch_id' => array(
				'label' => __('Merchant ID', 'vikelavon'),
				'type' => 'text'
			),
			'account' => array(
				'label' => __('Account Name', 'vikelavon'),
				'type' => 'text'
			),
			'currency'  => array(
				'label' => __('Currency', 'vikelavon'),
				'type'  => 'select',
				'options' => array(
						'AED: United Arab Emirates Dirham',
						'AFN: Afghan Afghani',
						'ALL: Albanian Lek',
						'AMD: Armenian Dram',
						'ANG: Netherlands Antillean Gulden',
						'AOA: Angolan Kwanza',
						'ARS: Argentine Peso',
						'AUD: Australian Dollar',
						'AWG: Aruban Florin',
						'AZN: Azerbaijani Manat',
						'BAM: Bosnia & Herzegovina Convertible Mark',
						'BBD: Barbadian Dollar',
						'BDT: Bangladeshi Taka',
						'BGN: Bulgarian Lev',
						'BIF: Burundian Franc',
						'BMD: Bermudian Dollar',
						'BND: Brunei Dollar',
						'BOB: Bolivian Boliviano',
						'BRL: Brazilian Real',
						'BSD: Bahamian Dollar',
						'BWP: Botswana Pula',
						'BZD: Belize Dollar',
						'CAD: Canadian Dollar',
						'CDF: Congolese Franc',
						'CHF: Swiss Franc',
						'CLP: Chilean Peso',
						'CNY: Chinese Renminbi Yuan',
						'COP: Colombian Peso',
						'CRC: Costa Rican Colón',
						'CVE: Cape Verdean Escudo',
						'CZK: Czech Koruna',
						'DJF: Djiboutian Franc',
						'DKK: Danish Krone',
						'DOP: Dominican Peso',
						'DZD: Algerian Dinar',
						'EEK: Estonian Kroon',
						'EGP: Egyptian Pound',
						'ETB: Ethiopian Birr',
						'EUR: Euro',
						'FJD: Fijian Dollar',
						'FKP: Falkland Islands Pound',
						'GBP: British Pound',
						'GEL: Georgian Lari',
						'GIP: Gibraltar Pound',
						'GMD: Gambian Dalasi',
						'GNF: Guinean Franc',
						'GTQ: Guatemalan Quetzal',
						'GYD: Guyanese Dollar',
						'HKD: Hong Kong Dollar',
						'HNL: Honduran Lempira',
						'HRK: Croatian Kuna',
						'HTG: Haitian Gourde',
						'HUF: Hungarian Forint',
						'IDR: Indonesian Rupiah',
						'ILS: Israeli New Sheqel',
						'INR: Indian Rupee',
						'ISK: Icelandic Króna',
						'JMD: Jamaican Dollar',
						'JPY: Japanese Yen',
						'KES: Kenyan Shilling',
						'KGS: Kyrgyzstani Som',
						'KHR: Cambodian Riel',
						'KMF: Comorian Franc',
						'KRW: South Korean Won',
						'KYD: Cayman Islands Dollar',
						'KZT: Kazakhstani Tenge',
						'LAK: Lao Kip',
						'LBP: Lebanese Pound',
						'LKR: Sri Lankan Rupee',
						'LRD: Liberian Dollar',
						'LSL: Lesotho Loti',
						'LTL: Lithuanian Litas',
						'LVL: Latvian Lats',
						'MAD: Moroccan Dirham',
						'MDL: Moldovan Leu',
						'MGA: Malagasy Ariary',
						'MKD: Macedonian Denar',
						'MNT: Mongolian Tögrög',
						'MOP: Macanese Pataca',
						'MRO: Mauritanian Ouguiya',
						'MUR: Mauritian Rupee',
						'MVR: Maldivian Rufiyaa',
						'MWK: Malawian Kwacha',
						'MXN: Mexican Peso',
						'MYR: Malaysian Ringgit',
						'MZN: Mozambican Metical',
						'NAD: Namibian Dollar',
						'NGN: Nigerian Naira',
						'NIO: Nicaraguan Córdoba',
						'NOK: Norwegian Krone',
						'NPR: Nepalese Rupee',
						'NZD: New Zealand Dollar',
						'PAB: Panamanian Balboa',
						'PEN: Peruvian Nuevo Sol',
						'PGK: Papua New Guinean Kina',
						'PHP: Philippine Peso',
						'PKR: Pakistani Rupee',
						'PLN: Polish Złoty',
						'PYG: Paraguayan Guaraní',
						'QAR: Qatari Riyal',
						'RON: Romanian Leu',
						'RSD: Serbian Dinar',
						'RUB: Russian Ruble',
						'RWF: Rwandan Franc',
						'SAR: Saudi Riyal',
						'SBD: Solomon Islands Dollar',
						'SCR: Seychellois Rupee',
						'SEK: Swedish Krona',
						'SGD: Singapore Dollar',
						'SHP: Saint Helenian Pound',
						'SLL: Sierra Leonean Leone',
						'SOS: Somali Shilling',
						'SRD: Surinamese Dollar',
						'STD: São Tomé and Príncipe Dobra',
						'SVC: Salvadoran Colón',
						'SZL: Swazi Lilangeni',
						'THB: Thai Baht',
						'TJS: Tajikistani Somoni',
						'TOP: Tongan Paʻanga',
						'TRY: Turkish Lira',
						'TTD: Trinidad and Tobago Dollar',
						'TWD: New Taiwan Dollar',
						'TZS: Tanzanian Shilling',
						'UAH: Ukrainian Hryvnia',
						'UGX: Ugandan Shilling',
						'USD: United States Dollar',
						'UYU: Uruguayan Peso',
						'UZS: Uzbekistani Som',
						'VEF: Venezuelan Bolívar',
						'VND: Vietnamese Đồng',
						'VUV: Vanuatu Vatu',
						'WST: Samoan Tala',
						'XAF: Central African Cfa Franc',
						'XCD: East Caribbean Dollar',
						'XOF: West African Cfa Franc',
						'XPF: Cfp Franc',
						'YER: Yemeni Rial',
						'ZAR: South African Rand',
						'ZMW: Zambian Kwacha',
				),
				'test' => array(
					'label' => 'Test Mode',
					'type' => 'select',
					'options' => array(__('No'), __('Yes'))
				),
			),
		);
	}
	
	/**
	 * @override
	 * Method used to begin a payment transaction.
	 * This method usually generates the HTML form of the payment.
	 * The HTML contents can be echoed directly because this method
	 * is executed always within a buffer.
	 *
	 * @return 	void
	 */
	protected function beginTransaction()
	{
		$amount_to_pay = round($this->get('total_to_pay'),2,PHP_ROUND_HALF_EVEN)*100;
		$txname = $this->get('oid').'.tx';
		
		$timestamp = strftime("%Y%m%d%H%M%S");
		$fp = fopen(dirname(__FILE__).DIRECTORY_SEPARATOR.'Elavon'.DIRECTORY_SEPARATOR.$txname, 'w+');
		fwrite($fp, $this->get('total_to_pay').'^'.$timestamp);
		fclose($fp);

		$url = $this->getParam('test') == 'YES' ? "https://pay.sandbox.elavonpaymentgateway.com/pay" : "https://pay.elavonpaymentgateway.com/pay";

	
		$tmp = $timestamp.".".$this->getParam('merch_id').".".$this->get('oid').".".$amount_to_pay.".".$this->getParam('currency');
		$sha1hash = sha1($tmp);
		$tmp = $sha1hash.".".$this->getParam('secret');
		$sha1hash = sha1($tmp);

		$form = '<form action="'.$url.'" method=POST >

					<input type=hidden name="MERCHANT_ID" value="'.$this->getParam('merch_id').'">
					<input type=hidden name="ORDER_ID" value="'.$this->get('oid').'">
					<input type=hidden name="CURRENCY" value="'.$this->getParam('currency').'">
					<input type=hidden name="AMOUNT" value="'.$amount_to_pay.'">
					<input type=hidden name="TIMESTAMP" value="'.$timestamp.'">
					<input type=hidden name="SHA1HASH" value="'.$sha1hash.'">
					<input type=hidden name="AUTO_SETTLE_FLAG" value="1">
					<input type="hidden" name="MERCHANT_RESPONSE_URL" value="'.$this->get('notify_url').'">
					<input type=submit class="btn btn-elavon-submit" value="Proceed to Payment">

				</form>';

		echo $form;

		return true;
	}
	
	/**
	 * @override
	 * Method used to validate the payment transaction.
	 * It is usually an end-point that the providers use to POST the
	 * transaction data.
	 *
	 * @param 	JPaymentStatus 	&$status 	The status object. In case the payment was 
	 * 										successful, you should invoke: $status->verified().
	 *
	 * @return 	void
	 *
	 * @see 	JPaymentStatus
	 */
	protected function validateTransaction(JPaymentStatus &$status)
	{

		$array_result = array();
		$array_result['log'] = "";
		$array_result['verified'] = 0;
		$array_result['tot_paid'] = 0;
		$file = '';

		//dropback in case the transaction file is missing
		$charge_amount = $this->get('total_to_pay') * 100;
		$txname = $_REQUEST['oid'] . '.tx';
		$fp = fopen(dirname(__FILE__).DIRECTORY_SEPARATOR.'Elavon'.DIRECTORY_SEPARATOR.$txname, 'r');
		$file = fread($fp, filesize(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Elavon' . DIRECTORY_SEPARATOR . $txname));
		fclose($fp);

		$parts = array();
		$parts = explode('^', $file);

		$amount_to_pay = $parts[0] ? $parts[0] : $charge_amount;
		$timestamp = $parts[1];
		$amount_to_pay = $amount_to_pay * 100;
	
		$tmp = $timestamp . "." . $this->getParam('merch_id') . "." . $this->get('oid') . "." . $_REQUEST['RESULT'] . "." . $_REQUEST['MESSAGE'] . "." . $_REQUEST['PASREF'] . "." . $_REQUEST['AUTHCODE'];
		$array_result['log'] .= $tmp. "\n";
		$sha1hash = sha1($tmp);

		$tmp = $sha1hash . "." . $this->getParam('secret');
		$array_result['log'] .= $tmp. "\n";
		$sha1hash = sha1($tmp);

		$array_result['log'] .= $_REQUEST . " \n sha1 hash calculated" . $sha1hash . " \n sha1 hash received" . $_REQUEST['SHA1HASH'];

		if ($_REQUEST['SHA1HASH'] == $sha1hash) {
			//status confirmed
			$status->verified();
			//set amount paid
			$status->paid($amount_to_pay / 100);
			unlink(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Elavon' . DIRECTORY_SEPARATOR . $txname);
		} 

		//append logs
		$status->appendLog($array_result['log']."\n");


	}
	
	/**
	 * @override
	 * Method used to finalise the payment.
	 * e.g. enter here the code used to redirect the
	 * customers to a specific landing page.
	 *
	 * @param 	boolean  $res 	True if the payment was successful, otherwise false.
	 *
	 * @return 	void
	 */
	protected function complete($res)
	{
		$redirect_url = $this->get('return_url');
		echo "	<a href=".$redirect_url."style=\"display: block; text-align: center; padding: 100px 0;font-size: large;\"> Go Back to our Website </a>";
		echo "	<script>
					setTimeout(function() {
						window.location.href = '" .$redirect_url. "';			
					}, 2000);
				</script>";
				die;
	}
}
