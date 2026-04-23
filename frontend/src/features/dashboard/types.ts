// === Skill category union (from backend SkillCategory::VALID_CATEGORIES) ===

export type SkillCategory =
  | 'programming_language'
  | 'framework'
  | 'infrastructure'
  | 'database'
  | 'design'
  | 'management'
  | 'other';

// === Skill info (for column headers) ===

export interface SkillDto {
  id: string;
  name: string;
  category: SkillCategory;
}

// === TeamCapacitySnapshot — heatmap primary data source ===

export interface MemberCapacityEntryDto {
  memberId: string;
  memberName: string;
  availablePercentage: number; // 0-100
  skillProficiencies: Record<string, number | null>; // skillId -> proficiency (1-5 or null)
}

export interface TeamCapacitySnapshotDto {
  entries: MemberCapacityEntryDto[];
  skills: SkillDto[];
  referenceDate: string;
}

// === Overload analysis (from OverloadAnalysisDto) ===

export interface MemberOverloadDto {
  memberId: string;
  memberName: string;
  standardHoursPerDay: number;
  totalAllocatedPercentage: number;
  allocatedHoursPerDay: number;
  overloadHours: number;
  isOverloaded: boolean;
}

export interface OverloadAnalysisDto {
  members: MemberOverloadDto[];
  overloadedCount: number;
  referenceDate: string;
}

// === Skill gap warnings (from SkillGapWarningListDto) ===

export interface SkillGapWarningDto {
  memberId: string;
  memberName: string;
  projectId: string;
  projectName: string;
  skillId: string;
  skillName: string;
  requiredLevel: number;
  actualLevel: number | null;
  deficitLevel: number;
}

export interface SkillGapWarningListDto {
  warnings: SkillGapWarningDto[];
  totalWarnings: number;
  referenceDate: string;
}

// === KPI summary (dashboard top banner) ===

export interface KpiSummaryDto {
  referenceDate: string;
  averageFulfillmentRate: number;
  activeProjectCount: number;
  overloadedMemberCount: number;
  upcomingEndsThisWeek: number;
  skillGapsTotal: number;
}

// === Capacity forecast (quarterly demand/supply outlook) ===

export type ForecastSeverity = 'ok' | 'watch' | 'critical';

export interface SkillForecastDto {
  skillId: string;
  skillName: string;
  demandHeadcount: number;
  supplyHeadcountEquivalent: number;
  gap: number;
  severity: ForecastSeverity;
}

export interface ForecastBucketDto {
  month: string; // YYYY-MM
  skills: SkillForecastDto[];
}

export interface CapacityForecastDto {
  referenceDate: string;
  monthsAhead: number;
  buckets: ForecastBucketDto[];
}

// === KPI trend (time-series) ===

export interface KpiTrendPointDto {
  date: string; // YYYY-MM-DD
  averageFulfillmentRate: number;
  activeProjectCount: number;
  overloadedMemberCount: number;
  upcomingEndsThisWeek: number;
  skillGapsTotal: number;
}

export interface KpiTrendDto {
  referenceDate: string;
  days: number;
  points: KpiTrendPointDto[];
}

// === Derived types for component internal use ===

/** Lookup key for skill gap: "memberId:skillId" */
export type SkillGapKey = `${string}:${string}`;

/** Processed cell data for rendering */
export interface HeatmapCellData {
  proficiency: number | null;
  hasSkillGap: boolean;
  gapDeficit: number | null;
  requiredLevel: number | null;
}

/** Processed row data for rendering */
export interface HeatmapRowData {
  memberId: string;
  memberName: string;
  availablePercentage: number;
  isOverloaded: boolean;
  overloadHours: number;
  totalAllocatedPercentage: number;
  cells: Record<string, HeatmapCellData>;
}
