/**
 * Sanctum SPA Cookie 認証対応の fetch ラッパー。
 *
 * - `credentials: 'include'` で session cookie / XSRF-TOKEN を送受信
 * - X-XSRF-TOKEN ヘッダを自動付与（非 GET リクエスト時）
 * - 401 応答はログイン画面へリダイレクト
 * - 非 2xx は HttpError として throw
 */

export const API_BASE = process.env.NEXT_PUBLIC_API_BASE_URL ?? 'http://localhost:8080';

export class HttpError extends Error {
  readonly status: number;
  readonly body: unknown;

  constructor(status: number, message: string, body: unknown) {
    super(message);
    this.status = status;
    this.body = body;
  }
}

function getCookie(name: string): string | null {
  if (typeof document === 'undefined') return null;
  const match = document.cookie
    .split(';')
    .map((c) => c.trim())
    .find((c) => c.startsWith(`${name}=`));
  return match ? decodeURIComponent(match.slice(name.length + 1)) : null;
}

async function ensureCsrfCookie(): Promise<void> {
  if (getCookie('XSRF-TOKEN')) return;
  await fetch(`${API_BASE}/sanctum/csrf-cookie`, {
    credentials: 'include',
  });
}

type FetchInit = RequestInit & { skipCsrf?: boolean };

export async function apiFetch<T>(path: string, init: FetchInit = {}): Promise<T> {
  const method = (init.method ?? 'GET').toUpperCase();
  const needsCsrf = !init.skipCsrf && method !== 'GET' && method !== 'HEAD';

  if (needsCsrf) {
    await ensureCsrfCookie();
  }

  const headers = new Headers(init.headers);
  headers.set('Accept', 'application/json');
  if (init.body && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }
  const xsrf = getCookie('XSRF-TOKEN');
  if (needsCsrf && xsrf) {
    headers.set('X-XSRF-TOKEN', xsrf);
  }

  const response = await fetch(`${API_BASE}${path}`, {
    ...init,
    credentials: 'include',
    headers,
  });

  if (response.status === 401) {
    if (typeof window !== 'undefined' && !window.location.pathname.startsWith('/login')) {
      window.location.href = '/login';
    }
    throw new HttpError(401, 'Unauthenticated', null);
  }

  if (!response.ok) {
    let body: unknown = null;
    try {
      body = await response.json();
    } catch {
      // ignore
    }
    const message =
      typeof body === 'object' && body !== null && 'message' in body
        ? String((body as { message: unknown }).message)
        : `HTTP ${response.status}`;
    throw new HttpError(response.status, message, body);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  return (await response.json()) as T;
}
