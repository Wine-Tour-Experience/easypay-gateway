<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Client;

class EPWC_Gateway_Easypay_Settings
{
    /**
     * Throttling Configuration
     */
    private $rate_limit_time = 60; // Time limit in seconds
    private $rate_limit_calls = 5; // Maximum number of allowed calls within the time limit
    private $transient_prefix = 'epwc_rate_limit_'; // Prefix for transients

    /**
     * Constructor: Initializes the plugin by setting up hooks and filters.
     */
    public function __construct()
    {
        // Hook to add settings page
        add_action('admin_menu', array($this, 'add_easypay_settings_page'));

        // Hook to add admin enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_media_scripts'));

        // Save setting fields
        add_action('wp_loaded', array($this, 'save_easypay_settings'));

        // Enqueue Admin styles
        add_action('admin_enqueue_scripts', array($this, 'easypay_admin_enqueue_assets'));

        // Modify submenu
        add_action('admin_menu', array($this, 'modify_easypay_menu_item'));

        // Add payment methods endpoint
        add_action('rest_api_init', array($this, 'add_payment_methods_endpoint'));
    }

    /**
     * Adds a settings page under the WooCommerce menu.
     */
    public function add_easypay_settings_page()
    {
        add_submenu_page(
            'woocommerce',
            esc_html__('easypay', 'easypay-checkout-for-woocommerce'),
            esc_html__('easypay', 'easypay-checkout-for-woocommerce'),
            'manage_options',
            'easypay-settings',
            array($this, 'easypay_settings_page')
        );
    }

    /**
     * Modifies the easypay menu item to include an icon.
     */
    public function modify_easypay_menu_item()
    {
        global $submenu;

        $img_url = esc_attr(plugins_url('../images/icon_easypay.svg', __FILE__));

        if (isset($submenu['woocommerce'])) {
            foreach ($submenu['woocommerce'] as $key => $item) {
                if ($item[2] === 'easypay-settings') {
                    $submenu['woocommerce'][$key][0] .= ' <img src="' . $img_url . '" height="20" style="vertical-align: middle;">';
                    break;
                }
            }
        }
    }

    // Create Setting Form
    public function easypay_settings_page()
    {
        ?>
        <div class="wrap <?php echo get_option('easypay_sandbox') == 1 ? 'easypay-sandbox-line' : ''; ?>"
             id="easypay-general-settings">
            <?php if (get_option('easypay_sandbox') == 1) {
                echo "<span class='easypay-sanbox-notice'>" . esc_html__('SANDBOX', 'easypay-checkout-for-woocommerce') . "</span>";
            } ?>
            <div class="easypay-container-settings">
                <div class="header-easypay">
                    <img src="<?php echo esc_attr(plugins_url('../images/logo.png', __FILE__)); ?>" alt="easypay">
                    <h2><?php echo esc_html__('General Settings', 'easypay-checkout-for-woocommerce'); ?></h2>
                </div>
                <form method="post" action="">
                    <?php wp_nonce_field('easypay_settings_nonce', 'easypay_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <td>
                                <h4><?php echo esc_html__('Connection Key', 'easypay-checkout-for-woocommerce'); ?></h4>
                            </td>
                            <td>
                                <input type="text" class="easypay-input-field" name="easypay_store_key"
                                       value="<?php echo esc_attr(get_option('easypay_store_key')); ?>"/>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <h4><?php echo esc_html__('easypay Sandbox', 'easypay-checkout-for-woocommerce'); ?></h4>
                            </td>
                            <td>
                                <div>
                                    <input type="checkbox" id="easypay-sandbox"
                                           name="easypay_sandbox" <?php if (get_option('easypay_sandbox') == 1) echo 'checked="checked"'; ?> />
                                    <label for="easypay-sandbox"><?php echo esc_html__('Enable easypay sandbox', 'easypay-checkout-for-woocommerce'); ?></label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <h4><?php echo esc_html__('Multiple Checkout - one per payment method', 'easypay-checkout-for-woocommerce'); ?></h4>
                            </td>
                            <td>
                                <div>
                                    <input type="checkbox" id="easypay-multiple-gateway"
                                           name="epwc_multiple_checkout" <?php if (get_option('epwc_multiple_checkout') == 1) echo 'checked="checked"'; ?> />
                                    <label for="easypay-multiple-gateway"><?php echo esc_html__('Enable easypay Multiple Checkout', 'easypay-checkout-for-woocommerce'); ?></label>
                                </div>
                            </td>
                        </tr>
                        <?php
                        $epwc_allowed_payment_methods = get_option('epwc_allowed_payment_methods', []);
                        $epwc_multiple_checkout = get_option('epwc_multiple_checkout');
                        $epwc_should_hide = (empty($epwc_allowed_payment_methods) || $epwc_allowed_payment_methods[0] === "[]") && $epwc_multiple_checkout == 1;
                        ?>
                        <tr id="easypay-force-gateways-section" data-force="<?php echo $epwc_should_hide ? 'show' : 'hide'; ?>" class="<?php echo $epwc_should_hide ? '' : 'easypay-hide'; ?>">
                            <td>
                                <h4><?php echo esc_html(__('Force Multiple Checkout', 'easypay-checkout-for-woocommerce')); ?></h4>
                            </td>
                            <td>
                                <div>
                                    <input type="checkbox" id="easypay-force-multiple-gateway" name="epwc_force_multiple_checkout" <?php if (get_option('epwc_force_multiple_checkout') == 1) echo 'checked="checked"'; ?> />
                                    <label for="easypay-force-multiple-gateway"><?php echo esc_html__('Force all easypay payments', 'easypay-checkout-for-woocommerce'); ?></label>
                                    <p class="easypay-small-desc"><?php echo esc_html__('If the separately contracted payment methods do not appear, choose this option to force easypay payment methods to appear', 'easypay-checkout-for-woocommerce'); ?></p>
                                </div>
                            </td>
                        </tr>
                        <tr id="easypay-logo-section" class="<?php if (get_option('epwc_multiple_checkout') == 1) {
                            echo "easypay-hide";
                        } ?>">
                            <td>
                                <h4><?php echo esc_html__('easypay Logo', 'easypay-checkout-for-woocommerce'); ?></h4>
                            </td>
                            <td>
                                <?php
                                $easypay_logo = esc_attr(get_option("easypay_logo"));

                                if (!empty($easypay_logo)) {
                                    ?>
                                    <div id="logo-preview-container">
                                        <img src="<?php echo esc_url($easypay_logo); ?>">
                                    </div>
                                    <?php
                                } else {
                                    ?>
                                    <div id="logo-preview-container">
                                        <i><?php echo esc_html__("Upload your custom logo", "easypay-checkout-for-woocommerce"); ?></i>
                                    </div>
                                    <?php
                                }
                                ?>
                                <input size="100" type="hidden" readonly name="easypay_logo" id="easypay_logo"
                                       value="<?php echo esc_url($easypay_logo); ?>">
                                <div>
                                    <a class="button-secondary" href="javascript:void(0)" id="upload-image-button">
                                        <?php echo esc_html__('Upload Logo', 'easypay-checkout-for-woocommerce'); ?>
                                    </a>
                                    <?php
                                    if (!empty($easypay_logo)) {
                                        echo "<a class='button-secondary' href='javascript:void(0)' id='clear-image-button'>" .
                                            esc_html__('Remove', 'easypay-checkout-for-woocommerce')
                                            . "</a>";
                                    }
                                    ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <h4><?php echo esc_html__('Checkout Display', 'easypay-checkout-for-woocommerce'); ?></h4>
                            </td>
                            <td>
                                <div>
                                    <select name="epwc_checkout_display">
                                        <option value="inline" <?php if (get_option('epwc_checkout_display') == 'inline') {
                                            echo "selected";
                                        } ?> ><?php echo esc_html__('Inline', 'easypay-checkout-for-woocommerce'); ?></option>
                                        <option value="popup" <?php if (get_option('epwc_checkout_display') == 'popup') {
                                            echo "selected";
                                        } ?> ><?php echo esc_html__('Popup', 'easypay-checkout-for-woocommerce'); ?></option>
                                    </select>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <h4><?php echo esc_html__('Checkout Info Details', 'easypay-checkout-for-woocommerce'); ?></h4>
                            </td>
                            <td>
                                <div>
                                    <select name="epwc_checkout_hide_details">
                                        <option value="true" <?php if (get_option('epwc_checkout_hide_details') == 'true') {
                                            echo "selected";
                                        } ?>><?php echo esc_html__('Hide Details', 'easypay-checkout-for-woocommerce'); ?></option>
                                        <option value="false" <?php if (get_option('epwc_checkout_hide_details') == 'false') {
                                            echo "selected";
                                        } ?>><?php echo esc_html__('Show Details', 'easypay-checkout-for-woocommerce'); ?></option>
                                    </select>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <h4><?php echo esc_html__('Order status before payment is completed', 'easypay-checkout-for-woocommerce'); ?></h4>
                            </td>
                            <td>
                                <div>
                                    <select name="epwc_checkout_order_flow">
                                        <option value="pending" <?php if (get_option('epwc_checkout_order_flow') == 'pending') {
                                            echo "selected";
                                        } ?>>
                                            <?php echo esc_html__('Pending', 'easypay-checkout-for-woocommerce'); ?></option>
                                        <option value="on-hold" <?php if (get_option('epwc_checkout_order_flow') == 'on-hold') {
                                            echo "selected";
                                        } ?>>
                                            <?php echo esc_html__('On Hold', 'easypay-checkout-for-woocommerce'); ?></option>
                                    </select>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <hr>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <h3><?php echo esc_html__('Payments Settings', 'easypay-checkout-for-woocommerce'); ?></h3>
                            </th>
                            <td>
                                <div>
                                    <?php echo esc_html__('Go to the WooCommerce settings to manage the payment methods', 'easypay-checkout-for-woocommerce'); ?>
                                    <br>
                                    <strong>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=checkout')); ?>">
                                            <?php echo esc_html__('Payments Settings', 'easypay-checkout-for-woocommerce'); ?></a>
                                    </strong>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <h3><?php echo esc_html__('easypay Configurations', 'easypay-checkout-for-woocommerce'); ?></h3>
                            </th>
                            <td>
                                <div>
                                    <?php echo esc_html__('Configurations that you must perform on your easypay account.', 'easypay-checkout-for-woocommerce'); ?>
                                    <br>
                                    <strong><?php echo esc_html__('Go to "Webservices" > "URL Configuration"', 'easypay-checkout-for-woocommerce'); ?></strong>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <h3><?php echo esc_html__('Store Connection', 'easypay-checkout-for-woocommerce'); ?></h3>
                            </th>
                            <td>
                                <div>
                                    <p><?php echo esc_html__('Go to the easypay backoffice and link your store.', 'easypay-checkout-for-woocommerce'); ?></p>
                                    <p>
                                        <b><?php echo esc_html__('Note:', 'easypay-checkout-for-woocommerce'); ?></b>
                                        <?php echo esc_html__('If the sandbox checkbox is active, you are establishing a connection within the sandbox platform.', 'easypay-checkout-for-woocommerce'); ?>
                                    </p>
                                    <strong>
                                        <a target="_blank"
                                           href="<?php echo get_option('easypay_sandbox') == 1 ? esc_url(EASYPAY_SANDBOX_URL) : esc_url(EASYPAY_PRODUCTION_URL); ?>">
                                            <?php echo esc_html__('View / Create Connection', 'easypay-checkout-for-woocommerce'); ?></a>
                                        <span class="<?php echo get_option('easypay_sandbox') == 1 ? 'easypay-sandbox-link' : 'easypay-production-link'; ?>">
                                            <?php echo get_option('easypay_sandbox') == 1 ? esc_html__('SANDBOX', 'easypay-checkout-for-woocommerce') : esc_html__('PRODUCTION', 'easypay-checkout-for-woocommerce'); ?>
                                        </span>
                                    </strong>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <hr>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <h3><?php echo esc_html__('Tutorial', 'easypay-checkout-for-woocommerce'); ?></h3>
                            </th>
                            <td>
                                <div>
                                    <?php echo esc_html__('Click the link to view the full tutorial on how to configure the plugin.', 'easypay-checkout-for-woocommerce'); ?>
                                    <br>
                                    <strong>
                                        <a target="_blank" href="<?php echo esc_url(EASYPAY_TUTORIAL_URL); ?>">
                                            <?php echo esc_html__('View Tutorial', 'easypay-checkout-for-woocommerce'); ?></a>
                                    </strong>
                                </div>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(esc_html__("Save Settings", 'easypay-checkout-for-woocommerce')); ?>
                </form>
            </div>
        </div>
        <?php
    }

    // Enqueue media scripts
    public function enqueue_media_scripts()
    {
        if (!did_action('wp_enqueue_media')) {
            wp_enqueue_media();
        }
    }

    /**
     * Saves the easypay settings.
     */
    public function save_easypay_settings()
    {
        if (isset($_POST['easypay_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['easypay_nonce'])), 'easypay_settings_nonce')) {
            if (current_user_can('manage_options')) {
                if (empty($_POST['easypay_store_key'])) {
                    echo '<div class="notice notice-danger is-dismissible">
                         <p>' . esc_html__('Error: Please fill the required field: Connection Key.', 'easypay-checkout-for-woocommerce') . '</p>
                     </div>';
                    return false;
                }

                // Sanitize and save the text field
                if (!empty($_POST['easypay_store_key'])) {
                    $easypay_store_key = sanitize_text_field($_POST['easypay_store_key']);
                    update_option('easypay_store_key', $easypay_store_key);
                }

                // Save the checkbox value
                $easypay_sandbox = isset($_POST['easypay_sandbox']) ? 1 : 0;
                update_option('easypay_sandbox', $easypay_sandbox);

                // Save the checkbox value
                $epwc_multiple_checkout = isset($_POST['epwc_multiple_checkout']) ? 1 : 0;
                update_option('epwc_multiple_checkout', $epwc_multiple_checkout);

                // Save the checkbox value
                $epwc_force_multiple_checkout = isset($_POST['epwc_force_multiple_checkout']) ? 1 : 0;
                update_option('epwc_force_multiple_checkout', $epwc_force_multiple_checkout);

                // Update checkout_display value
                update_option('epwc_checkout_display', sanitize_text_field($_POST['epwc_checkout_display']));

                // Update checkout_details value
                update_option('epwc_checkout_hide_details', sanitize_text_field($_POST['epwc_checkout_hide_details']));

                // Update checkout_order_flow value
                update_option('epwc_checkout_order_flow', sanitize_text_field($_POST['epwc_checkout_order_flow']));

                // Sanitize and save the image URL
                $easypay_logo = esc_url_raw($_POST['easypay_logo']);
                update_option('easypay_logo', $easypay_logo);

                echo '<div class="notice notice-success is-dismissible">
                     <p>' . esc_html__('Your settings have been saved.', 'easypay-checkout-for-woocommerce') . '</p>
                 </div>';
            }
        }
    }

    // Admin CSS styles for the plugin settings section
    public function easypay_admin_enqueue_assets()
    {
        // Add styles and scripts for the settings page
        if (isset($_GET['page']) && $_GET['page'] == 'easypay-settings') {
            wp_enqueue_style('admin-easypay-styles', plugins_url('public/css/admin-style.css', __DIR__), [], '1.0.3');
            wp_enqueue_script('admin-easypay-scripts', plugins_url('public/js/admin-script.js', __DIR__), array('jquery'), '1.0.3', true);
            wp_localize_script('admin-easypay-scripts', 'easypay', array(
                'epTranslationImageTitle' => esc_html__('Payment Methods Image', 'easypay-checkout-for-woocommerce'),
                'epTranslationImageBtn' => esc_html__('Choose Image', 'easypay-checkout-for-woocommerce'),
            ));
        }
    }

    /**
     * Registers the payment methods endpoint in the WooCommerce REST API.
     */
    public function add_payment_methods_endpoint()
    {
        if (defined('REST_REQUEST') && REST_REQUEST) {

            register_rest_route('easypay/v1', '/epwc-plugin-options/', array(
                'methods' => 'POST',
                'callback' => array($this, 'epwc_store_payment_methods'),
                'permission_callback' => array($this, 'check_api_key_permissions'),
            ));
        }
    }

    /**
     * Checks API Key permissions for the REST endpoint.
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function check_api_key_permissions(WP_REST_Request $request)
    {
        if (empty($request->get_param('allowed_payment_methods'))) {
            return new WP_Error('woocommerce_rest_cannot_view', 'allowed_payment_methods field missing', array('status' => 401));
        }

        return true;
    }

    /**
     * Handles storing the payment methods via the REST API.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function epwc_store_payment_methods(WP_REST_Request $request)
    {
        // Throttle requests
        if ($this->rate_limit_exceeded()) {
            error_log('Rate limit exceeded for IP: ' . $_SERVER['REMOTE_ADDR']);
            return new WP_REST_Response(array('error' => 'Too many requests. Please try again later.'), 429);
        }

        $pattern = '/^[a-zA-Z0-9,\s]*$/';

        if (preg_match($pattern, $request->get_param('allowed_payment_methods'))) {
            $epwc_allowed_payment_methods = str_replace(' ', '', $request->get_param('allowed_payment_methods'));
            $epwc_allowed_payment_methods = explode(',', sanitize_text_field($epwc_allowed_payment_methods));
        } else {
            $epwc_allowed_payment_methods = [];
        }

        // Sanitize and store plugin settings
        $epwc_plugin_settings = [];
        if ($request->get_param('plugin_settings')) {
            $epwc_plugin_settings = $request->get_param('plugin_settings');
            if (is_array($epwc_plugin_settings)) {
                $epwc_plugin_settings = array_map('sanitize_text_field', $epwc_plugin_settings);
            }
        }

        // Setting up the request URL
        $request_url = site_url() . '/wp-json/wc/v3/orders';

        $args = array(
            'method' => 'GET',
            'headers' => array(
                'Authorization' => $request->get_header('Authorization'),
                'Content-Type' => 'application/json'
            )
        );

        // Make the API request
        $response = wp_remote_request($request_url, $args);

        if (is_wp_error($response)) {
            // Handle the error and return the message
            return new WP_REST_Response(array('error' => $response->get_error_message()), 500);
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code === 200) {
            // Store the payment methods and plugin settings in the options table
            update_option('epwc_allowed_payment_methods', $epwc_allowed_payment_methods);
            update_option('epwc_plugin_settings', $epwc_plugin_settings);

            return new WP_REST_Response(
                array(
                    'message' => esc_html__('Payment Methods and Plugin Settings Added successfully', 'easypay-checkout-for-woocommerce'),
                    'epwc_allowed_payment_methods' => wp_json_encode(get_option('epwc_allowed_payment_methods')),
                    'epwc_plugin_settings' => wp_json_encode(get_option('epwc_plugin_settings')),
                ),
                200
            );
        } else {
            // Handle non-200 status code
            return new WP_REST_Response(array('error' => 'Unauthorized access.'), $response_code);
        }
    }

    /**
     * Checks if the call limit has been exceeded for the current IP address
     *
     * @return bool
     */
    private function rate_limit_exceeded()
    {
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $transient_key = $this->transient_prefix . hash_hmac('sha256', $ip_address . $user_agent, wp_salt());

        $call_count = get_transient($transient_key);

        if ($call_count === false) {
            // First access or transient expired
            set_transient($transient_key, 1, $this->rate_limit_time);
            return false;
        } elseif ($call_count < $this->rate_limit_calls) {
            // Increment call count and update transient
            set_transient($transient_key, $call_count + 1, $this->rate_limit_time);
            return false;
        } else {
            // Limit exceeded
            return true;
        }
    }
}

new EPWC_Gateway_Easypay_Settings();