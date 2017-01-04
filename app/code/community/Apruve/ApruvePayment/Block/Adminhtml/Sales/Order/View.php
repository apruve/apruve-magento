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
 * @copyright  Copyright (coffee) 2017 Apruve, Inc. (http://www.apruve.com).
 * @license    http://opensource.org/licenses/Apache-2.0  Apache License, Version 2.0
 * @author     Echidna Team
 *
 */

/**
 * Adminhtml sales order view
 */
class Apruve_ApruvePayment_Block_Adminhtml_Sales_Order_View extends Mage_Adminhtml_Block_Sales_Order_View
{

    protected function _isAllowedAction($action)
    {
        if($action == 'invoice') {
            $order = $this->getOrder();
            if($order->getPayment()->getMethod() == Apruve_ApruvePayment_Model_PaymentMethod::PAYMENT_METHOD_CODE) {
                return false;
            }
        }
        return parent::_isAllowedAction($action);
    }
}
