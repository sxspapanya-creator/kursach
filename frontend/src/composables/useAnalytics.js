import { ref, computed } from 'vue'
import { analyticsApi } from '../services/api/analytics'
import { formatMoneyWithCurrency, formatMoneyAmount } from '../utils/money'

export function useAnalytics() {
    const analytics = ref({
        totals: {},
        category_spending: [],
        date_range: {},
        financial_health: {}
    })
    const forecastData = ref(null)
    const detectedAnomalies = ref([])
    const loading = ref(false)

    const selectedPeriod = ref('month')
    const selectedDate = ref(new Date().toISOString().slice(0, 7))
    const selectedYear = ref(new Date().getFullYear())

    // Локальные изменения аномалий
    const localAnomalyChanges = ref(new Map())

    // Получить аналитику
    const fetchAnalytics = async () => {
        try {
            loading.value = true

            let year, month
            if (selectedPeriod.value === 'month') {
                [year, month] = selectedDate.value.split('-')
            } else {
                year = selectedYear.value
                month = null
            }

            const overviewRes = await analyticsApi.getOverview(
                selectedPeriod.value,
                parseInt(year),
                month ? parseInt(month) : null,
                false
            )

            if (overviewRes.data.status === 'success') {
                analytics.value = overviewRes.data.data || {}
            }

            const forecastRes = await analyticsApi.getForecast()
            if (forecastRes.data.status === 'success') {
                forecastData.value = forecastRes.data.data
                detectedAnomalies.value = forecastRes.data.data.anomalies_list || []
            }

            localAnomalyChanges.value.clear()
        } catch (err) {
            console.error('Analytics fetch error:', err)
        } finally {
            loading.value = false
        }
    }

    // Получить статус аномалии (с учетом локальных изменений)
    const getLocalAnomalyStatus = (anomaly) => {
        if (localAnomalyChanges.value.has(anomaly.id)) {
            return localAnomalyChanges.value.get(anomaly.id)
        }
        return anomaly.is_anomaly || false
    }

    // Переключить статус аномалии
    const toggleLocalAnomalyStatus = (anomaly) => {
        const currentStatus = getLocalAnomalyStatus(anomaly)
        localAnomalyChanges.value.set(anomaly.id, !currentStatus)
    }

    // Применить изменения аномалий
    const applyAnomalyChanges = async () => {
        if (localAnomalyChanges.value.size === 0) return

        const changes = []
        for (const [id, newStatus] of localAnomalyChanges.value.entries()) {
            const anomaly = detectedAnomalies.value.find(a => a.id === parseInt(id))
            if (anomaly && anomaly.is_anomaly !== newStatus) {
                changes.push({ id: parseInt(id), is_anomaly: newStatus })
            }
        }

        if (changes.length === 0) return

        try {
            await analyticsApi.batchMarkAnomalies(changes)
            for (const change of changes) {
                const anomaly = detectedAnomalies.value.find(a => a.id === change.id)
                if (anomaly) anomaly.is_anomaly = change.is_anomaly
            }
            localAnomalyChanges.value.clear()
            await fetchAnalytics()
        } catch (err) {
            console.error('Failed to update anomalies:', err)
            alert('Не удалось сохранить изменения')
        }
    }

    // Вычисляемые свойства
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

    return {
        analytics,
        forecastData,
        detectedAnomalies,
        loading,
        selectedPeriod,
        selectedDate,
        selectedYear,
        localAnomalyChanges,
        processedTotals,
        categorySpending,
        financialHealth,
        fetchAnalytics,
        getLocalAnomalyStatus,
        toggleLocalAnomalyStatus,
        applyAnomalyChanges
    }
}