import type { Metadata } from 'next';
import { Providers } from './providers';
import { ThemeBootstrap } from '@/lib/theme/ThemeBootstrap';
import './globals.css';

export const metadata: Metadata = {
  title: 'Team Resource Dashboard',
  description: 'Team resource allocation and skill map visualization',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="ja">
      <body className="bg-bg text-fg min-h-screen">
        <ThemeBootstrap />
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}
