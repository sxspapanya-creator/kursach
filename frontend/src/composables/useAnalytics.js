import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'
import { useCurrencies } from './useCurrencies'
import { useDateFormatter } from './useDateFormatter'

export function useAnalytics() {
    const { formatMoneyAmount, formatRate } = useCurrencies()
    const { formatDate: formatDateSimple } = useDateFormatter()

    const analytics = ref({ totals: {}, category_spending: [], date_range: {}, financial_health: {}, anomalies_info: {} })
    const forecastData = ref(null)
    const detectedAnomalies = ref([])
    const loading = ref(false)
    const applyingChanges = ref(false)
    const selectedPeriod = ref('month')
    const selectedDate = ref(new Date().toISOString().slice(0, 7))
    const selectedYear = ref(new Date().getFullYear())
    const includeAnomalies = ref(false)
    const showResultModal = ref(false)
    const resultModal = ref({ icon: '', title: '', data: '' })
    const localAnomalyChanges = ref(new Map())

    const formatMoney = (amount) => {
        const num = Number(amount)
        if (isNaN(num)) return '0 Br'
        return new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(num) + ' Br'
    }

    const formatDate = (dateStr) => {
        if (!dateStr) return '—'
        return formatDateSimple(dateStr)
    }

    const formatDay = (dateStr) => new Date(dateStr).getDate()

    const formatChange = (percent) => {
        if (!percent || percent === 0) return '0%'
        const sign = percent > 0 ? '+' : ''
        return `${sign}${percent}%`
    }

    const getTrendIcon = (trend) => {
        if (trend === 'growth') return '📈'
        if (trend === 'decline') return '📉'
        return '📊'
    }

    const getTrendText = (trend) => {
        if (trend === 'growth') return 'Рост'
        if (trend === 'decline') return 'Снижение'
        return 'Стабильно'
    }

    const getPaymentMethodText = (method) => {
        const methods = { cash: 'Наличные', card: 'Карта', transfer: 'Перевод' }
        return methods[method] || method || '—'
    }

    const getRussianMethodName = (method) => {
        const names = {
            'SimpleExtrapolation': 'Линейная экстраполяция',
            'LinearRegression': 'Линейная регрессия',
            'DoubleExponentialSmoothing': 'Двойное сглаживание',
            'HoltWinters': 'Хольта-Уинтерса'
        }
        return names[method] || method
    }

    const formatExchangeRate = (rate) => formatRate(rate)

    const getMapeClass = (mape) => {
        if (mape < 10) return 'excellent'
        if (mape < 20) return 'good'
        if (mape < 30) return 'normal'
        return 'bad'
    }

    const getMapeDesc = (mape) => {
        if (mape < 10) return 'отличная точность'
        if (mape < 20) return 'хорошая точность'
        if (mape < 30) return 'приемлемая точность'
        return 'низкая точность'
    }

    const getCvClass = (cvPercent) => {
        if (cvPercent === null || cvPercent === undefined) return 'bad'
        if (cvPercent < 15) return 'excellent'
        if (cvPercent < 30) return 'good'
        if (cvPercent < 50) return 'normal'
        return 'bad'
    }

    const getShortDay = (dayOfWeek) => {
        const short = { 'Понедельник': 'Пн', 'Вторник': 'Вт', 'Среда': 'Ср', 'Четверг': 'Чт', 'Пятница': 'Пт', 'Суббота': 'Сб', 'Воскресенье': 'Вс' }
        return short[dayOfWeek] || dayOfWeek?.slice(0, 2)
    }

    const getMethodDescription = (method) => {
        const descriptions = {
            'SimpleExtrapolation': `
    <strong>Линейная экстраполяция</strong><br><br>
    <strong>Как работает?</strong><br>
    1. Берет <strong>первый завершенный месяц</strong> и <strong>последний завершенный месяц</strong> из истории<br>
    2. Считает, на сколько в среднем менялись расходы за месяц<br>
    3. Ограничивает изменение (не больше +20% и не меньше -15% за месяц)<br>
    4. Продлевает (экстраполирует) этот тренд на будущие месяцы<br><br>
    <strong>Важные ограничения:</strong><br>
    • Не учитывает сезонные колебания<br>
    • Работает только для краткосрочного прогноза (1-2 месяца)<br>
  `,
            'LinearRegression': `
    <strong>Линейная регрессия</strong><br><br>
    <strong>Как работает?</strong><br>
    1. Строит оптимальную прямую линию тренда через ВСЕ точки данных<br>
    2. Использует <strong>метод наименьших квадратов</strong><br>
    3. Сглаживает случайные колебания в данных<br>
    4. Продлевает этот тренд на будущие месяцы<br><br>
    <strong>Важные ограничения:</strong><br>
    • Не учитывает сезонные колебания<br>
  `,
            'DoubleExponentialSmoothing': `
    <strong>Двойное экспоненциальное сглаживание (Метод Хольта)</strong><br><br>
    <strong>Как работает?</strong><br>
    1. Отслеживает два компонента: <strong>уровень</strong> и <strong>тренд</strong><br>
    2. Каждый месяц обновляет оба компонента с учетом новых данных<br>
    3. Чем свежее данные, тем больше их влияние<br>
    4. Прогноз = текущий уровень + тренд × количество шагов<br><br>
    <strong>Преимущества:</strong><br>
    • Адаптируется к изменению тренда<br>
    • Лучше работает при волатильных данных<br>
  `,
            'HoltWinters': `
    <strong>Тройное экспоненциальное сглаживание (Хольта-Уинтерса)</strong><br><br>
    <strong>Как работает?</strong><br>
    1. Отслеживает <strong>три компонента</strong>: уровень, тренд и сезонность<br>
    2. Сезонный период = <strong>12 месяцев</strong> (годовая сезонность)<br>
    3. Прогноз = (уровень + тренд × шаг) × сезонный коэффициент<br><br>
    <strong>Преимущества:</strong><br>
    • Учитывает сезонные колебания<br>
    • Самый точный метод при наличии сезонности<br>
  `
        }
        return descriptions[method] || `
    <strong>Метод прогнозирования</strong><br>
    Алгоритм автоматически выбирает оптимальную стратегию на основе:<br>
    • Количества месяцев данных<br>
    • Стабильности расходов<br>
    • Наличия тренда или сезонности
  `
    }

    const getLocalAnomalyStatus = (anomaly) => {
        if (localAnomalyChanges.value.has(anomaly.id)) return localAnomalyChanges.value.get(anomaly.id)
        return anomaly.is_anomaly || false
    }

    const hasLocalChange = (anomaly) => localAnomalyChanges.value.has(anomaly.id)

    const toggleLocalAnomalyStatus = (anomaly) => {
        const currentStatus = getLocalAnomalyStatus(anomaly)
        localAnomalyChanges.value.set(anomaly.id, !currentStatus)
    }

    const hasLocalChanges = computed(() => localAnomalyChanges.value.size > 0)

    const localAnomaliesTotalAmount = computed(() =>
        detectedAnomalies.value
            .filter(a => getLocalAnomalyStatus(a))
            .reduce((sum, a) => sum + (a.amount_in_byn || a.amount || 0), 0)
    )

    const resetLocalChanges = () => localAnomalyChanges.value.clear()

    const applyAnomalyChanges = async () => {
        if (localAnomalyChanges.value.size === 0) return
        applyingChanges.value = true
        try {
            const changes = []
            for (const [id, newStatus] of localAnomalyChanges.value.entries()) {
                const anomaly = detectedAnomalies.value.find(a => a.id === parseInt(id))
                if (anomaly && anomaly.is_anomaly !== newStatus) {
                    changes.push({ id: parseInt(id), is_anomaly: newStatus })
                }
            }
            if (changes.length === 0) { resetLocalChanges(); return }
            await axios.post('/api/transactions/mark-anomalies', { anomalies: changes }, { credentials: 'include' })
            for (const change of changes) {
                const anomaly = detectedAnomalies.value.find(a => a.id === change.id)
                if (anomaly) anomaly.is_anomaly = change.is_anomaly
            }
            resetLocalChanges()
            await fetchAnalytics()
        } catch (err) {
            console.error('Failed to update anomalies:', err)
            alert('Не удалось сохранить изменения')
        } finally {
            applyingChanges.value = false
        }
    }

    const processedTotals = computed(() => {
        const totals = analytics.value.totals || {}
        return {
            income: totals.income || 0,
            expenses: totals.expenses || 0,
            balance: totals.balance || 0,
            savings_rate: totals.savings_rate || 0
        }
    })

    const categorySpending = computed(() => analytics.value.category_spending || [])

    const financialHealth = computed(() => analytics.value.financial_health || {
        score: 0,
        status: 'poor',
        status_label: 'Не определено',
        color: '#95a5a6',
        components: {}
    })

    const balanceClass = computed(() => {
        const b = processedTotals.value.balance
        if (b > 0) return 'positive'
        if (b < 0) return 'negative'
        return 'neutral'
    })

    const currentMonthName = computed(() =>
        new Date().toLocaleString('ru', { month: 'long', year: 'numeric' })
    )

    const periodLabel = computed(() => {
        if (selectedPeriod.value === 'month') {
            return new Date(selectedDate.value).toLocaleString('ru', { month: 'long', year: 'numeric' })
        }
        return `${selectedYear.value} год`
    })

    const totalExpensesSum = computed(() =>
        categorySpending.value.reduce((sum, cat) => sum + (cat.total || 0), 0)
    )

    const maxDailyForecast = computed(() => {
        if (!forecastData.value?.remaining_current_month?.daily_breakdown?.length) return 0
        return Math.max(...forecastData.value.remaining_current_month.daily_breakdown.map(d => d.forecast), 1)
    })

    const getBarHeight = (forecast, maxForecast) => {
        if (maxForecast === 0) return 0
        const minHeight = 50, maxHeight = 160
        return minHeight + (maxHeight - minHeight) * (forecast / maxForecast)
    }

    const getCategoryPercent = (amount) => {
        if (totalExpensesSum.value === 0) return 0
        return Math.round((amount / totalExpensesSum.value) * 100)
    }

    const fetchAnalytics = async () => {
        try {
            loading.value = true
            let year, month
            if (selectedPeriod.value === 'month') {
                [year, month] = selectedDate.value.split('-')
            } else {
                year = selectedYear.value
            }

            const overviewRes = await axios.get('/api/analytics/overview', {
                params: {
                    period: selectedPeriod.value,
                    ...(month && { month: parseInt(month) }),
                    year: parseInt(year),
                    include_anomalies: includeAnomalies.value
                },
                credentials: 'include'
            })

            if (overviewRes.data.status === 'success') {
                analytics.value = overviewRes.data.data || {}
            }

            const forecastRes = await axios.get('/api/forecast', { credentials: 'include' })
            if (forecastRes.data.status === 'success') {
                forecastData.value = forecastRes.data.data
                detectedAnomalies.value = forecastRes.data.data.anomalies_list || []
            }

            resetLocalChanges()
        } catch (err) {
            console.error('Analytics fetch error:', err)
            if (err.response?.data?.message) {
                resultModal.value = { icon: '❌', title: 'Ошибка', data: err.response.data.message }
                showResultModal.value = true
            }
        } finally {
            loading.value = false
        }
    }

    const resetFilters = () => {
        selectedPeriod.value = 'month'
        selectedDate.value = new Date().toISOString().slice(0, 7)
        selectedYear.value = new Date().getFullYear()
        fetchAnalytics()
    }

    onMounted(() => fetchAnalytics())

    watch([selectedPeriod, selectedDate, selectedYear, includeAnomalies], () => {
        if (!loading.value) fetchAnalytics()
    })

    return {
        analytics,
        forecastData,
        detectedAnomalies,
        loading,
        applyingChanges,
        selectedPeriod,
        selectedDate,
        selectedYear,
        includeAnomalies,
        showResultModal,
        resultModal,
        processedTotals,
        categorySpending,
        financialHealth,
        balanceClass,
        currentMonthName,
        periodLabel,
        localAnomaliesTotalAmount,
        hasLocalChanges,
        maxDailyForecast,
        formatMoney,
        formatMoneyAmount,
        formatExchangeRate,
        formatDate,
        formatDay,
        formatChange,
        getTrendIcon,
        getTrendText,
        getPaymentMethodText,
        getRussianMethodName,
        getMapeClass,
        getMapeDesc,
        getCvClass,
        getShortDay,
        getMethodDescription,
        getBarHeight,
        getCategoryPercent,
        getLocalAnomalyStatus,
        hasLocalChange,
        toggleLocalAnomalyStatus,
        resetLocalChanges,
        applyAnomalyChanges,
        fetchAnalytics,
        resetFilters
    }
}