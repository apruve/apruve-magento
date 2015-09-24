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
        $q = $this->_getHashedQueryString();

        if(!isset($_GET[$q])) {
            //do nothing
            header("HTTP/1.1 404 Not Found");
            exit;
        }

        $input = file_get_contents('php://input');
        $data = json_decode($input);

        $status = $data->status;
        $paymentRequestId = $data->payment_request_id;
        $paymentId = $data->payment_id;
        Mage::log($data, null, 'webtex.log');

        //todo: compare status by rest request
        if($status == 'rejected') {
            if(!$this->_cancelOrder($paymentRequestId, $paymentId)) {
                header("HTTP/1.1 404 Not Found");
                exit;
            };
        } elseif($status == 'captured' ) {
            if(!$this->_addPayed($paymentRequestId, $paymentId)) {
                header("HTTP/1.1 404 Not Found");
                exit;
            };
        }


        header("HTTP/1.1 200");
        exit;
    }


    protected function _addPayed($paymentRequestId, $paymentId)
    {
        /** @var Mage_Sales_Model_Order_Payment_Transaction $transaction */
        $transaction = Mage::getModel('sales/order_payment_transaction')->getCollection()
            ->addAttributeToFilter('txn_id', array('eq' => $paymentRequestId . "_" . $paymentId))
            ->getFirstItem();
        if (!$transaction->getId()) {
            /** @var Mage_Sales_Model_Order_Payment_Transaction $transaction */
            $transaction = Mage::getModel('sales/order_payment_transaction')->getCollection()
                ->addAttributeToFilter('txn_id', array('eq' => $paymentRequestId))
                ->getFirstItem();
        }
        if ($transaction->getId()) {
            $order = $transaction->getOrder();
            /** @var Mage_Sales_Model_Order_Invoice_Api $iApi */
            $iApi = Mage::getModel('sales/order_invoice_api');
            $invoiceId = $iApi->create($order->getIncrementId(), array());
            $iApi->capture($invoiceId);
            return true;
        }

        return false;
    }


    protected function _cancelOrder($paymentRequestId, $paymentId)
    {
        /** @var Mage_Sales_Model_Order_Payment_Transaction $transaction */
        $transaction = Mage::getModel('sales/order_payment_transaction')->getCollection()
            ->addAttributeToFilter('txn_id', array('eq' => $paymentRequestId . "_" . $paymentId))
            ->getFirstItem();
        if (!$transaction->getId()) {
            /** @var Mage_Sales_Model_Order_Payment_Transaction $transaction */
            $transaction = Mage::getModel('sales/order_payment_transaction')->getCollection()
                ->addAttributeToFilter('txn_id', array('eq' => $paymentRequestId))
                ->getFirstItem();
        }
        if ($transaction->getId()) {
            $payment = $transaction->getOrder()->getPayment();
            $transaction->setOrderPaymentObject($payment);
            $transaction->setIsClosed(true);
            $transaction->save();
            $order = $transaction->getOrder();
            if($order && $order->getId() && !$order->isCanceled()) {
                $order->cancel();
                $order->save();
                return true;
            }
        }
        return false;
    }


    protected function _getHashedQueryString()
    {
        $merchantKey = Mage::getStoreConfig('payment/apruvepayment/merchant');
        $apiKey = Mage::getStoreConfig('payment/apruvepayment/api');
        $data = $apiKey.$merchantKey;
        $q = hash('sha256', $data);
        return $q;
    }
}
