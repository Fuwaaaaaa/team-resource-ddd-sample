import { NextResponse } from 'next/server';
import type { TeamCapacitySnapshotDto } from '@/features/dashboard/types';

export async function GET() {
  const data: TeamCapacitySnapshotDto = {
    referenceDate: '2026-03-19',
    skills: [
      { id: 'sk-ts',     name: 'TypeScript',  category: 'programming_language' },
      { id: 'sk-go',     name: 'Go',          category: 'programming_language' },
      { id: 'sk-py',     name: 'Python',      category: 'programming_language' },
      { id: 'sk-php',    name: 'PHP',         category: 'programming_language' },
      { id: 'sk-react',  name: 'React',       category: 'framework' },
      { id: 'sk-next',   name: 'Next.js',     category: 'framework' },
      { id: 'sk-laravel',name: 'Laravel',     category: 'framework' },
      { id: 'sk-docker', name: 'Docker',      category: 'infrastructure' },
      { id: 'sk-k8s',    name: 'K8s',         category: 'infrastructure' },
      { id: 'sk-aws',    name: 'AWS',         category: 'infrastructure' },
      { id: 'sk-pg',     name: 'PostgreSQL',  category: 'database' },
      { id: 'sk-redis',  name: 'Redis',       category: 'database' },
      { id: 'sk-figma',  name: 'Figma',       category: 'design' },
      { id: 'sk-pm',     name: 'PM',          category: 'management' },
    ],
    entries: [
      {
        memberId: 'm-1', memberName: 'Tanaka Yuto',
        availablePercentage: 20,
        skillProficiencies: {
          'sk-ts': 5, 'sk-go': 3, 'sk-py': 2, 'sk-php': null,
          'sk-react': 5, 'sk-next': 4, 'sk-laravel': null, 'sk-docker': 4,
          'sk-k8s': 3, 'sk-aws': 3, 'sk-pg': 4, 'sk-redis': 3,
          'sk-figma': 1, 'sk-pm': 2,
        },
      },
      {
        memberId: 'm-2', memberName: 'Suzuki Hana',
        availablePercentage: 0,
        skillProficiencies: {
          'sk-ts': 4, 'sk-go': null, 'sk-py': 5, 'sk-php': 3,
          'sk-react': 4, 'sk-next': 3, 'sk-laravel': 4, 'sk-docker': 3,
          'sk-k8s': 1, 'sk-aws': 2, 'sk-pg': 5, 'sk-redis': 4,
          'sk-figma': null, 'sk-pm': 3,
        },
      },
      {
        memberId: 'm-3', memberName: 'Sato Ren',
        availablePercentage: 50,
        skillProficiencies: {
          'sk-ts': 2, 'sk-go': 5, 'sk-py': 4, 'sk-php': null,
          'sk-react': 1, 'sk-next': null, 'sk-laravel': null, 'sk-docker': 5,
          'sk-k8s': 5, 'sk-aws': 4, 'sk-pg': 3, 'sk-redis': 2,
          'sk-figma': null, 'sk-pm': 1,
        },
      },
      {
        memberId: 'm-4', memberName: 'Yamamoto Aoi',
        availablePercentage: 35,
        skillProficiencies: {
          'sk-ts': 3, 'sk-go': null, 'sk-py': 1, 'sk-php': 5,
          'sk-react': 2, 'sk-next': 2, 'sk-laravel': 5, 'sk-docker': 2,
          'sk-k8s': null, 'sk-aws': 1, 'sk-pg': 4, 'sk-redis': 3,
          'sk-figma': 3, 'sk-pm': 4,
        },
      },
      {
        memberId: 'm-5', memberName: 'Watanabe Mei',
        availablePercentage: 70,
        skillProficiencies: {
          'sk-ts': 4, 'sk-go': 2, 'sk-py': 3, 'sk-php': null,
          'sk-react': 5, 'sk-next': 5, 'sk-laravel': null, 'sk-docker': 3,
          'sk-k8s': 2, 'sk-aws': 2, 'sk-pg': 2, 'sk-redis': 1,
          'sk-figma': 5, 'sk-pm': 3,
        },
      },
      {
        memberId: 'm-6', memberName: 'Ito Kaito',
        availablePercentage: 10,
        skillProficiencies: {
          'sk-ts': 3, 'sk-go': 4, 'sk-py': 3, 'sk-php': 2,
          'sk-react': 3, 'sk-next': 2, 'sk-laravel': 3, 'sk-docker': 4,
          'sk-k8s': 4, 'sk-aws': 5, 'sk-pg': 3, 'sk-redis': 4,
          'sk-figma': null, 'sk-pm': 2,
        },
      },
      {
        memberId: 'm-7', memberName: 'Nakamura Sora',
        availablePercentage: 100,
        skillProficiencies: {
          'sk-ts': 1, 'sk-go': null, 'sk-py': 2, 'sk-php': null,
          'sk-react': 1, 'sk-next': null, 'sk-laravel': null, 'sk-docker': 1,
          'sk-k8s': null, 'sk-aws': null, 'sk-pg': 1, 'sk-redis': null,
          'sk-figma': 2, 'sk-pm': 1,
        },
      },
      {
        memberId: 'm-8', memberName: 'Kobayashi Riko',
        availablePercentage: 5,
        skillProficiencies: {
          'sk-ts': 5, 'sk-go': 4, 'sk-py': 4, 'sk-php': 3,
          'sk-react': 4, 'sk-next': 4, 'sk-laravel': 2, 'sk-docker': 5,
          'sk-k8s': 3, 'sk-aws': 4, 'sk-pg': 5, 'sk-redis': 5,
          'sk-figma': 2, 'sk-pm': 3,
        },
      },
    ],
  };

  return NextResponse.json(data);
}
