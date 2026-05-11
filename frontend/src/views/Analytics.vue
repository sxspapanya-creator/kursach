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
        <button @click="fetchAnalytics" :disabled="loading" class="refresh-btn">
          {{ loading ? 'Обновление...' : '🔄 Обновить' }}
        </button>
      </div>
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

    <!-- Основные метрики -->
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
            📊 Ликвидность (30%): остаток до зарплаты<br>
            🛡️ Подушка (30%): сбережения ÷ расходы<br>
            💳 Долги (20%): платежи ÷ доход<br>
            💰 Сбережения (20%): остаток после трат<br>
            <span class="tooltip-score">Итог: {{ financialHealth.score }}/100</span>
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

    <!-- Рекомендуемые лимиты -->
    <div class="optimal-distribution-section" v-if="expenseCategoriesDistribution.length > 0">
      <button @click="showOptimalDistribution = !showOptimalDistribution" class="toggle-distribution">
        🎯 {{ showOptimalDistribution ? 'Скрыть' : 'Показать' }} рекомендуемые лимиты по категориям
      </button>
      <div v-if="showOptimalDistribution" class="distribution-table">
        <h3>Рекомендуемые лимиты по категориям расходов</h3>
        <table class="distribution-table">
          <thead>
          <tr>
            <th>Категория</th>
            <th>Текущая средняя</th>
            <th>Рекомендуемый лимит</th>
            <th>Действие</th>
          </tr>
          </thead>
          <tbody>
          <tr v-for="item in expenseCategoriesDistribution" :key="item.category_id">
            <td>{{ item.category_name }}</td>
            <td>{{ formatMoney(item.current_monthly_avg) }}</td>
            <td class="recommended-limit">{{ formatMoney(item.recommended_limit) }}</td>
            <td><button @click="applyOptimalLimit(item)" class="apply-limit-btn">Применить</button></td>
          </tr>
          </tbody>
        </table>
      </div>
    </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'

export default {
  name: 'AnalyticsPage',
  setup() {
    const analytics = ref({ totals: {}, category_spending: [], date_range: {}, financial_health: {}, forecasts: {} })
    const loading = ref(false)
    const selectedPeriod = ref('month')
    const selectedDate = ref(new Date().toISOString().slice(0, 7))
    const showOptimalDistribution = ref(false)
    const showResultModal = ref(false)
    const resultModal = ref({ icon: '', title: '', data: '' })

    // Функция для получения суммы в BYN из объекта (транзакции или категории)
    const getAmountInByn = (item) => {
      if (!item) return 0

      // Если уже есть amount_in_byn
      if (item.amount_in_byn !== undefined && item.amount_in_byn !== null) {
        return parseFloat(item.amount_in_byn) || 0
      }

      // Если есть total_in_byn (для категорий)
      if (item.total_in_byn !== undefined && item.total_in_byn !== null) {
        return parseFloat(item.total_in_byn) || 0
      }

      // Если есть total и exchange_rate
      if (item.exchange_rate && item.total) {
        return (parseFloat(item.total) || 0) * parseFloat(item.exchange_rate)
      }

      // Если есть amount и exchange_rate
      if (item.exchange_rate && item.amount) {
        return (parseFloat(item.amount) || 0) * parseFloat(item.exchange_rate)
      }

      // Если нет курса - считаем что это BYN
      if (item.total !== undefined && item.total !== null) {
        return parseFloat(item.total) || 0
      }

      if (item.amount !== undefined && item.amount !== null) {
        return parseFloat(item.amount) || 0
      }

      return 0
    }

    // Обработанные итоги (пересчитанные в BYN)
    const processedTotals = computed(() => {
      const totals = analytics.value.totals || {}

      let income = getAmountInByn(totals)
      let expenses = 0
      let balance = 0
      let savings_rate = 0

      // Если totals это объект с разными полями
      if (totals.income !== undefined) {
        income = getAmountInByn({ total: totals.income })
      }
      if (totals.expenses !== undefined) {
        expenses = getAmountInByn({ total: totals.expenses })
      }
      if (totals.balance !== undefined) {
        balance = getAmountInByn({ total: totals.balance })
      } else {
        balance = income - expenses
      }
      if (totals.savings_rate !== undefined) {
        savings_rate = totals.savings_rate
      } else if (income > 0) {
        savings_rate = (balance / income) * 100
      }

      return { income, expenses, balance, savings_rate }
    })

    // Обработанные распределения для лимитов
    const expenseCategoriesDistribution = computed(() => {
      const distribution = analytics.value.forecasts?.optimal_distribution || []
      return distribution
          .filter(item => item.category_name && !item.category_name.toLowerCase().includes('доход'))
          .map(item => ({
            ...item,
            current_monthly_avg: getAmountInByn({ total: item.current_monthly_avg }),
            recommended_limit: getAmountInByn({ total: item.recommended_limit })
          }))
    })

    const financialHealth = computed(() => analytics.value.financial_health || {
      score: 0,
      status: 'poor',
      status_label: 'Не определено',
      color: '#95a5a6'
    })

    const balanceClass = computed(() => {
      const balance = processedTotals.value.balance
      if (balance > 0) return 'positive'
      if (balance < 0) return 'negative'
      return 'neutral'
    })

    const fetchAnalytics = async () => {
      try {
        loading.value = true
        const [year, month] = selectedDate.value.split('-')
        const res = await axios.get('/api/analytics/overview', {
          params: {
            period: selectedPeriod.value,
            month: parseInt(month),
            year: parseInt(year)
          },
          credentials: 'include'
        })

        if (res.data.status === 'success') {
          const data = res.data.data || {}

          // Оставляем сырые данные, computed свойства пересчитают их в BYN
          analytics.value = {
            totals: data.totals || {},
            category_spending: data.category_spending || [],
            date_range: data.date_range || {},
            financial_health: data.financial_health || {},
            forecasts: data.forecasts || {}
          }
        }
      } catch (err) {
        console.error('Analytics fetch error:', err)
      } finally {
        loading.value = false
      }
    }

    const formatMoney = (amount) => {
      const num = Number(amount)
      if (isNaN(num)) return '0 Br'
      return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
      }).format(num) + ' Br'
    }

    onMounted(() => {
      fetchAnalytics()
    })

    watch([selectedPeriod, selectedDate], () => {
      if (!loading.value) fetchAnalytics()
    })

    return {
      analytics,
      loading,
      selectedPeriod,
      selectedDate,
      showOptimalDistribution,
      showResultModal,
      resultModal,
      balanceClass,
      financialHealth,
      processedTotals,
      expenseCategoriesDistribution,
      fetchAnalytics,
      formatMoney,
    }
  }
}
</script>

<style scoped>
@import '../css/analytics.css';
</style>