<?php
/**
 * Apruve
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
 *
 */


/**
 * Class Apruve_ApruvePayment_Model_Api_Payment
 * Provide methods to build payment
 * @see https://apruvit.atlassian.net/wiki/display/DOCCO/payment_request
 */
class Apruve_ApruvePayment_Model_Api_Payment extends Apruve_ApruvePayment_Model_Api_Abstract
{
    /**
     * Post request general fields
     * @var array
     */
    protected $_postFields = array(
        //required
        'merchant_id',
        'amount_cents',
        //optional
        'currency',
        'tax_cents',
        'shipping_cents',
        'finalize_on_create',
        'invoice_on_create',
        'line_items' => array(),
    );
    /**
     * Line Items Fields
     * @var array
     */
    protected $_lineItemFields = array(
        //required
        'title',
        'price_total_cents', // if qty -> should chanfe
        'price_ea_cents',
        'description',
        'variant_info',
        'sku',
        'vendor',
        'view_product_url',
    );
    /**
     * @var array
     */
    protected $_paymentRequest;
    protected $_quote;

    /**
     * Get json encoded payment request
     * @return string
     */
    public function getPaymentRequestJSON() 
    {
        return json_encode($this->_getPaymentRequest());
    }

    /**
     * Build Payment Request Array
     * @return array
     */
    protected function _getPaymentRequest() 
    {
        if ($this->_paymentRequest == null) {
            $this->_paymentRequest = array(
                'merchant_id'        => $this->getMerchantKey(),
                'amount_cents'       => $this->convertPrice($this->getAmount('amount_cents')),
                'currency'           => $this->getCurrency(),
                'tax_cents'          => $this->convertPrice($this->getAmount('tax_cents')),
                'shipping_cents'     => $this->convertPrice($this->getAmount('shipping_cents')),
                'finalize_on_create' => 'false',
                'invoice_on_create'  => 'false',
                'order_items'         => $this->getLineItems($this->getQuote())
            );
        }

        return $this->_paymentRequest;
    }

    /**
     * Return amount_cents, shipping_cents or tax_cents
     *
     * @param $key
     *
     * @return float | bool
     */
    public function getAmount( $key ) 
    {
        if (empty($this->amounts)) {
            $this->amounts = $this->getAmountsFromQuote($this->getQuote());
        }

        if (isset($this->amounts[ $key ])) {
            return $this->amounts[ $key ];
        }

        return false;
    }

    /**
     * Get current quote
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote() 
    {
        if ($this->_quote == null) {
            $this->_quote = Mage::getSingleton('checkout/session')->getQuote();
        }

        return $this->_quote;
    }

    /**
     * Build Line items array
     *
     * @param Mage_Sales_Model_Quote $itemsParent
     *
     * @return array
     */
    protected function getLineItems( $itemsParent ) 
    {
        $result = array();
        /** @var Mage_Sales_Model_Quote_Item[] $visibleItems */
        $visibleItems = $itemsParent->getAllVisibleItems();
        foreach ($visibleItems as $item) {
            $result[] = array(
                'title'             => $item->getName(),
                'price_total_cents' => $this->convertPrice($item->getBaseRowTotal()),
                'price_ea_cents'    => $this->convertPrice($item->getBasePrice()),
                'quantity'          => $item->getQty(),
                'description'       => $this->getShortDescription($item),
                'variant_info'      => $this->getVariantInfo($item),
                'sku'               => $item->getSku(),
                'view_product_url'  => $item->getProduct()->getProductUrl(false),
            );
        }

        // get discount line item
        if (( $discountItem = $this->_getDiscountItem($itemsParent) )) {
            $result[] = $discountItem;
        }

        return $result;
    }

    /**
     * Get secure hash
     * @see https://apruvit.atlassian.net/wiki/display/DOCCO/Checkout+Page+Tutorial#CheckoutPageTutorial-1b:CreatingaSecureHash
     * @return string
     */
    public function getSecureHash() 
    {
        $concatString = $this->getApiKey();

        foreach ($this->_getPaymentRequest() as $val) {
            if (! is_array($val)) {
                $concatString .= $val;
            } else {
                foreach ($val as $v) {
                    foreach ($v as $s) {
                        $concatString .= $s;
                    }
                }
            }
        }

        return hash('sha256', $concatString);
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     */
    public function getShopperInfo( $attrName ) 
    {
        $method = 'get' . ucfirst($attrName);
        if ($this->getQuote()->getCustomerIsGuest()) {
            return $this->getQuote()->getBillingAddress()->$method();
        }

        return $this->getQuote()->getCustomer()->$method();
    }
}