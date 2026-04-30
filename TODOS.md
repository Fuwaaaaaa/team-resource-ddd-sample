# TODOS

Next 26 (RBAC Admin Console) で defer された案件と、その後のレビューで見つかった改善ポイント。各項目は `/plan-ceo-review` または `/plan-eng-review` で計画化してから着手してください。

**最終更新: 2026-04-30**

| # | 内容 | 優先度 | 想定 PR |
|---|---|---|---|
| 1 | ~~監査ログ実用化: aggregate_id を人間可読 (Member名 / Project名) に解決、日付範囲フィルタ、actor (user) フィルタ、payload before/after diff、行クリックで対象画面へジャンプ~~ → **Next 27 で resolved (2026-04-30)**: aggregate_label (member / project / user) 解決、from/to/userId フィルタ追加、`*Changed` 系の `from → to` 整形、member/project/user 行のクリック遷移、operator IP 表示。before/after diff は payload に from/to 両方を持つイベント (`UserRoleChanged` 等) のみ対応 — 他イベントは event 拡張時に対応 | — | done |
| 2 | ~~アサイン提案 (`/allocations/suggestions`) の説明力強化~~ → **Next 28 で resolved (2026-04-30)**: scoreBreakdown (capacity/proficiency/experience) を内訳棒で可視化、`nextWeekConflict` バッジで翌週 100% 飽和を警告、recentAssignments で直近 90 日同スキル履歴を最大 5 件表示、空結果時 hint (`no_members_with_skill` / `min_proficiency_too_high` / `all_members_at_capacity`) で UI が原因別ガイドを出す | — | done |
| 3 | ~~パスワードリセット時のメール送信。SMTP 設定 + 招待リンク生成 + 初回ログイン強制パスワード変更~~ → **resolved (2026-04-30)**: 新規 user 作成を 16 文字 password 手渡し → **招待リンク 1 本** に置換。 `users` に `invite_token` (64-char hex) + `invite_token_expires_at` (24h) を追加、 `CreateUserHandler` で `UserInviteMail` 送信、 公開エンドポイント `GET /api/invite/{token}` + `POST /api/invite/{token}/accept` を追加。 frontend に `/invite/[token]` ページを追加 (中盤から password 設定)。 docker-compose に **mailpit** (SMTP 受信 UI: localhost:8025) を追加。 admin による既存 user の reset-password は別フロー (16 文字生成のまま) で TODO-3 範囲外。 招待リンクで本人が password を決めるため、 「初回ログイン強制変更」は不要 | — | done |
| 4 | ~~ユーザー削除 / 無効化機能~~ → **resolved (2026-04-30)**: `users.disabled_at` カラム追加 (soft delete でなく明示フラグ — audit_logs.user_id 参照を保全)。 `POST /api/admin/users/{id}/{disable,enable}` 2 endpoint、 `DisableUserHandler` で last-admin guard + self-check + sanctum tokens / sessions を即時失効、 `LoginRequest` で disabled user の login を 422 で拒否。 `UserDisabled` / `UserEnabled` ドメインイベント (audit_logs に記録)。 frontend は admin user 一覧に \"disabled\" バッジ + Disable/Re-enable ボタン (自身は disabled 不可) | — | done |
| 5 | ~~パスワードポリシー: 招待リンク + 初回ログイン強制変更~~ → **resolved with TODO-3 (2026-04-30)**: 招待リンク経由で本人が決めた password (12 文字以上) で運用開始するため、 「初回ログイン強制変更」は概念的に不要 | — | done |
| 6 | MFA / SSO (OIDC, SAML 等) 導入。エンタープライズ要件向け | P4 | (将来) |
| 7 | ~~`App\Enums\UserRole` を `App\Domain\Authorization\UserRole` に移動~~ → **resolved (2026-04-30)**: enum を `App\Domain\Authorization` 名前空間に移動し、 15 ファイルの use 文を一括更新。 `App\Enums` ディレクトリは削除 (空) | — | done |
| 8 | ~~`audit_logs` テーブルに `ip_address` / `user_agent` カラム追加。操作者追跡の強化~~ → **Next 27 で resolved (2026-04-30)**: migration + `RecordAuditLog` で Request 経由取得。CLI / queue では null | — | done |
| 9 | ~~Prometheus / Datadog 統合。`admin.user.*` カウンタの可視化~~ → **resolved (2026-04-30)**: `GET /api/metrics` (Prometheus text format)、 `Authorization: Bearer ${METRICS_TOKEN}` で認可、 token 未設定時は 404。 Counters: `admin_user_created_total` / `admin_user_role_changed_total` / `admin_user_password_reset_total` (`audit_logs` から COUNT) と `admin_user_email_taken_total` / `admin_user_last_admin_lock_total` / `admin_user_cannot_change_own_role_total` (Cache::increment via `MetricsCounter`、 例外 render パスで自動加算) の計 6 種 | — | done |
| 10 | ~~Alert: `last_admin_lock` 連続発生時の通知~~ → **resolved (2026-04-30)**: `infra/prometheus/alerts.yml` に 4 ルール追加 (`LastAdminLockBurst` page severity / `CannotChangeOwnRoleBurst` warn / `EmailTakenBurst` warn / `AdminMetricsScrapeDown` page)。 Prometheus + AlertManager / Datadog OpenMetrics 両方で使える。 `infra/prometheus/{prometheus.yml.example, README.md}` でローカル quickstart まで documented | — | done |
| 11 | ~~Artisan コマンド `admin:create-user --role=admin --email=... --name=...`~~ → **resolved (2026-04-30)**: `App\Console\Commands\AdminCreateUserCommand` を追加し、 既存 `CreateUserHandler` を再利用。 16 文字ランダムパスワードを生成して 1 回だけ stdout に表示、 `UserCreated` ドメインイベント経由で audit_logs にも自動記録される (HTTP 経由作成と完全に同じ経路)。 `--json` オプションで scripting 用出力にも対応。 ついでに `CreateUserHandler` の email 重複検知を SQLite/MySQL/pgsql 全 driver portable な `UniqueConstraintViolationException` キャッチに変更 | — | done |
| 12 | 「最後の admin」race condition の真の並行テスト (pcntl_fork / parallel test process)。現在は logical 検査のみ | P4 | (任意) |
| 13 | ~~`EventDescriptorResolver::resolve` と `RecordAuditLog::buildRecord` の `match (true)` 重複を 1 つの schema 関数に統合 (16 ケース)~~ → **PR #32 で resolved (2026-04-30)**: `App\EventStore\EventSchemaRegistry::describe()` を SoT 化し、両経路から委譲 | — | done |
| 14 | ~~E2E テスト基盤 (Playwright 推奨) 導入~~ → **Next 29 で resolved (2026-04-30)**: `frontend/playwright.config.ts` + `frontend/e2e/{login,rbac,dashboard}.spec.ts`、CI に `E2E (Playwright)` job (docker compose 起動 → npx playwright test → HTML report artifact) を追加。Login / RBAC / dashboard の critical path をカバー | — | done |
| 15 | ~~`DESIGN.md` を起こし、token system / spacing / typography / icon 戦略 (現在 text-only) を成文化~~ → **resolved (2026-04-30)**: 既存の token / typography / spacing / border-radius / iconography (Unicode marks) / Atomic Design 構成 / i18n / migration 状況を `DESIGN.md` に成文化 | — | done |
| 16 | ~~既存 `frontend/src/app/audit-logs/page.tsx` の token migration~~ → **Next 27 で resolved (2026-04-30)**: `bg-white` / `text-gray-*` 系を semantic tokens (`bg-surface` / `bg-surface-muted` / `border-border` / `text-fg` / `text-fg-muted` / `bg-primary/10` 等) に置換。`[data-theme="dark"]` で自動追従 | — | done |
| 17 | ~~`DomainEventStore` の pgsql aggregate+FOR_UPDATE バグ~~ → **PR #26 で resolved (2026-04-28)** | — | done |
| 18 | ~~DomainEventStore の retry path を実際に exercise する test~~ → **PR #31 で resolved (2026-04-29)**: `isUniqueViolation` recognizer の判定境界 (pgsql 23505 / sqlite 23000+UNIQUE / FK・NOT NULL false-positive 排除 / message fallback) を網羅。retry loop 自体の exercise はシングルプロセス PHPUnit の限界として acknowledged (production の `Log::warning` 発出 + コードインスペクションで担保) — 詳細は `tests/Feature/EventStore/DomainEventStoreConcurrencyTest.php` 冒頭 NOTE | — | done |
| 19 | ~~DomainEventStore retry に backoff/jitter + retry log 追加~~ → **PR #31 で resolved (2026-04-29)**: `BACKOFF_BASE_MICROS=1000`, `random_int(1, base) * 2^(attempt-1)` の指数バックオフ + `Log::warning('DomainEventStore.append.retry', ...)` を `DomainEventStore::append` に実装 | — | done |
| 20 | ~~`phpunit.xml` を sqlite-only から pgsql matrix に拡張~~ → **PR #30 で resolved (2026-04-30)**: CI の `.github/workflows/ci.yml` に pgsql matrix job を追加し、Next 26 期間中に発覚した「pgsql で fail / sqlite で pass」クラスを再発防止 | — | done |
| 21 | ~~deploy 後に `/login` を GET 200 確認する smoke test~~ → **PR #30 で resolved (2026-04-30)**: docker compose URL surface smoke として CI に組み込み (PR #28 の nginx routing バグが 6 週間検知されなかった反省) | — | done |

## 着手の進め方

1. plan を `~/.claude/plans/` か `docs/designs/` に書く (`/office-hours` 推奨)
2. `/plan-ceo-review` または `/plan-eng-review` で精査
3. 該当ブランチを切って実装
4. `/ship` で land + deploy
