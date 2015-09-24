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
class Apruve_ApruvePayment_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'apruvepayment';
    protected $_formBlockType = 'apruvepayment/payment_form';

    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canVoid = true;
    protected $_canUseCheckout = true;
    protected $_canCreateBillingAgreement = true;

    /**
     * Assign data to info model instance
     *
     * @param   mixed $data
     * @return  Mage_Payment_Model_Info
     */
    public function assignData($data)
    {
        //$result = parent::assignData($data);
        if (is_array($data)) {
            $this->getInfoInstance()->setAdditionalInformation('aprt', isset($data['aprt']) ? $data['aprt'] : null);
        } elseif ($data instanceof Varien_Object) {
            $aprt = $data->getAprt();
            $this->getInfoInstance()->setAdditionalInformation('aprt', isset($aprt) ? $aprt : null);
        }
        return $this;
    }

    /**
     * Check whether apruve payment request id(aprt) is exitst
     * @return Mage_Payment_Model_Abstract|void
     */
    public function validate()
    {
        if (!$this->getInfoInstance()->getAdditionalInformation('aprt')) {
            Mage::throwException('Smth going wrong, try again to post order with apruve');
        }
    }

    /**
     * Get token and create transaction
     * @param Varien_Object $payment
     * @param float $amount
     * @return Mage_Payment_Model_Abstract|void
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        $additionalInformation = $payment->getAdditionalInformation();
        $token = $additionalInformation['aprt'];
        /** @var Apruve_ApruvePayment_Model_Api_Rest $rest */
        $rest = Mage::getModel('apruvepayment/api_rest');
        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();
        $amounts = Mage::helper('apruvepayment')->getAmountsFromQuote($order->getQuote());
        $oldAmounts = Mage::getSingleton('checkout/session')->getApruveAmounts();
        if ($oldAmounts != $amounts) {
            $updateResult = $rest->updatePaymentRequest(
                $token,
                $amounts['amount_cents'],
                $amounts['tax_cents'],
                $amounts['shipping_cents']
            );
            Mage::getSingleton('checkout/session')->setApruveAmounts($amounts);
            if (!$updateResult) {
                Mage::throwException('Couldn\'t update order totals to Apruve');
            }
        }

        $apruvePayment = $rest->postPayment($token, $amount);
        if (!$apruvePayment) {
            Mage::throwException('Apruve couldn\'t process order information');
        }

        $payment->setTransactionId($token . "_" . $apruvePayment->id)
            ->setIsTransactionClosed(0);
        return $this;
    }

    public function capture(Varien_Object $payment, $amount)
    {
        if ($amount <= 0) {
            Mage::throwException(Mage::helper('paygate')->__('Invalid amount for capture.'));
        }

        $payment->setAmount($amount)
            ->setTransactionId($payment->getParentTransactionId() . '_capture');
        return $this;
    }
}
