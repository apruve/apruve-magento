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
 * Class Apruve_ApruvePayment_Model_Api_Rest_Shipment
 *
 * Provide rest methods to communicate with apruve
 */
class Apruve_ApruvePayment_Model_Api_Rest_Shipment extends Apruve_ApruvePayment_Model_Api_Rest
{
    /**
     * Get url for shipment create
     * @param string $apruveInvoiceId
     * @return string
     */
    protected function _getCreateShipmentUrl($apruveInvoiceId)
    {
        return $this->getBaseUrl(true) . $this->getApiUrl() . 'invoices/' . $apruveInvoiceId . '/shipments';
    }

    /**
     * Get url for shipment retrieve
     * @param string $apruveInvoiceId
     * @param string $apruveShipmentId
     * @return string
     */
    protected function _getShipmentUrl($apruveInvoiceId, $apruveShipmentId)
    {
        return $this->getBaseUrl(true) . $this->getApiUrl() . 'invoices/' . $apruveInvoiceId . '/shipments/' . $apruveShipmentId;
    }

    /**
     * Get url for shipment update
     * @param string $apruveInvoiceId
     * @param string $apruveShipmentId
     * @return string
     */
    protected function _getUpdateShipmentUrl($apruveInvoiceId, $apruveShipmentId)
    {
        return $this->getBaseUrl(true) . $this->getApiUrl() . 'invoices/' . $apruveInvoiceId . '/shipments/' . $apruveShipmentId;
    }

    /**
     * Retrieve an existing shipment by its ID in apruve
     *
     * @param $id string
     * @return $result string
     */
    public function getShipment($apruveInvoiceId, $apruveShipmentId)
    {
        $result = $this->execCurlRequest($this->_getShipmentUrl($apruveInvoiceId, $apruveShipmentId));
        return $result;
    }

    /**
     * Retrieve the latest comment from magento shipment
     *
     * @param $shipment Mage_Sales_Model_Order_Shipment
     * @return $comment Mage_Sales_Model_Order_Shipment_Comment
     */
    protected function _getShipmentComment($shipment)
    {
        $comment = Mage::getResourceModel('sales/order_shipment_comment_collection')
                ->setShipmentFilter($shipment->getId())
                ->setOrder('created_at', 'DESC')
                ->setPageSize(1)
                ->getFirstItem();

        return $comment;
    }

    /**
     * Retrieve the latest tracking info from magento shipment
     *
     * @param $shipment Mage_Sales_Model_Order_Shipment
     * @return $comment Mage_Sales_Model_Order_Shipment_Track
     */
    protected function _getShipmentTrack($shipment)
    {
        $track = Mage::getResourceModel('sales/order_shipment_track_collection')
                ->setShipmentFilter($shipment->getId())
                ->setOrder('updated_at', 'DESC')
                ->setPageSize(1)
                ->getFirstItem();

        return $track;
    }

    /**
     * Prepare shipment data for Apruve
     *
     * @param $shipment Mage_Sales_Model_Order_Shipment
     * @param $invoice Mage_Sales_Model_Order_Invoice
     * @return $data []
     */
    protected function _getShipmentData($shipment, $invoice)
    {
        $apruveEntity = Mage::getModel('apruvepayment/entity')->loadByInvoiceId($invoice->getIncrementId());
        $items = $apruveEntity->getShippedInvoiceItemIds($shipment);

        /* latest shipment comment */
        $comment = $this->_getShipmentComment($shipment);
        /* latest shipment tracking */
        $trackingInfo = $this->_getShipmentTrack($shipment);
        /* prepare invoice data */
        $data = json_encode([
            'amount_cents' => $this->convertPrice($invoice->getBaseGrandTotal()),
            'shipper' => $trackingInfo->getTitle(),
            'tracking_number' => $trackingInfo->getTrackNumber(),
            'shipped_at' => $this->getDateFormatted($trackingInfo->getCreatedAt()),
            'delivered_at' => '',
            'currency' => $this->getCurrency(),
            'merchant_notes' => $comment->getComment(),
            'invoice_items' => $items
        ]);

        return $data;
    }

    /**
     * Update Apruve shipment id to it's corresponding shipment in magento
     *
     * @param $id string
     * @param $shipment Mage_Sales_Model_Order_Shipment
     * @return bool
     * @throws Exception
     */
    protected function _updateShipmentId($apruveShipmentId, $shipment)
    {
        try {
            $apruveEntity = Mage::getModel('apruvepayment/entity')->loadByShipmentId($shipment->getIncrementId(), 'magento_id');
            $apruveEntity->setApruveId($apruveShipmentId);
            $apruveEntity->setMagentoId($shipment->getIncrementId());
            $apruveEntity->setEntityType('shipping');
            $apruveEntity->save();
        } catch(Exception $e) {
            Mage::helper('apruvepayment')->logException($e->getMessage());
            Mage::throwException(Mage::helper('apruvepayment')->__('Couldn\'t update shipment.'));
        }
        return true;
    }

    /**
     * Create new shipment in Apruve for an invoice based on shipment created in Magento
     *
     * @param string $apruveInvoiceId
     * @param Mage_Sales_Model_Order_Shipment $shipment
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @return $result string[]
     */
    public function createShipment($apruveInvoiceId, $shipment, $invoice)
    {
        $data = $this->_getShipmentData($shipment, $invoice);

        $curlOptions = [];
        $curlOptions[CURLOPT_POSTFIELDS] = $data;

        $result = $this->execCurlRequest($this->_getCreateShipmentUrl($apruveInvoiceId), 'POST', $curlOptions);
        $apruveShipmentId = isset($result['response']['id']) ? $result['response']['id'] : '';
        if($result['success'] == true) {
            $this->_updateShipmentId($apruveShipmentId, $shipment);
        }
        return $result;
    }

    /**
     * Create new invoice in Apruve for an order based on invoice created in Magento
     *
     * @param $id string
     * @return $result string
     */
    public function updateShipment($apruveInvoiceId, $apruveShipmentId, $shipment, $invoice)
    {
        $data = $this->_getShipmentData($shipment, $invoice);

        $curlOptions = [];
        $curlOptions[CURLOPT_POSTFIELDS] = $data;

        $result = $this->execCurlRequest($this->_getUpdateShipmentUrl($apruveInvoiceId, $apruveShipmentId), 'PUT', $curlOptions);
        return $result;
    }
}