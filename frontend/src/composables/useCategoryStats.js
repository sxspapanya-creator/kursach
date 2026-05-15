import { computed } from 'vue'

export function useCategoryStats(displayedCategories) {
    const displayedCategoriesWithMonthData = computed(() =>
        displayedCategories.value.map(category => ({
            ...category,
            total_amount: category.total_amount || 0,
            transaction_count: category.transaction_count || 0,
            all_time_count: category.all_time_count ?? 0,
            currency_stats: category.currency_stats || [],
            last_transaction_date: category.last_transaction_date
        }))
    )

    const formatMoney = (amount) => {
        if (amount == null || isNaN(amount)) return '0 Br'
        return new Intl.NumberFormat('ru-RU', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount) + ' Br'
    }

    const formatMoneyAmount = (amount) => {
        if (amount == null || isNaN(amount)) return '0'
        return new Intl.NumberFormat('ru-RU', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount)
    }

    const formatDate = (dateString) => {
        if (!dateString) return 'Нет транзакций'
        const date = new Date(dateString)
        return date.toLocaleDateString('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        })
    }

    return {
        displayedCategoriesWithMonthData,
        formatMoney,
        formatMoneyAmount,
        formatDate
    }
}