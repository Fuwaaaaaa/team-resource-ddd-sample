export interface AllocationDto {
  id: string;
  memberId: string;
  projectId: string;
  skillId: string;
  allocationPercentage: number;
  periodStart: string;
  periodEnd: string;
  status: 'active' | 'revoked';
}
