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

    protected $_isGateway                   = false;
    protected $_canOrder                    = true;
    protected $_canAuthorize                = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = false;
    protected $_canRefund                   = false;
    protected $_canRefundInvoicePartial     = false;
    protected $_canVoid                     = true;
    protected $_canUseInternal              = false;
    protected $_canUseCheckout              = true;
    protected $_canUseForMultishipping      = false;
    protected $_canFetchTransactionInfo     = true;
    protected $_canCreateBillingAgreement   = true;
    protected $_canReviewPayment            = true;

    public function getCheckoutRedirectUrl()
    {
        return Mage::getUrl('apruvepayment/payment/review');
    }


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
        }
        elseif ($data instanceof Varien_Object) {
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
        if(!$this->getInfoInstance()->getAdditionalInformation('aprt')) {
            Mage::throwException('Smth going wrong, try again to post order with apruve');
        }
    }

    /**
     * @return string
     */
    public function getConfigPaymentAction()
    {
        return 'order';
    }

    /**
     * Get token and create transaction
     * @param Varien_Object $payment
     * @param float $amount
     * @return Mage_Payment_Model_Abstract|void
     */
    public function order(Varien_Object $payment, $amount)
    {
        $additionalInformation = $payment->getAdditionalInformation();
        $token =  $additionalInformation['aprt'];

        //$amount = $payment->getBaseAmountOrdered() * 100; //cents
        //$rest = Mage::getModel('apruvepayment/api_rest');
        //$rest->postPayment($token, $amount); //return true or false
        //$payment->setSkipOrderProcessing(true);
        //echo 1;exit;

        $payment->setTransactionId($token);
        $payment->setIsTransactionClosed(0);
        $payment->resetTransactionAdditionalInfo();
    }


    public function capture(Varien_Object $payment, $amount)
    {

    }
}
