<?php

namespace App\Http\Controllers;

use App\Http\Services\UserService;
use App\Mail\SendTokenWithdraw;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    private $userService;

    public function __construct(UserService $userService)
    {
        $this->middleware('auth:api', ['except' => ['login', 'register']]);
        $this->userService = $userService;
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        if (!$token = auth()->attempt($validator->validated())) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
     
        $user = auth()->user();
        if ($user->status === 'deactive') {
            return response()->json(['error' => 'Your account is deactivated'], 401);
        }
        return $this->createNewToken($token);
    }

    public function register(Request $request)
    {
        if ($request->email == "admin@starverselabs.com") {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|between:2,100',
                'email' => 'required|string|email|max:100|unique:users',
                'password' => 'required|string|confirmed|min:6',
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|between:2,100',
                'email' => 'required|string|email|max:100|unique:users',
                'password' => 'required|string|confirmed|min:6',
                'referral_code' => 'required|string|max:20|exists:users,id',
            ]);
        }

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }
        $data_create = $validator->validated();
        try {
            // Call api curl create wallet
            $resCurl = $this->userService->createWallet();

            $data_create['wallet_private_key'] = data_get($resCurl, 'private_address', '');
            $data_create['wallet_public_key'] = data_get($resCurl, 'public_address', '');
            $data_create['mnemonic'] = data_get($resCurl, 'mnemonic', '');

            $user = User::create(array_merge(
                $data_create,
                ['password' => bcrypt($request->password)]
            ));

            if (!$user) {
                return response()->json([
                    'message' => 'Create user fail',
                ], 404);
            }

            if (!$token = auth()->attempt($request->only(['email', 'password']))) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            return $this->createNewToken($token);
        } catch (\Exception $exception) {
            Log::error('User registration failed.', [
                'exception' => get_class($exception),
            ]);
            return response()->json(['error' => 'Không thể tạo tài khoản.'], 500);
        }
    }

    public function logout()
    {
        auth()->logout();
        return response()->json(['message' => 'User successfully signed out']);
    }

    public function changePassWord(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_password' => 'required|string|min:6',
            'new_password' => 'required|string|confirmed|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }
        $userId = auth()->user()->id;

        $user = User::where('id', $userId)->update(
            ['password' => bcrypt($request->new_password)]
        );

        return response()->json([
            'message' => 'User successfully changed password',
            'user' => $user,
        ], 201);
    }

    private function createNewToken($token)
    {
        $user = auth()->user();
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => $user,
            'capabilities' => \App\Support\PermissionAccess::capabilities($user),
        ]);
    }

    public function verifyToken(Request $request)
    {
        $user = JWTAuth::toUser();

        if (!$user) {
            return $this->errorResponse('Token invalid', 403);
        }

        return response()->json([
            'access_token' => auth()->tokenById($user->getJWTIdentifier()),
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => $user,
            'capabilities' => \App\Support\PermissionAccess::capabilities($user),
        ]);
    }

    public function updateImage(Request $request)
    {
        $user = auth()->user();
//        $this->validate($request, [
//            'file_0' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:10240',
//            'file_1' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:10240',
//        ]);
        if ($request->file('file_0') && $request->file('file_1')) {
            $files = [
                $request->file('file_0'),
                $request->file('file_1')
            ];
            $contents = [];
            foreach ($files as $imagePath) {
                $imageName = $imagePath->getClientOriginalName();
                $dir = 'accuracy';
                $path = $imagePath->storeAs($dir, $imageName, 'public');
                $contents [] = (object)[
                    'url' => Storage::url($path)
                ];
            }
            $user->file = $contents;
            $user->save();
        }
        return response()->json([
            'message' => 'User successfully update kyc',
            'path' => $path,
            'file' => $user->file,
        ], 200);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function verifyKyc(Request $request)
    {
        $this->validate($request, [
            'status' => 'required',
            'user_id' => 'required',
        ]);
        $user = User::find($request->user_id);
        if (!$user) {
            return response()->json([
                'message' => 'User not found',
                'error' => true,
            ], 400);
        }

        if (!$user->wallet_public_key) {
            return response()->json([
                'message' => 'You do not have a wallet address, please contact admin',
                'error' => true,
            ], 400);
        }

        if ($user) {
            $user->status = $request->status;
            $user->save();
        }

        if ($request->get('status')) {
            $resKyc = $this->userService->kyc($user->wallet_public_key, $user->wallet_private_key);
            if (isset($resKyc->error)) {

                if ($user) {
                    $user->status = 0;
                    $user->save();
                }

                return response()->json([
                    'message' => $resKyc->error,
                    'error' => true,
                ], 400);
            }
        }

        return response()->json([
            'message' => "Change status KYC",
            'user' => $user,
        ], 200);
    }

    public function withdraw(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'message' => 'User not found',
                'data' => [],
            ], 404);
        }
        $this->validate($request, [
            'amount' => 'required|integer',
            'wallet_public_key' => 'required|string|max:255',
            'token' => 'required|string|max:255',
        ]);
        $verify = $this->userService->verifyToken($user, $request->get('token', ''));

        if (!$verify) {
            return response()->json([
                'message' => 'Token không hợp lệ',
                'data' => [],
            ], 400);
        }

        $resWallet = $this->userService->withdraw($request->all(), $user->wallet_public_key, $user->wallet_private_key);
        if (isset($resWallet->error)) {
            return response()->json([
                'message' => $resWallet->error,
                'data' => [],
            ], 404);
        }

        $this->userService->removeToken($user);
        return response()->json([
            'message' => data_get($resWallet, 'message', ''),
            'data' => [],
        ], 200);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function withdrawSendToken()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'message' => 'User not found',
                'data' => [],
            ], 404);
        }
        $token = str_random(36);
        $user->token = $token;
        $user->token_at = Carbon::now();
        $user->save();

        Mail::to($user->email)->send(new SendTokenWithdraw($token));

        return response()->json([
            'message' => 'Send token successfully please check your email: ' . strtoupper($user->email),
            'data' => [],
        ], 200);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getListUser(Request $request)
    {
        $keyword = $request->get('keyword', '');
        $status = $request->get('status', '');
        $users = User::where('role', '!=', 1);
        if ($keyword) {
            $users->where(function ($q) use ($keyword) {
                return $q->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('email', 'like', '%' . $keyword . '%');
            });
        }
        if ($status) {
            $users->where('status', (bool)$status);
        }

        return response()->json([
            'message' => 'Get users success',
            'data' => $users->get(),
        ], 200);
    }

    public function getReferencingTree(Request $request)
    {
        $user = auth()->user();
        $tree = $user->load('children');
        return response()->json($tree, 200);
    }

}
