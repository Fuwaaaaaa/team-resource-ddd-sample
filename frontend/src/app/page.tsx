import { DashboardContent } from './dashboard-page';

export default function Home() {
  return (
    <div className="max-w-[1400px] mx-auto px-4 py-8">
      {/* Static header — rendered as Server Component (zero JS) */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">
          Resource Heatmap
        </h1>
        <p className="text-sm text-gray-500 mt-1">
          Team skill proficiency and resource allocation overview
        </p>
      </div>

      {/* Interactive dashboard — Client Component */}
      <DashboardContent />
    </div>
  );
}
