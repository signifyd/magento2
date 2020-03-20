<?php

namespace Signifyd\Connect\Model\Payment\Adyen;

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

        if (!array_key_exists('adyen_avs_result', $additionalInfo) ||
            !array_key_exists('adyen_cvc_result', $additionalInfo)) {
            if ($retries < 5) {
                return false;
            }
        }

        $expireDate = isset($additionalInfo['adyen_expiry_date']) ?
            explode("/", $additionalInfo['adyen_expiry_date']) : [];

        $data = [
            'card_type' => isset($additionalInfo['cc_type']) ?
                $additionalInfo['cc_type'] : $order->getPayment()->getCcType(),
            'cc_trans_id' => isset($additionalInfo['pspReference']) ?
                $additionalInfo['pspReference'] : $order->getPayment()->getLastTransId(),
            'cc_last_4' => isset($additionalInfo['cardSummary']) ?
                $additionalInfo['cardSummary'] : $order->getPayment()->getCcLast4(),
            'cc_number' => isset($additionalInfo['adyen_card_bin']) ?
                $additionalInfo['adyen_card_bin'] : null,
            'cc_avs_status' => isset($additionalInfo['adyen_avs_result']) ?
                $this->processAvs($additionalInfo['adyen_avs_result']) : $order->getPayment()->getAvsStatus(),
            'cc_cvv_status' => isset($additionalInfo['adyen_cvc_result']) ?
                $this->processCvc($additionalInfo['adyen_cvc_result']) : null,
            'cc_exp_month' => isset($expireDate[0]) ? $expireDate[0] : null,
            'cc_exp_year' => isset($expireDate[1]) ? $expireDate[1] : null,
        ];

        return $data;
    }

    /**
     * @param $avs
     * @return mixed|null
     */
    public function processAvs($avs)
    {
        $validCodes = [
            -1 => null,
            0 => null,
            1 => "A",
            2 => "N",
            3 => "U",
            4 => "S",
            5 => "U",
            6 => "Z",
            7 => "Y",
            8 => null,
            9 => "A",
            10 => "N",
            11 => null,
            12 => "A",
            13 => "N",
            14 => "Z",
            15 => "Z",
            16 => "N",
            17 => "N",
            18 => "U",
            19 => "Z",
            20 => "Y",
            21 => "A",
            22 => "N",
            23 => "Z",
            24 => "Y",
            25 => "A",
            26 => "N"
        ];
        $avsArr = explode(" ", $avs);
        return (array_key_exists($avsArr[0], $validCodes)) ? $validCodes[$avsArr[0]] : null;
    }

    /**
     * @param $cvc
     * @return string|null
     */
    public function processCvc($cvc)
    {
        $validCode = null;
        $avsArr = explode(" ", $cvc);
        switch ($avsArr[0]) {
            case '0':
                $validCode = null;
                break;
            case '1':
                $validCode = 'M';
                break;
            case '2':
                $validCode = 'N';
                break;
            case '3':
                $validCode = 'P';
                break;
            case '4':
                $validCode = 'S';
                break;
            case '5':
                $validCode = 'U';
                break;
            case '6':
                $validCode = null;
                break;
            default:
                $validCode = null;
                break;
        }

        return $validCode;
    }
}
