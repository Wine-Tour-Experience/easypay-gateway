<?php
// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

$easypay_param = array();

if (isset($_GET['easypay_param'])) {
    $easypay_param = sanitize_text_field($_GET['easypay_param']);
    $easypay_param = base64_decode($easypay_param);
    $easypay_param = json_decode($easypay_param, true);
}
$orderId = epwc_validate_array_value('order_id', $easypay_param);

if (isset($_GET['easypay_response']) && $_GET['order_id']) {
    clear_cart($orderId);
}

if (isset($_GET['clear_cart'])) {
    WC()->cart->empty_cart();
}

if (empty($orderId)) {
    return '<h1>'.esc_html__('Invalid order details', 'easypay-checkout-for-woocommerce').'</h1>';
}

$checkoutDisplay = get_option('epwc_checkout_display');

?>

<div id="easypay-message"></div>

<?php if ($checkoutDisplay === 'popup') { ?>
    <div>
        <div class="ep-spinner-wrapper" style="margin:auto;display: flex; justify-content: center; align-items: center; height: 100px; width: 100%;">
            <svg class="ep-spinner" viewBox="0 0 50 50" style="width: 100px;">
                <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
            </svg>
        </div>
        <div id="button-wrapper-easypay" style="display: none;">
            <button id="easypay-checkout" class="btn btn-primary"><?php echo esc_html__('Pay Now', 'easypay-checkout-for-woocommerce'); ?></button>
        </div>
    </div>
<?php } else { ?>
    <div id="easypay-checkout">
        <div class="ep-spinner-wrapper" style="margin:auto;display: flex; justify-content: center; align-items: center; height: 500px; width: 100%; max-width: 400px;">
            <svg class="ep-spinner" viewBox="0 0 50 50" style="width: 100px;">
                <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
            </svg>
        </div>
    </div>
<?php } ?>