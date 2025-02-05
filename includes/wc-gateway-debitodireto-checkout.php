<?php



class EPWC_Gateway_DebitoDireto_Checkout extends WC_Payment_Gateway

{

    /**

     * Gateway's Constructor.

     *

     * One of the Woocommerce required functions

     */

    public function __construct()

    {

        // Class Variables -------------------------------------------------

        // Error

        $this->error = '<div class="error"><p>%s</p></div>';
        $settings_url = esc_url(get_admin_url().'admin.php?page=wc-settings&amp;tab=checkout&amp;section=wc_payment_gateway_debitodireto_checkout');
        $this->ahref = '<a href="'.$settings_url.'">';

        $this->a = '</a>';



        // Inherited Variables----------------------------------------------

        $this->id = 'debitodireto_checkout';



        $this->icon = apply_filters('woocommerce_custom_payment_gateway_icon', plugin_dir_url(__DIR__).'images/logos/dd-wide.png');



        $this->pg_icons  = plugins_url('../images/logos/dd-wide.png', __FILE__);



        $this->has_fields = false;

        $this->method_title = esc_html__('Direct Debit (easypay)', 'easypay-checkout-for-woocommerce');

        $this->method_description = esc_html__('Pay with Direct Debit', 'easypay-checkout-for-woocommerce');

        // -----------------------------------------------------------------



        // Load the form fields (is a function in this class)

        $this->init_form_fields();



        // Woocommerce function

        $this->init_settings();



        // Define user set variables from form_fields function

        $this->enabled = $this->get_option('enabled');

        $this->title = $this->get_option('title');

        $this->description = $this->get_option('description');

        $this->currency = 'EUR';

        $this->method = "debitodireto_checkout";

        $this->store_key = get_option('easypay_store_key');

        $this->checkout_display = get_option('epwc_checkout_display');

        // // Gateway Testing

        $this->sandbox = get_option('easypay_sandbox') === 'yes';



        // Validations

        $this->enabled = $this->check_gateway_enabled() ? 'yes' : 'no';



        // validate admin options

        add_action(

            'woocommerce_update_options_payment_gateways_'.$this->id,

            [$this, 'process_admin_options']

        );



        // Action for receipt page (see function in this class)

        add_action(

            'woocommerce_receipt_'.$this->id,

            [$this, 'receipt_page']

        );

    }



    /**

     * Checks if the gateway should be enabled

     */

    private function check_gateway_enabled(): bool

    {

        if ($this->get_option('enabled') !== 'yes') {

            return false;

        }



        if (! $this->has_valid_currency()) {

            return false;

        }



        return true;

    }



    /**

     * Check if the settings are correct

     */

    public function process_admin_options(): bool

    {

        return parent::process_admin_options();

    }



    /**

     * Start Gateway Settings Form Fields.

     *

     * One of the Woocommerce required functions that generates the var $this->settings

     */

    public function init_form_fields()

    {

        $this->form_fields = [

            'enabled' => [

                'title' => esc_html__('Enable/Disable', 'easypay-checkout-for-woocommerce'),

                'type' => 'checkbox',

                'label' => esc_html__('Enable Direct Debit Payment.', 'easypay-checkout-for-woocommerce'),

                'default' => 'no'

            ],

            'title' => [

                'title' => esc_html__('Title', 'easypay-checkout-for-woocommerce'),

                'type' => 'text',

                'description' => esc_html__('This controls the title which the user sees during checkout.', 'easypay-checkout-for-woocommerce'),

                'default' => esc_html__('Direct Debit', 'easypay-checkout-for-woocommerce'),

                'desc_tip' => true

            ],

            'description' => [

                'title' => esc_html__('Customer Message', 'easypay-checkout-for-woocommerce'),

                'type' => 'textarea',

                'default' => esc_html__('Pay with Direct Debit', 'easypay-checkout-for-woocommerce'),

                'css' => 'max-width: 400px;'

            ],

            

        ];

    }



    /**

     * Admin Panel Options

     */

    public function admin_options()

    {

        ob_start();

        wp_enqueue_media();

        ?>

            <h3><?php echo esc_html__('Direct Debit Settings', 'easypay-checkout-for-woocommerce'); ?></h3>

            <p><?php echo esc_html__('For a simple direct debit from your bank account.', 'easypay-checkout-for-woocommerce'); ?></p>

            <table class="form-table">

                <?php echo $this->generate_settings_html(); ?>

            </table>

        <?php



        echo ob_get_clean();

    }



    /**

     * Output for the order received page.

     */

    public function receipt_page(int $order)

    {

        echo $this->generate_form($order);

    }



    /**

     * Generates the form

     *

     * Request a new reference to API 01BG

     */

    public function generate_form(int $order): string

    {

        // $order is not used



        return '<div id="debitodireto_checkout"></div>';

    }



    /**

     * Process the payment and return the result.

     *

     * One of the Woocommerce required functions

     *

     * @param  integer  $order_id

     */

    function process_payment($order_id): array

    {

        $order = wc_get_order($order_id);


        $new_status = get_option('epwc_checkout_order_flow');
        $status_message = epwc_get_status_message($new_status);
        $order->update_status($new_status, $status_message);


        $orderData = $order->get_data();

        $items = [];



        foreach ($order->get_items() as $item) {

            $item_data = $item->get_data();



            $items[] = [

                "product_name" => $item_data['name'],

                "quantity" => $item_data['quantity'],

                "cost" => $item_data['total'],

            ];

        }



        $easypay_param["order_id"] = $order_id;

        $easypay_param["order_total"] = $orderData['total'];

        $easypay_param["order_items"] = $items;

        $easypay_param["order_billing_email"] = $orderData['billing']['email'];

        $easypay_param["order_billing_first_name"] = $orderData['billing']['first_name'];

        $easypay_param["order_billing_last_name"] = $orderData['billing']['last_name'];

        $easypay_param["order_shipping_first_name"] = $orderData['shipping']['first_name'];

        $easypay_param["order_billing_company"] = $orderData['billing']['company'];

        $easypay_param["order_shipping_company"] = $orderData['shipping']['company'];

        $easypay_param["order_billing_address_1"] = $orderData['billing']['address_1'];

        $easypay_param["order_shipping_address_1"] = $orderData['shipping']['address_1'];

        $easypay_param["order_billing_address_2"] = $orderData['billing']['address_2'];

        $easypay_param["order_shipping_address_2"] = $orderData['shipping']['address_2'];

        $easypay_param["order_billing_city"] = $orderData['billing']['city'];

        $easypay_param["order_shipping_city"] = $orderData['shipping']['city'];

        $easypay_param["order_billing_state"] = $orderData['billing']['state'];

        $easypay_param["order_shipping_state"] = $orderData['shipping']['state'];

        $easypay_param["order_billing_postcode"] = $orderData['billing']['postcode'];

        $easypay_param["order_shipping_postcode"] = $orderData['shipping']['postcode'];

        $easypay_param["order_billing_country"] = $orderData['billing']['country'];

        $easypay_param["order_shipping_country"] = $orderData['shipping']['country'];



        $easypay_param = wp_json_encode($easypay_param);

        $easypay_param = base64_encode($easypay_param);



        // Empty cart

        WC()->cart->empty_cart();



        $is_plain_permalink = !empty(get_option('permalink_structure')) ? get_option('permalink_structure') : '';
        $page = get_page_by_path('wc-easypay');
        $base_url = $page

            ? ( empty($is_plain_permalink) ? site_url('?page_id=' . $page->ID . '&wc-easypay=1') : get_permalink($page))

            : get_permalink($page);

        $redirect_url = add_query_arg('easypay_param', urlencode($easypay_param), $base_url);



        return [

            'result' => 'success',

            'redirect' => $redirect_url,

        ];

    }



    /**

     * Checking if this gateway is enabled and available in the user's country.

     */

    private function has_valid_currency(): bool

    {

        return get_woocommerce_currency() === 'EUR';

    }

}