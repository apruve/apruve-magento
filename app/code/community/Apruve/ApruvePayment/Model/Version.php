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
 * Used in creating options for Version config value selection
 */
class Apruve_ApruvePayment_Model_Version
{

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            /*array('value' => 'v3', 'label'=>Mage::helper('apruvepayment')->__('V3')),*/
            array('value' => 'v4', 'label'=>Mage::helper('apruvepayment')->__('V4')),
        );
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            /*'v3' => Mage::helper('apruvepayment')->__('V3'),*/
            'v4' => Mage::helper('apruvepayment')->__('V4'),
        );
    }
}
