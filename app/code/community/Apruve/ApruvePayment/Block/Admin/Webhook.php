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
class Apruve_ApruvePayment_Block_Admin_Webhook extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $merchantKey = Mage::getStoreConfig('payment/apruvepayment/merchant');
        $apiKey      = Mage::getStoreConfig('payment/apruvepayment/api');
        if ($merchantKey !== null && $apiKey !== null) {
            return Mage::app()->getDefaultStoreView()->getUrl(
                "apruvepayment/webhook/updateOrderStatus",
                array('_query' => array(hash('sha256', $apiKey.$merchantKey) => 1))
            );
        } else {
            $message = 'Please, specify merchant and api key';

            return $message;
        }
    }
}