import { create } from 'zustand';
import type { SkillCategory } from '@/features/dashboard/types';

interface DashboardFilterState {
  referenceDate: string;
  selectedProjectId: string | undefined;
  selectedCategories: SkillCategory[];
  showOverloadedOnly: boolean;
  searchMemberName: string;

  setReferenceDate: (date: string) => void;
  setSelectedProjectId: (projectId: string | undefined) => void;
  toggleCategory: (category: SkillCategory) => void;
  setSelectedCategories: (categories: SkillCategory[]) => void;
  setShowOverloadedOnly: (show: boolean) => void;
  setSearchMemberName: (name: string) => void;
  resetFilters: () => void;
}

const today = new Date().toISOString().split('T')[0] as string;

const initialState = {
  referenceDate: today,
  selectedProjectId: undefined as string | undefined,
  selectedCategories: [] as SkillCategory[],
  showOverloadedOnly: false,
  searchMemberName: '',
};

export const useDashboardFilterStore = create<DashboardFilterState>((set) => ({
  ...initialState,

  setReferenceDate: (date) => set({ referenceDate: date }),

  setSelectedProjectId: (projectId) => set({ selectedProjectId: projectId }),

  toggleCategory: (category) =>
    set((state) => {
      const exists = state.selectedCategories.includes(category);
      return {
        selectedCategories: exists
          ? state.selectedCategories.filter((c) => c !== category)
          : [...state.selectedCategories, category],
      };
    }),

  setSelectedCategories: (categories) =>
    set({ selectedCategories: categories }),

  setShowOverloadedOnly: (show) => set({ showOverloadedOnly: show }),

  setSearchMemberName: (name) => set({ searchMemberName: name }),

  resetFilters: () => set(initialState),
}));
