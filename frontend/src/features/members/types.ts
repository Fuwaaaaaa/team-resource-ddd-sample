export interface MemberSkillDto {
  id: string;
  skillId: string;
  proficiency: number;
}

export interface MemberDto {
  id: string;
  name: string;
  standardWorkingHours: number;
  skills: MemberSkillDto[];
}

export interface MemberKpiActiveAllocation {
  allocationId: string;
  projectId: string;
  projectName: string;
  skillId: string;
  skillName: string;
  percentage: number;
  startDate: string;
  endDate: string;
  daysRemaining: number;
}

export interface MemberKpiUpcomingEnd {
  allocationId: string;
  projectId: string;
  projectName: string;
  daysRemaining: number;
  endDate: string;
}

export interface MemberKpiSkill {
  skillId: string;
  skillName: string;
  proficiency: number;
}

export interface MemberKpiDto {
  memberId: string;
  memberName: string;
  referenceDate: string;
  currentUtilization: number;
  remainingCapacity: number;
  isOverloaded: boolean;
  activeAllocationCount: number;
  activeAllocations: MemberKpiActiveAllocation[];
  upcomingEnds: MemberKpiUpcomingEnd[];
  skills: MemberKpiSkill[];
}
