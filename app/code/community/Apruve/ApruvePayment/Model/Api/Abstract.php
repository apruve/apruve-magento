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
    protected function getHeaders()
    {
        return array(
            'Content-type: application/json',
            'Apruve-Api-Key: ' . $this->getApiKey(),
        );
    }

    /**
     * Get Merchant key from module configuration
     * @return string|null
     */
    protected function getMerchantKey()
    {
        $id = Mage::getStoreConfig('payment/apruvepayment/merchant');
        return $id ? $id : null;
    }

    /**
     * Get Api key from module configuration
     * @return string|null
     */
    protected function getApiKey()
    {
        $api = Mage::getStoreConfig('payment/apruvepayment/api');
        return $api ? $api : null;

    }


    /**
     * Check whether payment works in test mode
     * @return bool
     */
    protected function getIsTestMode()
    {
        return Mage::getStoreConfig('payment/apruvepayment/mode');
    }


    /**
     * Get Apruve base url based on mode
     * @param bool $secure
     * @return string
     */
    public function getBaseUrl($secure = false)
    {
        $http = $secure ? 'https://' : 'http://';
        if($this->getIsTestMode()) {
            return $http.'test.apruve.com/';
        } else {
            return $http.'www.apruve.com/';
        }
    }


    /**
     * Get api url part based on version
     * @return string
     */
    protected function getApiUrl()
    {
        return 'api/'.$this->_version.'/';
    }


    /**
     * Convert price to needed value
     * As current version supports only USD, convert price to cents
     * @param float $price
     * @return float
     */
    protected function convertPrice($price)
    {
        return $price * 100;
    }

    /**
     * @return Apruve_ApruvePayment_Helper_Data
     */
    protected function getHelper()
    {
        return Mage::helper('apruvepayment');
    }


    /**
     * Get Product short description
     * @param Mage_Sales_Model_Quote_Item | Mage_Sales_Model_Order_Item $item
     * @return string
     */
    protected function getShortDescription($item)
    {
        $shortDescription = $item->getProduct()->getShortDescription();


        if (isset($shortDescription) && strlen($shortDescription) > 3500) {
            $shortDescription = substr($shortDescription, 0, 3500);
        }

        return $shortDescription;
    }
    /**
     * Get Product configuration if exits
     * @param Mage_Sales_Model_Quote_Item | Mage_Sales_Model_Order_Item $item
     * @return string
     */
    protected function getVariantInfo($item)
    {
        $result = '';
        $variantInfo = array();
        $options = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct());
        if (isset($options['options'])) {
            $opt = $this->getProductCustomOptions($options['options']);
            $variantInfo = array_merge($variantInfo, $opt);
        }
        if (isset($options['attributes_info'])) {
            $opt = $this->getConfigurableOptions($options['attributes_info']);
            $variantInfo = array_merge($variantInfo, $opt);
        }

        if (isset($options['bundle_options'])) {
            $opt = $this->getBundleOptions($options['bundle_options']);
            $variantInfo = array_merge($variantInfo, $opt);
        }

        if (!empty($variantInfo)) {
            $result = $this->getFormatedVariantInfo($variantInfo);
        }

        return $result;
    }

    /**
     * @param array $options
     * @return array
     */
    protected function getProductCustomOptions($options)
    {
        $arr = array();
        foreach ($options as $option) {
            $arr[] = $option['label'] . ': ' . $option['value'];
        }

        return $arr;
    }

    /**
     * @param array $attributesInfo
     * @return array
     */
    protected function getConfigurableOptions($attributesInfo)
    {
        $arr = array();
        foreach ($attributesInfo as $option) {
            $arr[] = $option['label'] . ': ' . $option['value'];
        }
        return $arr;
    }

    /**
     * @param array $bundleOptions
     * @return array
     */
    protected function getBundleOptions($bundleOptions)
    {
        $arr = array();
        foreach ($bundleOptions as $option) {
            $arr[] = $option['label'] . ': ' . $option['value'][0]['title'];
        }
        return $arr;
    }

    /**
     * Concatenate all options to string
     * @param array $arr
     * @return string
     */
    //todo: new line symbol
    protected function getFormatedVariantInfo($arr)
    {
        if (count($arr) == 1) {
            $result = $arr[0];
        } else {
            $result = implode(', ', $arr);
        }

        if (isset($result) && strlen($result) > 255) {
            $result = substr($result, 0, 255);
        }

        return $result;
    }



    /**
     * @param Mage_Sales_Model_Quote $quote
     * @return float[]
     */
    public function getAmountsFromQuote($quote)
    {
        $result['amount_cents'] = $quote->getGrandTotal();
        $result['tax_cents'] = 0;
        $result['shipping_cents'] = 0;
        foreach ($quote->getAllAddresses() as $address) {
            /** @var Mage_Sales_Model_Quote_Address $address */
            $result['tax_cents'] += $address->getTaxAmount();
            $result['shipping_cents'] += $address->getShippingAmount();
        }

        return $result;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return float[]
     */
    public function getAmountsFromOrder($order)
    {
        $result['amount_cents'] = $order->getGrandTotal();
        $result['tax_cents'] = $order->getTaxAmount();
        $result['shipping_cents'] = $order->getShippingAmount();

        return $result;
    }



}
