<template>
  <div class="dashboard">
    <div class="quick-stats">
      <div class="stat income">
        <h3>Доходы</h3>
        <div class="amount">{{ formatMoney(stats.totalIncome) }}</div>
      </div>
      <div class="stat expense">
        <h3>Расходы</h3>
        <div class="amount">{{ formatMoney(stats.totalExpenses) }}</div>
      </div>
      <div class="stat balance" :class="balanceClass">
        <h3>Баланс</h3>
        <div class="amount">{{ formatMoney(stats.balance) }}</div>
      </div>
    </div>

    <div class="recent-transactions">
      <h2>Последние транзакции</h2>
      <div v-for="transaction in recentTransactions" :key="transaction.id" class="transaction-item">
        <div class="transaction-info">
          <div class="category-color" :style="{ backgroundColor: transaction.category.color }"></div>
          <div>
            <div class="description">{{ transaction.description || 'Без описания' }}</div>
            <div class="category">{{ transaction.category.name }}</div>
          </div>
        </div>
        <div class="amount" :class="transaction.type">
          {{ transaction.type === 'income' ? '+' : '-' }}{{ formatMoney(transaction.amount) }}
        </div>
      </div>
    </div>
  </div>
</template>