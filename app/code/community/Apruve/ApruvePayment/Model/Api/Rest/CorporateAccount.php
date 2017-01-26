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
 * @copyright  Copyright (coffee) 2017 Apruve, Inc. (http://www.apruve.com).
 * @license    http://opensource.org/licenses/Apache-2.0  Apache License, Version 2.0
 */

/**
 * Class Apruve_ApruvePayment_Model_Api_Rest_Order
 *
 * Provide rest methods to communicate with apruve
 */
class Apruve_ApruvePayment_Model_Api_Rest_Corporate_Account extends Apruve_ApruvePayment_Model_Api_Rest
{
    /**
     * Retrieve an existing corporate account by its ID in apruve
     *
     * @param string $apruveEmail
     * @param string $apruveMerchantId
     * @return $result string
     */
    public function getCorporateAccount($apruveEmail)
    {
        $data = json_encode([
            'email' => $apruveEmail,
            'merchant_id' => $this->getMerchantKey()
        ]);

        $curlOptions = [];
        $curlOptions[CURLOPT_POSTFIELDS] = $data;

        $result = $this->execCurlRequest($this->_getCorporateAccountUrl(), 'GET', $curlOptions);

//        $apruveInvoiceId = isset($result['response']['id']) ? $result['response']['id'] : '';
//        $apruveInvoiceItemIds = isset($result['response']['invoice_items']) ? $result['response']['invoice_items'] : '';
//        if($result['success'] == true) {
//            $this->_updateInvoiceId($apruveInvoiceId, $apruveInvoiceItemIds, $invoice);
//        }
//
        return $result;


    }

    /**
     * Retrieve an existing corporate account by its ID in apruve
     *
     * Get url for an Apruve corporate account
     * @return string
     */
    protected function _getCorporateAccountUrl()
    {
        return $this->getBaseUrl(true) . $this->getApiUrl() . 'corporate_accounts/';

    }
}