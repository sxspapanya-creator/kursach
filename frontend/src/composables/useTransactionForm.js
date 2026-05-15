import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { useCurrencies } from './useCurrencies'
import { useCategories } from './useCategories'

export function useTransactionForm() {
    const router = useRouter()
    const {
        currencies,
        fetchCurrencies,
        fetchAvailableDates,
        getCurrencyById,
        getCurrencySymbol,
        isDateAvailable
    } = useCurrencies()
    const { categories, fetchCategories, getFilteredCategories } = useCategories()

    const loading = ref(false)
    const error = ref('')
    const amountError = ref('')
    const dateError = ref('')
    const availableDates = ref([])
    const allDatesAllowed = ref(false)
    const minDate = ref('')
    const maxDate = ref('')
    const currenciesLoaded = ref(false)
    const categoriesLoaded = ref(false)

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
        payment_method: 'card',
    })

    // Установка минимальной и максимальной даты (последние 6 месяцев)
    const setDateRange = () => {
        const today = new Date()
        const sixMonthsAgo = new Date()
        sixMonthsAgo.setMonth(today.getMonth() - 6)
        maxDate.value = today.toISOString().split('T')[0]
        minDate.value = sixMonthsAgo.toISOString().split('T')[0]
    }

    // Получение доступных дат для выбранной валюты
    const fetchDatesForCurrency = async (currencyId) => {
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

    // Проверка доступности даты
    const checkDateAvailable = (date) => {
        if (allDatesAllowed.value) return true
        if (!date) return false
        return availableDates.value.includes(date)
    }

    // Проверка валидности даты
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

    // Установка сегодняшней даты
    const setTodayDate = () => {
        const today = new Date().toISOString().split('T')[0]
        form.value.date = today
        dateError.value = ''
        validateDate()
    }

    // Визуальная блокировка даты (для стилей)
    const isDateUnavailable = computed(() => {
        const today = new Date().toISOString().split('T')[0]
        if (form.value.date === today) return false
        if (allDatesAllowed.value) return false
        return !checkDateAvailable(form.value.date) && form.value.date !== ''
    })

    // Выбор валюты
    const selectCurrency = async (currency) => {
        form.value.currency_id = currency.id
        await fetchDatesForCurrency(currency.id)
    }

    // Текущая выбранная валюта
    const currentCurrency = computed(() => {
        return getCurrencyById(form.value.currency_id)
    })

    const currentCurrencySymbol = computed(() => {
        if (!currentCurrency.value) return 'Br'
        return getCurrencySymbol(currentCurrency.value.code)
    })

    const filteredCategories = computed(() => {
        return getFilteredCategories(form.value.type)
    })

    const isFormValid = computed(() => {
        return form.value.amount > 0 &&
            form.value.category_ids.length > 0 &&
            form.value.currency_id !== null &&
            form.value.date &&
            !dateError.value
    })

    const validateAmount = () => {
        const amount = parseFloat(form.value.amount)
        if (amount < 0.01) {
            amountError.value = 'Сумма должна быть больше 0'
        } else if (amount > 10000000) {
            amountError.value = 'Слишком большая сумма'
        } else {
            amountError.value = ''
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

    const roundToTwoDecimals = (value) => {
        const num = parseFloat(value)
        if (isNaN(num)) return 0
        return parseFloat(num.toFixed(2))
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
            loading.value = true
            error.value = ''

            const roundedAmount = roundToTwoDecimals(form.value.amount)

            const transactionData = {
                amount: roundedAmount,
                type: form.value.type,
                category_ids: form.value.category_ids,
                currency_id: form.value.currency_id,
                description: form.value.description,
                date: form.value.date,
                payment_method: form.value.payment_method
            }

            await axios.post('/api/transactions', transactionData)

            if (window.showNotification) {
                window.showNotification('success', 'Транзакция успешно добавлена')
            }

            router.push('/transactions')
        } catch (err) {
            console.error('Error creating transaction:', err)

            if (err.response?.data?.errors?.date) {
                error.value = err.response.data.errors.date[0]
            } else {
                error.value = err.response?.data?.message ||
                    err.response?.data?.errors?.category_ids?.[0] ||
                    'Ошибка при создании транзакции'
            }

            window.scrollTo({ top: 0, behavior: 'smooth' })
        } finally {
            loading.value = false
        }
    }

    // Загрузка начальных данных
    const loadInitialData = async () => {
        setDateRange()

        // Загружаем категории
        await fetchCategories()
        categoriesLoaded.value = true

        // Загружаем валюты
        await fetchCurrencies()
        currenciesLoaded.value = true

        // Устанавливаем валюту по умолчанию (BYN)
        const defaultCurrency = currencies.value.find(c => c.code === 'BYN')
        if (defaultCurrency) {
            form.value.currency_id = defaultCurrency.id
            await fetchDatesForCurrency(defaultCurrency.id)
        } else if (currencies.value.length > 0) {
            form.value.currency_id = currencies.value[0].id
            await fetchDatesForCurrency(currencies.value[0].id)
        }
    }

    // Watch для изменения типа транзакции
    watch(() => form.value.type, () => {
        form.value.category_ids = []
    })

    // Watch для изменения даты
    watch(() => form.value.date, () => {
        validateDate()
    })

    return {
        // Состояние
        form,
        loading,
        error,
        amountError,
        dateError,
        currencies,
        paymentMethods,
        minDate,
        maxDate,

        // Вычисляемые
        currentCurrencySymbol,
        filteredCategories,
        isFormValid,
        isDateUnavailable,

        // Методы
        validateAmount,
        toggleCategory,
        submitTransaction,
        selectCurrency,
        setTodayDate,
        validateDate,
        loadInitialData
    }
}