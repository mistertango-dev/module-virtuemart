<?php

define('DS', DIRECTORY_SEPARATOR);

define('_JEXEC', 1);
define('JPATH_BASE', dirname(dirname(dirname(dirname(__FILE__)))));

require_once(JPATH_BASE . DS . 'includes' . DS . 'defines.php');
require_once(JPATH_BASE . DS . 'includes' . DS . 'framework.php');
$userid    = '';
$usertype  = '';
$mainframe =& JFactory::getApplication('site');
$mainframe->initialise();

$user     =& JFactory::getUser();
$userid   = $user->get('id');
$usertype = $user->get('usertype');

$aCallbackData = $_POST;

$db    = JFactory::getDbo();
$query = $db->getQuery(true);
$query->select('*');
$query->from($db->quoteName('#__virtuemart_paymentmethods'));
$query->where($db->quoteName('payment_element') . ' LIKE ' . $db->quote('%mistertango%'));
$db->setQuery($query);
$res = $db->loadObject();

parse_str(str_replace(array('|', '"'), array('&', ''), $res->payment_params), $aConfigParams);

if (empty($aConfigParams) || !$aConfigParams['secret'])
	die('SecretKey not provided');

$aConfigParams['secret'] = trim($aConfigParams['secret']);

$aCallbackHeader = @json_decode(decrypt($aCallbackData['hash'], $aConfigParams['secret']), true);

if (empty($aCallbackHeader))
{
	die('Hash empty. Please check secret key.');
}

$aCallbackBody = @json_decode($aCallbackHeader['custom'], true);

if (empty($aCallbackBody))
{
	die('Callback body not found');
}

if (!empty($aCallbackBody))
{
	//TODO: find your order $aCallbackBody['description']
	//TODO: check amount $aCallbackBody['data']['amount']
	//TODO: change your order status

	$oidd = trim($aCallbackBody['description']);

	$amt = (float) trim($aCallbackBody['data']['amount']);

	$db    = JFactory::getDbo();
	$query = $db->getQuery(true);
	$query->select('*');
	$query->from($db->quoteName('#__virtuemart_orders'));
	$query->where($db->quoteName('order_number') . ' = ' . $db->quote($oidd));
	$db->setQuery($query);
	$res = $db->loadObject();

	$order_total = (float) $res->order_total;
	$amt         = (float) trim($aCallbackBody['data']['amount']);

	if ($amt < $order_total)
	{
		die('Amount too small');
	}

	if (isset($res->order_status) && in_array($res->order_status, array('P', 'C')))
	{
		$order = array();
		$order['order_status'] = 'U';
		$order['customer_notified'] = 1;

		$modelOrder = VmModel::getModel ('orders');
		$modelOrder->updateStatusForOneOrder ($res->virtuemart_order_id, $order, TRUE);
	}

	die('OK');
}

/**
 * @param $encoded_text
 * @param $key
 *
 * @return string
 */
function decrypt($encoded_text, $key)
{
	$key = str_pad($key, 32, "\0");

	$encoded_text   = trim($encoded_text);
	$ciphertext_dec = base64_decode($encoded_text);

	$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);

	# retrieves the IV, iv_size should be created using mcrypt_get_iv_size()
	$iv_dec = substr($ciphertext_dec, 0, $iv_size);

	# retrieves the cipher text (everything except the $iv_size in the front)
	$ciphertext_dec = substr($ciphertext_dec, $iv_size);

	# may remove 00h valued characters from end of plain text
	$sResult = @mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $ciphertext_dec, MCRYPT_MODE_CBC, $iv_dec);

	return trim($sResult);
}
