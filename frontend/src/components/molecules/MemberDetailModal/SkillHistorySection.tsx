'use client';

import { useState } from 'react';
import { useMemberSkillHistory } from '@/features/members/skillHistory';
import { useSkills } from '@/features/skills/api';

interface Props {
  memberId: string;
}

/**
 * メンバーのスキル習熟度履歴をシンプルなスパークラインっぽいテーブル + SVG 折れ線で表示。
 * 外部チャートライブラリは避け、素の SVG で描画（1on1 / 評価面談で使う程度の簡易表示）。
 */
export function SkillHistorySection({ memberId }: Props) {
  const skills = useSkills();
  const [selectedSkill, setSelectedSkill] = useState<string>('');
  const history = useMemberSkillHistory(memberId, selectedSkill || undefined);

  const skillName = (id: string) =>
    (skills.data ?? []).find((s) => s.id === id)?.name ?? id;

  const entries = history.data ?? [];

  return (
    <section>
      <h3 className="text-xs font-semibold text-fg-muted uppercase tracking-wider mb-2">
        Skill Growth History ({entries.length})
      </h3>

      <div className="mb-3">
        <label className="text-xs text-fg-muted mr-2">Filter by skill:</label>
        <select
          value={selectedSkill}
          onChange={(e) => setSelectedSkill(e.target.value)}
          className="px-2 py-1 text-sm border border-border rounded bg-surface"
        >
          <option value="">All skills</option>
          {(skills.data ?? []).map((s) => (
            <option key={s.id} value={s.id}>
              {s.name}
            </option>
          ))}
        </select>
      </div>

      {history.isLoading && <p className="text-xs text-fg-muted">Loading…</p>}

      {entries.length === 0 && !history.isLoading && (
        <p className="text-xs text-fg-muted">
          履歴なし。スキル熟練度の変更が記録されるとここに表示されます。
        </p>
      )}

      {entries.length > 0 && selectedSkill && (
        <GrowthChart entries={entries} />
      )}

      {entries.length > 0 && (
        <ul className="mt-3 space-y-1 text-xs">
          {entries.map((e, i) => (
            <li key={i} className="flex items-center justify-between py-1 border-b border-border/60">
              <span>
                <span className="font-medium">{skillName(e.skillId)}</span>
                <span className="mx-2 text-fg-muted">→</span>
                <span className="font-semibold text-primary">Level {e.proficiency}</span>
              </span>
              <span className="text-fg-muted">
                {new Date(e.changedAt).toLocaleString('ja-JP')}
                {e.changedByName && ` · ${e.changedByName}`}
              </span>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

function GrowthChart({ entries }: { entries: Array<{ changedAt: string; proficiency: number }> }) {
  if (entries.length < 2) return null;

  const width = 360;
  const height = 100;
  const padX = 20;
  const padY = 10;

  const minTime = new Date(entries[0].changedAt).getTime();
  const maxTime = new Date(entries[entries.length - 1].changedAt).getTime();
  const timeSpan = Math.max(1, maxTime - minTime);

  const points = entries.map((e) => {
    const t = new Date(e.changedAt).getTime();
    const x = padX + ((t - minTime) / timeSpan) * (width - padX * 2);
    // proficiency 1..5 → y 軸反転
    const y = padY + ((5 - e.proficiency) / 4) * (height - padY * 2);
    return { x, y, level: e.proficiency };
  });

  const path = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x.toFixed(1)} ${p.y.toFixed(1)}`).join(' ');

  return (
    <svg viewBox={`0 0 ${width} ${height}`} className="w-full h-24 bg-surface-muted rounded">
      {/* grid lines for levels 1-5 */}
      {[1, 2, 3, 4, 5].map((lvl) => {
        const y = padY + ((5 - lvl) / 4) * (height - padY * 2);
        return (
          <g key={lvl}>
            <line x1={padX} x2={width - padX} y1={y} y2={y} stroke="currentColor" opacity={0.1} />
            <text x={4} y={y + 3} fontSize="8" fill="currentColor" opacity={0.5}>
              {lvl}
            </text>
          </g>
        );
      })}
      <path d={path} fill="none" stroke="rgb(37 99 235)" strokeWidth="2" />
      {points.map((p, i) => (
        <circle key={i} cx={p.x} cy={p.y} r={3} fill="rgb(37 99 235)" />
      ))}
    </svg>
  );
}
