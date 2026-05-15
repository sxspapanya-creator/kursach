import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

export function useTransactionsList() {
    const router = useRouter()
    const transactions = ref([])
    const loading = ref(true)

    const filters = ref({
        type: '',
        month: new Date().toISOString().slice(0, 7),
        includeAnomalies: false
    })

    const fetchTransactions = async () => {
        try {
            loading.value = true
            const params = {
                include_anomalies: 'true'
            }

            if (filters.value.type) params.type = filters.value.type
            if (filters.value.month) {
                const [year, month] = filters.value.month.split('-')
                params.month = parseInt(month)
                params.year = parseInt(year)
            }

            const response = await axios.get('/api/transactions', { params })
            transactions.value = response.data.data || []
        } catch (error) {
            console.error('Error fetching transactions:', error)
        } finally {
            loading.value = false
        }
    }

    const editTransaction = (transaction) => {
        router.push(`/transactions/edit/${transaction.id}`)
    }

    const deleteTransaction = async (id) => {
        try {
            await axios.delete(`/api/transactions/${id}`)
            await fetchTransactions()
        } catch (error) {
            console.error('Error deleting transaction:', error)
            alert('Ошибка при удалении транзакции')
        }
    }

    const resetFilters = () => {
        filters.value = {
            type: '',
            month: new Date().toISOString().slice(0, 7),
            includeAnomalies: false
        }
        fetchTransactions()
    }

    const filteredStats = computed(() => {
        let income = 0
        let expenses = 0

        transactions.value.forEach(transaction => {
            let amountInByn = transaction.amount_in_byn || transaction.amount
            if (!transaction.amount_in_byn && transaction.exchange_rate) {
                amountInByn = transaction.amount * transaction.exchange_rate
            }

            if (transaction.type === 'income') {
                income += parseFloat(amountInByn) || 0
            } else if (transaction.type === 'expense') {
                expenses += parseFloat(amountInByn) || 0
            }
        })

        return { income, expenses, balance: income - expenses }
    })

    const filteredBalanceClass = computed(() => {
        const balance = filteredStats.value.balance
        if (balance > 0) return 'positive'
        if (balance < 0) return 'negative'
        return 'neutral'
    })

    onMounted(() => {
        fetchTransactions()
    })

    return {
        transactions,
        loading,
        filters,
        filteredStats,
        filteredBalanceClass,
        fetchTransactions,
        editTransaction,
        deleteTransaction,
        resetFilters
    }
}