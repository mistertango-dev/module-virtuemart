<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_search
 *
 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Class MistertangoController
 */
class MistertangoController extends JControllerLegacy
{
	/**
	 * @param bool $cachable
	 * @param bool $urlparams
	 *
	 * @return JControllerLegacy
	 */
	public function display($cachable = false, $urlparams = false)
	{
		$app = JFactory::getApplication();
		$app->input->set('view', 'payment');

		return parent::display($cachable, $urlparams);
	}

	/**
	 *
	 */
	public function paymentresponse()
	{
		$orderid             = trim($_POST['orderid']);
		$amount              = trim($_POST['amount']);
		$status              = trim($_POST['status']);
		$currency            = trim($_POST['currency']);
		$custom_payment_type = trim($_POST['custom_payment_type']);
		$hash                = trim($_POST['hash']);
		$invoice             = trim($_POST['invoice']);
		$Payment_type        = trim($_POST['Payment_type']);
		$payament_status     = trim($_POST['payament_status']);
		$ws_id               = trim($_POST['ws_id']);
		$type                = trim($_POST['type']);

		$db    = JFactory::getDbo();
		$query = "INSERT INTO `#__mistertengo_payment` (`orderid`, `amount`, `status`, `currency`, `invoice`, `hash`, `payament_status`, `Payment_type`, `ws_id`, `type`) VALUES ('$orderid', '$amount', '$status', '$currency', '$invoice', '$hash', '$payament_status', '$Payment_type ', '$ws_id', '$type');";
		$db->setQuery($query);
		$db->loadObject();
	}

	/**
	 *
	 */
	public function getpaymentresponse()
	{
		$orderid = trim($_POST['orderid']);

		$db    = JFactory::getDbo();
		$query = "select * from #__mistertengo_payment where orderid ='$orderid'";
		$db->setQuery($query);
		$res = $db->loadObject();

		echo $res->status;
		exit;
	}
}
