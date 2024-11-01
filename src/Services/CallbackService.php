<?php


namespace Splintr\Wp\Plugin\SplintrCheckout\Services;


class CallbackService
{
    public $client;

    public function __construct()
    {
        $this->init();
    }

    /**
     * Prepare Splintr Client instance
     */
    public function init()
    {
//        $this->client = (!empty(woo_splintr_checkout_wc_splintr_gateway_instance()->getClient()) ? woo_splintr_checkout_wc_splintr_gateway_instance()->getClient() : null);
    }

    /**
     * Handle Splintr Callback Request
     */
    public function handleCallbackRequest()
    {
//        $wcOrderId = $this->client->getReferenceIdFromCallbackPayload();
//        $orderId = $this->client->getOrderIdFromCallbackPayload();
//        $this->verifyOrder($wcOrderId, $orderId);
        exit;
    }

    /**
     * Verify Splintr order from remote
     *
     * @param $wcOrderId
     * @param $splintrOrderId
     *
     * @return bool
     */
    public function verifyOrder($wcOrderId, $splintrOrderId)
    {
//        $wcOrder = wc_get_order($wcOrderId);
//        if ($wcOrder) {
//	        try {
//		        $viewOrderRequest  = $this->client->generateViewOrderRequest($splintrOrderId);
//		        $viewOrderResponse = $this->client->viewOrder($viewOrderRequest);
//	        } catch (\Exception $viewOrderResponseException) {
//		        woo_splintr_checkout_instance()->logMessage(sprintf("Splintr Service timeout or disconnected.\nError message: '%s'.\nTrace: %s",
//			        $viewOrderResponseException->getMessage(),
//			        $viewOrderResponseException->getTraceAsString()));
//	        }
//            if (!empty($viewOrderResponse) && $viewOrderResponse->isSuccess()) {
//                update_post_meta($wcOrderId, 'payment_method', $wcOrder->get_payment_method());
//                update_post_meta($wcOrderId, '_splintr_order_id', $splintrOrderId);
//                $newOrderStatus = 'wc-processing';
//                woo_splintr_checkout_instance()->updateOrderStatusAndAddOrderNote(
//                    $wcOrder, $newOrderStatus);
//                return true;
//            } else {
//                if ('failed' !== $wcOrder->get_status()) {
//                    $newOrderStatus = 'wc-failed';
//                    woo_splintr_checkout_instance()->updateOrderStatusAndAddOrderNote($wcOrder, $newOrderStatus);
//                    return false;
//                }
//            }
//        }
//        woo_splintr_checkout_instance()->logMessage('Splintr - Verify failed: Order not found');
        return false;
    }
}