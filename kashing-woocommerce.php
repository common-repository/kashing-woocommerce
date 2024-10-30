<?php
/*
Plugin Name:  Kashing WooCommerce
Plugin URI:   https://wordpress.org/plugins/kashing-woocommerce/
Description:  Easily integrate Kashing Smart Payment Technology with your WooCommerce website.
Version:      1.1.1
Author:       Kashing Limited
Author URI:   https://www.kashing.co.uk/
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  kashing-wc
Domain Path:  /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * Initializing the Kashing Payment Gateway Class
 */

if ( ! class_exists( 'Kashing_WC' ) ) {

    define( 'KASHING_WC_PATH', dirname(__FILE__) . '/' );
    define( 'KASHING_WC_URL', plugins_url( '', __FILE__ ) );

    class Kashing_WC {

        /**
         * @var Instance of a class. Singleton pattern.
         */

        private static $instance;

        /**
         * @var An array of admin notices.
         */

        public $notices = array();

        /**
         * Get the instance of the class. Singleton pattern.
         *
         * @return Kashing_WC singleton class instance.
         */

        public static function get_instance() {
            if ( self::$instance === null ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Class constructor.
         */

        private function __construct() {

            add_action( 'admin_notices', array( $this, 'check_environment' ) );
            add_action( 'admin_notices', array( $this, 'admin_notices' ) );
            add_action( 'plugins_loaded', array( $this, 'plugin_init' ) );

            require_once( KASHING_WC_PATH . 'includes/class-wc-gateway-kashing-response.php' );

        }

        /**
         * Initialize plugin.
         */

        public function plugin_init() {

            // Main Payment Gateway Class

            require_once( KASHING_WC_PATH . 'includes/class-wc-gateway-kashing.php' );

            // Exception Class

            require_once( KASHING_WC_PATH . 'includes/class-wc-kashing-exception.php' );

            // Add a new Payment Gateway

            add_filter( 'woocommerce_payment_gateways', array( $this, 'filter_add_gateway' ) );

            // Text domain

            load_plugin_textdomain( 'kashing-wc', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );

        }

        /**
         * Add a new payment gateway to WooCommerce through a WC filter.
         *
         * @param array
         *
         * @return array
         */

        public function filter_add_gateway( $methods ) {
            $methods[] = 'WC_Gateway_Kashing'; // Append a new gateway method
            return $methods;
        }

        /**
         * Check the plugin environment and display necessary admin notices.
         */

        public function check_environment() {

            // TODO Secret Key and Merchant ID checks and notices

            $options = get_option( 'woocommerce_kashing_settings' ); // Get the Kashing Payments settings array
            $testmode = ( isset( $options['testmode'] ) && 'yes' === $options['testmode'] ) ? true : false;

            $msg = array();

            // Test Mode related

            if ( $testmode == true ) {

                $mode_prefix = 'test_';

                // Merchant ID
                if ( ! isset( $options[$mode_prefix . 'merchant_id'] ) || isset( $options[$mode_prefix . 'merchant_id'] ) && $options[$mode_prefix . 'merchant_id'] == '' ) {
                    $msg[] = __( 'Test Merchant ID field is empty.', 'kashing-wc' );
                }

                // Secret Key
                if ( ! isset( $options[$mode_prefix . 'skey'] ) || isset( $options[$mode_prefix . 'skey'] ) && $options[$mode_prefix . 'skey']  == '' ) {
                    $msg[] = __( 'Test Secret Key field is empty.', 'kashing-wc' );
                }

            } else {

                $mode_prefix = 'live_';

                // Merchant ID
                if ( ! isset( $options[$mode_prefix . 'merchant_id'] ) || isset( $options[$mode_prefix . 'merchant_id'] ) && $options[$mode_prefix . 'merchant_id'] == '' ) {
                    $msg[] = __( 'Live Merchant ID field is empty.', 'kashing-wc' );
                }

                // Secret Key
                if ( ! isset( $options[$mode_prefix . 'skey'] ) || isset( $options[$mode_prefix . 'skey'] ) && $options[$mode_prefix . 'skey']  == '' ) {
                    $msg[] = __( 'Live Secret Key field is empty.', 'kashing-wc' );
                }

            }

            // Print the notice

            if ( ! empty( $msg ) ) {
                $notice_msg = __( 'There are some Kashing Payments Gateway configuration errors:', 'kashing-wc' ) . ' ' . implode( ' ', $msg );
                $notice_msg .= ' <a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=kashing' ) ) . '">' . __( 'Go to Gateway Settings', 'kashing-wc' ) . '</a>.';
                $this->add_admin_notice( 'kashing-test-configuration-error', 'notice notice-error', $notice_msg, false );
            }

            // Check if the currency is supported.

            global $woocommerce;

            $currency = get_woocommerce_currency();

            if ( !in_array( $currency, Kashing_WC::supported_currencies() ) ) {
                $this->add_admin_notice( 'kashing-currency-error', 'notice notice-error', 'The current WooCommerce Currency "' . $currency . '" is not yet supported by Kashing Payments. Supported currencies: USD, GBP, EUR.', false );
            }

        }

        /**
         * Allow this class and other classes to add slug keyed notices (to avoid duplication).
         *
         * @param string
         * @param string
         * @param string
         * @param boolean
         *
         * @return void
         */

        public function add_admin_notice( $slug, $class, $message, $dismissible = false ) {
            $this->notices[ $slug ] = array(
                'class'       => $class,
                'message'     => $message,
                'dismissible' => $dismissible,
            );
        }

        /**
         * Display any notices added to the notices array during the environment check and elsewhere.
         *
         * @return void
         */

        public function admin_notices() {

            // Check user permission

            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                return;
            }

            // Loop through the notices array

            foreach ( $this->notices as $notice_key => $notice ) {

                echo '<div class="' . esc_attr( $notice['class'] ) . '" style="position:relative;">';

                if ( $notice['dismissible'] ) {
                    ?>
                    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc-kashing-hide-notice', $notice_key ), 'wc_kashing_hide_notices_nonce', '_wc_kashing_notice_nonce' ) ); ?>" class="woocommerce-message-close notice-dismiss" style="position:absolute;right:1px;padding:9px;text-decoration:none;"></a>
                    <?php
                }

                echo '<p>';
                echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
                echo '</p></div>';
            }
        }

        /**
         * Hide a specific notification.
         *
         * @since 4.0.0
         * @version 4.0.0
         */

        public function hide_admin_notices() {

            // TODO

            $slug_notice = 'wc-kashing-hide-notice';
            $slug_nonce = '_wc_kashing_notice_nonce';

            if ( isset( $_GET[ $slug_notice ] ) && isset( $_GET[ $slug_nonce ] ) ) {

                // Verify the nonce from the GET parameter
                if ( ! wp_verify_nonce( $_GET[ $slug_nonce ], 'wc_kashing_hide_notices_nonce' ) ) {
                    wp_die( __( 'Action failed. Please refresh the page and retry.', 'kashing-wc' ) );
                }

                // Insufficient permission
                if ( ! current_user_can( 'manage_woocommerce' ) ) {
                    wp_die( __( 'Cheating, huh?', 'kashing-wc' ) );
                }

                $notice = wc_clean( $_GET[ $slug_notice ] );

                // TODO
            }

        }

        /**
         * Returns the array of support currencies.
         *
         * @return array
         */

        public static function supported_currencies() {

            return array(
                'USD',
                'EUR',
                'GBP'
            );

        }

    }

    // Get instance of the main class

    Kashing_WC::get_instance();

}
