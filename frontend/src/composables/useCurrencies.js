import { ref, computed } from 'vue'
import axios from 'axios'

export function useCurrencies() {
    const currencies = ref([])
    const loading = ref(false)
    const availableDates = ref([])
    const allDatesAllowed = ref(false)

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

    const fetchAvailableDates = async (currencyId) => {
        if (!currencyId) return

        try {
            const response = await axios.get('/api/currencies/available-dates', {
                params: { currency_id: currencyId }
            })
            availableDates.value = response.data.data.available_dates || []
            allDatesAllowed.value = response.data.data.all_dates_allowed || false
        } catch (err) {
            console.error('Error fetching available dates:', err)
        }
    }

    const getCurrencyById = (id) => {
        return currencies.value.find(c => c.id === id)
    }

    const getCurrencySymbol = (currencyCode) => {
        const symbols = {
            'BYN': 'Br',
            'RUB': '₽',
            'USD': '$',
            'EUR': '€',
            'CNY': '¥',
        }
        return symbols[currencyCode] || currencyCode
    }

    const getCurrencyFlag = (code) => {
        const flags = {
            'BYN': '🇧🇾',
            'RUB': '🇷🇺',
            'USD': '🇺🇸',
            'EUR': '🇪🇺',
            'CNY': '🇨🇳',
        }
        return flags[code] || '💰'
    }

    const formatRate = (rate) => {
        if (!rate && rate !== 0) return '0.0000'
        return Number(rate).toFixed(4)
    }

    const isDateAvailable = (date) => {
        if (allDatesAllowed.value) return true
        if (!date) return false
        return availableDates.value.includes(date)
    }

    return {
        currencies,
        loading,
        availableDates,
        allDatesAllowed,
        fetchCurrencies,
        fetchAvailableDates,
        getCurrencyById,
        getCurrencySymbol,
        getCurrencyFlag,
        formatRate,
        isDateAvailable
    }
}