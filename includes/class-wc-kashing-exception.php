<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WC_Kashing_Exception extends Exception {

    /**
     * The non admin message of the exception.
     *
    * @var string
     */

    public $non_admin_message;

    /**
     * Exception constructor.
     *
     * @param string $error_message Main error message
     * @param string $non_admin_message An error message for non admin users
     */

    public function __construct( $error_message = '', $non_admin_message = '' ) {
        if ( $non_admin_message === true ) { // A generic error message for public.
            $this->non_admin_message = __( 'En error occured with the Kashing payment. Please contact the site administrator.', 'kashing-wc' );
        } else {
            $this->non_admin_message = $non_admin_message;
        }
        parent::__construct( $error_message );
    }

    /**
     * Returns a public message (an error for non-admin users).
     *
     * @return string
     */

    public function getPublicMessage() {
        return $this->non_admin_message;
    }

}