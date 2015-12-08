<?php

/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Apache License, Version 2.0
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/Apache-2.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@apruve.com so we can send you a copy immediately.
 *
 * @category   Apruve
 * @package    Apruve_Payment
 * @copyright  Copyright (coffee) 2014 Apruve, Inc. (http://www.apruve.com).
 * @license    http://opensource.org/licenses/Apache-2.0  Apache License, Version 2.0
 */

/**
 * Class Apruve_ApruvePayment_Model_Api_Rest
 *
 * Provide rest methods to communicate with apruve
 */
class Apruve_ApruvePayment_Model_Api_Rest extends Apruve_ApruvePayment_Model_Api_Abstract
{
    /**
     * Send Payment object
     * @param string $paymentRequestId
     * @param array $payment
     *
     * @return bool
     */
    public function postPayment($paymentRequestId, $payment)
    {
        $data = json_encode($payment);

        $c = curl_init($this->getPaymentUrl($paymentRequestId));

        curl_setopt($c, CURLOPT_HTTPHEADER, $this->getHeaders());
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($c);
        $http_status = curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);

        if ($http_status == '201') {
            return json_decode($response);
        } else {
            return false;
        }

    }

    /**
     * Update paymentRequest object
     * Availible fields to update are: amount_cents, shipping_cents, tax_cents
     * @param string $paymentRequestId
     * @param float $amount
     * @param float $shipping
     * @param float $tax
     * @return bool
     */
    public function updatePaymentRequest($paymentRequestId, $amount, $shipping, $tax, $orderIncrementId)
    {
        $data = json_encode(array(
            'merchant_order_id' => $orderIncrementId,
            'amount_cents' => $this->convertPrice($amount),
            'shipping_cents' => $this->convertPrice($shipping),
            'tax_cents' => $this->convertPrice($tax),
        ));

        $c = curl_init($this->getUpdatePaymentRequestUrl($paymentRequestId));

        curl_setopt($c, CURLOPT_HTTPHEADER, $this->getHeaders());
        curl_setopt($c, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($c);
        $http_status = curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);


        if ($http_status == '200') {
            return json_decode($response);
        } else {
            return false;
        }
    }

    /**
     * GET Apruve Payment Status
     * Check whether given status is same as in Apruve.com
     * @param $status
     * @param $apiUrl
     * @return bool
     */
    public function getApruveOrderStatus($apiUrl, $status)
    {
        $c = curl_init($apiUrl);
        curl_setopt($c, CURLOPT_HTTPHEADER, $this->getHeaders());
        curl_setopt($c, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_HEADER, true);
    }

    /**
     * Get url for send payment
     * @param string $paymentRequestId
     * @return string
     */
    protected function getPaymentUrl($paymentRequestId)
    {
        return $this->getBaseUrl(true) . $this->getApiUrl() . 'payment_requests/' . $paymentRequestId . '/payments';
    }


    /**
     * Get url for update paymentRequest
     * @param string $paymentRequestId
     * @return string
     */
    protected function getUpdatePaymentRequestUrl($paymentRequestId)
    {
        return $this->getBaseUrl(true) . $this->getApiUrl() . 'payment_requests/' . $paymentRequestId;
    }
}