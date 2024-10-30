<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( !class_exists( 'WC_Gateway_Kashing' ) ) {

    class WC_Gateway_Kashing extends WC_Payment_Gateway {

        /**
         * Test mode enabled or disabled.
         *
         * @var bool
         */

        public $testmode;

        /**
         * Debug logging on or off.
         *
         * @var bool
         */

        public $debug;

        /**
         * Test merchant ID.
         *
         * @var string
         */

        public $test_merchant_id;

        /**
         * Test Secret Key.
         *
         * @var string
         */

        public $test_skey;

        /**
         * Live merchant ID.
         *
         * @var string
         */

        public $live_merchant_id;

        /**
         * Live Secret Key.
         *
         * @var string
         */

        public $live_skey;

        /**
         * Whether or not logging is enabled.
         *
         * @var bool
         */

        public static $log_enabled = true;

        /**
         * WooCommerce Logger instance.
         *
         * @var WC_Logger Logger instance
         */

        public static $log = false;

        /**
         * Class Constructor
         */

        function __construct() {

            // Core gateway settings

            $this->id = 'kashing'; //– Unique ID for your gateway, e.g., ‘your_gateway’
            $this->icon = ''; //– If you want to show an image next to the gateway’s name on the frontend, enter a URL to an image.
            $this->has_fields = false;  //– Bool. Can be set to true if you want payment fields to show on the checkout (if doing a direct integration).
            $this->method_title = 'Kashing';  //– Title of the payment method shown on the admin page.
            $this->method_description = __( 'Allow customers to conveniently pay with a credit card via Kashing Payments.', 'kashing-wc' );  //– Description for the payment method shown on the admin page.
            $this->order_button_text = __( 'Pay with Kashing', 'kashing-wc' );
            $this->testmode = 'yes' === $this->get_option( 'testmode', 'no' );
            $this->description = $this->get_option( 'description' );
            $this->debug = 'yes' === $this->get_option( 'debug_log', 'no' );
            $this->supports = array(
                'products'
            );

            // An extra information in the gateway button about the test mode being active.

            if ( $this->testmode ) {
                $this->description = __( 'TEST MODE enabled.', 'kashing-wc' ) . ' ' . $this->description;
            }

            // Test API related data

            $this->test_merchant_id = $this->get_option( 'test_merchant_id' );
            $this->test_skey = $this->get_option( 'test_skey' );

            // Live API related data

            $this->live_merchant_id = $this->get_option( 'live_merchant_id' );
            $this->live_skey = $this->get_option( 'live_skey' );

            // Logging

            self::$log_enabled = $this->debug;

            // Load settings fields

            $this->init_form_fields();
            $this->init_settings();

            // Basic settings

            $this->title = $this->get_option('title');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Kashing Gateway Settings Page

            $this->form_fields = array(
                // Core Settings
                'enabled' => array(
                    'title' => __('Enable/Disable', 'kashing-wc'),
                    'type' => 'checkbox',
                    'label' => __('Enable Kashing Payments', 'kashing-wc'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'kashing-wc'),
                    'default' => __('Credit Card (Kashing)', 'kashing-wc'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Customer Message', 'kashing-wc'),
                    'type' => 'text',
                    'description' => __('This controls the description which the user sees during checkout.', 'kashing-wc'),
                    'default' => __('Pay with your credit card via Kashing.', 'kashing-wc'),
                    'desc_tip' => true,
                ),
                // API Settings
                'api-settings-title' => array(
                    'title' => __('Kashing Configuration', 'kashing-wc'),
                    'type' => 'title',
                    'description' => __('You may retrieve your Kashing Merchant ID and Secret Key here:', 'kashing-wc') . ' <a href="' . esc_url('https://www.kashing.co.uk/docs/?shell#account-information') . '" target="_blank">' . esc_html('API Documentation', 'kashing-wc') . '</a>'
                ),
                'testmode' => array(
                    'title' => __('Test Mode', 'kashing-wc'),
                    'type' => 'checkbox',
                    'label' => __('Enable the Test Mode', 'kashing-wc'),
                    'description' => __( 'Activate or deactivate the plugin Test Mode. When Test Mode is activated, no credit card payments are processed.', 'kashing-wc' ),
                    'default' => 'yes',
                    'desc_tip' => true,
                ),
                'test_merchant_id' => array(
                    'title' => __('Test Merchant ID', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Enter your testing Merchant ID.', 'kashing-wc'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'test_skey' => array(
                    'title' => __('Test Secret Key', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Enter your testing Secret Key.', 'kashing-wc'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'live_merchant_id' => array(
                    'title' => __('Live Merchant ID', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Enter your live Merchant ID.', 'kashing-wc'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'live_skey' => array(
                    'title' => __('Live Secret Key', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Enter your live Secret Key.', 'kashing-wc'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                // Advanced
                'kashing-advanced-title' => array(
                    'title' => __('Advanced', 'kashing-wc'),
                    'type' => 'title',
                    'description' => __('Some additional, advanced settings.', 'kashing-wc')
                ),
                'debug_log' => array(
                    'title' => __( 'Debug log', 'kashing-wc'),
                    'type' => 'checkbox',
                    'label' => __( 'Enable logging', 'kashing-wc'),
                    'description' => __( 'Enable logging for easier system debugging.', 'kashing-wc' ),
                    'default' => 'no',
                    'desc_tip' => true,
                )
            );

        }

        /**
         * Logging method.
         *
         * @param string $message Log message.
         * @param string $level Optional. Default 'info', emergency|alert|critical|error|warning|notice|info|debug
         */

        public static function log( $message, $level = 'info' ) {
            if ( self::$log_enabled ) {
                if ( empty( self::$log ) || self::$log == false ) {
                    self::$log = wc_get_logger();
                }
                self::$log->log( $level, $message, array( 'source' => 'kashing' ) );
            }
        }

        /**
         * Get gateway icon.
         *
         * @return string
         */

        public function get_icon() {

            $icon_html = '<img src="' . KASHING_WC_URL . '/assets/img/kashing-logo-small.png" alt="">';
            return apply_filters( 'woocommerce_kashing_icon', $icon_html, $this->id );

        }

        /**
         * WC payment processing extension.
         *
         * @param int $order_id
         *
         * @return array
         */

        function process_payment( $order_id ) {

            include_once( dirname( __FILE__ ) . '/class-wc-gateway-kashing-request.php' );

            $order = wc_get_order( $order_id);
            $kashing_request = new WC_Gateway_Kashing_Request( $this );
            $kashing_request_url = $kashing_request->get_request_url( $order );

            WC_Gateway_Kashing::log( 'Request URL result: ' . $kashing_request_url['result'] );

            if ( $kashing_request_url['result'] == 'success' ) {

                // TODO Set ANY additional status? on-hold or something

                $order->add_order_note( __( 'Kashing payment initialised.', 'kashing-wc' ) );

                // Reduce stock levels
                wc_reduce_stock_levels( $order_id );

                // Remove cart
                global $woocommerce;
                $woocommerce->cart->empty_cart();

                // Success
                return array(
                    'result'   => 'success',
                    'redirect' => $kashing_request_url['redirect_url'] // Redirect to the Kashing Payment Gateway
                );

            }

            // Payment error occured

            return array(
                'result' => 'fail',
                'redirect' => ''
            );

        }

    }
}