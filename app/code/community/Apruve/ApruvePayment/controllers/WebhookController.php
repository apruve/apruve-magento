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
        $paymentRequestId = $data->payment_request_id; //transaciton id

        //todo: compare status by rest request
        if($status == 'rejected') {
            if(!$this->_cancelOrder($paymentRequestId)) {
                header("HTTP/1.1 404 Not Found");
                exit;
            };
        } elseif($status == 'captured' ) {
            if(!$this->_addPayed($paymentRequestId)) {
                header("HTTP/1.1 404 Not Found");
                exit;
            };
        }


        header("HTTP/1.1 200");
        exit;
    }


    protected function _addPayed($paymentRequestId)
    {
        $transaction = Mage::getModel('sales/order_payment_transaction')->getCollection()
            ->addAttributeToFilter('txn_id', array('eq' => $paymentRequestId))
            ->getFirstItem();
        if(!$transaction->getId()) {
            return false;
        }

        $order = $transaction->getOrder();
        if(!$order->getId()) {
            return false;
        }

        $payment = $transaction->getOrder()->getPayment();
        if(!$payment->getId()) {
            exit;
        }
        $payment->capture(null);
        $order->save();

        $transaction->setOrderPaymentObject($payment);
        $transaction->setIsClosed(true);
        $transaction->save();
        return true;
    }


    protected function _cancelOrder($paymentRequestId)
    {
        $transaction = Mage::getModel('sales/order_payment_transaction')->getCollection()
            ->addAttributeToFilter('txn_id', array('eq' => $paymentRequestId))
            ->getFirstItem();
        if(!$transaction->getId()) {
            return false;
        }

        //todo: add customer notification
        $order = $transaction->getOrder();
        if(!$order->getId()) {
            return false;
        }
        $order->cancel();
        $order->save();

        $payment = $transaction->getOrder()->getPayment();
        $transaction->setOrderPaymentObject($payment);
        $transaction->setIsClosed(true);
        $transaction->save();
        return true;
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