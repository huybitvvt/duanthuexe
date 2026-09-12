<?php

namespace App\Http\Services;

use App\Models\User;

class InvestmentService
{
    private $paymentRef;
    private $user;
    private $amount;

    /**
     * InvestmentService constructor.
     * @param User $user
     * @param $amount
     */
    public function __construct()
    {
        $this->paymentRef = collect();
    }

    public function payRef($user, $amount)
    {
        $this->user = $user;
        $this->amount = $amount;
        $this->payLevel0();
        $this->payLevel1();
        $this->payLevel2();
        $this->payLevel3();
        $this->payLevel4();
        $recipients = array();
        $amounts = array();
        foreach ($this->paymentRef as $item) {
            array_push($recipients, $item['address']);
            array_push($amounts, (string)$item['amount']);
        }
        //$checkStep = $this->checkHierarchical();
        //if ($checkStep == 5 || $checkStep == 3) {
        //    $percent = config('app.percent');
        //}
        $client = new \GuzzleHttp\Client();
        $res = $client->post(env('URL_API_WALLET') . '/invest', [
            'json' => [
                "sender" => $this->user->wallet_public_key,
                "totalAmount" => (string)$amount,
                "recipients" => $recipients,
                "amounts" => $amounts
            ]
        ]);
        return $res;
    }

    private function checkHierarchical()
    {
        return $this->user->load('parentRec')->count();
    }

    private function payLevel0()
    {
        $parent_0 = $this->user->parent;
        if ($parent_0) {
            $this->paymentRef->add(['address' => $parent_0->wallet_public_key, 'amount' => $this->amount * 7 / 100]);
        }
    }

    private function payLevel1()
    {
        $parent_0 = $this->user->parent;
        if ($parent_0) {
            $parent_1 = $parent_0->parent;
            if ($parent_1) {
                if (count($parent_1->child) >= 3) {
                    $this->paymentRef->add(['address' => $parent_1->wallet_public_key, 'amount' => $this->amount * 3 / 100]);
                }
            }
        }
    }

    private function payLevel2()
    {
        $parent_0 = $this->user->parent;
        if ($parent_0) {
            $parent_1 = $parent_0->parent;
            if ($parent_1) {
                $parent_2 = $parent_1->parent;
                if ($parent_2 && count($parent_2->child) >= 6) {
                    $this->paymentRef->add(['address' => $parent_2->wallet_public_key, 'amount' => $this->amount * 3 / 100]);
                }
            }
        }
    }

    private function payLevel3()
    {
        $parent_0 = $this->user->parent;
        if ($parent_0) {
            $parent_1 = $parent_0->parent;
            if ($parent_1) {
                $parent_2 = $parent_1->parent;
                if ($parent_2) {
                    $parent_3 = $parent_2->parent;
                    if ($parent_3 && count($parent_3->child) >= 9) {
                        $this->paymentRef->add(['address' => $parent_3->wallet_public_key, 'amount' => $this->amount * 2 / 100]);
                    }
                }
            }
        }
    }

    private function payLevel4()
    {
        $parent_0 = $this->user->parent;
        if ($parent_0) {
            $parent_1 = $parent_0->parent;
            if ($parent_1) {
                $parent_2 = $parent_1->parent;
                if ($parent_2) {
                    $parent_3 = $parent_2->parent;
                    if ($parent_3) {
                        $parent_4 = $parent_3->parent;
                        if ($parent_4 && count($parent_4->child) >= 15) {
                            $this->paymentRef->add(['address' => $parent_4->wallet_public_key, 'amount' => $this->amount * 1 / 100]);
                        }
                    }
                }
            }
        }
    }
}
