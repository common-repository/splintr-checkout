<?php
/**
 * Plugin Name: Splintr Checkout
 * Description: Plugin for checking out using Splintr payment method
 * Author:      developers@splintr.com
 * Author URI:  https://www.splintr.com/
 * Version:     0.2.3
 * Text Domain: splintr-checkout
 */

use Splintr\Wp\Plugin\SplintrCheckout\Helpers\UrlHelper;
use Splintr\Wp\Plugin\SplintrCheckout\Services\CallbackService;
use Splintr\Wp\Plugin\SplintrCheckout\Services\ViewService;
use Splintr\Wp\Plugin\SplintrCheckout\Services\WcSplintrPaymentGateway;
use Splintr\Wp\Plugin\SplintrCheckout\SplintrCheckout;

defined('SPLINTR_CHECKOUT_VERSION') || define('SPLINTR_CHECKOUT_VERSION', '0.2.3');

// Use autoload if it isn't loaded before
// phpcs:ignore PSR2.ControlStructures.ControlStructureSpacing.SpacingAfterOpenBrace
if (!class_exists(SplintrCheckout::class)) {
    require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
}

add_action('woocommerce_init', function () {
    woo_splintr_checkout_initialize_instance();
});

/**
 * @return void
 * @throws Exception
 */
function woo_splintr_checkout_initialize_instance()
{
    if (empty($GLOBALS['woo_splintr_checkout']) || !($GLOBALS['woo_splintr_checkout'] instanceof SplintrCheckout)) {
        $GLOBALS['woo_splintr_checkout'] = new SplintrCheckout(
            UrlHelper::removeTrailingSlashes(plugin_dir_path(__FILE__)),
            plugins_url(null, __FILE__),
            dirname(__DIR__),
            SPLINTR_CHECKOUT_VERSION,
            'splintr-checkout',
        );
        $GLOBALS['woo_splintr_checkout']->initPlugin();
    }
}

/**
 * @return SplintrCheckout
 */
function woo_splintr_checkout_instance()
{
    return (!empty($GLOBALS['woo_splintr_checkout'])) ? $GLOBALS['woo_splintr_checkout'] : null;
}

/**
 * @return void
 * @throws Exception
 */
function woo_splintr_checkout_initialize_wc_splintr_payment_gateway()
{
    $splintrCheckout = woo_splintr_checkout_instance();
    if (empty($splintrCheckout)) {
        throw new \Exception('Splintr Checkout Not Initialized');
    }
    if (empty($GLOBALS['woo_splintr_checkout_wc_splintr_payment_gateway']) || !($GLOBALS['woo_splintr_checkout_wc_splintr_payment_gateway'] instanceof WcSplintrPaymentGateway)) {
        $GLOBALS['woo_splintr_checkout_wc_splintr_payment_gateway'] = new WcSplintrPaymentGateway();
    }
}

/**
 * @return WcSplintrPaymentGateway
 */
function woo_splintr_checkout_wc_splintr_gateway_instance()
{
    return !empty($GLOBALS['woo_splintr_checkout_wc_splintr_payment_gateway']) ? $GLOBALS['woo_splintr_checkout_wc_splintr_payment_gateway'] : null;
}

/**
 * @return void
 * @throws Exception
 */
function woo_splintr_checkout_initialize_callback_service()
{
    $splintrCheckout = woo_splintr_checkout_instance();
    if (empty($splintrCheckout)) {
        throw new \Exception('Splintr Checkout Not Initialized');
    }
    if (empty($GLOBALS['woo_splintr_checkout_callback_service']) || !($GLOBALS['woo_splintr_checkout_callback_service'] instanceof CallbackService)) {
        $GLOBALS['woo_splintr_checkout_callback_service'] = new CallbackService();
    }
}

/**
 * @return CallbackService
 */
function woo_splintr_checkout_callback_service_instance()
{
    return !empty($GLOBALS['woo_splintr_checkout_callback_service']) ? $GLOBALS['woo_splintr_checkout_callback_service'] : null;
}

/**
 * @return void
 * @throws Exception
 */
function woo_splintr_checkout_initialize_view_service()
{
    $splintrCheckout = woo_splintr_checkout_instance();
    if (empty($splintrCheckout)) {
        throw new \Exception('Splintr Checkout Not Initialized');
    }
    if (empty($GLOBALS['woo_splintr_checkout_view_service']) || !($GLOBALS['woo_splintr_checkout_view_service'] instanceof ViewService)) {
        $GLOBALS['woo_splintr_checkout_view_service'] = new ViewService(
            $splintrCheckout->getBasePath() . DIRECTORY_SEPARATOR . 'views',
            $splintrCheckout->getBaseUrl() . DIRECTORY_SEPARATOR . 'views'
        );
    }
}

/**
 * @return ViewService
 */
function woo_splintr_checkout_view_service_instance()
{
    return !empty($GLOBALS['woo_splintr_checkout_view_service']) ? $GLOBALS['woo_splintr_checkout_view_service'] : null;
}
