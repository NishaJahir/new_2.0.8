<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the GNU General Public License
 *
 * @author Novalnet AG <technic@novalnet.de>
 * @copyright Novalnet
 * @license GNU General Public License
 *
 * Script : NovalnetOrderConfirmationDataProvider.php
 *
 */

namespace Novalnet\Providers;

use Plenty\Plugin\Templates\Twig;

use Novalnet\Helper\PaymentHelper;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Comment\Contracts\CommentRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use \Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Novalnet\Services\PaymentService;
/**
 * Class NovalnetOrderConfirmationDataProvider
 *
 * @package Novalnet\Providers
 */
class NovalnetOrderConfirmationDataProvider
{
	/**
	 * Setup the Novalnet transaction comments for the requested order
	 *
	 * @param Twig $twig
	 * @param PaymentRepositoryContract $paymentRepositoryContract
	 * @param Arguments $arg
	 * @return string
	 */
	public function call(Twig $twig, PaymentRepositoryContract $paymentRepositoryContract, $arg)
	{
		$paymentHelper = pluginApp(PaymentHelper::class);
		$paymentService = pluginApp(PaymentService::class);
		$sessionStorage = pluginApp(FrontendSessionStorageFactoryContract::class);
		$order = $arg[0];
		$barzhlentoken = '';
		$barzahlenurl = '';
		$payments = $paymentRepositoryContract->getPaymentsByOrderId($order['id']);
		if (!empty ($order['id'])) {
			foreach($payments as $payment)
			{
				$properties = $payment->properties;
				foreach($properties as $property)
				{
				if ($property->typeId == 21) 
				{
				$invoiceDetails = $property->value;
				}
				if ($property->typeId == 30)
				{
				$tid_status = $property->value;
				}
				if ($property->typeId == 22)
				{
				$cashpayment_comments = $property->value;
				}
				}
				if($paymentHelper->getPaymentKeyByMop($payment->mopId))
				{
					if ($payment->method['paymentKey'] == 'NOVALNET_CASHPAYMENT')
					{
						$barzhlentoken = html_entity_decode((string)$sessionStorage->getPlugin()->getValue('novalnet_checkout_token'));
						$barzahlenurl = html_entity_decode((string)$sessionStorage->getPlugin()->getValue('novalnet_checkout_url'));
					}
					$orderId = (int) $payment->order['orderId'];
					$comment = '';
					$db_details = $paymentService->getDatabaseValues($orderId);
					$comments = '';
					$comments .= PHP_EOL . $paymentHelper->getTranslatedText('nn_tid') . $db_details['tid'];
					if(!empty($db_details['test_mode'])) {
						$comments .= PHP_EOL . $paymentHelper->getTranslatedText('test_order');
					}
					$bank_details = array_merge($db_details, json_decode($invoiceDetails, true));
					if (in_array($bank_details['paymentName'], ['novalnet_invoice', 'novalnet_prepayment'])) {
						$comments .= PHP_EOL . $paymentService->getInvoicePrepaymentComments($bank_details);
					}
					if(in_array($db_details['payment_id'], ['40','41'])) {
						$comments .= PHP_EOL . $paymentHelper->getTranslatedText('guarantee_text');
						if($tid_status == '75' && $db_details['payment_id'] == '41')
						{
							$comments .= PHP_EOL . $paymentHelper->getTranslatedText('gurantee_invoice_pending_payment_text');
						}
						if( $tid_status == '75' && $db_details['payment_id'] == '40')
						{
							$comments .= PHP_EOL . $paymentHelper->getTranslatedText('gurantee_sepa_pending_payment_text');
						}
					}
					if($db_details['payment_id'] == '59' ) {
						$comments .= $cashpayment_comments;
					}
				}
			}
					$comment .= (string) $comments;
					$comment .= PHP_EOL;
		}	
				  $payment_type = (string)$paymentHelper->getPaymentKeyByMop($payment->mopId);
				  return $twig->render('Novalnet::NovalnetOrderHistory', ['comments' => html_entity_decode($comment),'barzahlentoken' => $barzhlentoken,'payment_type' => html_entity_decode($payment_type),'barzahlenurl' => $barzahlenurl]);
	}
}

	

