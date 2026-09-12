<?php


namespace App\Http\Services;


use App\Models\User;
use Carbon\Carbon;

class UserService
{
    /**
     * @return array|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createWallet()
    {
        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET', env('URL_API_WALLET') . '/wallet/create');
        if ($res->getStatusCode()) {
            return json_decode($res->getBody());
        }
        return [];
    }

    /**
     * @return bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function kyc($wallet_public_key = '', $wallet_private_key = '')
    {
        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET', env('URL_API_WALLET') . '/wallet/kyc?publickey=' . $wallet_public_key . '&privatekey=' . $wallet_private_key);
        if ($res->getStatusCode()) {
            return json_decode($res->getBody());
        }
        return false;
    }

    public function withdraw(array $params, $wallet_public_key, $wallet_private_key)
    {
        $recipient = data_get($params, 'wallet_public_key', '');
        $amount = data_get($params, 'amount', '');
        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET', env('URL_API_WALLET') . '/wallet/withdraw?recipient=' . $recipient . '&amount=' . $amount . '&publickey=' . $wallet_public_key . '&privateKey=' . $wallet_private_key);
        if ($res->getStatusCode()) {
            return json_decode($res->getBody());
        }
        return [];
    }

    /**
     * @param User $user
     * @param string $token
     * @return bool
     */
    public function verifyToken(User $user, $token = '')
    {
        // Check chưa có token hoặc token không đúng
        if (!$user->token || !$token || $token != $user->token) {
            return false;
        }
        // Check thời gian token còn hiệu lưc không ?
        if (!$user->token_at) {
            return false;
        }

        $now = Carbon::now();
        $timeLimit = $now->diffInSeconds(Carbon::parse($user->token_at));

        if ($timeLimit > env('TIME_LIMIT_TOKEN')) {
            return false;
        }

        return true;
    }

    /**
     * @param User $user
     * @return bool
     */
    public function removeToken(User $user)
    {
        $user->token = null;
        $user->token_at = null;
        return $user->save();
    }

}
