<?php

class Apruve_ApruvePayment_Model_Observer
{

    public function finalizePayment($observer)
    {
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = $observer->getQuote();
        $payment = $quote->getPayment();
        if ($quote->getIsMultiShipping()
            && $payment->getMethod() == 'apruvepayment'
        ) {
            $additionalInformation = $payment->getAdditionalInformation();
            $token = $additionalInformation['aprt'];
            if ($token) {
                /** @var Apruve_ApruvePayment_Model_Api_Rest $apiHelper */
                $apiHelper = Mage::getModel('apruvepayment/api_rest');
                $apiHelper->finalizePaymentRequest($token);
            }
        }
    }

}