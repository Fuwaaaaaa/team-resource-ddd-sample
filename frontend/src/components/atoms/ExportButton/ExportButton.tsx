'use client';

import { useState } from 'react';
import { API_BASE } from '@/lib/http';

export interface ExportButtonProps {
  path: string; // "/api/export/members" など
  filename: string;
  label?: string;
}

/**
 * CSV ダウンロードボタン。apiFetch は blob ダウンロードを扱わないので
 * ここは fetch を直接叩き、credentials:include でセッション cookie を送る。
 */
export function ExportButton({ path, filename, label = 'Export CSV' }: ExportButtonProps) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const onClick = async () => {
    setBusy(true);
    setError(null);
    try {
      const res = await fetch(`${API_BASE}${path}`, { credentials: 'include' });
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Export failed.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <span className="inline-flex items-center gap-2">
      <button
        type="button"
        onClick={onClick}
        disabled={busy}
        className="px-3 py-1.5 text-xs font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-100 border border-gray-300 rounded-md transition-colors disabled:opacity-50"
      >
        {busy ? 'Exporting…' : label}
      </button>
      {error && <span className="text-xs text-red-600">{error}</span>}
    </span>
  );
}
