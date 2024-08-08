<?php
/*
Plugin Name: Custom Payment Link
Description: Generate and send payment links to customers using Authorize.Net.
Version: 1.0
Author: Sohaib Ali Khan
*/

// Include Composer autoloader if installed
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

use AnetAPI\MerchantAuthenticationType;
use AnetAPI\TransactionRequestType;
use AnetAPI\CreateTransactionRequest;
use AnetController\CreateTransactionController;
use AnetAPI\ANetEnvironment;

defined('ABSPATH') or die('No script kiddies please!');

// Register shortcode
add_shortcode('cpl_payment_link_form', 'cpl_payment_link_form_shortcode');

function cpl_payment_link_form_shortcode() {
    ob_start();
    cpl_admin_form();
    return ob_get_clean();
}

function cpl_admin_form() {
    ?>
    <div class="wrap">
        <h1>Generate Payment Link</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Order ID</th>
                    <td><input type="text" name="order_id" value="" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Amount</th>
                    <td><input type="text" name="amount" value="" /></td>
                </tr>
            </table>
            <?php submit_button('Generate Payment Link'); ?>
        </form>
    </div>
    <?php

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id']) && isset($_POST['amount'])) {
        $order_id = sanitize_text_field($_POST['order_id']);
        $amount = sanitize_text_field($_POST['amount']);

        // Generate payment link
        $payment_link = cpl_generate_payment_link($order_id, $amount);

        // Send email to customer
        cpl_send_payment_link($order_id, $payment_link);

        // Notify admin
        cpl_notify_admin($order_id, $payment_link);
    }
}

function cpl_generate_payment_link($order_id, $amount) {
    $merchantAuthentication = new MerchantAuthenticationType();
    $merchantAuthentication->setName('YOUR_API_LOGIN_ID');
    $merchantAuthentication->setTransactionKey('YOUR_TRANSACTION_KEY');
    
    $transactionRequest = new TransactionRequestType();
    $transactionRequest->setTransactionType("authCaptureTransaction");
    $transactionRequest->setAmount($amount);
    $transactionRequest->setInvoiceNumber($order_id);
    
    $request = new CreateTransactionRequest();
    $request->setMerchantAuthentication($merchantAuthentication);
    $request->setTransactionRequest($transactionRequest);
    
    $controller = new CreateTransactionController($request);
    $response = $controller->executeWithApiResponse(ANetEnvironment::SANDBOX);
    
    if ($response->getMessages()->getResultCode() == "Ok") {
        $transactionResponse = $response->getTransactionResponse();
        $payment_link = 'https://yourpaymentgateway.com/payment?id=' . $transactionResponse->getTransId();
    } else {
        $payment_link = 'Error generating payment link';
    }
    
    return $payment_link;
}

function cpl_send_payment_link($order_id, $payment_link) {
    $order = wc_get_order($order_id);
    if ($order) {
        $customer_email = $order->get_billing_email();
        $subject = 'Your Payment Link';
        $message = 'Click the following link to complete your payment: ' . $payment_link;
        wp_mail($customer_email, $subject, $message);
    }
}

function cpl_notify_admin($order_id, $payment_link) {
    $admin_email = 'rock@plexlogo.com';
    $subject = 'Payment Link Sent';
    $message = 'A payment link has been generated and sent to the customer for Order ID: ' . $order_id . '. Payment link: ' . $payment_link;
    wp_mail($admin_email, $subject, $message);
}
