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


class Apruve_ApruvePayment_Model_Api_Payment extends Apruve_ApruvePayment_Model_Api_Abstract
{
    /** @var Mage_Sales_Model_Order  */
    private $order;

    /** @var  Mage_Sales_Model_Quote */
    private $quote;

    private $amounts;

    function __construct(Mage_Sales_Model_Order $order)
    {
        $this->order = $order;
        $this->quote = $order->getQuote();
    }

    /**
     * Return amount_cents, shipping_cents or tax_cents
     * @param $key
     * @return float | bool
     */
    public function getAmount($key)
    {
        if (empty($this->amounts)) {
            $this->amounts = $this->getAmountsFromOrder($this->order);
        }

        if (isset($this->amounts[$key])) {
            return $this->amounts[$key];
        }

        return false;
    }

    /**
     * Generate payment request by given order
     *
     * @return array
     */
    public function getPayment()
    {
        return array(
            'amount_cents' => $this->convertPrice($this->getAmount('amount_cents')),
            'payment_items' => $this->getLineItems($this->order),
            'issue_on_create' => !$this->quote->getIsMultiShipping()
        );
    }

    /**
     * Build Line items array
     * @param Mage_Sales_Model_Order $itemsParent
     * @return array
     */
    protected function getLineItems($itemsParent)
    {
        $result = array();
        /** @var  Mage_Sales_Model_Order_Item[] $visibleItems */
        $visibleItems = $itemsParent->getAllVisibleItems();
        foreach ($visibleItems as $item) {

            $result[] = array(
                'title' => $item->getName(),
                'amount_cents' => $this->convertPrice($item->getPrice()) * $item->getQtyOrdered(),
                'price_ea_cents' => $this->convertPrice($item->getPrice()),
                'quantity' => $item->getQtyOrdered(),
                'description' => $this->getShortDescription($item),
                'variant_info' => $this->getVariantInfo($item),
                'sku' => $item->getSku(),
                'view_product_url' => $item->getProduct()->getProductUrl(false),
            );

        }

        return $result;
    }
}
