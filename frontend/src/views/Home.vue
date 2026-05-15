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
            <div class="stat-value">{{ formatMoneyWithCurrency(stats.monthlyIncome, 'BYN') }}</div>
            <div class="stat-label">Доходы за месяц</div>
          </div>
          <div class="hero-stat">
            <div class="stat-value">{{ formatMoneyWithCurrency(stats.monthlyExpenses, 'BYN') }}</div>
            <div class="stat-label">Расходы за месяц</div>
          </div>
          <div class="hero-stat">
            <div class="stat-value" :class="balanceClass">{{ formatMoneyWithCurrency(stats.monthlyBalance, 'BYN') }}</div>
            <div class="stat-label">Баланс месяца</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Last 3 Months Stats -->
    <div class="period-stats-section" v-if="monthlyTrendsProcessed.length > 0">
      <h2 class="section-title">Статистика за последние 3 месяца</h2>
      <div class="period-stats-grid">
        <div class="period-stat-card" v-for="(monthStat, index) in monthlyTrendsProcessed" :key="index">
          <div class="period-stat-header">
            <h3 class="period-stat-month">{{ formatMonth(monthStat.period) }}</h3>
            <div class="period-stat-total" :class="getBalanceClass(monthStat.balance)">
              {{ formatMoneyWithCurrency(monthStat.balance, 'BYN') }}
            </div>
          </div>

          <div class="period-stat-details">
            <div class="period-stat-item income">
              <div class="period-stat-label">
                <div class="period-stat-dot income"></div>
                Доходы
              </div>
              <div class="period-stat-amount">{{ formatMoneyWithCurrency(monthStat.income, 'BYN') }}</div>
            </div>

            <div class="period-stat-item expense">
              <div class="period-stat-label">
                <div class="period-stat-dot expense"></div>
                Расходы
              </div>
              <div class="period-stat-amount">{{ formatMoneyWithCurrency(monthStat.expenses, 'BYN') }}</div>
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
          <div class="stat-amount">{{ formatMoneyWithCurrency(stats.totalIncome, 'BYN') }}</div>
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
          <div class="stat-amount">{{ formatMoneyWithCurrency(stats.totalExpenses, 'BYN') }}</div>
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
          <div class="stat-amount">{{ formatMoneyWithCurrency(totalBalance, 'BYN') }}</div>
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
              {{ formatTransactionMoney(transaction) }}
            </div>
            <div v-if="transaction.currency && transaction.currency.code !== 'BYN' && transaction.exchange_rate" class="transaction-rate">
              1 {{ transaction.currency.code }} = {{ formatMoneyAmount(transaction.exchange_rate) }} Br
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { useHome } from '../composables/useHome.js'

export default {
  name: 'Home',
  setup() {
    return useHome()
  }
}
</script>

<style scoped>
@import '../css/home.css';
</style>