import { NextResponse } from 'next/server';
import type { SkillGapWarningListDto } from '@/features/dashboard/types';

export async function GET() {
  const data: SkillGapWarningListDto = {
    referenceDate: '2026-03-19',
    totalWarnings: 5,
    warnings: [
      {
        memberId: 'm-2', memberName: 'Suzuki Hana',
        projectId: 'p-1', projectName: 'Project Alpha',
        skillId: 'sk-k8s', skillName: 'K8s',
        requiredLevel: 3, actualLevel: 1, deficitLevel: 2,
      },
      {
        memberId: 'm-4', memberName: 'Yamamoto Aoi',
        projectId: 'p-2', projectName: 'Project Beta',
        skillId: 'sk-ts', skillName: 'TypeScript',
        requiredLevel: 4, actualLevel: 3, deficitLevel: 1,
      },
      {
        memberId: 'm-6', memberName: 'Ito Kaito',
        projectId: 'p-1', projectName: 'Project Alpha',
        skillId: 'sk-react', skillName: 'React',
        requiredLevel: 4, actualLevel: 3, deficitLevel: 1,
      },
      {
        memberId: 'm-1', memberName: 'Tanaka Yuto',
        projectId: 'p-3', projectName: 'Project Gamma',
        skillId: 'sk-py', skillName: 'Python',
        requiredLevel: 4, actualLevel: 2, deficitLevel: 2,
      },
      {
        memberId: 'm-3', memberName: 'Sato Ren',
        projectId: 'p-2', projectName: 'Project Beta',
        skillId: 'sk-react', skillName: 'React',
        requiredLevel: 3, actualLevel: 1, deficitLevel: 2,
      },
    ],
  };

  return NextResponse.json(data);
}
