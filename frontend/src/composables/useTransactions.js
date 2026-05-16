import { ref, computed, watch, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'

export function useTransactions() {
    const router = useRouter()
    const route = useRoute()

    // Флаги для предотвращения дублирования запросов
    let initialized = false
    let loadingCurrencies = false
    let loadingCategories = false

    const loading = ref(false)
    const submitting = ref(false)
    const error = ref('')
    const amountError = ref('')
    const dateError = ref('')
    const currencies = ref([])
    const categories = ref([])

    const paymentMethods = [
        { value: 'card', name: 'Карта', icon: '💳' },
        { value: 'cash', name: 'Наличные', icon: '💵' },
        { value: 'transfer', name: 'Перевод', icon: '🏦' }
    ]

    const getPaymentMethodLabel = (method) => {
        const found = paymentMethods.find(m => m.value === method)
        return found ? found.name : method
    }

    const form = ref({
        amount: '',
        type: 'expense',
        category_ids: [],
        currency_id: null,
        description: '',
        date: new Date().toISOString().split('T')[0],
        payment_method: 'card'
    })

    const transactions = ref([])
    const filters = ref({
        type: '',
        month: new Date().toISOString().slice(0, 7),
        includeAnomalies: false
    })

    const availableDates = ref([])
    const allDatesAllowed = ref(false)
    const minDate = ref('')
    const maxDate = ref('')

    // ========== МЕТОДЫ ФОРМАТИРОВАНИЯ ==========
    const getCurrencyCode = (currencyId) => {
        const codes = { 1: 'BYN', 2: 'RUB', 3: 'USD', 4: 'EUR', 5: 'CNY' }
        return codes[currencyId] || 'BYN'
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
        const currencyCode = transaction.currency?.code || getCurrencyCode(transaction.currency_id)
        const symbols = { 'BYN': 'Br', 'RUB': '₽', 'USD': '$', 'EUR': '€', 'CNY': '¥' }
        const symbol = symbols[currencyCode] || 'Br'
        return formatMoneyAmount(amount) + ' ' + symbol
    }

    const formatRate = (rate) => {
        if (!rate && rate !== 0) return '0.0000'
        return Number(rate).toFixed(4)
    }

    const getCurrencyFlag = (code) => {
        const flags = { 'BYN': '🇧🇾', 'RUB': '🇷🇺', 'USD': '🇺🇸', 'EUR': '🇪🇺', 'CNY': '🇨🇳' }
        return flags[code] || '💰'
    }

    const filteredCategories = computed(() => {
        return categories.value
            .filter(cat => cat.type === form.value.type)
            .sort((a, b) => (a.name || '').localeCompare(b.name || ''))
    })

    const currentCurrency = computed(() => {
        return currencies.value.find(c => c.id === form.value.currency_id)
    })

    const currentCurrencySymbol = computed(() => {
        const symbols = { 'BYN': 'Br', 'RUB': '₽', 'USD': '$', 'EUR': '€', 'CNY': '¥' }
        return symbols[currentCurrency.value?.code] || 'Br'
    })

    const isFormValid = computed(() => {
        return form.value.amount > 0 &&
            form.value.category_ids.length > 0 &&
            form.value.currency_id !== null &&
            form.value.date &&
            !dateError.value
    })

    const isDateUnavailable = computed(() => {
        const today = new Date().toISOString().split('T')[0]
        if (form.value.date === today) return false
        if (allDatesAllowed.value) return false
        return !checkDateAvailable(form.value.date) && form.value.date !== ''
    })

    const filteredStats = computed(() => {
        let income = 0, expenses = 0
        transactions.value.forEach(transaction => {
            let amountInByn = transaction.amount_in_byn || transaction.amount
            if (!transaction.amount_in_byn && transaction.exchange_rate) {
                amountInByn = transaction.amount * transaction.exchange_rate
            }
            if (transaction.type === 'income') income += parseFloat(amountInByn) || 0
            else if (transaction.type === 'expense') expenses += parseFloat(amountInByn) || 0
        })
        return { income, expenses, balance: income - expenses }
    })

    const filteredBalanceClass = computed(() => {
        const balance = filteredStats.value.balance
        if (balance > 0) return 'positive'
        if (balance < 0) return 'negative'
        return 'neutral'
    })

    const setDateRange = () => {
        const today = new Date()
        const sixMonthsAgo = new Date()
        sixMonthsAgo.setMonth(today.getMonth() - 6)
        maxDate.value = today.toISOString().split('T')[0]
        minDate.value = sixMonthsAgo.toISOString().split('T')[0]
    }

    const fetchDatesForCurrency = async (currencyId) => {
        if (!currencyId) return
        try {
            const response = await axios.get('/api/currencies/available-dates', {
                params: { currency_id: currencyId }
            })
            availableDates.value = response.data.data.available_dates || []
            allDatesAllowed.value = response.data.data.all_dates_allowed || false
            if (form.value.date) validateDate()
        } catch (err) {
            console.error('Error fetching available dates:', err)
        }
    }

    const checkDateAvailable = (date) => {
        if (allDatesAllowed.value) return true
        if (!date) return false
        return availableDates.value.includes(date)
    }

    const validateDate = () => {
        if (!form.value.date) {
            dateError.value = ''
            return
        }
        const today = new Date().toISOString().split('T')[0]
        if (form.value.date > today) {
            dateError.value = 'Нельзя выбрать дату в будущем'
            return
        }
        if (allDatesAllowed.value) {
            dateError.value = ''
            return
        }
        if (form.value.date === today && !checkDateAvailable(today)) {
            dateError.value = ''
            return
        }
        if (!checkDateAvailable(form.value.date)) {
            dateError.value = `На дату ${form.value.date} нет курса для выбранной валюты.`
        } else {
            dateError.value = ''
        }
    }

    const setTodayDate = () => {
        const today = new Date().toISOString().split('T')[0]
        form.value.date = today
        dateError.value = ''
        validateDate()
    }

    const validateAmount = () => {
        const amount = parseFloat(form.value.amount)
        if (amount < 0.01) amountError.value = 'Сумма должна быть больше 0'
        else if (amount > 10000000) amountError.value = 'Слишком большая сумма'
        else amountError.value = ''
    }

    const toggleCategory = (categoryId) => {
        const index = form.value.category_ids.indexOf(categoryId)
        if (index === -1) form.value.category_ids.push(categoryId)
        else form.value.category_ids.splice(index, 1)
    }

    const selectCurrency = async (currency) => {
        form.value.currency_id = currency.id
        await fetchDatesForCurrency(currency.id)
        await reloadRatesForDate(form.value.date)
    }

    const reloadRatesForDate = async (date) => {
        if (!date) return
        try {
            const response = await axios.get('/api/currencies', {
                params: { date }
            })
            const newRates = response.data.data || []
            currencies.value = currencies.value.map(currency => {
                const newRate = newRates.find(r => r.id === currency.id)
                if (newRate && newRate.rate !== undefined) {
                    return { ...currency, rate: newRate.rate }
                }
                return currency
            })
        } catch (err) {
            console.error('Error reloading rates:', err)
        }
    }

    const loadCurrencies = async () => {
        if (loadingCurrencies) return
        loadingCurrencies = true
        try {
            const response = await axios.get('/api/currencies', {
                params: { date: form.value.date }
            })
            currencies.value = response.data.data || []
            const defaultCurrency = currencies.value.find(c => c.code === 'BYN')
            if (defaultCurrency && !form.value.currency_id) {
                form.value.currency_id = defaultCurrency.id
            }
        } catch (err) {
            console.error('Error fetching currencies:', err)
        } finally {
            loadingCurrencies = false
        }
    }

    const loadCategories = async () => {
        if (loadingCategories) return
        loadingCategories = true
        try {
            const response = await axios.get('/api/categories')
            categories.value = response.data.data || []
        } catch (err) {
            console.error('Error fetching categories:', err)
        } finally {
            loadingCategories = false
        }
    }

    const submitTransaction = async () => {
        if (!isFormValid.value) return

        const today = new Date().toISOString().split('T')[0]
        if (form.value.date > today) {
            error.value = 'Нельзя создать транзакцию на будущую дату'
            window.scrollTo({ top: 0, behavior: 'smooth' })
            return
        }

        try {
            submitting.value = true
            error.value = ''

            await axios.post('/api/transactions', {
                amount: parseFloat(form.value.amount).toFixed(2),
                type: form.value.type,
                category_ids: form.value.category_ids,
                currency_id: form.value.currency_id,
                description: form.value.description,
                date: form.value.date,
                payment_method: form.value.payment_method
            })

            if (window.showNotification) {
                window.showNotification('success', 'Транзакция успешно добавлена')
            }
            router.push('/transactions')
        } catch (err) {
            console.error('Error creating transaction:', err)
            error.value = err.response?.data?.message || 'Ошибка при создании транзакции'
            window.scrollTo({ top: 0, behavior: 'smooth' })
        } finally {
            submitting.value = false
        }
    }

    const fetchTransaction = async (id) => {
        try {
            loading.value = true
            const response = await axios.get(`/api/transactions/${id}`)
            const data = response.data.data || response.data

            form.value.amount = parseFloat(data.amount).toFixed(2)
            form.value.type = data.type || 'expense'
            form.value.category_ids = data.categories?.map(c => c.id) || []
            form.value.currency_id = data.currency_id
            form.value.description = data.description || ''
            form.value.date = data.date?.split('T')[0] || ''
            form.value.payment_method = data.payment_method || 'card'

            if (data.currency_id) {
                await fetchDatesForCurrency(data.currency_id)
                await reloadRatesForDate(form.value.date)
            }
            validateDate()
        } catch (err) {
            console.error('Error fetching transaction:', err)
            error.value = err.response?.status === 404 ? 'Транзакция не найдена' : 'Ошибка загрузки'
        } finally {
            loading.value = false
        }
    }

    const updateTransaction = async (id) => {
        if (!isFormValid.value) return

        try {
            submitting.value = true
            error.value = ''

            await axios.put(`/api/transactions/${id}`, {
                amount: parseFloat(form.value.amount).toFixed(2),
                type: form.value.type,
                category_ids: form.value.category_ids,
                currency_id: form.value.currency_id,
                description: form.value.description,
                date: form.value.date,
                payment_method: form.value.payment_method
            })

            if (window.showNotification) {
                window.showNotification('success', 'Транзакция успешно обновлена')
            }
            router.push('/transactions')
        } catch (err) {
            console.error('Error updating transaction:', err)
            error.value = err.response?.data?.message || 'Ошибка при обновлении транзакции'
            window.scrollTo({ top: 0, behavior: 'smooth' })
        } finally {
            submitting.value = false
        }
    }

    const fetchTransactions = async () => {
        try {
            loading.value = true
            const params = { include_anomalies: filters.value.includeAnomalies ? 'true' : 'false' }
            if (filters.value.type) params.type = filters.value.type
            if (filters.value.month) {
                const [year, month] = filters.value.month.split('-')
                params.month = parseInt(month)
                params.year = parseInt(year)
            }
            const response = await axios.get('/api/transactions', { params })
            transactions.value = response.data.data || []
        } catch (err) {
            console.error('Error fetching transactions:', err)
            error.value = err.message
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
            return true
        } catch (err) {
            console.error('Error deleting transaction:', err)
            alert('Ошибка при удалении транзакции')
            return false
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

    const resetForm = () => {
        form.value = {
            amount: '',
            type: 'expense',
            category_ids: [],
            currency_id: null,
            description: '',
            date: new Date().toISOString().split('T')[0],
            payment_method: 'card'
        }
        error.value = ''
        amountError.value = ''
        dateError.value = ''
    }

    const init = async () => {
        if (initialized) return
        initialized = true
        setDateRange()
        await loadCategories()
        await loadCurrencies()
    }

    watch(() => form.value.date, async (newDate, oldDate) => {
        if (newDate && newDate !== oldDate && currencies.value.length > 0) {
            await reloadRatesForDate(newDate)
            validateDate()
        }
    })

    watch(() => form.value.type, () => {
        form.value.category_ids = []
    })

    watch(() => form.value.currency_id, (newVal, oldVal) => {
        if (newVal && newVal !== oldVal) {
            fetchDatesForCurrency(newVal)
        }
    })

    return {
        loading,
        submitting,
        error,
        amountError,
        dateError,
        currencies,
        categories,
        paymentMethods,
        getPaymentMethodLabel,
        form,
        transactions,
        filters,
        minDate,
        maxDate,
        filteredCategories,
        currentCurrencySymbol,
        isFormValid,
        isDateUnavailable,
        filteredStats,
        filteredBalanceClass,
        validateAmount,
        toggleCategory,
        selectCurrency,
        setTodayDate,
        validateDate,
        submitTransaction,
        updateTransaction,
        fetchTransaction,
        resetForm,
        fetchTransactions,
        editTransaction,
        deleteTransaction,
        resetFilters,
        getCurrencyFlag,
        formatRate,
        formatMoneyAmount,
        formatTransactionMoney,
        getCurrencyCode,
        init,
        reloadRatesForDate
    }
}