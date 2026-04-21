'use client';

import { useRef, useState } from 'react';
import { API_BASE, HttpError } from '@/lib/http';

export interface ImportReport {
  imported: number;
  failureCount: number;
  failures: Array<{ line: number; error: string; raw?: Record<string, string> }>;
}

interface Props {
  endpoint: string;       // e.g. '/api/import/members'
  label?: string;
  onDone?: (r: ImportReport) => void;
}

/**
 * CSV アップロードボタン。選択 → POST multipart/form-data → 結果サマリを表示。
 * 失敗行は折りたたみで詳細確認できる。
 */
export function ImportButton({ endpoint, label = 'Import CSV', onDone }: Props) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [report, setReport] = useState<ImportReport | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function getCsrf(): Promise<string | null> {
    if (typeof document === 'undefined') return null;
    const cookie = document.cookie
      .split(';')
      .map((c) => c.trim())
      .find((c) => c.startsWith('XSRF-TOKEN='));
    if (cookie) return decodeURIComponent(cookie.slice('XSRF-TOKEN='.length));
    await fetch(`${API_BASE}/sanctum/csrf-cookie`, { credentials: 'include' });
    const again = document.cookie
      .split(';')
      .map((c) => c.trim())
      .find((c) => c.startsWith('XSRF-TOKEN='));
    return again ? decodeURIComponent(again.slice('XSRF-TOKEN='.length)) : null;
  }

  const onFile = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    e.target.value = ''; // 同じファイルを再選択できるようにリセット
    if (!file) return;

    setBusy(true);
    setError(null);
    setReport(null);
    try {
      const form = new FormData();
      form.append('file', file);
      const xsrf = await getCsrf();
      const res = await fetch(`${API_BASE}${endpoint}`, {
        method: 'POST',
        credentials: 'include',
        headers: xsrf ? { 'X-XSRF-TOKEN': xsrf, Accept: 'application/json' } : { Accept: 'application/json' },
        body: form,
      });
      if (!res.ok) {
        let body: unknown = null;
        try {
          body = await res.json();
        } catch {}
        throw new HttpError(
          res.status,
          typeof body === 'object' && body !== null && 'message' in body
            ? String((body as { message: unknown }).message)
            : `HTTP ${res.status}`,
          body,
        );
      }
      const json = (await res.json()) as { data: ImportReport };
      setReport(json.data);
      onDone?.(json.data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Upload failed.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="inline-flex flex-col items-start gap-2">
      <div>
        <input
          ref={inputRef}
          type="file"
          accept=".csv,text/csv"
          onChange={onFile}
          className="hidden"
        />
        <button
          type="button"
          onClick={() => inputRef.current?.click()}
          disabled={busy}
          className="px-3 py-1.5 text-xs font-medium text-green-700 bg-green-50 border border-green-200 rounded-md hover:bg-green-100 disabled:opacity-50"
        >
          {busy ? 'Uploading…' : label}
        </button>
      </div>
      {error && <p className="text-xs text-red-600">{error}</p>}
      {report && (
        <div className="text-xs text-gray-700 bg-green-50 border border-green-200 rounded px-3 py-2">
          <div>
            ✓ Imported <b>{report.imported}</b>
            {report.failureCount > 0 && (
              <>
                {' '}
                · ✗ Failed <b className="text-red-600">{report.failureCount}</b>
              </>
            )}
          </div>
          {report.failureCount > 0 && (
            <details className="mt-1">
              <summary className="cursor-pointer text-red-600 hover:underline">
                View failures
              </summary>
              <ul className="mt-1 space-y-0.5 max-h-40 overflow-y-auto">
                {report.failures.map((f, i) => (
                  <li key={i}>
                    line {f.line}: {f.error}
                  </li>
                ))}
              </ul>
            </details>
          )}
        </div>
      )}
    </div>
  );
}
