<template>
  <div class="analytics-page">
    <!-- Заголовок и управление периодом -->
    <div class="header">
      <h1>📊 Аналитика бюджета</h1>
      <div class="period-controls">
        <select v-model="selectedPeriod">
          <option value="month">Месяц</option>
          <option value="year">Год</option>
        </select>
        <input v-model="selectedDate" type="month" @change="fetchAnalytics">
        <label class="anomaly-toggle">
          <input type="checkbox" v-model="includeAnomalies" @change="fetchAnalytics">
          <span>📌 Включить разовые траты</span>
        </label>
        <button @click="fetchAnalytics" :disabled="loading" class="refresh-btn">
          {{ loading ? 'Обновление...' : '🔄 Обновить' }}
        </button>
      </div>
    </div>

    <!-- Модальное окно для результатов -->
    <div v-if="showResultModal" class="modal-overlay" @click="showResultModal = false">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3 class="modal-title">
            <span class="modal-icon">{{ resultModal.icon }}</span>
            {{ resultModal.title }}
          </h3>
          <button @click="showResultModal = false" class="modal-close">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
          </button>
        </div>
        <div class="modal-body">
          <pre class="result-json">{{ resultModal.data }}</pre>
        </div>
        <div class="modal-footer">
          <button @click="showResultModal = false" class="btn btn-secondary">Закрыть</button>
        </div>
      </div>
    </div>

    <div class="metrics-grid">
      <div class="metric-card health-card" :style="{ borderColor: financialHealth.color }">
        <div class="metric-tooltip">
          <div class="metric-icon">❤️</div>
          <div class="metric-content">
            <h3>Финансовое здоровье</h3>
            <div class="metric-value">{{ financialHealth.score }}/100</div>
            <div class="metric-label">{{ financialHealth.status_label }}</div>
            <div class="health-progress">
              <div class="progress-bar" :style="{ width: financialHealth.score + '%', backgroundColor: financialHealth.color }"></div>
            </div>
          </div>
          <div class="tooltip-text">
            <strong>Как рассчитано?</strong><br>
            📊 Ликвидность (30%): {{ financialHealth.components?.liquidity?.score || 0 }}%<br>
            <span class="tooltip-sub">Остаток до зарплаты: {{ formatMoney(financialHealth.components?.liquidity?.balance) }}</span><br>
            🛡️ Подушка (30%): {{ financialHealth.components?.emergency_fund?.score || 0 }}%<br>
            <span class="tooltip-sub">Сбережения: {{ formatMoney(financialHealth.components?.emergency_fund?.savings) }}</span><br>
            💳 Долги (20%): {{ financialHealth.components?.debt_load?.score || 0 }}%<br>
            <span class="tooltip-sub">Платежи: {{ formatMoney(financialHealth.components?.debt_load?.monthly_payments) }}</span><br>
            💰 Сбережения (20%): {{ financialHealth.components?.savings_rate?.score || 0 }}%<br>
            <span class="tooltip-sub">Сэкономлено: {{ formatMoney(financialHealth.components?.savings_rate?.saved_amount) }}</span><br>
            <span class="tooltip-score">⭐ Итог: {{ financialHealth.score }}/100</span>
          </div>
        </div>
      </div>

      <div class="metric-card income-card">
        <div class="metric-icon">📈</div>
        <div class="metric-content">
          <h3>Доходы</h3>
          <div class="metric-value">{{ formatMoney(processedTotals.income) }}</div>
          <div class="metric-label">{{ analytics.date_range?.label || 'За период' }}</div>
        </div>
      </div>

      <div class="metric-card expense-card">
        <div class="metric-icon">📉</div>
        <div class="metric-content">
          <h3>Расходы</h3>
          <div class="metric-value">{{ formatMoney(processedTotals.expenses) }}</div>
          <div class="metric-label">{{ analytics.date_range?.label || 'За период' }}</div>
        </div>
      </div>

      <div class="metric-card balance-card" :class="balanceClass">
        <div class="metric-icon">⚖️</div>
        <div class="metric-content">
          <h3>Баланс</h3>
          <div class="metric-value">{{ formatMoney(processedTotals.balance) }}</div>
          <div class="metric-label">чистый остаток</div>
        </div>
      </div>

      <div class="metric-card savings-card">
        <div class="metric-icon">💰</div>
        <div class="metric-content">
          <h3>Норма сбережений</h3>
          <div class="metric-value">{{ processedTotals.savings_rate?.toFixed(1) || '0' }}%</div>
          <div class="metric-label">от дохода</div>
          <div class="savings-progress">
            <div class="progress-bar" :style="{ width: Math.min(processedTotals.savings_rate || 0, 100) + '%' }"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Расходы по категориям -->
    <div class="categories-section">
      <div class="section-header">
        <h2>📂 Расходы по категориям</h2>
        <span class="total-expenses">Всего: {{ formatMoney(processedTotals.expenses) }}</span>
      </div>
      <div class="categories-grid">
        <div v-for="cat in categorySpending" :key="cat.id" class="category-card" :style="{ borderLeftColor: cat.color }">
          <div class="category-header">
            <span class="category-name">{{ cat.name }}</span>
            <span class="category-amount">{{ formatMoney(cat.total) }}</span>
          </div>
          <div class="category-progress">
            <div class="progress-bar" :style="{ width: getCategoryPercent(cat.total) + '%', backgroundColor: cat.color }"></div>
          </div>
          <div class="category-footer">
            <span class="category-percent">{{ getCategoryPercent(cat.total) }}%</span>
            <span v-if="cat.budget_limit > 0" class="category-limit" :class="getBudgetStatusClass(cat)">
              Лимит: {{ formatMoney(cat.budget_limit) }}
              <span class="limit-indicator" :class="cat.budget_status">●</span>
            </span>
            <span v-else class="category-limit no-limit">Лимит не установлен</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Прогноз (если есть) -->
    <div v-if="forecastData" class="forecast-section">
      <div class="section-header">
        <h2>🔮 Прогноз расходов</h2>
        <div class="forecast-badge">
          <span class="model-badge">{{ forecastData.model }}</span>
          <span class="confidence-badge" :class="forecastData.confidence_level">
            {{ forecastData.confidence_text }} ({{ forecastData.confidence }}%)
          </span>
        </div>
      </div>

      <div class="forecast-grid">
        <!-- Остаток текущего месяца -->
        <div class="forecast-card remaining">
          <h3>📅 Остаток {{ currentMonthName }}</h3>
          <div class="forecast-amount">{{ formatMoney(forecastData.remaining_current_month?.forecast_total) }}</div>
          <div class="forecast-detail">
            Уже потрачено: {{ formatMoney(forecastData.remaining_current_month?.already_spent) }}
          </div>
          <div class="forecast-detail">
            Средний расход в день: {{ formatMoney(forecastData.remaining_current_month?.weighted_daily_rate) }}
          </div>
          <div class="forecast-detail">
            Осталось дней: {{ forecastData.remaining_current_month?.days_left }}
          </div>
          <div class="forecast-full-month">
            Весь месяц: {{ formatMoney(forecastData.remaining_current_month?.forecast_full_month) }}
          </div>
        </div>

        <!-- Следующий месяц -->
        <div class="forecast-card next">
          <h3>📆 {{ forecastData.next_month?.month }}</h3>
          <div class="forecast-amount">{{ formatMoney(forecastData.next_month?.total_forecast) }}</div>
          <div class="forecast-detail">
            Средний расход: {{ formatMoney(forecastData.next_month?.daily_average) }}/день
          </div>
          <div class="forecast-detail">
            Изменение: {{ formatChange(forecastData.next_month?.change_from_previous) }}
          </div>
          <div class="forecast-trend" :class="forecastData.next_month?.trend">
            {{ getTrendIcon(forecastData.next_month?.trend) }} {{ getTrendText(forecastData.next_month?.trend) }}
          </div>
        </div>

        <!-- Второй месяц -->
        <div class="forecast-card second">
          <h3>📆 {{ forecastData.second_month?.month }}</h3>
          <div class="forecast-amount">{{ formatMoney(forecastData.second_month?.total_forecast) }}</div>
          <div class="forecast-detail">
            Средний расход: {{ formatMoney(forecastData.second_month?.daily_average) }}/день
          </div>
          <div class="forecast-detail">
            Изменение: {{ formatChange(forecastData.second_month?.change_from_previous) }}
          </div>
          <div class="forecast-trend" :class="forecastData.second_month?.trend">
            {{ getTrendIcon(forecastData.second_month?.trend) }} {{ getTrendText(forecastData.second_month?.trend) }}
          </div>
        </div>
      </div>

      <!-- Подневный прогноз -->
      <div v-if="forecastData.remaining_current_month?.daily_breakdown?.length" class="daily-forecast">
        <h3>📊 Прогноз по дням</h3>
        <div class="daily-grid">
          <div v-for="day in forecastData.remaining_current_month.daily_breakdown.slice(0, 14)" :key="day.date" class="daily-card">
            <div class="daily-date">{{ formatDate(day.date) }}</div>
            <div class="daily-day">{{ day.day_of_week }}</div>
            <div class="daily-amount">{{ formatMoney(day.forecast) }}</div>
          </div>
        </div>
      </div>

      <!-- Прогноз по категориям -->
      <div v-if="forecastData.category_forecasts?.length" class="category-forecast">
        <h3>📈 Прогноз по категориям на {{ forecastData.next_month?.month || 'следующий месяц' }}</h3>
        <div class="category-forecast-grid">
          <div v-for="cat in forecastData.category_forecasts" :key="cat.category_id" class="category-forecast-card">
            <div class="cat-color" :style="{ backgroundColor: cat.color }"></div>
            <div class="cat-info">
              <div class="cat-name">{{ cat.category_name }}</div>
              <div class="cat-share">{{ cat.share_percent }}% от общего бюджета</div>
            </div>
            <div class="cat-forecast">{{ formatMoney(cat.forecast) }}</div>
            <div class="cat-daily">{{ formatMoney(cat.daily_average) }}/день</div>
            <!-- Рекомендация по лимиту (если есть) -->
            <div v-if="cat.recommendation" class="cat-recommendation" :class="cat.recommendation.action">
              <span v-if="cat.recommendation.action === 'set'">➕ Рекомендуемый лимит: {{ formatMoney(cat.recommendation.recommended_limit) }}</span>
              <span v-else-if="cat.recommendation.action === 'increase'">📈 Увеличить лимит до {{ formatMoney(cat.recommendation.recommended_limit) }}</span>
              <span v-else-if="cat.recommendation.action === 'decrease'">📉 Уменьшить лимит до {{ formatMoney(cat.recommendation.recommended_limit) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Информация об аномалиях -->
      <div v-if="forecastData.excluded_anomalies?.total_count > 0" class="anomalies-info">
        <div class="anomalies-icon">⚠️</div>
        <div class="anomalies-text">
          {{ forecastData.excluded_anomalies.message }}<br>
          <small>Сумма исключённых транзакций: {{ formatMoney(forecastData.excluded_anomalies.total_amount) }}</small>
        </div>
      </div>
    </div>

    <!-- Информация об аномалиях в обзоре -->
    <div v-if="analytics.anomalies_info?.count > 0 && !includeAnomalies" class="anomalies-banner">
      <span>⚠️</span>
      <span>{{ analytics.anomalies_info.message }}</span>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'

export default {
  name: 'AnalyticsPage',
  setup() {
    const analytics = ref({
      totals: {},
      category_spending: [],
      date_range: {},
      financial_health: {},
      anomalies_info: {}
    })
    const forecastData = ref(null)
    const loading = ref(false)
    const selectedPeriod = ref('month')
    const selectedDate = ref(new Date().toISOString().slice(0, 7))
    const includeAnomalies = ref(false)
    const showResultModal = ref(false)
    const resultModal = ref({ icon: '', title: '', data: '' })

    // ==================== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ====================

    const formatMoney = (amount) => {
      const num = Number(amount)
      if (isNaN(num)) return '0 Br'
      return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
      }).format(num) + ' Br'
    }

    const formatDate = (dateStr) => {
      const [year, month, day] = dateStr.split('-')
      return `${day}.${month}`
    }

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

    const getBudgetStatusClass = (category) => {
      if (category.budget_status === 'good') return 'status-good'
      if (category.budget_status === 'warning') return 'status-warning'
      if (category.budget_status === 'critical') return 'status-critical'
      return ''
    }

    // ==================== ВЫЧИСЛЯЕМЫЕ СВОЙСТВА ====================

    const processedTotals = computed(() => {
      const totals = analytics.value.totals || {}
      return {
        income: totals.income || 0,
        expenses: totals.expenses || 0,
        balance: totals.balance || 0,
        savings_rate: totals.savings_rate || 0
      }
    })

    const categorySpending = computed(() => {
      return analytics.value.category_spending || []
    })

    const financialHealth = computed(() => {
      return analytics.value.financial_health || {
        score: 0,
        status: 'poor',
        status_label: 'Не определено',
        color: '#95a5a6',
        components: {}
      }
    })

    const balanceClass = computed(() => {
      const balance = processedTotals.value.balance
      if (balance > 0) return 'positive'
      if (balance < 0) return 'negative'
      return 'neutral'
    })

    const currentMonthName = computed(() => {
      const now = new Date()
      return now.toLocaleString('ru', { month: 'long', year: 'numeric' })
    })

    const totalExpensesSum = computed(() => {
      return categorySpending.value.reduce((sum, cat) => sum + (cat.total || 0), 0)
    })

    // ==================== МЕТОДЫ ====================

    const getCategoryPercent = (amount) => {
      if (totalExpensesSum.value === 0) return 0
      return Math.round((amount / totalExpensesSum.value) * 100)
    }

    const fetchAnalytics = async () => {
      try {
        loading.value = true
        const [year, month] = selectedDate.value.split('-')

        // Запрос обзора
        const overviewRes = await axios.get('/api/analytics/overview', {
          params: {
            period: selectedPeriod.value,
            month: parseInt(month),
            year: parseInt(year),
            include_anomalies: includeAnomalies.value
          },
          credentials: 'include'
        })

        if (overviewRes.data.status === 'success') {
          analytics.value = overviewRes.data.data || {}
        }

        // Запрос прогноза
        const forecastRes = await axios.get('/api/forecast', {
          credentials: 'include'
        })

        if (forecastRes.data.status === 'success') {
          forecastData.value = forecastRes.data.data
        }

      } catch (err) {
        console.error('Analytics fetch error:', err)
        if (err.response?.data?.message) {
          resultModal.value = {
            icon: '❌',
            title: 'Ошибка',
            data: err.response.data.message
          }
          showResultModal.value = true
        }
      } finally {
        loading.value = false
      }
    }

    onMounted(() => {
      fetchAnalytics()
    })

    watch([selectedPeriod, selectedDate, includeAnomalies], () => {
      if (!loading.value) fetchAnalytics()
    })

    return {
      analytics,
      forecastData,
      loading,
      selectedPeriod,
      selectedDate,
      includeAnomalies,
      showResultModal,
      resultModal,
      processedTotals,
      categorySpending,
      financialHealth,
      balanceClass,
      currentMonthName,
      formatMoney,
      formatDate,
      formatChange,
      getTrendIcon,
      getTrendText,
      getBudgetStatusClass,
      getCategoryPercent,
      fetchAnalytics
    }
  }
}
</script>

<style scoped>
@import '../css/analytics.css';
</style>