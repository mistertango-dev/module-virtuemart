<?php

defined('_JEXEC') or die('Restricted access');

if (!class_exists('Creditcard'))
{
	require_once JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'creditcard.php';
}
if (!class_exists('vmPSPlugin'))
{
	require JPATH_VM_PLUGINS . DS . 'vmpsplugin.php';
}

/**
 * Class PlgVmpaymentMistertango
 */
class PlgVmpaymentMistertango extends vmPSPlugin
{
	/**
	 * @var array
	 */
	protected $_Mistertangonet_params = array(
		"version"        => "3.1",
		"delim_char"     => ",",
		"delim_data"     => "TRUE",
		"relay_response" => "FALSE",
		"encap_char"     => "|",
	);

	/**
	 * @var
	 */
	public $approved;

	/**
	 * @var
	 */
	public $declined;

	/**
	 * @var
	 */
	public $error;

	/**
	 * @var
	 */
	public $held;

	/**
	 *
	 */
	const APPROVED = 1;

	/**
	 *
	 */
	const DECLINED = 2;

	/**
	 *
	 */
	const ERROR = 3;

	/**
	 *
	 */
	const HELD = 4;

	/**
	 *
	 */
	const Mistertango_DEFAULT_PAYMENT_CURRENCY = "EUR";

	/**
	 * PlgVmpaymentMistertango constructor.
	 *
	 * @param object $subject
	 * @param array  $config
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);

		$this->_loggable   = true;
		$this->_tablepkey  = 'id';
		$this->_tableId    = 'id';
		$this->tableFields = array_keys($this->getTableSQLFields());
		$varsToPush        = $this->getVarsToPush();

		$this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
	}

	/**
	 * @return string
	 */
	protected function getVmPluginCreateTableSQL()
	{
		return $this->createTableSQL('Payment Mistertango Table');
	}

	/**
	 * @return array
	 */
	public function getTableSQLFields()
	{
		$SQLfields = array(
			'id'                          => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id'         => 'int(1) UNSIGNED',
			'order_number'                => 'char(64)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
			'payment_name'                => 'varchar(5000)',
			'payment_order_total'         => 'decimal(15,5) NOT NULL',
			'payment_currency'            => 'smallint(1)',
			'return_context'              => 'char(255)',
			'cost_per_transaction'        => 'decimal(10,2)',
			'cost_percent_total'          => 'char(10)',
			'tax_id'                      => 'smallint(1)'
		);

		return $SQLfields;
	}

	/**
	 * @param VirtueMartCart $cart
	 * @param int            $selected
	 * @param                $htmlIn
	 *
	 * @return bool
	 */
	public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
	{
		if ($this->getPluginMethods($cart->vendorId) === 0)
		{
			if (empty($this->_name))
			{
				$app = JFactory::getApplication();
				$app->enqueueMessage(vmText::_('COM_VIRTUEMART_CART_NO_' . strtoupper($this->_psType)));

				return false;
			}
			else
			{
				return false;
			}
		}
		$method_name = $this->_psType . '_name';

		VmConfig::loadJLang('com_virtuemart', true);

		$htmla = '';
		foreach ($this->methods as $currentMethod)
		{
			if ($this->checkConditions($cart, $currentMethod, $cart->pricesUnformatted))
			{
				$methodSalesPrice            = $this->setCartPrices($cart, $cart->pricesUnformatted, $currentMethod);
				$currentMethod->$method_name = $this->renderPluginName($currentMethod);
				$html                        = $this->getPluginHtml($currentMethod, $selected, $methodSalesPrice);
				$htmla[]                     = $html;
			}
		}
		$htmlIn[] = $htmla;

		return true;
	}

	/**
	 * @param VirtueMartCart $cart
	 * @param int            $method
	 * @param array          $cart_prices
	 *
	 * @return bool
	 */
	protected function checkConditions($cart, $method, $cart_prices)
	{
		$this->convert_condition_amount($method);
		$amount  = $this->getCartAmount($cart_prices);
		$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

		if ($amount < $method->min_amount || ($method->max_amount != 0 && $amount > $method->max_amount))
		{
			return false;
		}

		$countries = array();
		if (!empty($method->countries))
		{
			if (!is_array($method->countries))
			{
				$countries[0] = $method->countries;
			}
			else
			{
				$countries = $method->countries;
			}
		}

		if (!is_array($address))
		{
			$address                          = array();
			$address['virtuemart_country_id'] = 0;
		}

		if (!isset($address['virtuemart_country_id']))
		{
			$address['virtuemart_country_id'] = 0;
		}

		if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0)
		{
			return true;
		}

		return false;
	}

	/**
	 * @param VirtueMartCart $cart
	 *
	 * @return bool|null
	 */
	public function plgVmOnCheckoutCheckDataPayment(VirtueMartCart $cart)
	{
		if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id))
		{
			return null;
		}

		$currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id);
		if (!$currentMethod)
		{
			return false;
		}

		return true;
	}

	/**
	 * @param $jplugin_id
	 *
	 * @return bool|mixed
	 */
	public function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
	{
		return parent::onStoreInstallPluginTable($jplugin_id);
	}

	/**
	 * @param VirtueMartCart $cart
	 * @param                $msg
	 *
	 * @return bool|null
	 */
	public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg)
	{
		if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id))
		{
			return null;
		}

		$currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id);
		if (!$currentMethod)
		{
			return false;
		}

		if (!class_exists('vmCrypt'))
		{
			require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'vmcrypt.php');
		}
		$session            = JFactory::getSession();
		$sessionMistertango = new stdClass();

		$session->set('Mistertango', json_encode($sessionMistertango), 'vm');

		return true;
	}

	/**
	 * @param VirtueMartCart $cart
	 * @param array          $cart_prices
	 * @param                $payment_name
	 *
	 * @return bool|null
	 */
	public function plgVmOnSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$payment_name)
	{
		$currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id);
		if (!$currentMethod)
		{
			return null;
		}

		if (!$this->selectedThisElement($currentMethod->payment_element))
		{
			return false;
		}

		$cart_prices['payment_tax_id'] = 0;
		$cart_prices['payment_value']  = 0;

		if (!$this->checkConditions($cart, $currentMethod, $cart_prices))
		{
			return false;
		}

		$payment_name = $this->renderPluginName($currentMethod);

		$this->setCartPrices($cart, $cart_prices, $currentMethod);

		return true;
	}

	/**
	 * @param $plugin
	 *
	 * @return string
	 */
	protected function renderPluginName($plugin)
	{
		$return         = '';
		$plugin_name    = $this->_psType . '_name';
		$plugin_desc    = $this->_psType . '_desc';
		$description    = '';
		$logosFieldName = $this->_psType . '_logos';
		$logos          = $plugin->$logosFieldName;
		if (!empty($logos))
		{
			$return = $this->displayLogos($logos) . ' ';
		}
		$sandboxWarning = '';
		if ($plugin->sandbox)
		{
			$sandboxWarning .= ' <span style="color:red;font-weight:bold">Sandbox (' . $plugin->virtuemart_paymentmethod_id . ')</span><br />';
		}
		if (!empty($plugin->$plugin_desc))
		{
			$description = '<span class="' . $this->_type . '_description">' . $plugin->$plugin_desc . '</span>';
		}
		$pluginName = $return . '<span class="' . $this->_type . '_name">' . $plugin->$plugin_name . '</span>' . $description;
		$pluginName .= $sandboxWarning;

		return $pluginName;
	}

	/**
	 * @param $virtuemart_order_id
	 * @param $virtuemart_payment_id
	 *
	 * @return null|string
	 */
	public function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id)
	{
		$currentMethod = $this->selectedThisByMethodId($virtuemart_payment_id);
		if (!$currentMethod)
		{
			return null;
		}

		if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id)))
		{
			return null;
		}

		VmConfig::loadJLang('com_virtuemart');

		$html = '<table class="adminlist table">' . "\n";
		$html .= $this->getHtmlHeaderBE();
		$html .= $this->getHtmlRowBE('COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name);
		$html .= $this->getHtmlRowBE('MistertangoNET_PAYMENT_ORDER_TOTAL', $paymentTable->payment_order_total . " " . self::Mistertango_DEFAULT_PAYMENT_CURRENCY);
		$html .= $this->getHtmlRowBE('MistertangoNET_COST_PER_TRANSACTION', $paymentTable->cost_per_transaction);
		$html .= $this->getHtmlRowBE('MistertangoNET_COST_PERCENT_TOTAL', $paymentTable->cost_percent_total);
		$code = "Mistertangonet_response_";
		foreach ($paymentTable as $key => $value)
		{
			if (substr($key, 0, strlen($code)) == $code)
			{
				$html .= $this->getHtmlRowBE($key, $value);
			}
		}
		$html .= '</table>' . "\n";

		return $html;
	}

	/**
	 * @param VirtueMartCart $cart
	 * @param                $order
	 *
	 * @return bool
	 */
	public function plgVmConfirmedOrder(VirtueMartCart $cart, $order)
	{
		$method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id);
		if (!$method)
		{
			return null;
		}

		if (!$this->selectedThisElement($method->payment_element))
		{
			return false;
		}

		VmConfig::loadJLang('com_virtuemart', true);
		VmConfig::loadJLang('com_virtuemart_orders', true);

		if (!class_exists('VirtueMartModelOrders'))
		{
			require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
		}

		$this->getPaymentCurrency($method, $order['details']['BT']->payment_currency_id);
		$currency_code_3 = shopFunctions::getCurrencyByID($method->payment_currency, 'currency_code_3');
		$email_currency  = $this->getEmailCurrency($method);

		$totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total, $method->payment_currency);

		$dbValues['payment_name']                = $this->renderPluginName($method) . '<br />' . $method->payment_info;
		$dbValues['order_number']                = $order['details']['BT']->order_number;
		$dbValues['virtuemart_paymentmethod_id'] = $order['details']['BT']->virtuemart_paymentmethod_id;
		$dbValues['cost_per_transaction']        = $method->cost_per_transaction;
		$dbValues['cost_min_transaction']        = $method->cost_min_transaction;
		$dbValues['cost_percent_total']          = $method->cost_percent_total;
		$dbValues['payment_currency']            = $currency_code_3;
		$dbValues['email_currency']              = $email_currency;
		$dbValues['payment_order_total']         = $totalInPaymentCurrency['value'];
		$dbValues['tax_id']                      = $method->tax_id;
		$this->storePSPluginInternalData($dbValues);

		$modelOrder                 = VmModel::getModel('orders');
		$order['order_status']      = $this->getNewStatus($method);
		$order['customer_notified'] = 1;
		$order['comments']          = '';
		$modelOrder->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order, true);

		$cart->emptyCart();

		$session = JFactory::getSession();
		$session->set('username', $method->username);
		$session->set('secret', $method->secret);
		$session->set('callback_url', $method->callback_url);
		$session->set('order', $order['details']['BT']->order_number);
		$session->set('email', $order['details']['BT']->email);
		$session->set('amount', $order['details']['BT']->order_total);

		$application = JFactory::getApplication();
		$application->enqueueMessage(JText::_('VMPAYMENT_MISTERTANGO_MSG_CONFIRMED_ORDER'), 'Warning');
		$application->redirect('index.php?option=com_mistertango&view=payment');

		return true;
	}

	/**
	 * @param $method
	 *
	 * @return string
	 */
	function getNewStatus($method)
	{
		if (isset($method->status_pending) and $method->status_pending != "")
		{
			return $method->status_pending;
		}
		else
		{
			return 'P';
		}
	}

	/**
	 * @param $virtuemart_order_id
	 * @param $html
	 */
	public function _handlePaymentCancel($virtuemart_order_id, $html)
	{
		if (!class_exists('VirtueMartModelOrders'))
		{
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}

		$mainframe = JFactory::getApplication();
		$mainframe->enqueueMessage($html);
		$mainframe->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart&task=editpayment', false), vmText::_('COM_VIRTUEMART_CART_ORDERDONE_DATA_NOT_VALID'));
	}

	/**
	 * @param $virtuemart_paymentmethod_id
	 * @param $paymentCurrencyId
	 *
	 * @return bool|null
	 */
	public function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
	{
		$currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id);
		if (!$currentMethod)
		{
			return null;
		}

		if (!$this->selectedThisElement($currentMethod->payment_element))
		{
			return false;
		}

		$currentMethod->payment_currency = self::Mistertango_DEFAULT_PAYMENT_CURRENCY;

		if (!class_exists('VirtueMartModelVendor'))
		{
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'vendor.php');
		}

		$db = JFactory::getDBO();

		$q = 'SELECT   `virtuemart_currency_id` FROM `#__virtuemart_currencies` WHERE `currency_code_3`= "' . self::Mistertango_DEFAULT_PAYMENT_CURRENCY . '"';
		$db->setQuery($q);
		$paymentCurrencyId = $db->loadResult();

		return null;
	}

	/**
	 * @return array
	 */
	protected function _setHeader()
	{
		return $this->_Mistertangonet_params;
	}

	/**
	 * @param $string
	 * @param $length
	 *
	 * @return bool|string
	 */
	protected function _getField($string, $length)
	{
		if (!class_exists('shopFunctionsF'))
		{
			require(VMPATH_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
		}

		return ShopFunctionsF::vmSubstr($string, 0, $length);
	}

	/**
	 * @param $usrBT
	 *
	 * @return array
	 */
	protected function _setBillingInformation($usrBT)
	{
		if (!class_exists('ShopFunctions'))
			require(VMPATH_ADMIN . DS . 'helpers' . DS . 'shopfunctions.php');
		$clientIp = ShopFunctions::getClientIP();

		// Customer Name and Billing Address
		return array(
			'x_email'       => isset($usrBT->email) ? $this->_getField($usrBT->email, 100) : '', //get email
			'x_first_name'  => isset($usrBT->first_name) ? $this->_getField($usrBT->first_name, 50) : '',
			'x_last_name'   => isset($usrBT->last_name) ? $this->_getField($usrBT->last_name, 50) : '',
			'x_company'     => isset($usrBT->company) ? $this->_getField($usrBT->company, 50) : '',
			'x_address'     => isset($usrBT->address_1) ? $this->_getField($usrBT->address_1, 60) : '',
			'x_city'        => isset($usrBT->city) ? $this->_getField($usrBT->city, 40) : '',
			'x_zip'         => isset($usrBT->zip) ? $this->_getField($usrBT->zip, 20) : '',
			'x_state'       => isset($usrBT->virtuemart_state_id) ? $this->_getField(ShopFunctions::getStateByID($usrBT->virtuemart_state_id), 40) : '',
			'x_country'     => isset($usrBT->virtuemart_country_id) ? $this->_getField(ShopFunctions::getCountryByID($usrBT->virtuemart_country_id), 60) : '',
			'x_phone'       => isset($usrBT->phone_1) ? $this->_getField($usrBT->phone_1, 25) : '',
			'x_fax'         => isset($usrBT->fax) ? $this->_getField($usrBT->fax, 25) : '',
			'x_customer_ip' => $clientIp,
		);
	}

	/**
	 * @param $usrST
	 *
	 * @return array
	 */
	protected function _setShippingInformation($usrST)
	{
		return array(
			'x_ship_to_first_name' => isset($usrST->first_name) ? $this->_getField($usrST->first_name, 50) : '',
			'x_ship_to_last_name'  => isset($usrST->first_name) ? $this->_getField($usrST->last_name, 50) : '',
			'x_ship_to_company'    => isset($usrST->company) ? $this->_getField($usrST->company, 50) : '',
			'x_ship_to_address'    => isset($usrST->first_name) ? $this->_getField($usrST->address_1, 60) : '',
			'x_ship_to_city'       => isset($usrST->city) ? $this->_getField($usrST->city, 40) : '',
			'x_ship_to_zip'        => isset($usrST->zip) ? $this->_getField($usrST->zip, 20) : '',
			'x_ship_to_state'      => isset($usrST->virtuemart_state_id) ? $this->_getField(ShopFunctions::getStateByID($usrST->virtuemart_state_id), 40) : '',
			'x_ship_to_country'    => isset($usrST->virtuemart_country_id) ? $this->_getField(ShopFunctions::getCountryByID($usrST->virtuemart_country_id), 60) : '',
		);
	}

	/**
	 * @param VirtueMartCart $cart
	 * @param array          $cart_prices
	 * @param                $paymentCounter
	 *
	 * @return int|null
	 */
	public function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter)
	{

		$return = $this->onCheckAutomaticSelected($cart, $cart_prices);
		if (isset($return))
		{
			return 0;
		}
		else
		{
			return null;
		}
	}

	/**
	 * @param $virtuemart_order_id
	 * @param $virtuemart_paymentmethod_id
	 * @param $payment_name
	 *
	 * @return bool
	 */
	protected function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
	{
		$this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);

		return true;
	}

	/**
	 * @param $order_number
	 * @param $method_id
	 *
	 * @return mixed
	 */
	function plgVmOnShowOrderPrintPayment($order_number, $method_id)
	{
		return parent::onShowOrderPrint($order_number, $method_id);
	}

	/**
	 * @param $data
	 *
	 * @return bool
	 */
	function plgVmDeclarePluginParamsPaymentVM3(&$data)
	{
		return $this->declarePluginParams('payment', $data);
	}

	/**
	 * @param $name
	 * @param $id
	 * @param $table
	 *
	 * @return bool
	 */
	function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
	{
		return $this->setOnTablePluginParams($name, $id, $table);
	}
}
