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
 * @author     Echidna Team
 *
 */

/**
 * Order submit service model
 * Fix for the Magento version < 1.9.2.0
 */
class Apruve_ApruvePayment_Model_Sales_Service_Order extends Mage_Sales_Model_Service_Order
{
    /**
     * Prepare order invoice based on order data and requested items qtys. If $qtys is not empty - the function will
     * prepare only specified items, otherwise all containing in the order.
     *
     * @param array $qtys
     *
     * @return Mage_Sales_Model_Order_Invoice
     */
    public function prepareInvoice($qtys = array())
    {
        if (version_compare(Mage::getVersion(), '1.9.2.0', '<')) {
            $invoice  = $this->_convertor->toInvoice($this->_order);
            $totalQty = 0;
            foreach ($this->_order->getAllItems() as $orderItem) {
                $qty = 0;
                if (! $this->_canInvoiceItem($orderItem, array())) {
                    continue;
                }

                $item = $this->_convertor->itemToInvoiceItem($orderItem);
                if ($orderItem->isDummy()) {
                    $qty = $orderItem->getQtyOrdered() ? $orderItem->getQtyOrdered() : 1;
                } else if (! empty($qtys)) {
                    if (isset($qtys[$orderItem->getId()])) {
                        $qty = (float)$qtys[$orderItem->getId()];
                    }
                } else {
                    $qty = $orderItem->getQtyToInvoice();
                }

                $totalQty += $qty;
                $item->setQty($qty);
                $invoice->addItem($item);
            }

            $invoice->setTotalQty($totalQty);
            $invoice->collectTotals();
            $this->_order->getInvoiceCollection()->addItem($invoice);
        } else {
            $invoice = parent::prepareInvoice($qtys);
        }

        return $invoice;
    }
}
