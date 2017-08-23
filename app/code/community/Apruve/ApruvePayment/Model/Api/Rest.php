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
 * Class Apruve_ApruvePayment_Model_Api_Rest
 *
 * Provide rest methods to communicate with apruve
 */
class Apruve_ApruvePayment_Model_Api_Rest extends Apruve_ApruvePayment_Model_Api_Abstract
{
    /**
     * Executes all the curl requests
     *
     * @param $curlOptions string[]
     *
     * @return $response string
     */
    public function execCurlRequest($url, $method = 'GET', $curlOptions = array())
    {
        $curl = curl_init();

        curl_setopt_array(
            $curl, array(
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => "",
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_HTTPHEADER     => $this->getHeaders(),
            )
        );

        curl_setopt_array($curl, $curlOptions);

        $response    = curl_exec($curl);
        $err         = curl_error($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        $result = $this->_prepareResponse($response, $url, $err, $http_status, $curlOptions);

        return $result;
    }
}