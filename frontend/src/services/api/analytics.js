import axios from 'axios'

export const analyticsApi = {
    // Получить обзор аналитики
    getOverview(period, year, month, includeAnomalies = false) {
        return axios.get('/api/analytics/overview', {
            params: { period, year, month, include_anomalies: includeAnomalies }
        })
    },

    // Получить прогноз
    getForecast() {
        return axios.get('/api/forecast')
    },

    // Получить месячные тренды
    getMonthlyTrends(months = 12) {
        return axios.get('/api/analytics/monthly-trends', { params: { months } })
    },

    // Массовое обновление аномалий
    batchMarkAnomalies(anomalies) {
        return axios.post('/api/analytics/batch-mark-anomalies', { anomalies })
    }
}