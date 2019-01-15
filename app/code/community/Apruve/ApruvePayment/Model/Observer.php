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
 * This is an observer created to be triggered based on Magento's event
 * to update Apruve using Apruve's API calls.
 */
class Apruve_ApruvePayment_Model_Observer
{
    /**
     * Get order and payment objects from observer
     *
     * @param Varien_Event_Observer $observer
     * @return []
     */
    protected function _getOrderInfo($observer)
    {
        $order = null;
        $payment = null;

        if ($order = $observer->getEvent()->getOrder()) {
            $payment = $order->getPayment();
        } elseif ($orders = $observer->getEvent()->getOrders()) {
            if ($order = array_shift($orders)) {
                $payment = $order->getPayment();
            }
        }

        return array($order, $payment);
    }

    /**
     * Finalize the order in Apruve
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function finalizeOrder($observer)
    {
        list($order, $payment) = $this->_getOrderInfo($observer);

        if ($payment->getMethod() == Apruve_ApruvePayment_Model_PaymentMethod::PAYMENT_METHOD_CODE) {
            try {
                /**
                 * @var Apruve_ApruvePayment_Helper_Data $apiVersion
                 */
                $apiVersion = Mage::helper('apruvepayment')->getApiVersion();
                $additionalInformation = $payment->getAdditionalInformation();
                $token = $additionalInformation['aprt'];
                if ($token && !$order->getApruveOrderId()) {
                    /**
                     * @var Apruve_ApruvePayment_Model_Api_Rest_Order $orderApi
                     */
                    $orderApi = Mage::getModel('apruvepayment/api_rest_order');
                    $result = $orderApi->finalizeOrder($token, $order);
                    if(!$result || !$result['success']) {
                        Mage::throwException($result['message']);
                    }
                }
            } catch(Exception $e) {
                Mage::throwException($e->getMessage());
            }
        }

    }

    /**
     * Cancel the order in Apruve
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function cancelOrder($observer)
    {
        /**
         * @var Mage_Sales_Model_Order $order
         */
        list($order, $payment) = $this->_getOrderInfo($observer);

        if ($order->getId() && $payment->getMethod() == Apruve_ApruvePayment_Model_PaymentMethod::PAYMENT_METHOD_CODE) {
            $apruveEntity = Mage::getModel('apruvepayment/entity')->loadByOrderId($order->getIncrementId(), 'magento_id');
            $apruveOrderId = $apruveEntity->getApruveId();

            /**
             * @var Apruve_ApruvePayment_Model_Api_Rest_Order $orderApi
             */
            $orderApi = Mage::getModel('apruvepayment/api_rest_order');
            $result = $orderApi->cancelOrder($apruveOrderId);
        }
    }

    /**
     * Create invoice in Apruve
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function createInvoice($observer)
    {
        $invoice = $observer->getEvent()->getInvoice();

        /**
         * @var Mage_Sales_Model_Order $order
         */
        $order = $invoice->getOrder();
        $payment = $order->getPayment();

        if ($order->getId() && $invoice->getIncrementId()
            && $payment->getMethod() == Apruve_ApruvePayment_Model_PaymentMethod::PAYMENT_METHOD_CODE
        ) {
            /**
             * @var Apruve_ApruvePayment_Model_Api_Rest_Invoice $invoiceApi
             */
            $invoiceApi = Mage::getModel('apruvepayment/api_rest_invoice');

            $apruveEntity = Mage::getModel('apruvepayment/entity')->loadByInvoiceId($invoice->getIncrementId());
            $apruveInvoiceId = $apruveEntity->getApruveId();
            if($apruveInvoiceId && $invoice->getState() != Mage_Sales_Model_Order_Invoice::STATE_CANCELED) {
                $result = $invoiceApi->updateInvoice($apruveInvoiceId, $invoice);
            } elseif($apruveInvoiceId && $invoice->getState() == Mage_Sales_Model_Order_Invoice::STATE_CANCELED) {
                $result = $invoiceApi->cancelInvoice($apruveInvoiceId);
            } else {
                $apruveEntity = Mage::getModel('apruvepayment/entity')->loadByOrderId($order->getIncrementId());
                $apruveOrderId = $apruveEntity->getApruveId();
                $result = $invoiceApi->createInvoice($apruveOrderId, $invoice);
            }
        }
    }

    /**
     * Get Apruve Invoice from the shipment for an order in magento
     *
     * @param Mage_Sales_Model_Order_Shipment $shipment
     * @return Mage_Sales_Model_Order_Invoice
     */
    protected function _getInvoiceFromShipment($shipment)
    {
        $order = $shipment->getOrder();

        $shipmentDetails = array();
        foreach ($shipment->getAllItems() as $item) {
            $shipmentDetails[$item->getSku()] = $item->getQty();
        }

        $hasInvoices = $order->hasInvoices();
        $invoices = $order->getInvoiceCollection();

        /* if only one invoice is there then return it's apruve id */
        if($hasInvoices && $invoices->getSize() == 1) {
            return $invoices->getFirstItem();
        } elseif($hasInvoices) {
            /* if order has more invoices we have to select a matching invoice for the shipment */
            $apruveInvoice = '';
            foreach($invoices as $invoice) {
                $items = $invoice->getAllItems();
                $apruveInvoice = $invoice;
                foreach($items as $item) {
                    if($item->getQty() != $shipmentDetails[$item->getSku()]) {
                        $apruveInvoice = '';
                        break;
                    }
                }

                if($apruveInvoice) return $apruveInvoice;
            }
        }
    }

    /**
     * Get the list of items shipped with it's qty
     *
     * @param Mage_Sales_Model_Order_Shipment $shipment
     * @return []
     */
    protected function _getShippedItemQty($shipment)
    {
        $qtys = array();
        foreach($shipment->getAllItems() as $item) {
            $orderItem = $item->getOrderItem();
            $qtys[$orderItem->getId()] = $item->getQty();
        }

        return $qtys;
    }

    /**
     * Create Magento Invoice from the shipment for an order in magento
     *
     * @param Mage_Sales_Model_Order_Shipment $shipment
     * @return Mage_Sales_Model_Order_Invoice
     */
    protected function _createInvoiceFromShipment($shipment)
    {
        $order = $shipment->getOrder();

        try {
            $itemQty = $this->_getShippedItemQty($shipment);
            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice($itemQty);
            if (!$invoice->getTotalQty()) {
                return $invoice;
            }

            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::NOT_CAPTURE);
            $invoice->register();

            $invoice->getOrder()->setCustomerNoteNotify(false);
            $invoice->getOrder()->setIsInProcess(true);

            $transactionSave = Mage::getModel('core/resource_transaction')
                                   ->addObject($invoice)
                                   ->addObject($invoice->getOrder());

            $transactionSave->save();
        } catch (Mage_Core_Exception $e) {
            Mage::helper('apruvepayment')->logException($e->getMessage());
            throw new Exception($e->getMessage(), 1);
        }

        return $invoice;
    }

    /**
     * Create shipment in Apruve
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function createShipment($observer)
    {
        $shipment = $observer->getEvent()->getShipment();

        /**
         * @var Mage_Sales_Model_Order $order
         */
        $order = $shipment->getOrder();
        $payment = $order->getPayment();
        if ($order->getId() && $shipment->getIncrementId()
            && $payment->getMethod() == Apruve_ApruvePayment_Model_PaymentMethod::PAYMENT_METHOD_CODE
        ) {
            $apruveEntity = Mage::getModel('apruvepayment/entity')->loadByShipmentId($shipment->getIncrementId(), 'magento_id');
            $apruveShipmentId = $apruveEntity->getApruveId();

            if($apruveShipmentId) {
                $invoice = $this->_getInvoiceFromShipment($shipment);
            } else {
                $invoice = $this->_createInvoiceFromShipment($shipment);
            }

            /**
             * @var Apruve_ApruvePayment_Model_Api_Rest_Shipment $shipmentApi
             */
            $shipmentApi = Mage::getModel('apruvepayment/api_rest_shipment');
            $apruveEntity = Mage::getModel('apruvepayment/entity')->loadByInvoiceId($invoice->getIncrementId(), 'magento_id');
            $apruveInvoiceId = $apruveEntity->getApruveId();

            if($apruveShipmentId) {
                $result = $shipmentApi->updateShipment($apruveInvoiceId, $apruveShipmentId, $shipment, $invoice);
            } else {
                $result = $shipmentApi->createShipment($apruveInvoiceId, $shipment, $invoice);
            }
        }
    }
}