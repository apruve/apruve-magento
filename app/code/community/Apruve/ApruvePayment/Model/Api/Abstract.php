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

abstract class Apruve_ApruvePayment_Model_Api_Abstract
{
    /**
     * @var string
     */
    protected $_version = 'v3';
    //protected $_testMode;


    /**
     * Generate headers for rest request
     * @return array
     */
    protected function _getHeaders()
    {
        return array(
            'Content-type: application/json',
            'Apruve-Api-Key: ' . $this->_getApiKey(),
        );
    }

    /**
     * Get Merchant key from module configuration
     * @return string|null
     */
    protected function _getMerchantKey()
    {
        $id = Mage::getStoreConfig('payment/apruvepayment/merchant');
        return $id ? $id : null;
    }

    /**
     * Get Api key from module configuration
     * @return string|null
     */
    protected function _getApiKey()
    {
        $api = Mage::getStoreConfig('payment/apruvepayment/api');
        return $api ? $api : null;

    }


    /**
     * Check whether payment works in test mode
     * @return bool
     */
    protected function _getIsTestMode()
    {
        return Mage::getStoreConfig('payment/apruvepayment/testmode');
    }


    /**
     * Get Apruve base url based on mode
     * @param bool $secure
     * @return string
     */
    public function getBaseUrl($secure = false)
    {
        $http = $secure ? 'https://' : 'http://';
        if($this->_getIsTestMode()) {
            return $http.'test.apruve.com/';
        } else {
            return $http.'www.apruve.com/';
        }
    }


    /**
     * Get api url part based on version
     * @return string
     */
    protected function _getApiUrl()
    {
        return 'api/'.$this->_version.'/';
    }


    /**
     * Convert price to needed value
     * As current version supports only USD, convert price to cents
     * @param float $price
     * @return float
     */
    protected function _convertPrice($price)
    {
        return $price * 100;
    }

}