<?php

namespace Automattic\WooCommerce\Blocks\Payments\Integrations;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class EasypayVirtualIbanCheckout extends AbstractPaymentMethodType
{
   
    private $gateway;

    protected $name = 'virtualiban_checkout';

    public function initialize()
    {
        $this->settings = get_option('woocommerce_virtualiban_checkout_settings');
        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateway = !empty($gateways[ $this->name ]) ? $gateways[ $this->name ] : '';
    }

    public function is_active()
    {
        return ! empty($this->settings['enabled']) && get_option('epwc_multiple_checkout') == 1 && 'yes' === $this->settings['enabled'];
    }

    public function get_payment_method_script_handles() {
		wp_register_script(
			'wc-easypay-virtualiban',
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
		return [ 'wc-easypay-virtualiban' ];
	}

    public function get_payment_method_data()
    {
        return [
            'title' => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
            'logo'  =>  EASYPAY_URL . 'images/logos/tb-wide.png'
        ];
    }
}
