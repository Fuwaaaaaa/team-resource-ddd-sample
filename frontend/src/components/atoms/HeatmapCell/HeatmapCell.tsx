import { memo } from 'react';
import type { HeatmapCellData } from '@/features/dashboard/types';

export interface HeatmapCellProps {
  data: HeatmapCellData;
  skillName?: string;
  memberName?: string;
}

/**
 * Heatmap の 1 セル。色だけでなく数値 (1-5) も常に表示するため色覚多様性に配慮。
 * aria-label は「memberName: skillName proficiency 3/5」の形で screen reader に読ませる。
 */
const PROFICIENCY_CLASSES: Record<number, string> = {
  1: 'bg-heatmap-1 text-red-900',
  2: 'bg-heatmap-2 text-orange-900',
  3: 'bg-heatmap-3 text-yellow-900',
  4: 'bg-heatmap-4 text-lime-900',
  5: 'bg-heatmap-5 text-green-900',
};

const NULL_CELL_CLASS = 'bg-heatmap-null text-fg-muted';

function HeatmapCellComponent({ data, skillName, memberName }: HeatmapCellProps) {
  const { proficiency, hasSkillGap, gapDeficit, requiredLevel } = data;

  const baseClass =
    proficiency !== null
      ? PROFICIENCY_CLASSES[proficiency] ?? NULL_CELL_CLASS
      : NULL_CELL_CLASS;

  const gapClass = hasSkillGap ? 'ring-2 ring-inset ring-skillgap-ring bg-skillgap-bg' : '';

  const displayValue = proficiency !== null ? proficiency : '-';

  const ariaLabel = (() => {
    const who = memberName ?? 'Member';
    const what = skillName ?? 'skill';
    if (hasSkillGap) {
      return `${who}: ${what} proficiency ${proficiency ?? 0}/5, below required ${requiredLevel} (deficit ${gapDeficit})`;
    }
    if (proficiency !== null) {
      return `${who}: ${what} proficiency ${proficiency}/5`;
    }
    return `${who}: ${what} not held`;
  })();

  return (
    <td
      className={`relative w-12 h-10 text-center text-sm font-medium border border-border transition-colors ${baseClass} ${gapClass}`}
      aria-label={ariaLabel}
      title={ariaLabel}
    >
      <span className="relative z-10" aria-hidden="true">{displayValue}</span>
      {hasSkillGap && (
        <span
          className="absolute bottom-0.5 right-0.5 text-[10px] text-danger font-bold"
          aria-hidden="true"
        >
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
    p.requiredLevel === n.requiredLevel &&
    prev.skillName === next.skillName &&
    prev.memberName === next.memberName
  );
});

HeatmapCell.displayName = 'HeatmapCell';
