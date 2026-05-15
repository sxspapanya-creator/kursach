import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'

export function useHomeData() {
    const router = useRouter()

    const stats = ref({
        totalIncome: 0,
        totalExpenses: 0,
        monthlyIncome: 0,
        monthlyExpenses: 0,
        monthlyBalance: 0
    })

    const monthlyTrends = ref([])
    const recentTransactions = ref([])
    const loading = ref(true)
    const error = ref(null)

    const getCurrencySymbol = (currencyCode) => {
        const symbols = {
            'BYN': 'Br',
            'RUB': '₽',
            'USD': '$',
            'EUR': '€',
            'CNY': '¥'
        }
        return symbols[currencyCode] || 'Br'
    }

    const formatMoneyWithCurrency = (amount, currencyCode = 'BYN') => {
        if (amount === null || amount === undefined || isNaN(amount)) return `0 ${getCurrencySymbol(currencyCode)}`
        const symbol = getCurrencySymbol(currencyCode)
        return new Intl.NumberFormat('ru-RU', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount) + ' ' + symbol
    }

    const formatMoneyAmount = (amount) => {
        if (amount === null || amount === undefined || isNaN(amount)) return '0'
        return new Intl.NumberFormat('ru-RU', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount)
    }

    const formatTransactionMoney = (transaction) => {
        if (!transaction) return '0 Br'
        const amount = transaction.amount || 0
        const currencyCode = transaction.currency?.code || 'BYN'
        const currencySymbol = getCurrencySymbol(currencyCode)
        return new Intl.NumberFormat('ru-RU', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount) + ' ' + currencySymbol
    }

    const getAmountInByn = (transaction) => {
        if (!transaction) return 0
        if (transaction.amount_in_byn !== null && transaction.amount_in_byn !== undefined) {
            return parseFloat(transaction.amount_in_byn) || 0
        }
        if (transaction.exchange_rate) {
            return (parseFloat(transaction.amount) || 0) * parseFloat(transaction.exchange_rate)
        }
        return parseFloat(transaction.amount) || 0
    }

    const monthlyTrendsProcessed = computed(() => {
        if (!monthlyTrends.value || monthlyTrends.value.length === 0) return []
        const lastThree = monthlyTrends.value.slice(-3)
        return lastThree.map(month => {
            let income = parseFloat(month.income) || 0
            let expenses = parseFloat(month.expenses || month.expense) || 0
            let balance = parseFloat(month.balance) || (income - expenses)
            const total = income + expenses
            const incomePercentage = total > 0 ? (income / total) * 100 : 0
            const expensesPercentage = total > 0 ? (expenses / total) * 100 : 0
            return {
                period: month.period || month.month,
                income,
                expenses,
                balance,
                incomePercentage,
                expensesPercentage
            }
        })
    })

    const totalBalance = computed(() => stats.value.totalIncome - stats.value.totalExpenses)

    const balanceClass = computed(() => {
        const balance = stats.value.monthlyBalance
        if (balance > 0) return 'positive'
        if (balance < 0) return 'negative'
        return 'neutral'
    })

    const totalBalanceClass = computed(() => {
        const balance = totalBalance.value
        if (balance > 0) return 'positive'
        if (balance < 0) return 'negative'
        return 'neutral'
    })

    const getBalanceClass = (balance) => {
        if (balance > 0) return 'positive'
        if (balance < 0) return 'negative'
        return 'neutral'
    }

    const formatDate = (dateString) => {
        if (!dateString) return 'Дата не указана'
        try {
            const date = new Date(dateString)
            const now = new Date()
            const diffTime = Math.abs(now - date)
            const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24))

            if (date.toDateString() === now.toDateString()) return 'Сегодня'
            const yesterday = new Date(now)
            yesterday.setDate(yesterday.getDate() - 1)
            if (date.toDateString() === yesterday.toDateString()) return 'Вчера'
            if (diffDays <= 7) {
                return date.toLocaleDateString('ru-RU', { weekday: 'short', day: 'numeric' })
            }
            return date.toLocaleDateString('ru-RU', {
                day: 'numeric',
                month: 'short',
                year: diffDays > 365 ? 'numeric' : undefined
            })
        } catch (error) {
            return 'Неверная дата'
        }
    }

    const formatMonth = (monthString) => {
        if (!monthString) return ''
        const [year, month] = monthString.split('-')
        const date = new Date(year, parseInt(month) - 1, 1)
        return date.toLocaleDateString('ru-RU', { month: 'long', year: 'numeric' })
    }

    const editTransaction = (transaction) => {
        router.push(`/transactions/edit/${transaction.id}`)
    }

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
        getAmountInByn,
        formatDate,
        formatMonth,
        editTransaction,
        getCurrencySymbol
    }
}