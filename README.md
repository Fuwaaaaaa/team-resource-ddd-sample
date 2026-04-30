**English** | [日本語](./README.ja.md)

# Team Resource DDD Sample

A sample dashboard implementation that links team members' skill proficiency levels (1-5) to project allocations and visualizes resource surpluses and deficits.

The backend uses **Laravel 13 (PHP 8.3+)** with clean architecture / DDD, and the frontend uses **Next.js 14 (TypeScript)** with App Router + Atomic Design.

---

## Table of Contents

- [Architecture Overview](#architecture-overview)
- [Backend Design](#backend-design)
  - [Layer Structure](#layer-structure)
  - [Aggregates and Entities](#aggregates-and-entities)
  - [Domain Services](#domain-services)
  - [Application Layer and DTOs](#application-layer-and-dtos)
- [Frontend Design](#frontend-design)
  - [Server / Client Component Split](#server--client-component-split)
  - [Atomic Design](#atomic-design)
  - [State Management Separation](#state-management-separation)
  - [Performance Optimization](#performance-optimization)
- [Directory Structure](#directory-structure)
- [Admin Operations (Next 26)](#admin-operations-next-26)
- [Setup](#setup)

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│  Frontend (Next.js / TypeScript)                            │
│                                                             │
│  page.tsx (Server Component)                                │
│    └─ DashboardContent (Client Component)                   │
│         ├─ React Query × 3 hooks (parallel data fetching)   │
│         ├─ Zustand (UI filter state)                        │
│         └─ ResourceHeatmap → HeatmapCell                    │
└────────────────────────┬────────────────────────────────────┘
                         │ REST API (JSON)
┌────────────────────────┴────────────────────────────────────┐
│  Backend (Laravel / PHP)                                    │
│                                                             │
│  Interfaces  →  Application  →  Domain                      │
│  (Controller)   (Handler+DTO)   (Entity+Service)            │
│                       ↓                                     │
│                  Infrastructure                             │
│                  (Eloquent Repository / Service impl)       │
└─────────────────────────────────────────────────────────────┘
```

**Design principles:**
- The Domain layer has zero framework dependencies (POPO: Plain Old PHP Object)
- Eloquent is used only inside repositories in the Infrastructure layer
- Application-layer Handlers return DTOs; Eloquent models never leak outward
- The frontend cleanly separates server state (React Query) from UI state (Zustand)

---

## Backend Design

### Layer Structure

```
backend/app/
├── Domain/           Pure PHP. Core business rules
├── Application/      Use cases. Command/Query + Handler + DTO
├── Infrastructure/   Framework-dependent. Repository/service implementations
└── Interfaces/       HTTP Controller, FormRequest, API Resource
```

The dependency direction is **Domain ← Application ← Infrastructure / Interfaces**. The Domain layer depends on no other layer.

### Aggregates and Entities

This project has four aggregates.

#### Skill Aggregate

```
Domain/Skill/
├── Skill.php                  Aggregate root (skill master)
├── SkillId.php                Value object (identifier)
├── SkillName.php              Value object (1-100 chars)
├── SkillCategory.php          Value object (7 categories)
└── SkillRepositoryInterface.php
```

`SkillCategory` defines seven values: `programming_language`, `framework`, `infrastructure`, `database`, `design`, `management`, and `other`.

#### Member Aggregate

```
Domain/Member/
├── Member.php                 Aggregate root
├── MemberSkill.php            Child entity (skill held by a member)
├── SkillProficiency.php       Value object (proficiency 1-5)
├── StandardWorkingHours.php   Value object (standard working hours, default 8.0h)
├── MemberId.php / MemberName.php / MemberSkillId.php
├── MemberRepositoryInterface.php
└── Events/
    ├── MemberCreated.php
    └── MemberSkillUpdated.php
```

A `Member` holds multiple `MemberSkill`s. Each `MemberSkill` is a pair of `SkillId` (which skill) and `SkillProficiency` (proficiency 1-5). `StandardWorkingHours` is used for overload detection.

#### Project Aggregate

```
Domain/Project/
├── Project.php                Aggregate root
├── RequiredSkill.php          Child entity (skill requirement of the project)
├── RequiredProficiency.php    Value object (minimum required proficiency 1-5)
├── ProjectId.php / ProjectName.php / RequiredSkillId.php
├── ProjectRepositoryInterface.php
└── Events/
    └── ProjectRequirementChanged.php
```

A `Project` has multiple `RequiredSkill`s. Each `RequiredSkill` defines which skill is required, the minimum proficiency, and how many people are needed (headcount).

#### Allocation Aggregate

```
Domain/Allocation/
├── ResourceAllocation.php              Aggregate root
├── AllocationPercentage.php            Value object (0-100%)
├── AllocationPeriod.php                Value object (start date / end date)
├── AllocationStatus.php                Value object (active / revoked)
├── AllocationId.php
├── ResourceAllocationRepositoryInterface.php
└── Events/
    ├── AllocationCreated.php
    └── AllocationRevoked.php
```

`ResourceAllocation` expresses which member is allocated to which project, in which skill role, at what percentage of effort, and over what time period. Aggregates reference each other only by ID; they hold no direct references.

### Domain Services

```
Domain/Service/
├── AllocationServiceInterface.php   Domain service interface
├── ResourceSurplusDeficit.php       Surplus/deficit analysis result (value object)
├── SkillGapEntry.php                Per-skill gap (value object)
├── SkillGapAnalysis.php             Cross-project analysis result
├── TeamCapacitySnapshot.php         Snapshot of team capacity
├── MemberCapacityEntry.php          Per-member capacity
├── OverloadAnalysis.php             Overload analysis result
├── MemberOverloadEntry.php          Per-member overload information
└── SkillGapWarning.php              Skill shortage warning
```

`AllocationServiceInterface` is a stateless domain service that defines the following six methods:

| Method | Purpose |
|---------|------|
| `calculateSurplusDeficit()` | Calculate per-skill staffing surplus/deficit at the project level |
| `buildTeamCapacitySnapshot()` | Build a member-by-skill available-capacity matrix for the entire team |
| `canAllocate()` | Verify that an additional allocation does not exceed the 100% cap for a member |
| `analyzeSkillGaps()` | List skill shortages across multiple projects in priority order |
| `detectOverload()` | Detect overload by comparing a member's standard working hours vs. total allocations |
| `detectSkillGapWarnings()` | Generate warnings when an allocated member does not meet the required proficiency |

Domain services do not call repositories directly. The Application-layer Handler fetches the necessary data from repositories and passes it as arguments.

The implementation lives in `Infrastructure/Service/AllocationService.php` (pure PHP logic, no Eloquent dependency).

### Application Layer and DTOs

```
Application/Dashboard/
├── Queries/
│   ├── GetOverloadAnalysisQuery.php        Input parameters
│   ├── GetOverloadAnalysisHandler.php      Use case execution
│   ├── GetSkillGapWarningsQuery.php
│   └── GetSkillGapWarningsHandler.php
└── DTOs/
    ├── MemberOverloadDto.php               Primitive types only
    ├── OverloadAnalysisDto.php
    ├── SkillGapWarningDto.php
    └── SkillGapWarningListDto.php
```

**Role of the DTO:** The Handler fetches domain entities from repositories, passes them to the domain service, and converts the result into a DTO before returning. DTOs are composed solely of primitive types (`string`, `int`, `float`, `bool`), preventing domain objects or Eloquent models from leaking outward.

```
HTTP Request
  → Controller (Interfaces): builds Query object
  → Handler (Application): Repository → DomainService → DTO conversion
  ← returns DTO (primitive types only)
  → Controller → JSON Response
```

---

## Frontend Design

### Server / Client Component Split

In Next.js App Router, rather than marking everything `'use client'`, we split Server and Client Components appropriately.

```
page.tsx (Server Component)
│   ← SSRs static HTML (h1 title, etc.). Not included in the JS bundle
│
├── layout.tsx (Server Component)
│   └── Providers (Client Component)
│       └── QueryErrorBoundary (Client Component)
│
└── DashboardContent (Client Component)
    │   ← Must be Client because it uses Zustand / React Query hooks
    │
    └── ResourceHeatmap (Client Component)
        └── HeatmapCell (can also work as a Server Component)
            ← Uses no hooks, only memo. Runs as Client because the parent is Client,
              but we deliberately omit 'use client' to signal "this file itself
              can also run on the server".
```

Only the following four files are marked `'use client'`:

| File | Reason |
|---------|------|
| `providers.tsx` | QueryClientProvider (React Context) |
| `error-boundary.tsx` | Class Component (Error Boundary) |
| `dashboard-page.tsx` | Zustand hooks + useQueryClient |
| `ResourceHeatmap.tsx` | React Query hooks + useMemo |

### Atomic Design

```
components/
├── atoms/
│   └── HeatmapCell/          Smallest unit. Displays proficiency for one cell
└── molecules/
    └── ResourceHeatmap/      Heatmap composed of multiple atoms
```

- **HeatmapCell (Atom):** Color gradient for proficiency 1-5 + skill-gap indicator (red ring + `!` mark)
- **ResourceHeatmap (Molecule):** Merges three API data sources and renders a member-by-skill heatmap

### State Management Separation

Server state and UI state are clearly separated.

#### Server state: React Query (TanStack Query)

```typescript
// frontend/src/features/dashboard/api.ts

// Query key factory (used for cache invalidation)
export const dashboardKeys = {
  all: ['dashboard'] as const,
  capacity: (date: string) => [...dashboardKeys.all, 'capacity', date] as const,
  overload: (date: string) => [...dashboardKeys.all, 'overload', date] as const,
  skillGaps: (date: string, projectId?: string) => [...dashboardKeys.all, 'skillGaps', date, projectId ?? 'all'] as const,
};
```

The three hooks use different cache strategies depending on data volatility:

| Hook | staleTime | gcTime | Reason |
|--------|-----------|--------|------|
| `useTeamCapacity` | 5 min | 10 min | Team composition is relatively stable |
| `useOverloadAnalysis` | 2 min | 5 min | Sensitive to allocation changes |
| `useSkillGapWarnings` | 3 min | 10 min | Intermediate volatility |

In addition, the defaults enable `retry: 2` (retry twice on failure) and `refetchOnReconnect: true` (auto-refetch when the network reconnects).

#### UI state: Zustand

```typescript
// frontend/src/stores/useDashboardFilterStore.ts

interface DashboardFilterState {
  referenceDate: string;            // Reference date
  selectedProjectId: string | undefined;  // Project filter
  selectedCategories: SkillCategory[];    // Skill category filter
  showOverloadedOnly: boolean;      // Show only overloaded members
  searchMemberName: string;         // Member-name search
}
```

Each Zustand field is subscribed to via an individual selector `(s) => s.fieldName`, preventing re-renders when unrelated fields change.

### Performance Optimization

| Technique | Where applied | Effect |
|------|---------|------|
| `React.memo` (custom comparator) | HeatmapCell | Compares 4 fields to prevent unnecessary re-renders |
| `React.memo` | ResourceHeatmap | Memoization of the entire component |
| `useMemo` × 6 places | inside ResourceHeatmap | overloadMap, skillGapMap, filteredSkills, skillsByCategory, rows, filteredRows |
| Map-based O(1) lookup | overloadMap, skillGapMap | Constant-time per-cell data lookup |
| Separate staleTime | three React Query hooks | Wasteless cache tuned to data volatility |
| Per-field Zustand selectors | DashboardContent | Minimal re-renders per filter field |

### Strict TypeScript

- `tsconfig.json` enables `"strict": true`
- **Zero `any` types across the entire project**
- All API response types are defined in `features/dashboard/types.ts`
- TypeScript interfaces correspond 1:1 to backend PHP DTOs
- Composite keys are also type-safe via template literal types: `` SkillGapKey = `${string}:${string}` ``

### Error Handling

- **QueryErrorBoundary:** Catches rendering errors and provides a "Try again" button to recover
- **React Query error states:** Aggregates errors from each query and displays a red error message
- **Loading state:** Shows a pulse animation until all three queries finish loading
- **Empty state:** Shows a message when filter results are empty
- **Refresh button:** Manually re-runs all queries via `queryClient.invalidateQueries`

---

## Directory Structure

```
team-resource-ddd-sample/
│
├── backend/
│   └── app/
│       ├── Domain/                          # Domain layer (pure PHP, zero deps)
│       │   ├── Skill/                       #   Skill master aggregate
│       │   ├── Member/                      #   Member aggregate (holds skill proficiencies)
│       │   ├── Project/                     #   Project aggregate (holds skill requirements)
│       │   ├── Allocation/                  #   Resource allocation aggregate
│       │   └── Service/                     #   Domain services + result value objects
│       │
│       ├── Application/                     # Application layer
│       │   └── Dashboard/
│       │       ├── Queries/                 #   Query + Handler (CQRS)
│       │       └── DTOs/                    #   DTOs of primitive types only
│       │
│       └── Infrastructure/                  # Infrastructure layer
│           └── Service/
│               └── AllocationService.php    #   Domain service implementation
│
├── frontend/
│   └── src/
│       ├── app/                             # Next.js App Router
│       │   ├── page.tsx                     #   Server Component (static header)
│       │   ├── layout.tsx                   #   Server Component (HTML structure)
│       │   ├── dashboard-page.tsx           #   Client Component (filters + heatmap)
│       │   ├── providers.tsx                #   Client Component (QueryClient + ErrorBoundary)
│       │   ├── error-boundary.tsx           #   Client Component (error handling)
│       │   └── api/dashboard/               #   Mock API routes (3 endpoints)
│       │
│       ├── components/                      # Atomic Design
│       │   ├── atoms/HeatmapCell/           #   Proficiency cell (color + gap display)
│       │   └── molecules/ResourceHeatmap/   #   Heatmap body
│       │
│       ├── features/dashboard/              # Feature unit
│       │   ├── types.ts                     #   TypeScript type definitions (matches DTOs)
│       │   └── api.ts                       #   React Query hooks (three)
│       │
│       ├── stores/
│       │   └── useDashboardFilterStore.ts   #   Zustand (UI filter state)
│       │
│       └── lib/
│           └── query-client.ts              #   QueryClient configuration
│
└── README.md
```

---

## Admin Operations (Next 26)

Users with the `admin` role can add users, change roles, and reset passwords from the `/admin/users` screen via the UI. Backend domain events (`UserCreated` / `UserRoleChanged` / `UserPasswordReset`) are recorded to `audit_logs` through the existing `RecordAuditLog` listener and can be tracked from the `/audit-logs` screen (admin only).

Design notes:
- Role / authorization middleware is organized under `App\Domain\Authorization`. The `User` model is treated as the authentication principal and is kept distinct from domain aggregates (Member / Project / Allocation).
- Role changes are serialized for concurrent edits via OCC (`expectedUpdatedAt`) + DB transaction + `lockForUpdate`.
- Requests that try to change the "last admin" to a non-admin role are rejected with 422 (preventing system lockout).
- Password reset invalidates all of the target user's Sanctum API tokens and DB sessions (database session driver). When users reset their own password, the response includes `requiresRelogin: true`, and the UI redirects to `/login` automatically.

In the unlikely event that all admins are lost via the UI (e.g., everyone is now manager / viewer), recovery is possible via direct DB update:

```sql
UPDATE users SET role = 'admin' WHERE email = '<your_email>';
```

---

## Setup

### One-shot startup (Docker Compose)

```bash
cp .env.example .env
docker compose up --build
```

- Open `http://localhost:8080` in your browser
- Login: `admin@example.com` / `password`
- The dashboard and Members / Projects / Allocations CRUD are functional

The initial build performs the following:
1. Starts PostgreSQL 16 + health check
2. Runs `artisan key:generate` → `artisan migrate` → `artisan db:seed` inside the backend container
3. Runs `npm ci` + `npm run dev` inside the frontend container
4. nginx proxies `:8080` → `/api`, `/sanctum`, `/login` to the backend, and everything else to the frontend (same-origin so the Sanctum SPA cookie works as-is)

### Local standalone startup (when the above is not available)

Backend:
```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Frontend:
```bash
cd frontend
cp .env.example .env.local
npm install
npm run dev
```

You will need to change `NEXT_PUBLIC_API_BASE_URL` to something like `http://localhost:8000` (note that this becomes a different origin, so additional configuration is required to handle Sanctum session cookies correctly). The recommended setup is the same-origin Docker Compose approach.

### End-to-End tests (Playwright)

Critical-path browser tests live under `frontend/e2e/`. They run against the full Docker Compose stack on `http://localhost:8080`.

```bash
# Boot the stack first (in a separate terminal)
docker compose up

# Then, in frontend/, run the E2E suite
cd frontend
npm run e2e:install   # one-time: install the Chromium browser bundle
npm run e2e           # run all specs
npm run e2e:ui        # interactive Playwright UI
```

Spec files cover login (success / failure / unauthenticated redirect), RBAC (viewer cannot reach `/admin/users`, admin can), and the dashboard heatmap loading. See [`frontend/playwright.config.ts`](./frontend/playwright.config.ts). CI runs the same suite via the `E2E (Playwright)` job and uploads HTML reports on failure.
