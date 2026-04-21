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

export interface AllocationSimulationDto {
  wouldCreate: AllocationDto;
  currentTotalPercentage: number;
  projectedTotalPercentage: number;
  projectedAvailablePercentage: number;
  projectedOverloaded: boolean;
  projectedOverloadHours: number;
}
