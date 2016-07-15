<?php

/**
 * Class KongaPay
 *
 * @author: Jacob Ayokunle <jacob.ayokunle@konga.com>
 */

require 'OAuthToken.php';

class KongaPay extends OAuthToken
{

    protected $kongapay_requery_url;

    public function requeryTransaction($transaction_reference)
    {
        $this->kongapay_requery_url = $this->is_test ?
            'https://api-sandbox.kongapay.com/v3/' : 'https://api.kongapay.com/v3';

        $requeryUrl = $this->kongapay_requery_url . 'payments/wallet/merchant/' .
            $this->oauth_merchant_id . '/payment/' . $transaction_reference;

        $access_token = $this->getAccessToken();

        if ($access_token) {
            $requeryUrl = $requeryUrl . '?access_token=' . $access_token;

            $this->connect($requeryUrl, 'GET');

            return $this->result;
        }

        return false;
    }
}