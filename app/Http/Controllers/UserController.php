<?php


namespace App\Http\Controllers;


use App\Entities\Customer;
use App\Http\Services\Users\UserService;
use App\Mail\ForgotPassword;
use App\Models\PasswordReset;
use App\Models\User;
use App\Models\UserRole;
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
        return $this->successResponse($this->userService->getByStore($request->store_id));
    }

    public function store(Request $request)
    {
//        $request->validate(OrderValidator::store());
        try {
            DB::beginTransaction();
            $user = $this->userService->store($request->all());

            if (!$user) {
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
//        $request->validate(OrderValidator::store());
        try {
            DB::beginTransaction();
            $data = $request->only('name', 'phone', 'email', 'address', 'store_id', 'role_id', 'status');
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
       

            
        $user->update(['status' => 'deactive']);

        
        $deleted = $user->delete();

        
        if ($deleted) {
            return $this->successResponse(true, 'Người dùng đã được xóa thành công');
        } else {
            return $this->errorResponse('Xảy ra lỗi khi xóa người dùng', 500);
        }
        }
}
