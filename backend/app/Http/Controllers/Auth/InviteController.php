<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Authorization\UserAggregateId;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
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
 *
 * 例外: user が既に disable されているとき (admin が招待発行後に disable した場合) は
 * token が技術的に valid であっても 410 Gone で reject し、 audit_logs に記録する。
 * 「disabled に対する未消化 token を受け取った相手」 が password を設定してアカウント
 * を有効化してしまうのを防ぐ (offboarding 後に invite が来るケースの安全網)。
 */
class InviteController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $user = $this->findByToken($token);
        if ($user === null) {
            return response()->json(['error' => 'invite_invalid_or_expired'], 404);
        }
        if ($user->isDisabled()) {
            $this->auditDisabledInviteUse($user, phase: 'show');

            return response()->json(['error' => 'invite_disabled'], 410);
        }
        if ($user->invite_token_expires_at === null || $user->invite_token_expires_at->isPast()) {
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

        $user = $this->findByToken($token);
        if ($user === null) {
            return response()->json(['error' => 'invite_invalid_or_expired'], 404);
        }
        if ($user->isDisabled()) {
            $this->auditDisabledInviteUse($user, phase: 'accept');

            return response()->json(['error' => 'invite_disabled'], 410);
        }
        if ($user->invite_token_expires_at === null || $user->invite_token_expires_at->isPast()) {
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
     * Token に該当する user を返す (失効・disabled 状態は問わない)。
     * 呼び出し側で disabled / expired の優先順位を判定する。
     */
    private function findByToken(string $token): ?User
    {
        if ($token === '' || strlen($token) !== 64) {
            return null;
        }

        return User::query()->where('invite_token', $token)->first();
    }

    /**
     * disabled な user 宛の invite token が使われた事象を audit_logs に記録する。
     * 公開エンドポイントなので actor (user_id) は null。 IP / UA で操作者を追跡する。
     */
    private function auditDisabledInviteUse(User $user, string $phase): void
    {
        AuditLog::create([
            'user_id' => null,
            'event_type' => 'InviteRejectedDisabledUser',
            'aggregate_type' => 'user',
            'aggregate_id' => UserAggregateId::fromUserId($user->id),
            'payload' => [
                'email' => $user->email,
                'phase' => $phase, // 'show' | 'accept'
                'reason' => 'user_disabled',
            ],
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 512),
            'created_at' => now(),
        ]);
    }
}
