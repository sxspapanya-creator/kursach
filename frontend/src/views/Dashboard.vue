<template>
  <div class="dashboard">
    <!-- Заголовок и фильтры -->
    <div class="dashboard-header">
      <div class="header-left">
        <h1 class="page-title">Панель управления</h1>
        <p class="page-subtitle">Обзор ваших финансов</p>
      </div>
      <div class="header-right">
        <div class="period-selector">
          <select v-model="selectedPeriod" @change="fetchData" class="select-field">
            <option value="today">Сегодня</option>
            <option value="week">Эта неделя</option>
            <option value="month" selected>Этот месяц</option>
            <option value="year">Этот год</option>
            <option value="all">Все время</option>
          </select>
        </div>
        <div class="date-range">
          {{ formatDateRange() }}
        </div>
      </div>
    </div>

    <!-- Основная статистика -->
    <div class="stats-grid">
      <div class="stat-card income-card">
        <div class="stat-header">
          <div class="stat-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 2V22" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M17 5L12 10L7 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="stat-info">
            <h3 class="stat-title">Доходы</h3>
            <div class="stat-change" v-if="stats.incomeChange !== null">
              <span :class="stats.incomeChange >= 0 ? 'positive' : 'negative'">
                {{ stats.incomeChange >= 0 ? '↗' : '↘' }} {{ Math.abs(stats.incomeChange) }}%
              </span>
              <span class="change-label">с прошлого периода</span>
            </div>
          </div>
        </div>
        <div class="stat-value">{{ formatMoney(stats.totalIncome) }}</div>
      </div>

      <div class="stat-card expense-card">
        <div class="stat-header">
          <div class="stat-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 22V2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M7 19L12 14L17 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="stat-info">
            <h3 class="stat-title">Расходы</h3>
            <div class="stat-change" v-if="stats.expenseChange !== null">
              <span :class="stats.expenseChange <= 0 ? 'positive' : 'negative'">
                {{ stats.expenseChange <= 0 ? '↘' : '↗' }} {{ Math.abs(stats.expenseChange) }}%
              </span>
              <span class="change-label">с прошлого периода</span>
            </div>
          </div>
        </div>
        <div class="stat-value">{{ formatMoney(stats.totalExpenses) }}</div>
      </div>

      <div class="stat-card balance-card" :class="balanceClass">
        <div class="stat-header">
          <div class="stat-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
              <path d="M8 14S9.5 16 12 16s4-2 4-2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <line x1="9" y1="9" x2="9.01" y2="9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <line x1="15" y1="9" x2="15.01" y2="9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="stat-info">
            <h3 class="stat-title">Баланс</h3>
            <div class="savings-rate" v-if="stats.savingsRate !== null">
              <span class="rate-label">Норма сбережений:</span>
              <span class="rate-value">{{ stats.savingsRate }}%</span>
            </div>
          </div>
        </div>
        <div class="stat-value">{{ formatMoney(stats.balance) }}</div>
      </div>

      <div class="stat-card transactions-card">
        <div class="stat-header">
          <div class="stat-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect x="1" y="4" width="22" height="16" rx="2" stroke="currentColor" stroke-width="2"/>
              <line x1="1" y1="10" x2="23" y2="10" stroke="currentColor" stroke-width="2"/>
            </svg>
          </div>
          <div class="stat-info">
            <h3 class="stat-title">Транзакции</h3>
            <div class="transactions-count">
              <span class="count-value">{{ stats.transactionCount || 0 }}</span>
              <span class="count-label">за период</span>
            </div>
          </div>
        </div>
        <div class="transactions-breakdown">
          <div class="breakdown-item">
            <span class="breakdown-label">Доходы:</span>
            <span class="breakdown-value income">{{ stats.incomeCount || 0 }}</span>
          </div>
          <div class="breakdown-item">
            <span class="breakdown-label">Расходы:</span>
            <span class="breakdown-value expense">{{ stats.expenseCount || 0 }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Основное содержимое - две колонки -->
    <div class="content-grid">
      <!-- Левая колонка: График и транзакции -->
      <div class="content-column">
        <!-- График расходов -->
        <div class="content-section">
          <div class="section-header">
            <h2 class="section-title">Динамика расходов</h2>
            <div class="section-actions">
              <button @click="toggleChartType" class="btn-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                </svg>
              </button>
            </div>
          </div>
          <div class="chart-container">
            <div v-if="loading" class="chart-loading">
              <div class="loading-spinner"></div>
            </div>
            <div v-else-if="chartData.labels.length === 0" class="chart-empty">
              <div class="empty-icon">📊</div>
              <p>Нет данных для графика</p>
            </div>
            <canvas v-else ref="chartCanvas" class="chart-canvas"></canvas>
          </div>
        </div>

        <!-- Последние транзакции -->
        <div class="content-section">
          <div class="section-header">
            <h2 class="section-title">Недавние транзакции</h2>
            <router-link to="/transactions" class="btn-link">
              Все транзакции
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M5 12h14M12 5l7 7-7 7"/>
              </svg>
            </router-link>
          </div>
          <div class="transactions-list">
            <div v-if="loading" class="loading-state">
              <div class="spinner"></div>
              <p>Загрузка транзакций...</p>
            </div>
            <div v-else-if="recentTransactions.length === 0" class="empty-state">
              <div class="empty-icon">💸</div>
              <p>Нет транзакций</p>
              <router-link to="/transactions/add" class="btn btn-primary btn-small">
                + Добавить транзакцию
              </router-link>
            </div>
            <div v-else class="transactions-items">
              <div
                  v-for="transaction in recentTransactions"
                  :key="transaction.id"
                  class="transaction-item"
                  @click="viewTransaction(transaction)"
              >
                <div class="transaction-icon" :style="{ backgroundColor: transaction.category.color + '20' }">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path v-if="transaction.type === 'income'" d="M12 2v20M17 5l-5 5-5-5"/>
                    <path v-else d="M12 22v-20M7 19l5-5 5 5"/>
                    <path d="M5 12h14"/>
                  </svg>
                </div>

                <div class="transaction-details">
                  <div class="transaction-main">
                    <span class="transaction-description">
                      {{ transaction.description || 'Без описания' }}
                    </span>
                    <span class="transaction-amount" :class="transaction.type">
                      {{ transaction.type === 'income' ? '+' : '-' }}{{ formatMoney(transaction.amount) }}
                    </span>
                  </div>
                  <div class="transaction-meta">
                    <span class="transaction-category" :style="{ color: transaction.category.color }">
                      {{ transaction.category.name }}
                    </span>
                    <span class="transaction-date">
                      {{ formatDate(transaction.date) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Правая колонка: Категории и быстрые действия -->
      <div class="content-column">
        <!-- Расходы по категориям -->
        <div class="content-section">
          <div class="section-header">
            <h2 class="section-title">Расходы по категориям</h2>
            <router-link to="/categories" class="btn-link">
              Все категории
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M5 12h14M12 5l7 7-7 7"/>
              </svg>
            </router-link>
          </div>
          <div class="categories-list">
            <div v-if="loading" class="loading-state">
              <div class="spinner"></div>
              <p>Загрузка категорий...</p>
            </div>
            <div v-else-if="categorySpending.length === 0" class="empty-state">
              <div class="empty-icon">📊</div>
              <p>Нет данных о расходах</p>
            </div>
            <div v-else class="categories-items">
              <div
                  v-for="category in categorySpending"
                  :key="category.id"
                  class="category-item"
              >
                <div class="category-info">
                  <div class="category-color" :style="{ backgroundColor: category.color }"></div>
                  <div class="category-name">{{ category.name }}</div>
                  <div class="category-percentage">{{ category.percentage }}%</div>
                </div>
                <div class="category-amount">{{ formatMoney(category.amount) }}</div>
                <div class="category-progress">
                  <div
                      class="progress-bar"
                      :style="{ backgroundColor: category.color + '20' }"
                  >
                    <div
                        class="progress-fill"
                        :style="{
                        width: `${category.percentage}%`,
                        backgroundColor: category.color
                      }"
                    ></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Быстрые действия -->
        <div class="content-section quick-actions-section">
          <div class="section-header">
            <h2 class="section-title">Быстрые действия</h2>
          </div>
          <div class="actions-grid">
            <router-link to="/transactions/add?type=income" class="action-card">
              <div class="action-icon income">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12 5v14M5 12h14"/>
                </svg>
              </div>
              <div class="action-content">
                <h4 class="action-title">Добавить доход</h4>
                <p class="action-description">Зафиксировать поступление средств</p>
              </div>
            </router-link>

            <router-link to="/transactions/add?type=expense" class="action-card">
              <div class="action-icon expense">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <line x1="5" y1="12" x2="19" y2="12" stroke-width="2"/>
                </svg>
              </div>
              <div class="action-content">
                <h4 class="action-title">Добавить расход</h4>
                <p class="action-description">Записать трату</p>
              </div>
            </router-link>

            <router-link to="/categories" class="action-card">
              <div class="action-icon categories">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                  <line x1="7" y1="7" x2="7.01" y2="7"/>
                </svg>
              </div>
              <div class="action-content">
                <h4 class="action-title">Управление категориями</h4>
                <p class="action-description">Настроить категории расходов и доходов</p>
              </div>
            </router-link>

            <router-link to="/reports" class="action-card">
              <div class="action-icon reports">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                  <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                  <line x1="12" y1="22.08" x2="12" y2="12"/>
                </svg>
              </div>
              <div class="action-content">
                <h4 class="action-title">Отчеты</h4>
                <p class="action-description">Подробная аналитика</p>
              </div>
            </router-link>
          </div>
        </div>

        <!-- Бюджет -->
        <div class="content-section budget-section" v-if="budgetStats.total > 0">
          <div class="section-header">
            <h2 class="section-title">Бюджет</h2>
            <div class="budget-usage">
              {{ budgetStats.used }} / {{ formatMoney(budgetStats.total) }}
            </div>
          </div>
          <div class="budget-progress">
            <div class="progress-bar">
              <div
                  class="progress-fill"
                  :style="{ width: `${Math.min(budgetStats.percentage, 100)}%` }"
                  :class="{
                  'safe': budgetStats.percentage <= 70,
                  'warning': budgetStats.percentage > 70 && budgetStats.percentage <= 90,
                  'danger': budgetStats.percentage > 90
                }"
              ></div>
            </div>
            <div class="progress-info">
              <span class="percentage">{{ Math.round(budgetStats.percentage) }}%</span>
              <span v-if="budgetStats.remaining > 0" class="remaining">
                Осталось: {{ formatMoney(budgetStats.remaining) }}
              </span>
              <span v-else class="overspent">
                Превышение: {{ formatMoney(Math.abs(budgetStats.remaining)) }}
              </span>
            </div>
          </div>
          <div class="budget-categories">
            <div
                v-for="category in budgetStats.categories"
                :key="category.id"
                class="budget-category"
            >
              <div class="budget-category-info">
                <div class="category-color" :style="{ backgroundColor: category.color }"></div>
                <div class="category-name">{{ category.name }}</div>
              </div>
              <div class="budget-category-progress">
                <div class="category-percentage">{{ category.percentage }}%</div>
                <div class="category-amount">{{ formatMoney(category.spent) }} / {{ formatMoney(category.limit) }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { Chart, registerables } from 'chart.js'

Chart.register(...registerables)

export default {
  name: 'Dashboard',
  setup() {
    const router = useRouter()
    const chartCanvas = ref(null)
    const chartInstance = ref(null)

    const loading = ref(false)
    const selectedPeriod = ref('month')
    const chartType = ref('line') // 'line' или 'bar'

    const stats = ref({
      totalIncome: 0,
      totalExpenses: 0,
      balance: 0,
      incomeChange: null,
      expenseChange: null,
      savingsRate: null,
      transactionCount: 0,
      incomeCount: 0,
      expenseCount: 0
    })

    const recentTransactions = ref([])
    const categorySpending = ref([])
    const budgetStats = ref({
      total: 0,
      used: 0,
      remaining: 0,
      percentage: 0,
      categories: []
    })

    const chartData = ref({
      labels: [],
      datasets: []
    })

    const balanceClass = computed(() => {
      if (stats.value.balance > 0) return 'positive'
      if (stats.value.balance < 0) return 'negative'
      return 'neutral'
    })

    const formatMoney = (amount) => {
      if (amount === null || amount === undefined) return '0 ₽'
      return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
      }).format(amount) + ' ₽'
    }

    const formatDate = (dateString) => {
      const date = new Date(dateString)
      const now = new Date()
      const diffTime = Math.abs(now - date)
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))

      if (diffDays === 0) return 'Сегодня'
      if (diffDays === 1) return 'Вчера'
      if (diffDays <= 7) return `${diffDays} дня назад`

      return date.toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'short'
      })
    }

    const formatDateRange = () => {
      const now = new Date()
      switch (selectedPeriod.value) {
        case 'today':
          return now.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long' })
        case 'week':
          const weekStart = new Date(now.setDate(now.getDate() - now.getDay() + 1))
          return `${weekStart.getDate()} - ${now.getDate()} ${now.toLocaleDateString('ru-RU', { month: 'long' })}`
        case 'month':
          return now.toLocaleDateString('ru-RU', { month: 'long', year: 'numeric' })
        case 'year':
          return now.getFullYear().toString()
        default:
          return 'Все время'
      }
    }

    const fetchData = async () => {
      try {
        loading.value = true

        await Promise.all([
          fetchStats(),
          fetchRecentTransactions(),
          fetchCategorySpending(),
          fetchBudgetStats(),
          fetchChartData()
        ])

      } catch (error) {
        console.error('Error fetching dashboard data:', error)
      } finally {
        loading.value = false
      }
    }

    const fetchStats = async () => {
      try {
        const response = await axios.get('/api/dashboard/stats', {
          params: { period: selectedPeriod.value }
        })

        if (response.data.success) {
          stats.value = response.data.data
        }
      } catch (error) {
        console.error('Error fetching stats:', error)
      }
    }

    const fetchRecentTransactions = async () => {
      try {
        const response = await axios.get('/api/transactions/recent', {
          params: { limit: 5, period: selectedPeriod.value }
        })

        if (response.data.success) {
          recentTransactions.value = response.data.data
        }
      } catch (error) {
        console.error('Error fetching recent transactions:', error)
      }
    }

    const fetchCategorySpending = async () => {
      try {
        const response = await axios.get('/api/categories/spending', {
          params: { period: selectedPeriod.value }
        })

        if (response.data.success) {
          categorySpending.value = response.data.data
        }
      } catch (error) {
        console.error('Error fetching category spending:', error)
      }
    }

    const fetchBudgetStats = async () => {
      try {
        const response = await axios.get('/api/budget/stats', {
          params: { period: selectedPeriod.value }
        })

        if (response.data.success) {
          budgetStats.value = response.data.data
        } else {
          budgetStats.value = {
            total: 0,
            used: 0,
            remaining: 0,
            percentage: 0,
            categories: []
          }
        }
      } catch (error) {
        console.error('Error fetching budget stats:', error)
        budgetStats.value = {
          total: 0,
          used: 0,
          remaining: 0,
          percentage: 0,
          categories: []
        }
      }
    }

    const fetchChartData = async () => {
      try {
        const response = await axios.get('/api/dashboard/chart', {
          params: { period: selectedPeriod.value, type: 'expenses' }
        })

        if (response.data.success) {
          chartData.value = response.data.data
          renderChart()
        }
      } catch (error) {
        console.error('Error fetching chart data:', error)
        chartData.value = {
          labels: [],
          datasets: []
        }
      }
    }

    const renderChart = () => {
      if (!chartCanvas.value) return

      // Удаляем предыдущий график
      if (chartInstance.value) {
        chartInstance.value.destroy()
      }

      // Ожидаем обновления DOM
      nextTick(() => {
        if (chartData.value.labels.length === 0) return

        const ctx = chartCanvas.value.getContext('2d')

        chartInstance.value = new Chart(ctx, {
          type: chartType.value,
          data: {
            labels: chartData.value.labels,
            datasets: chartData.value.datasets.map(dataset => ({
              ...dataset,
              borderColor: dataset.color,
              backgroundColor: dataset.color + '20',
              borderWidth: 2,
              pointBackgroundColor: dataset.color,
              pointBorderColor: '#fff',
              pointBorderWidth: 2,
              pointRadius: 4,
              pointHoverRadius: 6,
              fill: chartType.value === 'line',
              tension: 0.3
            }))
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                display: false
              },
              tooltip: {
                mode: 'index',
                intersect: false,
                backgroundColor: 'rgba(30, 41, 59, 0.95)',
                titleColor: '#f8fafc',
                bodyColor: '#e2e8f0',
                borderColor: '#475569',
                borderWidth: 1,
                padding: 12,
                boxPadding: 6,
                callbacks: {
                  label: (context) => {
                    return `${context.dataset.label}: ${formatMoney(context.raw)}`
                  }
                }
              }
            },
            scales: {
              x: {
                grid: {
                  color: 'rgba(226, 232, 240, 0.5)'
                },
                ticks: {
                  color: '#64748b'
                }
              },
              y: {
                grid: {
                  color: 'rgba(226, 232, 240, 0.5)'
                },
                ticks: {
                  color: '#64748b',
                  callback: (value) => formatMoney(value)
                }
              }
            },
            interaction: {
              intersect: false,
              mode: 'nearest'
            },
            elements: {
              line: {
                tension: 0.3
              }
            }
          }
        })
      })
    }

    const toggleChartType = () => {
      chartType.value = chartType.value === 'line' ? 'bar' : 'line'
      renderChart()
    }

    const viewTransaction = (transaction) => {
      router.push(`/transactions/${transaction.id}`)
    }

    onMounted(() => {
      fetchData()
    })

    watch(selectedPeriod, () => {
      fetchData()
    })

    return {
      loading,
      selectedPeriod,
      stats,
      recentTransactions,
      categorySpending,
      budgetStats,
      chartData,
      chartCanvas,
      balanceClass,
      chartType,
      formatMoney,
      formatDate,
      formatDateRange,
      fetchData,
      toggleChartType,
      viewTransaction
    }
  }
}
</script>

<style scoped>
.dashboard {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

/* Заголовок */
.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 1rem;
  padding: 0.5rem 0;
}

.header-left {
  flex: 1;
}

.page-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 0.25rem 0;
}

.page-subtitle {
  font-size: 0.95rem;
  color: #64748b;
  margin: 0;
}

.header-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.5rem;
}

.period-selector {
  position: relative;
}

.select-field {
  padding: 0.5rem 2rem 0.5rem 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: white;
  color: #1e293b;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg width='16' height='16' viewBox='0 0 24 24' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M6 9L12 15L18 9' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.75rem center;
  background-size: 16px;
}

.select-field:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.date-range {
  font-size: 0.875rem;
  color: #64748b;
  font-weight: 500;
}

/* Статистика */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1rem;
}

.stat-card {
  background: white;
  border-radius: 12px;
  padding: 1.25rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
  transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.stat-header {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.stat-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.income-card .stat-icon {
  background: #dcfce7;
  color: #16a34a;
}

.expense-card .stat-icon {
  background: #fee2e2;
  color: #dc2626;
}

.balance-card.positive .stat-icon {
  background: #dbeafe;
  color: #2563eb;
}

.balance-card.negative .stat-icon {
  background: #fee2e2;
  color: #dc2626;
}

.balance-card.neutral .stat-icon {
  background: #f1f5f9;
  color: #64748b;
}

.transactions-card .stat-icon {
  background: #f3e8ff;
  color: #9333ea;
}

.stat-info {
  flex: 1;
}

.stat-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: #64748b;
  margin: 0 0 0.25rem 0;
}

.stat-change {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.75rem;
}

.positive {
  color: #16a34a;
  font-weight: 600;
}

.negative {
  color: #dc2626;
  font-weight: 600;
}

.change-label {
  color: #94a3b8;
}

.stat-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1e293b;
  line-height: 1.2;
}

.balance-card.positive .stat-value {
  color: #16a34a;
}

.balance-card.negative .stat-value {
  color: #dc2626;
}

.balance-card.neutral .stat-value {
  color: #64748b;
}

.savings-rate {
  font-size: 0.75rem;
  color: #64748b;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.rate-value {
  font-weight: 600;
  color: #16a34a;
}

.transactions-count {
  display: flex;
  align-items: baseline;
  gap: 0.25rem;
  margin-top: 0.25rem;
}

.count-value {
  font-size: 1.25rem;
  font-weight: 700;
  color: #1e293b;
}

.count-label {
  font-size: 0.75rem;
  color: #94a3b8;
}

.transactions-breakdown {
  margin-top: 0.75rem;
  padding-top: 0.75rem;
  border-top: 1px solid #e2e8f0;
}

.breakdown-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.75rem;
  margin-bottom: 0.25rem;
}

.breakdown-item:last-child {
  margin-bottom: 0;
}

.breakdown-label {
  color: #64748b;
}

.breakdown-value {
  font-weight: 600;
}

.breakdown-value.income {
  color: #16a34a;
}

.breakdown-value.expense {
  color: #dc2626;
}

/* Основное содержимое */
.content-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.5rem;
}

@media (min-width: 1024px) {
  .content-grid {
    grid-template-columns: 2fr 1fr;
  }
}

.content-section {
  background: white;
  border-radius: 12px;
  padding: 1.25rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
  margin-bottom: 1.5rem;
}

.content-section:last-child {
  margin-bottom: 0;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.section-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.btn-link {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #3b82f6;
  text-decoration: none;
  transition: color 0.2s;
}

.btn-link:hover {
  color: #2563eb;
}

.btn-icon {
  background: #f1f5f9;
  border: none;
  border-radius: 6px;
  padding: 0.5rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background-color 0.2s;
}

.btn-icon:hover {
  background: #e2e8f0;
}

/* График */
.chart-container {
  height: 250px;
  position: relative;
}

.chart-loading,
.chart-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: #94a3b8;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #e2e8f0;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.empty-icon {
  font-size: 2rem;
  margin-bottom: 0.5rem;
  opacity: 0.5;
}

.chart-canvas {
  width: 100%;
  height: 100%;
}

/* Транзакции */
.transactions-list,
.categories-list {
  min-height: 200px;
}

.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1rem;
  text-align: center;
  color: #64748b;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #e2e8f0;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 1rem;
}

.transactions-items {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.transaction-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem;
  border-radius: 8px;
  cursor: pointer;
  transition: background-color 0.2s;
  border: 1px solid transparent;
}

.transaction-item:hover {
  background-color: #f8fafc;
  border-color: #e2e8f0;
}

.transaction-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.transaction-details {
  flex: 1;
  min-width: 0;
}

.transaction-main {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.25rem;
}

.transaction-description {
  font-weight: 500;
  color: #1e293b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
  margin-right: 1rem;
}

.transaction-amount {
  font-weight: 600;
  white-space: nowrap;
  flex-shrink: 0;
}

.transaction-amount.income {
  color: #16a34a;
}

.transaction-amount.expense {
  color: #dc2626;
}

.transaction-meta {
  display: flex;
  gap: 0.75rem;
  font-size: 0.75rem;
  color: #64748b;
}

.transaction-category {
  font-weight: 500;
}

/* Категории */
.categories-items {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.category-item {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.category-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.category-color {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  flex-shrink: 0;
}

.category-name {
  font-weight: 500;
  color: #1e293b;
  font-size: 0.875rem;
  flex: 1;
}

.category-percentage {
  font-size: 0.75rem;
  font-weight: 600;
  color: #64748b;
}

.category-amount {
  font-size: 0.875rem;
  font-weight: 600;
  color: #1e293b;
  text-align: right;
}

.category-progress .progress-bar {
  height: 4px;
  border-radius: 2px;
  overflow: hidden;
  background-color: #e2e8f0;
}

.category-progress .progress-fill {
  height: 100%;
  border-radius: 2px;
  transition: width 0.3s ease;
}

/* Быстрые действия */
.quick-actions-section {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border: none;
}

.quick-actions-section .section-title {
  color: white;
}

.actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 1rem;
}

.action-card {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.75rem;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.1);
  text-decoration: none;
  transition: all 0.2s;
  backdrop-filter: blur(10px);
}

.action-card:hover {
  background: rgba(255, 255, 255, 0.2);
  transform: translateY(-2px);
}

.action-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.action-icon.income {
  background: rgba(34, 197, 94, 0.2);
  color: #22c55e;
}

.action-icon.expense {
  background: rgba(239, 68, 68, 0.2);
  color: #ef4444;
}

.action-icon.categories {
  background: rgba(168, 85, 247, 0.2);
  color: #a855f7;
}

.action-icon.reports {
  background: rgba(245, 158, 11, 0.2);
  color: #f59e0b;
}

.action-content {
  flex: 1;
}

.action-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: white;
  margin: 0 0 0.125rem 0;
  line-height: 1.2;
}

.action-description {
  font-size: 0.6875rem;
  color: rgba(255, 255, 255, 0.8);
  margin: 0;
  line-height: 1.2;
}

/* Бюджет */
.budget-section {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}

.budget-usage {
  font-size: 0.875rem;
  font-weight: 600;
  color: #1e293b;
}

.budget-progress {
  margin-bottom: 1rem;
}

.budget-progress .progress-bar {
  height: 8px;
  border-radius: 4px;
  overflow: hidden;
  background-color: #e2e8f0;
  margin-bottom: 0.5rem;
}

.budget-progress .progress-fill {
  height: 100%;
  border-radius: 4px;
  transition: width 0.3s ease;
}

.budget-progress .progress-fill.safe {
  background: #22c55e;
}

.budget-progress .progress-fill.warning {
  background: #f59e0b;
}

.budget-progress .progress-fill.danger {
  background: #ef4444;
}

.progress-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.75rem;
  color: #64748b;
}

.percentage {
  font-weight: 600;
  color: #1e293b;
}

.remaining {
  color: #16a34a;
}

.overspent {
  color: #dc2626;
}

.budget-categories {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.budget-category {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.budget-category-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.budget-category-info .category-color {
  width: 8px;
  height: 8px;
}

.budget-category-info .category-name {
  font-size: 0.75rem;
  font-weight: 500;
  color: #64748b;
}

.budget-category-progress {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.6875rem;
  color: #94a3b8;
}

.category-percentage {
  font-weight: 600;
  color: #1e293b;
}

/* Кнопки */
.btn {
  display: inline-block;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  font-size: 0.875rem;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
}

.btn-primary {
  background: #3b82f6;
  color: white;
}

.btn-primary:hover {
  background: #2563eb;
}

.btn-small {
  padding: 0.375rem 0.75rem;
  font-size: 0.8125rem;
}

/* Адаптивность */
@media (max-width: 768px) {
  .dashboard-header {
    flex-direction: column;
    align-items: stretch;
  }

  .header-right {
    align-items: stretch;
  }

  .select-field {
    width: 100%;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .actions-grid {
    grid-template-columns: 1fr;
  }
}
</style>