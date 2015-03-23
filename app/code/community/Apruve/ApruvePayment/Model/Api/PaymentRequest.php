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
 * Class Apruve_ApruvePayment_Model_Api_PaymentRequest
 * Provide methods to build paymentRequest
 * @see https://apruvit.atlassian.net/wiki/display/DOCCO/payment_request
 */
class Apruve_ApruvePayment_Model_Api_PaymentRequest extends Apruve_ApruvePayment_Model_Api_Abstract
{
    /**
     * Post request general fields
     * @var array
     */
    protected $_postFields = array(
        //required
        'merchant_id',
        'amount_cents',
        'line_items' => array(),
        //optional
        'tax_cents',
        'shipping_cents',
        'currency', // current only USD
        'shopperName',
        'shopperEmail'
    );

    /**
     * Line Items Fields
     * @var array
     */
    protected $_lineItemFields = array(
        //required
        'title',
        'amount_cents', // if qty -> should chanfe
        'description',
        'variant_info',
        'sku',
        'vendor',
        'price_ea_cents',
        'view_product_url',
    );

    /**
     * @var array
     */
    protected $_paymentRequest;


    public function __construct()
    {
        $this->_paymentRequest = $this->_setPaymentRequest();
    }

    /**
     * Get json encoded payment request
     * @return string
     */
    public function getPaymentRequestJSON()
    {
        return json_encode($this->_paymentRequest);
    }

    /**
     * Get secure hash
     * @see https://apruvit.atlassian.net/wiki/display/DOCCO/Checkout+Page+Tutorial#CheckoutPageTutorial-1b:CreatingaSecureHash
     * @return string
     */
    public function getSecureHash()
    {
        $concatString = $this->_getApiKey();

        foreach ($this->_paymentRequest as $val) {
            if(!is_array($val)) {
                $concatString .= $val;
            } else {
                foreach($val as $v) {
                    foreach ($v as $s) {
                        $concatString .= $s;
                    }

                }
            }
        }

        return hash('sha256', $concatString);
    }


    /**
     * Build Payment Request Array
     * @return array
     */
    protected function _setPaymentRequest()
    {
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $paymentRequest = array(
            'merchant_id' => $this->_getMerchantKey(),
            'amount_cents' => $this->_convertPrice($quote->getGrandTotal()),
            'currency' => 'USD',
            'tax_cents' => $this->_convertPrice($quote->getShippingAddress()->getTaxAmount()),
            'shipping_cents' => $this->_convertPrice($quote->getShippingAddress()->getShippingAmount()),
            'line_items' => $this->_getLineItems($quote),
            'shopperName' => $this->_getShopperInfo($quote, 'name'),
            'shopperEmail' => $this->_getShopperInfo($quote, 'email')
        );

        return $paymentRequest;
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     */
    function _getShopperInfo($quote, $attrName)
    {
        $method = 'get'.ucfirst($attrName);
        if ($quote->getCustomerIsGuest()) {
            return $quote->getBillingAddress()->$method();
        }

        return $quote->getCustomer()->$method();
    }

    /**
     * Build Line items array
     * @param Mage_Sales_Model_Quote $quote
     * @return array
     */
    protected function _getLineItems($quote)
    {
        $line_items = array();
        foreach ($quote->getAllVisibleItems() as $item) {
            $qty = $item->getQty();
            $title = $item->getName();
            $amount_cents = $this->_convertPrice($item->getPrice()) * $qty;
            $shortDescription = $item->getShortDescription();
            $viewUrl = $item->getProduct()->getProductUrl(false);
            $priceEaCents = $this->_convertPrice($item->getPrice());

            $line_item = array(
                'title' => $title,
                'amount_cents' => $amount_cents,
                'description' => $shortDescription,
                'view_product_url' => $viewUrl,
                'price_ea_cents' => $priceEaCents,
                'quantity' => $qty,

            );

            $variantInfo = $this->_getVariantInfo($item);
            if($variantInfo) {
                $line_item['variant_info'] = $variantInfo;
            }

            $line_items[] = $line_item;
        }

        return $line_items;
    }


    /**
     * Get Product configuration if exits
     * @param Mage_Sales_Model_Quote_Item $item
     * @return string
     */
    protected function _getVariantInfo($item)
    {
        $result = '';
        $variantInfo = array();
        $options = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct());
        if(isset($options['options'])) {
            $opt = $this->_getProductCustomOptions($options['options']);
            $variantInfo =  array_merge($variantInfo, $opt);
        }
        if(isset($options['attributes_info'])) {
            $opt = $this->_getConfigurableOptions($options['attributes_info']);
            $variantInfo =  array_merge($variantInfo, $opt);
        }

        if(isset($options['bundle_options'])) {
            $opt = $this->_getBundleOptions($options['bundle_options']);
            $variantInfo =  array_merge($variantInfo, $opt);
        }

        if(!empty($variantInfo)) {
            $result = $this->_getFormatedVariantInfo($variantInfo);
        }

        return $result;

    }

    /**
     * @param array $options
     * @return array
     */
    protected function _getProductCustomOptions($options)
    {
        $arr = array();
        foreach ($options as $option) {
            $arr[] = $option['label'].': '.$option['value'];
        }

        return $arr;
    }

    /**
     * @param array $attributesInfo
     * @return array
     */
    protected function _getConfigurableOptions($attributesInfo)
    {
        $arr = array();
        foreach ($attributesInfo as $option) {
            $arr[] = $option['label'].': '.$option['value'];
        }
        return $arr;
    }

    /**
     * @param array $bundleOptions
     * @return array
     */
    protected function _getBundleOptions($bundleOptions)
    {
        $arr = array();
        foreach($bundleOptions as $option) {
            $arr[] = $option['label'].': '.$option['value'][0]['title'];
        }
        return $arr;
    }

    /**
     * Concatenate all options to string
     * @param array $arr
     * @return string
     */
    //todo: new line symbol
    protected function _getFormatedVariantInfo($arr)
    {
        if(count($arr) == 1) {
            $result = $arr[0];
        } else {
            $result = implode(', ', $arr);
        }

        return $result;
    }

}

