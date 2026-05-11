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

    <!-- Кнопки методов бюджетирования -->
    <div class="budget-methods-bar">
      <button @click="calculate503020" :disabled="calcLoading" class="method-btn method-503020">
        <span class="method-icon">📐</span>
        Правило 50/30/20
      </button>
      <button @click="calculate6040" :disabled="calcLoading" class="method-btn method-6040">
        <span class="method-icon">⚖️</span>
        Метод 60/40
      </button>
      <button @click="calculateFourEnvelopes" :disabled="calcLoading" class="method-btn method-envelopes">
        <span class="method-icon">📬</span>
        4 конверта
      </button>
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

    <!-- Расходы по категориям -->
    <div class="category-analysis" v-if="processedCategorySpending.length">
      <div class="section-header">
        <h2>📋 Анализ расходов по категориям</h2>
        <div class="total-summary">Всего расходов: <strong>{{ formatMoney(processedTotals.expenses || 0) }}</strong></div>
      </div>
      <div class="analysis-vertical">
        <div class="pie-chart-section">
          <h3>Распределение расходов</h3>
          <div class="pie-chart-container">
            <div class="pie-chart">
              <svg width="200" height="200" viewBox="0 0 200 200">
                <circle cx="100" cy="100" r="80" fill="none" stroke="#f0f0f0" stroke-width="40" />
                <g v-for="(category, index) in processedCategorySpending" :key="category.id">
                  <circle cx="100" cy="100" r="80" fill="none"
                          :stroke="category.color || getCategoryColor(index)"
                          stroke-width="40"
                          :stroke-dasharray="getDashArray(category)"
                          :stroke-dashoffset="getDashOffset(category, index)"
                          class="pie-segment" />
                </g>
                <text x="100" y="95" text-anchor="middle" class="pie-center-text">{{ processedCategorySpending.length }}</text>
                <text x="100" y="115" text-anchor="middle" class="pie-center-subtext">категорий</text>
              </svg>
            </div>
            <div class="pie-legend">
              <div v-for="(category, index) in processedCategorySpending.slice(0, 5)" :key="category.id" class="legend-item">
                <div class="legend-color" :style="{ backgroundColor: category.color || getCategoryColor(index) }"></div>
                <div class="legend-text">
                  <span class="legend-name">{{ category.name }}</span>
                  <span class="legend-value">{{ formatMoney(category.total_in_byn || category.total) }}</span>
                </div>
                <div class="legend-percentage">{{ getCategoryPercentage(category.total_in_byn || category.total) }}%</div>
              </div>
            </div>
          </div>
        </div>
        <div class="category-table-section">
          <h3>Детализация по категориям</h3>
          <div class="table-container">
            <table class="categories-table">
              <thead>
              <tr>
                <th class="col-category">Категория</th>
                <th class="col-amount">Сумма</th>
                <th class="col-limit">Лимит</th>
                <th class="col-status">Статус</th>
              </tr>
              </thead>
              <tbody>
              <tr v-for="category in processedCategorySpending" :key="category.id">
                <td class="col-category">
                  <div class="category-info">
                    <div class="category-color" :style="{ backgroundColor: category.color || '#3498db' }"></div>
                    <span class="category-name">{{ category.name }}</span>
                  </div>
                </td>
                <td class="col-amount">
                  <div class="amount-value">{{ formatMoney(category.total_in_byn || category.total) }}</div>
                </td>
                <td class="col-limit">
                  <span v-if="category.budget_limit" class="limit-value">{{ formatMoney(category.budget_limit) }}</span>
                  <span v-else class="no-limit">Не задан</span>
                  <div v-if="category.limit_percentage" class="limit-progress">
                    <div class="progress-bar">
                      <div class="progress-fill" :class="category.budget_status" :style="{ width: Math.min(category.limit_percentage, 100) + '%' }"></div>
                    </div>
                    <span class="progress-text">{{ category.limit_percentage.toFixed(1) }}%</span>
                  </div>
                </td>
                <td class="col-status">
                  <div class="status-badge" :class="category.budget_status">{{ getBudgetStatusLabel(category.budget_status) }}</div>
                </td>
              </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
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
    const calcLoading = ref(false)
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

    // Обработанные расходы по категориям (пересчитанные в BYN)
    const processedCategorySpending = computed(() => {
      const categories = analytics.value.category_spending || []

      return categories.map(cat => {
        const totalInByn = getAmountInByn(cat)

        // Пересчитываем процент использования лимита
        let limitPercentage = null
        let budgetStatus = cat.budget_status

        if (cat.budget_limit && cat.budget_limit > 0) {
          const limitInByn = getAmountInByn({ total: cat.budget_limit })
          limitPercentage = (totalInByn / limitInByn) * 100

          if (budgetStatus === 'good' && limitPercentage > 80) budgetStatus = 'warning'
          if (budgetStatus === 'warning' && limitPercentage > 100) budgetStatus = 'critical'
        }

        return {
          ...cat,
          total_in_byn: totalInByn,
          total: totalInByn, // Переопределяем для совместимости
          limit_percentage: limitPercentage,
          budget_status: budgetStatus
        }
      }).sort((a, b) => (b.total_in_byn || 0) - (a.total_in_byn || 0))
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

    const calculate503020 = async () => {
      try {
        calcLoading.value = true
        const [year, month] = selectedDate.value.split('-')
        const res = await axios.get('/api/budget/rule-50-30-20', {
          params: { month: parseInt(month), year: parseInt(year) },
          credentials: 'include'
        })
        // Конвертируем суммы в ответе в BYN для отображения
        const data = res.data.data
        if (data) {
          if (data.income) data.income = getAmountInByn({ total: data.income })
          if (data.needs) data.needs = getAmountInByn({ total: data.needs })
          if (data.wants) data.wants = getAmountInByn({ total: data.wants })
          if (data.savings) data.savings = getAmountInByn({ total: data.savings })
        }
        resultModal.value = {
          icon: '📐',
          title: 'Правило 50/30/20',
          data: JSON.stringify(data, null, 2)
        }
        showResultModal.value = true
      } catch (err) {
        alert(err.response?.data?.message || 'Ошибка')
      } finally {
        calcLoading.value = false
      }
    }

    const calculate6040 = async () => {
      try {
        calcLoading.value = true
        const [year, month] = selectedDate.value.split('-')
        const res = await axios.get('/api/budget/rule-60-40', {
          params: { month: parseInt(month), year: parseInt(year) },
          credentials: 'include'
        })
        const data = res.data.data
        if (data) {
          if (data.income) data.income = getAmountInByn({ total: data.income })
          if (data.expenses) data.expenses = getAmountInByn({ total: data.expenses })
          if (data.surplus) data.surplus = getAmountInByn({ total: data.surplus })
        }
        resultModal.value = {
          icon: '⚖️',
          title: 'Метод 60/40',
          data: JSON.stringify(data, null, 2)
        }
        showResultModal.value = true
      } catch (err) {
        alert(err.response?.data?.message || 'Ошибка')
      } finally {
        calcLoading.value = false
      }
    }

    const calculateFourEnvelopes = async () => {
      try {
        calcLoading.value = true
        const [year, month] = selectedDate.value.split('-')
        const res = await axios.get('/api/budget/four-envelopes', {
          params: { month: parseInt(month), year: parseInt(year) },
          credentials: 'include'
        })
        const data = res.data.data
        if (data) {
          if (data.total_income) data.total_income = getAmountInByn({ total: data.total_income })
          if (data.envelopes) {
            data.envelopes = data.envelopes.map(e => ({
              ...e,
              amount: getAmountInByn({ total: e.amount })
            }))
          }
        }
        resultModal.value = {
          icon: '📬',
          title: 'Метод четырёх конвертов',
          data: JSON.stringify(data, null, 2)
        }
        showResultModal.value = true
      } catch (err) {
        alert(err.response?.data?.message || 'Ошибка')
      } finally {
        calcLoading.value = false
      }
    }

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

    const getCategoryPercentage = (amount) => {
      const total = processedTotals.value.expenses || 1
      return ((amount / total) * 100).toFixed(1)
    }

    const getBudgetStatusLabel = (status) => {
      switch(status) {
        case 'good': return 'В норме'
        case 'warning': return 'Близко к лимиту'
        case 'critical': return 'Превышен'
        default: return 'Без лимита'
      }
    }

    const getCategoryColor = (index) => {
      const colors = ['#3498db', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#d35400', '#34495e']
      return colors[index % colors.length]
    }

    const getDashArray = (category) => {
      const total = category.total_in_byn || category.total || 0
      const percent = getCategoryPercentage(total)
      const circ = 2 * Math.PI * 80
      return `${(percent / 100) * circ} ${circ}`
    }

    const getDashOffset = (category, idx) => {
      const cats = processedCategorySpending.value
      let total = 0
      for (let i = 0; i < idx; i++) {
        if (cats[i]) {
          const amount = cats[i].total_in_byn || cats[i].total || 0
          total += parseFloat(getCategoryPercentage(amount))
        }
      }
      return -(total / 100) * (2 * Math.PI * 80)
    }

    const applyOptimalLimit = async (item) => {
      if (!item?.category_id) {
        alert('Ошибка: ID категории не найден')
        return
      }
      try {
        const res = await axios.put(`/api/categories/${item.category_id}`, {
          budget_limit: item.recommended_limit
        })
        if (res.data.status === 'success') {
          alert(`Лимит для категории "${item.category_name}" установлен в ${formatMoney(item.recommended_limit)}`)
          fetchAnalytics()
        }
      } catch (err) {
        alert('Ошибка при обновлении лимита')
      }
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
      calcLoading,
      selectedPeriod,
      selectedDate,
      showOptimalDistribution,
      showResultModal,
      resultModal,
      balanceClass,
      financialHealth,
      processedTotals,
      processedCategorySpending,
      expenseCategoriesDistribution,
      fetchAnalytics,
      calculate503020,
      calculate6040,
      calculateFourEnvelopes,
      formatMoney,
      getCategoryPercentage,
      getBudgetStatusLabel,
      getCategoryColor,
      getDashArray,
      getDashOffset,
      applyOptimalLimit
    }
  }
}
</script>

<style scoped>
@import '../css/analytics.css';

.budget-methods-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-bottom: 2rem;
  padding: 1rem;
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  border-radius: 16px;
  border: 1px solid #e2e8f0;
}

.method-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.6rem 1.2rem;
  border: none;
  border-radius: 10px;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.method-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.method-503020 { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
.method-6040 { background: linear-gradient(135deg, #10b981, #059669); color: white; }
.method-envelopes { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 16px;
  width: 90%;
  max-width: 700px;
  max-height: 80vh;
  display: flex;
  flex-direction: column;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #e2e8f0;
}

.modal-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
  font-size: 1.125rem;
}

.modal-close {
  background: none;
  border: none;
  cursor: pointer;
  color: #64748b;
}

.modal-body {
  padding: 1.5rem;
  overflow-y: auto;
  flex: 1;
}

.result-json {
  background: #1e293b;
  color: #e2e8f0;
  padding: 1rem;
  border-radius: 8px;
  font-size: 0.75rem;
  font-family: monospace;
  white-space: pre-wrap;
  margin: 0;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  padding: 1rem 1.5rem;
  border-top: 1px solid #e2e8f0;
}

.btn-secondary {
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  cursor: pointer;
}

.btn-secondary:hover { background: #e2e8f0; }

.optimal-distribution-section {
  margin: 2rem 0;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 16px;
}

.toggle-distribution {
  width: 100%;
  padding: 0.75rem;
  background: #e2e8f0;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
}

.metric-tooltip {
  position: relative;
  display: flex;
  align-items: center;
  gap: 1rem;
  cursor: help;
}

.tooltip-text {
  visibility: hidden;
  width: 260px;
  background-color: #1e293b;
  color: #fff;
  text-align: left;
  border-radius: 12px;
  padding: 0.75rem 1rem;
  position: absolute;
  z-index: 100;
  bottom: 125%;
  left: 50%;
  margin-left: -130px;
  font-size: 0.75rem;
  font-weight: normal;
  line-height: 1.4;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
  opacity: 0;
  transition: opacity 0.2s;
  pointer-events: none;
}

.metric-tooltip:hover .tooltip-text {
  visibility: visible;
  opacity: 1;
}

.tooltip-text strong {
  display: block;
  margin-bottom: 0.5rem;
  color: #93c5fd;
}

.tooltip-score {
  display: inline-block;
  margin-top: 0.5rem;
  padding-top: 0.5rem;
  border-top: 1px solid #475569;
  width: 100%;
  font-weight: 600;
  color: #facc15;
}
</style>