'use client';

import { useState } from 'react';
import {
  useCreateNote,
  useDeleteNote,
  useNotes,
  type NoteEntityType,
} from '@/features/notes/api';
import { useMe } from '@/features/auth/api';

interface Props {
  entityType: NoteEntityType;
  entityId: string;
}

/**
 * 任意の entity (member / project / allocation) に紐づく運用メモセクション。
 * 追加 / 削除のみ、編集は将来拡張。
 */
export function NotesSection({ entityType, entityId }: Props) {
  const notes = useNotes(entityType, entityId);
  const create = useCreateNote();
  const del = useDeleteNote();
  const { data: me } = useMe();

  const [body, setBody] = useState('');

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!body.trim()) return;
    await create.mutateAsync({ entityType, entityId, body: body.trim() });
    setBody('');
  };

  const canDelete = (authorId: number | null) =>
    !!me && (me.role === 'admin' || authorId === me.id);

  return (
    <section>
      <h3 className="text-xs font-semibold text-fg-muted uppercase tracking-wider mb-2">
        Notes ({notes.data?.length ?? 0})
      </h3>

      <form onSubmit={onSubmit} className="flex gap-2 mb-3">
        <input
          type="text"
          value={body}
          onChange={(e) => setBody(e.target.value)}
          maxLength={2000}
          placeholder="運用メモ (育成目的 / 配置理由 など)"
          className="flex-1 px-3 py-1.5 text-sm border border-border rounded bg-surface"
        />
        <button
          type="submit"
          disabled={create.isPending || !body.trim()}
          className="px-3 py-1.5 text-sm bg-primary text-primary-fg rounded disabled:opacity-50"
        >
          {create.isPending ? '...' : '追加'}
        </button>
      </form>

      {notes.isLoading && <p className="text-xs text-fg-muted">Loading…</p>}
      {notes.data && notes.data.length === 0 && (
        <p className="text-xs text-fg-muted">メモはまだありません。</p>
      )}
      <ul className="space-y-2">
        {notes.data?.map((n) => (
          <li
            key={n.id}
            className="p-3 border border-border rounded-md bg-surface"
          >
            <div className="flex items-start justify-between gap-2">
              <p className="text-sm text-fg whitespace-pre-wrap flex-1">{n.body}</p>
              {canDelete(n.author_id) && (
                <button
                  onClick={() =>
                    del.mutate({ id: n.id, entityType, entityId })
                  }
                  disabled={del.isPending}
                  className="text-xs text-danger hover:underline shrink-0"
                >
                  削除
                </button>
              )}
            </div>
            <div className="text-[11px] text-fg-muted mt-1">
              {n.author?.name ?? '(unknown)'} ·{' '}
              {new Date(n.created_at).toLocaleString('ja-JP')}
            </div>
          </li>
        ))}
      </ul>
    </section>
  );
}
