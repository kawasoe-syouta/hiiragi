<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate(['password' => ['required', 'string']]);

        if (! hash_equals((string) config('site.admin_password'), $request->string('password')->value())) {
            return response()->json(['message' => 'パスワードが違います'], 401);
        }

        return response()->json(['token' => config('site.admin_token')]);
    }
}
