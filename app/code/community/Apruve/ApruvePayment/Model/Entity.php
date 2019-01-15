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
 * Model to access apruve object ids stored in magento
 */
class Apruve_ApruvePayment_Model_Entity extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('apruvepayment/entity');
    }

    /**
     * Get the order item from apruve_entity table based on order id
     *
     * @param string $id
     * @return Apruve_ApruvePayment_Model_Entity
     */
    public function loadByOrderId($id)
    {
        $itemsData = $this->getCollection()->addFieldToFilter('magento_id', $id)->addFieldToFilter('entity_type', 'order')->setPageSize(1)->getData();
        if(isset($itemsData[0])) {
            $this->setData($itemsData[0]);
        }

        return $this;
    }

    /**
     * Get the invoice item from apruve_entity table based on invoice id
     *
     * @param string $id
     * @return Apruve_ApruvePayment_Model_Entity
     */
    public function loadByInvoiceId($id)
    {
        $itemsData = $this->getCollection()->addFieldToFilter('magento_id', $id)->addFieldToFilter('entity_type', 'invoice')->setPageSize(1)->getData();
        if(isset($itemsData[0])) {
            $this->setData($itemsData[0]);
        }

        return $this;
    }

    /**
     * Get the shipment item from apruve_entity table based on shipment id
     *
     * @param string $id
     * @return Apruve_ApruvePayment_Model_Entity
     */
    public function loadByShipmentId($id)
    {
        $itemsData = $this->getCollection()->addFieldToFilter('magento_id', $id)->addFieldToFilter('entity_type', 'shipping')->setPageSize(1)->getData();
        if(isset($itemsData[0])) {
            $this->setData($itemsData[0]);
        }

        return $this;
    }

    /**
     * Retrieve only the apruve item ids in an array
     *
     * @return []|bool
     */
    public function getItemIds()
    {
        if($this->getId()) {
            $items = Mage::helper('core')->jsonDecode($this->getApruveItemId());

            $itemIds = array();
            foreach ($items as $item) {
                $itemIds[] = array('id' => $item['id']);
            }

            return $itemIds;
        }

        return false;
    }

}