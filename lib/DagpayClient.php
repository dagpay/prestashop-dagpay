<?php
/**
* 2007-2019 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2019 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class DagpayClient
{
    private $curl;
    private $environment_id;
    private $user_id;
    private $secret;
    private $test;
    private $platform;
    private $curlFactory;

    public function __construct(
        $environment_id,
        $user_id,
        $secret,
        $mode,
        $platform
    ) {
        $this->environment_id = $environment_id;
        $this->user_id = $user_id;
        $this->secret = $secret;
        $this->test = $mode;
        $this->platform = $platform;
    }

    private function getRandomString($length)
    {
        return strtoupper(
            bin2hex(
                random_bytes(
                    ceil($length / 2)
                )
            )
        );
    }

    private function getSignature($tokens)
    {
        return hash_hmac(
            "sha512",
            implode(":", $tokens),
            $this->secret
        );
    }

    private function getCreateInvoiceSignature($info)
    {
        return $this->getSignature([
            $info["currencyAmount"],
            $info["currency"],
            $info["description"],
            $info["data"],
            $info["userId"],
            $info["paymentId"],
            $info["date"],
            $info["nonce"]
        ]);
    }

    public function getInvoiceInfoSignature($info)
    {
        return $this->getSignature([
            $info['id'],
            $info['userId'],
            $info['environmentId'],
            $info['coinAmount'],
            $info['currencyAmount'],
            $info['currency'],
            $info['description'],
            $info['data'],
            $info['paymentId'],
            $info['qrCodeUrl'],
            $info['paymentUrl'],
            $info['state'],
            $info['createdDate'],
            $info['updatedDate'],
            $info['expiryDate'],
            $info['validForSeconds'],
            $info['statusDelivered'] ? "true" : "false",
            $info['statusDeliveryAttempts'],
            $info['statusLastAttemptDate'] !== null ? $info['statusLastAttemptDate'] : "",
            $info['statusDeliveredDate'] !== null ? $info['statusDeliveredDate'] : "",
            $info['date'],
            $info['nonce']
        ]);
    }

    public function createInvoice($id, $currency, $total, $description = '')
    {
        $invoice = [
            "userId" => $this->user_id,
            "environmentId" => $this->environment_id,
            "currencyAmount" => $total,
            "currency" => $currency,
            "description" => "Dagcoin Payment Gateway invoice : ". $description,
            "data" => "Order",
            "paymentId" => (string) $id,
            "date" => date('c'),
            "nonce" => $this->getRandomString(32)
        ];
        $signature = $this->getCreateInvoiceSignature($invoice);
        $create_invoice_request_info = $invoice;
        $create_invoice_request_info["signature"] = $signature;
        $result = $this->makeRequest('POST', 'invoices', $create_invoice_request_info);
        return $result;
    }

    public function getInvoiceInfo($id)
    {
        $result = $this->makeRequest('GET', 'invoices/' . $id);
        return $result;
    }

    public function cancelInvoice($id)
    {
        $result = $this->makeRequest('POST', 'invoices/cancel', [
            "invoiceId" => $id
        ]);
        return $result;
    }

    private function curlGet($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_GET, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                                            'Content-Type: application/json',
                                            'Connection: Keep-Alive'
                                            ));

        $result = curl_exec($ch);

        curl_close($ch);

        return json_encode($result);
    }

    private function curlPost($url, $data = [])
    {
        $data = json_encode($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_POST, true);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true);

        $headers = array();
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $info = curl_getinfo($ch);

        curl_close($ch);

        return json_decode($result, true);
    }

    private function makeRequest($method, $url, $data = [])
    {
        if ($this->platform === 'standalone') {
            if ($method == 'POST') {
                $data = $this->curlPost($this->getUrl() . $url, $data);
                return $data['payload'];
            } elseif ($method == 'GET') {
                return json_decode($this->curlGet($this->getUrl() . $url));
            }
        } elseif ('wordpress') {
            $data = json_encode($data);
            $request["headers"] = [ 'Content-Type' => 'application/json'];
            $response = null;
            if ($method == 'POST') {
                $request["body"] = $data;
                $response = wp_safe_remote_post($this->getUrl() . $url, $request);
            } elseif ($method == 'GET') {
                $response = wp_safe_remote_get($this->getUrl() . $url, $request);
            }
            $data = json_decode(wp_remote_retrieve_body($response));
            if (!$data->success) {
                throw new \Exception($data->error ? $data->error : "Something went wrong! Please try again later or contact our support...");
            }
            return $data->payload;
        }
    }

    private function getUrl()
    {
        return $this->test ?  'https://api.dagpay.io/api/' : 'https://test-api.dagpay.io/api/';
    }
}
