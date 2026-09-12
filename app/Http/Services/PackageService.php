<?php


namespace App\Http\Services;


use App\Models\Package;
use App\Models\UserPackage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PackageService
{
    public function handleGroup1(array $params)
    {
        $current = Carbon::now();
        $profit_per_year = $params['interest_percent'] * $params['amount'] / 100;
        $profit_usdt = $params['repay_percent_usdt'] * $profit_per_year / 100;
        $profit_svl = $profit_per_year - $profit_usdt;

        /*Trả thưởng sau khi trừ 1-3% phíu và chia đều cho 12 tháng*/
        $fee = 3; // Phí rút
        $reward_usdt = ($profit_usdt - ($fee * $profit_usdt) / 100);
        $reward_svl = ($profit_svl - ($fee * $profit_svl) / 100);
        $bonus_svl = $params['amount'] * 10;
        $reward_svl = $reward_svl + $bonus_svl;
        /*End*/

        $dataCreate = [
            'user_id' => $params['user_id'],
            'package_id' => $params['id'],
            'amount' => $params['amount'],
            'reward_day' => $current->copy()->addYear(),
            'reward_usdt' => $reward_usdt,
            'reward_svl' => $reward_svl,
        ];
        return UserPackage::create($dataCreate);
    }

    public function handleGroup2(array $params)
    {
        $current = Carbon::now();
        $amount = $params['amount'];
        $interest_percent = $params['interest_percent'];
        $repay_percent_usdt = $params['repay_percent_usdt'];

        $profit_per_year = $interest_percent * $amount / 100;
        $profit_usdt = $repay_percent_usdt * $profit_per_year / 100;
        $profit_svl = $profit_per_year - $profit_usdt;
        $fee = 3; // Phí rút
        /*Trả thưởng sau khi trừ 1-3% phíu và chia đều cho 12 tháng*/
        $reward_usdt = ($profit_usdt - ($fee * $profit_usdt) / 100) / 12;
        $reward_svl = ($profit_svl - ($fee * $profit_svl) / 100) / 12;
        $bonus_svl = $params['amount'] * 10;
        /*End*/
        for ($i = 1; $i <= 12; $i++) {
            if ($i == 1) {
                $dataCreate = [
                    'user_id' => $params['user_id'],
                    'package_id' => $params['id'],
                    'amount' => $params['amount'],
                    'reward_day' => $current->copy()->addmonths($i),
                    'reward_usdt' => $reward_usdt,
                    'reward_svl' => $reward_svl + $bonus_svl,
                ];
            } else {
                $dataCreate = [
                    'user_id' => $params['user_id'],
                    'package_id' => $params['id'],
                    'amount' => $params['amount'],
                    'reward_day' => $current->copy()->addmonths($i),
                    'reward_usdt' => $reward_usdt,
                    'reward_svl' => $reward_svl,
                ];
            }

            UserPackage::create($dataCreate);
        }
        return true;
    }

    public function handleGroup3(array $params)
    {
        $current = Carbon::now();
        $amount = $params['amount'];
        $interest_percent = $params['interest_percent'];
        $repay_percent_usdt = $params['repay_percent_usdt'];

        $profit_per_year = $interest_percent * $amount / 100;
        $profit_usdt = $repay_percent_usdt * $profit_per_year / 100;
        $profit_svl = $profit_per_year - $profit_usdt;

        $fee = 3; // Phí rút

        /*Trả thưởng sau khi trừ 1-3% phíu và chia đều cho 12 tháng*/
        $reward_usdt = ($profit_usdt - ($fee * $profit_usdt) / 100) / 12;
        $reward_svl = ($profit_svl - ($fee * $profit_svl) / 100) / 12;
        $bonus_svl = ($params['amount'] * 10) / 12;

        /*End*/
        for ($i = 1; $i <= 12; $i++) {
            if ($i == 1) {
                $dataCreate = [
                    'user_id' => $params['user_id'],
                    'package_id' => $params['id'],
                    'amount' => $params['amount'],
                    'reward_day' => $current->copy()->addmonths($i),
                    'reward_usdt' => $reward_usdt,
                    'reward_svl' => $reward_svl,
                ];
            } else {
                $dataCreate = [
                    'user_id' => $params['user_id'],
                    'package_id' => $params['id'],
                    'amount' => $params['amount'],
                    'reward_day' => $current->copy()->addmonths($i),
                    'reward_usdt' => $reward_usdt,
                    'reward_svl' => $reward_svl + $bonus_svl,
                ];
            }
            UserPackage::create($dataCreate);
        }
        return true;
    }

    public function getPackage($user, $package_group)
    {
        $packages = Package::where('group_id', $package_group)->orderBy('amount', 'DESC')->get();
        foreach ($packages as $item) {
            $check = UserPackage::where('package_id', $item->id)
                ->where('user_id', $user->id)
                ->where('is_profit_usdt_paid', 0);
            if ($check->count() > 0) {
                data_set($item, 'is_invest', true);
            } else {
                data_set($item, 'is_invest', false);
            }
            // Check tra thuong
            $check->where('reward_day', '<=', Carbon::now());
            if ($check->first()) {
                data_set($item, 'is_reward_day', true);
            } else {
                data_set($item, 'is_reward_day', false);
            }
            data_set($item, 'reward_day', $this->getRewardDay($user->id, $item->id));
        }
        return $packages;
    }

    private function getRewardDay($user_id, $package_id)
    {
        $package_user = UserPackage::where('package_id', $package_id)
            ->where('user_id', $user_id)
            ->orderBy('reward_day', 'ASC')
            ->where('is_profit_usdt_paid', 0)
            ->first();
        if ($package_user) {
            return $package_user->reward_day;
        }
        return '';
    }

    public function getListClaim(int $package_id, int $user_id)
    {
        $list = UserPackage::where('user_id', $user_id)
            ->where('package_id', $package_id)
            ->where('is_profit_usdt_paid', 0)
            ->where('reward_day', '<=', Carbon::now())
            ->get();

        return $list;
    }

    public function transfer(string $recipient, $amount)
    {
        $client = new \GuzzleHttp\Client();
        $res = $client->post(env('URL_API_WALLET') . '/transfer', [
            'json' => [
                "recipient" => $recipient,
                "amount" => (string)$amount,
            ]
        ]);
        return $res;
    }

    public function updateIsPaidProfit($items)
    {
        foreach ($items as $item) {
            $item->is_profit_usdt_paid = true;
            $item->save();
        }
        return true;
    }

    /**
     * @param array $ids
     * @param array $params
     * @return array
     */
    public function reportInvestment(array $ids, array $params)
    {
        $dataRange = data_get($params, 'data_range', []);
        $userPackage = UserPackage::select(
            'user_packages.*',
            'packages.group_id'
        )->join('packages', 'packages.id', '=', 'user_packages.package_id')
            ->whereIn('user_id', $ids);
        if (!empty($dataRange)) {
            $start = $dataRange[0];
            $end = $dataRange[1];
            if ($start != 'null' && $end != 'null') {
                $userPackage->where(function ($query) use ($start, $end) {
                    return $query->where('user_packages.created_at', '>=', Carbon::parse($start)->format('Y-m-d'))
                        ->where('user_packages.created_at', '<=', Carbon::parse($end)->format('Y-m-d'));
                });
            }
        }
        $userPackage = $userPackage->get();

        $userPackage->transform(function ($item, $key) {
            if ($item->group_id == 2 || $item->group_id == 3) {
                $item->amount = $item->amount / 12;
                return $item;
            }
            return $item;
        });
        return [
            'total_price' => number_format($userPackage->sum('amount')),
            'total_user' => $userPackage->unique('user_id')->count(),
        ];
    }
}
