<?php

require 'OAuthToken.php';

class KongaPay extends OAuthToken
{

    protected $kongapay_url;

    /**
     * Used to requery KongaPay transactions
     *
     * @param $transaction_reference
     * @return bool
     */
    public function requeryTransaction($transaction_reference)
    {
        $this->kongapay_url = $this->is_test ?
            'https://api-sandbox.kongapay.com/v3/' : 'https://api.kongapay.com/v3';

        $requery_url = $this->kongapay_url . 'payments/wallet/merchant/' .
            $this->merchant_id . '/payment/' . $transaction_reference;

        $access_token = $this->getAccessToken();

        if ($access_token) {
            $requery_url = $requery_url . '?access_token=' . $access_token;

            $this->connect($requery_url, 'GET');

            return $this->result;
        }

        return false;
    }

    /**
     * Used to debit user using Pre-authorized Token
     *
     * @param $transaction_reference
     * @param $token
     * @param $amount
     * @param int $currency
     * @return bool | mixed
     */
    public function debitTransactionUsingToken($transaction_reference, $token, $amount, $currency = 566)
    {
        $this->kongapay_url = $this->is_test ?
            'https://api-sandbox.kongapay.com/v3/' : 'https://api.kongapay.com/v3';

        $debit_url = $this->kongapay_url . '/payments/wallet/merchant/' . 
            $this->merchant_id . '/pay';

        $params = array(
            'payment_reference' => $transaction_reference,
            'token' => $token,
            'amount' => $amount,
            'currency' => $currency
        );

        $access_token = $this->getAccessToken();

        if ($access_token) {
            $debit_url = $debit_url . '?access_token=' . $access_token;

            $this->connect($debit_url, 'POST', $params);

            return $this->result;
        }

        return false;
    }
}