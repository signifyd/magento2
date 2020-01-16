<?php

namespace Signifyd\Connect\Model\Payment\Cybersorurce;

class Mapper
{
    /**
     * @param $order
     * @return array|bool
     */
    public function getData($order)
    {
        $additionalInfo = $order->getPayment()->getAdditionalInformation();
        if (empty($additionalInfo)) {
            return false;
        }

        if (!is_array($additionalInfo)) {
            return false;
        }

        $expireDate = isset($additionalInfo['card_expiry_date']) ? explode("-", $additionalInfo['card_expiry_date']) : [];
        $data = [
            'card_type' => $this->getCybersourceCardTypeByCode($additionalInfo['card_type']),
            'cc_trans_id' => isset($additionalInfo['transaction_id']) ? $additionalInfo['transaction_id'] : null,
            'cc_last_4' => isset($additionalInfo['card_number']) ? $additionalInfo['card_number'] : null,
            'cc_number' => isset($additionalInfo['card_bin']) ? $additionalInfo['card_bin'] : null,
            'cc_avs_status' => isset($additionalInfo['auth_avs_code']) ? $additionalInfo['auth_avs_code'] : null,
            'cc_cid_status' => isset($additionalInfo['auth_cv_result']) ? $additionalInfo['auth_cv_result'] : null,
            'cc_exp_month' => isset($expireDate[0]) ? $expireDate[0] : null,
            'cc_exp_year' => isset($expireDate[1]) ? $expireDate[1] : null,
        ];

        return $data;
    }

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
}
