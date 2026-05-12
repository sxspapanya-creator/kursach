<template>
  <div class="transactions-page">
    <!-- Hero Section -->
    <div class="hero-section">
      <div class="hero-content">
        <h1 class="hero-title">
          <span class="hero-icon">📊</span>
          Транзакции
        </h1>
        <p class="hero-subtitle">История всех доходов и расходов</p>
      </div>
    </div>

    <!-- Header with Actions -->
    <div class="section-header">
      <div class="header-content">
        <h2 class="section-title">Все транзакции</h2>
        <div class="header-actions">
          <router-link to="/transactions/create?type=income" class="btn btn-success">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 5v14M5 12h14"/>
            </svg>
            Добавить доход
          </router-link>
          <router-link to="/transactions/create?type=expense" class="btn btn-danger">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 5v14M5 12h14"/>
            </svg>
            Добавить расход
          </router-link>
          <router-link to="/transactions/mass-delete" class="btn btn-danger">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
            </svg>
            Массовое удаление
          </router-link>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-section">
      <div class="filters-grid">
        <div class="filter-group">
          <label class="filter-label">Тип транзакции</label>
          <select v-model="filters.type" @change="fetchTransactions" class="filter-select">
            <option value="">Все типы</option>
            <option value="income">Доходы</option>
            <option value="expense">Расходы</option>
          </select>
        </div>

        <div class="filter-group">
          <label class="filter-label">Месяц</label>
          <input v-model="filters.month" type="month" @change="fetchTransactions" class="filter-input">
        </div>

        <div class="filter-actions">
          <button @click="resetFilters" class="btn btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
              <path d="M3 3v5h5M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/>
              <path d="M16 16h5v5"/>
            </svg>
            Сбросить
          </button>
        </div>
      </div>
    </div>

    <!-- Transactions List -->
    <div class="transactions-section">
      <div v-if="loading" class="loading-state">
        <div class="loading-spinner"></div>
        <p>Загрузка транзакций...</p>
      </div>

      <div v-else-if="transactions.length === 0" class="empty-state">
        <div class="empty-icon">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <rect x="1" y="4" width="22" height="16" rx="2"/>
            <line x1="1" y1="10" x2="23" y2="10"/>
            <circle cx="12" cy="14" r="1"/>
          </svg>
        </div>
        <h3>Транзакций не найдено</h3>
        <p>Попробуйте изменить параметры фильтрации или добавьте новую транзакцию</p>
        <div class="empty-actions">
          <router-link to="/transactions/create" class="btn btn-primary btn-large">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 5v14M5 12h14"/>
            </svg>
            Добавить транзакцию
          </router-link>
          <button @click="resetFilters" class="btn btn-secondary btn-large">
            Сбросить фильтры
          </button>
        </div>
      </div>

      <div v-else class="transactions-container">
        <!-- Summary -->
        <div class="transactions-summary">
          <div class="summary-card">
            <div class="summary-icon income">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2V22"/>
                <path d="M17 5L12 10L7 5"/>
              </svg>
            </div>
            <div class="summary-content">
              <div class="summary-label">Общий доход</div>
              <div class="summary-amount income">{{ formatMoneyAmount(filteredStats.income) }} Br</div>
            </div>
          </div>

          <div class="summary-card">
            <div class="summary-icon expense">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 22V2"/>
                <path d="M7 19L12 14L17 19"/>
              </svg>
            </div>
            <div class="summary-content">
              <div class="summary-label">Общий расход</div>
              <div class="summary-amount expense">{{ formatMoneyAmount(filteredStats.expenses) }} Br</div>
            </div>
          </div>

          <div class="summary-card">
            <div class="summary-icon balance" :class="filteredBalanceClass">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <path d="M8 14S9.5 16 12 16s4-2 4-2"/>
                <line x1="9" y1="9" x2="9.01" y2="9"/>
                <line x1="15" y1="9" x2="15.01" y2="9"/>
              </svg>
            </div>
            <div class="summary-content">
              <div class="summary-label">Баланс</div>
              <div class="summary-amount" :class="filteredBalanceClass">
                {{ formatMoneyAmount(filteredStats.balance) }} Br
              </div>
            </div>
          </div>
        </div>

        <!-- Transactions List -->
        <div class="transactions-list">
          <div
              v-for="transaction in transactions"
              :key="transaction.id"
              class="transaction-card"
              @click="editTransaction(transaction)"
          >
            <div class="transaction-content">
              <!-- Иконка с цветными полосками категорий -->
              <div class="transaction-icon">
                <div class="categories-stack" v-if="transaction.categories && transaction.categories.length">
                  <div
                      v-for="(category, index) in transaction.categories"
                      :key="category.id"
                      class="category-piece"
                      :style="{
                        backgroundColor: category.color || '#95a5a6',
                        width: `${100 / transaction.categories.length}%`,
                        left: `${(index * 100) / transaction.categories.length}%`
                      }"
                      :title="category.name"
                  ></div>
                </div>
                <div v-else class="categories-stack no-category">
                  <div class="category-piece" style="background-color: #95a5a6; width: 100%; left: 0;"></div>
                </div>
              </div>

              <div class="transaction-info">
                <div class="transaction-header">
                  <h4 class="transaction-description">
                    {{ transaction.description || 'Без описания' }}
                  </h4>
                  <div class="transaction-type" :class="transaction.type">
                    <span>{{ transaction.type === 'income' ? 'Доход' : 'Расход' }}</span>
                  </div>
                </div>

                <div class="transaction-meta">
                  <div class="meta-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                      <line x1="16" y1="2" x2="16" y2="6"/>
                      <line x1="8" y1="2" x2="8" y2="6"/>
                      <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    <span>{{ formatDate(transaction.date) }}</span>
                  </div>

                  <!-- Категории в виде тегов -->
                  <div class="meta-item categories-list">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                      <line x1="7" y1="7" x2="7.01" y2="7"/>
                    </svg>
                    <div class="categories-tags" v-if="transaction.categories && transaction.categories.length">
                      <span
                          v-for="category in transaction.categories"
                          :key="category.id"
                          class="category-tag"
                          :style="{
                            backgroundColor: (category.color || '#95a5a6') + '20',
                            color: category.color || '#95a5a6',
                            borderColor: category.color || '#95a5a6'
                          }"
                      >
                        {{ category.name }}
                      </span>
                    </div>
                    <span v-else class="no-category-text">Без категории</span>
                  </div>

                  <div class="meta-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="2" y="6" width="20" height="12" rx="2"/>
                      <path d="M12 16v-4M8 16v-4"/>
                    </svg>
                    <span>{{ getPaymentMethodLabel(transaction.payment_method) }}</span>
                  </div>
                </div>
              </div>

              <div class="transaction-amount-container">
                <div class="transaction-amount" :class="transaction.type">
                  <span class="amount-sign">{{ transaction.type === 'income' ? '+' : '-' }}</span>
                  {{ formatTransactionMoney(transaction) }}
                </div>
                <!-- Показываем курс только если валюта НЕ BYN -->
                <div v-if="transaction.currency?.code !== 'BYN' && transaction.exchange_rate" class="transaction-rate">
                  1 {{ transaction.currency?.code || getCurrencyCode(transaction.currency_id) }} = {{ formatRate(transaction.exchange_rate) }} Br
                </div>
              </div>

              <div class="transaction-actions" @click.stop>
                <button
                    @click.stop="editTransaction(transaction)"
                    class="action-btn edit-btn"
                    title="Редактировать"
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                  </svg>
                </button>
                <button
                    @click.stop="deleteTransaction(transaction.id)"
                    class="action-btn delete-btn"
                    title="Удалить"
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    <line x1="10" y1="11" x2="10" y2="17"/>
                    <line x1="14" y1="11" x2="14" y2="17"/>
                  </svg>
                </button>
              </div>
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
  name: 'TransactionList',
  setup() {
    const router = useRouter()
    const transactions = ref([])
    const loading = ref(true)

    const filters = ref({
      type: '',
      month: new Date().toISOString().slice(0, 7)
    })

    // Символы валют для 5 основных валют
    const getCurrencySymbol = (currencyId, currencyCode) => {
      if (currencyCode) {
        const symbols = {
          'BYN': 'Br',
          'RUB': '₽',
          'USD': '$',
          'EUR': '€',
          'CNY': '¥'
        }
        return symbols[currencyCode] || 'Br'
      }

      const symbols = {
        1: 'Br',   // BYN
        2: '₽',    // RUB
        3: '$',    // USD
        4: '€',    // EUR
        5: '¥'     // CNY
      }
      return symbols[currencyId] || 'Br'
    }

    // Код валюты по ID
    const getCurrencyCode = (currencyId) => {
      const codes = {
        1: 'BYN',
        2: 'RUB',
        3: 'USD',
        4: 'EUR',
        5: 'CNY'
      }
      return codes[currencyId] || 'BYN'
    }

    // Форматирование курса (4 знака после запятой)
    const formatRate = (rate) => {
      if (!rate && rate !== 0) return '0.0000'
      return Number(rate).toFixed(4)
    }

    // Форматирование суммы для транзакции (с валютой)
    const formatTransactionMoney = (transaction) => {
      if (!transaction) return '0 Br'
      const amount = transaction.amount || 0
      const currencySymbol = transaction.currency?.symbol || getCurrencySymbol(transaction.currency_id, transaction.currency?.code)

      return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }).format(amount) + ' ' + currencySymbol
    }

    // Форматирование просто суммы (для сводки)
    const formatMoneyAmount = (amount) => {
      if (amount === null || amount === undefined || isNaN(amount)) return '0'
      return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }).format(amount)
    }

    const formatDate = (dateString) => {
      if (!dateString) return 'Дата не указана'
      try {
        const date = new Date(dateString)
        return date.toLocaleDateString('ru-RU', {
          day: 'numeric',
          month: 'short',
          year: 'numeric'
        })
      } catch {
        return 'Неверная дата'
      }
    }

    const getPaymentMethodLabel = (method) => {
      const methods = {
        cash: 'Наличные',
        card: 'Карта',
        transfer: 'Перевод'
      }
      return methods[method] || method
    }

    const fetchTransactions = async () => {
      try {
        loading.value = true
        const params = {}

        if (filters.value.type) params.type = filters.value.type
        if (filters.value.month) {
          const [year, month] = filters.value.month.split('-')
          params.month = parseInt(month)
          params.year = parseInt(year)
        }

        const response = await axios.get('/api/transactions', { params })
        transactions.value = response.data.data || []
      } catch (error) {
        console.error('Error fetching transactions:', error)
      } finally {
        loading.value = false
      }
    }

    const editTransaction = (transaction) => {
      router.push(`/transactions/edit/${transaction.id}`)
    }

    const deleteTransaction = async (id) => {
      try {
        await axios.delete(`/api/transactions/${id}`)
        await fetchTransactions()
      } catch (error) {
        console.error('Error deleting transaction:', error)
        alert('Ошибка при удалении транзакции')
      }
    }

    const resetFilters = () => {
      filters.value = {
        type: '',
        month: new Date().toISOString().slice(0, 7)
      }
      fetchTransactions()
    }

    // Computed properties для сводки (суммы в BYN)
    const filteredStats = computed(() => {
      let income = 0
      let expenses = 0

      transactions.value.forEach(transaction => {
        // Используем amount_in_byn если есть, иначе конвертируем через курс
        let amountInByn = transaction.amount_in_byn || transaction.amount

        // Если нет amount_in_byn, но есть курс
        if (!transaction.amount_in_byn && transaction.exchange_rate) {
          amountInByn = transaction.amount * transaction.exchange_rate
        }

        if (transaction.type === 'income') {
          income += parseFloat(amountInByn) || 0
        } else if (transaction.type === 'expense') {
          expenses += parseFloat(amountInByn) || 0
        }
      })

      return { income, expenses, balance: income - expenses }
    })

    const filteredBalanceClass = computed(() => {
      const balance = filteredStats.value.balance
      if (balance > 0) return 'positive'
      if (balance < 0) return 'negative'
      return 'neutral'
    })

    onMounted(() => {
      fetchTransactions()
    })

    return {
      transactions,
      loading,
      filters,
      filteredStats,
      filteredBalanceClass,
      fetchTransactions,
      editTransaction,
      deleteTransaction,
      resetFilters,
      formatTransactionMoney,
      formatMoneyAmount,
      formatRate,
      formatDate,
      getPaymentMethodLabel,
      getCurrencyCode
    }
  }
}
</script>

<style scoped>
@import '../css/transaction_list.css';
</style>