export interface RequiredSkillDto {
  id: string;
  skillId: string;
  requiredProficiency: number;
  headcount: number;
}

export type ProjectStatus = 'planning' | 'active' | 'completed' | 'canceled';

export interface ProjectDto {
  id: string;
  name: string;
  status: ProjectStatus;
  plannedStartDate: string | null;
  plannedEndDate: string | null;
  requiredSkills: RequiredSkillDto[];
}

export const PROJECT_STATUS_LABELS: Record<ProjectStatus, string> = {
  planning: '計画中',
  active: '稼働中',
  completed: '完了',
  canceled: '中止',
};

/** 現在 → 次の許可される遷移先 */
export const PROJECT_STATUS_TRANSITIONS: Record<ProjectStatus, ProjectStatus[]> = {
  planning: ['active', 'canceled'],
  active: ['completed', 'canceled'],
  completed: [],
  canceled: [],
};

export interface ProjectKpiSkillBreakdown {
  skillId: string;
  skillName: string;
  requiredHeadcount: number;
  qualifiedHeadcount: number;
  gap: number;
}

export interface ProjectKpiUpcomingEnd {
  allocationId: string;
  memberId: string;
  memberName: string;
  daysRemaining: number;
  endDate: string;
}

export interface ProjectKpiDto {
  projectId: string;
  projectName: string;
  status: ProjectStatus;
  referenceDate: string;
  fulfillmentRate: number;
  totalRequiredHeadcount: number;
  totalQualifiedHeadcount: number;
  activeAllocationCount: number;
  personMonthsAllocated: number;
  requiredSkillsBreakdown: ProjectKpiSkillBreakdown[];
  upcomingEnds: ProjectKpiUpcomingEnd[];
}
