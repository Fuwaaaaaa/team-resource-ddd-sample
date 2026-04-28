**[English](./README.md)** | 日本語

# Team Resource DDD Sample

チームメンバーのスキル熟練度（1-5）とプロジェクトへの工数割り当てを関連付け、リソースの過不足を視覚化するダッシュボードのサンプル実装です。

バックエンドは **Laravel 13（PHP 8.3+）** によるクリーンアーキテクチャ / DDD、フロントエンドは **Next.js 14（TypeScript）** による App Router + Atomic Design で構成しています。

---

## 目次

- [アーキテクチャ概要](#アーキテクチャ概要)
- [バックエンド設計](#バックエンド設計)
  - [レイヤー構成](#レイヤー構成)
  - [集約とエンティティ](#集約とエンティティ)
  - [ドメインサービス](#ドメインサービス)
  - [Application層とDTO](#application層とdto)
- [フロントエンド設計](#フロントエンド設計)
  - [Server / Client Componentsの使い分け](#server--client-componentsの使い分け)
  - [Atomic Designの採用](#atomic-designの採用)
  - [状態管理の分離](#状態管理の分離)
  - [パフォーマンス最適化](#パフォーマンス最適化)
- [ディレクトリ構成](#ディレクトリ構成)
- [セットアップ](#セットアップ)

---

## アーキテクチャ概要

```
┌─────────────────────────────────────────────────────────────┐
│  Frontend (Next.js / TypeScript)                            │
│                                                             │
│  page.tsx (Server Component)                                │
│    └─ DashboardContent (Client Component)                   │
│         ├─ React Query × 3 hooks (並列データ取得)            │
│         ├─ Zustand (UIフィルタ状態)                          │
│         └─ ResourceHeatmap → HeatmapCell                    │
└────────────────────────┬────────────────────────────────────┘
                         │ REST API (JSON)
┌────────────────────────┴────────────────────────────────────┐
│  Backend (Laravel / PHP)                                    │
│                                                             │
│  Interfaces層  →  Application層  →  Domain層                │
│  (Controller)     (Handler+DTO)     (Entity+Service)        │
│                        ↓                                    │
│                   Infrastructure層                          │
│                   (Eloquent Repository / Service実装)        │
└─────────────────────────────────────────────────────────────┘
```

**設計原則:**
- Domain層はフレームワーク依存ゼロ（POPO: Plain Old PHP Object）
- EloquentはInfrastructure層のリポジトリ内でのみ使用
- Application層のHandlerはDTOを返却し、Eloquentモデルが外部に漏れない
- フロントエンドはサーバー状態（React Query）とUI状態（Zustand）を明確に分離

---

## バックエンド設計

### レイヤー構成

```
backend/app/
├── Domain/           純粋PHP。ビジネスルールの中核
├── Application/      ユースケース。Command/Query + Handler + DTO
├── Infrastructure/   フレームワーク依存。リポジトリ実装、サービス実装
└── Interfaces/       HTTP Controller、FormRequest、API Resource
```

各レイヤーの依存方向は **Domain ← Application ← Infrastructure / Interfaces** です。Domain層は他のどのレイヤーにも依存しません。

### 集約とエンティティ

本プロジェクトには4つの集約（Aggregate）があります。

#### Skill集約

```
Domain/Skill/
├── Skill.php                  集約ルート（スキルマスタ）
├── SkillId.php                値オブジェクト（識別子）
├── SkillName.php              値オブジェクト（1-100文字）
├── SkillCategory.php          値オブジェクト（7種のカテゴリ）
└── SkillRepositoryInterface.php
```

`SkillCategory` は `programming_language`, `framework`, `infrastructure`, `database`, `design`, `management`, `other` の7種を定義しています。

#### Member集約

```
Domain/Member/
├── Member.php                 集約ルート
├── MemberSkill.php            子エンティティ（メンバーが持つスキル）
├── SkillProficiency.php       値オブジェクト（熟練度 1-5）
├── StandardWorkingHours.php   値オブジェクト（標準稼働時間、デフォルト8.0h）
├── MemberId.php / MemberName.php / MemberSkillId.php
├── MemberRepositoryInterface.php
└── Events/
    ├── MemberCreated.php
    └── MemberSkillUpdated.php
```

`Member` は複数の `MemberSkill` を保持します。各 `MemberSkill` は `SkillId`（どのスキルか）と `SkillProficiency`（1-5の熟練度）のペアです。`StandardWorkingHours` はOverload検出に使われます。

#### Project集約

```
Domain/Project/
├── Project.php                集約ルート
├── RequiredSkill.php          子エンティティ（プロジェクトが求めるスキル要件）
├── RequiredProficiency.php    値オブジェクト（最低要求熟練度 1-5）
├── ProjectId.php / ProjectName.php / RequiredSkillId.php
├── ProjectRepositoryInterface.php
└── Events/
    └── ProjectRequirementChanged.php
```

`Project` は複数の `RequiredSkill` を持ちます。各 `RequiredSkill` には「どのスキルが」「最低熟練度いくつで」「何人必要か（headcount）」が定義されます。

#### Allocation集約

```
Domain/Allocation/
├── ResourceAllocation.php              集約ルート
├── AllocationPercentage.php            値オブジェクト（0-100%）
├── AllocationPeriod.php                値オブジェクト（開始日/終了日）
├── AllocationStatus.php                値オブジェクト（active / revoked）
├── AllocationId.php
├── ResourceAllocationRepositoryInterface.php
└── Events/
    ├── AllocationCreated.php
    └── AllocationRevoked.php
```

`ResourceAllocation` は「どのメンバーを」「どのプロジェクトに」「どのスキルロールで」「何%の工数で」「いつからいつまで」割り当てるかを表現します。集約間はIDで参照し、直接の参照は持ちません。

### ドメインサービス

```
Domain/Service/
├── AllocationServiceInterface.php   ドメインサービスのインターフェース
├── ResourceSurplusDeficit.php       過不足分析結果（値オブジェクト）
├── SkillGapEntry.php                スキル別ギャップ（値オブジェクト）
├── SkillGapAnalysis.php             複数プロジェクト横断分析結果
├── TeamCapacitySnapshot.php         チームキャパシティのスナップショット
├── MemberCapacityEntry.php          メンバー別キャパシティ
├── OverloadAnalysis.php             過負荷分析結果
├── MemberOverloadEntry.php          メンバー別過負荷情報
└── SkillGapWarning.php              スキル不足警告
```

`AllocationServiceInterface` はステートレスなドメインサービスで、以下の6メソッドを定義しています:

| メソッド | 目的 |
|---------|------|
| `calculateSurplusDeficit()` | プロジェクト単位でスキル別の人員過不足を算出 |
| `buildTeamCapacitySnapshot()` | チーム全体のメンバー×スキルの空きキャパシティマトリクスを構築 |
| `canAllocate()` | メンバーへの追加割り当てが100%上限を超えないか検証 |
| `analyzeSkillGaps()` | 複数プロジェクト横断でスキル不足を優先度順にリスト化 |
| `detectOverload()` | メンバーの標準稼働時間 vs 割り当て合計から過負荷を検出 |
| `detectSkillGapWarnings()` | 割り当て済みメンバーが要求熟練度を満たさない場合に警告を生成 |

ドメインサービスはリポジトリを直接呼びません。Application層のHandlerが必要なデータをリポジトリから取得し、引数として渡します。

実装は `Infrastructure/Service/AllocationService.php` にあります（純粋PHPロジック、Eloquent依存なし）。

### Application層とDTO

```
Application/Dashboard/
├── Queries/
│   ├── GetOverloadAnalysisQuery.php        入力パラメータ
│   ├── GetOverloadAnalysisHandler.php      ユースケース実行
│   ├── GetSkillGapWarningsQuery.php
│   └── GetSkillGapWarningsHandler.php
└── DTOs/
    ├── MemberOverloadDto.php               プリミティブ型のみ
    ├── OverloadAnalysisDto.php
    ├── SkillGapWarningDto.php
    └── SkillGapWarningListDto.php
```

**DTOの役割:** Handlerはリポジトリからドメインエンティティを取得し、ドメインサービスに渡し、結果をDTOに変換して返します。DTOはプリミティブ型（`string`, `int`, `float`, `bool`）のみで構成され、ドメインオブジェクトやEloquentモデルが外部に漏れることを防ぎます。

```
HTTP Request
  → Controller (Interfaces層): Queryオブジェクト生成
  → Handler (Application層): Repository → DomainService → DTO変換
  ← DTO (プリミティブ型のみ) を返却
  → Controller → JSON Response
```

---

## フロントエンド設計

### Server / Client Componentsの使い分け

Next.js App Routerでは、すべてを `'use client'` にするのではなく、Server / Client Componentを適切に分離しています。

```
page.tsx (Server Component)
│   ← 静的なHTML（h1タイトル等）をSSR。JSバンドルに含まれない
│
├── layout.tsx (Server Component)
│   └── Providers (Client Component)
│       └── QueryErrorBoundary (Client Component)
│
└── DashboardContent (Client Component)
    │   ← Zustand hooks / React Query hooks を使用するため Client が必須
    │
    └── ResourceHeatmap (Client Component)
        └── HeatmapCell (Server Component としても動作可能)
            ← hooks を使わず memo のみ。親が Client なので Client として実行されるが、
              'use client' を付けず「このファイル自体はサーバーでも使える」と明示
```

`'use client'` を付与しているファイルは以下の4つだけです:

| ファイル | 理由 |
|---------|------|
| `providers.tsx` | QueryClientProvider（React Context） |
| `error-boundary.tsx` | Class Component（Error Boundary） |
| `dashboard-page.tsx` | Zustand hooks + useQueryClient |
| `ResourceHeatmap.tsx` | React Query hooks + useMemo |

### Atomic Designの採用

```
components/
├── atoms/
│   └── HeatmapCell/          最小単位。1セルの熟練度表示
└── molecules/
    └── ResourceHeatmap/      複数のatomを組み合わせたヒートマップ
```

- **HeatmapCell（Atom）:** 熟練度1-5の色グラデーション表示 + スキルギャップ表示（赤ring + `!`マーク）
- **ResourceHeatmap（Molecule）:** 3つのAPIデータソースをマージし、メンバー×スキルのヒートマップを描画

### 状態管理の分離

サーバー状態とUI状態を明確に分けています。

#### サーバー状態: React Query（TanStack Query）

```typescript
// frontend/src/features/dashboard/api.ts

// クエリキーファクトリ（キャッシュ無効化に使用）
export const dashboardKeys = {
  all: ['dashboard'] as const,
  capacity: (date: string) => [...dashboardKeys.all, 'capacity', date] as const,
  overload: (date: string) => [...dashboardKeys.all, 'overload', date] as const,
  skillGaps: (date: string, projectId?: string) => [...dashboardKeys.all, 'skillGaps', date, projectId ?? 'all'] as const,
};
```

3つのフックはデータの揮発性に応じて異なるキャッシュ戦略を持ちます:

| フック | staleTime | gcTime | 理由 |
|--------|-----------|--------|------|
| `useTeamCapacity` | 5分 | 10分 | チーム構成は比較的安定 |
| `useOverloadAnalysis` | 2分 | 5分 | アロケーション変動に敏感 |
| `useSkillGapWarnings` | 3分 | 10分 | 中間の揮発性 |

加えて、デフォルト設定で `retry: 2`（失敗時2回リトライ）と `refetchOnReconnect: true`（ネットワーク復帰時に自動再取得）を有効にしています。

#### UI状態: Zustand

```typescript
// frontend/src/stores/useDashboardFilterStore.ts

interface DashboardFilterState {
  referenceDate: string;            // 基準日
  selectedProjectId: string | undefined;  // プロジェクトフィルタ
  selectedCategories: SkillCategory[];    // スキルカテゴリフィルタ
  showOverloadedOnly: boolean;      // 過負荷メンバーのみ表示
  searchMemberName: string;         // メンバー名検索
}
```

Zustandの各フィールドは個別のセレクタ `(s) => s.fieldName` でサブスクライブし、無関係なフィールド変更時の再レンダリングを防止しています。

### パフォーマンス最適化

| 手法 | 適用箇所 | 効果 |
|------|---------|------|
| `React.memo`（カスタム比較関数） | HeatmapCell | 4フィールド比較で不要な再レンダリング防止 |
| `React.memo` | ResourceHeatmap | コンポーネント全体のメモ化 |
| `useMemo` × 6箇所 | ResourceHeatmap内 | overloadMap, skillGapMap, filteredSkills, skillsByCategory, rows, filteredRows |
| Mapベース O(1) ルックアップ | overloadMap, skillGapMap | セルごとのデータ検索を定数時間に |
| staleTime分離 | 3つのReact Queryフック | データ揮発性に応じた無駄のないキャッシュ |
| Zustand個別セレクタ | DashboardContent | フィルタフィールドごとに最小限の再レンダリング |

### TypeScriptの厳格な運用

- `tsconfig.json` で `"strict": true` を有効化
- **`any` 型はプロジェクト全体でゼロ**
- すべてのAPI レスポンス型を `features/dashboard/types.ts` に定義
- バックエンドのPHP DTOと1対1で対応するTypeScriptインターフェース
- テンプレートリテラル型 `` SkillGapKey = `${string}:${string}` `` で合成キーも型安全

### エラーハンドリング

- **QueryErrorBoundary:** レンダリングエラーをキャッチし「Try again」ボタンで復帰可能
- **React Query エラー状態:** 各クエリのエラーを統合し、赤いエラーメッセージを表示
- **Loading状態:** 3クエリすべての読み込み完了まで pulse アニメーションを表示
- **Empty状態:** フィルタ結果が空の場合のメッセージ表示
- **Refreshボタン:** `queryClient.invalidateQueries` で全クエリを手動再取得

---

## ディレクトリ構成

```
team-resource-ddd-sample/
│
├── backend/
│   └── app/
│       ├── Domain/                          # ドメイン層（純粋PHP、依存ゼロ）
│       │   ├── Skill/                       #   スキルマスタ集約
│       │   ├── Member/                      #   メンバー集約（スキル熟練度を保持）
│       │   ├── Project/                     #   プロジェクト集約（スキル要件を保持）
│       │   ├── Allocation/                  #   リソース割り当て集約
│       │   └── Service/                     #   ドメインサービス + 結果値オブジェクト
│       │
│       ├── Application/                     # アプリケーション層
│       │   └── Dashboard/
│       │       ├── Queries/                 #   Query + Handler（CQRS）
│       │       └── DTOs/                    #   プリミティブ型のみのDTO
│       │
│       └── Infrastructure/                  # インフラ層
│           └── Service/
│               └── AllocationService.php    #   ドメインサービス実装
│
├── frontend/
│   └── src/
│       ├── app/                             # Next.js App Router
│       │   ├── page.tsx                     #   Server Component（静的ヘッダー）
│       │   ├── layout.tsx                   #   Server Component（HTML構造）
│       │   ├── dashboard-page.tsx           #   Client Component（フィルタ+ヒートマップ）
│       │   ├── providers.tsx                #   Client Component（QueryClient+ErrorBoundary）
│       │   ├── error-boundary.tsx           #   Client Component（エラーハンドリング）
│       │   └── api/dashboard/               #   モックAPIルート（3エンドポイント）
│       │
│       ├── components/                      # Atomic Design
│       │   ├── atoms/HeatmapCell/           #   熟練度セル（色+ギャップ表示）
│       │   └── molecules/ResourceHeatmap/   #   ヒートマップ本体
│       │
│       ├── features/dashboard/              # Feature単位
│       │   ├── types.ts                     #   TypeScript型定義（DTO対応）
│       │   └── api.ts                       #   React Query hooks（3つ）
│       │
│       ├── stores/
│       │   └── useDashboardFilterStore.ts   #   Zustand（UIフィルタ状態）
│       │
│       └── lib/
│           └── query-client.ts              #   QueryClient設定
│
└── README.md
```

---

## 管理者運用 (Next 26)

`admin` ロールのユーザーは `/admin/users` 画面でユーザーの追加・ロール変更・パスワード再発行を UI 上で実行できます。バックエンドのドメインイベント (`UserCreated` / `UserRoleChanged` / `UserPasswordReset`) は既存の `RecordAuditLog` リスナー経由で `audit_logs` に記録され、`/audit-logs` 画面 (admin のみ) から追跡できます。

設計上の補足:
- ロール / 認可ミドルウェアは `App\Domain\Authorization` 配下に整理しています。`User` モデルは認証主体として扱い、ドメイン集約 (Member / Project / Allocation) と区別します。
- ロール変更は OCC (`expectedUpdatedAt`) + DB transaction + `lockForUpdate` で並行編集を直列化します。
- 「最後の admin」を非 admin に変更しようとしたリクエストは 422 で拒否します (システム lockout 防止)。
- パスワードリセットは Sanctum の API トークンと、対象ユーザーの DB セッション (database session driver) を全て無効化します。自分自身をリセットした場合はレスポンス `requiresRelogin: true` で UI が `/login` へ自動遷移します。

万が一、UI から admin が完全に喪失した (例: ユーザー全員が manager / viewer) 場合は DB 直接更新で復旧できます:

```sql
UPDATE users SET role = 'admin' WHERE email = '<your_email>';
```

---

## セットアップ

### 一発起動（Docker Compose）

```bash
cp .env.example .env
docker compose up --build
```

- `http://localhost:8080` をブラウザで開く
- ログイン: `admin@example.com` / `password`
- ダッシュボード / Members / Projects / Allocations の CRUD が動作

初回ビルドで以下が行われます:
1. PostgreSQL 16 起動 + ヘルスチェック
2. backend コンテナ内で `artisan key:generate` → `artisan migrate` → `artisan db:seed`
3. frontend コンテナで `npm ci` + `npm run dev`
4. nginx が `:8080` → `/api`, `/sanctum`, `/login` を backend へ、それ以外を frontend へプロキシ（同一オリジンのため Sanctum SPA Cookie がそのまま使える）

### ローカル個別起動（上記が使えない場合）

バックエンド:
```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

フロントエンド:
```bash
cd frontend
cp .env.example .env.local
npm install
npm run dev
```

`NEXT_PUBLIC_API_BASE_URL` を `http://localhost:8000` 相当に変更する必要があります（ただし異なるオリジンになるため Sanctum のセッション cookie を正しく扱うには追加設定が必要）。推奨は Docker Compose での同一オリジン起動です。
