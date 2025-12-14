<template>
  <div class="home">
    <!-- Hero Section -->
    <div class="hero-section">
      <div class="hero-content">
        <h1 class="hero-title">
          <span class="hero-icon">💰</span>
          Управление финансами
        </h1>
        <p class="hero-subtitle">Контролируйте доходы и расходы с умом</p>
        <div class="hero-stats">
          <div class="hero-stat">
            <div class="stat-value">{{ formatMoney(stats.monthlyIncome) }}</div>
            <div class="stat-label">Доходы за месяц</div>
          </div>
          <div class="hero-stat">
            <div class="stat-value">{{ formatMoney(stats.monthlyExpenses) }}</div>
            <div class="stat-label">Расходы за месяц</div>
          </div>
          <div class="hero-stat">
            <div class="stat-value" :class="balanceClass">{{ formatMoney(stats.monthlyBalance) }}</div>
            <div class="stat-label">Баланс месяца</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Last 3 Months Stats -->
    <div class="period-stats-section" v-if="monthlyTrends.length > 0">
      <h2 class="section-title">Статистика за последние 3 месяца</h2>
      <div class="period-stats-grid">
        <div class="period-stat-card" v-for="(monthStat, index) in last3Months" :key="index">
          <div class="period-stat-header">
            <h3 class="period-stat-month">{{ formatMonth(monthStat.period) }}</h3>
            <div class="period-stat-total" :class="getBalanceClass(monthStat.balance)">
              {{ formatMoney(monthStat.balance) }}
            </div>
          </div>

          <div class="period-stat-details">
            <div class="period-stat-item income">
              <div class="period-stat-label">
                <div class="period-stat-dot income"></div>
                Доходы
              </div>
              <div class="period-stat-amount">{{ formatMoney(monthStat.income) }}</div>
            </div>

            <div class="period-stat-item expense">
              <div class="period-stat-label">
                <div class="period-stat-dot expense"></div>
                Расходы
              </div>
              <div class="period-stat-amount">{{ formatMoney(monthStat.expenses) }}</div>
            </div>
          </div>

          <div class="period-stat-progress">
            <div class="progress-bar">
              <div class="progress-income" :style="{ width: monthStat.incomePercentage + '%' }"></div>
              <div class="progress-expense" :style="{ width: monthStat.expensesPercentage + '%' }"></div>
            </div>
            <div class="progress-labels">
              <span>{{ Math.round(monthStat.incomePercentage) }}% доходы</span>
              <span>{{ Math.round(monthStat.expensesPercentage) }}% расходы</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Stats Dashboard -->
    <div class="dashboard-stats">
      <div class="stat-card stat-income">
        <div class="stat-card-header">
          <div class="stat-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 2V22" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M17 5L12 10L7 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
        </div>
        <div class="stat-card-content">
          <h3 class="stat-title">Все доходы</h3>
          <div class="stat-amount">{{ formatMoney(stats.totalIncome) }}</div>
          <div class="stat-period">за все время</div>
        </div>
      </div>

      <div class="stat-card stat-expense">
        <div class="stat-card-header">
          <div class="stat-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 22V2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M7 19L12 14L17 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
        </div>
        <div class="stat-card-content">
          <h3 class="stat-title">Все расходы</h3>
          <div class="stat-amount">{{ formatMoney(stats.totalExpenses) }}</div>
          <div class="stat-period">за все время</div>
        </div>
      </div>

      <div class="stat-card stat-total-balance" :class="totalBalanceClass">
        <div class="stat-card-header">
          <div class="stat-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
              <path d="M8 14S9.5 16 12 16s4-2 4-2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <line x1="9" y1="9" x2="9.01" y2="9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <line x1="15" y1="9" x2="15.01" y2="9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
        </div>
        <div class="stat-card-content">
          <h3 class="stat-title">Общий баланс</h3>
          <div class="stat-amount">{{ formatMoney(totalBalance) }}</div>
          <div class="stat-period">за все время</div>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions-section">
      <h2 class="section-title">Быстрые действия</h2>
      <div class="actions-grid">
        <router-link to="/transactions/create?type=income" class="action-card">
          <div class="action-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 5V19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="action-content">
            <h4>Добавить доход</h4>
            <p>Записать поступление средств</p>
          </div>
          <div class="action-arrow">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
          </div>
        </router-link>

        <router-link to="/transactions/create?type=expense" class="action-card">
          <div class="action-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <line x1="5" y1="12" x2="19" y2="12" stroke="currentColor" stroke-width="2"/>
            </svg>
          </div>
          <div class="action-content">
            <h4>Добавить расход</h4>
            <p>Записать трату средств</p>
          </div>
          <div class="action-arrow">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
          </div>
        </router-link>

        <router-link to="/transactions" class="action-card">
          <div class="action-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect x="1" y="4" width="22" height="16" rx="2" stroke="currentColor" stroke-width="2"/>
              <line x1="1" y1="10" x2="23" y2="10" stroke="currentColor" stroke-width="2"/>
            </svg>
          </div>
          <div class="action-content">
            <h4>Все транзакции</h4>
            <p>Просмотр истории операций</p>
          </div>
          <div class="action-arrow">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
          </div>
        </router-link>

        <router-link to="/categories" class="action-card">
          <div class="action-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <line x1="7" y1="7" x2="7.01" y2="7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="action-content">
            <h4>Категории</h4>
            <p>Управление категориями</p>
          </div>
          <div class="action-arrow">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
          </div>
        </router-link>
      </div>
    </div>

    <!-- Recent Transactions -->
    <div class="recent-transactions-section">
      <div class="section-header">
        <h2 class="section-title">Последние транзакции</h2>
        <router-link to="/transactions" class="section-link">
          Все транзакции
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
        </router-link>
      </div>

      <div v-if="loading" class="loading-state">
        <div class="loading-spinner"></div>
        <p>Загрузка данных...</p>
      </div>

      <div v-else-if="recentTransactions.length === 0" class="empty-state">
        <div class="empty-icon">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="1" y="4" width="22" height="16" rx="2"/>
            <line x1="1" y1="10" x2="23" y2="10"/>
          </svg>
        </div>
        <h3>Транзакций пока нет</h3>
        <p>Начните отслеживать свои финансы прямо сейчас</p>
        <router-link to="/transactions/create" class="btn btn-primary btn-large">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 5v14M5 12h14"/>
          </svg>
          Добавить первую транзакцию
        </router-link>
      </div>

      <div v-else class="transactions-list">
        <div
            v-for="transaction in recentTransactions"
            :key="transaction.id"
            class="transaction-card"
            @click="editTransaction(transaction)"
        >
          <div class="transaction-header">
            <div class="category-badge" :style="{ backgroundColor: transaction.category.color + '20' }">
              <div class="category-color" :style="{ backgroundColor: transaction.category.color }"></div>
              <span class="category-name">{{ transaction.category.name }}</span>
            </div>
            <div class="transaction-type" :class="transaction.type">
              <span>{{ transaction.type === 'income' ? 'Доход' : 'Расход' }}</span>
            </div>
          </div>

          <div class="transaction-content">
            <h4 class="transaction-description">
              {{ transaction.description || 'Без описания' }}
            </h4>
            <div class="transaction-date">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
              </svg>
              {{ formatDate(transaction.date) }}
            </div>
          </div>

          <div class="transaction-amount-container">
            <div class="transaction-amount" :class="transaction.type">
              <span class="amount-sign">{{ transaction.type === 'income' ? '+' : '-' }}</span>
              {{ formatMoney(transaction.amount) }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

export default {
  name: 'Home',
  setup() {
    const router = useRouter()

    const stats = ref({
      totalIncome: 0,
      totalExpenses: 0,
      monthlyIncome: 0,
      monthlyExpenses: 0,
      monthlyBalance: 0
    })

    const monthlyTrends = ref([]) // Теперь получаем с сервера
    const recentTransactions = ref([])
    const loading = ref(true)
    const error = ref(null)

    // Получаем последние 3 месяца из трендов
    const last3Months = computed(() => {
      if (!monthlyTrends.value || monthlyTrends.value.length === 0) return []

      const lastThree = monthlyTrends.value.slice(-3).map(month => {
        const total = month.income + month.expenses
        const incomePercentage = total > 0 ? (month.income / total) * 100 : 0
        const expensesPercentage = total > 0 ? (month.expenses / total) * 100 : 0

        return {
          ...month,
          incomePercentage,
          expensesPercentage
        }
      })

      return lastThree
    })

    // Компьютед свойства
    const totalBalance = computed(() => {
      return stats.value.totalIncome - stats.value.totalExpenses
    })

    const balanceClass = computed(() => {
      const balance = stats.value.monthlyBalance
      if (balance > 0) return 'positive'
      if (balance < 0) return 'negative'
      return 'neutral'
    })

    const totalBalanceClass = computed(() => {
      const balance = totalBalance.value
      if (balance > 0) return 'positive'
      if (balance < 0) return 'negative'
      return 'neutral'
    })

    const fetchDashboardData = async () => {
      try {
        loading.value = true
        error.value = null

        console.log('Загрузка данных дашборда с сервера...')

        // Используем готовые эндпоинты с сервера
        const [summaryResponse, recentResponse, trendsResponse, allTransactionsResponse] = await Promise.all([
          // 1. Статистика за текущий месяц (уже есть в TransactionController)
          axios.get('/api/transactions/summary'),

          // 2. Последние транзакции (уже есть в TransactionController)
          axios.get('/api/transactions/recent', {
            params: { limit: 6 }
          }),

          // 3. Тренды по месяцам (из AnalyticsController)
          axios.get('/api/analytics/monthly-trends', {
            params: { months: 12 }
          }),

          // 4. Все транзакции для расчета общей статистики
          axios.get('/api/transactions', { params: { limit: 1000 } })
        ])

        console.log('Статистика за месяц:', summaryResponse.data)
        console.log('Тренды:', trendsResponse.data)
        console.log('Все транзакций:', allTransactionsResponse.data.data?.length || 0)

        // Обрабатываем статистику за месяц
        const monthlyStats = summaryResponse.data.data
        if (monthlyStats) {
          stats.value.monthlyIncome = monthlyStats.income || 0
          stats.value.monthlyExpenses = monthlyStats.expenses || 0
          stats.value.monthlyBalance = monthlyStats.balance || 0
        }

        // Обрабатываем тренды по месяцам
        const trendsData = trendsResponse.data.data
        if (trendsData && trendsData.trends) {
          monthlyTrends.value = trendsData.trends
        }

        // Рассчитываем общую статистику из всех транзакций
        const allTransactions = allTransactionsResponse.data.data || allTransactionsResponse.data || []
        let totalIncome = 0
        let totalExpenses = 0

        allTransactions.forEach(transaction => {
          const amount = parseFloat(transaction.amount) || 0
          if (transaction.type === 'income') {
            totalIncome += amount
          } else if (transaction.type === 'expense') {
            totalExpenses += amount
          }
        })

        stats.value.totalIncome = totalIncome
        stats.value.totalExpenses = totalExpenses

        console.log('Общая статистика:', {
          totalIncome,
          totalExpenses,
          totalBalance: totalIncome - totalExpenses
        })

        // Обрабатываем последние транзакции
        const recentTransactionsData = recentResponse.data.data || recentResponse.data || []
        recentTransactions.value = recentTransactionsData.map(t => ({
          id: t.id,
          amount: t.amount,
          type: t.type,
          description: t.description,
          date: t.date,
          category: {
            id: t.category_id,
            name: t.category?.name || 'Неизвестно',
            color: t.category?.color || '#94a3b8'
          }
        }))

      } catch (err) {
        console.error('Error fetching dashboard data:', err)
        error.value = 'Ошибка загрузки данных. Проверьте подключение к серверу.'

        // Fallback данные для разработки
        stats.value = {
          totalIncome: 12550,
          totalExpenses: 8500,
          monthlyIncome: 3500,
          monthlyExpenses: 4200,
          monthlyBalance: -700
        }

        // Fallback тренды
        const currentDate = new Date()
        monthlyTrends.value = [
          {
            period: `${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}`,
            income: 3500,
            expenses: 4200,
            balance: -700
          },
          {
            period: `${currentDate.getFullYear()}-${String(currentDate.getMonth()).padStart(2, '0')}`,
            income: 4500,
            expenses: 2300,
            balance: 2200
          },
          {
            period: `${currentDate.getFullYear()}-${String(currentDate.getMonth() - 1).padStart(2, '0')}`,
            income: 3000,
            expenses: 2000,
            balance: 1000
          }
        ]

        recentTransactions.value = [
          {
            id: 1,
            description: 'Тестовый доход',
            amount: 50,
            type: 'income',
            date: new Date().toISOString(),
            category: { name: 'Доход', color: '#10b981' }
          },
          {
            id: 2,
            description: 'Тестовый расход',
            amount: 500,
            type: 'expense',
            date: new Date().toISOString(),
            category: { name: 'Расход', color: '#ef4444' }
          }
        ]
      } finally {
        loading.value = false
      }
    }

    const getBalanceClass = (balance) => {
      if (balance > 0) return 'positive'
      if (balance < 0) return 'negative'
      return 'neutral'
    }

    const formatMoney = (amount) => {
      if (amount === null || amount === undefined || isNaN(amount)) return '0 Br'

      // Форматирование в белорусских рублях
      return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
        style: 'decimal'
      }).format(amount) + ' Br'
    }

    const formatDate = (dateString) => {
      if (!dateString) return 'Дата не указана'

      try {
        const date = new Date(dateString)
        const now = new Date()
        const diffTime = Math.abs(now - date)
        const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24))

        // Если сегодня
        if (date.toDateString() === now.toDateString()) {
          return 'Сегодня'
        }

        // Если вчера
        const yesterday = new Date(now)
        yesterday.setDate(yesterday.getDate() - 1)
        if (date.toDateString() === yesterday.toDateString()) {
          return 'Вчера'
        }

        // Если в течение недели
        if (diffDays <= 7) {
          return date.toLocaleDateString('ru-RU', {
            weekday: 'short',
            day: 'numeric'
          })
        }

        // Более недели назад
        return date.toLocaleDateString('ru-RU', {
          day: 'numeric',
          month: 'short',
          year: diffDays > 365 ? 'numeric' : undefined
        })
      } catch (error) {
        console.error('Error formatting date:', error, dateString)
        return 'Неверная дата'
      }
    }

    const formatMonth = (monthString) => {
      const [year, month] = monthString.split('-')
      const date = new Date(year, parseInt(month) - 1, 1)
      return date.toLocaleDateString('ru-RU', {
        month: 'long',
        year: 'numeric'
      })
    }

    const editTransaction = (transaction) => {
      router.push(`/transactions/edit/${transaction.id}`)
    }

    onMounted(async () => {
      await fetchDashboardData()
    })

    return {
      stats,
      monthlyTrends,
      last3Months,
      recentTransactions,
      loading,
      error,
      totalBalance,
      balanceClass,
      totalBalanceClass,
      getBalanceClass,
      formatMoney,
      formatDate,
      formatMonth,
      editTransaction
    }
  }
}
</script>

<style scoped>
/* Стили остаются такими же как в предыдущем варианте */
.home {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1.5rem 2rem;
}

/* Hero Section */
.hero-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 3rem 1rem;
  margin-bottom: 2rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 20px;
  color: white;
  position: relative;
  overflow: hidden;
}

.hero-section::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.1) 0%, transparent 50%);
}

.hero-content {
  position: relative;
  z-index: 1;
  max-width: 800px;
}

.hero-title {
  font-size: 2.5rem;
  font-weight: 800;
  margin-bottom: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.hero-icon {
  font-size: 3rem;
}

.hero-subtitle {
  font-size: 1.25rem;
  opacity: 0.9;
  margin-bottom: 2rem;
  font-weight: 300;
}

.hero-stats {
  display: flex;
  justify-content: center;
  gap: 3rem;
  margin-top: 2rem;
}

.hero-stat {
  text-align: center;
}

.hero-stat .stat-value {
  font-size: 1.75rem;
  font-weight: 700;
  margin-bottom: 0.25rem;
}

.hero-stat .stat-value.positive {
  color: #4ade80;
}

.hero-stat .stat-value.negative {
  color: #f87171;
}

.hero-stat .stat-value.neutral {
  color: #e2e8f0;
}

.hero-stat .stat-label {
  font-size: 0.875rem;
  opacity: 0.8;
}

/* Period Stats Section */
.period-stats-section {
  margin-bottom: 2.5rem;
}

.period-stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
}

.period-stat-card {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e2e8f0;
  transition: transform 0.2s, box-shadow 0.2s;
}

.period-stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.period-stat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.25rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #e2e8f0;
}

.period-stat-month {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.period-stat-total {
  font-size: 1.25rem;
  font-weight: 700;
}

.period-stat-total.positive {
  color: #16a34a;
}

.period-stat-total.negative {
  color: #dc2626;
}

.period-stat-total.neutral {
  color: #64748b;
}

.period-stat-details {
  margin-bottom: 1.25rem;
}

.period-stat-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.period-stat-item:last-child {
  margin-bottom: 0;
}

.period-stat-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #64748b;
}

.period-stat-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.period-stat-dot.income {
  background: #10b981;
}

.period-stat-dot.expense {
  background: #ef4444;
}

.period-stat-amount {
  font-size: 1rem;
  font-weight: 600;
}

.period-stat-item.income .period-stat-amount {
  color: #10b981;
}

.period-stat-item.expense .period-stat-amount {
  color: #ef4444;
}

.period-stat-progress {
  margin-top: 1.25rem;
}

.progress-bar {
  height: 8px;
  background: #e2e8f0;
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 0.5rem;
  display: flex;
}

.progress-income {
  background: #10b981;
  height: 100%;
  transition: width 0.3s ease;
}

.progress-expense {
  background: #ef4444;
  height: 100%;
  transition: width 0.3s ease;
}

.progress-labels {
  display: flex;
  justify-content: space-between;
  font-size: 0.75rem;
  color: #64748b;
}

/* Dashboard Stats */
.dashboard-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2.5rem;
}

.stat-card {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e2e8f0;
  transition: transform 0.2s, box-shadow 0.2s;
  position: relative;
  overflow: hidden;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.stat-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
}

.stat-income::before {
  background: linear-gradient(90deg, #10b981 0%, #34d399 100%);
}

.stat-expense::before {
  background: linear-gradient(90deg, #ef4444 0%, #f87171 100%);
}

.stat-total-balance.positive::before {
  background: linear-gradient(90deg, #16a34a 0%, #22c55e 100%);
}

.stat-total-balance.negative::before {
  background: linear-gradient(90deg, #ef4444 0%, #f87171 100%);
}

.stat-total-balance.neutral::before {
  background: linear-gradient(90deg, #64748b 0%, #94a3b8 100%);
}

.stat-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stat-income .stat-icon {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.stat-expense .stat-icon {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.stat-total-balance.positive .stat-icon {
  background: rgba(22, 163, 74, 0.1);
  color: #16a34a;
}

.stat-total-balance.negative .stat-icon {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.stat-total-balance.neutral .stat-icon {
  background: rgba(100, 116, 139, 0.1);
  color: #64748b;
}

.stat-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 0.5rem;
}

.stat-amount {
  font-size: 2rem;
  font-weight: 700;
  color: #1e293b;
  line-height: 1.2;
  margin-bottom: 0.25rem;
}

.stat-total-balance.positive .stat-amount {
  color: #16a34a;
}

.stat-total-balance.negative .stat-amount {
  color: #dc2626;
}

.stat-total-balance.neutral .stat-amount {
  color: #64748b;
}

.stat-period {
  font-size: 0.875rem;
  color: #94a3b8;
}

/* Quick Actions */
.quick-actions-section {
  margin-bottom: 2.5rem;
}

.section-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 1.5rem;
}

.actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.25rem;
}

.action-card {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  text-decoration: none;
  color: inherit;
  border: 2px solid #e2e8f0;
  transition: all 0.2s;
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  position: relative;
}

.action-card:hover {
  border-color: #3b82f6;
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.action-icon {
  width: 48px;
  height: 48px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f1f5f9;
  color: #475569;
  flex-shrink: 0;
}

.action-card:hover .action-icon {
  background: #3b82f6;
  color: white;
}

.action-content {
  flex: 1;
}

.action-content h4 {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 0.25rem 0;
}

.action-content p {
  font-size: 0.875rem;
  color: #64748b;
  margin: 0;
  line-height: 1.4;
}

.action-arrow {
  color: #94a3b8;
  opacity: 0;
  transition: opacity 0.2s;
}

.action-card:hover .action-arrow {
  opacity: 1;
}

/* Recent Transactions */
.recent-transactions-section {
  margin-bottom: 2.5rem;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.section-link {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: #3b82f6;
  text-decoration: none;
  transition: color 0.2s;
}

.section-link:hover {
  color: #2563eb;
}

.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1rem;
  text-align: center;
  background: white;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #e2e8f0;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 1rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.empty-icon {
  width: 64px;
  height: 64px;
  margin-bottom: 1rem;
  color: #94a3b8;
}

.empty-state h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 0.5rem;
}

.empty-state p {
  color: #64748b;
  margin-bottom: 1.5rem;
}

/* Transactions List */
.transactions-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.transaction-card {
  background: white;
  border-radius: 12px;
  padding: 1.25rem;
  border: 1px solid #e2e8f0;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.transaction-card:hover {
  border-color: #3b82f6;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}

.transaction-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.category-badge {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0.75rem;
  border-radius: 20px;
  background: #f8fafc;
}

.category-color {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}

.category-name {
  font-size: 0.75rem;
  font-weight: 600;
  color: #475569;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 120px;
}

.transaction-type {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
}

.transaction-type.income {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.transaction-type.expense {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.transaction-content {
  flex: 1;
}

.transaction-description {
  font-size: 1rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 0.5rem 0;
  line-height: 1.3;
  word-break: break-word;
}

.transaction-date {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.75rem;
  color: #64748b;
  font-weight: 500;
}

.transaction-date svg {
  flex-shrink: 0;
}

.transaction-amount-container {
  text-align: right;
}

.transaction-amount {
  font-size: 1.125rem;
  font-weight: 700;
  white-space: nowrap;
}

.transaction-amount.income {
  color: #10b981;
}

.transaction-amount.expense {
  color: #ef4444;
}

.amount-sign {
  font-size: 0.875rem;
  font-weight: 600;
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.625rem 1.25rem;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 600;
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

.btn-secondary {
  background: #f1f5f9;
  color: #475569;
}

.btn-secondary:hover {
  background: #e2e8f0;
}

.btn-large {
  padding: 0.875rem 1.75rem;
  font-size: 1rem;
}

.btn-small {
  padding: 0.375rem 0.75rem;
  font-size: 0.8125rem;
}

/* Responsive Design */
@media (max-width: 768px) {
  .home {
    padding: 0 1rem 1.5rem;
  }

  .hero-section {
    padding: 2rem 1rem;
    border-radius: 16px;
  }

  .hero-title {
    font-size: 1.75rem;
    flex-direction: column;
    gap: 0.25rem;
  }

  .hero-icon {
    font-size: 2rem;
  }

  .hero-subtitle {
    font-size: 1rem;
  }

  .hero-stats {
    flex-direction: column;
    gap: 1.5rem;
  }

  .period-stats-grid {
    grid-template-columns: 1fr;
  }

  .dashboard-stats {
    grid-template-columns: 1fr;
  }

  .actions-grid {
    grid-template-columns: 1fr;
  }

  .transactions-list {
    grid-template-columns: 1fr;
  }

  .category-name {
    max-width: 80px;
  }
}
</style>