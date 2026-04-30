<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * 招待リンクの公開エンドポイント (auth:sanctum 配下に置かない)。
 *
 *   GET  /api/invite/{token}         → user info プレビュー (form をレンダする前の挨拶用)
 *   POST /api/invite/{token}/accept  → password を設定して invite を消化
 *
 * Token は admin が user を作成したときに発行された 64-char hex で、
 * 24 時間有効・single-use。 accept されると invite_token / invite_token_expires_at は
 * 両方 null に戻り、 同じ token は二度と使えない。
 */
class InviteController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $user = $this->findValidByToken($token);
        if ($user === null) {
            return response()->json(['error' => 'invite_invalid_or_expired'], 404);
        }

        return response()->json([
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
        ]);
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'min:12', 'max:72', 'confirmed'],
        ], [
            'password.confirmed' => 'パスワードと確認用パスワードが一致しません。',
            'password.min' => 'パスワードは 12 文字以上にしてください。',
        ]);

        $user = $this->findValidByToken($token);
        if ($user === null) {
            return response()->json(['error' => 'invite_invalid_or_expired'], 404);
        }

        $user->forceFill([
            'password' => Hash::make((string) $request->input('password')),
            'invite_token' => null,
            'invite_token_expires_at' => null,
        ])->save();

        return response()->json([
            'status' => 'ok',
            'email' => $user->email,
        ]);
    }

    /**
     * Token に該当しかつ未失効の user を返す。 未該当 / 失効 / 既に accept 済は null。
     */
    private function findValidByToken(string $token): ?User
    {
        if ($token === '' || strlen($token) !== 64) {
            return null;
        }

        return User::query()
            ->where('invite_token', $token)
            ->where('invite_token_expires_at', '>', now())
            ->first();
    }
}
