/**
 * Dictionary-based i18n. 型安全のためキーは全て union 型で固定し、
 * 新規キー追加時は ja/en 両方に追加しないと型エラーになる。
 *
 * 外部ライブラリ (next-intl 等) を使わない理由:
 *   - プロジェクトが既に zustand + React Query に寄っており、追加 dep を避けたい
 *   - App Router で全ページが 'use client' のため SSR 翻訳は不要
 *   - キー数が限定的 (50 前後) で補間/複数形も簡単
 */

export const SUPPORTED_LOCALES = ['ja', 'en'] as const;
export type Locale = (typeof SUPPORTED_LOCALES)[number];
export const DEFAULT_LOCALE: Locale = 'ja';

export const ja = {
  // Header / nav
  'nav.heatmap': 'ヒートマップ',
  'nav.timeline': 'タイムライン',
  'nav.members': 'メンバー',
  'nav.projects': 'プロジェクト',
  'nav.allocations': 'アサイン',
  'nav.requests': '変更申請',
  'nav.audit': '監査',
  'header.signOut': 'サインアウト',
  'header.signingOut': 'サインアウト中…',
  'header.language': '言語',
  'header.theme': 'テーマ',
  'header.themeLight': 'ライト',
  'header.themeDark': 'ダーク',
  'header.themeSystem': 'システム',
  'header.menu': 'メニュー',

  // Dashboard KPI banner
  'kpi.fulfillmentRate': '全 active/planning の平均充足率',
  'kpi.projectsCount': '{count} プロジェクト',
  'kpi.overloadedMembers': '過負荷メンバー数',
  'kpi.needsAttention': '要対処',
  'kpi.capacityFine': '余裕あり',
  'kpi.upcomingEnds': '今週終了するアサイン',
  'kpi.within7Days': '7 日以内',
  'kpi.skillGaps': 'スキル不足人数(総計)',
  'kpi.skillGapsSub': '全 active/planning の gap 合計',
  'kpi.loadFailed': 'KPI サマリの取得に失敗しました。',

  // Capacity forecast
  'forecast.title': 'キャパシティ予測',
  'forecast.periodLabel': '期間',
  'forecast.monthsSuffix': 'ヶ月',
  'forecast.loadFailed': 'キャパシティ予測の取得に失敗しました。',
  'forecast.noDemand': '予測対象の需要データがありません。',
  'forecast.skillColumn': 'スキル',
  'forecast.cellHint': 'セル表記: ギャップ (供給 / 需要) — 供給 1.0 = 1 名フルタイム相当。',

  // KPI trend
  'trend.title': 'KPI トレンド',
  'trend.loadFailed': 'トレンドデータの取得に失敗しました。',
  'trend.empty': 'スナップショット未蓄積です。',
  'trend.emptyHint': 'を毎日実行するとデータが貯まります。',
  'trend.metricFulfillment': '平均充足率',
  'trend.metricOverloaded': '過負荷メンバー数',
  'trend.metricSkillGaps': 'スキル不足(総計)',
  'trend.metricUpcomingEnds': '今週終了アサイン',
  'trend.daysSuffix': '日',
  'trend.dateLabel': '日付: {date}',

  // Allocation request
  'request.formTitle': '新規アサイン申請',
  'request.member': 'Member',
  'request.project': 'Project',
  'request.skill': 'Skill',
  'request.percentage': '割合 (%)',
  'request.startDate': '開始日',
  'request.endDate': '終了日',
  'request.reason': '理由 (任意)',
  'request.selectPlaceholder': '-- 選択 --',
  'request.submit': '申請を提出',
  'request.submitting': '送信中…',
  'request.submitted': '申請を提出しました (ID: {id})',

  // Common
  'common.loading': '読込中…',
  'common.error': 'エラーが発生しました',
  'common.cancel': 'キャンセル',
  'common.ok': 'OK',
} as const;

// 型安全を保つため en は ja と同じキーを必須とする
export type TranslationKey = keyof typeof ja;

export const en: Record<TranslationKey, string> = {
  'nav.heatmap': 'Heatmap',
  'nav.timeline': 'Timeline',
  'nav.members': 'Members',
  'nav.projects': 'Projects',
  'nav.allocations': 'Allocations',
  'nav.requests': 'Requests',
  'nav.audit': 'Audit',
  'header.signOut': 'Sign out',
  'header.signingOut': 'Signing out…',
  'header.language': 'Language',
  'header.theme': 'Theme',
  'header.themeLight': 'Light',
  'header.themeDark': 'Dark',
  'header.themeSystem': 'System',
  'header.menu': 'Menu',

  'kpi.fulfillmentRate': 'Avg fulfillment (active/planning)',
  'kpi.projectsCount': '{count} projects',
  'kpi.overloadedMembers': 'Overloaded members',
  'kpi.needsAttention': 'Needs attention',
  'kpi.capacityFine': 'Capacity fine',
  'kpi.upcomingEnds': 'Assignments ending this week',
  'kpi.within7Days': 'within 7 days',
  'kpi.skillGaps': 'Total skill gap headcount',
  'kpi.skillGapsSub': 'Sum of gaps across active/planning',
  'kpi.loadFailed': 'Failed to load KPI summary.',

  'forecast.title': 'Capacity forecast',
  'forecast.periodLabel': 'Range',
  'forecast.monthsSuffix': ' mo',
  'forecast.loadFailed': 'Failed to load capacity forecast.',
  'forecast.noDemand': 'No demand data available.',
  'forecast.skillColumn': 'Skill',
  'forecast.cellHint': 'Cell: gap (supply / demand) — supply 1.0 = one full-time equivalent.',

  'trend.title': 'KPI trend',
  'trend.loadFailed': 'Failed to load trend data.',
  'trend.empty': 'No snapshots captured yet.',
  'trend.emptyHint': ' daily to populate the series.',
  'trend.metricFulfillment': 'Avg fulfillment rate',
  'trend.metricOverloaded': 'Overloaded members',
  'trend.metricSkillGaps': 'Skill gap (total)',
  'trend.metricUpcomingEnds': 'Ends this week',
  'trend.daysSuffix': 'd',
  'trend.dateLabel': 'Date: {date}',

  'request.formTitle': 'New allocation request',
  'request.member': 'Member',
  'request.project': 'Project',
  'request.skill': 'Skill',
  'request.percentage': 'Allocation (%)',
  'request.startDate': 'Start date',
  'request.endDate': 'End date',
  'request.reason': 'Reason (optional)',
  'request.selectPlaceholder': '-- select --',
  'request.submit': 'Submit request',
  'request.submitting': 'Submitting…',
  'request.submitted': 'Request submitted (ID: {id})',

  'common.loading': 'Loading…',
  'common.error': 'An error occurred',
  'common.cancel': 'Cancel',
  'common.ok': 'OK',
};

export const messages: Record<Locale, Record<TranslationKey, string>> = {
  ja,
  en,
};
