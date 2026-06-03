<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Authorization\UserAggregateId;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        // disable 済 account は token が未失効でも復活させない (TODO-25)。
        // show は副作用無しの閲覧なので監査ログは残さず、フォームを出さないための 410 のみ返す。
        if ($user->isDisabled()) {
            return response()->json(['error' => 'invite_user_disabled'], 410);
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

        // disable 済 account は invite から復活させない (TODO-25)。
        // 根本対策は DisableUserHandler 側で disable 時に token を失効させること
        // (= 通常はここに到達せず findValidByToken が null → 404)。 この分岐は
        //   (a) この修正以前に発行され disable された token
        //   (b) findValidByToken の後・以下の save の前に admin が disable する TOCTOU 残余窓
        // への defense-in-depth。 残余窓に lock/transaction を足さないのは、 LoginRequest が
        // disabled user の login を 422 で塞ぐため残余窓でも実アクセスが付与されないから (engineered enough)。
        // token は消費せず (= 二度目以降も 404 ではなく 410)、監査ログに記録する。
        if ($user->isDisabled()) {
            $this->recordDisabledRejection($user, $request);

            return response()->json([
                'error' => 'invite_user_disabled',
                'message' => 'This account has been disabled. Contact an administrator.',
            ], 410);
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
     * disable 済 user への invite accept 試行を audit_logs に記録する。
     *
     * これはアグリゲートの状態変化ではない (user は何も変わらない) ため、
     * EventSchemaRegistry / domain_events ストアには載せず、共通 writer
     * {@see AuditLog::record()} 経由で audit_logs に 1 行書き込む
     * ({@see RecordAuditLog} のドメインイベント経路と同じ writer)。
     * 公開エンドポイントなので Auth::id() は通常 null になる。
     */
    private function recordDisabledRejection(User $user, Request $request): void
    {
        // aggregate_id は uuid 列。 users.id は bigint なので、 ドメインイベント経路
        // ({@see \App\EventStore\EventSchemaRegistry} の 'user' stream) と同じ
        // {@see UserAggregateId} で決定的 uuid に解決し、 user の監査行を横断クエリできるようにする。
        AuditLog::record(
            eventType: 'InviteRejectedUserDisabled',
            aggregateType: 'user',
            aggregateId: UserAggregateId::fromUserId($user->id),
            payload: ['userId' => $user->id, 'reason' => 'user_disabled'],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            userId: Auth::id(),
        );
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
