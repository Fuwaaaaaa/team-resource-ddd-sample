'use client';

import { memo, useMemo } from 'react';
import {
  useTeamCapacity,
  useOverloadAnalysis,
  useSkillGapWarnings,
} from '@/features/dashboard/api';
import { useDashboardFilterStore } from '@/stores/useDashboardFilterStore';
import { HeatmapCell } from '@/components/atoms/HeatmapCell';
import type {
  HeatmapRowData,
  HeatmapCellData,
  SkillDto,
  SkillGapKey,
  MemberOverloadDto,
  SkillGapWarningDto,
  SkillCategory,
} from '@/features/dashboard/types';

// === Helpers ===

function availabilityBarColor(percentage: number, isOverloaded: boolean): string {
  if (isOverloaded) return 'bg-red-500';
  if (percentage <= 10) return 'bg-orange-400';
  if (percentage <= 30) return 'bg-yellow-400';
  return 'bg-green-500';
}

function availabilityTextColor(isOverloaded: boolean): string {
  return isOverloaded ? 'text-red-600 font-bold' : 'text-gray-700';
}

const CATEGORY_LABELS: Record<SkillCategory, string> = {
  programming_language: 'Language',
  framework: 'Framework',
  infrastructure: 'Infra',
  database: 'Database',
  design: 'Design',
  management: 'Mgmt',
  other: 'Other',
};

const CATEGORY_ORDER: SkillCategory[] = [
  'programming_language',
  'framework',
  'infrastructure',
  'database',
  'design',
  'management',
  'other',
];

const PROFICIENCY_LEGEND_CLASSES: Record<number, string> = {
  1: 'bg-red-200',
  2: 'bg-orange-200',
  3: 'bg-yellow-200',
  4: 'bg-lime-200',
  5: 'bg-green-300',
};

const EMPTY_CELL: HeatmapCellData = {
  proficiency: null,
  hasSkillGap: false,
  gapDeficit: null,
  requiredLevel: null,
};

// === Component ===

export interface ResourceHeatmapProps {
  className?: string;
}

function ResourceHeatmapComponent({ className = '' }: ResourceHeatmapProps) {
  // --- Filter state (individual selectors for minimal re-renders) ---
  const referenceDate = useDashboardFilterStore((s) => s.referenceDate);
  const selectedProjectId = useDashboardFilterStore((s) => s.selectedProjectId);
  const selectedCategories = useDashboardFilterStore((s) => s.selectedCategories);
  const showOverloadedOnly = useDashboardFilterStore((s) => s.showOverloadedOnly);
  const searchMemberName = useDashboardFilterStore((s) => s.searchMemberName);

  // --- Data fetching (3 parallel queries) ---
  const capacityQuery = useTeamCapacity(referenceDate);
  const overloadQuery = useOverloadAnalysis(referenceDate);
  const skillGapQuery = useSkillGapWarnings(referenceDate, selectedProjectId);

  // --- Derived data via useMemo ---

  const overloadMap = useMemo(() => {
    const map = new Map<string, MemberOverloadDto>();
    if (overloadQuery.data) {
      for (const member of overloadQuery.data.members) {
        map.set(member.memberId, member);
      }
    }
    return map;
  }, [overloadQuery.data]);

  const skillGapMap = useMemo(() => {
    const map = new Map<SkillGapKey, SkillGapWarningDto>();
    if (skillGapQuery.data) {
      for (const warning of skillGapQuery.data.warnings) {
        const key: SkillGapKey = `${warning.memberId}:${warning.skillId}`;
        const existing = map.get(key);
        if (!existing || warning.deficitLevel > existing.deficitLevel) {
          map.set(key, warning);
        }
      }
    }
    return map;
  }, [skillGapQuery.data]);

  const filteredSkills = useMemo((): SkillDto[] => {
    if (!capacityQuery.data) return [];
    const skills = capacityQuery.data.skills;
    if (selectedCategories.length === 0) return skills;
    return skills.filter((s) => selectedCategories.includes(s.category));
  }, [capacityQuery.data, selectedCategories]);

  const skillsByCategory = useMemo(() => {
    const groups = new Map<SkillCategory, SkillDto[]>();
    for (const skill of filteredSkills) {
      const list = groups.get(skill.category) ?? [];
      list.push(skill);
      groups.set(skill.category, list);
    }
    return groups;
  }, [filteredSkills]);

  const orderedCategories = useMemo(
    () => CATEGORY_ORDER.filter((c) => skillsByCategory.has(c)),
    [skillsByCategory]
  );

  const rows = useMemo((): HeatmapRowData[] => {
    if (!capacityQuery.data) return [];

    return capacityQuery.data.entries.map((entry) => {
      const overload = overloadMap.get(entry.memberId);

      const cells: Record<string, HeatmapCellData> = {};
      for (const skill of filteredSkills) {
        const proficiency = entry.skillProficiencies[skill.id] ?? null;
        const gapKey: SkillGapKey = `${entry.memberId}:${skill.id}`;
        const gap = skillGapMap.get(gapKey);

        cells[skill.id] = {
          proficiency,
          hasSkillGap: gap !== undefined,
          gapDeficit: gap?.deficitLevel ?? null,
          requiredLevel: gap?.requiredLevel ?? null,
        };
      }

      return {
        memberId: entry.memberId,
        memberName: entry.memberName,
        availablePercentage: entry.availablePercentage,
        isOverloaded: overload?.isOverloaded ?? false,
        overloadHours: overload?.overloadHours ?? 0,
        totalAllocatedPercentage: overload?.totalAllocatedPercentage ?? 0,
        cells,
      };
    });
  }, [capacityQuery.data, filteredSkills, overloadMap, skillGapMap]);

  const filteredRows = useMemo(() => {
    let result = rows;

    if (showOverloadedOnly) {
      result = result.filter((r) => r.isOverloaded);
    }

    if (searchMemberName.trim()) {
      const query = searchMemberName.trim().toLowerCase();
      result = result.filter((r) =>
        r.memberName.toLowerCase().includes(query)
      );
    }

    return result;
  }, [rows, showOverloadedOnly, searchMemberName]);

  // --- Loading / Error / Empty states ---

  const isLoading =
    capacityQuery.isLoading ||
    overloadQuery.isLoading ||
    skillGapQuery.isLoading;

  const error =
    capacityQuery.error ?? overloadQuery.error ?? skillGapQuery.error;

  if (isLoading) {
    return (
      <div className={`flex items-center justify-center p-8 ${className}`}>
        <div className="animate-pulse text-gray-500">
          Loading resource heatmap...
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className={`p-4 bg-red-50 border border-red-200 rounded-lg ${className}`}>
        <p className="text-red-700 text-sm">
          Failed to load heatmap data: {(error as Error).message}
        </p>
      </div>
    );
  }

  if (filteredRows.length === 0) {
    return (
      <div className={`p-8 text-center text-gray-500 ${className}`}>
        No members match the current filters.
      </div>
    );
  }

  // --- Render ---

  return (
    <div className={`overflow-x-auto rounded-lg border border-gray-200 shadow-sm ${className}`}>
      <table className="border-collapse">
        <thead>
          {/* Category group header row */}
          <tr className="bg-gray-50">
            <th
              rowSpan={2}
              className="sticky left-0 z-20 bg-gray-50 px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-r border-gray-200 min-w-[180px]"
            >
              Member
            </th>
            <th
              rowSpan={2}
              className="sticky left-[180px] z-20 bg-gray-50 px-3 py-2 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-r border-gray-200 min-w-[100px]"
            >
              Avail %
            </th>
            {orderedCategories.map((category) => {
              const skills = skillsByCategory.get(category) ?? [];
              return (
                <th
                  key={category}
                  colSpan={skills.length}
                  className="px-2 py-1.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-200"
                >
                  {CATEGORY_LABELS[category]}
                </th>
              );
            })}
          </tr>
          {/* Individual skill header row */}
          <tr className="bg-gray-50">
            {orderedCategories.flatMap((category) => {
              const skills = skillsByCategory.get(category) ?? [];
              return skills.map((skill) => (
                <th
                  key={skill.id}
                  className="px-1 py-1.5 text-center text-[11px] font-medium text-gray-600 border-b border-gray-200 max-w-[48px] truncate"
                  title={`${skill.name} (${CATEGORY_LABELS[skill.category]})`}
                >
                  {skill.name.length > 6
                    ? `${skill.name.slice(0, 5)}\u2026`
                    : skill.name}
                </th>
              ));
            })}
          </tr>
        </thead>

        <tbody>
          {filteredRows.map((row) => (
            <tr
              key={row.memberId}
              className={`border-b border-gray-100 hover:bg-gray-50/50 transition-colors ${
                row.isOverloaded ? 'bg-red-50/30' : ''
              }`}
            >
              {/* Sticky member name cell */}
              <td className="sticky left-0 z-10 bg-white px-4 py-2 border-r border-gray-200">
                <div className="flex items-center gap-2">
                  <span className="text-sm font-medium text-gray-900 truncate max-w-[120px]">
                    {row.memberName}
                  </span>
                  {row.isOverloaded && (
                    <span
                      className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-700"
                      title={`Overloaded by ${row.overloadHours.toFixed(1)}h/day`}
                    >
                      OVR
                    </span>
                  )}
                </div>
              </td>

              {/* Sticky availability cell */}
              <td className="sticky left-[180px] z-10 bg-white px-3 py-2 border-r border-gray-200">
                <div className="flex flex-col items-center gap-1">
                  <span
                    className={`text-xs tabular-nums ${availabilityTextColor(row.isOverloaded)}`}
                  >
                    {row.availablePercentage}%
                  </span>
                  <div className="w-full h-1.5 bg-gray-200 rounded-full overflow-hidden">
                    <div
                      className={`h-full rounded-full transition-all ${availabilityBarColor(
                        row.availablePercentage,
                        row.isOverloaded
                      )}`}
                      style={{
                        width: `${Math.min(100, Math.max(0, row.availablePercentage))}%`,
                      }}
                    />
                  </div>
                </div>
              </td>

              {/* Skill proficiency cells */}
              {orderedCategories.flatMap((category) => {
                const skills = skillsByCategory.get(category) ?? [];
                return skills.map((skill) => (
                  <HeatmapCell
                    key={`${row.memberId}-${skill.id}`}
                    data={row.cells[skill.id] ?? EMPTY_CELL}
                  />
                ));
              })}
            </tr>
          ))}
        </tbody>
      </table>

      {/* Legend */}
      <div className="flex flex-wrap items-center gap-4 px-4 py-3 bg-gray-50 border-t border-gray-200 text-xs text-gray-600">
        <span className="font-semibold">Proficiency:</span>
        {([1, 2, 3, 4, 5] as const).map((level) => (
          <span key={level} className="flex items-center gap-1">
            <span
              className={`inline-block w-4 h-4 rounded border border-gray-300 ${PROFICIENCY_LEGEND_CLASSES[level]}`}
            />
            {level}
          </span>
        ))}
        <span className="flex items-center gap-1">
          <span className="inline-block w-4 h-4 rounded border border-gray-300 bg-gray-100" />
          None
        </span>
        <span className="mx-1 text-gray-400">|</span>
        <span className="flex items-center gap-1">
          <span className="inline-block w-4 h-4 rounded ring-2 ring-inset ring-red-500 bg-red-50 border border-gray-300" />
          Skill Gap
        </span>
        <span className="flex items-center gap-1">
          <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-700">
            OVR
          </span>
          Overloaded
        </span>
      </div>
    </div>
  );
}

export const ResourceHeatmap = memo(ResourceHeatmapComponent);
ResourceHeatmap.displayName = 'ResourceHeatmap';
