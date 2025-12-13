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
            <div class="stat-value">{{ formatMoney(stats.totalIncome) }}</div>
            <div class="stat-label">Общий доход</div>
          </div>
          <div class="hero-stat">
            <div class="stat-value">{{ formatMoney(stats.totalExpenses) }}</div>
            <div class="stat-label">Общие расходы</div>
          </div>
        </div>
      </div>
      <div class="hero-illustration">
        <div class="illustration-circle"></div>
        <div class="illustration-graph"></div>
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
          <div class="stat-trend" v-if="stats.incomeTrend">
            <span :class="stats.incomeTrend >= 0 ? 'trend-up' : 'trend-down'">
              {{ stats.incomeTrend >= 0 ? '↗' : '↘' }}
              {{ Math.abs(stats.incomeTrend) }}%
            </span>
          </div>
        </div>
        <div class="stat-card-content">
          <h3 class="stat-title">Доходы</h3>
          <div class="stat-amount">{{ formatMoney(stats.totalIncome) }}</div>
          <div class="stat-period">текущий месяц</div>
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
          <div class="stat-trend" v-if="stats.expenseTrend">
            <span :class="stats.expenseTrend <= 0 ? 'trend-up' : 'trend-down'">
              {{ stats.expenseTrend <= 0 ? '↘' : '↗' }}
              {{ Math.abs(stats.expenseTrend) }}%
            </span>
          </div>
        </div>
        <div class="stat-card-content">
          <h3 class="stat-title">Расходы</h3>
          <div class="stat-amount">{{ formatMoney(stats.totalExpenses) }}</div>
          <div class="stat-period">текущий месяц</div>
        </div>
      </div>

      <div class="stat-card stat-balance" :class="balanceClass">
        <div class="stat-card-header">
          <div class="stat-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
              <path d="M8 14S9.5 16 12 16s4-2 4-2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <line x1="9" y1="9" x2="9.01" y2="9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <line x1="15" y1="9" x2="15.01" y2="9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="stat-trend">
            <span :class="balanceClass">
              {{ stats.balance >= 0 ? '😊' : '😔' }}
            </span>
          </div>
        </div>
        <div class="stat-card-content">
          <h3 class="stat-title">Баланс</h3>
          <div class="stat-amount">{{ formatMoney(stats.balance) }}</div>
          <div class="stat-period">текущий остаток</div>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions-section">
      <h2 class="section-title">Быстрые действия</h2>
      <div class="actions-grid">
        <router-link to="/transactions/add" class="action-card action-primary">
          <div class="action-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 5V19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="action-content">
            <h4>Добавить транзакцию</h4>
            <p>Записать новый доход или расход</p>
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
            <p>Управление категориями расходов</p>
          </div>
          <div class="action-arrow">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
          </div>
        </router-link>

        <router-link to="/analytics" class="action-card">
          <div class="action-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <polyline points="3.27 6.96 12 12.01 20.73 6.96" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <line x1="12" y1="22.08" x2="12" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="action-content">
            <h4>Аналитика</h4>
            <p>Статистика и финансовые отчеты</p>
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
        <router-link to="/transactions/add" class="btn btn-primary btn-large">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 5v14M5 12h14"/>
          </svg>
          Добавить первую транзакцию
        </router-link>
      </div>

      <div v-else class="transactions-grid">
        <div
            v-for="transaction in recentTransactions"
            :key="transaction.id"
            class="transaction-card"
            @click="viewTransaction(transaction)"
        >
          <div class="transaction-header">
            <div class="transaction-category" :style="{ backgroundColor: transaction.category.color }">
              {{ transaction.category.name.charAt(0) }}
            </div>
            <div class="transaction-type" :class="transaction.type">
              <span>{{ transaction.type === 'income' ? 'Доход' : 'Расход' }}</span>
            </div>
          </div>

          <div class="transaction-content">
            <h4 class="transaction-description">
              {{ transaction.description || 'Без описания' }}
            </h4>
            <div class="transaction-meta">
              <span class="transaction-date">{{ formatDate(transaction.date) }}</span>
              <span class="transaction-category-name" :style="{ color: transaction.category.color }">
                {{ transaction.category.name }}
              </span>
            </div>
          </div>

          <div class="transaction-amount" :class="transaction.type">
            <span class="amount-sign">{{ transaction.type === 'income' ? '+' : '-' }}</span>
            {{ formatMoney(transaction.amount) }}
          </div>
        </div>
      </div>
    </div>

    <!-- Recommendations -->
    <div v-if="recommendations.length > 0" class="recommendations-section">
      <div class="section-header">
        <h2 class="section-title">Рекомендации</h2>
        <div class="recommendations-count">{{ recommendations.length }}</div>
      </div>

      <div class="recommendations-grid">
        <div
            v-for="(rec, index) in recommendations"
            :key="index"
            class="recommendation-card"
            :class="rec.type"
        >
          <div class="recommendation-icon">
            <svg v-if="rec.type === 'critical'" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 9V12M12 15H12.01M5.07183 19H18.9282C20.4678 19 21.4301 17.3333 20.6603 16L13.7321 4C12.9623 2.66667 11.0377 2.66667 10.2679 4L3.33975 16C2.56995 17.3333 3.53223 19 5.07183 19Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <svg v-else-if="rec.type === 'warning'" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 8V12M12 16H12.01M22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <svg v-else width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M22 11.08V12C21.9988 14.1564 21.3005 16.2547 20.0093 17.9818C18.7182 19.709 16.9033 20.9725 14.8354 21.5839C12.7674 22.1953 10.5573 22.1219 8.53447 21.3746C6.51168 20.6273 4.78465 19.2461 3.61096 17.4371C2.43727 15.628 1.87979 13.4881 2.02168 11.3363C2.16356 9.18455 2.99721 7.13631 4.39828 5.49706C5.79935 3.85781 7.69279 2.71537 9.79619 2.24013C11.8996 1.7649 14.1003 1.98232 16.07 2.85999" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M22 4L12 14.01L9 11.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>

          <div class="recommendation-content">
            <h4>{{ rec.title }}</h4>
            <p>{{ rec.message }}</p>
          </div>

          <div class="recommendation-action" v-if="rec.action">
            <button class="btn btn-small" :class="rec.type === 'success' ? 'btn-primary' : 'btn-secondary'">
              {{ rec.action }}
            </button>
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
      balance: 0,
      incomeTrend: null,
      expenseTrend: null
    })

    const recentTransactions = ref([])
    const recommendations = ref([])
    const loading = ref(true)

    const balanceClass = computed(() => {
      if (stats.value.balance > 0) return 'positive'
      if (stats.value.balance < 0) return 'negative'
      return 'neutral'
    })

    const fetchDashboardData = async () => {
      try {
        loading.value = true

        const currentDate = new Date()
        const params = {
          month: currentDate.getMonth() + 1,
          year: currentDate.getFullYear()
        }

        const [analyticsResponse, transactionsResponse] = await Promise.all([
          axios.get('/api/analytics/overview', { params }),
          axios.get('/api/transactions/recent', {
            params: { limit: 4 }
          })
        ])

        const analytics = analyticsResponse.data.data || {}
        stats.value = {
          totalIncome: analytics.total_income || 0,
          totalExpenses: analytics.total_expenses || 0,
          balance: analytics.balance || 0,
          incomeTrend: analytics.income_trend || null,
          expenseTrend: analytics.expense_trend || null
        }

        recommendations.value = analytics.recommendations || []
        recentTransactions.value = transactionsResponse.data.data || []

      } catch (error) {
        console.error('Error fetching dashboard data:', error)
        // Mock data for development
        stats.value = {
          totalIncome: 125000,
          totalExpenses: 87500,
          balance: 37500,
          incomeTrend: 12.5,
          expenseTrend: -5.3
        }

        recommendations.value = [
          {
            type: 'success',
            title: 'Отличный баланс!',
            message: 'Ваши доходы превышают расходы на 30%',
            action: 'Посмотреть детали'
          },
          {
            type: 'warning',
            title: 'Высокие траты на еду',
            message: 'Вы потратили 45% бюджета на еду в этом месяце',
            action: 'Скорректировать'
          }
        ]

        recentTransactions.value = [
          {
            id: 1,
            description: 'Зарплата',
            amount: 100000,
            type: 'income',
            date: new Date().toISOString(),
            category: { name: 'Зарплата', color: '#10b981' }
          },
          {
            id: 2,
            description: 'Супермаркет',
            amount: 7500,
            type: 'expense',
            date: new Date().toISOString(),
            category: { name: 'Продукты', color: '#f59e0b' }
          }
        ]
      } finally {
        loading.value = false
      }
    }

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

    const viewTransaction = (transaction) => {
      router.push(`/transactions/${transaction.id}`)
    }

    onMounted(() => {
      fetchDashboardData()
    })

    return {
      stats,
      recentTransactions,
      recommendations,
      loading,
      balanceClass,
      formatMoney,
      formatDate,
      viewTransaction
    }
  }
}
</script>

<style scoped>
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

.hero-stat .stat-label {
  font-size: 0.875rem;
  opacity: 0.8;
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

.stat-balance.positive::before {
  background: linear-gradient(90deg, #3b82f6 0%, #60a5fa 100%);
}

.stat-balance.negative::before {
  background: linear-gradient(90deg, #ef4444 0%, #f87171 100%);
}

.stat-balance.neutral::before {
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

.stat-balance.positive .stat-icon {
  background: rgba(59, 130, 246, 0.1);
  color: #3b82f6;
}

.stat-balance.negative .stat-icon {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.stat-balance.neutral .stat-icon {
  background: rgba(100, 116, 139, 0.1);
  color: #64748b;
}

.stat-trend {
  font-size: 0.875rem;
  font-weight: 600;
}

.trend-up {
  color: #10b981;
}

.trend-down {
  color: #ef4444;
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

.stat-balance.positive .stat-amount {
  color: #3b82f6;
}

.stat-balance.negative .stat-amount {
  color: #ef4444;
}

.stat-balance.neutral .stat-amount {
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

.action-card.action-primary {
  border-color: #3b82f6;
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(59, 130, 246, 0.02) 100%);
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

.action-card.action-primary .action-icon {
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

.transactions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.25rem;
}

.transaction-card {
  background: white;
  border-radius: 12px;
  padding: 1.25rem;
  border: 1px solid #e2e8f0;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
  overflow: hidden;
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
  margin-bottom: 1rem;
}

.transaction-category {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 600;
  font-size: 0.75rem;
}

.transaction-type {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.25rem 0.5rem;
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

.transaction-description {
  font-size: 1rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 0.75rem 0;
  line-height: 1.3;
}

.transaction-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.75rem;
  color: #64748b;
}

.transaction-category-name {
  font-weight: 600;
}

.transaction-amount {
  position: absolute;
  bottom: 1.25rem;
  right: 1.25rem;
  font-size: 1.125rem;
  font-weight: 700;
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

/* Recommendations */
.recommendations-section {
  margin-bottom: 2.5rem;
}

.recommendations-count {
  background: #3b82f6;
  color: white;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
}

.recommendations-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.25rem;
}

.recommendation-card {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid #e2e8f0;
  display: flex;
  gap: 1rem;
  align-items: flex-start;
}

.recommendation-card.critical {
  border-left: 4px solid #ef4444;
  background: linear-gradient(90deg, rgba(239, 68, 68, 0.05) 0%, transparent 100%);
}

.recommendation-card.warning {
  border-left: 4px solid #f59e0b;
  background: linear-gradient(90deg, rgba(245, 158, 11, 0.05) 0%, transparent 100%);
}

.recommendation-card.success {
  border-left: 4px solid #10b981;
  background: linear-gradient(90deg, rgba(16, 185, 129, 0.05) 0%, transparent 100%);
}

.recommendation-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.recommendation-card.critical .recommendation-icon {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.recommendation-card.warning .recommendation-icon {
  background: rgba(245, 158, 11, 0.1);
  color: #f59e0b;
}

.recommendation-card.success .recommendation-icon {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.recommendation-content {
  flex: 1;
}

.recommendation-content h4 {
  font-size: 1rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 0.5rem 0;
}

.recommendation-content p {
  font-size: 0.875rem;
  color: #64748b;
  margin: 0;
  line-height: 1.4;
}

.recommendation-action {
  flex-shrink: 0;
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

  .dashboard-stats {
    grid-template-columns: 1fr;
  }

  .actions-grid {
    grid-template-columns: 1fr;
  }

  .transactions-grid {
    grid-template-columns: 1fr;
  }

  .recommendations-grid {
    grid-template-columns: 1fr;
  }
}
</style>