<?php
/**
 * Plugin Name: WC PP V1
 * Description: Custom Payment Gateway for WooCommerce
 * Version: 1.0.0
 * Author: FaroukDev
 * Text Domain: woocommerce-custom-payment
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'init_custom_payment_gateway');

function init_custom_payment_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Custom_Payment_Gateway extends WC_Payment_Gateway {

        public function __construct() {
            $this->id                 = 'custom_payment_gateway';
            $this->has_fields         = true;
            $this->method_title       = 'Custom Payment Gateway';
            $this->method_description = 'Custom Payment Gateway for WooCommerce';

            // Initialize form fields and settings
            $this->init_form_fields();
            $this->init_settings();

            // Get settings values
            $this->title         = $this->get_option('title');
            $this->description   = $this->get_option('description');
            $this->enabled       = $this->get_option('enabled');
            $this->external_url  = $this->get_option('external_url');
            $this->secret_key    = $this->get_option('secret_key');
            $this->payment_image = $this->get_option('payment_image');
            $this->instructions  = $this->get_option('instructions');

            // Hook for saving settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            // Hook for the thank you page
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
            // Hook for handling the response from the payment gateway
            add_action('woocommerce_api_wc_custom_payment_response', array($this, 'process_response'));

            // Register the custom payment endpoint
            add_action('init', 'register_custom_payment_endpoint');


        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable Custom Payment Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'Custom Payment Gateway',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'Payment method description that the customer will see on your checkout.',
                    'default'     => 'Pay using our custom payment gateway.',
                ),
                'external_url' => array(
                    'title'       => 'External URL',
                    'type'        => 'text',
                    'description' => 'The URL to send order data to.',
                ),
                'secret_key' => array(
                    'title'       => 'Secret Key',
                    'type'        => 'text',
                    'description' => 'Secret key for signing the order data.',
                ),
                'payment_image' => array(
                    'title'       => 'Payment Method Image',
                    'type'        => 'text',
                    'description' => 'URL of the image to display for the payment method.',
                    'desc_tip'    => true,
                ),
                'instructions' => array(
                    'title'       => 'Instructions',
                    'type'        => 'textarea',
                    'description' => 'Instructions that will be added to the thank you page and emails.',
                    'default'     => '',
                    'desc_tip'    => true,
                ),
            );
        }

        function register_custom_payment_endpoint() {
            add_rewrite_rule('^process_custom_payment_response/?', 'index.php?wc-api=wc_custom_payment_response', 'top');
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                wc_add_notice('Order retrieval error: Invalid order ID.', 'error');
                return array('result' => 'failure');
            }
        
            // Update order status to pending payment
            $order->update_status('pending-payment', 'Awaiting payment via Custom Payment Gateway');
        
            // Redirect to the external payment gateway
            return array(
                'result'   => 'success',
                'redirect' => $this->process_external_redirect($order),
            );
        }

        public function process_external_redirect($order) {
            $order_data = $order->get_data();
        
            $order_info = array(
                'order_id'       => $order_data['id'],
                'order_key'      => $order_data['order_key'],
                'status'         => $order_data['status'],
                'total'          => $order_data['total'],
                'currency'       => $order_data['currency'],
                'billing_email'  => $order_data['billing']['email'],
                'billing_phone'  => $order_data['billing']['phone'],
                'billing_name'   => $order_data['billing']['first_name'] . ' ' . $order_data['billing']['last_name'],
                'billing_address' => $order_data['billing']['address_1'] . ' ' . $order_data['billing']['address_2'],
                'billing_city'   => $order_data['billing']['city'],
                'billing_state'  => $order_data['billing']['state'],
                'billing_postcode' => $order_data['billing']['postcode'],
                'billing_country' => $order_data['billing']['country'],
                // Payment processing URL (whether paid or not)
                'return_url'      => esc_url($this->get_custom_return_url()),
            );
        
            // Convert order info to JSON and create a signature
            $order_info_64 = base64_encode(json_encode($order_info, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $signature = hash_hmac('sha256', $order_info_64, $this->secret_key);
        
            // Construct the URL with query parameters
            $query_params = http_build_query(array(
                'data' => $order_info_64,
                'signature'  => $signature,
            ));
        
            $redirect_url = $this->external_url . '?' . $query_params;
        
            // Return the constructed redirect URL
            return $redirect_url;
        }

        public function process_response() {
            // Log the data for debugging
            if (!isset($_GET['order_info']) || !isset($_GET['signature'])) {
                error_log('Missing order_info or signature.');
                return;
            }
        
            $decoded_order_info = base64_decode($_GET['order_info']);
            $order_info = json_decode(stripslashes($decoded_order_info), true);
            $signature = $_GET['signature'];
        
            if (!$order_info || !$signature) {
                error_log('Failed to decode order_info or signature.');
                wp_die('Invalid data.', 'Invalid Data', array('response' => 400));
            }
        
            // Verify the signature
            $calculated_signature = hash_hmac('sha256', $_GET['order_info'], $this->secret_key);
            if ($calculated_signature !== $signature) {
                error_log('Signature verification failed.');
                wp_die('Signature verification failed.', 'Signature Verification Error', array('response' => 400));
            }
        
            // Get the order ID from the order info
            $order_id = $order_info['order_id'];
            $order = wc_get_order($order_id);
        
            if (!$order) {
                error_log('Order not found.');
                wp_die('Order not found.', 'Order Not Found', array('response' => 404));
            }

            if($order_info['status'] !== 'paid') {
                error_log('Payment not completed.');
                wp_die('Payment not completed.', 'Payment Not Completed', array('response' => 400));
                // wait and redirect to checkout page
                sleep(5);
                wp_redirect(home_url('/checkout'));                     
            }
        
            // Update the order status based on the payment response
            $order->payment_complete();
            $order->reduce_order_stock();
            $order->update_status('processing', 'Payment completed successfully via Custom PP Gateway');
        
            // Redirect to the order received page
            wp_redirect($this->get_return_url($order));
            exit;
        }

        public function thankyou_page($order_id) {
            if ($this->instructions) {
                echo wpautop(wptexturize($this->instructions));
            }
        }

        public function get_custom_return_url() {
            return add_query_arg('wc-api', 'wc_custom_payment_response', home_url('/'));
        }

        public function payment_fields() {
            if ($this->description) {
                echo wpautop(wp_kses_post($this->description));
            }

            if ($this->payment_image) {
                echo '<img src="' . esc_url($this->payment_image) . '" alt="' . esc_attr($this->title) . '">';
            }
        }
    }

    function add_custom_payment_gateway($methods) {
        $methods[] = 'WC_Custom_Payment_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_custom_payment_gateway');
}
