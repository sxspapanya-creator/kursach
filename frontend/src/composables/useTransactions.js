import { ref, computed } from 'vue'
import { transactionApi } from '../services/api/transactions'
import { formatTransactionMoney, getAmountInByn, formatDate } from '../utils/money'
import { formatDate as formatDateUtil } from '../utils/date'

export function useTransactions() {
    const transactions = ref([])
    const loading = ref(false)
    const error = ref(null)

    const filters = ref({
        type: '',
        month: new Date().toISOString().slice(0, 7),
        includeAnomalies: false
    })

    // Получить транзакции
    const fetchTransactions = async () => {
        try {
            loading.value = true
            error.value = null

            const params = {
                include_anomalies: filters.value.includeAnomalies ? 'true' : 'false'
            }

            if (filters.value.type) params.type = filters.value.type
            if (filters.value.month) {
                const [year, month] = filters.value.month.split('-')
                params.month = parseInt(month)
                params.year = parseInt(year)
            }

            const response = await transactionApi.getTransactions(params)
            transactions.value = response.data.data || []
        } catch (err) {
            error.value = err.message
            console.error('Error fetching transactions:', err)
        } finally {
            loading.value = false
        }
    }

    // Сбросить фильтры
    const resetFilters = () => {
        filters.value = {
            type: '',
            month: new Date().toISOString().slice(0, 7),
            includeAnomalies: false
        }
        fetchTransactions()
    }

    // Удалить транзакцию
    const deleteTransaction = async (id) => {
        try {
            await transactionApi.deleteTransaction(id)
            await fetchTransactions()
            return true
        } catch (err) {
            console.error('Error deleting transaction:', err)
            alert('Ошибка при удалении транзакции')
            return false
        }
    }

    // Статистика по отфильтрованным транзакциям
    const filteredStats = computed(() => {
        let income = 0
        let expenses = 0

        transactions.value.forEach(transaction => {
            const amountInByn = getAmountInByn(transaction)

            if (transaction.type === 'income') {
                income += amountInByn
            } else if (transaction.type === 'expense') {
                expenses += amountInByn
            }
        })

        return { income, expenses, balance: income - expenses }
    })

    const balanceClass = computed(() => {
        const balance = filteredStats.value.balance
        if (balance > 0) return 'positive'
        if (balance < 0) return 'negative'
        return 'neutral'
    })

    return {
        transactions,
        loading,
        error,
        filters,
        filteredStats,
        balanceClass,
        fetchTransactions,
        resetFilters,
        deleteTransaction
    }
}