import { memo } from 'react';
import type { HeatmapCellData } from '@/features/dashboard/types';

export interface HeatmapCellProps {
  data: HeatmapCellData;
}

const PROFICIENCY_CLASSES: Record<number, string> = {
  1: 'bg-red-200 text-red-900',
  2: 'bg-orange-200 text-orange-900',
  3: 'bg-yellow-200 text-yellow-900',
  4: 'bg-lime-200 text-lime-900',
  5: 'bg-green-300 text-green-900',
};

const NULL_CELL_CLASS = 'bg-gray-100 text-gray-400';

function HeatmapCellComponent({ data }: HeatmapCellProps) {
  const { proficiency, hasSkillGap, gapDeficit } = data;

  const baseClass =
    proficiency !== null
      ? PROFICIENCY_CLASSES[proficiency] ?? NULL_CELL_CLASS
      : NULL_CELL_CLASS;

  const gapClass = hasSkillGap
    ? 'ring-2 ring-inset ring-red-500 bg-red-50'
    : '';

  const displayValue = proficiency !== null ? proficiency : '-';

  return (
    <td
      className={`relative w-12 h-10 text-center text-sm font-medium border border-gray-200 transition-colors ${baseClass} ${gapClass}`}
      title={
        hasSkillGap
          ? `Skill gap: deficit ${gapDeficit} (required ${data.requiredLevel}, actual ${proficiency ?? 0})`
          : proficiency !== null
            ? `Proficiency: ${proficiency}/5`
            : 'No skill'
      }
    >
      <span className="relative z-10">{displayValue}</span>
      {hasSkillGap && (
        <span className="absolute bottom-0.5 right-0.5 text-[10px] text-red-600 font-bold">
          !
        </span>
      )}
    </td>
  );
}

export const HeatmapCell = memo(HeatmapCellComponent, (prev, next) => {
  const p = prev.data;
  const n = next.data;
  return (
    p.proficiency === n.proficiency &&
    p.hasSkillGap === n.hasSkillGap &&
    p.gapDeficit === n.gapDeficit &&
    p.requiredLevel === n.requiredLevel
  );
});

HeatmapCell.displayName = 'HeatmapCell';
