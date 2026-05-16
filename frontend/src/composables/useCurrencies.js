import { ref, computed } from 'vue'
import axios from 'axios'

export function useCurrencies() {
    const currencies = ref([])
    const loading = ref(false)
    const availableDates = ref([])
    const allDatesAllowed = ref(false)
    const minDate = ref('')
    const maxDate = ref('')

    const currencySymbols = {
        'BYN': 'Br',
        'RUB': '₽',
        'USD': '$',
        'EUR': '€',
        'CNY': '¥'
    }

    const currencyFlags = {
        'BYN': '🇧🇾',
        'RUB': '🇷🇺',
        'USD': '🇺🇸',
        'EUR': '🇪🇺',
        'CNY': '🇨🇳'
    }

    const currencyIds = {
        1: 'BYN',
        2: 'RUB',
        3: 'USD',
        4: 'EUR',
        5: 'CNY'
    }

    const fetchCurrencies = async () => {
        try {
            loading.value = true
            const response = await axios.get('/api/currencies')
            currencies.value = response.data.data || []
            return currencies.value
        } catch (error) {
            console.error('Error fetching currencies:', error)
            return []
        } finally {
            loading.value = false
        }
    }

    const getCurrencyById = (id) => {
        return currencies.value.find(c => c.id === id)
    }

    const getCurrencySymbol = (currencyCode) => {
        return currencySymbols[currencyCode] || currencyCode || 'Br'
    }

    const getCurrencyFlag = (code) => {
        return currencyFlags[code] || '💰'
    }

    const getCurrencyCode = (currencyId) => {
        return currencyIds[currencyId] || 'BYN'
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

    const setDateRange = (monthsBack = 6) => {
        const today = new Date()
        const past = new Date()
        past.setMonth(today.getMonth() - monthsBack)
        maxDate.value = today.toISOString().split('T')[0]
        minDate.value = past.toISOString().split('T')[0]
    }

    const fetchAvailableDates = async (currencyId) => {
        if (!currencyId) return
        loading.value = true
        try {
            const response = await axios.get('/api/currencies/available-dates', {
                params: { currency_id: currencyId }
            })
            availableDates.value = response.data.data.available_dates || []
            allDatesAllowed.value = response.data.data.all_dates_allowed || false
        } catch (err) {
            console.error('Error fetching available dates:', err)
            availableDates.value = []
            allDatesAllowed.value = false
        } finally {
            loading.value = false
        }
    }

    const isDateAvailable = (date) => {
        if (!date) return true
        if (allDatesAllowed.value) return true
        return availableDates.value.includes(date)
    }

    const validateDate = (date) => {
        if (!date) return ''

        const today = new Date().toISOString().split('T')[0]
        if (date > today) return 'Нельзя выбрать дату в будущем'

        if (!allDatesAllowed.value && date !== today && !isDateAvailable(date)) {
            return `На дату ${date} нет курса для выбранной валюты`
        }
        return ''
    }

    const availableDatesHint = computed(() => {
        if (allDatesAllowed.value) return 'Все даты доступны'
        if (availableDates.value.length === 0) return 'Нет доступных дат'
        if (availableDates.value.length > 10) {
            return `${availableDates.value[0]} ... ${availableDates.value[availableDates.value.length - 1]} (${availableDates.value.length} дат)`
        }
        return availableDates.value.join(', ')
    })

    const formatRate = (rate) => {
        if (!rate && rate !== 0) return '0.0000'
        return Number(rate).toFixed(4)
    }

    const formatTransactionMoney = (transaction) => {
        if (!transaction) return '0 Br'
        const amount = transaction.amount || 0
        const currencyCode = transaction.currency?.code || getCurrencyCode(transaction.currency_id)
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

    return {
        currencies,
        loading,
        availableDates,
        allDatesAllowed,
        minDate,
        maxDate,
        availableDatesHint,
        fetchCurrencies,
        getCurrencyById,
        getCurrencySymbol,
        getCurrencyFlag,
        getCurrencyCode,
        getAmountInByn,
        setDateRange,
        fetchAvailableDates,
        isDateAvailable,
        validateDate,
        formatRate,
        formatTransactionMoney,
        formatMoneyAmount
    }
}