
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'

export function useEditTransaction(props) {
    const router = useRouter()
    const route = useRoute()

    const loading = ref(true)
    const submitting = ref(false)
    const error = ref('')
    const amountError = ref('')
    const dateError = ref('')
    const categories = ref([])
    const currencies = ref([])
    const availableDates = ref([])
    const allDatesAllowed = ref(false)
    const minDate = ref('')
    const maxDate = ref('')

    const paymentMethods = [
        { value: 'card', name: 'Карта', icon: '💳' },
        { value: 'cash', name: 'Наличные', icon: '💵' },
        { value: 'transfer', name: 'Перевод', icon: '🏦' },
    ]

    const form = ref({
        amount: '',
        type: 'expense',
        category_ids: [],
        currency_id: null,
        description: '',
        date: new Date().toISOString().split('T')[0],
        payment_method: 'card'
    })

    const roundToTwoDecimals = (value) => {
        const num = parseFloat(value)
        if (isNaN(num)) return ''
        return num.toFixed(2)
    }

    const roundAmount = () => {
        if (form.value.amount) {
            form.value.amount = roundToTwoDecimals(form.value.amount)
        }
    }

    const setDateRange = () => {
        const today = new Date()
        const sixMonthsAgo = new Date()
        sixMonthsAgo.setMonth(today.getMonth() - 6)
        maxDate.value = today.toISOString().split('T')[0]
        minDate.value = sixMonthsAgo.toISOString().split('T')[0]
    }

    const fetchAvailableDates = async (currencyId) => {
        if (!currencyId) return

        try {
            const response = await axios.get('/api/currencies/available-dates', {
                params: { currency_id: currencyId }
            })

            availableDates.value = response.data.data.available_dates || []
            allDatesAllowed.value = response.data.data.all_dates_allowed || false

            if (form.value.date) {
                validateDate()
            }
        } catch (err) {
            console.error('Error fetching available dates:', err)
        }
    }

    const isDateAvailable = (date) => {
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

        if (form.value.date === today && !isDateAvailable(today)) {
            dateError.value = ''
            return
        }

        if (!isDateAvailable(form.value.date)) {
            dateError.value = `На дату ${form.value.date} нет курса для выбранной валюты.`
        } else {
            dateError.value = ''
        }
    }

    const isTodayUnavailable = computed(() => false)

    const setTodayDate = () => {
        const today = new Date().toISOString().split('T')[0]
        form.value.date = today
        dateError.value = ''
        validateDate()
    }

    const isDateUnavailable = computed(() => {
        const today = new Date().toISOString().split('T')[0]
        if (form.value.date === today) return false
        if (allDatesAllowed.value) return false
        return !isDateAvailable(form.value.date) && form.value.date !== ''
    })

    const selectCurrency = (currency) => {
        form.value.currency_id = currency.id
    }

    const currentCurrency = computed(() => {
        return currencies.value.find(c => c.id === form.value.currency_id)
    })

    const currentCurrencySymbol = computed(() => {
        if (!currentCurrency.value) return 'Br'
        const symbols = {
            'BYN': 'Br',
            'RUB': '₽',
            'USD': '$',
            'EUR': '€',
            'CNY': '¥',
        }
        return symbols[currentCurrency.value.code] || currentCurrency.value.code
    })

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

    const filteredCategories = computed(() => {
        if (!categories.value || !Array.isArray(categories.value)) return []
        return categories.value
            .filter(cat => cat && cat.type === form.value.type)
            .sort((a, b) => (a.name || '').localeCompare(b.name || ''))
    })

    const isFormValid = computed(() => {
        return form.value.amount > 0 &&
            form.value.category_ids &&
            form.value.category_ids.length > 0 &&
            form.value.currency_id !== null &&
            form.value.date &&
            !dateError.value
    })

    const fetchTransaction = async () => {
        try {
            loading.value = true
            error.value = ''

            const transactionId = props.id || route.params.id

            const [transactionRes, categoriesRes, currenciesRes] = await Promise.all([
                axios.get(`/api/transactions/${transactionId}`),
                axios.get('/api/categories'),
                axios.get('/api/currencies')
            ])

            const transactionData = transactionRes.data.data || transactionRes.data
            const categoriesData = categoriesRes.data.data || categoriesRes.data
            const currenciesData = currenciesRes.data.data || currenciesRes.data

            if (!transactionData) {
                throw new Error('Транзакция не найдена')
            }

            categories.value = categoriesData
            currencies.value = currenciesData

            let formattedDate = transactionData.date
            if (formattedDate) {
                formattedDate = formattedDate.split('T')[0]
            }

            let categoryIds = []
            if (transactionData.categories && Array.isArray(transactionData.categories)) {
                categoryIds = transactionData.categories.map(cat => cat.id)
            }

            const roundedAmount = transactionData.amount
                ? parseFloat(transactionData.amount).toFixed(2)
                : ''

            form.value.amount = roundedAmount
            form.value.type = transactionData.type || 'expense'
            form.value.category_ids = categoryIds
            form.value.currency_id = transactionData.currency_id || null
            form.value.description = transactionData.description || ''
            form.value.date = formattedDate
            form.value.payment_method = transactionData.payment_method || 'card'

        } catch (err) {
            console.error('Error fetching transaction:', err)
            error.value = err.response?.status === 404
                ? 'Транзакция не найдена'
                : (err.response?.data?.message || err.message || 'Не удалось загрузить транзакцию')
        } finally {
            loading.value = false
        }
    }

    const toggleCategory = (categoryId) => {
        const index = form.value.category_ids.indexOf(categoryId)
        if (index === -1) {
            form.value.category_ids.push(categoryId)
        } else {
            form.value.category_ids.splice(index, 1)
        }
    }

    const validateAmount = () => {
        const amount = parseFloat(form.value.amount)
        if (isNaN(amount) || amount < 0.01) {
            amountError.value = 'Сумма должна быть больше 0'
        } else if (amount > 10000000) {
            amountError.value = 'Слишком большая сумма'
        } else {
            amountError.value = ''
        }
    }

    const updateTransaction = async () => {
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

            const transactionId = props.id || route.params.id
            const roundedAmount = parseFloat(form.value.amount).toFixed(2)

            const transactionData = {
                amount: roundedAmount,
                type: form.value.type,
                category_ids: form.value.category_ids,
                currency_id: form.value.currency_id,
                description: form.value.description,
                date: form.value.date,
                payment_method: form.value.payment_method
            }

            await axios.put(`/api/transactions/${transactionId}`, transactionData)

            if (window.showNotification) {
                window.showNotification('success', 'Транзакция успешно обновлена')
            }

            router.push('/transactions')
        } catch (err) {
            console.error('Error updating transaction:', err)
            error.value = err.response?.data?.message ||
                err.response?.data?.errors?.category_ids?.[0] ||
                'Ошибка при обновлении транзакции'
            window.scrollTo({ top: 0, behavior: 'smooth' })
        } finally {
            submitting.value = false
        }
    }

    onMounted(() => {
        setDateRange()
        fetchTransaction()
    })

    watch(() => form.value.type, () => {
        form.value.category_ids = []
    })

    watch(() => form.value.date, () => {
        validateDate()
    })

    watch(() => form.value.currency_id, (newVal, oldVal) => {
        if (newVal && newVal !== oldVal) {
            fetchAvailableDates(newVal)
        }
    }, { immediate: true })

    return {
        form,
        loading,
        submitting,
        error,
        amountError,
        dateError,
        categories,
        currencies,
        filteredCategories,
        paymentMethods,
        isFormValid,
        currentCurrencySymbol,
        getCurrencyFlag,
        formatRate,
        validateAmount,
        roundAmount,
        toggleCategory,
        fetchTransaction,
        updateTransaction,
        selectCurrency,
        minDate,
        maxDate,
        isDateUnavailable,
        isTodayUnavailable,
        setTodayDate
    }
}