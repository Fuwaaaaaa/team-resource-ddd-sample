# TODOS

Next 26 (RBAC Admin Console) で defer された案件と、その後のレビューで見つかった改善ポイント。各項目は `/plan-ceo-review` または `/plan-eng-review` で計画化してから着手してください。

**最終更新: 2026-04-30**

| # | 内容 | 優先度 | 想定 PR |
|---|---|---|---|
| 1 | 監査ログ実用化: aggregate_id を人間可読 (Member名 / Project名) に解決、日付範囲フィルタ、actor (user) フィルタ、payload before/after diff、行クリックで対象画面へジャンプ | P1 | Next 27 |
| 2 | アサイン提案 (`/allocations/suggestions`) の説明力強化: スコア内訳の表示、候補メンバーの現在週/翌週負荷との衝突警告、直近 90 日の同スキル割当履歴サマリ、空結果時の「最低熟練度を下げると候補が出ます」ヒント | P2 | Next 28 |
| 3 | パスワードリセット時のメール送信。SMTP 設定 + 招待リンク生成 + 初回ログイン強制パスワード変更 | P3 | (任意) |
| 4 | ユーザー削除 / 無効化機能。退職者対応として soft delete もしくは disabled フラグ | P3 | (任意) |
| 5 | パスワードポリシー: 招待リンク + 初回ログイン強制変更 (16 文字生成 password の手渡しからの脱却) | P3 | (TODO-3 と統合) |
| 6 | MFA / SSO (OIDC, SAML 等) 導入。エンタープライズ要件向け | P4 | (将来) |
| 7 | `App\Enums\UserRole` を `App\Domain\Authorization\UserRole` に移動。Authorization bounded context への整理 | P3 | (リファクタ専用 PR) |
| 8 | `audit_logs` テーブルに `ip_address` / `user_agent` カラム追加。操作者追跡の強化 | P2 | (TODO-1 と同時着手推奨) |
| 9 | Prometheus / Datadog 統合。`admin.user.*` カウンタの可視化 | P3 | (任意) |
| 10 | Alert: `last_admin_lock` 連続発生時の通知 (=誤操作 or 攻撃の早期検知) | P3 | (TODO-9 後) |
| 11 | Artisan コマンド `admin:create-user --role=admin --email=... --name=...`。CLI からの初期 admin 作成 | P3 | (任意) |
| 12 | 「最後の admin」race condition の真の並行テスト (pcntl_fork / parallel test process)。現在は logical 検査のみ | P4 | (任意) |
| 13 | `EventDescriptorResolver::resolve` と `RecordAuditLog::buildRecord` の `match (true)` 重複を 1 つの schema 関数に統合 (16 ケース) | P2 | (リファクタ専用 PR) |
| 14 | E2E テスト基盤 (Playwright 推奨) 導入。admin / Auth / KPI を critical path で覆う | P2 | (任意) |
| 15 | `DESIGN.md` を起こし、token system / spacing / typography / icon 戦略 (現在 text-only) を成文化 | P3 | (任意) |
| 16 | 既存 `frontend/src/app/audit-logs/page.tsx` の token migration: raw `bg-white` / `text-gray-*` を semantic tokens (`bg-surface` / `text-fg-muted` 等) に置換し、ダークモードを完全対応 | P2 | (Next 27 と同時着手推奨) |
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
