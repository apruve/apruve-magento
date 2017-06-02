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
 * Class Apruve_ApruvePayment_Model_Api_Rest_Invoice
 *
 * Provide rest methods to communicate with apruve
 */
class Apruve_ApruvePayment_Model_Api_Rest_Invoice extends Apruve_ApruvePayment_Model_Api_Rest
{
    /**
     * Get url for invoice create
     * @param string $apruveOrderId
     * @return string
     */
    protected function _getCreateInvoiceUrl($apruveOrderId)
    {
        if($apruveOrderId === null){
            Mage::throwException(Mage::helper('apruvepayment')->__('No Apruve Order ID for _getCreateInvoiceUrl'));
        }
        return $this->getBaseUrl(true) . $this->getApiUrl() . 'orders/' . $apruveOrderId . '/invoices';
    }

    /**
     * Get url for invoice retrieve
     * @param string $apruveOrderId
     * @return string
     */
    protected function _getInvoiceUrl($apruveInvoiceId)
    {
        return $this->getBaseUrl(true) . $this->getApiUrl() . 'invoices/' . $apruveInvoiceId;
    }

    /**
     * Get url for invoice cancel
     * @param string $apruveOrderId
     * @return string
     */
    protected function _getCancelInvoiceUrl($apruveInvoiceId)
    {
        return $this->getBaseUrl(true) . $this->getApiUrl() . 'invoices/' . $apruveInvoiceId . '/cancel';
    }

    /**
     * Get url for invoice update
     * @param string $apruveOrderId
     * @return string
     */
    protected function _getUpdateInvoiceUrl($apruveInvoiceId)
    {
        return $this->getBaseUrl(true) . $this->getApiUrl() . 'invoices/' . $apruveInvoiceId;
    }

    /**
     * Retrieve an existing invoice by its ID in apruve
     *
     * @param $id string
     * @return $result string
     */
    public function getInvoice($apruveInvoiceId)
    {
        $result = $this->execCurlRequest($this->_getInvoiceUrl($apruveInvoiceId));
        return $result;
    }

    /**
     * Update Apruve invoice id to it's corresponding invoice in magento
     *
     * @param $id string
     * @param $invoice Mage_Sales_Model_Order_Invoice
     * @return bool
     * @throws Exception
     */
    protected function _updateInvoiceId($apruveInvoiceId, $apruveInvoiceItemIds, $invoice)
    {
        try {
            $apruveInvoiceItemIds = Mage::helper('core')->jsonEncode($apruveInvoiceItemIds);
            $apruveEntity = Mage::getModel('apruvepayment/entity')->loadByInvoiceId($invoice->getIncrementId());
            $apruveEntity->setApruveId($apruveInvoiceId);
            $apruveEntity->setApruveItemId($apruveInvoiceItemIds);
            $apruveEntity->setMagentoId($invoice->getIncrementId());
            $apruveEntity->setEntityType('invoice');
            $apruveEntity->save();
        } catch(Exception $e) {
            Mage::helper('apruvepayment')->logException($e->getMessage());
            Mage::throwException(Mage::helper('apruvepayment')->__('Couldn\'t update invoice.'));
        }
        return true;
    }

    /**
     * Retrieve the latest comment from magento invoice
     *
     * @param $invoice Mage_Sales_Model_Order_Invoice
     * @return $comment Mage_Sales_Model_Order_Invoice_Comment
     */
    protected function _getInvoiceComment($invoice)
    {
        $comment = Mage::getResourceModel('sales/order_invoice_comment_collection')
                ->setInvoiceFilter($invoice->getId())
                ->setOrder('created_at', 'DESC')
                ->setPageSize(1)
                ->getFirstItem();

        return $comment;
    }

    /**
     * Prepare invoice data for Apruve
     *
     * @param $shipment Mage_Sales_Model_Order_Invoice
     * @return $data []
     */
    protected function _getInvoiceData($invoice)
    {
        $invoiceItems = Mage::helper('apruvepayment')->getAllVisibleItems($invoice);

        $items = [];
        foreach($invoiceItems as $invoiceItem) {
            $orderItem = $invoiceItem->getOrderItem();
            /* create invoice item for apruve */
            $item = [];
            $item['price_ea_cents'] = $this->convertPrice($invoiceItem->getBasePrice());
            $item['quantity'] = $invoiceItem->getQty();
            $item['price_total_cents'] = $this->convertPrice($invoiceItem->getBaseRowTotal());
            $item['currency'] = $this->getCurrency();
            $item['title'] = $invoiceItem->getName();
            $item['merchant_notes'] = $invoiceItem->getAdditionalData();
            $item['description'] = $invoiceItem->getDescription();
            $item['sku'] = $invoiceItem->getSku();
            $item['variant_info'] = $orderItem->getProductOptions();
            $item['vendor'] = $this->getVendor($orderItem);
            /* add invoice item to $items array */
            $items[] = $item;
        }
        // get discount line item
        if(($discountItem = $this->_getDiscountItem($invoice))) {
            $items[] = $discountItem;
        }

        /* latest shipment comment */
        $comment = $this->_getInvoiceComment($invoice);

        /* prepare invoice data */
        $data = json_encode([
            'invoice' => [
                'amount_cents' => $this->convertPrice($invoice->getBaseGrandTotal()),
                'currency' => $this->getCurrency(),
                'shipping_cents' => $this->convertPrice($invoice->getBaseShippingAmount()),
                'tax_cents' => $this->convertPrice($invoice->getBaseTaxAmount()),
                'merchant_notes' => $comment->getComment(),
                'merchant_invoice_id' => $invoice->getIncrementId(),
                //'due_at' => '2016-06-01T13:54:21Z',
                'invoice_items' => $items,
                'issue_on_create' => true
            ]
        ]);

        return $data;
    }

    /**
     * Create new invoice in Apruve for an order based on invoice created in Magento
     *
     * @param $apruveOrderId string
     * @return $result string[]
     */
    public function createInvoice($apruveOrderId, $invoice)
    {
        $data = $this->_getInvoiceData($invoice);

        $curlOptions = [];
        $curlOptions[CURLOPT_POSTFIELDS] = $data;

        $result = $this->execCurlRequest($this->_getCreateInvoiceUrl($apruveOrderId), 'POST', $curlOptions);
        $apruveInvoiceId = isset($result['response']['id']) ? $result['response']['id'] : '';
        $apruveInvoiceItemIds = isset($result['response']['invoice_items']) ? $result['response']['invoice_items'] : '';
        if($result['success'] == true) {
            $this->_updateInvoiceId($apruveInvoiceId, $apruveInvoiceItemIds, $invoice);
        }

        return $result;
    }

    /**
     * Update an existing invoice in Apruve
     *
     * @param $id string
     * @return $result string
     */
    public function updateInvoice($apruveInvoiceId, $invoice)
    {
        $data = $this->_getInvoiceData($invoice);

        $curlOptions = [];
        $curlOptions[CURLOPT_POSTFIELDS] = $data;

        $result = $this->execCurlRequest($this->_getUpdateInvoiceUrl($apruveInvoiceId), 'PUT', $curlOptions);

        return $result;
    }

    /**
     * Retrieve an existing invoice item IDS based on its ID in apruve
     *
     * @param $id string
     * @return $result string
     */
    public function getInvoiceItemIds($apruveInvoiceId)
    {
        $invoice = $this->getInvoice($apruveInvoiceId);
        $invoiceArray = json_decode($invoice);
        $items = [];
        foreach($invoiceArray['invoice_items'] as $item) {
            $items[] = $item['id'];
        }
        return $items;
    }

    /**
     * cancel an existing invoice by its ID in apruve
     *
     * @param $id string
     * @return $result string
     */
    public function cancelInvoice($apruveInvoiceId)
    {
        $result = $this->execCurlRequest($this->_getCancelInvoiceUrl($apruveInvoiceId), 'POST');
        return $result;
    }


    /**
     * refund an existing invoice by its ID in apruve
     *
     * @param $id string
     * @return $result string
     */
    public function refundInvoice($apruveInvoiceId, $amount)
    {

        $data = json_encode(array(
            "amount_cents" => ($amount * 100),
            "currency" => "USD",
            "reason" => "OTHER"
        ));
        $curlOptions = [];
        $curlOptions[CURLOPT_POSTFIELDS] = $data;

        $result = $this->execCurlRequest($this->_getInvoiceRefundUrl($apruveInvoiceId), 'POST', $curlOptions);

        return $result;
    }

    /**
     * Get url for invoice refund
     * @param string $apruveOrderId
     * @return string
     */
    protected function _getInvoiceRefundUrl($apruveInvoiceId)
    {
        return $this->getBaseUrl(true) . $this->getApiUrl() . 'invoices/' . $apruveInvoiceId . '/invoice_returns';
    }

}