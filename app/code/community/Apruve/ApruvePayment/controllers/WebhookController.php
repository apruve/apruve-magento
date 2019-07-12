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
class Apruve_ApruvePayment_WebhookController extends Mage_Core_Controller_Front_Action
{
    public function updateOrderStatusAction()
    {
        $hash = $this->_getHashedQueryString();
        $identifier = key(array_slice(Mage::app()->getRequest()->getParams(), 0, 1, TRUE));


        // if the hash doesn't match the data sent by Apruve terminate the code
        if ($identifier != $hash) {
            $this->getResponse()->setHeader("HTTP/1.1", "404", true);
            return;
        }

        // if the hash matches the data sent by Apruve move forward with the appropriate process
        $input = file_get_contents('php://input');
        $data  = json_decode($input);

        Mage::helper('apruvepayment')->logException($data);
        try {
            $event  = $data->event;
            $entity = $data->entity;

            // check the event triggered in Apruve to call appropriate action in Magento
            if ($event == 'invoice.funded' || $event == 'invoice.closed') {
                $invoiceId = $entity->merchant_invoice_id;
                if (! $this->_capturePayment($invoiceId)) {
                    $this->getResponse()->setHeader("HTTP/1.1", "404", true);
                    return;
                };
            } elseif ($event == 'payment_term.accepted') {
                return; // should not be triggering anything in magento
            } elseif ($event == 'order.canceled') {
                $orderId = $entity->merchant_order_id;
                if (! $this->_cancelOrder($orderId)) {
                    $this->getResponse()->setHeader("HTTP/1.1", "404", true);
                    return;
                };
            } elseif ($event == 'order.accepted') {
                $orderId = $entity->merchant_order_id;
                if (! $this->_paymentTermAccepted($orderId)) {
                    $this->getResponse()->setHeader("HTTP/1.1", "404", true);
                    return;
                };
            }
        } catch (Exception $e) {
            Mage::helper('apruvepayment')->logException('Error for transaction UUID: ' . $data->uuid . '. Message: ' . $e->getMessage());
        }

        header("HTTP/1.1 200");
        return;
    }

    /**
     * Capture payment based on invoice increment ID
     *
     * @param string $orderId
     *
     * @return bool
     */
    protected function _capturePayment($invoiceId)
    {
        try {
            if ($invoiceId) {
                /** @var Mage_Sales_Model_Order_Invoice_Api $iApi */
                $iApi = Mage::getModel('sales/order_invoice_api');
                $invoice = Mage::getModel('sales/order_invoice')->loadByIncrementId($invoiceId);

                if ($invoice && $invoice->canCapture()) {
                    $iApi->capture($invoiceId);
                };
            }

            return true;
        }  catch (Exception $e) {
            Mage::log("Cannot find this invoice in Magento - possible duplicate webhook - capturePayment - InvoiceId: {$invoiceId}");
        }

        return false;
    }

    /**
     * Change the order status based on the order increment ID
     *
     * @param string $orderId
     *
     * @return bool
     */
    protected function _changeOrderStatus($orderId)
    {
        try {
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            Mage::helper('apruvepayment')->logException($order->getData());
            Mage::helper('apruvepayment')->logException($orderId);

            if ($order && $order->getId() && ! $order->isCanceled()) {
                Mage::helper('apruvepayment')->logException('creating invoice...');
                $result = $this->_createInvoice($order->getIncrementId());
            }

            return $result;
        } catch (Exception $e) {
            Mage::log("Cannot find this order in Magento - possible duplicate webhook - changeOrderStatus - OrderId: {$orderId}");
        }

        return false;
    }

    /**
     * Change the order status based on the order increment ID
     *
     * @param string $orderId
     *
     * @return bool
     */
    protected function _paymentTermAccepted($orderId)
    {
        try {
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            Mage::helper('apruvepayment')->logException($order->getData());
            Mage::helper('apruvepayment')->logException($orderId);

            if ($order && $order->getId()) {
                Mage::helper('apruvepayment')->logException('creating payment accepted...');

                $order->setStatus('buyer_approved');

                $order->save();
            }

            return true;
        } catch (Exception $e) {
            Mage::log("Cannot find this order in Magento - possible duplicate webhook - paymentTermAccepted - OrderId: {$orderId}");
        }


        return false;
    }

    /**Ball
     * Change the order status based on the order increment ID
     *
     * @param string $orderId
     *
     * @return bool
     */
    protected function _createInvoice($orderId)
    {
        try {
            if ($orderId) {
                /** @var Mage_Sales_Model_Order_Invoice_Api $iApi */
                $iApi      = Mage::getModel('sales/order_invoice_api');
                $invoiceId = $iApi->create($orderId, array());

                return true;
            }
        } catch (Exception $e) {
            Mage::log("Cannot find this invoice in Magento - possible duplicate webhook - createInvoice - OrderId: {$orderId}");
        }

        return false;
    }

    /**
     * cancel and order in magento based on Order Increment Id
     *
     * @param string $orderId
     *
     * @return bool
     */
    protected function _cancelOrder($orderId)
    {
        try {
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            Mage::log("orderId: {$orderId}");
            Mage::log(var_dump($order));
            if ($order && $order->getId() && ! $order->isCanceled()) {
                $order->cancel();
                $order->save();
            }

            return true;
        } catch (Exception $e) {
            Mage::log("Cannot find this entity in Magento - possible duplicate webhook - cancelOrder - OrderId: {$orderId}");
            Mage::log("Message: {$e->getMessage()}");
            Mage::log("Message: {$e->getTraceAsString()}");
        }

        return false;
    }

    /**
     * Order and transaction
     *
     * @param string $paymentRequestId
     * @param string $paymentId
     *
     * @return bool
     */
    protected function _addPayed($paymentRequestId, $paymentId)
    {
        /** @var Mage_Sales_Model_Order_Payment_Transaction $transaction */
        $transaction = Mage::getModel('sales/order_payment_transaction')->getCollection()
                           ->addAttributeToFilter('txn_id', array('eq' => $paymentRequestId . "_" . $paymentId))
                           ->getFirstItem();
        if (! $transaction->getId()) {
            /** @var Mage_Sales_Model_Order_Payment_Transaction $transaction */
            $transaction = Mage::getModel('sales/order_payment_transaction')->getCollection()
                               ->addAttributeToFilter('txn_id', array('eq' => $paymentRequestId))
                               ->getFirstItem();
        }

        if ($transaction->getId()) {
            $order = $transaction->getOrder();
            /** @var Mage_Sales_Model_Order_Invoice_Api $iApi */
            $iApi      = Mage::getModel('sales/order_invoice_api');
            $invoiceId = $iApi->create($order->getIncrementId(), array());
            $iApi->capture($invoiceId);

            return true;
        }

        return false;
    }

    /**
     * Get the hashed string id based on Apruve merchant id and API key
     *
     * @return string
     */
    protected function _getHashedQueryString()
    {
        $merchantKey = Mage::getStoreConfig('payment/apruvepayment/merchant');
        $apiKey      = Mage::getStoreConfig('payment/apruvepayment/api');
        $data        = $apiKey . $merchantKey;
        $hash        = hash('sha256', $data);

        return $hash;
    }
}
