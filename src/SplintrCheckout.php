<?php

namespace Splintr\Wp\Plugin\SplintrCheckout;

use Exception;
use Splintr\PhpSdk\Traits\ConfigTrait;
use Splintr\Wp\Plugin\SplintrCheckout\Helpers\MoneyHelper;
use Splintr\Wp\Plugin\SplintrCheckout\Services\WcSplintrPaymentGateway;
use WC_Order;
use WooCommerce;

class SplintrCheckout
{
    const SPLINTR_PAYMENT_GATEWAY_ID = 'splint-payment-gateway',
          SPLINTR_ORDER_STATUS_INTEGER_PROCESSING = 4;

    protected $pluginFilename;
    protected $basePath;
    protected $baseUrl;
    protected $version;

    /**
     * SplintrCheckout constructor.
     * @param string $basePath
     * @param string $baseUrl
     * @param string $pluginFilename
     * @param string $version
     * @param string $textDomain
     */
    public function __construct($basePath, $baseUrl, $pluginFilename, $version, $textDomain)
    {
        $this->basePath = $basePath;
        $this->baseUrl = $baseUrl;
        $this->pluginFilename = $pluginFilename;
        $this->version = $version;
        $this->textDomain = $textDomain;
    }

    /**
     * @throws Exception
     */
    public function initPlugin()
    {
        add_action('init', [$this, 'checkWooCommerceExistence']);
        if (!class_exists(WooCommerce::class)) {
            return;
        }

        // Splintr payment type services init
        woo_splintr_checkout_initialize_wc_splintr_payment_gateway();
        woo_splintr_checkout_initialize_callback_service();
        woo_splintr_checkout_initialize_view_service();

        // Load text domain
        add_action('init', [$this, 'loadTextDomain']);

        // Handle Splintr custom requests
        add_action('parse_request', [$this, 'handleCustomRequests'], 1000);

        // Enqueue Splintr scripts on FE and Admin
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminSettingScripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);

        // Add rewrite rules for Splintr pretty merchant urls
        add_action('init', [$this, 'addCustomRewriteRules']);

        // Update options to database on settings saved
        add_action('woocommerce_update_options_checkout_' . static::SPLINTR_PAYMENT_GATEWAY_ID, [$this, 'onSaveSettings'], 10, 1);

        // Register and adjust payment types on checkout
        add_filter('woocommerce_payment_gateways', [$this, 'registerPaymentGateways']);

        // Add Splintr order failed massage on Cart page
        add_action('init', [$this, 'addFailedMessageOnCartPage'], 1000);

        // Ajax calls on redirect url returned
        add_action('wp_ajax_splintr-verify', [$this, 'handleRedirectSuccess']);
        add_action('wp_ajax_nopriv_splintr-verify', [$this, 'handleRedirectSuccess']);
        add_action('wp_head', [$this, 'checkoutParams']);

        // Handle refund when a refund is created
        add_action('woocommerce_create_refund', [$this, 'refundPayment'], 10, 2);

        // Add note on Refund
        add_action('woocommerce_order_item_add_action_buttons', [$this, 'addRefundNote']);

        // TODO: We should extract this outside to another service.
        add_action('rest_api_init', [$this, 'registerApiRoutes']);
    }

    /**
     * // TODO: We should extract this method outside to another service.
     * registerApiRoutes is reponsible for registering our custom endpoints that may be used by our internal tools.
     */
    public function registerApiRoutes() {
        register_rest_route('splintr/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'webhook')
        ));
    }

    // TODO: We should extract this method outside to another service.
    public function webhook($request) {
        $event = sanitize_text_field($request["event"]);

        if (!$event) {
            wp_send_json_error([
                "message" => "Undefined event. Have you provided an event type?"
            ], 400);
        }

        if ($event === "order:status_change") {
            $this->handleOrderStatusChange($request);
        }

        wp_send_json_error([
            "message" => sprintf("Unsupported event type '%s'.", $event)
        ], 400);
    }

    // TODO: We should extract this method outside to another service.
    private function handleOrderStatusChange($request) {
        $status = sanitize_text_field($request["status"]);

        $order = wc_get_order($request["reference"]);

        if (!$order) {
            wp_send_json_error(array(
                "message" => sprintf("Order with reference number '%s' was not found.", $reference)
            ), 400);
        }

        if ($request["status"] !== "captured") {
            wp_send_json_error([
                "message" => sprintf("Expected 'captured' status, '%s' was received.", $status)
            ], 400);
        }

        if ($order->has_status("wc-processing")) {
            wp_send_json_error([
                "message" => sprintf("Order '%s' has been previously completed. No further actions were taken.", $reference)
            ], 400);
        }

        if ($status === 'captured') {
            $order->update_status('wc-processing');
        }

        wp_send_json(array(), 204);
    }

    /**
     * Run this method under the "init" action
     */
    public function checkWooCommerceExistence()
    {
        if (class_exists(WooCommerce::class)) {
            // Add "Settings" link when the plugin is active
            add_filter('plugin_action_links_woo-splintr-checkout/woo-splintr-checkout.php', [$this, 'addSettingsLinks']);
        } else {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
            // Throw a notice if WooCommerce is NOT active
            deactivate_plugins($this->pluginFilename);
            add_action('admin_notices', [$this, 'noticeNonWooCommerce']);
        }
    }

    /**
     * Throw a notice if WooCommerce is NOT active
     */
    public function noticeNonWooCommerce()
    {
        $class = 'notice notice-warning';

        $message = sprintf(
            __('Plugin `%s` not working because WooCommerce is not active. Please activate WooCommerce first.', 'splintr-checkout'), 'Splintr Checkout');

        printf('<div class="%1$s"><p><strong>%2$s</strong></p></div>', $class, $message);
    }

    /**
     * Localize the plugin
     */
    public function loadTextDomain()
    {
        $locale = determine_locale();
        $mofile = $locale . '.mo';
        load_textdomain('splintr-checkout', $this->basePath . '/languages/' . 'splintr-checkout' . '-' . $mofile);
    }

    /**
     * @return mixed
     */
    public function getBasePath()
    {
        return $this->basePath;
    }

    /**
     * @return mixed
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * @param $settings
     *
     * @return void
     */
    public function onSaveSettings($settings)
    {
        woo_splintr_checkout_wc_splintr_gateway_instance()->onSaveSettings($settings);
    }

    /**
     * Add more links to plugin settings
     *
     * @param $pluginLinks
     *
     * @return array
     */
    public function addSettingsLinks($pluginLinks)
    {
        $pluginLinks[] = '<a href="' . $this->getAdminSettingLink() . '">' . esc_html__('Settings',
                'splintr-checkout') . '</a>';

        return $pluginLinks;
    }

    /**
     * Add relevant links to plugins page
     */
    public function getAdminSettingLink()
    {
        if (version_compare(WC()->version, '2.6', '>=')) {
            $sectionSlug = woo_splintr_checkout_wc_splintr_gateway_instance()->id;
        } else {
            $sectionSlug = strtolower(WcSplintrPaymentGateway::class);
        }

        return admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $sectionSlug);
    }

    /**
     * Add Splintr Payment Gateway
     *
     * @param $gateways
     *
     * @return array
     */
    public function registerPaymentGateways($gateways)
    {
        $gateways[] = woo_splintr_checkout_wc_splintr_gateway_instance();

        return $gateways;
    }

    /**
     * Enqueue admin scripts for settings
     */
    public function enqueueAdminSettingScripts()
    {
        $screen = get_current_screen();
        // Only enqueue the setting scripts on the Splintr Checkout settings screen.
        if ($screen && ('woocommerce_page_wc-settings' === $screen->id) && isset($_GET['tab'], $_GET['section']) && ('checkout' === $_GET['tab']) && (static::SPLINTR_PAYMENT_GATEWAY_ID === $_GET['section'])) {
            wp_enqueue_script('splintr-checkout-admin-js', $this->baseUrl . '/assets/dist/js/admin.js', [],
                $this->version, true);
            wp_enqueue_style('splintr-checkout-admin-css', $this->baseUrl . '/assets/dist/css/admin.css', [],
                $this->version);
        } // Load the admin stylesheet on shop order screen
        elseif ($screen && ('shop_order' === $screen->post_type)) {
            wp_enqueue_style('splintr-checkout-admin-css', $this->baseUrl . '/assets/dist/css/admin.css', [],
                $this->version);
        }
    }

    /**
     * Enqueue FE stylesheet and scripts
     */
    public function enqueueScripts()
    {
        wp_enqueue_style('splintr-checkout', $this->baseUrl . '/assets/dist/css/main.css', [], $this->version);
        wp_enqueue_script('splintr-checkout', $this->baseUrl . '/assets/dist/js/main.js', [], $this->version, true);
    }

    /**
     * Add rewrite rules for Splintr API and Webhook response page
     */
    public function addCustomRewriteRules()
    {
        add_rewrite_rule(WcSplintrPaymentGateway::SPLINTR_CALLBACK_SLUG . '/?$', 'index.php?pagename=' . WcSplintrPaymentGateway::SPLINTR_CALLBACK_SLUG, 'top');
        add_rewrite_rule(WcSplintrPaymentGateway::SPLINTR_REDIRECT_SLUG . '/?$', 'index.php?pagename=' . WcSplintrPaymentGateway::SPLINTR_REDIRECT_SLUG, 'top');
    }

    /**
     * Add needed params for Splintr checkout redirect url
     */
    public function checkoutParams()
    {
        $wcOrderId = filter_input(INPUT_GET, 'referenceNumber', FILTER_SANITIZE_STRING);
        if (!empty($wcOrderId)) {
	        $verifyFailedUrl = $this->getWcOrderCancelUrl($wcOrderId) ?
		        $this->getWcOrderCancelUrl($wcOrderId) :
		        wc_get_cart_url();
            ?>
        <script>
            let splintrCheckoutParams = {
                'ajaxUrl': '<?php echo esc_attr(admin_url('admin-ajax.php'))?>',
                'splintrVerifyFailedUrl': '<?php echo $verifyFailedUrl ?>',
            }
        </script>
            <?php
        }
    }

    /**
     * Handle process for Splintr endpoint slug returned
     *
     * @param \WP $wp
     *
     */
    public function handleCustomRequests($wp)
    {
        $pagename = null;
        if (isset($wp->query_vars['pagename'])) {
            $pagename = $wp->query_vars['pagename'];
        }

        if (WcSplintrPaymentGateway::SPLINTR_CALLBACK_SLUG === $pagename) {
            woo_splintr_checkout_callback_service_instance()->handleCallbackRequest();
        } elseif (WcSplintrPaymentGateway::SPLINTR_REDIRECT_SLUG === $pagename) {
            $this->handleRedirectRequest();
        }
    }

    /**
     * Handle redirect url returned from Splintr
     */
    public function handleRedirectRequest()
    {
        if (!$this->isOrderInRequest()) {
            wp_redirect($this->getCartUrlForUnreachableWcOrder());
        }

        $this->handleRedirectSuccess();

        exit();
    }

    /**
     * @return bool
     */
    private function isOrderInRequest()
    {
        return !empty(filter_input(INPUT_GET, 'referenceNumber'));
    }

	/**
	 * Handle redirect success url returned from Splintr
	 */
	public function handleRedirectSuccess() {
		$wcOrderId = filter_input( INPUT_GET, 'referenceNumber' );
		$wcOrder = wc_get_order( $wcOrderId );

		if (empty($wcOrder)) {
            wp_send_json(
                [
                    'message' => 'splintr_verify_failed',
                ]
            );
            return;
		}

        $this->updateOrderStatusAndAddOrderNote($wcOrder, 'wc-on-hold');

		wp_redirect($this->getWcOrderSuccessUrl($wcOrder));
	}

    /**
     * Add Splintr Failed Message on cart page
     */
    public function addFailedMessageOnCartPage()
    {
        $splintrTransactionStatusParam = filter_input(INPUT_GET, 'transaction_status', FILTER_SANITIZE_STRING)
            ?: '';
        $splintrRedirectFromParam = filter_input(INPUT_GET, 'redirect_from', FILTER_SANITIZE_STRING)
            ?: '';
        if ('error' === $splintrTransactionStatusParam && 'splintr' === $splintrRedirectFromParam) {
            wc_add_notice(__('We are unable to process your payment through Splintr. Please contact us if you need assistance.', 'splintr-checkout'), 'error');
        }
    }

    /**
     * Generate url for Splintr order verified successful
     *
     * @param WC_Order $wcOrder
     *
     * @return string
     */
    public function getWcOrderSuccessUrl($wcOrder)
    {
        if ($wcOrder) {
            return esc_url_raw($wcOrder->get_checkout_order_received_url());
        }

        return home_url();
    }

    /**
     * Generate url for Splintr order verified failed
     *
     * @param int $wcOrderId
     *
     * @return string
     */
    public function getWcOrderCancelUrl($wcOrderId)
    {
        $wcOrder = wc_get_order($wcOrderId);
        if ($wcOrder) {
            return add_query_arg(
                [
                    'redirect_from' => 'splintr',
                    'transaction_status' => 'error',
                    'cancel_order' => 'true',
                    'order' => $wcOrder->get_order_key(),
                    'referenceNumber' => $wcOrderId,
                    '_wpnonce' => wp_create_nonce('woocommerce-cancel_order'),
                ],
                $wcOrder->get_cancel_order_url_raw()
            );
        }

        return wc_get_cart_url();
    }

    /**
     * Generate url for unreachable WC Order
     *
     * @return string
     */
    public function getCartUrlForUnreachableWcOrder()
    {
        return add_query_arg(
            [
                'redirect_from' => 'splintr',
                'transaction_status' => 'error',
            ],
            wc_get_cart_url()
        );
    }

    /**
     * Detect if an order is verified or not
     *
     * @param $wcOrderId
     *
     * @return bool
     */
    public function isOrderVerified($wcOrderId)
    {
        return !!get_post_meta($wcOrderId, '_splintr_referenceNumber', true);
    }

    /**
     * Check if Splintr Gateway is enabled in admin settings
     */
    public function isSplintrGatewayEnabled()
    {
        $gatewayOptions = get_option($this->getWCSplintrGatewayOptionKey(), null);

        return 'yes' === (!empty($gatewayOptions['enabled']) ? $gatewayOptions['enabled'] : 'no');
    }

    /**
     * Get WC Splintr Gateway option key
     */
    public function getWCSplintrGatewayOptionKey()
    {
        return 'woocommerce_' . static::SPLINTR_PAYMENT_GATEWAY_ID . '_settings';
    }

    /**
     * Update order status wrapper
     *
     * @param WC_Order $wcOrder
     * @param string $newOrderStatus
     *
     */
    public function updateOrderStatusAndAddOrderNote($wcOrder, $newOrderStatus)
    {
        if ($wcOrder) {
            try {
                $wcOrder->update_status($newOrderStatus, 'Splintr - ',true);
            } catch (Exception $exception) {
                $this->logMessage(sprintf("Splintr - Failed to Update Order Status - Order ID: %s, New order status: %s. Error Message: %s",
                    $wcOrder->get_id(), $newOrderStatus, $exception->getMessage()));
            }
        }
    }

    /**
     * Add Splintr Refund Note
     *
     * @param WC_Order $order
     */
    public function addRefundNote($order)
    {
        if ($this->isSplintrPaymentMethods($order->get_payment_method())) {
            echo '<br>' . __( 'This order is paid via Splintr - Buy Now Pay Later.', 'splintr-checkout' );
            echo '<br>' . '<strong>' . __( 'You can only refund once.', 'splintr-checkout' ) . '</strong>';
        }
    }

    /**
     * Check if order is paid via Splintr
     *
     * @param $paymentMethodId
     * @return bool
     */
    public function isSplintrPaymentMethods($paymentMethodId)
    {
        $splintrPaymentMethods = [static::SPLINTR_PAYMENT_GATEWAY_ID];
        return !!in_array($paymentMethodId, $splintrPaymentMethods);
    }

    /**
     * Update WC order status in Admin order management screen according to Splintr API
     *
     * @throws \Splintr\PhpSdkLib\GuzzleHttp\Exception\GuzzleException
     */
    public function updateAdminOrderManagementStatus($column, $post_id)
    {
        global $the_order;
        $wcOrder = wc_get_order($post_id);
        if (!$wcOrder) {
            return;
        }
        if ('order_status' === $column) {
            $this->updateOrderStatusAndAddOrderNote($wcOrder, $post_id);
        }
    }
}
