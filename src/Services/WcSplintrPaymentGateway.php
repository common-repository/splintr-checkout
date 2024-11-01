<?php

namespace Splintr\Wp\Plugin\SplintrCheckout\Services;

use Exception;
use Splintr\Wp\Plugin\SplintrCheckout\Helpers\MoneyHelper;
use Splintr\Wp\Plugin\SplintrCheckout\SplintrCheckout;
use WC_Admin_Settings;
use WC_Order;
use WC_Payment_Gateway;
use WC_Product;

class WcSplintrPaymentGateway extends WC_Payment_Gateway
{
	protected $environment;
	protected $prettyMerchantUrlsEnabled;
	protected $baseApiUrl;
	protected $merchantId;
	protected $merchantName;

	const SPLINTR_CHECKOUT_URL = 'https://checkout.splintr.com';
    const SPLINTR_CALLBACK_SLUG = 'splintr-callback';
    const SPLINTR_REDIRECT_SLUG = 'splintr-redirect';
    const ENVIRONMENT_LIVE_MODE = 'live_mode';
    const ENVIRONMENT_SANDBOX_MODE = 'sandbox_mode';

    public function __construct()
    {
        $this->initBaseAttributes();
        $this->initSettingAttributes();
        $this->init();
    }

    public function init()
    {
        // Load the settings form.
        $this->initFormFields();

        // Process admin options
        add_action('woocommerce_update_options_payment_gateways_'.$this->id, [$this, 'processAdminOptions']);

        // Add Splintr text on Order Received page
        add_filter('woocommerce_thankyou_order_received_text', [$this, 'orderReceivedText'], 10, 2);
    }

    /**
     * Handle remote request after saving
     *
     * @param $settings
     */
    public function onSaveSettings($settings)
    {
    	$this->validateRequiredFields();
        $this->processRewriteRules();

        $this->updateThisSettingsToOptions();

        $this->initSettingAttributes();
        $this->initFormFields();
    }

    public function isSandboxMode()
    {
        return (static::ENVIRONMENT_SANDBOX_MODE === $this->environment);
    }

    public function isLiveMode()
    {
        return (static::ENVIRONMENT_LIVE_MODE === $this->environment);
    }

    public function isPrettyMerchantUrlsEnabled()
    {
        return ('yes' === $this->prettyMerchantUrlsEnabled  && 'yes' === $this->enabled);
    }

    public function initSettings()
    {
        parent::init_settings();
    }

    public function processAdminOptions()
    {
        parent::process_admin_options();
    }

	/**
	 * @inheritDoc
	 * @throws Exception
	 */
    public function process_payment($orderId)
    {
        return $this->processCheckoutRequest($orderId);
    }

    /**
     * @param $orderId
     *
     * @return array | false
     * @throws Exception
     */
    protected function processCheckoutRequest($orderId)
    {
        try {
            $sessionToken = $this->buildSessionToken(wc_get_order($orderId), $orderId);
        } catch (\Exception $e) {
            // TODO: We should probably log this error and improve the exception to make it more specific.
            return false;
        }

        return [
            'result' => 'success',
            'redirect' => $this->buildCheckoutUrl($sessionToken),
        ];
    }

    private function buildCheckoutUrl($token) {
        $checkoutUrl = sprintf(
            "%s/?token=%s&merchantUrl=%s",
            static::SPLINTR_CHECKOUT_URL,
            $token,
            $this->getRedirectUrl()
        );

        if ($this->isSandboxMode()) {
            $checkoutUrl .= '&sandboxMode=true';
        }

        if (strpos(get_locale(), 'ar') === 0) {
            $checkoutUrl = str_replace('?token', 'ar?token', $checkoutUrl);
        }

        return $checkoutUrl;
    }

    /**
     * @param WC_Order $wcOrder
     * @param string $wcOrderId
     * @return string
     * @throws Exception
     */
	private function buildSessionToken($wcOrder, $wcOrderId) {
        $jsonPayload = json_encode([
            'reference' => (string)$wcOrderId,
            'merchant' => [
                'id' => $this->getMerchantId(),
                "name" => $this->getMerchantName()
            ],
            'total' => MoneyHelper::formatAndRoundNumber($wcOrder->get_total()),
            'currency' => $wcOrder->get_currency(),
            'items' => $this->populateOrderItemsParams($wcOrder)
        ]);

        if (!$jsonPayload) {
            throw new Exception("Unable to build checkout session. Could not encode request payload.");
        }

        return base64_encode($jsonPayload);
	}

	public function populateOrderItemsParams($wcOrder) {
		$wcOrderItems = $wcOrder->get_items();
		$itemsData    = [];

		foreach ( $wcOrderItems as $itemId => $wcOrderItem ) {
			/** @var WC_Product $wcOrderItemProduct */
			$wcOrderItemProduct = $wcOrderItem->get_product();

			$wcOrderItemQuantity     = $wcOrderItem->get_quantity();
			$wcOrderItemTitle        = strip_tags( $wcOrderItem->get_name() );
			$wcOrderItemRegularPrice = MoneyHelper::formatAndRoundNumber( $wcOrderItemProduct->get_regular_price() );
			$wcOrderItemSalePrice    = MoneyHelper::formatAndRoundNumber( $wcOrderItemProduct->get_sale_price() );
			$wcOrderItemUnitPrice    = $wcOrderItemSalePrice ? $wcOrderItemSalePrice : $wcOrderItemRegularPrice;
			$wcOrderItemDesciption   = $wcOrderItemProduct->get_description() ? $wcOrderItemProduct->get_description() : 'N/A';

			$itemsData[] = [
				'name'        => $wcOrderItemTitle,
				'description'  => $this->getMaxCharacterForString( $wcOrderItemDesciption, 255 ),
				'quantity'     => $wcOrderItemQuantity,
				'amount'   => $wcOrderItemUnitPrice,
			];
		}

		return $itemsData;
	}

    /**
     * Get Splintr Callback Url
     *
     * @return string
     */
    public function getCallbackUrl()
    {
        if ( ! empty(get_option('permalink_structure'))) {
            return home_url(static::SPLINTR_CALLBACK_SLUG);
        }

        return add_query_arg(
            ['pagename' => static::SPLINTR_CALLBACK_SLUG], home_url()
        );
    }

    /**
     * Get Splintr Redirect Url
     *
     * @return string
     */
    public function getRedirectUrl()
    {
        if (!empty(get_option('permalink_structure'))) {
            return home_url(static::SPLINTR_REDIRECT_SLUG);
        }

        return add_query_arg(
            ['pagename' => static::SPLINTR_REDIRECT_SLUG],
            home_url()
        );
    }

    /**
     * @param string $str
     * @param int $maxCharacter
     *
     * @return false|string
     */
    public function getMaxCharacterForString($str, $maxCharacter)
    {
        if (strlen($str) > $maxCharacter) {
            return substr($str, 0, $maxCharacter);
        }

        return $str;
    }

    /**
     * Handle rewrite rules on enable/disable pretty merchant urls
     */
    protected function processRewriteRules()
    {
        $this->prettyMerchantUrlsEnabled = $this->get_option('pretty_merchant_urls');
        if ($this->isPrettyMerchantUrlsEnabled()) {
            // Add rewrite rules
            woo_splintr_checkout_instance()->addCustomRewriteRules();
            // Flush rewrite rules
            flush_rewrite_rules(false);
        }
    }

    /**
     * We need to update settings to db options (table options)
     */
    public function updateThisSettingsToOptions()
    {
        update_option(
            $this->get_option_key(),
            apply_filters(
                'woocommerce_settings_api_sanitized_fields_'.$this->id,
                $this->settings
            ),
            'yes'
        );
    }

    /**
     * Init admin form fields
     */
    public function initFormFields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __( 'Enable/Disable', 'splintr-checkout'),
                'label' => __( 'Enable Splintr Payment Gateway', 'splintr-checkout'),
                'type' => 'checkbox',
            ],
            'environment' => [
                'title' => __( 'Environment', 'splintr-checkout'),
                'label' => __( 'Select an environment', 'splintr-checkout'),
                'type' => 'select',
                'default' => static::ENVIRONMENT_LIVE_MODE,
                'options' => [
                    static::ENVIRONMENT_LIVE_MODE => 'Live Mode',
                    static::ENVIRONMENT_SANDBOX_MODE => 'Sandbox Mode',
                ],
                'description' => __( 'This setting specifies whether you will process live transactions, or whether you will process simulated transactions using our Sandbox environment.',
                    'splintr-checkout'),
            ],
            'merchant_id' => [
                'title' => __( 'Merchant ID', 'splintr-checkout'),
                'type' => 'text',
                'description' => __( 'Get your Merchant ID from Splintr.', 'splintr-checkout'),
                'default' => '',
            ],
            'merchant_name' => [
	            'title' => __( 'Merchant Name', 'splintr-checkout'),
	            'type' => 'text',
	            'description' => __( 'Display name on Checkout.', 'splintr-checkout'),
	            'default' => '',
            ],
            'debug_info' => [
                'title' => __( 'Debug Info', 'splintr-checkout'),
                'type' => 'title',
                'description' =>
                    '<p>'.sprintf('PHP Version: %s', PHP_VERSION).'</p>'
                    .'<p>'.sprintf('PHP loaded extension: %s', implode( ', ', get_loaded_extensions())).'</p>'
            ]
        ];
    }

	/**
	 * Raise Admin Error notice on failed API params
	 */
	public function raiseAdminError() {
		WC_Admin_Settings::add_error(
			__( 'Error! Splintr Checkout params cannot be retrieved correctly, Splintr disabled. 
			Please recheck your Splintr settings (Merchant Id, Merchant Name...).',
				'splintr-checkout' )
		);
	}

	/**
	 * Check if all the required field values have been input or not
	 * and verify api params
	 */
	public function validateRequiredFields()
	{
		if (!$this->get_option('merchant_id') || !$this->get_option('merchant_name')) {
			$this->settings['enabled'] = 'no';
			$this->raiseAdminError();
			return false;
		}

		return true;
	}

    /**
     * Add Splintr Text after successful payment
     *
     * @param string $str
     * @param int $orderId
     *
     * @return string
     *
     */
    public function orderReceivedText($str, $orderId)
    {
        $wcOrder = wc_get_order($orderId);
        $payment_method = $wcOrder->get_payment_method();

        if (!empty($payment_method) && $this->id === $payment_method) {

            return $str . woo_splintr_checkout_view_service_instance()->render('/woocommerce/checkout/splintr-thankyou',
                    [
                        'textDomain' => 'splintr-checkout',
                    ]);
        }

        return $str;
    }

    /**
     * Return the name of Splintr settings option in the WP DB.
     *
     * @return string
     */
    public function get_option_key()
    {
        return woo_splintr_checkout_instance()->getWCSplintrGatewayOptionKey();
    }

    /**
     * Initialize attributes that are fixed
     */
    protected function initBaseAttributes()
    {
        $this->id = SplintrCheckout::SPLINTR_PAYMENT_GATEWAY_ID;
        $this->icon = woo_splintr_checkout_instance()->getBaseUrl() . '/assets/dist/img/splintr.png';
        $this->has_fields = true;
        $this->description = __( 'Buy now and pay later. No late payment fees.', 'splintr-checkout' );
        $this->order_button_text = __( 'Proceed to Splintr', 'splintr-checkout' );
        $this->method_title = __( 'Splintr Payment Gateway', 'splintr-checkout' );
        $this->method_description = __( 'Add Splintr to your checkout to let your customer pay it their way whilst getting paid upfront', 'splintr-checkout' );
	    $this->title = __( 'Splintr', 'splintr-checkout' );
	    $this->initBaseSettings();
    }

    /**
     * Initialize attributes that can be changed through admin settings
     */
    protected function initSettingAttributes()
    {
        // Ensure the environment is set before calling isLiveMode()
        $this->environment = $this->get_option('environment', static::ENVIRONMENT_LIVE_MODE);
        $this->enabled = $this->get_option('enabled', 'no' );
        $this->prettyMerchantUrlsEnabled = $this->get_option('pretty_merchant_urls', 'no');
    }

    /**
     * Initialize base settings for Splintr actions
     */
    protected function initBaseSettings()
    {
        $this->merchantId = $this->get_option('merchant_id', null);
        $this->merchantName = $this->get_option('merchant_name', null);
    }

	/**
	 * Get Merchant Id from Admin setting
	 *
	 * @return mixed
	 */
	protected function getMerchantId() {
		return $this->merchantId;
	}

	/**
	 * Get Merchant Name from Admin setting
	 *
	 * @return mixed
	 */
	protected function getMerchantName() {
		return $this->merchantName;
	}
}