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
            <h3 class="period-stat-month">{{ formatMonth(monthStat.month || monthStat.period) }}</h3>
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
              <div class="period-stat-amount">{{ formatMoney(monthStat.expenses || monthStat.expense || 0) }}</div>
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
            <div class="categories-container">
              <div
                  v-for="cat in transaction.categories"
                  :key="cat.id"
                  class="category-badge"
                  :style="{ backgroundColor: cat.color + '20' }"
              >
                <div class="category-color" :style="{ backgroundColor: cat.color }"></div>
                <span class="category-name">{{ cat.name }}</span>
              </div>
              <div v-if="!transaction.categories || transaction.categories.length === 0" class="category-badge">
                <div class="category-color" :style="{ backgroundColor: '#94a3b8' }"></div>
                <span class="category-name">Неизвестно</span>
              </div>
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

    const monthlyTrends = ref([])
    const recentTransactions = ref([])
    const loading = ref(true)
    const error = ref(null)

    // Получаем последние 3 месяца из трендов
    const last3Months = computed(() => {
      if (!monthlyTrends.value || monthlyTrends.value.length === 0) return []

      const lastThree = monthlyTrends.value.slice(-3).map(month => {
        // API возвращает 'expense', но мы используем 'expenses' в шаблоне
        const expenses = month.expenses || month.expense || 0
        const income = month.income || 0
        const total = income + expenses
        const incomePercentage = total > 0 ? (income / total) * 100 : 0
        const expensesPercentage = total > 0 ? (expenses / total) * 100 : 0

        return {
          ...month,
          income,
          expenses, // Нормализуем к expenses
          balance: month.balance || (income - expenses),
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

        console.log('Загрузка данных дашборда...')

        // Используем fetch с credentials для работы с сессиями
        const headers = {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }

        // 1. Статистика за текущий месяц
        const summaryUrl = '/api/transactions/summary'
        const summaryResponse = await fetch(summaryUrl, {
          headers,
          credentials: 'include'
        })

        // 2. Последние транзакции
        const recentUrl = '/api/transactions/recent?limit=6'
        const recentResponse = await fetch(recentUrl, {
          headers,
          credentials: 'include'
        })

        // 3. Тренды по месяцам
        const trendsUrl = '/api/analytics/monthly-trends?months=12'
        const trendsResponse = await fetch(trendsUrl, {
          headers,
          credentials: 'include'
        })

        // 4. Все транзакции для расчета общей статистики
        const allTransactionsUrl = '/api/transactions?limit=1000'
        const allTransactionsResponse = await fetch(allTransactionsUrl, {
          headers,
          credentials: 'include'
        })

        // Проверяем статусы ответов перед парсингом
        if (summaryResponse.status === 401 || recentResponse.status === 401 || 
            trendsResponse.status === 401 || allTransactionsResponse.status === 401) {
          throw new Error('Unauthorized')
        }

        if (!summaryResponse.ok || !recentResponse.ok || !trendsResponse.ok || !allTransactionsResponse.ok) {
          console.error('Ошибки ответов:', {
            summary: { status: summaryResponse.status, ok: summaryResponse.ok },
            recent: { status: recentResponse.status, ok: recentResponse.ok },
            trends: { status: trendsResponse.status, ok: trendsResponse.ok },
            allTransactions: { status: allTransactionsResponse.status, ok: allTransactionsResponse.ok }
          })
        }

        // Парсим JSON ответы
        const [summaryData, recentData, trendsData, allTransactionsData] = await Promise.all([
          summaryResponse.json().catch((e) => {
            console.error('Ошибка парсинга summary:', e)
            return { status: 'error', data: null }
          }),
          recentResponse.json().catch((e) => {
            console.error('Ошибка парсинга recent:', e)
            return { status: 'error', data: [] }
          }),
          trendsResponse.json().catch((e) => {
            console.error('Ошибка парсинга trends:', e)
            return { status: 'error', data: [] }
          }),
          allTransactionsResponse.json().catch((e) => {
            console.error('Ошибка парсинга allTransactions:', e)
            return { status: 'error', data: [] }
          })
        ])

        console.log('Ответы от сервера (RAW):', {
          summary: summaryData,
          recent: recentData,
          trends: trendsData,
          allTransactions: allTransactionsData
        })

        // Обрабатываем статистику за месяц
        if (summaryData.status === 'success' && summaryData.data) {
          const monthlyStats = summaryData.data
          stats.value.monthlyIncome = parseFloat(monthlyStats.income) || 0
          stats.value.monthlyExpenses = parseFloat(monthlyStats.expenses) || 0
          stats.value.monthlyBalance = parseFloat(monthlyStats.balance) || 0
          console.log('Статистика за месяц установлена:', {
            income: stats.value.monthlyIncome,
            expenses: stats.value.monthlyExpenses,
            balance: stats.value.monthlyBalance
          })
        } else {
          console.warn('Не удалось получить статистику за месяц:', summaryData)
        }

        // Обрабатываем тренды по месяцам
        if (trendsData.status === 'success' && trendsData.data) {
          const trendsResult = trendsData.data
          if (Array.isArray(trendsResult)) {
            monthlyTrends.value = trendsResult
            console.log('Тренды установлены:', trendsResult.length, 'месяцев')
          } else {
            console.warn('Тренды не являются массивом:', trendsResult)
          }
        } else {
          console.warn('Не удалось получить тренды:', trendsData)
        }

        // Рассчитываем общую статистику из всех транзакций
        let allTransactions = []
        if (allTransactionsData.status === 'success' && allTransactionsData.data) {
          allTransactions = Array.isArray(allTransactionsData.data) 
            ? allTransactionsData.data 
            : []
        } else {
          console.warn('Не удалось получить все транзакции:', allTransactionsData)
        }

        console.log('Всего транзакций получено:', allTransactions.length)

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

        console.log('Общая статистика рассчитана:', {
          totalIncome,
          totalExpenses,
          totalBalance: totalIncome - totalExpenses,
          transactionsCount: allTransactions.length
        })

        // Обрабатываем последние транзакции
        let recentTransactionsData = []
        if (recentData.status === 'success' && recentData.data) {
          recentTransactionsData = Array.isArray(recentData.data) 
            ? recentData.data 
            : []
        } else {
          console.warn('Не удалось получить последние транзакции:', recentData)
        }

        recentTransactions.value = recentTransactionsData.map(t => {
          let categories = []
          if (t.categories && Array.isArray(t.categories) && t.categories.length > 0) {
            categories = t.categories.map(cat => ({
              id: cat.id,
              name: cat.name,
              color: cat.color
            }))
          }
          return {
            id: t.id,
            amount: t.amount,
            type: t.type,
            description: t.description,
            date: t.date,
            categories: categories
          }
        })

        console.log('Последние транзакции обработаны:', recentTransactions.value.length)
        
        // Финальная проверка данных
        console.log('Финальное состояние stats:', {
          monthlyIncome: stats.value.monthlyIncome,
          monthlyExpenses: stats.value.monthlyExpenses,
          monthlyBalance: stats.value.monthlyBalance,
          totalIncome: stats.value.totalIncome,
          totalExpenses: stats.value.totalExpenses,
          monthlyTrendsCount: monthlyTrends.value.length,
          recentTransactionsCount: recentTransactions.value.length
        })

      } catch (err) {
        console.error('Error fetching dashboard data:', err)

        // Если ошибка 401 - пользователь не авторизован
        if (err.message && (err.message.includes('401') || err.message.includes('Unauthorized'))) {
          console.log('Пользователь не авторизован')
          error.value = 'Требуется авторизация'
        } else {
          error.value = 'Ошибка загрузки данных: ' + (err.message || 'Неизвестная ошибка')
        }

        // Очищаем данные при ошибке
        stats.value = {
          totalIncome: 0,
          totalExpenses: 0,
          monthlyIncome: 0,
          monthlyExpenses: 0,
          monthlyBalance: 0
        }

        monthlyTrends.value = []
        recentTransactions.value = []

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
@import '../css/home.css';
</style>