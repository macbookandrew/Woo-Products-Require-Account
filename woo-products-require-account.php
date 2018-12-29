<?php
/*
 * Plugin Name: Woo Products Require Account
 * Plugin URI: http://andrewrminion.com/2016/03/woo-products-require-account/
 * Description: Adds a checkbox on each WooCommerce product edit page that allows you to require an account to purchase that product, even if guest mode is enabled.
 * Version: 1.0.2
 * Author: Andrew Minion
 * Author URI: https://www.andrewrminion.com
*/

if (!defined('ABSPATH')) {
    exit;
}

// set some globals
global $signup_option_changed;
global $guest_checkout_option_changed;

// display checkbox on product data general tab
add_action( 'woocommerce_product_options_general_product_data', 'wsra_checkbox' );
function wsra_checkbox() {
    global $woocommerce, $post;
    echo '<div class="options_group">';
    woocommerce_wp_checkbox(
        array(
            'id'            => '_wsra_checkbox',
            'wrapper_class' => 'require_account',
            'label'         => __( 'User Account', 'woocommerce' ),
            'description'   => __( 'Require a user account when purchasing this product', 'woocommerce' )
            )
    );

    echo '</div>';
}

// save product data
add_action( 'woocommerce_process_product_meta', 'wsra_save_data' );
function wsra_save_data( $post_id ) {
    $wsra_checkbox = isset( $_POST['_wsra_checkbox'] ) ? 'yes' : 'no';
    update_post_meta( $post_id, '_wsra_checkbox', $wsra_checkbox );
}

// ensure users can register on checkout
add_action( 'woocommerce_before_checkout_form', 'wsra_ensure_checkout_registration_possible', -1 );
function wsra_ensure_checkout_registration_possible( $checkout = '' ) {
    global $signup_option_changed, $guest_checkout_option_changed;
    if ( wsra_check_required_account() && ! is_user_logged_in() ) {

        // ensure users can sign up
        if ( false === $checkout->enable_signup ) {
            $checkout->enable_signup = true;
            $signup_option_changed = true;
        }

        // ensure users are required to register an account
        if ( true === $checkout->enable_guest_checkout ) {
            $checkout->enable_guest_checkout = false;
            $guest_checkout_option_changed = true;

            if ( ! is_user_logged_in() ) {
                $checkout->must_create_account = true;
            }
        }
    }
}

// display account fields as required
add_action( 'woocommerce_checkout_fields', 'wsra_require_checkout_account_fields', 10 );
function wsra_require_checkout_account_fields( $checkout_fields ) {
    if ( wsra_check_required_account() && ! is_user_logged_in() ) {

        $account_fields = array(
            'account_username',
            'account_password',
            'account_password-2',
        );

        foreach ( $account_fields as $account_field ) {
            if ( isset( $checkout_fields['account'][ $account_field ] ) ) {
                $checkout_fields['account'][ $account_field ]['required'] = true;
            }
        }
    }

    return $checkout_fields;
}

// restore the settings after switching them for the checkout form
add_action( 'woocommerce_after_checkout_form', 'wsra_restore_checkout_registration_settings', 100 );
function wsra_restore_checkout_registration_settings( $checkout = '' ) {
    global $signup_option_changed, $guest_checkout_option_changed;

    if ( $signup_option_changed ) {
        $checkout->enable_signup = false;
    }

    if ( $guest_checkout_option_changed ) {
        $checkout->enable_guest_checkout = true;
        if ( ! is_user_logged_in() ) { // Also changed must_create_account
            $checkout->must_create_account = false;
        }
    }
}

// force registration during checkout process
add_action( 'woocommerce_before_checkout_process', 'wsra_force_registration_during_checkout', 10 );
function wsra_force_registration_during_checkout( $woocommerce_params ) {
    if ( wsra_check_required_account() && ! is_user_logged_in() ) {
        $_POST['createaccount'] = 1;
    }
}

// check if account is required
function wsra_check_required_account() {
    $require_account = false;
    global $woocommerce;

    // loop through order items to get Robly sublist IDs
    foreach ( $woocommerce->cart->cart_contents as $item ) {
        if ( 'yes' === get_post_meta( $item['product_id'], '_wsra_checkbox', true ) ) {
            $require_account = true;
        }
    }

    return $require_account;
}
