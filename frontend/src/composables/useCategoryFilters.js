import { ref, computed } from 'vue'

export function useCategoryFilters(incomeCategories, expenseCategories) {
    const activeTab = ref('all')

    const displayedCategories = computed(() => {
        if (activeTab.value === 'income') return incomeCategories.value
        if (activeTab.value === 'expense') return expenseCategories.value
        return [...incomeCategories.value, ...expenseCategories.value]
    })

    const setTab = (tab) => {
        activeTab.value = tab
    }

    return {
        activeTab,
        displayedCategories,
        setTab
    }
}