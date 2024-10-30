<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle the Kashing Gateway response.
 */

class WC_Gateway_Kashing_Response {

    /**
     * @var Instance of a class. Singleton pattern.
     */

    private static $instance;

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

    public function __construct() {

        add_action( 'init', array( $this, 'handle_kashing_response' ) );
        add_action( 'woocommerce_thankyou_kashing', array( $this, 'show_transaction_details' ) );

    }

    /**
     * Detect and handle Kashing response.
     *
     * @return void
     */

    public function handle_kashing_response() {

        if ( $this->is_kashing_response() )  {

            $order_key = $_GET['key'];

            // Get WooCommerce order object
            $order = new WC_Order( wc_get_order_id_by_order_key( $order_key ) );

            if ( get_post_meta( $order->get_id(), '_kashing_processed', true ) == '' ) {

                // Determine the success or failure based on the response and reason code

                if ( $_GET['Response'] == 1 && $_GET['Reason'] == 1 && isset( $_GET['TransactionID'] ) ) { // Success

                    // Payment success
                    $order->add_order_note( __( 'Kashing payment successful.', 'kashing-wc' ) );

                    // Set post meta with the Transaction ID
                    update_post_meta( $order->get_id(), '_kashing_processed', 'yes' );
                    update_post_meta( $order->get_id(), '_transaction_id', $_GET['TransactionID'] );

                    // Payment
                    $order->payment_complete();

                } else {
                    // Payment failure
                    $order->update_status( 'failed', __( 'Kashing payment failed.', 'kashing-wc' ) );
                }

            }

        }

    }

    /**
     * Detect and handle Kashing response.
     *
     * @return boolean
     */

    public function is_kashing_response() {

        if ( isset( $_GET ) && isset( $_GET['key'] ) && isset( $_GET['Response'] ) && isset( $_GET['Reason'] ) )  {
            return true;
        }

        return false;

    }


    /**
     * Show transaction details.
     *
     * @return void
     */

    public function show_transaction_details() {

        if ( $this->is_kashing_response() && current_user_can( 'administrator' ) )  {

            $output = '';

            // Determine the success or failure based on the response and reason code

            if ( $_GET[ 'Response' ] == 1 && $_GET[ 'Reason' ] == 1 ) { // Success

                $output .= '<div class="kashing-frontend-notice kashing-success">';
                $output .= '<p><strong>' . __( 'Kashing payment successful!', 'kashing-wc' ) . '</strong></p><p>' . __( 'Transaction details', 'kashing-wc' ) . ':</p><ul>';
                $output .= '<li>' . __( 'Transaction ID', 'kashing-wc' ) . ': <strong>' . esc_html( $_GET[ 'TransactionID' ] ) . '</strong></li>';
                if ( $_GET[ 'Response' ] ) {
                    $output .= '<li>' . __( 'Response Code', 'kashing-wc' ) . ': <strong>' . esc_html( $_GET[ 'Response' ] ) . '</strong></li>';
                }
                if ( $_GET[ 'Reason' ] ) {
                    $output .= '<li>' . __( 'Reason Code', 'kashing-wc' ) . ': <strong>' . esc_html( $_GET[ 'Reason' ] ) . '</strong></li>';
                }
                $output .= '</ul><p>' . __( 'This notice is displayed to site administrators only.', 'kashing-wc' ) . '</p>';
                $output .= '</div>';

            } else { // Payment failure

                $output .= '<div class="kashing-frontend-notice kashing-errors">';
                $output .= '<p><strong>' . __( 'Kashing payment failed.', 'kashing-wc' ) . '</strong></p><p>' . __( 'Transaction details', 'kashing-wc' ) . ':</p><ul>';
                $output .= '<li>' . __( 'Transaction ID', 'kashing-wc' ) . ': <strong>' . esc_html( $_GET[ 'TransactionID' ] ) . '</strong></li>';
                if ( $_GET[ 'Response' ] ) {
                    $output .= '<li>' . __( 'Response Code', 'kashing-wc' ) . ': <strong>' . esc_html( $_GET[ 'Response' ] ) . '</strong></li>';
                }
                if ( $_GET[ 'Reason' ] ) {
                    $output .= '<li>' . __( 'Reason Code', 'kashing-wc' ) . ': <strong>' . esc_html( $_GET[ 'Reason' ] ) . '</strong></li>';
                }
                $output .= '</ul><p>' . __( 'This notice is displayed to site administrators only.', 'kashing-wc' ) . '</p>';
                $output .= '</div>';

            }

            echo $output;

        }

    }


}

// Get instance of the main class

WC_Gateway_Kashing_Response::get_instance();