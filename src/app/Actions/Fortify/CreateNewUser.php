<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use App\Http\Requests\Auth\RegisterRequest;

class CreateNewUser implements CreatesNewUsers
{
    /**
     * 新規ユーザーのバリデーション＆作成
     */
    public function create(array $input): User
    {
        $req = app(RegisterRequest::class);

        Validator::make($input, $req->rules(), $req->messages(), $req->attributes())
            ->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }
}
