<template>
  <div class="home">
    <div class="hero">
      <h1>💰 Управление бюджетом</h1>
      <p>Контролируйте свои доходы и расходы в одном месте</p>
    </div>

    <!-- Быстрая статистика -->
    <div class="quick-stats">
      <div class="stat-card income">
        <div class="stat-icon">📈</div>
        <div class="stat-content">
          <h3>Доходы</h3>
          <div class="amount">{{ formatMoney(stats.totalIncome) }}</div>
          <div class="period">за текущий месяц</div>
        </div>
      </div>

      <div class="stat-card expense">
        <div class="stat-icon">📉</div>
        <div class="stat-content">
          <h3>Расходы</h3>
          <div class="amount">{{ formatMoney(stats.totalExpenses) }}</div>
          <div class="period">за текущий месяц</div>
        </div>
      </div>

      <div class="stat-card balance" :class="balanceClass">
        <div class="stat-icon">⚖️</div>
        <div class="stat-content">
          <h3>Баланс</h3>
          <div class="amount">{{ formatMoney(stats.balance) }}</div>
          <div class="period">остаток</div>
        </div>
      </div>
    </div>

    <!-- Быстрые действия -->
    <div class="quick-actions">
      <h2>Быстрые действия</h2>
      <div class="actions-grid">
        <router-link to="/transactions/create" class="action-card">
          <div class="action-icon">➕</div>
          <h4>Добавить транзакцию</h4>
          <p>Новый доход или расход</p>
        </router-link>

        <router-link to="/transactions" class="action-card">
          <div class="action-icon">📋</div>
          <h4>Все транзакции</h4>
          <p>История операций</p>
        </router-link>

        <router-link to="/categories" class="action-card">
          <div class="action-icon">🏷️</div>
          <h4>Категории</h4>
          <p>Управление категориями</p>
        </router-link>

        <router-link to="/analytics" class="action-card">
          <div class="action-icon">📊</div>
          <h4>Аналитика</h4>
          <p>Статистика и отчеты</p>
        </router-link>
      </div>
    </div>

    <!-- Последние транзакции -->
    <div class="recent-transactions">
      <div class="section-header">
        <h2>Последние транзакции</h2>
        <router-link to="/transactions" class="view-all">Все транзакции →</router-link>
      </div>

      <div v-if="loading" class="loading">Загрузка...</div>

      <div v-else-if="recentTransactions.length === 0" class="empty-state">
        <p>Транзакций пока нет</p>
        <router-link to="/transactions/create" class="btn btn-primary">
          Добавить первую транзакцию
        </router-link>
      </div>

      <div v-else class="transactions-list">
        <div
            v-for="transaction in recentTransactions"
            :key="transaction.id"
            class="transaction-item"
        >
          <div class="transaction-main">
            <div
                class="category-color"
                :style="{ backgroundColor: transaction.category.color }"
            ></div>
            <div class="transaction-info">
              <div class="description">
                {{ transaction.description || 'Без описания' }}
              </div>
              <div class="meta">
                <span class="category">{{ transaction.category.name }}</span>
                <span class="date">{{ formatDate(transaction.date) }}</span>
              </div>
            </div>
          </div>
          <div class="amount" :class="transaction.type">
            {{ transaction.type === 'income' ? '+' : '-' }}{{ formatMoney(transaction.amount) }}
          </div>
        </div>
      </div>
    </div>

    <!-- Уведомления и рекомендации -->
    <div v-if="recommendations.length > 0" class="recommendations">
      <h2>Рекомендации</h2>
      <div class="recommendations-list">
        <div
            v-for="(rec, index) in recommendations"
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
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

export default {
  name: 'Home',
  setup() {
    const stats = ref({
      totalIncome: 0,
      totalExpenses: 0,
      balance: 0
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

        // Получаем статистику за текущий месяц
        const currentDate = new Date()
        const params = {
          month: currentDate.getMonth() + 1,
          year: currentDate.getFullYear()
        }

        const [analyticsResponse, transactionsResponse] = await Promise.all([
          axios.get('/api/analytics/overview', { params }),
          axios.get('/api/transactions', {
            params: { ...params, limit: 5 }
          })
        ])

        const analytics = analyticsResponse.data.data
        stats.value = {
          totalIncome: analytics.total_income,
          totalExpenses: analytics.total_expenses,
          balance: analytics.balance
        }

        recommendations.value = analytics.recommendations || []
        recentTransactions.value = transactionsResponse.data.data || []

      } catch (error) {
        console.error('Error fetching dashboard data:', error)
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

    const formatDate = (dateString) => {
      return new Date(dateString).toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'short'
      })
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
      formatDate
    }
  }
}
</script>

<style scoped>
.home {
  max-width: 1200px;
  margin: 0 auto;
  padding: 2rem;
}

.hero {
  text-align: center;
  margin-bottom: 3rem;
  padding: 2rem 0;
}

.hero h1 {
  font-size: 2.5rem;
  margin-bottom: 1rem;
  color: #2c3e50;
}

.hero p {
  font-size: 1.2rem;
  color: #7f8c8d;
}

.quick-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 3rem;
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

.quick-actions {
  margin-bottom: 3rem;
}

.quick-actions h2 {
  margin-bottom: 1.5rem;
  color: #2c3e50;
}

.actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
}

.action-card {
  display: block;
  background: white;
  padding: 1.5rem;
  border-radius: 12px;
  text-decoration: none;
  color: inherit;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
  transition: transform 0.3s, box-shadow 0.3s;
  border: 2px solid transparent;
}

.action-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 20px rgba(0,0,0,0.15);
  border-color: #3498db;
}

.action-icon {
  font-size: 2rem;
  margin-bottom: 1rem;
}

.action-card h4 {
  margin: 0 0 0.5rem 0;
  color: #2c3e50;
}

.action-card p {
  margin: 0;
  color: #7f8c8d;
  font-size: 0.9rem;
}

.recent-transactions {
  background: white;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
  margin-bottom: 2rem;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.section-header h2 {
  margin: 0;
  color: #2c3e50;
}

.view-all {
  color: #3498db;
  text-decoration: none;
  font-weight: 600;
}

.transactions-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.transaction-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  border: 1px solid #ecf0f1;
  border-radius: 8px;
  transition: background-color 0.2s;
}

.transaction-item:hover {
  background-color: #f8f9fa;
}

.transaction-main {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex: 1;
}

.category-color {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}

.transaction-info {
  flex: 1;
}

.description {
  font-weight: 600;
  margin-bottom: 0.25rem;
}

.meta {
  display: flex;
  gap: 1rem;
  font-size: 0.8rem;
  color: #95a5a6;
}

.amount {
  font-weight: 700;
  font-size: 1.1rem;
}

.amount.income {
  color: #27ae60;
}

.amount.expense {
  color: #e74c3c;
}

.recommendations {
  background: white;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.recommendations h2 {
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

.loading {
  text-align: center;
  padding: 3rem;
  color: #7f8c8d;
}

.empty-state {
  text-align: center;
  padding: 3rem;
  color: #7f8c8d;
}

.empty-state p {
  margin-bottom: 1rem;
}
</style>