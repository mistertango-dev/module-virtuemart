<?php

defined('_JEXEC') or die;

JHtml::script('https://payment.mistertango.com/resources/scripts/mt.collect.js?v=01', true);

error_reporting(0);
$user        = JFactory::getUser();
$session     =& JFactory::getSession();
$username    = $session->get('username');
$secret      = $session->get('secret');
$callbackUrl = $session->get('callback_url');
$order       = $session->get('order');
$email       = $session->get('email');
$amount      = $session->get('amount');

$lang         = JFactory::getLanguage();
$languages    = JLanguageHelper::getLanguages('lang_code');
$languageCode = $languages[$lang->getTag()]->sef;

function encrypt($plain_text, $key)
{
	$key = str_pad($key, 32, "\0");

	$plain_text = trim($plain_text);
	# create a random IV to use with CBC encoding
	$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
	$iv      = mcrypt_create_iv($iv_size, MCRYPT_RAND);

	# creates a cipher text compatible with AES (Rijndael block size = 128)
	# to keep the text confidential
	# only suitable for encoded input that never ends with value 00h (because of default zero padding)
	$ciphertext = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key,
		$plain_text, MCRYPT_MODE_CBC, $iv);

	# prepend the IV for it to be available for decryption
	$ciphertext = $iv . $ciphertext;

	# encode the resulting cipher text so it can be represented by a string
	$sResult = base64_encode($ciphertext);

	return trim($sResult);
}
?>

<script type="text/javascript">
    var paid;
    document.addEventListener(
        "DOMContentLoaded",
        function () {
            mrTangoCollect.load();
        },
        false
    );
    mrTangoCollect.set.recipient("<?php echo $username; ?>");
    mrTangoCollect.set.lang("<?php echo $languageCode; ?>");
    mrTangoCollect.set.payer("<?php echo $email; ?>");
	<?php if($callbackUrl) { ?>
    mrTangoCollect.custom = {'callback': '<?php echo encrypt($callbackUrl, $secret); ?>'};
	<?php } ?>
	<?php if($email && $username && $order && $amount) { ?>
    mrTangoCollect.submit("<?php echo $amount; ?>", "EUR", "<?php echo $order; ?>");
	<?php } ?>

    mrTangoCollect.onSuccess = function (response) {
        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function () {
            if (xhttp.readyState == 4 && xhttp.status == 200) {
                //var response = JSON.parse(xhttp.responseText);


                ////MTPayment.order = response.order;
                //MTPayment.success = true;

                //MTPayment.afterSuccess();
            }
        };

        xhttp.open('POST', 'index.php?option=com_mistertango&task=paymentresponse&tmpl=component', true);
        xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
        xhttp.send(
            '&orderid=' + response.order.description +
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

    mrTangoCollect.onClosed = function (response) {
        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function () {
            if (xhttp.readyState == 4 && xhttp.status == 200) {
                if (xhttp.responseText == 'PAID') {
                    window.location.href = "index.php?option=com_virtuemart&view=cart&layout=order_done";
                }
            }
        };
        xhttp.open('POST', 'index.php?option=com_mistertango&task=getpaymentresponse&tmpl=component', true);
        xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
        xhttp.send(
            '&orderid=<?php echo $order; ?>'
        );
    };

    function submit(amount, currency, order) {
        mrTangoCollect.submit(amount, currency, order);

        return false;
    }
</script>
<h3><?php echo JText::_('COM_MISTERTANGO_TITLE'); ?></h3>
<p>
	<?php echo JText::_('COM_MISTERTANGO_TEXT'); ?><a href="javascript:void(0);"
                                                      onclick="submit('<?php echo $amount; ?>', 'EUR', '<?php echo $order; ?>');"
                                                      id="pay-button"><?php echo JText::_('COM_MISTERTANGO_BTN_TITLE'); ?></a>.
</p>
