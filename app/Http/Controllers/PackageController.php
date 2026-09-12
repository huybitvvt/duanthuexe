<?php


namespace App\Http\Controllers;


use App\Http\Services\InvestmentService;
use App\Http\Services\PackageService;
use App\Models\Package;
use App\Models\UserPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PackageController
{
    private $packageService;
    private $investmentService;
    private $ids = [];

    /**
     * PackageController constructor.
     * @param PackageService $packageService
     */
    public function __construct(PackageService $packageService, InvestmentService $investmentService)
    {
        $this->packageService = $packageService;
        $this->investmentService = $investmentService;
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPackages()
    {
        $user = auth()->user();
        return response()->json([
            'message' => 'get data success',
            'data' => [
                'package_group_1' => $this->packageService->getPackage($user, Package::PACKAGE_GROUP_1),
                'package_group_2' => $this->packageService->getPackage($user, Package::PACKAGE_GROUP_2),
                'package_group_3' => $this->packageService->getPackage($user, Package::PACKAGE_GROUP_3),
            ],
        ], 200);
    }

    public function report(Request $request)
    {
        $user = auth()->user();
        array_push($this->ids, $user->id);
        $this->recursive($user->children());
        $report = $this->packageService->reportInvestment($this->ids, $request->all());
        return response()->json([
            'message' => 'get data success',
            'data' => $report
        ], 200);
    }

    private function recursive($children)
    {
        if ($children->count()) {
            foreach ($children->get() as $item) {
                array_push($this->ids, $item->id);
                if (!$item->children()->count()) {
                    continue;
                }
                $this->recursive($item->children());
            }
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function getHistoryInvestment()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }
        $investments = UserPackage::with('package')
            ->where('user_id', $user->id)
            ->orderBy('updated_at', 'DESC')
            ->get();
        return response()->json([
            'message' => 'get data success',
            'data' => $investments
        ], 200);
    }

    public function claim(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }
        $user = auth()->user();
        $listClaim = $this->packageService->getListClaim($request->package_id, $user->id);
        $price_usdt = $listClaim->sum('reward_usdt');
        if ($price_usdt <= 0) {
            return response()->json([
                'message' => 'The amount of the commission is too small',
            ], 400);
        }
        $response = $this->packageService->transfer($user->wallet_public_key, $price_usdt);
        if ($response->getStatusCode()) {
            $response = json_decode($response->getBody());
            if ($response->status) {
                $this->packageService->updateIsPaidProfit($listClaim);
                return response()->json([
                    'message' => 'You have successfully withdrawn the profit of: ' . $price_usdt . 'USDT',
                ], 200);
            }
        }

        return response()->json([
            'message' => 'Profit withdrawal failed',
        ], 400);
    }

    public function storeInvestment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|integer|between:1,3',
            'amount' => 'required|numeric',
            'interest_percent' => 'required|integer',
            'repay_percent_usdt' => 'required|integer',
            'repay_percent_svl' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        /*Pay ref 1*/
        $response = $this->investmentService->payRef($user, $request->amount);
        if ($response && $response->getStatusCode() == 200) {
            $response = json_decode($response->getBody());
            $user->is_invest = 1;
            $user->save();
            if (!isset($response->error) && $response->status) {
                $request->merge(['user_id' => $user->id]);
                $group_id = $request->get('group_id');
                if ($group_id == 1) {
                    $this->packageService->handleGroup1($request->all());
                } elseif ($group_id == 2) {
                    $this->packageService->handleGroup2($request->all());
                } else {
                    $this->packageService->handleGroup3($request->all());
                }
                return response()->json([
                    'message' => 'Investment success',
                    'data' => [],
                ], 200);
            }
        }

        return response()->json([
            'message' => 'An error occurred',
            'data' => [],
        ], 400);
    }

    /**
     * @param Request $request
     * @return array|\Psr\Http\Message\StreamInterface
     */
    public function invest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric',
        ]);

        $user = auth()->user();
        $data = $validator->validate();
        $response = $this->investmentService->payRef($user, $data['amount']);

        if ($response->getStatusCode()) {
            return $response->getBody();
        }

        return [];
    }

    public function sumSvl(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            abort(404, 'User not found');
        }
        $svlAmount = UserPackage::where('user_id', $user->id)
            ->where('is_profit_usdt_paid', 1)
            ->where('is_profit_svl_paid', 0)->sum('reward_svl');
        return response()->json([
            'message' => 'success',
            'data' => $svlAmount
        ], 200);
    }
}
