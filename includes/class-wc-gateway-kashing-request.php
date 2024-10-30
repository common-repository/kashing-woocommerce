<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Generates requests to send to Kashing.
 */

class WC_Gateway_Kashing_Request {

    /**
     * Pointer to gateway making the request so we can grab the Kashing settings like Secret Keys etc.
     *
     * @var WC_Gateway_Kashing
     */

    protected $gateway;

    /**
     * Whether the test mode is enabled or not.
     *
     * @var boolean
     */

    private $testmode;

    /**
     * Kashing API URL.
     *
     * @var string
     */

    private $api_url;

    /**
     * Merchant ID.
     *
     * @var string
     */

    private $merchant_id;

    /**
     * Secret Key.
     *
     * @var string
     */

    private $secret_key;

    /**
     * Class constructor.
     *
     * @param WC_Gateway_Kashing $gateway
     */

    public function __construct( $gateway ) {

        $this->log( 'WC_Kashing_Payment_Request object created.' );

        $this->gateway = $gateway;

        if ( $gateway->testmode == 'yes' ) {
            $this->testmode = true;
            $this->api_url = 'https://staging-api.kashing.co.uk/';
            $this->merchant_id = $gateway->test_merchant_id;
            $this->secret_key = $gateway->test_skey;
        } else {
            $this->testmode = false;
            $this->api_url = 'https://api.kashing.co.uk/';
            $this->merchant_id = $gateway->live_merchant_id;
            $this->secret_key = $gateway->live_skey;
        }

    }

    /**
     * Get the Kashing request URL for the payment.
     *
     * @param  WC_Order $order
     * @return array
     */

    public function get_request_url( $order ) {

        $kashing_api_call = $this->api_call_transaction( $order );

        return $kashing_api_call;

    }

    /**
     * Get the Kashing request URL parameters.
     *
     * @param  WC_Order $order
     * @return array
     */

    private function api_call_transaction( $order ) {

        try {

            $this->log( 'Preparing a Kashing New Transaction API Call.' );

            // Client data

            $order_client_data = $this->get_order_client_data( $order );

            if ( $order_client_data == false ) {
                throw new WC_Kashing_Exception( __( 'Billing data missing.', 'kashing-wc' ) );
            }

            // API Call URL along with the endpoint

            $url = $this->api_url . 'transaction/init';

            // Transaction Amount

            $amount = $order->get_total();

            // Transaction Currency

            $currency = $order->get_currency();

            if ( !$this->currency_supported( $currency ) ) {
                throw new WC_Kashing_Exception( __( 'The transaction currency is not yet supported by Kashing Payments.', 'kashing-wc' ) );
            }

            // Return URL

            $return_url = $this->gateway->get_return_url( $order );

            // Transaction Description

            $description = 'WooCommerce Order #' . $order->get_id() . ' by user_id=' . $order->get_user_id() . ' from ' . get_home_url();

            // Transaction Data Array

            $transaction_data = array(
                'merchantid' => sanitize_text_field( $this->merchant_id ), // TODO test?
                'amount' => sanitize_text_field( $amount ),
                'currency' => sanitize_text_field( $currency ),
                'returnurl' => sanitize_text_field( $return_url ),
                "description" => sanitize_text_field( $description )
            );

            // Add form input data

            $transaction_data = array_merge(
                $transaction_data,
                $order_client_data
            );

            // Get the transaction psign

            $transaction_psign = $this->get_psign( $transaction_data );

            // Final API Call Body with the psign (merging with the $transaction_data array)

            $final_transaction_array = array(
                'transactions' => array(
                    array_merge(
                        $transaction_data,
                        array(
                            'psign' => $transaction_psign
                        )
                    )
                )
            );

            // API Call body in JSON Format

            $body = json_encode( $final_transaction_array );
            $this->log( 'Endpoint: ' . $url );
            $this->log( 'Kashing transaction body: ' . $body );

            // Make the API Call

            $response = wp_remote_post(
                $url,
                array(
                    'method' => 'POST',
                    'timeout' => 10,
                    'headers' => array( 'Content-Type' => 'application/json' ),
                    'body' => $body,
                )
            );

            // Deal with the call response

            if ( is_wp_error( $response ) ) {

                throw new WC_Kashing_Exception(
                    __( 'There was something wrong with the WordPress API Call.', 'kashing-wc' ),
                    true
                );

            } else {
                $this->log( 'Transaction initialised.' );
            }

            // Remote call successful, extract data

            $response_body = json_decode( $response[ 'body' ] ); // Decode the response body from JSON

            if ( isset( $response_body->results ) && isset( $response_body->results[0] ) && 
                 isset( $response_body->results[0]->responsecode ) && 
                 isset( $response_body->results[0]->reasoncode ) ) {

                if ( $response_body->results[0]->responsecode == 4 && $response_body->results[0]->reasoncode == 1 && isset( $response_body->results ) && isset( $response_body->results[0]->redirect ) ) { // We've got a redirection

                    // Everything is fine, redirecting the user
                    $redirect_url = $response_body->results[0]->redirect; // Kashing redirect URL

                    $this->log( 'Kashing API call response correct, gateway URL: ' . esc_url( $redirect_url ) );

                    return array(
                        'result' => 'success',
                        'redirect_url' => $redirect_url
                    );

                } else { // There is no Redirect URL
                    throw new WC_Kashing_Exception(
                        __( 'There was something wrong with a redirection response from the Kashing server.', 'kashing-wc' ),
                        true
                    );
                }

                // There was an error

                $this->log( 'There was an error with the Kashing API call' );
                $this->log( 'Response Code: ' . $response_body->results[0]->responsecode );
                $this->log( 'Reason Code: ' . $response_body->results[0]->reasoncode );
                $this->log( 'Error: ' . $response_body->results[0]->error );

                // Additional suggestion based on the error type

                $suggestion = $this->get_api_error_suggestion( $response_body->results[0]->responsecode, $response_body->results[0]->reasoncode );

                if ( $suggestion != false ) {
                    $this->log( 'Suggestion: ' . $suggestion );
                }

            }

            throw new WC_Kashing_Exception(
                __( 'There was an error with the Kashing API call response.', 'kashing-wc' ),
                true
            );

        } catch( WC_Kashing_Exception $e ) {

            // Handle all thrown errors

            if ( $e->getPublicMessage() != '' && !current_user_can( 'administrator' ) ) {
                wc_add_notice( $e->getPublicMessage(), 'error' );
            } else {
                wc_add_notice( $e->getMessage(), 'error' );
            }

            $this->log( $e->getMessage() );

            return array(
                'result' => 'error',
            );

        }

    }

    /**
     * Get the WC order client data to be sent to Kashing API.
     * @param  WC_Order $order
     * @return array, boolean
     */

    public function get_order_client_data( $order ) {

        $client_data = array();

        // First Name
        if ( $order->get_billing_first_name() != '' ) {
            $client_data['firstname'] = $order->get_billing_first_name();
        } else {
            return false;
        }

        // Last Name
        if ( $order->get_billing_last_name() != '' ) {
            $client_data['lastname'] = $order->get_billing_last_name();
        } else {
            return false;
        }

        // Email Address
        if ( $order->get_billing_email() != '' ) {
            $client_data['email'] = $order->get_billing_email();
        }

        // Phone
        if ( $order->get_billing_phone() != '' ) {
            $client_data['phone'] = $order->get_billing_phone();
        }

        // Address 1
        if ( $order->get_billing_address_1() != '' ) {
            $client_data['address1'] = $order->get_billing_address_1();
        } else {
            return false;
        }

        // Address 2
        if ( $order->get_billing_address_2() != '' ) {
            $client_data['address2'] = $order->get_billing_address_2();
        }

        // City
        if ( $order->get_billing_city() != '' ) {
            $client_data['city'] = $order->get_billing_city();
        } else {
            return false;
        }

        // Postcode
        if ( $order->get_billing_postcode() != '' ) {
            $client_data['postcode'] = $order->get_billing_postcode();
        } else {
            return false;
        }

        // Country
        if ( $order->get_billing_country() != '' ) {
            $client_data['country'] = $order->get_billing_country();
        }

        return $client_data;

    }

    /**
     * Get the Kashing transaction psign.
     *
     * @param  array
     * @return string
     */

    private function get_psign( $transaction_data ) {

        // The transaction string to be hashed: secret key + transaction data string
        $transaction_string = $this->secret_key . $this->extract_transaction_data( $transaction_data );

        // SHA1
        $psign = sha1( $transaction_string );

        return $psign;

    }

    /**
     * Extract transaction data values from the transaction data array.
     *
     * @param array
     * @return string
     */

    public function extract_transaction_data( $transaction_data_array ) {

        $data_string = '';

        foreach ( $transaction_data_array as $data_key => $data_value ) {
            $data_string .= $data_value;
        }

        return $data_string;

    }

    /**
     * Log messages.
     *
     * @param string
     * @param string
     * @return void
     */

    private function log( $message, $level = 'info' ) {
        WC_Gateway_Kashing::log( $message, $level );
    }

    /**
     * Is the WooCommerce transaction currency supported by Kashing Payments?
     *
     * @param string
     * @return boolean
     */

    public function currency_supported( $currency ) {

        $supported_currencies = array( 'USD', 'GBP', 'EURO' );

        if ( in_array( $currency, $supported_currencies ) ) {
            return true;
        }

        return false;

    }

    /**
     * Additional suggestion for the plugin administrator based on the response and reason code from Kashing API.
     *
     * @param int
     * @param int
     *
     * @return string
     */

    public function get_api_error_suggestion( $response_code, $reason_code ) {

        if ( $response_code == 3 ) {
            switch ( $reason_code ) {
                case 9:
                    return __( 'Please make sure your Merchant ID is correct.', 'kashing' );
                    break;
                case 104:
                    return __( 'Please make sure that your Secret API Key and Merchant ID are correct.', 'kashing' );
                    break;
            }
        }

        return '';

    }

}