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

class Apruve_ApruvePayment_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * @return Apruve_ApruvePayment_Model_Api_PaymentRequest
     */
    public function getPaymentRequestApiModel()
    {
        return Mage::getModel('apruvepayment/api_PaymentRequest');
    }

    public function getMode()
    {
        $sourceModel = Mage::getModel('apruvepayment/mode');
        $sourceArray = $sourceModel->toArray();
        return $sourceArray[Mage::getStoreConfig('payment/apruvepayment/mode')];
    }

    public function getSrc()
    {
        $sourceModel = Mage::getModel('apruvepayment/mode');
        $sourceArray = $sourceModel->toSrcArray();
        return $sourceArray[Mage::getStoreConfig('payment/apruvepayment/mode')];
    }
}