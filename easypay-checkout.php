<?php
/**
 * Plugin Name: easypay Gateway Checkout for WooCommerce
 * Description: easypay Payment Checkout for WooCommerce - Don't leave for tomorrow what you can receive today
 * Version: 1.1.2
 * Author: easypay
 * Author URI: https://easypay.pt/en
 * Requires at least: 6.0
 * Tested up to: 6.4
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: easypay-checkout-for-woocommerce
 * Domain Path: /languages/
 * @package easypay-checkout-for-woocommerce
 * @category Gateway
 * @author easypay
 */

// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

// Function to remove admin notices from custom admin page
function epwc_remove_custom_admin_notices() {
    // Check if we are on the custom admin page
    if (isset($_GET['page']) && $_GET['page'] == 'easypay-settings') {
        // Remove all admin notices
        remove_all_actions('admin_notices');

    }
}

// initializing plugin constants
require_once plugin_dir_path(__FILE__) . 'constants.php';

// Uninstall
require_once 'core/uninstall.php';
register_deactivation_hook(__FILE__, 'epwc_easypay_deactivation_easypay_checkout');

// Plugin initialization
add_action("init", "epwc_init");
add_action('plugins_loaded', 'epwc_woocommerce_gateway_easypay_checkout_init', 0);
add_shortcode("wc_easypay_form", "epwc_payment_form");
add_action('wp_head', 'epwc_add_noindex_to_wc_easypay_page');
add_action('admin_notices', 'epwc_remove_custom_admin_notices', 1);
/**
 * Initializate the Plugin
 */
function epwc_init()
{
    epwc_create_pages();
}

/**
 * Get the status message for a given status.
 *
 * @param string $status The order status.
 * @return string The status message.
 */
function epwc_get_status_message($status) {
    $status_messages = array(
        'pending'    => esc_html__('Order is pending payment', 'woocommerce'),
        'on-hold'    => esc_html__('Order is on hold', 'woocommerce')
    );

    return $status_messages[$status] ?? esc_html__('Order status updated', 'woocommerce');
}

/**
 * Generate the order data in JSON format
 */
function epwc_get_order_json($orderId = null)
{
    if ($orderId === null) {
        return null;
    }

    $siteUrl = epwc_base_url();

    $storeKey = esc_attr(get_option('easypay_store_key'));

    $order = wc_get_order($orderId);
    $data = [];
    $data['domain'] = get_option('siteurl');
    $data['rest_api'] = rest_url();
    $data['wordpress_address'] = $siteUrl;
    $data['store_key'] = $storeKey;
    $data['kind'] = 'sale';

    $orderData = [];
    $orderData['id'] = (int) $orderId;
    $orderData['amount'] = $order->get_total();

    $orderData['items'] = [];

    $idx = 0;
    foreach ($order->get_items() as $item) {
        $orderData['items'][$idx]['id'] = $item->get_product_id();
        $orderData['items'][$idx]['title'] = $item->get_name();
        $orderData['items'][$idx]['quantity'] = $item->get_quantity();
        $orderData['items'][$idx]['price'] = (float) $item->get_total();

        $idx++;
    }

    $orderData['customer']['name'] = $order->get_billing_first_name().' '.$order->get_billing_last_name();

    $email = $order->get_billing_email();

    if (epwc_containsHtml($email)) {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    }

    if (strpos($email, '&') !== false) {
        $email = epwc_decodeHtmlEntities($email);
    }

    $orderData['customer']['email'] = $email;

    $orderData['customer']['phone'] = $order->get_billing_phone();
    $orderData['customer']['fiscal_number'] = get_user_meta($order->get_user_id(), 'fiscal_number', true);

    if(!empty(get_option("epwc_multiple_checkout") )){
        $orderData['payment_methods'] = [epwc_payment_method($order->get_payment_method())];
    }
    /*else{
        $orderData['payment_methods'] = [];
    }*/

    $data['order'] = $orderData;

    $data['is_test'] = !empty(get_option('easypay_sandbox') ) ? true : false;

    return wp_json_encode($data);
}

//Function to check if the value contains HTML
function epwc_containsHtml($string) {
    return $string != wp_strip_all_tags($string);
}
// Function to convert HTML entities back to readable text
function epwc_decodeHtmlEntities($text) {
    return html_entity_decode($text);
}

function epwc_payment_method($payment_gateway){
    switch ($payment_gateway) {
        case 'creditcard_checkout':
            return 'cc';
        case 'debitodireto_checkout':
            return 'dd';
        case 'multibanco_checkout':
            return 'mb';
        case 'mbway_checkout':
            return 'mbw';
        case 'santandercunsumer_checkout':
            return 'sc';
        case 'universoflex_checkout':
            return 'uf';
        case 'virtualiban_checkout':
            return 'vi';
        case 'applepay_checkout':
            return 'ap';
        default:
            return '';
    }
}
/**
 * WC Gateway Class
 */
function epwc_woocommerce_gateway_easypay_checkout_init()
{
    if (! class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', 'epwc_woocommerce_notice_easypay_checkout');
        return;
    }

    // Of block checkout is active or not
    $wc_blocks_active = false;
    $wc_blocks_active = class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType');
    add_action('woocommerce_blocks_loaded', 'epwc_easypay_blocks_add_payment_methods');

    function epwc_easypay_blocks_add_payment_methods(){
        $epwc_allowed_payment_methods = (empty(get_option('epwc_allowed_payment_methods', array())) && get_option('epwc_force_multiple_checkout') == 1)
            ? EASYPAY_ALLOWED_PAYMENT_METHODS
            : (empty(get_option('epwc_allowed_payment_methods', array())) ? array() : get_option('epwc_allowed_payment_methods', array()) ) ;

        $epwc_file_path_cc = __DIR__ . '/includes/woocommerce-blocks/creditcard/EasypayCreditCardCheckout.php';
        $epwc_file_path_dd = __DIR__ . '/includes/woocommerce-blocks/debitodireto/EasypayDebitoDiretoCheckout.php';
        $epwc_file_path_mb = __DIR__ . '/includes/woocommerce-blocks/multibanco/EasypayMultibancoCheckout.php';
        $epwc_file_path_mbw = __DIR__ . '/includes/woocommerce-blocks/mbway/EasypayMBWayCheckout.php';
        $epwc_file_path_sc = __DIR__ . '/includes/woocommerce-blocks/santanderconsumer/EasypaySantanderConsumerCheckout.php';
        $epwc_file_path_uf = __DIR__ . '/includes/woocommerce-blocks/universoflex/EasypayUniversoFlexCheckout.php';
        $epwc_file_path_vi = __DIR__ . '/includes/woocommerce-blocks/virtualiban/EasypayVirtualIbanCheckout.php';
        $epwc_file_path_ap = __DIR__ . '/includes/woocommerce-blocks/applepay/EasypayApplePayCheckout.php';
        $epwc_file_path_ep = __DIR__ . '/includes/woocommerce-blocks/easypay/EasypayPaymentCheckout.php';

        // EasypayCreditCardCheckout
        if (file_exists($epwc_file_path_cc) && in_array('cc', $epwc_allowed_payment_methods)) {
            require_once $epwc_file_path_cc;

            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new \Automattic\WooCommerce\Blocks\Payments\Integrations\EasypayCreditCardCheckout());
                }
            );
        }

        // EasypayDebitoDiretoCheckout
        if (file_exists($epwc_file_path_dd) && in_array('dd', $epwc_allowed_payment_methods) ) {
            require_once $epwc_file_path_dd;

            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new \Automattic\WooCommerce\Blocks\Payments\Integrations\EasypayDebitoDiretoCheckout());
                }
            );
        }

        // EasypayMultibancoCheckout
        if (file_exists($epwc_file_path_mb) && in_array('mb', $epwc_allowed_payment_methods) ) {
            require_once $epwc_file_path_mb;

            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new \Automattic\WooCommerce\Blocks\Payments\Integrations\EasypayMultibancoCheckout());
                }
            );
        }

        // EasypayMBWayCheckout
        if (file_exists($epwc_file_path_mbw) && in_array('mbw', $epwc_allowed_payment_methods) ) {
            require_once $epwc_file_path_mbw;

            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new \Automattic\WooCommerce\Blocks\Payments\Integrations\EasypayMBWayCheckout());
                }
            );
        }

        // EasypaySantanderConsumerCheckout
        if (file_exists($epwc_file_path_sc) && in_array('sc', $epwc_allowed_payment_methods) ) {
            require_once $epwc_file_path_sc;

            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new \Automattic\WooCommerce\Blocks\Payments\Integrations\EasypaySantanderConsumerCheckout());
                }
            );
        }

        // EasypayUniversoFlexCheckout
        if (file_exists($epwc_file_path_uf) && in_array('uf', $epwc_allowed_payment_methods) ) {
            require_once $epwc_file_path_uf;

            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new \Automattic\WooCommerce\Blocks\Payments\Integrations\EasypayUniversoFlexCheckout());
                }
            );
        }

        // EasypayVirtualIbanCheckout
        if (file_exists($epwc_file_path_vi) && in_array('vi', $epwc_allowed_payment_methods) ) {
            require_once $epwc_file_path_vi;

            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new \Automattic\WooCommerce\Blocks\Payments\Integrations\EasypayVirtualIbanCheckout());
                }
            );
        }


        // EasypayApplePayCheckout
        if (file_exists($epwc_file_path_ap) && in_array('ap', $epwc_allowed_payment_methods) ) {
            require_once $epwc_file_path_ap;

            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new \Automattic\WooCommerce\Blocks\Payments\Integrations\EasypayApplePayCheckout());
                }
            );
        }

        // EasypayPaymentCheckout
        if (file_exists($epwc_file_path_ep)) {
            require_once $epwc_file_path_ep;

            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new \Automattic\WooCommerce\Blocks\Payments\Integrations\EasypayPaymentCheckout());
                }
            );
        }
    }

    // Check if it is HPOS and Blocks compliant
    add_action('before_woocommerce_init', function () {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    });

    /**
     * Add the Easypay Gateway Settings Page
     */
    include realpath(plugin_dir_path(__FILE__)).DIRECTORY_SEPARATOR
        .'includes'.DIRECTORY_SEPARATOR
        .'wc-gateway-easypay-settings.php';

    // Hook to add a "Settings" link next to your plugin's description on the Plugins page
    add_filter('plugin_action_links', 'epwc_add_settings_link_to_description', 10, 2);

    function epwc_add_settings_link_to_description($links, $file) {
        if (plugin_basename(__FILE__) === $file) {
            $links[] = '<a href="admin.php?page=easypay-settings">'. esc_html__('Settings', 'easypay-checkout-for-woocommerce') .'</a>';
            if( get_option('epwc_multiple_checkout') == 1){
                // Allowed Payment Gateways
                $epwc_allowed_payment_methods = (empty(get_option('epwc_allowed_payment_methods', array())) && get_option('epwc_force_multiple_checkout') == 1)
                    ? EASYPAY_ALLOWED_PAYMENT_METHODS
                    : get_option('epwc_allowed_payment_methods', array());

                foreach ($epwc_allowed_payment_methods as $key => $allowed_payment_method) {
                    if(!empty(epwc_allowed_payment_links($allowed_payment_method))){
                        $links[] = epwc_allowed_payment_links($allowed_payment_method);
                    }
                }
            }else{
                $links[] = '<a href="admin.php?page=wc-settings&tab=checkout&section=easypay_checkout">'. esc_html__('Easypay Payment', 'easypay-checkout-for-woocommerce') .'</a>';
            }
        }

        return $links;
    }

    // Localisation
    load_plugin_textdomain('easypay-checkout-for-woocommerce', false, dirname(plugin_basename(__FILE__)).'/languages');
}
// Return Allowed Gateway links
function epwc_allowed_payment_links($allowed_payment_method){
    switch ($allowed_payment_method) {
        case 'cc':
            return '<a href="admin.php?page=wc-settings&tab=checkout&section=creditcard_checkout">'. esc_html__('Visa&Mastercard Card', 'easypay-checkout-for-woocommerce') .'</a>';
        case 'dd':
            return '<a href="admin.php?page=wc-settings&tab=checkout&section=debitodireto_checkout">'. esc_html__('Direct Debit', 'easypay-checkout-for-woocommerce') .'</a>';
        case 'mb':
            return '<a href="admin.php?page=wc-settings&tab=checkout&section=multibanco_checkout">'. esc_html__('Multibanco Reference', 'easypay-checkout-for-woocommerce') .'</a>';
        case 'mbw':
            return '<a href="admin.php?page=wc-settings&tab=checkout&section=mbway_checkout">'. esc_html__('MB Way', 'easypay-checkout-for-woocommerce') .'</a>';
        case 'sc':
            return '<a href="admin.php?page=wc-settings&tab=checkout&section=santanderconsumer_checkout">'. esc_html__('Santander Consumer Finance', 'easypay-checkout-for-woocommerce') .'</a>';
        case 'uf':
            return '<a href="admin.php?page=wc-settings&tab=checkout&section=universoflex_checkout">'. esc_html__('Universo Flex', 'easypay-checkout-for-woocommerce') .'</a>';
        case 'vi':
            return '<a href="admin.php?page=wc-settings&tab=checkout&section=virtualiban_checkout">'. esc_html__('Virtual IBAN (Bank Transfer)', 'easypay-checkout-for-woocommerce') .'</a>';
        case 'ap':
            return '<a href="admin.php?page=wc-settings&tab=checkout&section=applepay_checkout">'. esc_html__('Apple Pay', 'easypay-checkout-for-woocommerce') .'</a>';
        default:
            return '';
    }
}
/**
 * Add the Easypay Gateway to WooCommerce
 */
function epwc_woocommerce_add_gateway_easypay_checkout(array $methods): array
{
    // include Credit Card Gateway
    if (! class_exists('EPWC_Gateway_CreditCard_Checkout')) {
        include realpath(plugin_dir_path(__FILE__)).DIRECTORY_SEPARATOR
            .'includes'.DIRECTORY_SEPARATOR
            .'wc-gateway-creditcard-checkout.php';
    }

    // include Debito Direto Gateway
    if (! class_exists('EPWC_Gateway_DebitoDireto_Checkout')) {
        include realpath(plugin_dir_path(__FILE__)).DIRECTORY_SEPARATOR
            .'includes'.DIRECTORY_SEPARATOR
            .'wc-gateway-debitodireto-checkout.php';
    }

    // include Multibanco Gateway
    if (! class_exists('EPWC_Gateway_Multibanco_Checkout')) {
        include realpath(plugin_dir_path(__FILE__)).DIRECTORY_SEPARATOR
            .'includes'.DIRECTORY_SEPARATOR
            .'wc-gateway-multibanco-checkout.php';
    }

    // include MBWAY Gateway
    if (! class_exists('EPWC_Gateway_Mbway_Checkout')) {
        include realpath(plugin_dir_path(__FILE__)).DIRECTORY_SEPARATOR
            .'includes'.DIRECTORY_SEPARATOR
            .'wc-gateway-mbway-checkout.php';
    }

    // include Santander Consumer Gateway
    if (! class_exists('EPWC_Gateway_SantanderConsumer_Checkout')) {
        include realpath(plugin_dir_path(__FILE__)).DIRECTORY_SEPARATOR
            .'includes'.DIRECTORY_SEPARATOR
            .'wc-gateway-santanderconsumer-checkout.php';
    }

    // include Universo Flex Gateway
    if (! class_exists('EPWC_Gateway_UniversoFlex_Checkout')) {
        include realpath(plugin_dir_path(__FILE__)).DIRECTORY_SEPARATOR
            .'includes'.DIRECTORY_SEPARATOR
            .'wc-gateway-universoflex-checkout.php';
    }

    // include Virtual IBAN Gateway
    if (! class_exists('EPWC_Gateway_VirtualIban_Checkout')) {
        include realpath(plugin_dir_path(__FILE__)).DIRECTORY_SEPARATOR
            .'includes'.DIRECTORY_SEPARATOR
            .'wc-gateway-virtualiban-checkout.php';
    }

    // include Apple Pay Gateway
    if (! class_exists('EPWC_Gateway_ApplePay_Checkout')) {
        include realpath(plugin_dir_path(__FILE__)).DIRECTORY_SEPARATOR
            .'includes'.DIRECTORY_SEPARATOR
            .'wc-gateway-applepay-checkout.php';
    }

    // include Easypay Gateway
    if (! class_exists('EPWC_Gateway_Easypay_Checkout')) {
        include realpath(plugin_dir_path(__FILE__)).DIRECTORY_SEPARATOR
            .'includes'.DIRECTORY_SEPARATOR
            .'wc-gateway-easypay-checkout.php';
    }

    if( get_option('epwc_multiple_checkout') == 1){
        // Allowed Payment Gateways
        $epwc_allowed_payment_methods = (empty(get_option('epwc_allowed_payment_methods', array())) && get_option('epwc_force_multiple_checkout') == 1)
            ? EASYPAY_ALLOWED_PAYMENT_METHODS
            : get_option('epwc_allowed_payment_methods', array());

        if (!empty($epwc_allowed_payment_methods)) {
            foreach ($epwc_allowed_payment_methods as $key => $allowed_payment_method) {
                if (!empty(epwc_allowed_payment_gateways($allowed_payment_method))) {
                    $methods[] = epwc_allowed_payment_gateways($allowed_payment_method);
                }
            }
        }
    }
    else{
        $methods[] = 'EPWC_Gateway_Easypay_Checkout';
    }

    return $methods;
}
add_filter('woocommerce_payment_gateways', 'epwc_woocommerce_add_gateway_easypay_checkout');

// Return Allowed Gateway classess
function epwc_allowed_payment_gateways($allowed_payment_method){
    switch ($allowed_payment_method) {
        case 'cc':
            return 'EPWC_Gateway_CreditCard_Checkout';
        case 'dd':
            return 'EPWC_Gateway_DebitoDireto_Checkout';
        case 'mb':
            return 'EPWC_Gateway_Multibanco_Checkout';
        case 'mbw':
            return 'EPWC_Gateway_Mbway_Checkout';
        case 'sc':
            return 'EPWC_Gateway_SantanderConsumer_Checkout';
        case 'uf':
            return 'EPWC_Gateway_UniversoFlex_Checkout';
        case 'vi':
            return 'EPWC_Gateway_VirtualIban_Checkout';
        case 'ap':
            return 'EPWC_Gateway_ApplePay_Checkout';
        default:
            return '';
    }
}

/**
 * WooCommerce Gateway Fallback Notice
 *
 * Request to user that Easypay Plugin needs the last version of WooCommerce
 */
function epwc_woocommerce_notice_easypay_checkout()
{
    echo '<div class="error"><p>'.esc_html__('WooCommerce easypay Gateway Checkout depends on the last version of WooCommerce to work!', 'easypay-checkout-for-woocommerce').'</p></div>';
}

/**
 * Gets site's base URL (ex: https://easypay-wocommerce.store)
 */
function epwc_base_url(): string
{
    return rtrim(get_option('siteurl'), '/');
}

/**
 * Creates the WP Post
 */
function epwc_create_pages()
{
    $page_slug = 'wc-easypay';
    $page_query = get_page_by_path( $page_slug , OBJECT );

    $post = [
        'post_author' => 1,
        'post_content' => "[wc_easypay_form]",
        'post_name' => "wc-easypay",
        'post_status' => "publish",
        'post_title' => "easypay",
        'post_type' => 'page',
    ];

    if (!get_page_by_path( $page_slug , OBJECT )) {
        wp_insert_post($post, false);

    } else {

        if ($page_query->post_content !== "[wc_easypay_form]") {
            $post['ID'] = $page_query->ID;
            $post['post_content'] = "[wc_easypay_form]";
            wp_insert_post($post, false);
        }
    }
}

function epwc_add_noindex_to_wc_easypay_page() {
    if (is_page('wc-easypay')) {
        echo '<meta name="robots" content="noindex, nofollow" />' . "\n";
    }
}

/**
 * Includes the payment form HTML/CSS/JS
 */
function epwc_payment_form()
{
    include(EASYPAY_DIRECTORY.'/public/easypay-form.php');
    return ob_get_clean();
}

/**
 * Validates the array value
 */
function epwc_validate_array_value(string $key, array $array, string $default = null)
{
    return isset($array[$key])
        ? ($array[$key] ?? $default)
        : $default;
}

/**
 * Gets the API url (it depends on the sandbox status)
 */
function epwc_get_api_url(): string
{
    $sandbox = !empty(get_option('easypay_sandbox') ? true : false);

    return $sandbox
        ? EASYPAY_SANDBOX_URL
        : EASYPAY_PRODUCTION_URL;
}


/**
 * Get easypay checkout settings
 */
function epwc_get_ep_checkout_settings() {
    $storedSettings = (array) get_option('epwc_plugin_settings');

    unset($storedSettings['fontFamilyCustom'], $storedSettings['language'], $storedSettings['hideDetails']);
    ksort($storedSettings);

    $defaultSettings = array(
        'logoUrl' => null,
        'backgroundColor' => '#FFFFFF',
        'accentColor' => '#0D71F9',
        'errorColor' => '#FF151F',
        'inputBackgroundColor' => 'transparent',
        'inputBorderColor' => '#DADADA',
        'inputBorderRadius' => 50,
        'inputFloatingLabel' => true,
        'buttonBackgroundColor' => '#0D71F9',
        'buttonBorderRadius' => 50,
        'buttonBoxShadow' => false,
        'fontFamily' => 'Overpass',
        'baseFontSize' => 10
    );
    ksort($defaultSettings);

    $isDefaultSettings = array_diff($storedSettings, $defaultSettings) === array();
    $ep_checkout_settings = $isDefaultSettings ? null : $storedSettings;

    if (!isset($ep_checkout_settings['logoUrl']) || $ep_checkout_settings['logoUrl'] === null) {
        $ep_checkout_settings['logoUrl'] = '';
    }

    if (!empty($ep_checkout_settings['fontFamilyCustom'])) {
        $ep_checkout_settings['fontFamily'] = $ep_checkout_settings['fontFamilyCustom'];
        unset($ep_checkout_settings['fontFamilyCustom']);
    }

    unset($ep_checkout_settings['language']);

    epwc_convert_keys_to_type($ep_checkout_settings, array('baseFontSize', 'buttonBorderRadius', 'inputBorderRadius'), 'int');
    epwc_convert_keys_to_type($ep_checkout_settings, array('inputFloatingLabel', 'buttonBoxShadow'), 'bool');

    return $ep_checkout_settings;
}

/**
 * Convert keys to a specific type
 */
function epwc_convert_keys_to_type(&$settings, $keys, $type) {
    foreach ($keys as $key) {
        if (isset($settings[$key])) {
            switch ($type) {
                case 'int':
                    $settings[$key] = (int) $settings[$key];
                    break;
                case 'bool':
                    $settings[$key] = filter_var($settings[$key], FILTER_VALIDATE_BOOLEAN);
                    break;
            }
        }
    }
}


/**
 * Prints the var and exits
 */
function epwc_dd($var)
{
    echo "<pre>";
    print_r($var);
    exit;
}

/**
 * Add easypay gateway title for admin setting
 * @param $title
 * @param $payment_gateway
 */
function epwc_easypay_gateway_title_for_admin_setting($title, $payment_gateway) {
    $current_wp_setting_tab = '';$img='';
    $easypay_gateway_array = array('multibanco_checkout','debitodireto_checkout','mbway_checkout','creditcard_checkout','santanderconsumer_checkout','universoflex_checkout','virtualiban_checkout','applepay_checkout');

    if (isset($_GET['page']) && $_GET['page'] === 'wc-settings' && isset($_GET['tab'])) {
        $current_wp_setting_tab = sanitize_text_field($_GET['tab']);
    }

    if (in_array($payment_gateway, $easypay_gateway_array) && $current_wp_setting_tab === 'checkout') {
        $payment_gateways = new WC_Payment_Gateways();
        $easypay_gateway = $payment_gateways->payment_gateways()[$payment_gateway];
        $icon_url = $easypay_gateway->pg_icons;
        $img = $easypay_gateway->title.'<img height="24" src="'. $icon_url.'" alt="'. esc_attr__('easypay Supported Payment Methods', 'easypay-checkout-for-woocommerce') .'">';
        return  $img ;
    }elseif ($payment_gateway == 'easypay_checkout' && $current_wp_setting_tab === 'checkout') {
        $payment_gateways = new WC_Payment_Gateways();
        $easypay_gateway = $payment_gateways->payment_gateways()[$payment_gateway];
        $icon_url = $easypay_gateway->icon;
        $pg_icons = $easypay_gateway->pg_icons;
        if(empty(get_option('easypay_logo'))){
            $img = $easypay_gateway->title;
        }

        return  $easypay_gateway->title.'<img height="24" src="'. $icon_url.'" alt="'. esc_attr__('easypay Supported Payment Methods', 'easypay-checkout-for-woocommerce') .'">' ;
    }
    return $title;
}
add_filter('woocommerce_gateway_title', 'epwc_easypay_gateway_title_for_admin_setting', 10, 2);

/**
 * Emties the cart url for easypay
 */
function epwc_easypay_woocommerce_clear_cart_url() {
    if ( isset( $_GET['easypay-empty-cart'] ) ) {
        WC()->cart->empty_cart();
    }
}

add_action( 'init', 'epwc_easypay_woocommerce_clear_cart_url' );

/**
 * Enqueue the CSS for the spinner
 */
function epwc_easypay_enqueue_assets() {
    $request_url = sanitize_url($_SERVER['REQUEST_URI']);
    $style_url = plugins_url('public/css/style.css', __FILE__);

    wp_enqueue_style('custom-easypay-styles', $style_url, [],'1.0');

    // Check if the site URL contains "wc-easypay"
    if (strpos($request_url, 'wc-easypay') !== false)
    {
        $script_url = plugins_url('public/js/script.js', __FILE__);
        // Enqueue your JavaScript file that needs the nonce
        wp_enqueue_script('custom-easypay-script', $script_url, [], '1.0', true);

        $easypay_param = array();
        if (isset($_GET['easypay_param'])) {
            $easypay_param = sanitize_text_field($_GET['easypay_param']);
            $easypay_param = base64_decode($easypay_param);
            $easypay_param = json_decode($easypay_param, true);
        }
        $order_id = epwc_validate_array_value('order_id', $easypay_param);
        $order = wc_get_order($order_id);

        $epEasypaySuccessUrl = $order ? $order->get_checkout_order_received_url() : site_url();
        /*$epEasypaySuccessUrl =  is_user_logged_in() ? wc_get_account_endpoint_url('orders'): site_url();*/
        $ep_fail_url = site_url("?easypay_response=fail&order_id={$order_id}");
        $ep_order_json = epwc_get_order_json($order_id);

        //easypay settings
        $ep_checkout_settings = epwc_get_ep_checkout_settings();

        $ep_translations = array(
            'errorOccurred' => esc_html__('An error has occurred. Please, ', 'easypay-checkout-for-woocommerce'),
            'tryAgain' => esc_html__('try again', 'easypay-checkout-for-woocommerce'),
            'viewOrders' => esc_html__('Check your orders.', 'easypay-checkout-for-woocommerce'),
            'orderPaid' => esc_html__('The order has already been paid.', 'easypay-checkout-for-woocommerce'),
        );

        // Pass nonce to JavaScript
        wp_localize_script('custom-easypay-script', 'easypay', array(
            'nonce' => wp_create_nonce('wc_easypay_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'epEasypaySuccessUrl' => $epEasypaySuccessUrl,
            'epCheckoutDisplay' => get_option('epwc_checkout_display'),
            'epCheckoutHideDetails' => get_option('epwc_checkout_hide_details'),
            'epEasypaySuccessApi' => rest_url(EASYPAY_SUCCESS_API),
            'epSandbox' => !empty(get_option('easypay_sandbox') ) ? true : false,
            'epApiUrl' => epwc_get_api_url(),
            'epEasypayFailUrl'  => $ep_fail_url,
            'epOrderJson'  => wp_json_encode($ep_order_json),
            'epCheckoutSettings'  => wp_json_encode($ep_checkout_settings),
            'epLanguage'  => (get_locale() == 'pt_PT') ? 'pt_PT' : 'en',
            'epTranslations'  => $ep_translations
        ));
    }
}
add_action('wp_enqueue_scripts', 'epwc_easypay_enqueue_assets');

function epwc_clear_cart_after_payment($order_id)
{
    // Check if the order ID is valid
    if (!$order_id) {
        return;
    }
    epwc_clear_cart($order_id);
}

// Clear cart after successful payment
function epwc_clear_cart($order_id) {
    // Check if the order ID is valid
    if (!$order_id) {
        return;
    }

    // Get the order object
    $order = wc_get_order($order_id);

    if (WC()->cart) {
        WC()->cart->empty_cart();
    }
}
add_action('woocommerce_payment_complete', 'epwc_clear_cart_after_payment');

/**
 * Add the API to clear the cart
 */
function epwc_clear_cart_api()
{
    register_rest_route('easypay-checkout/v1', '/success', array(
        'methods' => 'POST',
        'callback' => 'epwc_post_payment_success',
        'permission_callback' => '__return_true',
    ));
}

/**
 * Clear the cart after payment
 */
function epwc_post_payment_success($request) {
    $nonce_action = 'wc_easypay_nonce';
    $nonce = sanitize_text_field($_GET['_wpnonce']);

    if (!empty($nonce) && wp_verify_nonce($nonce, $nonce_action)) {
        $order_id = $request->get_param('order_id');
        epwc_clear_cart($order_id);
        return new WP_REST_Response('success', 200);
    } else {
        return new WP_REST_Response('error', 403);
    }
}

add_action('rest_api_init', 'epwc_clear_cart_api');

// Callback function to clear the cart
function epwc_clear_woocommerce_cart() {
    if (defined('DOING_AJAX') && DOING_AJAX) {
        WC()->cart->empty_cart();
        wp_send_json_success();
    } else {
        wp_send_json_error();
    }
}

// Hook for logged-in users
add_action('wp_ajax_clear_cart', 'epwc_clear_woocommerce_cart');

// Hook for non-logged-in users
add_action('wp_ajax_nopriv_clear_cart', 'epwc_clear_woocommerce_cart');
add_action('woocommerce_payment_complete', 'epwc_clear_cart_after_payment');