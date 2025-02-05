<?php

namespace Automattic\WooCommerce\Blocks\Payments\Integrations;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Dummy Payments Blocks integration
 *
 * @since 1.0.3
 */
final class EasypayPaymentCheckout extends AbstractPaymentMethodType
{
    /**
     * The gateway instance.
     *
     * @var WC_Easypay_EasyPay
     */
    private $gateway;

    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    protected $name = 'easypay_checkout';

    /**
     * Initializes the payment method type.
     */
    public function initialize()
    {
        $this->settings = get_option('epwc_woocommerce_easypay_checkout_settings');
        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateway = !empty($gateways[ $this->name ]) ? $gateways[ $this->name ] : '';
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active()
    {
        return ! empty($this->settings['enabled']) && empty(get_option('epwc_multiple_checkout')) && 'yes' === $this->settings['enabled'];
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles() {
		wp_register_script(
			'wc-easypay-easypay',
			plugins_url('src/index.js', __FILE__),
			[
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
			],
			'1.0.3',
			true
		);
		return [ 'wc-easypay-easypay' ];
	}

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data()
    {
        if (! empty(get_option('easypay_logo'))) {
            $icon = get_option('easypay_logo');
        }else{
            $icon = EASYPAY_URL . 'images/logos/pg-icons.png';
        }
        return [
            'title' => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
            'logo'  => $icon
        ];
    }
}
