import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { useMassDeleteDate } from './useMassDeleteDate'
import { useMassDeleteCategories } from './useMassDeleteCategories'
import { useMassDeleteType } from './useMassDeleteType'
import { useMassDeletePreview } from './useMassDeletePreview'

export function useMassDelete() {
    const router = useRouter()
    const activeTab = ref('date')
    const deleteFilters = ref({ type: '' })

    const {
        dateSelectionType,
        dateRange,
        singleDate,
        dateFromError,
        dateToError,
        singleDateError,
        minDate,
        maxDate,
        availableDates,
        allDatesAllowed,
        setDateRange,
        isDateAvailable,
        validateDateFrom,
        validateDateTo,
        validateSingleDate,
        availableDatesHint,
        isDateSelectionValid,
        isDateSelectionUnavailable,
        resetDateFilters
    } = useMassDeleteDate()

    const {
        allCategories,
        selectedCategories,
        categoryPeriod,
        fetchCategories,
        resetCategoryFilters
    } = useMassDeleteCategories()

    const {
        deleteType,
        typePeriod,
        resetTypeFilters
    } = useMassDeleteType()

    const {
        previewTransactions,
        previewLoaded,
        loading,
        error,
        showConfirmDialog,
        deleting,
        previewStats,
        fetchPreview,
        executeDelete,
        resetPreview
    } = useMassDeletePreview()

    const currencies = ref([])
    const bynCurrency = ref(null)

    const fetchCurrencies = async () => {
        try {
            const response = await axios.get('/api/currencies')
            currencies.value = response.data.data || []
            const byn = currencies.value.find(c => c.code === 'BYN')
            if (byn) {
                bynCurrency.value = byn
                await fetchAvailableDates(byn.id)
            }
        } catch (err) {
            console.error('Error fetching currencies:', err)
        }
    }

    const fetchAvailableDates = async (currencyId) => {
        try {
            const response = await axios.get('/api/currencies/available-dates', {
                params: { currency_id: currencyId }
            })
            availableDates.value = response.data.data.available_dates || []
            allDatesAllowed.value = response.data.data.all_dates_allowed || false
            setDateRange(availableDates.value)
        } catch (err) {
            console.error('Error fetching available dates:', err)
        }
    }

    const getCurrencySymbol = (currencyCode) => {
        const symbols = { 'BYN': 'Br', 'RUB': '₽', 'USD': '$', 'EUR': '€', 'CNY': '¥' }
        return symbols[currencyCode] || 'Br'
    }

    const formatMoney = (amount) => {
        if (amount === null || isNaN(amount)) return '0 Br'
        return new Intl.NumberFormat('ru-RU', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount) + ' Br'
    }

    const formatTransactionMoney = (transaction) => {
        const currencyCode = transaction.currency?.code || 'BYN'
        const symbol = getCurrencySymbol(currencyCode)
        return new Intl.NumberFormat('ru-RU', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(transaction.amount) + ' ' + symbol
    }

    const formatDate = (dateString) => {
        if (!dateString) return ''
        const date = new Date(dateString)
        return date.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' })
    }

    const previewByDate = async () => {
        if (isDateSelectionUnavailable.value) {
            error.value = 'Выбраны даты без курса валют'
            setTimeout(() => { error.value = '' }, 3000)
            return
        }

        let params = { fetch_all: true }
        if (dateSelectionType.value === 'range') {
            params.date_from = dateRange.value.from
            params.date_to = dateRange.value.to
        } else {
            params.date_from = singleDate.value
            params.date_to = singleDate.value
        }
        if (deleteFilters.value.type) params.type = deleteFilters.value.type

        await fetchPreview(params)
    }

    const previewByCategory = async () => {
        if (selectedCategories.value.length === 0) {
            error.value = 'Выберите хотя бы одну категорию'
            setTimeout(() => { error.value = '' }, 3000)
            return
        }

        const opts = {
            fetch_all: true,
            category_ids: [...selectedCategories.value]
        }
        if (categoryPeriod.value.month) {
            const [year, month] = categoryPeriod.value.month.split('-')
            opts.year = parseInt(year, 10)
            opts.month = parseInt(month, 10)
        }
        await fetchPreview(opts)
    }

    const previewByType = async () => {
        if (!deleteType.value) {
            error.value = 'Выберите тип транзакций для удаления'
            setTimeout(() => { error.value = '' }, 3000)
            return
        }

        let params = { fetch_all: true, type: deleteType.value }
        if (typePeriod.value.month) {
            const [year, month] = typePeriod.value.month.split('-')
            params.year = parseInt(year)
            params.month = parseInt(month)
        }
        await fetchPreview(params)
    }

    const handleDelete = async () => {
        const transactionIds = previewTransactions.value.map(t => t.id)
        await executeDelete(transactionIds, router, window.showNotification)
    }

    const resetAll = () => {
        resetDateFilters()
        resetCategoryFilters()
        resetTypeFilters()
        resetPreview()
        deleteFilters.value = { type: '' }
        activeTab.value = 'date'
    }

    const switchTab = (tab) => {
        activeTab.value = tab
        resetPreview()
    }

    onMounted(() => {
        fetchCategories()
        fetchCurrencies()
    })

    return {
        activeTab,
        deleteFilters,
        previewTransactions,
        previewLoaded,
        loading,
        error,
        showConfirmDialog,
        deleting,
        previewStats,
        dateSelectionType,
        dateRange,
        singleDate,
        dateFromError,
        dateToError,
        singleDateError,
        minDate,
        maxDate,
        allCategories,
        selectedCategories,
        categoryPeriod,
        deleteType,
        typePeriod,
        isDateSelectionValid,
        isDateSelectionUnavailable,
        availableDatesHint,
        isDateAvailable,
        validateDateFrom,
        validateDateTo,
        validateSingleDate,
        formatMoney,
        formatTransactionMoney,
        formatDate,
        previewByDate,
        previewByCategory,
        previewByType,
        handleDelete,
        resetAll,
        switchTab
    }
}