<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255|exists:users,email',
            'password' => 'required|min:4|max:15',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            $admin = User::where('email', $request->email)->first();
            if($admin){
                if (!Hash::check($request->password, $admin->password)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid credentials',
                    ]);
                }

                // Generate a plain text token for the seller
                $token = $admin->createToken('user-token')->plainTextToken;

                return response()->json([
                    'status' => 'success',
                    'message' => 'Login Successful',
                    'admin' => $admin,
                    'token' => $token,
                ]);                
            }else{
                return response()->json([
                    'status' => 'error',
                    'message' => 'Your Email Does not Exist.',
                ]); // 403 Forbidden status code
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Login failed: ' . $e->getMessage(),
            ]);
        }

    }
}
