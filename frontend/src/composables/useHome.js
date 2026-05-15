import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

export function useHome() {
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

    const editTransaction = (transaction) => {
        router.push(`/transactions/edit/${transaction.id}`)
    }

    const userHasPremiumPlan = () => {
        try {
            const raw = localStorage.getItem('user')
            if (!raw) return false
            return JSON.parse(raw).plan_code === 'premium'
        } catch {
            return false
        }
    }

    const fetchDashboardData = async () => {
        try {
            loading.value = true
            error.value = null

            const headers = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }

            const summaryUrl = '/api/transactions/summary'
            const recentUrl = '/api/transactions/recent?limit=6&include_anomalies=true'
            const allTransactionsUrl = '/api/transactions?limit=10000&include_anomalies=true'
            const trendsUrl = '/api/analytics/monthly-trends?months=12'

            const summaryPromise = fetch(summaryUrl, { headers, credentials: 'include' })
            const recentPromise = fetch(recentUrl, { headers, credentials: 'include' })
            const allTxPromise = fetch(allTransactionsUrl, { headers, credentials: 'include' })
            const trendsPromise = userHasPremiumPlan()
                ? fetch(trendsUrl, { headers, credentials: 'include' })
                : Promise.resolve(null)

            const summaryResponse = await summaryPromise
            const recentResponse = await recentPromise
            const allTransactionsResponse = await allTxPromise
            const trendsResponse = await trendsPromise

            if (summaryResponse.status === 401 || recentResponse.status === 401 ||
                allTransactionsResponse.status === 401) {
                throw new Error('Unauthorized')
            }

            let trendsData = { status: 'error', data: [] }
            if (trendsResponse) {
                if (trendsResponse.status === 401) throw new Error('Unauthorized')
                trendsData = await trendsResponse.json().catch(() => ({ status: 'error', data: [] }))
            }

            const [summaryData, recentData, allTransactionsData] = await Promise.all([
                summaryResponse.json().catch(() => ({ status: 'error', data: null })),
                recentResponse.json().catch(() => ({ status: 'error', data: [] })),
                allTransactionsResponse.json().catch(() => ({ status: 'error', data: [] }))
            ])

            let allTransactions = []
            if (allTransactionsData.status === 'success' && allTransactionsData.data) {
                allTransactions = Array.isArray(allTransactionsData.data) ? allTransactionsData.data : []
            }

            const now = new Date()
            const currentYear = now.getFullYear()
            const currentMonth = now.getMonth() + 1

            const currentMonthTransactions = allTransactions.filter(t => {
                if (!t.date) return false
                const date = new Date(t.date)
                return date.getFullYear() === currentYear && (date.getMonth() + 1) === currentMonth
            })

            let monthlyIncome = 0
            let monthlyExpenses = 0

            currentMonthTransactions.forEach(transaction => {
                const amountInByn = getAmountInByn(transaction)
                if (transaction.type === 'income') monthlyIncome += amountInByn
                else if (transaction.type === 'expense') monthlyExpenses += amountInByn
            })

            stats.value.monthlyIncome = monthlyIncome
            stats.value.monthlyExpenses = monthlyExpenses
            stats.value.monthlyBalance = monthlyIncome - monthlyExpenses

            // ТРЕНДЫ
            const monthlyMap = new Map()
            allTransactions.forEach(transaction => {
                if (!transaction.date) return
                const date = new Date(transaction.date)
                const year = date.getFullYear()
                const month = date.getMonth() + 1
                const period = `${year}-${String(month).padStart(2, '0')}`

                if (!monthlyMap.has(period)) {
                    monthlyMap.set(period, { income: 0, expenses: 0, period })
                }
                const monthData = monthlyMap.get(period)
                const amountInByn = getAmountInByn(transaction)
                if (transaction.type === 'income') monthData.income += amountInByn
                else if (transaction.type === 'expense') monthData.expenses += amountInByn
            })

            let calculatedTrends = Array.from(monthlyMap.values())
                .map(item => ({ ...item, balance: item.income - item.expenses }))
                .sort((a, b) => a.period.localeCompare(b.period))

            if (calculatedTrends.length > 0) {
                monthlyTrends.value = calculatedTrends
            } else if (trendsData.status === 'success' && trendsData.data && Array.isArray(trendsData.data)) {
                monthlyTrends.value = trendsData.data
            }

            let totalIncome = 0
            let totalExpenses = 0
            allTransactions.forEach(transaction => {
                const amountInByn = getAmountInByn(transaction)
                if (transaction.type === 'income') totalIncome += amountInByn
                else if (transaction.type === 'expense') totalExpenses += amountInByn
            })
            stats.value.totalIncome = totalIncome
            stats.value.totalExpenses = totalExpenses

            let recentTransactionsData = []
            if (recentData.status === 'success' && recentData.data) {
                recentTransactionsData = Array.isArray(recentData.data) ? recentData.data : []
            }
            recentTransactions.value = recentTransactionsData.map(t => ({
                id: t.id,
                amount: t.amount,
                type: t.type,
                description: t.description,
                date: t.date,
                currency: t.currency,
                exchange_rate: t.exchange_rate,
                amount_in_byn: t.amount_in_byn,
                categories: (t.categories && Array.isArray(t.categories)) ? t.categories.map(cat => ({
                    id: cat.id,
                    name: cat.name,
                    color: cat.color
                })) : []
            }))

        } catch (err) {
            console.error('Error fetching dashboard data:', err)
            if (err.message && (err.message.includes('401') || err.message.includes('Unauthorized'))) {
                error.value = 'Требуется авторизация'
            } else {
                error.value = 'Ошибка загрузки данных: ' + (err.message || 'Неизвестная ошибка')
            }
            stats.value = {
                totalIncome: 0,
                totalExpenses: 0,
                monthlyIncome: 0,
                monthlyExpenses: 0,
                monthlyBalance: 0
            }
            monthlyTrends.value = []
            recentTransactions.value = []
        } finally {
            loading.value = false
        }
    }

    onMounted(() => {
        fetchDashboardData()
    })

    return {
        // Состояния
        stats,
        monthlyTrends,
        recentTransactions,
        loading,
        error,

        // Вычисляемые
        totalBalance,
        balanceClass,
        totalBalanceClass,
        monthlyTrendsProcessed,

        // Методы
        getBalanceClass,
        formatMoneyWithCurrency,
        formatMoneyAmount,
        formatTransactionMoney,
        formatDate,
        formatMonth,
        editTransaction,
        fetchDashboardData
    }
}