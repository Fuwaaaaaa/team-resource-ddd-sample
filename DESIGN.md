# Design System

This is the design source-of-truth for the **Team Resource Dashboard** UI. It documents the color tokens, typography, spacing, border-radius, iconography, and component organization that every screen should follow.

The system is intentionally minimal: text-only iconography, semantic color tokens with built-in dark-mode support, and Atomic Design with no `organisms/` layer (yet). New screens should compose existing tokens before introducing new primitives.

> Implementation lives at:
> - `frontend/src/styles/tokens.css` (CSS custom properties — single source)
> - `frontend/tailwind.config.ts` (semantic class names)
> - `frontend/src/app/globals.css` (Tailwind + token import)

---

## Philosophy

1. **Semantic over raw.** Use `bg-surface` / `text-fg-muted` / `border-border` instead of `bg-white` / `text-gray-500` / `border-gray-200`. Components stay theme-agnostic; palette swaps ripple through automatically.
2. **One source of truth.** All colors live in `tokens.css` as CSS custom properties (`--color-*`). Tailwind's `theme.extend.colors` just re-exposes them with `rgb(var(--name) / <alpha-value>)` so opacity modifiers (`bg-primary/10`) keep working.
3. **Dark mode via CSS variables.** The same Tailwind classes adapt automatically when `[data-theme="dark"]` is set on a container — no `dark:` variant prefixes in component code.
4. **No icon library.** The project ships no icon font, no SVG sprite, no `lucide-react`. Adding one is out of scope. Use Unicode marks (see [Iconography](#iconography)).
5. **Boring HTML first.** Native `<select>` / `<input type="date">` / `<input type="number">` over custom widgets unless there's a real reason. Lower bundle size, better a11y by default.

---

## Color Tokens

All values are defined as `R G B` triplets so Tailwind can apply alpha via the slash syntax. Pair token name with the role, **never** with the literal palette swatch.

### Surfaces

| Token | Light (rgb) | Dark (rgb) | Use |
|---|---|---|---|
| `bg` | 249 250 251 (gray-50) | 17 24 39 | Page-level background (rendered by `<body>`) |
| `surface` | 255 255 255 (white) | 31 41 55 | Cards, panels, modal bodies, table backgrounds |
| `surface-muted` | 243 244 246 (gray-100) | 55 65 81 | Subdued cells, table headers, payload pre blocks |
| `border` | 229 231 235 (gray-200) | 75 85 99 | Default 1 px border on cards / inputs / table dividers |

### Text

| Token | Light | Dark | Use |
|---|---|---|---|
| `fg` | 17 24 39 (gray-900) | 243 244 246 | Body text, headings |
| `fg-muted` | 107 114 128 (gray-500) | 156 163 175 | Secondary copy, captions, table column headers, placeholder hints |

### Brand & Semantic

| Token | Light | Dark | Use |
|---|---|---|---|
| `primary` | 37 99 235 (blue-600) | 96 165 250 | Primary actions (Sign in, Apply), links, focus ring |
| `primary-hover` | 29 78 216 (blue-700) | 59 130 246 | Hover state for `primary` buttons |
| `warning` | 234 88 12 (orange-600) | (same) | Soft warnings, "next-week conflict" badges |
| `danger` | 220 38 38 (red-600) | (same) | Destructive actions (Revoke), error text |
| `danger-bg` | 254 226 226 (red-100) | (same) | Error message panels (paired with `danger` border + text) |
| `success` | 22 163 74 (green-600) | (same) | Successful states ("active" status, success toasts) |

> Dark-mode overrides are listed only when they exist. Tokens without a dark entry render the same value in both themes — they're already neutral enough.

### Heatmap & domain-specific

| Token | Use |
|---|---|
| `heatmap-1` … `heatmap-5` | Member skill proficiency 1..5 (red-200 → green-300, accessible foreground guaranteed) |
| `heatmap-null` | Skill not held by member (gray-100) |
| `skillgap-ring` / `skillgap-bg` | Skill-shortage warning ring + tinted background |

These are intentionally not dark-mode-aware — heatmap fills carry the same hue mapping in both themes.

### Color rules

- **Never use raw palette classes** (`bg-white`, `text-gray-500`, `border-blue-200`) in new code.
- **Brand chip pattern**: `bg-primary/10 text-primary` (alpha-10 tint + on-color text). Reuse `warning/10`/`danger/10`/`success/10` for warning / danger / success chips.
- **Borders are always `border-border`**, including modal dividers and table-row separators (`border-t border-border`).
- **Only `audit-logs/page.tsx` and `allocations/page.tsx` are fully token-migrated today** (see TODO-16). Other pages still use legacy palette classes; **convert them as you touch them**.

---

## Typography

There is no custom font — the app inherits the system stack via Tailwind's default. Locale is `ja` (set on `<html lang>`).

### Sizes (Tailwind class → use)

| Class | Use |
|---|---|
| `text-[10px]` / `text-[11px]` | UUID-style monospace strings, dense table metadata. Avoid for prose. |
| `text-xs` (12 px) | Captions, table cell content, helper text under inputs |
| `text-sm` (14 px) | Default body text, form input value, list rows |
| `text-base` (16 px) | Inherit from `body`. Rarely used directly in pages. |
| `text-lg` (18 px) | Prominent inline copy (rare). |
| `text-xl` (20 px) | Section subtitles |
| `text-2xl` (24 px) | Page-level `<h1>` (e.g. `Resource Heatmap`, `Audit logs`) |

`text-xs` is by far the most common (250+ occurrences); `text-sm` second. Anything larger than `text-2xl` is unused — there is no marketing surface in this product.

### Weights

| Class | Use |
|---|---|
| `font-normal` | Body copy (default — usually inherited) |
| `font-medium` | Labels, table headers, chip labels, button text |
| `font-semibold` | Section titles inside cards (e.g. `<h2>` of a card) |
| `font-bold` | Page-level headings (`<h1>`) |

`font-medium` is used heavily — every label and chip. `font-bold` should be reserved for the single page-title heading.

### Monospace

`font-mono` is reserved for content that is literally a token / id:
- UUID display: `font-mono text-[10px] text-fg-muted`
- IP address display: `font-mono text-[10px]`
- Diff payload `from → to` blocks
- Pre-formatted JSON in audit logs: `font-mono text-[11px]`

---

## Spacing & Layout

The Tailwind 4 px scale (`p-1` = 4 px, `p-2` = 8 px, …) is used throughout. Common patterns:

### Containers

- Page shell: `max-w-[1400px] mx-auto px-4 py-8 space-y-{4|6}`. The `1400px` cap is consistent across `/audit-logs`, `/allocations`, `/members`, `/projects`. Don't widen it.
- Card/panel: `p-4 bg-surface rounded-lg border border-border`. For "this is a result, attention here" panels, use `border-2` and a thicker visual weight (e.g. `border-2 border-purple-200` for the suggestions panel — *the sole exception to "tokens only", and only because there is no `accent-bg` token yet*).

### Form inputs

- Input / select size: `px-3 py-1.5 text-sm border border-border rounded-md bg-surface text-fg`
- Label: `block text-xs font-medium text-fg-muted mb-1`
- Field group: `<div>` with the label + input — no extra wrapper class needed unless laying out side-by-side

### Buttons

| Variant | Class |
|---|---|
| Primary | `px-4 py-1.5 text-sm font-medium text-white bg-primary rounded-md hover:bg-primary-hover disabled:opacity-50` |
| Secondary | `px-4 py-1.5 text-sm font-medium text-primary bg-primary/10 border border-primary/20 rounded-md hover:bg-primary/20` |
| Tertiary / link-like | `text-xs text-fg-muted hover:text-fg` |
| Destructive | `px-2 py-1 text-xs text-danger hover:bg-danger-bg rounded` |

Buttons in tables use the smaller `px-2 py-1 text-xs` to fit row height.

### Tables

- `<table className="w-full text-sm">`
- `<thead className="bg-surface-muted text-fg-muted">` with cells `px-4 py-2 text-left font-medium`
- `<tbody>` rows: `border-t border-border align-top`
- Cell padding: `px-4 py-2` (or `px-4 py-2 text-xs` for dense content)

### Vertical rhythm

- Page sections: `space-y-4` (gap-16 between cards) or `space-y-6` for emphasized separation
- Form fields stacked: `space-y-2` to `space-y-4` depending on density

---

## Border Radius

Two values cover everything:

| Class | Use |
|---|---|
| `rounded-md` (6 px) | Buttons, inputs, chips, dense list items |
| `rounded-lg` (8 px) | Cards, panels, modal bodies, table containers |

`rounded` (4 px) and `rounded-full` are reserved for explicit cases (notification dot, badge pill). `rounded-sm` and `rounded-xl` are not used.

---

## Iconography

**No icon library is installed**, intentionally. Adding one (lucide-react, heroicons, etc.) is out of scope. Until that decision changes, use:

### Unicode marks

| Glyph | Meaning | Examples in code |
|---|---|---|
| `→` | Arrow, transition, period range | `2026-04-01 → 2026-09-30`, `from → to` payload diff |
| `←` | Back navigation | `← ホームへ戻る` (Forbidden component) |
| `✓` | Success, completed | "✓ Imported <count>" (`ImportButton`) |
| `✕` / `×` | Close / dismiss | Modal close, simulation panel dismiss |
| `⚠` / `⚠️` | Warning | "⚠ 翌週負荷 100%" badge, "⚠️ 翌週負荷 100% 以上" reasons string |
| `+` | Add / create | `+ 新規ユーザー`, `+ Add member` |
| `·` | Inline separator | `reason1 · reason2 · reason3` in candidate explanation |
| `—` | Empty value placeholder | `— select —`, `— system —`, `— All —` (filter selects) |

### Accessibility

- **All non-text glyphs are decorative**. The surrounding text is the screen-reader signal. Don't use the glyph as the only label.
- **Interactive elements that LOOK like icons** must have `aria-label` (see `AppHeader` menu / theme / language toggles, `Forbidden` back link).
- **Heatmap cells** carry full text via `aria-label="memberName: skillName proficiency 3/5"` — the colored block alone is decorative.

### When to break this rule

If a future screen genuinely needs > 5 distinct icons in close proximity (e.g. a settings panel with toolbar), open a discussion to add a small SVG sprite — but don't pull in a 200 KB icon library for 5 glyphs.

---

## Component Organization (Atomic Design)

Two layers in active use, layout one on the side. There is **no `organisms/` directory yet** — when something larger than a molecule emerges, create one and split this doc accordingly.

```
frontend/src/components/
├── atoms/        # Primitives: HeatmapCell, RoleBadge, Forbidden, ExportButton, ImportButton
├── molecules/    # Composites: ResourceHeatmap, UserCreateModal, KpiTrendChart, NotificationsBell, …
└── layout/       # Page-shell: AppHeader (only)
```

### Naming

- **Atoms** are usually single files (`RoleBadge.tsx`) or small folders with a co-located test (`HeatmapCell/HeatmapCell.tsx` + `__tests__/`).
- **Molecules** can be single files or folders. Folders are preferred when the molecule has 2+ sub-components or fixtures.
- A `__tests__/` sibling holds the matching `*.test.tsx` (Jest + Testing Library). E2E specs live separately under `frontend/e2e/`.

### When to add an atom vs molecule

- **Atom**: presentational, reads `props` only, no `useQuery` / no Zustand, no `useState` outside trivial UI state.
- **Molecule**: composes 2+ atoms, may pull data via React Query, may dispatch to Zustand. Forms / modals / charts belong here.
- **Page** (`src/app/**/page.tsx`): orchestrates molecules + handles routing concerns. No styling logic of its own beyond layout glue.

---

## State management

(Not strictly design but inseparable from UI patterns.)

| Concern | Tool |
|---|---|
| Server state (data fetched from `/api/*`) | React Query (`useQuery` / `useMutation`) |
| UI state (filters, modal open/close, theme, locale) | `useState` for component-local; Zustand for cross-component (`useThemeStore`, `useLocaleStore`) |
| Forms | Local `useState` + native browser validation. No form library. |

No Redux. No Context except for React Query / theme bootstrap.

---

## i18n

The app ships **ja + en**. Default locale is `ja`. All user-facing strings should pass through `useTranslation()`:

```tsx
const t = useTranslation();
return <h1>{t('admin.users.title')}</h1>;
```

Translation keys live in `frontend/src/lib/i18n/messages.ts`. **Do not hard-code Japanese or English strings** in new components — the typed `TranslationKey` will reject keys that aren't defined for both locales.

---

## Migration plan (current state)

This doc captures intent; the codebase is partially there.

| Page / Component | Token state | Action |
|---|---|---|
| `audit-logs/page.tsx` | ✅ Fully migrated (PR #33) | — |
| `login/page.tsx` | ⚠ Mixed | Convert `bg-white` / `bg-gray-50` / `text-gray-*` / `border-gray-*` to semantic tokens when next touched |
| `allocations/page.tsx` | ⚠ Suggestions panel migrated (PR #34); the rest still uses raw classes | Same — opportunistic |
| `members/page.tsx`, `projects/page.tsx`, `timeline/page.tsx`, `admin/users/page.tsx` | ⚠ Raw palette | Each page is one focused PR (~30 min). Track as TODO when you queue it. |
| Forbidden, RoleBadge, ImportButton, ExportButton | ✅ Already semantic | — |

Don't bundle full migration into a feature PR — it inflates the diff and obscures functional changes. Open a dedicated `chore/token-migrate-{page}` PR per file.

---

## Adding a new token

1. **Add the CSS variable** to both `:root` and `[data-theme="dark"]` blocks in `frontend/src/styles/tokens.css`. Comment with the closest Tailwind palette equivalent so reviewers can sanity-check the hue.
2. **Expose to Tailwind** in `frontend/tailwind.config.ts` `theme.extend.colors` using the `rgb(var(--name) / <alpha-value>)` pattern (so `bg-yourtoken/20` works).
3. **Document it in this file**, in the appropriate table.
4. **Use it.** Then ratchet — replace any occurrence that fits the new role.

If you find yourself wanting a one-off color that has no clear semantic role, **don't** add a token. Use the closest existing one. Tokens are for repeated semantic meaning, not for every distinct hue.

---

## Things this doc deliberately does not cover

- **Animation / motion**: there is none currently, beyond CSS hover transitions on buttons. When animation enters the app, this section becomes relevant.
- **Illustration / imagery**: the product is data-dense and uses no decorative imagery. Adding any requires a separate design decision.
- **Print / export styling**: the only export today is CSV / PDF (server-rendered). When in-browser print views appear, add a section.

When any of the above starts appearing in production, update this doc as part of the same PR.
