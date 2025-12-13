<template>
  <div class="analytics-page">
    <div class="header">
      <h1>📊 Аналитика бюджета</h1>
      <div class="period-controls">
        <select v-model="selectedPeriod" @change="fetchAnalytics">
          <option value="month">Месяц</option>
          <option value="week">Неделя</option>
          <option value="year">Год</option>
        </select>
        <input v-model="selectedDate" type="month" @change="fetchAnalytics">
      </div>
    </div>

    <!-- Основная статистика -->
    <div class="stats-overview">
      <div class="stat-card income">
        <div class="stat-icon">📈</div>
        <div class="stat-content">
          <h3>Доходы</h3>
          <div class="amount">{{ formatMoney(analytics.total_income) }}</div>
          <div class="period">{{ analytics.date_range?.label || 'За период' }}</div>
        </div>
      </div>

      <div class="stat-card expense">
        <div class="stat-icon">📉</div>
        <div class="stat-content">
          <h3>Расходы</h3>
          <div class="amount">{{ formatMoney(analytics.total_expenses) }}</div>
          <div class="period">{{ analytics.date_range?.label || 'За период' }}</div>
        </div>
      </div>

      <div class="stat-card balance" :class="balanceClass">
        <div class="stat-icon">⚖️</div>
        <div class="stat-content">
          <h3>Баланс</h3>
          <div class="amount">{{ formatMoney(analytics.balance) }}</div>
          <div class="period">чистый остаток</div>
        </div>
      </div>
    </div>

    <!-- Рекомендации -->
    <div class="recommendations-section">
      <h2>💡 Рекомендации по бюджету</h2>
      <div v-if="loading" class="loading">Анализируем ваши финансы...</div>
      <div v-else class="recommendations-list">
        <div
            v-for="(rec, index) in analytics.recommendations"
            :key="index"
            class="recommendation"
            :class="rec.type"
        >
          <div class="rec-icon">
            <span v-if="rec.type === 'critical'">⚠️</span>
            <span v-else-if="rec.type === 'warning'">🔔</span>
            <span v-else">✅</span>
          </div>
          <div class="rec-content">
            <h4>{{ rec.title }}</h4>
            <p>{{ rec.message }}</p>
          </div>
        </div>
        <div v-if="!analytics.recommendations || analytics.recommendations.length === 0" class="no-recommendations">
          <p>Ваш бюджет выглядит отлично! Продолжайте в том же духе. 🎉</p>
        </div>
      </div>
    </div>

    <!-- Расходы по категориям -->
    <div class="category-analysis">
      <div class="analysis-header">
        <h2>📋 Расходы по категориям</h2>
        <div class="total-expenses">
          Всего расходов: <strong>{{ formatMoney(analytics.total_expenses) }}</strong>
        </div>
      </div>

      <div v-if="loading" class="loading">Загрузка данных...</div>

      <div v-else-if="!analytics.category_spending || analytics.category_spending.length === 0" class="no-data">
        <p>Нет данных о расходах за выбранный период</p>
      </div>

      <div v-else class="analysis-content">
        <!-- График распределения -->
        <div class="chart-section">
          <h3>Распределение расходов</h3>
          <div class="chart-container">
            <div
                v-for="category in analytics.category_spending"
                :key="category.name"
                class="chart-bar"
                :style="{
                width: getCategoryPercentage(category.total) + '%',
                backgroundColor: category.color
              }"
                :title="`${category.name}: ${formatMoney(category.total)} (${getCategoryPercentage(category.total)}%)`"
            >
              <span class="bar-label" v-if="getCategoryPercentage(category.total) > 10">
                {{ category.name }} ({{ getCategoryPercentage(category.total) }}%)
              </span>
            </div>
          </div>
        </div>

        <!-- Детальная таблица -->
        <div class="table-section">
          <h3>Детализация по категориям</h3>
          <div class="categories-table">
            <div class="table-header">
              <div class="col-category">Категория</div>
              <div class="col-amount">Сумма</div>
              <div class="col-percentage">Доля</div>
            </div>

            <div
                v-for="category in sortedCategorySpending"
                :key="category.name"
                class="table-row"
            >
              <div class="col-category">
                <div class="category-info">
                  <div
                      class="category-color"
                      :style="{ backgroundColor: category.color }"
                  ></div>
                  <span>{{ category.name }}</span>
                </div>
              </div>
              <div class="col-amount">
                {{ formatMoney(category.total) }}
              </div>
              <div class="col-percentage">
                <div class="percentage-bar">
                  <div
                      class="percentage-fill"
                      :style="{
                      width: getCategoryPercentage(category.total) + '%',
                      backgroundColor: category.color
                    }"
                  ></div>
                  <span class="percentage-text">
                    {{ getCategoryPercentage(category.total) }}%
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Анализ эффективности -->
    <div class="efficiency-analysis">
      <h2>📈 Эффективность бюджета</h2>
      <div class="efficiency-cards">
        <div class="efficiency-card">
          <div class="eff-icon">💰</div>
          <div class="eff-content">
            <h4>Норма сбережений</h4>
            <div class="eff-value" :class="getSavingsRateClass(savingsRate)">
              {{ savingsRate.toFixed(1) }}%
            </div>
            <p>Отношение сбережений к доходам</p>
          </div>
        </div>

        <div class="efficiency-card">
          <div class="eff-icon">📊</div>
          <div class="eff-content">
            <h4>Соотношение доход/расход</h4>
            <div class="eff-value" :class="getRatioClass(incomeExpenseRatio)">
              {{ incomeExpenseRatio.toFixed(2) }}:1
            </div>
            <p>Доходы к расходам</p>
          </div>
        </div>

        <div class="efficiency-card">
          <div class="eff-icon">🎯</div>
          <div class="eff-content">
            <h4>Эффективность бюджета</h4>
            <div class="eff-value" :class="getEfficiencyClass(budgetEfficiency)">
              {{ budgetEfficiency }}%
            </div>
            <p>Выполнение плана по расходам</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

export default {
  name: 'AnalyticsPage',
  setup() {
    const analytics = ref({
      total_income: 0,
      total_expenses: 0,
      balance: 0,
      category_spending: [],
      recommendations: [],
      date_range: {}
    })

    const loading = ref(false)
    const selectedPeriod = ref('month')
    const selectedDate = ref(new Date().toISOString().slice(0, 7))

    const balanceClass = computed(() => {
      if (analytics.value.balance > 0) return 'positive'
      if (analytics.value.balance < 0) return 'negative'
      return 'neutral'
    })

    const sortedCategorySpending = computed(() => {
      if (!analytics.value.category_spending) return []
      return [...analytics.value.category_spending].sort((a, b) => b.total - a.total)
    })

    const savingsRate = computed(() => {
      if (analytics.value.total_income === 0) return 0
      return (analytics.value.balance / analytics.value.total_income) * 100
    })

    const incomeExpenseRatio = computed(() => {
      if (analytics.value.total_expenses === 0) return 0
      return analytics.value.total_income / analytics.value.total_expenses
    })

    const budgetEfficiency = computed(() => {
      // Простая метрика эффективности бюджета
      if (analytics.value.total_income === 0) return 0
      const targetSavings = analytics.value.total_income * 0.2 // Цель - 20% сбережений
      const actualSavings = Math.max(0, analytics.value.balance)
      return Math.min(100, (actualSavings / targetSavings) * 100).toFixed(0)
    })

    const fetchAnalytics = async () => {
      try {
        loading.value = true

        const params = {
          period: selectedPeriod.value,
          month: selectedDate.value.split('-')[1],
          year: selectedDate.value.split('-')[0]
        }

        const response = await axios.get('/api/analytics/overview', { params })
        analytics.value = response.data.data

      } catch (error) {
        console.error('Error fetching analytics:', error)
        alert('Ошибка при загрузке аналитики')
      } finally {
        loading.value = false
      }
    }

    const formatMoney = (amount) => {
      return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB'
      }).format(amount)
    }

    const getCategoryPercentage = (categoryAmount) => {
      if (analytics.value.total_expenses === 0) return 0
      return ((categoryAmount / analytics.value.total_expenses) * 100).toFixed(1)
    }

    const getSavingsRateClass = (rate) => {
      if (rate >= 20) return 'excellent'
      if (rate >= 10) return 'good'
      if (rate >= 0) return 'warning'
      return 'critical'
    }

    const getRatioClass = (ratio) => {
      if (ratio >= 1.5) return 'excellent'
      if (ratio >= 1.2) return 'good'
      if (ratio >= 1.0) return 'warning'
      return 'critical'
    }

    const getEfficiencyClass = (efficiency) => {
      if (efficiency >= 100) return 'excellent'
      if (efficiency >= 80) return 'good'
      if (efficiency >= 60) return 'warning'
      return 'critical'
    }

    onMounted(() => {
      fetchAnalytics()
    })

    return {
      analytics,
      loading,
      selectedPeriod,
      selectedDate,
      balanceClass,
      sortedCategorySpending,
      savingsRate,
      incomeExpenseRatio,
      budgetEfficiency,
      fetchAnalytics,
      formatMoney,
      getCategoryPercentage,
      getSavingsRateClass,
      getRatioClass,
      getEfficiencyClass
    }
  }
}
</script>

<style scoped>
.analytics-page {
  max-width: 1200px;
  margin: 0 auto;
  padding: 2rem;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.period-controls {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.period-controls select,
.period-controls input {
  padding: 0.5rem;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 1rem;
}

.stats-overview {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  display: flex;
  align-items: center;
  background: white;
  padding: 1.5rem;
  border-radius: 12px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  border-left: 4px solid;
}

.stat-card.income {
  border-left-color: #27ae60;
}

.stat-card.expense {
  border-left-color: #e74c3c;
}

.stat-card.balance.positive {
  border-left-color: #27ae60;
}

.stat-card.balance.negative {
  border-left-color: #e74c3c;
}

.stat-card.balance.neutral {
  border-left-color: #95a5a6;
}

.stat-icon {
  font-size: 2rem;
  margin-right: 1rem;
}

.stat-content h3 {
  margin: 0 0 0.5rem 0;
  color: #7f8c8d;
  font-size: 0.9rem;
  text-transform: uppercase;
}

.stat-content .amount {
  font-size: 1.8rem;
  font-weight: bold;
  margin: 0 0 0.25rem 0;
}

.stat-content .period {
  font-size: 0.8rem;
  color: #95a5a6;
}

.recommendations-section,
.category-analysis,
.efficiency-analysis {
  background: white;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
  margin-bottom: 2rem;
}

.recommendations-section h2,
.category-analysis h2,
.efficiency-analysis h2 {
  margin-bottom: 1.5rem;
  color: #2c3e50;
}

.recommendations-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.recommendation {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding: 1rem;
  border-radius: 8px;
  border-left: 4px solid;
}

.recommendation.critical {
  background: #ffeaea;
  border-left-color: #e74c3c;
}

.recommendation.warning {
  background: #fff4e6;
  border-left-color: #f39c12;
}

.recommendation.success {
  background: #e8f6ef;
  border-left-color: #27ae60;
}

.rec-icon {
  font-size: 1.2rem;
  flex-shrink: 0;
}

.rec-content h4 {
  margin: 0 0 0.5rem 0;
  color: #2c3e50;
}

.rec-content p {
  margin: 0;
  color: #666;
  line-height: 1.4;
}

.no-recommendations {
  text-align: center;
  padding: 2rem;
  color: #7f8c8d;
  font-style: italic;
}

.analysis-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.total-expenses {
  color: #7f8c8d;
  font-size: 1.1rem;
}

.analysis-content {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
}

.chart-section h3,
.table-section h3 {
  margin-bottom: 1rem;
  color: #2c3e50;
  font-size: 1.1rem;
}

.chart-container {
  display: flex;
  height: 60px;
  border-radius: 8px;
  overflow: hidden;
  background: #f8f9fa;
  border: 1px solid #ecf0f1;
}

.chart-bar {
  height: 100%;
  transition: width 0.3s ease;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
}

.bar-label {
  color: white;
  font-size: 0.7rem;
  font-weight: 600;
  text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  padding: 0 4px;
}

.categories-table {
  border: 1px solid #ecf0f1;
  border-radius: 8px;
  overflow: hidden;
}

.table-header {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr;
  background: #34495e;
  color: white;
  font-weight: 600;
  padding: 1rem;
}

.table-row {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr;
  padding: 1rem;
  border-bottom: 1px solid #ecf0f1;
  align-items: center;
}

.table-row:last-child {
  border-bottom: none;
}

.table-row:hover {
  background: #f8f9fa;
}

.col-category {
  display: flex;
  align-items: center;
}

.category-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.category-color {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}

.col-amount {
  font-weight: 600;
  text-align: right;
}

.col-percentage {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.percentage-bar {
  flex: 1;
  background: #ecf0f1;
  height: 8px;
  border-radius: 4px;
  position: relative;
  overflow: hidden;
}

.percentage-fill {
  height: 100%;
  border-radius: 4px;
  transition: width 0.3s ease;
}

.percentage-text {
  font-size: 0.8rem;
  color: #7f8c8d;
  min-width: 40px;
  text-align: right;
}

.efficiency-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
}

.efficiency-card {
  background: #f8f9fa;
  padding: 1.5rem;
  border-radius: 8px;
  text-align: center;
  border: 2px solid transparent;
  transition: border-color 0.3s;
}

.efficiency-card:hover {
  border-color: #3498db;
}

.eff-icon {
  font-size: 2rem;
  margin-bottom: 1rem;
}

.eff-content h4 {
  margin: 0 0 0.5rem 0;
  color: #2c3e50;
}

.eff-value {
  font-size: 1.5rem;
  font-weight: bold;
  margin-bottom: 0.5rem;
}

.eff-value.excellent {
  color: #27ae60;
}

.eff-value.good {
  color: #f39c12;
}

.eff-value.warning {
  color: #e67e22;
}

.eff-value.critical {
  color: #e74c3c;
}

.eff-content p {
  margin: 0;
  color: #7f8c8d;
  font-size: 0.9rem;
}

.loading {
  text-align: center;
  padding: 3rem;
  color: #7f8c8d;
  font-style: italic;
}

.no-data {
  text-align: center;
  padding: 3rem;
  color: #7f8c8d;
}

/* Адаптивность */
@media (max-width: 768px) {
  .analysis-content {
    grid-template-columns: 1fr;
  }

  .header {
    flex-direction: column;
    gap: 1rem;
    align-items: flex-start;
  }

  .period-controls {
    width: 100%;
  }

  .stats-overview {
    grid-template-columns: 1fr;
  }
}
</style>