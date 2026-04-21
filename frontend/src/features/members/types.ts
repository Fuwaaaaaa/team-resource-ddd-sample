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
