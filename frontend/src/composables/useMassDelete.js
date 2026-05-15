import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { useCurrencies } from './useCurrencies'
import { useDateFormatter } from './useDateFormatter'

export function useMassDelete() {
    const router = useRouter()
    const deleteFilters = ref({ type: '' })

    // Используем существующие хуки для форматирования
    const currenciesHook = useCurrencies()
    const dateFormatter = useDateFormatter()

    // ========== СОСТОЯНИЯ ==========
    const activeTab = ref('date')
    const loading = ref(false)
    const error = ref('')
    const showConfirmDialog = ref(false)
    const deleting = ref(false)

    const previewTransactions = ref([])
    const previewLoaded = ref(false)

    const previewStats = computed(() => {
        let income = 0, expenses = 0
        previewTransactions.value.forEach(t => {
            const amount = parseFloat(t.amount) || 0
            if (t.type === 'income') income += amount
            else expenses += amount
        })
        return { income, expenses, balance: income - expenses }
    })

    const dateSelectionType = ref('range')
    const dateRange = ref({ from: '', to: '' })
    const singleDate = ref('')
    const dateFromError = ref('')
    const dateToError = ref('')
    const singleDateError = ref('')
    const minDate = ref('')
    const maxDate = ref('')
    const availableDates = ref([])
    const allDatesAllowed = ref(false)

    const allCategories = ref([])
    const selectedCategories = ref([])
    const categoryPeriod = ref({ month: '' })

    const deleteType = ref('')
    const typePeriod = ref({ month: '' })

    // ========== МЕТОДЫ ДЛЯ ДАТ ==========
    const setDateRange = (dates) => {
        if (dates && dates.length > 0) {
            minDate.value = dates[0]
            maxDate.value = dates[dates.length - 1]
        } else {
            const today = new Date()
            const sixMonthsAgo = new Date()
            sixMonthsAgo.setMonth(today.getMonth() - 6)
            maxDate.value = today.toISOString().split('T')[0]
            minDate.value = sixMonthsAgo.toISOString().split('T')[0]
        }
    }

    const isDateAvailable = (date) => {
        if (!date || allDatesAllowed.value) return true
        return availableDates.value.includes(date)
    }

    const validateDateFrom = () => {
        dateFromError.value = ''
        if (dateRange.value.from && !isDateAvailable(dateRange.value.from)) {
            dateFromError.value = 'На эту дату нет курса валют'
        }
    }

    const validateDateTo = () => {
        dateToError.value = ''
        if (dateRange.value.to && !isDateAvailable(dateRange.value.to)) {
            dateToError.value = 'На эту дату нет курса валют'
        }
    }

    const validateSingleDate = () => {
        singleDateError.value = ''
        if (singleDate.value && !isDateAvailable(singleDate.value)) {
            singleDateError.value = 'На эту дату нет курса валют'
        }
    }

    const availableDatesHint = computed(() => {
        if (allDatesAllowed.value) return ''
        if (availableDates.value.length === 0) return 'Нет доступных дат'
        if (availableDates.value.length > 10) {
            return `${availableDates.value[0]} ... ${availableDates.value[availableDates.value.length - 1]} (${availableDates.value.length} дат)`
        }
        return availableDates.value.join(', ')
    })

    const isDateSelectionValid = computed(() => {
        if (dateSelectionType.value === 'range') {
            return dateRange.value.from && dateRange.value.to
        }
        return !!singleDate.value
    })

    const isDateSelectionUnavailable = computed(() => {
        if (dateSelectionType.value === 'range') {
            return (dateRange.value.from && !isDateAvailable(dateRange.value.from)) ||
                (dateRange.value.to && !isDateAvailable(dateRange.value.to))
        }
        return singleDate.value && !isDateAvailable(singleDate.value)
    })

    // ========== МЕТОДЫ ДЛЯ КАТЕГОРИЙ ==========
    const fetchCategories = async () => {
        try {
            const response = await axios.get('/api/categories')
            allCategories.value = response.data.data || []
        } catch (err) {
            console.error('Error fetching categories:', err)
        }
    }

    // ========== МЕТОДЫ ДЛЯ ВАЛЮТ ==========
    const fetchCurrencies = async () => {
        await currenciesHook.fetchCurrencies()
        const byn = currenciesHook.currencies.value.find(c => c.code === 'BYN')
        if (byn) {
            await currenciesHook.fetchAvailableDates(byn.id)
            availableDates.value = currenciesHook.availableDates.value
            allDatesAllowed.value = currenciesHook.allDatesAllowed.value
            setDateRange(currenciesHook.availableDates.value)
        }
    }

    // ========== МЕТОДЫ ДЛЯ PREVIEW ==========
    const buildTransactionsQueryString = (opts) => {
        const sp = new URLSearchParams()
        if (opts.fetch_all) sp.set('fetch_all', '1')
        if (opts.type) sp.set('type', opts.type)
        if (opts.date_from) sp.set('date_from', opts.date_from)
        if (opts.date_to) sp.set('date_to', opts.date_to)
        if (opts.year != null && opts.year !== '') sp.set('year', String(opts.year))
        if (opts.month != null && opts.month !== '') sp.set('month', String(opts.month))
        if (opts.category_ids?.length) {
            for (const id of opts.category_ids) {
                sp.append('category_ids[]', String(id))
            }
        }
        return sp.toString()
    }

    const fetchPreview = async (params) => {
        try {
            loading.value = true
            error.value = ''
            const qs = buildTransactionsQueryString(params)
            const response = await axios.get(`/api/transactions?${qs}`)
            previewTransactions.value = response.data.data || []
            previewLoaded.value = true
            if (previewTransactions.value.length === 0) {
                error.value = 'По вашему запросу транзакции не найдены'
                setTimeout(() => { error.value = '' }, 3000)
            }
        } catch (err) {
            console.error('Error previewing transactions:', err)
            error.value = 'Ошибка при поиске транзакций'
        } finally {
            loading.value = false
        }
    }

    const executeDelete = async (transactionIds, router, showNotification) => {
        try {
            deleting.value = true
            error.value = ''
            await axios.post('/api/transactions/mass-delete', { transaction_ids: transactionIds })
            showConfirmDialog.value = false
            previewTransactions.value = []
            previewLoaded.value = false
            if (showNotification) {
                showNotification('success', `Удалено ${transactionIds.length} транзакций`)
            }
            router.push('/transactions')
        } catch (err) {
            console.error('Error deleting transactions:', err)
            error.value = err.response?.data?.message || 'Ошибка при удалении транзакций'
        } finally {
            deleting.value = false
        }
    }

    // ========== МЕТОДЫ ДЕЙСТВИЙ ==========
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
        // Сброс дат
        dateSelectionType.value = 'range'
        dateRange.value = { from: '', to: '' }
        singleDate.value = ''
        dateFromError.value = ''
        dateToError.value = ''
        singleDateError.value = ''

        // Сброс категорий
        selectedCategories.value = []
        categoryPeriod.value = { month: '' }

        // Сброс типа
        deleteType.value = ''
        typePeriod.value = { month: '' }

        // Сброс превью
        previewTransactions.value = []
        previewLoaded.value = false
        showConfirmDialog.value = false
        error.value = ''
        deleteFilters.value = { type: '' }
        activeTab.value = 'date'
    }

    const switchTab = (tab) => {
        activeTab.value = tab
        previewTransactions.value = []
        previewLoaded.value = false
    }

    // ========== ФОРМАТИРОВАНИЕ ==========
    const formatMoney = currenciesHook.formatMoneyAmount
    const formatTransactionMoney = currenciesHook.formatTransactionMoney
    const formatDate = dateFormatter.formatDate

    // ========== ИНИЦИАЛИЗАЦИЯ ==========
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