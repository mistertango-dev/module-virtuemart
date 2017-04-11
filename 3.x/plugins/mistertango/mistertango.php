<?php

/**
 *
 * @author Valérie Isaksen
 * @version $Id: Mistertango.php 5122 2011-12-18 22:24:49Z alatak $
 * @package VirtueMart
 * @subpackage payment
 * @copyright Copyright (C) 2004-2008 soeren, 2012-2015 The VirtueMart team and authors - All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
 */
defined('_JEXEC') or die('Restricted access');

if (!class_exists('Creditcard')) {
	require_once(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'creditcard.php');
}
if (!class_exists('vmPSPlugin')) {
	require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

class plgVmpaymentMistertango extends vmPSPlugin {

	private $_cc_name = '';
	private $_cc_type = '';
	private $_cc_number = '';
	private $_cc_cvv = '';
	private $_cc_expire_month = '';
	private $_cc_expire_year = '';
	private $_cc_valid = FALSE;
	private $_errormessage = array();
	protected $_Mistertangonet_params = array(
		"version" => "3.1",
		"delim_char" => ",",
		"delim_data" => "TRUE",
		"relay_response" => "FALSE",
		"encap_char" => "|",
	);
	public $approved;
	public $declined;
	public $error;
	public $held;

	const APPROVED = 1;
	const DECLINED = 2;
	const ERROR = 3;
	const HELD = 4;

	const Mistertango_DEFAULT_PAYMENT_CURRENCY = "USD";

	/**
	 * Constructor
	 *
	 * For php4 compatability we must not use the __constructor as a constructor for plugins
	 * because func_get_args ( void ) returns a copy of all passed arguments NOT references.
	 * This causes problems with cross-referencing necessary for the observer design pattern.
	 *
	 * @param object $subject The object to observe
	 * @param array $config  An array that holds the plugin configuration
	 * @since 1.5
	 */
	// instance of class
	function __construct(& $subject, $config) {

		parent::__construct($subject, $config);

		$this->_loggable = TRUE;
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';
		$this->tableFields = array_keys($this->getTableSQLFields());
		$varsToPush = $this->getVarsToPush();

		$this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
		
		
		
		
	}

	protected function getVmPluginCreateTableSQL() {
		return $this->createTableSQL('Payment Mistertango Table');
	}

	function getTableSQLFields() {

		$SQLfields = array(
			'id' => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id' => 'int(1) UNSIGNED',
			'order_number' => 'char(64)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
			'payment_name' => 'varchar(5000)',
			'payment_order_total' => 'decimal(15,5) NOT NULL',
			'payment_currency' => 'smallint(1)',
			'return_context' => 'char(255)',
			'cost_per_transaction' => 'decimal(10,2)',
			'cost_percent_total' => 'char(10)',
			'tax_id' => 'smallint(1)'
		);
		return $SQLfields;
	}

	/**
	 * This shows the plugin for choosing in the payment list of the checkout process.
	 *
	 * @author Valerie Cartan Isaksen
	 */
	function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, &$htmlIn)
	{

		//JHTML::_ ('behavior.tooltip');

		if ($this->getPluginMethods($cart->vendorId) === 0) {
			if (empty($this->_name)) {
				$app = JFactory::getApplication();
				$app->enqueueMessage(vmText::_('COM_VIRTUEMART_CART_NO_' . strtoupper($this->_psType)));
				return FALSE;
			} else {
				return FALSE;
			}
		}
		$html = array();
		$method_name = $this->_psType . '_name';

		
		VmConfig::loadJLang('com_virtuemart', true);
		//vmJsApi::jCreditCard();
		$htmla = '';
		$html = array();
		foreach ($this->methods as $this->_currentMethod) {
			if ($this->checkConditions($cart, $this->_currentMethod, $cart->pricesUnformatted)) {
				$methodSalesPrice = $this->setCartPrices($cart, $cart->pricesUnformatted, $this->_currentMethod);
				$this->_currentMethod->$method_name = $this->renderPluginName($this->_currentMethod);
				$html = $this->getPluginHtml($this->_currentMethod, $selected, $methodSalesPrice);
				if ($selected == $this->_currentMethod->virtuemart_paymentmethod_id) {
					$this->_getAuthorizeNetFromSession();
				} $dd = "";
				

				$htmla[] = $html;

				
			}
		}
		$htmlIn[] = $htmla;

		return TRUE;
	}



	/**
	 * Check if the payment conditions are fulfilled for this payment method
	 *
	 * @author: Valerie Isaksen
	 *
	 * @param $cart_prices: cart prices
	 * @param $payment
	 * @return true: if the conditions are fulfilled, false otherwise
	 *
	 */
	protected function checkConditions($cart, $method, $cart_prices) {
		$this->convert_condition_amount($method);
		$amount = $this->getCartAmount($cart_prices);
		$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

		$amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
			OR
			($method->min_amount <= $amount AND ($method->max_amount == 0)));
		if (!$amount_cond) {
			return FALSE;
		}
		$countries = array();
		if (!empty($method->countries)) {
			if (!is_array($method->countries)) {
				$countries[0] = $method->countries;
			} else {
				$countries = $method->countries;
			}
		}

		// probably did not gave his BT:ST address
		if (!is_array($address)) {
			$address = array();
			$address['virtuemart_country_id'] = 0;
		}

		if (!isset($address['virtuemart_country_id'])) {
			$address['virtuemart_country_id'] = 0;
		}
		if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
			return TRUE;
		}

		return FALSE;
	}


	function _setAuthorizeNetIntoSession ()
	{
		if (!class_exists('vmCrypt')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'vmcrypt.php');
		}
		$session = JFactory::getSession();
		$sessionAuthorizeNet = new stdClass();
	
		$session->set('Mistertango', json_encode($sessionAuthorizeNet), 'vm');
	}

	function _getAuthorizeNetFromSession() {
		if (!class_exists('vmCrypt')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'vmcrypt.php');
		}
		$session = JFactory::getSession();
		$MistertangonetSession = $session->get('Mistertango', 0, 'vm');

		if (!empty($MistertangonetSession)) {
			$MistertangonetData = (object)json_decode($MistertangonetSession,true);
			
		}
	}

	/**
	 * This is for checking the input data of the payment method within the checkout
	 *
	 * @author Valerie Cartan Isaksen
	 */
	function plgVmOnCheckoutCheckDataPayment(VirtueMartCart $cart) {

		if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
			return NULL; // Another method was selected, do nothing
		}

		if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
			return FALSE;
		}
		$this->_getAuthorizeNetFromSession();
		
		return TRUE;

	}

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 *
	 */
	function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {

		return parent::onStoreInstallPluginTable($jplugin_id);
	}

	/**
	 * This is for adding the input data of the payment method to the cart, after selecting
	 *
	 * @author Valerie Isaksen
	 *
	 * @param VirtueMartCart $cart
	 * @return null if payment not selected; true if card infos are correct; string containing the errors id cc is not valid
	 */
	public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg) {

		if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
			return NULL; // Another method was selected, do nothing
		}

		if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
			return FALSE;
		}

		
		
		$this->_setAuthorizeNetIntoSession();
		return TRUE;
	}

	public function plgVmOnSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$payment_name) {

		if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
			return FALSE;
		}

		$this->_getAuthorizeNetFromSession();
		$cart_prices['payment_tax_id'] = 0;
		$cart_prices['payment_value'] = 0;

		if (!$this->checkConditions($cart, $this->_currentMethod, $cart_prices)) {
			return FALSE;
		}
		$payment_name = $this->renderPluginName($this->_currentMethod);

		$this->setCartPrices($cart, $cart_prices, $this->_currentMethod);

		return TRUE;
	}

	/*
		 * @param $plugin plugin
		 */

	protected function renderPluginName($plugin) {

		$return = '';
		$plugin_name = $this->_psType . '_name';
		$plugin_desc = $this->_psType . '_desc';
		$description = '';
		// 		$params = new JParameter($plugin->$plugin_params);
		// 		$logo = $params->get($this->_psType . '_logos');
		$logosFieldName = $this->_psType . '_logos';
		$logos = $plugin->$logosFieldName;
		if (!empty($logos)) {
			$return = $this->displayLogos($logos) . ' ';
		}
		$sandboxWarning = '';
		if ($plugin->sandbox) {
			$sandboxWarning .= ' <span style="color:red;font-weight:bold">Sandbox (' . $plugin->virtuemart_paymentmethod_id . ')</span><br />';
		}
		if (!empty($plugin->$plugin_desc)) {
			$description = '<span class="' . $this->_type . '_description">' . $plugin->$plugin_desc . '</span>';
		}
		$this->_getAuthorizeNetFromSession();
		$extrainfo = $this->getExtraPluginNameInfo();
		$pluginName = $return . '<span class="' . $this->_type . '_name">' . $plugin->$plugin_name . '</span>' . $description;
		$pluginName .= $sandboxWarning . $extrainfo;
		return $pluginName;
	}

	/**
	 * Display stored payment data for an order
	 *
	 * @see components/com_virtuemart/helpers/vmPaymentPlugin::plgVmOnShowOrderPaymentBE()
	 */
	function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id) {

		if (!($this->_currentMethod = $this->selectedThisByMethodId($virtuemart_payment_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
			return NULL;
		}
		VmConfig::loadJLang('com_virtuemart');

		$html = '<table class="adminlist table">' . "\n";
		$html .= $this->getHtmlHeaderBE();
		$html .= $this->getHtmlRowBE('COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name);
		$html .= $this->getHtmlRowBE('MistertangoNET_PAYMENT_ORDER_TOTAL', $paymentTable->payment_order_total . " " . self::Mistertango_DEFAULT_PAYMENT_CURRENCY);
		$html .= $this->getHtmlRowBE('MistertangoNET_COST_PER_TRANSACTION', $paymentTable->cost_per_transaction);
		$html .= $this->getHtmlRowBE('MistertangoNET_COST_PERCENT_TOTAL', $paymentTable->cost_percent_total);
		$code = "Mistertangonet_response_";
		foreach ($paymentTable as $key => $value) {
			if (substr($key, 0, strlen($code)) == $code) {
				$html .= $this->getHtmlRowBE($key, $value);
			}
		}
		$html .= '</table>' . "\n";
		return $html;
	}

	/**
	 * Reimplementation of vmPaymentPlugin::plgVmOnConfirmedOrderStorePaymentData()
	 */

	/**
	 * Reimplementation of vmPaymentPlugin::plgVmOnConfirmedOrder()
	 *
	 * @link http://www.Mistertango.net/support/AIM_guide.pdf
	 * Credit Cards Test Numbers
	 * Visa Test Account           4007000000027
	 * Amex Test Account           370000000000002
	 * Master Card Test Account    6011000000000012
	 * Discover Test Account       5424000000000015
	 * @author Valerie Isaksen
	 */
	function plgVmConfirmedOrder(VirtueMartCart $cart, $order) {

		if (!($this->_currentMethod = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
			return FALSE;
		}

		$this->setInConfirmOrder($cart);

		$usrBT = $order['details']['BT'];
		
		
		$usrST = ((isset($order['details']['ST'])) ? $order['details']['ST'] : $order['details']['BT']);
		$session = JFactory::getSession();
		$return_context = $session->getId();

		$payment_currency_id = shopFunctions::getCurrencyIDByName(self::Mistertango_DEFAULT_PAYMENT_CURRENCY);
		$totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total,$payment_currency_id);
		$cd = CurrencyDisplay::getInstance($cart->pricesCurrency);

		// Set up data
		$formdata = array();
		$formdata = array_merge($this->_setHeader(), $formdata);
		
		$formdata = array_merge($this->_setBillingInformation($usrBT), $formdata);
		$formdata = array_merge($this->_setShippingInformation($usrST), $formdata);
		
		
		// prepare the array to post
		$poststring = '';
		foreach ($formdata AS $key => $val) {
			$poststring .= urlencode($key) . "=" . urlencode($val) . "&";
		}
		$poststring = rtrim($poststring, "& ");

		// Prepare data that should be stored in the database
		$dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['virtuemart_order_id'] = $order['details']['BT']->virtuemart_order_id;
		$dbValues['payment_method_id'] = $order['details']['BT']->virtuemart_paymentmethod_id;
		$dbValues['return_context'] = $return_context;
		$dbValues['payment_name'] = parent::renderPluginName($this->_currentMethod);
		$dbValues['cost_per_transaction'] = $this->_currentMethod->cost_per_transaction;
		$dbValues['cost_percent_total'] = $this->_currentMethod->cost_percent_total;
		$pay = $dbValues['payment_order_total'] = $totalInPaymentCurrency['value'];
		$dbValues['payment_currency'] = $payment_currency_id;

		$this->storePSPluginInternalData($dbValues);

		// send a request
		//$response = $this->_sendRequest($poststring);
		$session = JFactory::getSession();
		
		$session->set('username',$this->_currentMethod->username);
		$session->set('order',		$dbValues['order_number']);
		$session->set('amount',$pay);
		$session->set('email',$formdata['x_email']);
		$session->set('secret',$formdata['secret']);
 
	    $this->debugLog($response , "plgVmConfirmedOrder", 'debug');


		$authnet_values = array(); // to check the values???
		// evaluate the response
		//$html = $this->_handleResponse($response, $authnet_values, $order, $dbValues['payment_name']);
		if ($this->error) {
			$new_status = $this->_currentMethod->payment_declined_status;
			$this->_handlePaymentCancel($order['details']['BT']->virtuemart_order_id, $html);
			return; // will not process the order
		} else {
			if ($this->approved) {
				$this->_clearAuthorizeNetSession();
				$new_status = $this->_currentMethod->payment_approved_status;
			} else {
				if ($this->declined) {
					vRequest::setVar('html', $html);
					$new_status = $this->_currentMethod->payment_declined_status;
					$this->_handlePaymentCancel($order['details']['BT']->virtuemart_order_id, $html);
					return;
				} else {
					if ($this->held) {
						$this->_clearAuthorizeNetSession();
						$new_status = $this->_currentMethod->payment_held_status;
					}
				}
			}
		}
		$modelOrder = VmModel::getModel('orders');
		$order['order_status'] = $new_status;
		$order['customer_notified'] = 1;
		$order['comments'] = '';
		$modelOrder->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order, TRUE);

		//We delete the old stuff
		$cart->emptyCart();
		vRequest::setVar('html', $html);
		
		$application = JFactory::getApplication();
		$application->enqueueMessage(JText::_('MSG'), 'Warning');
		$application->redirect('index.php?option=com_mistertango&view=payment');


	
	}

	function _handlePaymentCancel($virtuemart_order_id, $html) {

		if (!class_exists('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}
		$modelOrder = VmModel::getModel('orders');
		//$modelOrder->remove(array('virtuemart_order_id' => $virtuemart_order_id));
		// error while processing the payment
		$mainframe = JFactory::getApplication();
		$mainframe->enqueueMessage($html);
		$mainframe->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart&task=editpayment', FALSE), vmText::_('COM_VIRTUEMART_CART_ORDERDONE_DATA_NOT_VALID'));
	}

	/**
	 * @param $virtuemart_paymentmethod_id
	 * @param $paymentCurrencyId
	 * @return bool|null
	 */
	function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

		if (!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
			return FALSE;
		}
		$this->_currentMethod->payment_currency = self::Mistertango_DEFAULT_PAYMENT_CURRENCY;

		if (!class_exists('VirtueMartModelVendor')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'vendor.php');
		}
		$vendorId = 1; //VirtueMartModelVendor::getLoggedVendor();
		$db = JFactory::getDBO();

		$q = 'SELECT   `virtuemart_currency_id` FROM `#__virtuemart_currencies` WHERE `currency_code_3`= "' . self::Mistertango_DEFAULT_PAYMENT_CURRENCY . '"';
		$db->setQuery($q);
		$paymentCurrencyId = $db->loadResult();
	}

	function _clearAuthorizeNetSession() {

		$session = JFactory::getSession();
		$session->clear('Mistertango', 'vm');
	}

	/**
	 * renderPluginName
	 * Get the name of the payment method
	 *
	 * @author Valerie Isaksen
	 * @param  $payment
	 * @return string Payment method name
	 */
	function getExtraPluginNameInfo() {


	}



	/**
	 * _getFormattedDate
	 *
	 *
	 */
	function _getFormattedDate($month, $year) {

		return sprintf('%02d-%04d', $month, $year);
	}

	function _setHeader() {

		return $this->_Mistertangonet_params;
	}





	function _getfield($string, $length) {
		if (!class_exists('shopFunctionsF')) {
			require(VMPATH_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
		}
		return ShopFunctionsF::vmSubstr($string, 0, $length);
	}

	function _setBillingInformation($usrBT) {
		if (!class_exists('ShopFunctions'))
			require(VMPATH_ADMIN . DS . 'helpers' . DS . 'shopfunctions.php');
		$clientIp= ShopFunctions::getClientIP();
		// Customer Name and Billing Address
		return array(
			'x_email' => isset($usrBT->email) ? $this->_getField($usrBT->email, 100) : '', //get email
			'x_first_name' => isset($usrBT->first_name) ? $this->_getField($usrBT->first_name, 50) : '',
			'x_last_name' => isset($usrBT->last_name) ? $this->_getField($usrBT->last_name, 50) : '',
			'x_company' => isset($usrBT->company) ? $this->_getField($usrBT->company, 50) : '',
			'x_address' => isset($usrBT->address_1) ? $this->_getField($usrBT->address_1, 60) : '',
			'x_city' => isset($usrBT->city) ? $this->_getField($usrBT->city, 40) : '',
			'x_zip' => isset($usrBT->zip) ? $this->_getField($usrBT->zip, 20) : '',
			'x_state' => isset($usrBT->virtuemart_state_id) ? $this->_getField(ShopFunctions::getStateByID($usrBT->virtuemart_state_id), 40) : '',
			'x_country' => isset($usrBT->virtuemart_country_id) ? $this->_getField(ShopFunctions::getCountryByID($usrBT->virtuemart_country_id), 60) : '',
			'x_phone' => isset($usrBT->phone_1) ? $this->_getField($usrBT->phone_1, 25) : '',
			'x_fax' => isset($usrBT->fax) ? $this->_getField($usrBT->fax, 25) : '',
			'x_customer_ip' => $clientIp,
		);
	}

	function _setShippingInformation($usrST) {

		// Customer Name and Billing Address
		return array(
			'x_ship_to_first_name' => isset($usrST->first_name) ? $this->_getField($usrST->first_name, 50) : '',
			'x_ship_to_last_name' => isset($usrST->first_name) ? $this->_getField($usrST->last_name, 50) : '',
			'x_ship_to_company' => isset($usrST->company) ? $this->_getField($usrST->company, 50) : '',
			'x_ship_to_address' => isset($usrST->first_name) ? $this->_getField($usrST->address_1, 60) : '',
			'x_ship_to_city' => isset($usrST->city) ? $this->_getField($usrST->city, 40) : '',
			'x_ship_to_zip' => isset($usrST->zip) ? $this->_getField($usrST->zip, 20) : '',
			'x_ship_to_state' => isset($usrST->virtuemart_state_id) ? $this->_getField(ShopFunctions::getStateByID($usrST->virtuemart_state_id), 40) : '',
			'x_ship_to_country' => isset($usrST->virtuemart_country_id) ? $this->_getField(ShopFunctions::getCountryByID($usrST->virtuemart_country_id), 60) : '',
		);
	}







	/**
	 * We must reimplement this triggers for joomla 1.7
	 */

	/**
	 * plgVmOnCheckAutomaticSelectedPayment
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 *
	 * @author Valerie Isaksen
	 * @param VirtueMartCart cart: the cart object
	 * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
	 *
	 */

	function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {

		$return = $this->onCheckAutomaticSelected($cart, $cart_prices);
		if (isset($return)) {
			return 0;
		} else {
			return NULL;
		}
	}

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the method-specific data.
	 *
	 * @param integer $order_id The order ID
	 * @return mixed Null for methods that aren't active, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	protected function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {

		$this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
		return TRUE;
	}

	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $method_id  method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	function plgVmOnShowOrderPrintPayment($order_number, $method_id) {

		return parent::onShowOrderPrint($order_number, $method_id);
	}

	/**
	 * Save updated order data to the method specific table
	 *
	 * @param array $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.


	public function plgVmOnUpdateOrderPayment(  $_formData) {
	return null;
	}
	 */
	/**
	 * Save updated orderline data to the method specific table
	 *
	 * @param array $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.


	public function plgVmOnUpdateOrderLine(  $_formData) {
	return null;
	}
	 */
	/**
	 * plgVmOnEditOrderLineBE
	 * This method is fired when editing the order line details in the backend.
	 * It can be used to add line specific package codes
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise


	public function plgVmOnEditOrderLineBE(  $_orderId, $_lineId) {
	return null;
	}
	 */

	/**
	 * This method is fired when showing the order details in the frontend, for every orderline.
	 * It can be used to display line specific package codes, e.g. with a link to external tracking and
	 * tracing systems
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise

	public function plgVmOnShowOrderLineFE(  $_orderId, $_lineId) {
	return null;
	}
	 */
	function plgVmDeclarePluginParamsPaymentVM3( &$data) {
		return $this->declarePluginParams('payment', $data);
	}

	function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {

		return $this->setOnTablePluginParams($name, $id, $table);
	}


}

// No closing tag
