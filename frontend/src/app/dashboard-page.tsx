'use client';

import { useQueryClient } from '@tanstack/react-query';
import { ResourceHeatmap } from '@/components/molecules/ResourceHeatmap';
import { useDashboardFilterStore } from '@/stores/useDashboardFilterStore';
import { dashboardKeys } from '@/features/dashboard/api';
import type { SkillCategory } from '@/features/dashboard/types';

const ALL_CATEGORIES: { value: SkillCategory; label: string }[] = [
  { value: 'programming_language', label: 'Language' },
  { value: 'framework', label: 'Framework' },
  { value: 'infrastructure', label: 'Infra' },
  { value: 'database', label: 'Database' },
  { value: 'design', label: 'Design' },
  { value: 'management', label: 'Mgmt' },
];

export function DashboardContent() {
  const queryClient = useQueryClient();
  const referenceDate = useDashboardFilterStore((s) => s.referenceDate);
  const setReferenceDate = useDashboardFilterStore((s) => s.setReferenceDate);
  const selectedCategories = useDashboardFilterStore((s) => s.selectedCategories);
  const toggleCategory = useDashboardFilterStore((s) => s.toggleCategory);
  const showOverloadedOnly = useDashboardFilterStore((s) => s.showOverloadedOnly);
  const setShowOverloadedOnly = useDashboardFilterStore((s) => s.setShowOverloadedOnly);
  const searchMemberName = useDashboardFilterStore((s) => s.searchMemberName);
  const setSearchMemberName = useDashboardFilterStore((s) => s.setSearchMemberName);
  const resetFilters = useDashboardFilterStore((s) => s.resetFilters);

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: dashboardKeys.all });
  };

  return (
    <>
      {/* Filters */}
      <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-4 mb-6">
        <div className="flex flex-wrap items-center gap-4">
          {/* Date picker */}
          <div className="flex items-center gap-2">
            <label htmlFor="ref-date" className="text-xs font-medium text-gray-600">
              Date
            </label>
            <input
              id="ref-date"
              type="date"
              value={referenceDate}
              onChange={(e) => setReferenceDate(e.target.value)}
              className="px-2 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          {/* Member search */}
          <div className="flex items-center gap-2">
            <label htmlFor="search" className="text-xs font-medium text-gray-600">
              Search
            </label>
            <input
              id="search"
              type="text"
              placeholder="Member name..."
              value={searchMemberName}
              onChange={(e) => setSearchMemberName(e.target.value)}
              className="px-2 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 w-40"
            />
          </div>

          {/* Overloaded only toggle */}
          <label className="flex items-center gap-1.5 cursor-pointer">
            <input
              type="checkbox"
              checked={showOverloadedOnly}
              onChange={(e) => setShowOverloadedOnly(e.target.checked)}
              className="rounded border-gray-300 text-red-600 focus:ring-red-500"
            />
            <span className="text-xs font-medium text-gray-600">
              Overloaded only
            </span>
          </label>

          {/* Divider */}
          <div className="h-6 w-px bg-gray-300" />

          {/* Category filters */}
          <div className="flex items-center gap-1.5 flex-wrap">
            <span className="text-xs font-medium text-gray-600 mr-1">Skills:</span>
            {ALL_CATEGORIES.map((cat) => {
              const isActive = selectedCategories.includes(cat.value);
              return (
                <button
                  key={cat.value}
                  onClick={() => toggleCategory(cat.value)}
                  className={`px-2.5 py-1 text-xs rounded-full border transition-colors ${
                    isActive
                      ? 'bg-blue-100 border-blue-400 text-blue-700 font-medium'
                      : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-50'
                  }`}
                >
                  {cat.label}
                </button>
              );
            })}
          </div>

          {/* Refresh */}
          <button
            onClick={handleRefresh}
            className="px-3 py-1.5 text-xs font-medium text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-md border border-blue-200 transition-colors"
          >
            Refresh
          </button>

          {/* Reset */}
          <button
            onClick={resetFilters}
            className="ml-auto px-3 py-1.5 text-xs text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md transition-colors"
          >
            Reset
          </button>
        </div>
      </div>

      {/* Heatmap */}
      <ResourceHeatmap />
    </>
  );
}
