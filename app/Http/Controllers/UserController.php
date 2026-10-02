<?php


namespace App\Http\Controllers;


use App\Entities\Customer;
use App\Http\Services\Users\UserService;
use App\Mail\ForgotPassword;
use App\Models\PasswordReset;
use App\Models\User;
use App\Models\UserRole;
use App\Support\PermissionAccess;
use App\Validators\OrderValidator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(Request $request)
    {
        return $this->successResponse($this->userService->index($request->all()));
    }

    public function getStaffByStore(Request $request)
    {
        $request->validate(['store_id' => 'nullable|integer|exists:stores,id']);
        $storeId = $request->input('store_id') ?: $request->user()->store_id;
        if (!$storeId) {
            return $this->successResponse([]);
        }
        if (!PermissionAccess::allows($request->user(), 'hr.view', (int) $storeId)) {
            PermissionAccess::can($request->user(), 'order.create', (int) $storeId);
        }
        return $this->successResponse($this->userService->getByStore($storeId));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->userRules());
        try {
            DB::beginTransaction();
            $user = $this->userService->store($data);

            if (!$user) {
                DB::rollBack();
                return $this->errorResponse('User not found', Response::HTTP_BAD_REQUEST);
            }
//            $user->roles()->sync([$request->role_id]);
            DB::commit();
            return $this->successResponse('Tạo mới thành công');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->errorResponse('Error', Response::HTTP_BAD_REQUEST);
        }
    }

    public function update(Request $request)
    {
        $request->validate(['id' => 'required|integer|exists:users,id,deleted_at,NULL']);
        $data = $request->validate($this->userRules((int) $request->id));
        try {
            DB::beginTransaction();
            $user = $this->userService->update($request->id, $data);
            DB::commit();
            return $this->successResponse($user, 'Cập nhật thành công');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->errorResponse('Error', Response::HTTP_BAD_REQUEST);
        }
    }

    public function forgotPassword(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|string|max:255',
        ]);
        $mail = $request->email;
        $user = User::where('email', $mail)->first();

        if (!$user) {
            return response()->json([
                'message' => "Mail does not exist",
            ], 422);
        }

        $token = Str::random(60);
        $passwordReset = PasswordReset::updateOrCreate(['email' => $mail], ['token' => $token]);
        if ($passwordReset) {
//            $url = config('app.url') . '/reset-password?token=' . $token . '&email=' . $mail;
            Mail::to($mail)->send(new ForgotPassword($token));
            return response()->json([
                'message' => "Please check your email to proceed to get the password",
            ], 200);
        }
        return response()->json([
            'message' => "Send email false",
        ], 400);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:100',
            'password' => 'required|string|min:6',
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        $token = $request->token;
        $passwordReset = PasswordReset::where('token', $token)->first();
        if (!$passwordReset) {
            return response()->json([
                'message' => "Password recovery token does not exist or has expired",
            ], 400);
        }

        if (Carbon::parse($passwordReset->updated_at)->addMinutes(720)->isPast()) {
            $passwordReset->delete();
            return response()->json([
                'message' => "Password recovery token does not exist or has expired",
            ], 400);
        }

        $user = User::where('email', $passwordReset->email)->firstOrFail();
        $user->update([
            'password' => Hash::make($request->password)
        ]);
        $passwordReset->delete();
        return response()->json([
            'message' => "Successfully retrieved password",
        ], 200);
    }

    public function changePassword(Request $request)
    {
        $request->validate(['user_id' => 'required|integer|exists:users,id,deleted_at,NULL', 'password' => 'required|string|min:6|max:255']);
        $user = User::find($request->user_id);
        if (!$user) {
            return $this->errorResponse('User not found', 403);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return $this->successResponse($user, 'Đổi password thành công');
    }

	public function changeMyPassword(Request $request)
    {
        $request->validate(['user_id' => 'required|integer|exists:users,id,deleted_at,NULL', 'password' => 'required|string|min:6|max:255']);
		$authUserId = auth()->user()->id;
		$paramUserId = $request->user_id;

		if ($authUserId != $paramUserId) {
			return response()->json([
                'message' => "Thông tin User không hợp lệ",
            ], 400);
		}

        $user = User::find($request->user_id);
        if (!$user) {
            return $this->errorResponse('User not found', 403);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return $this->successResponse($user, 'Đổi password thành công');
    }

    public function destroy(User $user): JsonResponse
    {
        if ((int) $user->id === (int) auth()->id()) {
            return $this->errorResponse('Không thể xóa tài khoản đang đăng nhập.', 403);
        }
        $user->update(['status' => 'deactive']);

        
        $deleted = $user->delete();

        
        if ($deleted) {
            return $this->successResponse(true, 'Người dùng đã được xóa thành công');
        } else {
            return $this->errorResponse('Xảy ra lỗi khi xóa người dùng', 500);
        }
    }

    private function userRules(?int $id = null): array
    {
        $required = $id ? 'sometimes|required' : 'required';
        $rules = [
            'name' => $required.'|string|max:255',
            'email' => [$id ? 'sometimes' : 'required', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'role_id' => $required.'|integer|exists:roles,id',
            'store_id' => 'nullable|integer|exists:stores,id',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'status' => 'sometimes|required|in:active,deactive',
        ];
        if (!$id) {
            $rules['password'] = 'required|string|min:6|max:255';
        }
        return $rules;
    }
}
