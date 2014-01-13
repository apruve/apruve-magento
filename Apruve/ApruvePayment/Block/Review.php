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

class Apruve_ApruvePayment_Block_Review extends Mage_Core_Block_Template
{
    /**
     * Get url for place order action
     * @return string
     */
    public function getPlaceOrderUrl()
    {
        return $this->getUrl('apruvepayment/payment/placeOrder');
    }


    private function _getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get error
     * @return mixed
     */
    public function getErrors()
    {
        $errors = $this->getApruveSession()->getErrors();
        $this->getApruveSession()->unsetData('errors');
        return $errors;
    }


    /**
     * @return Apruve_ApruvePayment_Model_Session
     */
    public function getApruveSession()
    {
        return Mage::getSingleton('apruvepayment/session');
    }

    /**
     * Check if quote contain only virtual products
     * @return bool
     */
    public function getQuoteIsVirtual()
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        return $quote->getIsVirtual();
    }


}