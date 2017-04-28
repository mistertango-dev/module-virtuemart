<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_search
 *
 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

JHtml::script('https://payment.mistertango.com/resources/scripts/mt.collect.js?v=01', true);	

error_reporting(0);	
$user = JFactory::getUser();	
$session =& JFactory::getSession();
$username = $session->get('username');
$order = $session->get('order');
$amount = $session->get('amount');
$email = $session->get('email');

$lang = JFactory::getLanguage();
$languages = JLanguageHelper::getLanguages('lang_code');
$languageCode = $languages[ $lang->getTag() ]->sef;



?>

		
<script type="text/javascript">

var paid;
document.addEventListener("DOMContentLoaded", function() {
mrTangoCollect.load();}, false);
mrTangoCollect.set.recipient("<?php echo $username; ?>");
mrTangoCollect.set.lang("<?php echo $languageCode; ?>");
mrTangoCollect.set.payer("<?php echo $email; ?>");

<?php if($email && $username && $order && $amount) { ?>
mrTangoCollect.submit("<?php echo $amount; ?>", "EUR", "<?php echo $order; ?>");
<?php } ?>
mrTangoCollect.onSuccess = function(response){
     //alert('Success response');
     //console.log(response);
	  //console.log(response.status);

	
	
	        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (xhttp.readyState == 4 && xhttp.status == 200) {
                //var response = JSON.parse(xhttp.responseText);

               

                    ////MTPayment.order = response.order;
                    //MTPayment.success = true;

                    //MTPayment.afterSuccess();
                }
            }
       
		
	
        xhttp.open('POST', 'index.php?option=com_mistertango&task=paymentresponse&tmpl=component', true);
        xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
        xhttp.send(
            '&orderid=' + response.order.description+
			'&amount=' + response.order.amount +
			'&status=' + response.status +
			'&currency=' + response.payment.currency +
			'&custom_payment_type=' + response.payment.type +
			'&hash=' + response.hash +
            '&invoice=' + response.order.invoice +
            '&Payment_type=' + response.payment.type +
			'&type=' + response.type +
            '&ws_id=' + response.order.ws_id +
            '&payament_status=' + response.order.status
        );

	
};
mrTangoCollect.onClosed = function(response){
    //alert('Close');
    //console.log(response);
	//window.location.href="index.php?option=com_virtuemart&view=cart&layout=order_done";
	
	
		
	        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (xhttp.readyState == 4 && xhttp.status == 200) {
                //var response = JSON.parse(xhttp.responseText);

               

                    ////MTPayment.order = response.order;
                    //MTPayment.success = true;

                    //MTPayment.afterSuccess();
					
					//console.log(xhttp.responseText);
					if(xhttp.responseText == 'PAID')
window.location.href="index.php?option=com_virtuemart&view=cart&layout=order_done";

                }
            }
       
		
	
        xhttp.open('POST', 'index.php?option=com_mistertango&task=getpaymentresponse&tmpl=component', true);
        xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
        xhttp.send(
            '&orderid=<?php echo $order; ?>' 
        );


};


</script>
<h3><?php echo JText::_('COM_MISTERTANGO_TITLE'); ?></h3>

<p><?php echo JText::_('COM_MISTERTANGO_TEXT'); ?><a href="#" onclick='mrTangoCollect.submit("<?php echo $amount; ?>", "EUR", "<?php echo $order; ?>");' id="pay-button"><?php echo JText::_('COM_MISTERTANGO_BTN_TITLE'); ?></a>.</p>

<style>
.warning{display:none;}
</style>
