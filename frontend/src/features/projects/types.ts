export interface RequiredSkillDto {
  id: string;
  skillId: string;
  requiredProficiency: number;
  headcount: number;
}

export interface ProjectDto {
  id: string;
  name: string;
  requiredSkills: RequiredSkillDto[];
}
