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
 * This is for providing a new payment gateway (Apruve) for checkout.
 */
class Apruve_ApruvePayment_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{
    const PAYMENT_METHOD_CODE = 'apruvepayment';

    protected $_code = self::PAYMENT_METHOD_CODE;
    protected $_formBlockType = 'apruvepayment/payment_form';

    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canVoid = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canCreateBillingAgreement = true;
    protected $_isGateway = true;
    protected $_canManageRecurringProfiles = false;
    protected $_canUseForMultishipping = false;
    protected $_canReviewPayment = true;

    /**
     * Can edit order (renew order)
     *
     * @return bool
     */
    public function canEdit()
    {
        return false;
    }

    /**
     * Assign data to info model instance
     *
     * @param   mixed $data
     * @return  Mage_Payment_Model_Info
     */
    public function assignData($data)
    {
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
     * @throws Mage_Core_Exception
     */
    public function validate()
    {
        parent::validate();

        if (Mage::app()->getStore()->isAdmin()) {
            $paymentInfo = $this->getInfoInstance();
            if ($paymentInfo instanceof Mage_Sales_Model_Order_Payment) {
                $billingCountry = $paymentInfo->getOrder()->getBillingAddress()->getCountryId();
            } else {
                $billingCountry = $paymentInfo->getQuote()->getBillingAddress()->getCountryId();
            }
            if (!$this->canUseForCountry($billingCountry)) {
                Mage::throwException($this->_getHelper()->__('Selected payment type is not allowed for billing country.'));
            }
            return $this;
        } elseif (!$this->getInfoInstance()->getAdditionalInformation('aprt')) {
            Mage::throwException('Something is going wrong, try again to post order with apruve.');
        }
    }

    /**
     * Get token and create transaction
     * @param Varien_Object $payment
     * @param float $amount
     * @return Mage_Payment_Model_Abstract|void
     * @throws Mage_Core_Exception
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        parent::authorize($payment, $amount);
        Mage::helper('apruvepayment')->logException('Authorize...');

        $additionalInformation = $payment->getAdditionalInformation();
        $token = $additionalInformation['aprt'];

        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();

        /** @var Apruve_ApruvePayment_Helper_Data $apiVersion */
        $apiVersion = Mage::helper('apruvepayment')->getApiVersion();

        /** @var Apruve_ApruvePayment_Model_Api_Rest_Order $orderApi */
        $orderApi = Mage::getModel('apruvepayment/api_rest_order');


        $updateResult = $orderApi->updateOrder($token, $order);
        if (!$updateResult || !$updateResult['success']) {
            Mage::throwException('Couldn\'t update order in Apruve.');
        }

        return $this;
    }

    /**
     * Captures a payment
     *
     * @param   Varien_Object $payment
     * @return  bool
     * @throws  Mage_Core_Exception
     */
    public function capture(Varien_Object $payment, $amount)
    {
        parent::capture($payment, $amount);
        Mage::helper('apruvepayment')->logException('Capture...');

        if ($amount <= 0) {
            Mage::throwException(Mage::helper('paygate')->__('Invalid amount for capture.'));
        }

        $payment->setSkipTransactionCreation(true);
        return $this;
    }

    /**
     * Check void availability
     *
     * @param   Varien_Object $payment
     * @return  bool
     */
    public function canVoid(Varien_Object $payment)
    {
        if ($payment instanceof Mage_Sales_Model_Order_Invoice
            || $payment instanceof Mage_Sales_Model_Order_Creditmemo
        ) {
            return false;
        }
        if ($payment->getAmountPaid()) {
            $this->_canVoid = false;
        }

        return true;
    }

    /**
     * Attempt to void the authorization on cancelling
     *
     * @param Varien_Object $payment
     * @return Apruve_ApruvePayment_Model_PaymentMethod | false
     */
    public function cancel(Varien_Object $payment)
    {
        Mage::helper('apruvepayment')->logException('Cancel...');

        if (!$payment->getOrder()->getInvoiceCollection()->count()) {
            return $this->void($payment);
        }

        return false;
    }
}
