export type AbsenceType = 'vacation' | 'sick' | 'holiday' | 'training' | 'other';

export interface AbsenceDto {
  id: string;
  memberId: string;
  startDate: string; // YYYY-MM-DD
  endDate: string;
  type: AbsenceType;
  note: string;
  canceled: boolean;
  daysInclusive: number;
}

export const ABSENCE_TYPE_LABELS: Record<AbsenceType, string> = {
  vacation: '有給休暇',
  sick: '病欠',
  holiday: '休日',
  training: '研修',
  other: 'その他',
};
