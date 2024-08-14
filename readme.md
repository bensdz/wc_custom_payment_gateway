# WC Custom Payment Gateway

**WC Custom Payment Gateway** is a custom WooCommerce payment gateway plugin that allows users to redirect to an external payment page for payment processing. This plugin is useful for integrating third-party payment solutions that require redirection from your WooCommerce store.

## Features

- Redirect customers to an external payment gateway.
- Secure the payment data using HMAC signature.
- Automatic order status update after successful payment.
- Customizable payment instructions and images.
- Admin settings to configure the external payment URL and secret key.

## Installation

1. **Download the Plugin**

   - Clone the repository or download the ZIP file from the GitHub repository.

2. **Upload to WordPress**

   - Upload the plugin files to the `/wp-content/plugins/` directory or upload the ZIP file via the WordPress plugin manager.

3. **Activate the Plugin**

   - In your WordPress dashboard, navigate to `Plugins` > `Installed Plugins` and activate the "WC Custom Payment Gateway" plugin.

4. **Configure the Plugin**
   - Go to `WooCommerce` > `Settings` > `Payments` tab.
   - Select "Custom Payment Gateway" and configure the following settings:
     - **Title**: The title of the payment method displayed during checkout.
     - **Description**: A short description of the payment method.
     - **External URL**: The URL to which the payment data will be sent.
     - **Secret Key**: A secret key used for signing the payment data.
     - **Payment Method Image**: An image to be displayed alongside the payment method.
     - **Instructions**: Instructions that will be shown on the thank you page after payment.

## Usage

1. **Checkout Process**
   - When a customer selects the custom payment gateway during checkout, they will be redirected to the external payment page defined in the settings.
   - The plugin will securely send order data to the external URL with an HMAC signature.
2. **Handling Payment Response**
   - The external payment gateway should redirect the customer back to the WordPress site with the signed order data.
   - The plugin will verify the signature, update the order status, and redirect the customer to the WooCommerce order received page.

## Development

### Hooks & Filters

- **Hooks:**
  - `woocommerce_update_options_payment_gateways_{id}`: Saves the payment gateway settings.
  - `woocommerce_thankyou_{id}`: Displays additional instructions on the thank you page.
  - `init`: Processes the payment response from the external gateway.

### Customizing the Plugin

- You can extend or modify the plugin by editing the `wc-custom-payment-gateway.php` file or adding your custom functions to the plugin.

## Contributing

Feel free to fork this repository, submit pull requests, or open issues to suggest improvements or report bugs.
