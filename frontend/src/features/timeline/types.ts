export interface TimelineAllocation {
  id: string;
  projectId: string;
  projectName: string;
  skillId: string;
  skillName: string;
  percentage: number;
  periodStart: string;
  periodEnd: string;
}

export interface TimelineRow {
  memberId: string;
  memberName: string;
  allocations: TimelineAllocation[];
}

export interface TimelineResponse {
  periodStart: string;
  periodEnd: string;
  rows: TimelineRow[];
}
