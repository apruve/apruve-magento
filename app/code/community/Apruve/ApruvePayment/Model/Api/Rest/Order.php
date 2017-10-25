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
     * Retrieve an existing order by its ID in apruve
     *
     * @param $id string
     *
     * @return $result string
     */
    public function getOrder($apruveOrderId)
    {
        $result = $this->execCurlRequest($this->_getOrderUrl($apruveOrderId));

        return $result;
    }

    /**
     * Get url order url
     *
     * @param string $apruveOrderId
     *
     * @return string
     */
    protected function _getOrderUrl($apruveOrderId)
    {
        return $this->getBaseUrl(true).$this->getApiUrl().'orders/'.$apruveOrderId;
    }

    /**
     * Update an existing order by its ID in apruve
     *
     * This also determines if the order is from the frontend or the backend
     *
     * @param string $apruveOrderId
     * @param Mage_Sales_Model_Order $order
     *
     * @return string $result
     */
    public function updateOrder($apruveOrderId, $order)
    {
        if (Mage::app()->getStore()->isAdmin()) {
            return $this->_updateAdminOrder($apruveOrderId, $order);
        } else {
            return $this->_updateFrontendOrder($apruveOrderId, $order);
        }
    }

    /**
     * Update an existing admin order by its ID in apruve
     *
     * @param string $apruveOrderId
     * @param Mage_Sales_Model_Order $order
     *
     * @return string $result
     */
    protected function _updateAdminOrder($apruveOrderId, $order)
    {
        $result    = null;
        $lineItems = $this->_getLineItems($order);

        // get discount line item
        if (($discountItem = $this->_getDiscountItem($order))) {
            $lineItems[] = $discountItem;
        }

        $corporateAccount = Mage::getModel('apruvepayment/api_rest_account');
        $corporateAccount->getCorporateAccount($order->getCustomerEmail());
        $shopperId   = $corporateAccount->getShopperId($order->getCustomerEmail());
        $paymentTerm = $corporateAccount->getPaymentTerm();

        if ($shopperId) {
            $data = json_encode(
                array(
                    'order' => array(
                        'merchant_id'       => $this->getMerchantKey(),
                        'merchant_order_id' => $order->getIncrementId(),
                        'shopper_id'        => $shopperId,
                        'payment_term'      => $paymentTerm,
                        'amount_cents'      => $this->convertPrice($order->getBaseGrandTotal()),
                        'shipping_cents'    => $this->convertPrice($order->getBaseShippingAmount()),
                        'tax_cents'         => $this->convertPrice($order->getBaseTaxAmount()),
                        'invoice_on_create' => 'false',
                        'order_items'       => $lineItems
                    )
                )
            );

            $curlOptions                     = array();
            $curlOptions[CURLOPT_POSTFIELDS] = $data;

            if ($apruveOrderId === null) {
                $curlAction = 'POST';
            } else {
                $curlAction = 'PUT';
            }

            $result = $this->execCurlRequest($this->_getUpdateOrderUrl($apruveOrderId), $curlAction, $curlOptions);
            if ($result['success'] == true) {
                if ($apruveOrderId == null) {
                    $apruveOrderId = $result['response']['id'];
                }

                Mage::helper('apruvepayment')->logException('Order updated successfully...');
                $this->_updateOrderId($apruveOrderId, $order);
            }
        }

        return $result;
    }

    /**
     * Get Magento line items prepared for Apruve
     *
     * @param $lineItems Mage_Sales_Model_Order_Item
     *
     * @return $items array
     */
    protected function _getLineItems($order)
    {
        $items = array();

        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = array(
                'title'             => $item->getName(),
                'price_total_cents' => $item->getRowTotal() * 100,
                'price_ea_cents'    => $item->getPrice() * 100,
                'quantity'          => $item->getQtyOrdered(),
                'description'       => $item->getDescription(),
                'sku'               => $item->getSku(),
                'view_product_url'  => $item->getProduct()->getUrlInStore()
            );
        }

        return $items;
    }

    /**
     * Get url for update order
     *
     * @param string $apruveOrderId
     *
     * @return string
     */
    protected function _getUpdateOrderUrl($apruveOrderId)
    {
        return $this->getBaseUrl(true).$this->getApiUrl().'orders/'.$apruveOrderId;
    }

    /**
     * Update Apruve order id to it's corresponding order in magento
     *
     * @param $id string
     * @param $order Mage_Sales_Model_Order
     *
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
            Mage::helper('apruvepayment')->logException('Couldn\'t update the order: '.$e->getMessage());
            Mage::throwException(Mage::helper('apruvepayment')->__('Couldn\'t update order.'));
        }

        return true;
    }

    /**
     * Update an existing frontend order by its ID in apruve
     *
     * @param string $apruveOrderId
     * @param Mage_Sales_Model_Order $order
     *
     * @return string $result
     */
    protected function _updateFrontendOrder($apruveOrderId, $order)
    {
        $result    = null;
        $lineItems = $this->_getLineItems($order);

        // get discount line item
        if (($discountItem = $this->_getDiscountItem($order))) {
            $lineItems[] = $discountItem;
        }

        $data                            = json_encode(
            array(
                'order' => array(
                    'merchant_order_id' => $order->getIncrementId(),
                    'amount_cents'      => $this->convertPrice($order->getBaseGrandTotal()),
                    'shipping_cents'    => $this->convertPrice($order->getBaseShippingAmount()),
                    'tax_cents'         => $this->convertPrice($order->getBaseTaxAmount()),
                    'invoice_on_create' => 'false',
                    'order_items'       => $lineItems
                )
            )
        );
        $curlOptions                     = array();
        $curlOptions[CURLOPT_POSTFIELDS] = $data;
        $result                          = $this->execCurlRequest(
            $this->_getUpdateOrderUrl($apruveOrderId), 'PUT',
            $curlOptions
        );
        if ($result['success'] == true) {
            Mage::helper('apruvepayment')->logException('Order updated successfully...');
            $this->_updateOrderId($apruveOrderId, $order);
        }

        return $result;
    }

    /**
     * Get invoices from order
     *
     * @param $apruveOrderId string
     *
     * @return $invoices
     * @throws Mage_Core_Exception
     */
    public function getInvoices($apruveOrderId)
    {
        $result = $this->execCurlRequest($this->_getOrderInvoicesUrl($apruveOrderId));
        if ($result['success'] == true) {
            Mage::helper('apruvepayment')->logException('getInvoices...');

            return $result['response'];
        } else {
            Mage::throwException(Mage::helper('apruvepayment')->__('Couldn\'t get invoices from order.'));
        }

        return $result;
    }

    /**
     * Get url for order invoices
     *
     * @param string $apruveOrderId
     *
     * @return string
     */
    protected function _getOrderInvoicesUrl($apruveOrderId)
    {
        return $this->getBaseUrl(true).$this->getApiUrl().'orders/'.$apruveOrderId.'/invoices';
    }

    /**
     * Finalize an existing order by its ID in apruve
     *
     * @param $apruveOrderId string
     * @param $order Mage_Sales_Model_Order
     *
     * @return $result string
     */
    public function finalizeOrder($apruveOrderId, $order)
    {
        $result = $this->execCurlRequest($this->_getFinalizeOrderUrl($apruveOrderId), 'POST');
        if ($result['success'] == true) {
            $this->_updateOrderId($apruveOrderId, $order);
            Mage::helper('apruvepayment')->logException('Order finalized successfully...');
        }

        return $result;
    }

    /**
     * Get url for order finalizing
     *
     * @param string $apruveOrderId
     *
     * @return string
     */
    protected function _getFinalizeOrderUrl($apruveOrderId)
    {
        return $this->getBaseUrl(true).$this->getApiUrl().'orders/'.$apruveOrderId.'/finalize';
    }

    /**
     * Cancel an existing order by its ID in apruve
     *
     * @param $id string
     *
     * @return $result string
     */
    public function cancelOrder($apruveOrderId)
    {
        $result = $this->execCurlRequest($this->_getCancelOrderUrl($apruveOrderId), 'POST');

        return $result;
    }

    /**
     * Get url for order cancel
     *
     * @param string $apruveOrderId
     *
     * @return string
     */
    protected function _getCancelOrderUrl($apruveOrderId)
    {
        return $this->getBaseUrl(true).$this->getApiUrl().'orders/'.$apruveOrderId.'/cancel';
    }

    /**
     * Get order from quote
     *
     * @param $quote Mage_Sales_Model_Quote
     *
     * @return $order Mage_Sales_Model_Order
     * @throws Mage_Core_Exception
     */
    protected function _getOrderFromQuote($quote)
    {
        $orderIncrementId = $quote->getReservedOrderId();
        $order            = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        if (!$order->getId()) {
            Mage::throwException(Mage::helper('apruvepayment')->__('Couldn\'t load the order.'));
        }

        return $order;
    }
}
