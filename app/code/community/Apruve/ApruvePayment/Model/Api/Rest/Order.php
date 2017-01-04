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
 * Class Apruve_ApruvePayment_Model_Api_Rest_Order
 *
 * Provide rest methods to communicate with apruve
 */
class Apruve_ApruvePayment_Model_Api_Rest_Order extends Apruve_ApruvePayment_Model_Api_Rest
{
    /**
     * Get url order url
     * @param string $apruveOrderId
     * @return string
     */
    protected function _getOrderUrl($apruveOrderId)
    {
        return $this->getBaseUrl(true) . $this->getApiUrl() . 'orders/' . $apruveOrderId;
    }


    /**
     * Get url for update order
     * @param string $apruveOrderId
     * @return string
     */
    protected function _getUpdateOrderUrl($apruveOrderId)
    {
        return $this->getBaseUrl(true) . $this->getApiUrl() . 'orders/' . $apruveOrderId;
    }

    /**
     * Get url for order finalizing
     * @param string $apruveOrderId
     * @return string
     */
    protected function _getFinalizeOrderUrl($apruveOrderId)
    {
        return $this->getBaseUrl(true) . $this->getApiUrl() . 'orders/' . $apruveOrderId . '/finalize';
    }

    /**
     * Get url for order cancel
     * @param string $apruveOrderId
     * @return string
     */
    protected function _getCancelOrderUrl($apruveOrderId)
    {
        return $this->getBaseUrl(true) . $this->getApiUrl() . 'orders/' . $apruveOrderId . '/cancel';
    }

    /**
     * Retrieve an existing order by its ID in apruve
     *
     * @param $id string
     * @return $result string
     */
    public function getOrder($apruveOrderId)
    {
        $result = $this->execCurlRequest($this->_getOrderUrl($apruveOrderId));
        return $result;
    }

    /**
     * Update Apruve order id to it's corresponding order in magento
     *
     * @param $id string
     * @param $order Mage_Sales_Model_Order
     * @return bool
     * @throws Mage_Core_Exception
     */
    protected function _updateOrderId($apruveOrderId, $order)
    {
        try {
            $apruveEntity = Mage::getModel('apruvepayment/entity')->loadByOrderId($order->getIncrementId());
            $apruveEntity->setApruveId($apruveOrderId);
            $apruveEntity->setMagentoId($order->getIncrementId());
            $apruveEntity->setEntityType('order');
            $apruveEntity->save();
        } catch(Exception $e) {
            Mage::helper('apruvepayment')->logException('Couldn\'t update the order: ' . $e->getMessage());
            Mage::throwException(Mage::helper('apruvepayment')->__('Couldn\'t update order.'));
        }
        return true;
    }

    /**
     * Update an existing order by its ID in apruve
     *
     * @param string $apruveOrderId
     * @param Mage_Sales_Model_Order $order
     * @return string $result
     */
    public function updateOrder($apruveOrderId, $order)
    {
        $lineItems = [];
        // get discount line item
        if(($discountItem = $this->_getDiscountItem($order))) {
            $lineItems[] = $discountItem;
        }

        $data = json_encode(array(
            'order' => array(
                'merchant_order_id' => $order->getIncrementId(),
                'amount_cents'      => $this->convertPrice($order->getBaseGrandTotal()),
                'shipping_cents'    => $this->convertPrice($order->getBaseShippingAmount()),
                'tax_cents'         => $this->convertPrice($order->getBaseTaxAmount()),
                'invoice_on_create' => 'false',
                'order_items'       => $lineItems
            )
        ));

        $curlOptions = [];
        $curlOptions[CURLOPT_POSTFIELDS] = $data;

        $result = $this->execCurlRequest($this->_getUpdateOrderUrl($apruveOrderId), 'PUT', $curlOptions);
        if($result['success'] == true) {
            Mage::helper('apruvepayment')->logException('Order updated successfully...');
            $this->_updateOrderId($apruveOrderId, $order);
        }
        return $result;
    }

    /**
     * Get order from quote
     *
     * @param $quote Mage_Sales_Model_Quote
     * @return $order Mage_Sales_Model_Order
     * @throws Mage_Core_Exception
     */
    protected function _getOrderFromQuote($quote)
    {
        $orderIncrementId = $quote->getReservedOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        if(!$order->getId()) {
            Mage::throwException(Mage::helper('apruvepayment')->__('Couldn\'t load the order.'));
        }
        return $order;
    }

    /**
     * Finalize an existing order by its ID in apruve
     *
     * @param $apruveOrderId string
     * @param $order Mage_Sales_Model_Order
     * @return $result string
     */
    public function finalizeOrder($apruveOrderId, $order)
    {
        $result = $this->execCurlRequest($this->_getFinalizeOrderUrl($apruveOrderId), 'POST');
        if($result['success'] == true) {
            $this->_updateOrderId($apruveOrderId, $order);
            Mage::helper('apruvepayment')->logException('Order finalized successfully...');
        }

        return $result;
    }

    /**
     * Cancel an existing order by its ID in apruve
     *
     * @param $id string
     * @return $result string
     */
    public function cancelOrder($apruveOrderId)
    {
        $result = $this->execCurlRequest($this->_getCancelOrderUrl($apruveOrderId), 'POST');
        return $result;
    }
}