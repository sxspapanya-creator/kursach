import { onMounted } from 'vue'
import { useHomeData } from './useHomeData'
import { useHomeFetch } from './useHomeFetch'

export function useHome() {
    const {
        stats,
        monthlyTrends,
        recentTransactions,
        loading,
        error,
        totalBalance,
        balanceClass,
        totalBalanceClass,
        monthlyTrendsProcessed,
        getBalanceClass,
        formatMoneyWithCurrency,
        formatMoneyAmount,
        formatTransactionMoney,
        getAmountInByn,
        formatDate,
        formatMonth,
        editTransaction
    } = useHomeData()

    const { fetchDashboardData } = useHomeFetch(stats, monthlyTrends, recentTransactions, loading, error, getAmountInByn)

    onMounted(() => {
        fetchDashboardData()
    })

    return {
        stats,
        monthlyTrends,
        recentTransactions,
        loading,
        error,
        totalBalance,
        balanceClass,
        totalBalanceClass,
        monthlyTrendsProcessed,
        getBalanceClass,
        formatMoneyWithCurrency,
        formatMoneyAmount,
        formatTransactionMoney,
        formatDate,
        formatMonth,
        editTransaction
    }
}