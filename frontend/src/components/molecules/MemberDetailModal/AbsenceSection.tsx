'use client';

import { useState } from 'react';
import {
  useAbsencesByMember,
  useCancelAbsence,
  useRegisterAbsence,
} from '@/features/absences/api';
import { ABSENCE_TYPE_LABELS, type AbsenceType } from '@/features/absences/types';

interface Props {
  memberId: string;
}

const TYPE_OPTIONS: AbsenceType[] = ['vacation', 'sick', 'holiday', 'training', 'other'];

export function AbsenceSection({ memberId }: Props) {
  const absences = useAbsencesByMember(memberId);
  const register = useRegisterAbsence();
  const cancel = useCancelAbsence();

  const today = new Date().toISOString().slice(0, 10);
  const [startDate, setStartDate] = useState(today);
  const [endDate, setEndDate] = useState(today);
  const [type, setType] = useState<AbsenceType>('vacation');
  const [note, setNote] = useState('');

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (endDate < startDate) return;
    try {
      await register.mutateAsync({ memberId, startDate, endDate, type, note });
      setNote('');
    } catch {
      // エラーは React Query の isError/error で表示するだけ (ここでは握りつぶし)
    }
  };

  return (
    <section>
      <h3 className="text-xs font-semibold text-fg-muted uppercase tracking-wider mb-2">
        Absences ({absences.data?.filter((a) => !a.canceled).length ?? 0} active)
      </h3>

      {/* 登録フォーム */}
      <form
        onSubmit={onSubmit}
        className="p-3 bg-surface-muted rounded-md flex flex-wrap gap-2 items-end mb-3"
      >
        <label className="flex flex-col text-xs gap-0.5">
          <span className="text-fg-muted">Start</span>
          <input
            type="date"
            value={startDate}
            onChange={(e) => setStartDate(e.target.value)}
            className="px-2 py-1 text-sm border border-border rounded bg-surface"
            required
          />
        </label>
        <label className="flex flex-col text-xs gap-0.5">
          <span className="text-fg-muted">End</span>
          <input
            type="date"
            value={endDate}
            onChange={(e) => setEndDate(e.target.value)}
            min={startDate}
            className="px-2 py-1 text-sm border border-border rounded bg-surface"
            required
          />
        </label>
        <label className="flex flex-col text-xs gap-0.5">
          <span className="text-fg-muted">Type</span>
          <select
            value={type}
            onChange={(e) => setType(e.target.value as AbsenceType)}
            className="px-2 py-1 text-sm border border-border rounded bg-surface"
          >
            {TYPE_OPTIONS.map((t) => (
              <option key={t} value={t}>
                {ABSENCE_TYPE_LABELS[t]}
              </option>
            ))}
          </select>
        </label>
        <label className="flex flex-col text-xs gap-0.5 flex-1 min-w-[160px]">
          <span className="text-fg-muted">Note</span>
          <input
            type="text"
            value={note}
            onChange={(e) => setNote(e.target.value)}
            maxLength={500}
            className="px-2 py-1 text-sm border border-border rounded bg-surface"
            placeholder="任意"
          />
        </label>
        <button
          type="submit"
          disabled={register.isPending || endDate < startDate}
          className="px-3 py-1.5 text-sm bg-primary text-primary-fg rounded disabled:opacity-50"
        >
          {register.isPending ? '登録中…' : '登録'}
        </button>
      </form>

      {register.isError && (
        <p className="text-xs text-danger mb-2">
          登録に失敗しました: {(register.error as Error).message}
        </p>
      )}

      {/* リスト */}
      {absences.isLoading && <p className="text-xs text-fg-muted">Loading…</p>}
      {absences.data && absences.data.length === 0 && (
        <p className="text-xs text-fg-muted">不在の登録はありません。</p>
      )}
      <ul className="space-y-2">
        {absences.data?.map((a) => (
          <li
            key={a.id}
            className={`flex items-center justify-between gap-3 p-3 border border-border rounded-md ${
              a.canceled ? 'bg-surface-muted opacity-60' : 'bg-surface'
            }`}
          >
            <div className="flex-1 min-w-0">
              <div className="font-medium text-sm text-fg">
                {ABSENCE_TYPE_LABELS[a.type]}
                <span className="ml-2 text-xs text-fg-muted">
                  {a.startDate} → {a.endDate} ({a.daysInclusive}日)
                </span>
              </div>
              {a.note && <div className="text-xs text-fg-muted mt-0.5">{a.note}</div>}
            </div>
            {!a.canceled && (
              <button
                onClick={() => cancel.mutate(a.id)}
                disabled={cancel.isPending}
                className="px-2 py-1 text-xs text-danger hover:bg-danger-bg rounded shrink-0 disabled:opacity-50"
              >
                Cancel
              </button>
            )}
            {a.canceled && (
              <span className="text-[10px] font-semibold uppercase tracking-wider text-fg-muted">
                canceled
              </span>
            )}
          </li>
        ))}
      </ul>
    </section>
  );
}
