'use client';

import { use, useState } from 'react';
import { useRouter } from 'next/navigation';
import { HttpError } from '@/lib/http';
import { useAcceptInvite, useInvitePreview } from '@/features/invite/api';

interface PageProps {
  params: Promise<{ token: string }>;
}

/**
 * 招待リンク (`/invite/{token}`) からアクセスする password 設定フォーム。
 *
 * Token は admin が user 作成時に発行された 64-char hex で、 24 時間有効・single-use。
 * フォーム送信で `POST /api/invite/{token}/accept` を呼び、 password を確定する。
 *
 * 公開ページ (Next.js middleware で /invite/* は authn 不要)。
 */
export default function InviteAcceptPage({ params }: PageProps) {
  const { token } = use(params);
  const router = useRouter();
  const preview = useInvitePreview(token);
  const accept = useAcceptInvite(token);

  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [error, setError] = useState<string | null>(null);

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    if (password !== confirm) {
      setError('パスワードと確認用パスワードが一致しません。');
      return;
    }
    if (password.length < 12) {
      setError('パスワードは 12 文字以上にしてください。');
      return;
    }
    try {
      await accept.mutateAsync({ password, password_confirmation: confirm });
      // 成功 → /login にリダイレクト (success param で UI に "登録完了" を出してもよい)
      router.push('/login?invited=1');
    } catch (err) {
      if (err instanceof HttpError && err.status === 404) {
        setError('招待リンクが無効または失効しています。 admin に再発行を依頼してください。');
      } else if (err instanceof HttpError && err.status === 422) {
        const body = err.body as { errors?: Record<string, string[]> };
        const firstErr = body.errors?.password?.[0];
        setError(firstErr ?? 'パスワードが要件を満たしていません。');
      } else {
        setError('予期しないエラーが発生しました。 しばらく待って再度お試しください。');
      }
    }
  };

  // 不正フォーマット token はそもそも fetch しない (api.ts の enabled で弾く)
  if (!/^[0-9a-f]{64}$/.test(token)) {
    return (
      <main className="min-h-screen grid place-items-center bg-bg px-4">
        <div className="max-w-sm bg-surface text-fg rounded-lg border border-border p-6 space-y-3 text-center">
          <h1 className="text-lg font-semibold">招待リンクが無効です</h1>
          <p className="text-sm text-fg-muted">
            URL が壊れている可能性があります。 メールに記載されたリンクをそのままコピーしてアクセスしてください。
          </p>
        </div>
      </main>
    );
  }

  if (preview.isLoading) {
    return (
      <main className="min-h-screen grid place-items-center bg-bg px-4">
        <p className="text-sm text-fg-muted">招待を確認中…</p>
      </main>
    );
  }

  if (preview.isError) {
    const isExpired = preview.error instanceof HttpError && preview.error.status === 404;
    return (
      <main className="min-h-screen grid place-items-center bg-bg px-4">
        <div className="max-w-sm bg-surface text-fg rounded-lg border border-border p-6 space-y-3 text-center">
          <h1 className="text-lg font-semibold">
            {isExpired ? '招待リンクが失効しています' : '招待を読み込めませんでした'}
          </h1>
          <p className="text-sm text-fg-muted">
            {isExpired
              ? '24 時間有効、 既に accept された、 または無効な token です。 admin に再発行を依頼してください。'
              : 'しばらく待って再度お試しください。'}
          </p>
        </div>
      </main>
    );
  }

  const user = preview.data!;

  return (
    <main className="min-h-screen grid place-items-center bg-bg px-4">
      <form
        onSubmit={onSubmit}
        className="w-full max-w-sm bg-surface text-fg rounded-lg border border-border shadow-sm p-6 space-y-4"
      >
        <div>
          <h1 className="text-xl font-semibold">パスワードを設定</h1>
          <p className="text-sm text-fg-muted mt-1">
            ようこそ、 <strong>{user.name}</strong> さん ({user.role})
          </p>
          <p className="text-xs text-fg-muted mt-1">{user.email}</p>
        </div>

        {error && (
          <div className="rounded-md border border-danger/40 bg-danger-bg text-danger text-sm px-3 py-2">
            {error}
          </div>
        )}

        <div className="space-y-1">
          <label htmlFor="password" className="text-xs font-medium text-fg">
            新しいパスワード (12 文字以上)
          </label>
          <input
            id="password"
            type="password"
            autoComplete="new-password"
            required
            minLength={12}
            maxLength={72}
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            className="w-full px-3 py-2 text-sm border border-border rounded-md bg-surface text-fg focus:outline-none focus:ring-2 focus:ring-primary"
          />
        </div>

        <div className="space-y-1">
          <label htmlFor="confirm" className="text-xs font-medium text-fg">
            パスワード (確認)
          </label>
          <input
            id="confirm"
            type="password"
            autoComplete="new-password"
            required
            minLength={12}
            maxLength={72}
            value={confirm}
            onChange={(e) => setConfirm(e.target.value)}
            className="w-full px-3 py-2 text-sm border border-border rounded-md bg-surface text-fg focus:outline-none focus:ring-2 focus:ring-primary"
          />
        </div>

        <button
          type="submit"
          disabled={accept.isPending}
          className="w-full py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-primary-hover disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {accept.isPending ? '設定中…' : 'パスワードを設定してログイン画面へ'}
        </button>
      </form>
    </main>
  );
}
