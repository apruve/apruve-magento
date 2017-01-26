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
     * @return Apruve_ApruvePayment_Model_Api_Payment
     */
    public function getPaymentApiModel()
    {
        return Mage::getModel('apruvepayment/api_payment');
    }

    public function getMode()
    {
        $sourceModel = Mage::getModel('apruvepayment/mode');
        $sourceArray = $sourceModel->toArray();
        return $sourceArray[Mage::getStoreConfig('payment/apruvepayment/mode')];
    }

    public function getApiVersion()
    {
        return Mage::getStoreConfig('payment/apruvepayment/version');
    }

    public function isAutoSubmit()
    {
        return Mage::getStoreConfig('payment/apruvepayment/autosubmit');
    }

    public function getSrc()
    {
        $apruveUrl = Mage::getModel('apruvepayment/api_payment')->getBaseUrl();
        return $apruveUrl . 'js/apruve.js?display=compact';
    }

    /**
     * Log the messages and data to apruve.log if log is enabled
     *
     * @var string|array|object $data
     * @return void
     */
    public function logException($data)
    {
        $isEnabled = Mage::getStoreConfig('payment/apruvepayment/log');
        if($isEnabled) {
            Mage::log($data, 7, 'apruve.log', true);
        }
    }

    /**
     * Retrieve only the visible items from a item collection for order, invoice and shipment
     *
     * @param Mage_Sales_Model_Abstract $object
     * @return Mage_Core_Model_Abstract[]
     */
    public function getAllVisibleItems($object)
    {
        $items = array();
        foreach ($object->getItemsCollection() as $item) {
            $orderItem = $item->getOrderItem();
            if (!$orderItem->isDeleted() && !$orderItem->getParentItemId()) {
                $qty = (int) $item->getQty();
                $qty = $qty > 0 ? $qty : (int) $item->getQtyOrdered();
                if ($qty) {
                    $items[] =  $item;
                }
            }
        }
        return $items;
    }
}