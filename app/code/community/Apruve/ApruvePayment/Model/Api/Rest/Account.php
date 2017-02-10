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
 * Class Apruve_ApruvePayment_Model_Api_Rest_Account
 *
 * Provide rest methods to communicate with apruve
 */
class Apruve_ApruvePayment_Model_Api_Rest_Account extends Apruve_ApruvePayment_Model_Api_Rest
{

    /**
     * Corporate account general fields
     * @var array
     */
    protected $_fields = array(
        //required
        'id',
        'customer_uuid',
        'merchant_uuid',
        'type',
        'payment_term_strategy_name',
        'name',
        'authorized_buyers' => array(),
    );

    /**
     * Retrieve an existing corporate account by its ID in apruve
     *
     * @param string $apruveEmail
     * @param string $apruveMerchantId
     * @return $result string
     */
    public function getCorporateAccount($email)
    {
        $data = json_encode([
            'email' => $email
        ]);

        $curlOptions = [];
        $curlOptions[CURLOPT_POSTFIELDS] = $data;

        $result = $this->execCurlRequest($this->_getCorporateAccountUrl(), 'GET', $curlOptions);

        if ($result) {
            if($result['success'] == false){
                Mage::throwException(Mage::helper('apruvepayment')->__($result['messsage']));
            }
            $this->_fields = $result['response'];
            return $this->_fields;
        } else {
            Mage::throwException(Mage::helper('apruvepayment')->__('An unknown error has occurred.  Please try again or contact Apruve support.'));
        }
    }

    /**
     * Retrieve an the first instance of the buyers shopper_id
     *
     * @param string $mail
     * @return $result string
     */
    public function getShopperId($email)
    {
        $corperateAccountArray = $this->_fields[0];
        foreach ($corperateAccountArray['authorized_buyers'] as $buyer) {
            if (strcasecmp($buyer['email'],$email) == 0) {
                return $buyer['id'];
            }
        }
        Mage::throwException(Mage::helper('apruvepayment')->__('Couldn\'t find a shopper with that email address at Apruve.'));
    }

    /**
     * Retrieve the payment term
     *
     * Get url for an Apruve corporate account
     * @return string
     */
    public function getPaymentTerm()
    {
        if ($this->getCorporateAccountId()) {
            return array('corporate_account_id' => $this->getCorporateAccountId());
        } else {
            return null;
        }
    }

    public function getCorporateAccountId()
    {
        return $this->_fields[0]['id'];
    }

    /**
     * Retrieve an existing corporate account by its ID in apruve
     *
     * Get url for an Apruve corporate account
     * @return string
     */
    protected function _getCorporateAccountUrl()
    {
        return $this->getBaseUrl(true) . $this->getApiUrl() . 'merchants/' . $this->getMerchantKey() . '/corporate_accounts';

    }
}
