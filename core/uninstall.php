<?php

if (!defined('ABSPATH')) {

    exit;

}
// Soft deactivation

function epwc_easypay_deactivation_easypay_checkout()

{

    $option_name = 'epwc_woocommerce_easypay_checkout_settings';



    delete_option($option_name);



    delete_site_option($option_name);



}