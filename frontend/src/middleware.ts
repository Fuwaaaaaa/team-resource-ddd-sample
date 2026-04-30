import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

/**
 * 一次ガード: XSRF-TOKEN cookie が無ければ /login へ飛ばす。
 * 厳密な認証判定は各画面で /api/me を叩いて行う（401 → /login）。
 */
export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;

  if (
    pathname.startsWith('/login') ||
    pathname.startsWith('/invite/') || // 招待リンクは未認証でアクセスする
    pathname.startsWith('/_next') ||
    pathname.startsWith('/api') ||
    pathname === '/favicon.ico'
  ) {
    return NextResponse.next();
  }

  const xsrf = request.cookies.get('XSRF-TOKEN')?.value;
  if (!xsrf) {
    const url = request.nextUrl.clone();
    url.pathname = '/login';
    return NextResponse.redirect(url);
  }

  return NextResponse.next();
}

export const config = {
  matcher: ['/((?!_next/static|_next/image|favicon.ico).*)'],
};
