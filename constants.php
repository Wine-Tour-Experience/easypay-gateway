<?php
// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

// Plugin constants

define("EASYPAY_URL", plugin_dir_url(__FILE__));

define("EASYPAY_DIRECTORY", __DIR__);

define("EASYPAY_SUCCESS_API", "wp-json/easypay-checkout/v1/success");

define("EASYPAY_SANDBOX_URL","https://e-commerce.test.easypay.pt");

define("EASYPAY_PRODUCTION_URL","https://e-commerce.easypay.pt");

define("EASYPAY_TUTORIAL_URL","https://www.easypay.pt/manual-woocommerce");

define("EASYPAY_ALLOWED_PAYMENT_METHODS", ["cc", "dd", "mb", "mbw", "sc", "uf", "vi", "ap"]);

?>