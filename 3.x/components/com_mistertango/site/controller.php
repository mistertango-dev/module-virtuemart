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
 * Search Component Controller
 *
 * @package     Joomla.Site
 * @subpackage  com_search
 * @since       1.5
 */
class MistertangoController extends JControllerLegacy
{
	/**
	 * Method to display a view.
	 *
	 * @param   boolean			If true, the view output will be cached
	 * @param   array  An array of safe url parameters and their variable types, for valid values see {@link JFilterInput::clean()}.
	 *
	 * @return  JController		This object to support chaining.
	 * @since   1.5
	 */
	public function display($cachable = false, $urlparams = false)
	{
	
	    $app     = JFactory::getApplication();
		$app->input->set('view', 'payment'); // force it to be the search view

		return parent::display($cachable, $urlparams);
	}
	
	
	
	public function paymentresponse()
	{
	    //print_r($_POST);
	
	   $orderid = trim($_POST['orderid']);
	    $amount = trim($_POST['amount']);
		 $status = trim($_POST['status']);
		  $currency = trim($_POST['currency']);
		   $custom_payment_type = trim($_POST['custom_payment_type']);
		    $hash = trim($_POST['hash']);
			 $invoice = trim($_POST['invoice']);
			  $Payment_type = trim($_POST['Payment_type']);
			   $payament_status = trim($_POST['payament_status']);
			    $ws_id = trim($_POST['ws_id']);
				 $type = trim($_POST['type']);
				  
		
			$db = JFactory::getDbo();
			$query = "INSERT INTO `#__mistertengo_payment` (`orderid`, `amount`, `status`, `currency`, `invoice`, `hash`, `payament_status`, `Payment_type`, `ws_id`, `type`) VALUES ('$orderid', '$amount', '$status', '$currency', '$invoice', '$hash', '$payament_status', '$Payment_type ', '$ws_id', '$type');" ;
			$db->setQuery($query);
			$db->loadObject();
			
			
			/* update the order status */
			
			
			/*
			
				$db = JFactory::getDbo();
				$query = "Select * from  #__virtuemart_orders where order_number = '$orderid'" ;
				$db->setQuery($query);
				$res = $db->loadObject();

				$oid = $res->virtuemart_order_id;
				
				
				if($res->order_status != 'C'){
				
				
				
			
				$db = JFactory::getDbo();
				$query = "update #__virtuemart_orders set order_status = 'C' where order_number = '$orderid'" ;
				$db->setQuery($query);
				$db->loadObject();


				$db = JFactory::getDbo();
				$query = "update #__virtuemart_order_items  set order_status = 'C' where virtuemart_order_id = '$oid'" ;
				$db->setQuery($query);
				$db->loadObject();
				
				
				}
			*/
			
	}
	public function getpaymentresponse()
	{
	       $orderid = trim($_POST['orderid']);
	
	        $db = JFactory::getDbo();
			$query = "select * from #__mistertengo_payment where orderid ='$orderid'" ;
			$db->setQuery($query);
			$res = $db->loadObject();
			
			
	  echo  $res->status;
	  exit;
	}
	
	

}
