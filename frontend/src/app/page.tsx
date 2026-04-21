import { AppHeader } from '@/components/layout/AppHeader';
import { DashboardContent } from './dashboard-page';

export default function Home() {
  return (
    <>
      <AppHeader />
      <div className="max-w-[1400px] mx-auto px-4 py-8">
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-gray-900">Resource Heatmap</h1>
          <p className="text-sm text-gray-500 mt-1">
            Team skill proficiency and resource allocation overview
          </p>
        </div>
        <DashboardContent />
      </div>
    </>
  );
}
