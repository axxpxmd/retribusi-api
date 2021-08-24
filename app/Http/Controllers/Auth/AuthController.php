<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of welcome
 *
 * @author Asip Hamdi
 * Github : axxpxmd
 */

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;

// Models
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $this->validate($request, [
            'username' => 'required',
            'password' => 'required'
        ], [
            'username.required' => 'Username tidak boleh kosong.',
            'password.required' => 'Password tidak boleh kosong.'
        ]);

        // Get Params
        $username = $request->username;
        $password = $request->password;

        try {
            $user = User::select('id', 'username')
                ->where('username', $username)->where('password', md5($password))
                ->first();

            if ($user == null) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Username atau password salah!'
                ], 403);
            }

            $data = array(
                'id' => Crypt::encrypt($user->id),
                'username' => $user->username,
            );

            return response()->json([
                'status'  => 200,
                'message' => 'Succesfully',
                'user'    => $data,
                'token'   => JWTAuth::fromuser($user),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function logout()
    {
        try {
            auth()->logout();
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }

        return response()->json(['message' => 'Sukses!.']);
    }
}
