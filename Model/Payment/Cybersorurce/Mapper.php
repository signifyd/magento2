<?php

namespace Signifyd\Connect\Model\Payment\Cybersorurce;

class Mapper
{
    /**
     * @param $order
     * @return array|bool
     */
    public function getData($order, $retries)
    {
        $additionalInfo = $order->getPayment()->getAdditionalInformation();
        if (empty($additionalInfo)) {
            return false;
        }

        if (!is_array($additionalInfo)) {
            return false;
        }

        if (!array_key_exists('auth_avs_code', $additionalInfo) || !array_key_exists('auth_avs_code', $additionalInfo)) {
            if ($retries < 5) {
                return false;
            }
        }

        $expireDate = isset($additionalInfo['card_expiry_date']) ? explode("-", $additionalInfo['card_expiry_date']) : [];
        $data = [
            'card_type' => $this->getCybersourceCardTypeByCode($additionalInfo['card_type']),
            'cc_trans_id' => isset($additionalInfo['transaction_id']) ? $additionalInfo['transaction_id'] : $order->getPayment()->getLastTransId(),
            'cc_last_4' => isset($additionalInfo['card_number']) ? $additionalInfo['card_number'] : $order->getPayment()->getCcLast4(),
            'cc_number' => isset($additionalInfo['card_bin']) ? $additionalInfo['card_bin'] : null,
            'cc_avs_status' => isset($additionalInfo['auth_avs_code']) ? $this->processAvs($additionalInfo['auth_avs_code']) : $order->getPayment()->getAvsStatus(),
            'cc_cvv_status' => isset($additionalInfo['auth_cv_result']) ? $this->processCvc($additionalInfo['auth_cv_result']) : null,
            'cc_exp_month' => isset($expireDate[0]) ? $expireDate[0] : null,
            'cc_exp_year' => isset($expireDate[1]) ? $expireDate[1] : null,
        ];

        return $data;
    }

    /**
     * @param $code
     * @return mixed|string
     */
    public function getCybersourceCardTypeByCode($code)
    {
        $cardTypes = [
            '001' => 'Visa',
            '002' => 'Master Card',
            '003' => 'American Express',
            '004' => 'Discover',
        ];

        if (!isset($cardTypes[$code])) {
            return '***Unknown***';
        } else {
            return $cardTypes[$code];
        }
    }

    /**
     * @param $avs
     * @return mixed|null
     */
    public function processAvs($avs)
    {
        $validCodes = [
            "F" => "Z",
            "H" => "Y",
            "T" => "A",
            "1" => "S",
            "2" => "E",
            "K" => "N",
            "L" => "Z",
            "O" => "A"
        ];

        return (array_key_exists($avs, $validCodes)) ? $validCodes[$avs] : $avs;
    }

    /**
     * @param $cvc
     * @return string|null
     */
    public function processCvc($cvc)
    {
        $validCodes = [
            "D" => "U",
            "I" => "N",
            "X" => "U",
            "1" => "U",
            "2" => "N",
            "3" => "P"
        ];
        return (array_key_exists($cvc, $validCodes)) ? $validCodes[$cvc] : $cvc;
    }
}
