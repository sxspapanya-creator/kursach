import axios from 'axios'

const API_URL = '/api/transactions'

export const transactionApi = {
    // Получить список транзакций
    getTransactions(params = {}) {
        return axios.get(API_URL, { params })
    },

    // Получить последние транзакции
    getRecentTransactions(limit = 10, includeAnomalies = false) {
        return axios.get(`${API_URL}/recent`, {
            params: { limit, include_anomalies: includeAnomalies ? 'true' : 'false' }
        })
    },

    // Получить сводку по месяцу
    getSummary(month, year, excludeAnomalies = true) {
        return axios.get(`${API_URL}/summary`, {
            params: { month, year, exclude_anomalies: excludeAnomalies }
        })
    },

    // Получить одну транзакцию
    getTransaction(id) {
        return axios.get(`${API_URL}/${id}`)
    },

    // Создать транзакцию
    createTransaction(data) {
        return axios.post(API_URL, data)
    },

    // Обновить транзакцию
    updateTransaction(id, data) {
        return axios.put(`${API_URL}/${id}`, data)
    },

    // Удалить транзакцию
    deleteTransaction(id) {
        return axios.delete(`${API_URL}/${id}`)
    },

    // Массовое удаление
    massDeleteTransactions(ids) {
        return axios.post(`${API_URL}/mass-delete`, { transaction_ids: ids })
    },

    // Отметить как аномалию
    markAsAnomaly(id, isAnomaly, reason = null) {
        return axios.post(`${API_URL}/${id}/mark-anomaly`, { is_anomaly: isAnomaly, reason })
    },

    // Получить аномальные транзакции
    getAnomalies(startDate = null, endDate = null) {
        return axios.get(`${API_URL}/anomalies`, {
            params: { start_date: startDate, end_date: endDate }
        })
    },

    // Массовое обновление аномалий
    batchMarkAnomalies(anomalies) {
        return axios.post(`${API_URL}/batch-mark-anomalies`, { anomalies })
    }
}