export type ChangeRequestType = 'create_allocation' | 'revoke_allocation';
export type ChangeRequestStatus = 'pending' | 'approved' | 'rejected';

export interface CreateAllocationPayload {
  memberId: string;
  projectId: string;
  skillId: string;
  allocationPercentage: number;
  periodStart: string;
  periodEnd: string;
}

export interface RevokeAllocationPayload {
  allocationId: string;
}

export interface AllocationChangeRequestDto {
  id: string;
  type: ChangeRequestType;
  payload: CreateAllocationPayload | RevokeAllocationPayload | Record<string, unknown>;
  requestedBy: number;
  reason: string | null;
  status: ChangeRequestStatus;
  requestedAt: string;
  decidedBy: number | null;
  decidedAt: string | null;
  decisionNote: string | null;
  resultingAllocationId: string | null;
}
