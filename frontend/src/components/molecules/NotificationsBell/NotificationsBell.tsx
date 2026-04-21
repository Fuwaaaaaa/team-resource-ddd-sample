'use client';

import { useEffect, useRef, useState } from 'react';
import {
  useMarkAllNotificationsRead,
  useMarkNotificationRead,
  useNotifications,
} from '@/features/notifications/api';

export function NotificationsBell() {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const { data } = useNotifications(false);
  const markRead = useMarkNotificationRead();
  const markAll = useMarkAllNotificationsRead();

  const items = data?.data ?? [];
  const unread = data?.meta.unreadCount ?? 0;

  // 外クリックで閉じる
  useEffect(() => {
    if (!open) return;
    const onDown = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', onDown);
    return () => document.removeEventListener('mousedown', onDown);
  }, [open]);

  return (
    <div ref={ref} className="relative">
      <button
        onClick={() => setOpen((v) => !v)}
        aria-label="Notifications"
        className="relative p-1.5 rounded-md hover:bg-gray-100 text-gray-600 hover:text-gray-900"
      >
        <svg
          width="18"
          height="18"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
        >
          <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
          <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
        </svg>
        {unread > 0 && (
          <span className="absolute -top-1 -right-1 flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold text-white bg-red-500 rounded-full">
            {unread > 99 ? '99+' : unread}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute right-0 mt-2 w-80 max-h-[70vh] overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg z-50">
          <div className="sticky top-0 bg-white border-b border-gray-100 px-3 py-2 flex items-center justify-between">
            <span className="text-sm font-semibold text-gray-800">Notifications</span>
            {unread > 0 && (
              <button
                onClick={() => markAll.mutate()}
                disabled={markAll.isPending}
                className="text-xs text-blue-600 hover:underline disabled:opacity-50"
              >
                Mark all read
              </button>
            )}
          </div>
          {items.length === 0 && (
            <div className="px-4 py-8 text-center text-xs text-gray-500">
              通知はありません。
            </div>
          )}
          <ul>
            {items.map((n) => (
              <li
                key={n.id}
                onClick={() => {
                  if (!n.read_at) markRead.mutate(n.id);
                }}
                className={`px-3 py-2 border-b border-gray-100 cursor-pointer hover:bg-gray-50 ${
                  n.read_at ? 'opacity-60' : 'bg-blue-50/30'
                }`}
              >
                <div className="flex items-start justify-between gap-2">
                  <span className="text-xs font-semibold text-gray-900">{n.title}</span>
                  {!n.read_at && (
                    <span className="mt-1 w-2 h-2 rounded-full bg-blue-500 shrink-0" aria-label="Unread" />
                  )}
                </div>
                <div className="text-xs text-gray-600 mt-0.5">{n.body}</div>
                <div className="text-[10px] text-gray-400 mt-1">
                  {new Date(n.created_at).toLocaleString('ja-JP')}
                </div>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}
