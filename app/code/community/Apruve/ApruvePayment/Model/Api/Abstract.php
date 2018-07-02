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
 * Class Apruve_ApruvePayment_Model_Api_Abstract
 *
 * This is an abstract for Apruve payment gateway.
 */
abstract class Apruve_ApruvePayment_Model_Api_Abstract
{
    /**
     * @var string
     */
    const DATE_FORMAT = DateTime::ATOM;

    /**
     * Get Apruve base url based on mode
     *
     * @param bool $secure
     *
     * @return string
     */
    public function getBaseUrl( $secure = true ) 
    {
        $http = $secure ? 'https://' : 'http://';
        if ($this->getIsTestMode()) {
            return $http . 'test.apruve.com/';
        } else {
            return $http . 'app.apruve.com/';
        }
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
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return float[]
     */
    public function getAmountsFromQuote( $quote ) 
    {
        $result['amount_cents']   = $quote->getGrandTotal();
        $result['tax_cents']      = 0;
        $result['shipping_cents'] = 0;
        foreach ($quote->getAllAddresses() as $address) {
            /** @var Mage_Sales_Model_Quote_Address $address */
            $result['tax_cents']      += $address->getTaxAmount();
            $result['shipping_cents'] += $address->getShippingAmount();
        }

        return $result;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     *
     * @return float[]
     */
    public function getAmountsFromOrder( $order ) 
    {
        $result['amount_cents']   = $order->getGrandTotal();
        $result['tax_cents']      = $order->getTaxAmount();
        $result['shipping_cents'] = $order->getShippingAmount();

        return $result;
    }

    /**
     * Generate headers for rest request
     * @return array
     */
    protected function getHeaders() 
    {
        return array(
            "accept: application/json",
            "apruve-api-key: " . $this->getApiKey(),
            "content-type: application/json"
        );
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
     * Get api url part based on version
     * @return string
     */
    protected function getApiUrl() 
    {
        return 'api/' . $this->getApiVersion() . '/';
    }

    /**
     * Get the API version selected for the store
     * @return string
     */
    protected function getApiVersion() 
    {
        return Mage::helper('apruvepayment')->getApiVersion();
    }

    /**
     * Prepare the response array for the API call
     *
     * @var string|JSON $response
     * @var string $url
     * @var string $err
     * @var string|integer $httpStatus
     * @var string|[] $curlOptions
     * @return string[] $result
     */
    protected function _prepareResponse( $response, $url = '', $err = '', $httpStatus = '', $curlOptions = '' ) 
    {
        $result  = array();
        $success = true;
        $message = '';
        if ($err) {
            $message = "Request Error:" . $err;
            $success = false;
        }

        if ($httpStatus < 200 || $httpStatus >= 300) {
            $responseDecoded = json_decode($response);
            if (isset($responseDecoded->error)) {
                $message = $responseDecoded->error;
            } else {
                $message = "Request Error: Request could not be processed";
            }

            $success = false;
        }

        $result['success']   = $success;
        $result['code']      = $httpStatus;
        $result['messsage']  = $message;
        $result['response']  = Mage::helper('core')->jsonDecode($response);
        $result['post_data'] = $curlOptions;
        $result['url']       = $url;
        Mage::helper('apruvepayment')->logException($result);

        return $result;
    }

    /**
     * Returns an expiry date in future for the order items created in apruve
     *
     * @return date
     */
    protected function getExpiryDate() 
    {
        return $this->getDateFormatted('+1 week');
    }

    /**
     * Returns a formatted date based on the constant DATE_FORMAT
     *
     * @return date
     */
    protected function getDateFormatted( $date ) 
    {
        return date(self::DATE_FORMAT, strtotime($date));
    }

    /**
     * Get the order item's vendor/manufacturer data
     *
     * @return string
     */
    protected function getVendor( $orderItem ) 
    {
        $product       = $orderItem->getProduct();
        $attributeCode = Mage::getStoreConfig('payment/apruvepayment/product_vendor');
        $vendor        = $product->getData($attributeCode);

        return $vendor;
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
     * Build Discount Line item
     *
     * @param Mage_Sales_Model_Quote|Mage_Sales_Model_Order $object
     *
     * @return array
     */
    protected function _getDiscountItem( $object ) 
    {
        if ($object instanceof Mage_Sales_Model_Quote) {
            $discountAmount = $this->convertPrice($object->getBaseSubtotal() - $object->getBaseSubtotalWithDiscount());
        } elseif ($object instanceof Mage_Sales_Model_Order) {
            $discountAmount = $this->convertPrice($object->getBaseDiscountAmount());
        } elseif ($object instanceof Mage_Sales_Model_Order_Invoice) {
            $discountAmount = $this->convertPrice($object->getBaseDiscountAmount());
        } else {
            return false;
        }

        if ($discountAmount) {
            $discountAmount = - 1 * abs($discountAmount);
        } else {
            return false;
        }

        $helper                            = Mage::helper('apruvepayment');
        $discountItem                      = array();
        $discountItem['title']             = $helper->__('Discount');
        $discountItem['price_total_cents'] = $discountAmount;
        $discountItem['price_ea_cents']    = $discountAmount;
        $discountItem['quantity']          = 1;
        $discountItem['description']       = $helper->__('Cart Discount');
        $discountItem['sku']               = $helper->__('Discount');

        return $discountItem;
    }

    /**
     * Get the current store currency
     *
     * @return date
     */
    protected function getCurrency() 
    {
        return Mage::app()->getStore()->getBaseCurrencyCode();
    }

    /**
     * Convert price to needed value
     * As current version supports only USD, convert price to cents
     *
     * @param float $price
     *
     * @return float
     */
    protected function convertPrice( $price ) 
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
     *
     * @param Mage_Sales_Model_Quote_Item | Mage_Sales_Model_Order_Item $item
     *
     * @return string
     */
    protected function getShortDescription( $item ) 
    {
        $shortDescription = $item->getProduct()->getShortDescription();


        if (isset($shortDescription) && strlen($shortDescription) > 3500) {
            $shortDescription = substr($shortDescription, 0, 3500);
        }

        return $shortDescription;
    }

    /**
     * Get Product configuration if exits
     *
     * @param Mage_Sales_Model_Quote_Item | Mage_Sales_Model_Order_Item $item
     *
     * @return string
     */
    protected function getVariantInfo( $item ) 
    {
        $result      = '';
        $variantInfo = array();
        $options     = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct());
        if (isset($options['options'])) {
            $opt         = $this->getProductCustomOptions($options['options']);
            $variantInfo = array_merge($variantInfo, $opt);
        }

        if (isset($options['attributes_info'])) {
            $opt         = $this->getConfigurableOptions($options['attributes_info']);
            $variantInfo = array_merge($variantInfo, $opt);
        }

        if (isset($options['bundle_options'])) {
            $opt         = $this->getBundleOptions($options['bundle_options']);
            $variantInfo = array_merge($variantInfo, $opt);
        }

        if (! empty($variantInfo)) {
            $result = $this->getFormatedVariantInfo($variantInfo);
        }

        return $result;
    }

    /**
     * @param array $options
     *
     * @return array
     */
    protected function getProductCustomOptions( $options ) 
    {
        $arr = array();
        foreach ($options as $option) {
            $arr[] = $option['label'] . ': ' . $option['value'];
        }

        return $arr;
    }

    /**
     * Concatenate all options to string
     *
     * @param array $arr
     *
     * @return string
     *
     * @param array $attributesInfo
     *
     * @return array
     */
    protected function getConfigurableOptions( $attributesInfo ) 
    {
        $arr = array();
        foreach ($attributesInfo as $option) {
            $arr[] = $option['label'] . ': ' . $option['value'];
        }

        return $arr;
    }

    /**
     * @param array $bundleOptions
     *
     * @return array
     */
    protected function getBundleOptions( $bundleOptions ) 
    {
        $arr = array();
        foreach ($bundleOptions as $option) {
            $arr[] = $option['label'] . ': ' . $option['value'][0]['title'];
        }

        return $arr;
    }


    protected function getFormatedVariantInfo( $arr ) 
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
}
