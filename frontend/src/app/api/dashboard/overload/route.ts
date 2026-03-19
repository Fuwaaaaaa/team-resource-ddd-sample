import { NextResponse } from 'next/server';
import type { OverloadAnalysisDto } from '@/features/dashboard/types';

export async function GET() {
  const data: OverloadAnalysisDto = {
    referenceDate: '2026-03-19',
    overloadedCount: 2,
    members: [
      {
        memberId: 'm-1', memberName: 'Tanaka Yuto',
        standardHoursPerDay: 8.0,
        totalAllocatedPercentage: 80,
        allocatedHoursPerDay: 6.4,
        overloadHours: 0, isOverloaded: false,
      },
      {
        memberId: 'm-2', memberName: 'Suzuki Hana',
        standardHoursPerDay: 8.0,
        totalAllocatedPercentage: 120,
        allocatedHoursPerDay: 9.6,
        overloadHours: 1.6, isOverloaded: true,
      },
      {
        memberId: 'm-3', memberName: 'Sato Ren',
        standardHoursPerDay: 8.0,
        totalAllocatedPercentage: 50,
        allocatedHoursPerDay: 4.0,
        overloadHours: 0, isOverloaded: false,
      },
      {
        memberId: 'm-4', memberName: 'Yamamoto Aoi',
        standardHoursPerDay: 8.0,
        totalAllocatedPercentage: 65,
        allocatedHoursPerDay: 5.2,
        overloadHours: 0, isOverloaded: false,
      },
      {
        memberId: 'm-5', memberName: 'Watanabe Mei',
        standardHoursPerDay: 6.0,
        totalAllocatedPercentage: 30,
        allocatedHoursPerDay: 1.8,
        overloadHours: 0, isOverloaded: false,
      },
      {
        memberId: 'm-6', memberName: 'Ito Kaito',
        standardHoursPerDay: 8.0,
        totalAllocatedPercentage: 110,
        allocatedHoursPerDay: 8.8,
        overloadHours: 0.8, isOverloaded: true,
      },
      {
        memberId: 'm-7', memberName: 'Nakamura Sora',
        standardHoursPerDay: 8.0,
        totalAllocatedPercentage: 0,
        allocatedHoursPerDay: 0,
        overloadHours: 0, isOverloaded: false,
      },
      {
        memberId: 'm-8', memberName: 'Kobayashi Riko',
        standardHoursPerDay: 8.0,
        totalAllocatedPercentage: 95,
        allocatedHoursPerDay: 7.6,
        overloadHours: 0, isOverloaded: false,
      },
    ],
  };

  return NextResponse.json(data);
}
