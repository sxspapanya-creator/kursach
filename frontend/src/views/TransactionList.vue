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
            Добавить доходы
          </router-link>
          <router-link to="/transactions/create?type=expense" class="btn btn-danger">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 5v14M5 12h14"/>
            </svg>
            Добавить расходы
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
          <label class="filter-label">Категория</label>
          <select v-model="filters.category_id" @change="fetchTransactions" class="filter-select">
            <option value="">Все категории</option>
            <option v-for="category in categories" :value="category.id" :key="category.id">
              {{ category.name }}
            </option>
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
              <div class="summary-amount income">{{ formatMoney(filteredStats.income) }}</div>
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
              <div class="summary-amount expense">{{ formatMoney(filteredStats.expenses) }}</div>
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
                {{ formatMoney(filteredStats.balance) }}
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
              <div class="transaction-icon">
                <div
                    class="category-icon"
                    :style="{ backgroundColor: transaction.category.color + '20' }"
                >
                  <div
                      class="category-dot"
                      :style="{ backgroundColor: transaction.category.color }"
                  ></div>
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

                  <div class="meta-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                      <line x1="7" y1="7" x2="7.01" y2="7"/>
                    </svg>
                    <span>{{ transaction.category.name }}</span>
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
                  {{ formatMoney(transaction.amount) }}
                </div>
                <div class="transaction-actions">
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

        <!-- Pagination (if needed) -->
        <div v-if="pagination.total > pagination.perPage" class="pagination">
          <button
              @click="prevPage"
              :disabled="pagination.currentPage === 1"
              class="pagination-btn"
          >
            ← Назад
          </button>
          <span class="pagination-info">
            Страница {{ pagination.currentPage }} из {{ pagination.totalPages }}
          </span>
          <button
              @click="nextPage"
              :disabled="pagination.currentPage === pagination.totalPages"
              class="pagination-btn"
          >
            Вперед →
          </button>
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
    const categories = ref([])
    const loading = ref(true)

    const filters = ref({
      type: '',
      category_id: '',
      month: new Date().toISOString().slice(0, 7)
    })

    const pagination = ref({
      currentPage: 1,
      perPage: 20,
      total: 0,
      totalPages: 1
    })

    const fetchTransactions = async () => {
      try {
        loading.value = true
        const params = {
          page: pagination.value.currentPage,
          per_page: pagination.value.perPage
        }

        if (filters.value.type) params.type = filters.value.type
        if (filters.value.category_id) params.category_id = filters.value.category_id
        if (filters.value.month) {
          const [year, month] = filters.value.month.split('-')
          params.month = parseInt(month)
          params.year = parseInt(year)
        }

        const response = await axios.get('/api/transactions', { params })
        transactions.value = response.data.data || response.data || []

        // Update pagination if available
        if (response.data.meta) {
          pagination.value = {
            ...pagination.value,
            currentPage: response.data.meta.current_page,
            total: response.data.meta.total,
            totalPages: response.data.meta.last_page
          }
        }

        // Calculate stats for filtered transactions
        calculateFilteredStats()
      } catch (error) {
        console.error('Error fetching transactions:', error)
      } finally {
        loading.value = false
      }
    }

    const fetchCategories = async () => {
      try {
        const response = await axios.get('/api/categories')
        categories.value = response.data.data || response.data || []
      } catch (error) {
        console.error('Error fetching categories:', error)
      }
    }

    // УДАЛЕНО: функция fetchStats() - не нужна, так как нет эндпоинтов

    const calculateFilteredStats = () => {
      let income = 0
      let expenses = 0

      transactions.value.forEach(transaction => {
        if (transaction.type === 'income') {
          income += parseFloat(transaction.amount) || 0
        } else if (transaction.type === 'expense') {
          expenses += parseFloat(transaction.amount) || 0
        }
      })

      return {
        income,
        expenses,
        balance: income - expenses
      }
    }

    const editTransaction = (transaction) => {
      router.push(`/transactions/edit/${transaction.id}`)
    }

    const deleteTransaction = async (id) => {
      try {
        await axios.delete(`/api/transactions/${id}`)
        await fetchTransactions()
        // УДАЛЕНО: await fetchStats() - не нужно
      } catch (error) {
        console.error('Error deleting transaction:', error)
        alert('Ошибка при удалении транзакции')
      }
    }

    const resetFilters = () => {
      filters.value = {
        type: '',
        category_id: '',
        month: new Date().toISOString().slice(0, 7)
      }
      pagination.value.currentPage = 1
      fetchTransactions()
    }

    const nextPage = () => {
      if (pagination.value.currentPage < pagination.value.totalPages) {
        pagination.value.currentPage++
        fetchTransactions()
      }
    }

    const prevPage = () => {
      if (pagination.value.currentPage > 1) {
        pagination.value.currentPage--
        fetchTransactions()
      }
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
        return date.toLocaleDateString('ru-RU', {
          day: 'numeric',
          month: 'short',
          year: 'numeric'
        })
      } catch (error) {
        console.error('Error formatting date:', error, dateString)
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

    // Computed properties
    const filteredStats = computed(() => calculateFilteredStats())

    const filteredBalanceClass = computed(() => {
      const balance = filteredStats.value.balance
      if (balance > 0) return 'positive'
      if (balance < 0) return 'negative'
      return 'neutral'
    })

    onMounted(async () => {
      // ИСПРАВЛЕНО: удален ненужный fetchStats()
      await Promise.all([fetchTransactions(), fetchCategories()])
    })

    return {
      transactions,
      categories,
      loading,
      filters,
      pagination,
      filteredStats,
      filteredBalanceClass,
      fetchTransactions,
      editTransaction,
      deleteTransaction,
      resetFilters,
      nextPage,
      prevPage,
      formatMoney,
      formatDate,
      getPaymentMethodLabel
    }
  }
}
</script>

<style scoped>
.transactions-page {
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
  padding: 2rem 1rem;
  margin-bottom: 1.5rem;
  background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
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
  font-size: 2rem;
  font-weight: 800;
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.hero-icon {
  font-size: 2.5rem;
}

.hero-subtitle {
  font-size: 1.125rem;
  opacity: 0.9;
  margin-bottom: 1.5rem;
  font-weight: 300;
}

.hero-stats {
  display: flex;
  justify-content: center;
  gap: 2rem;
  margin-top: 1rem;
}

.hero-stat {
  text-align: center;
}

.hero-stat .stat-value {
  font-size: 1.5rem;
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

/* Section Header */
.section-header {
  margin-bottom: 1.5rem;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.section-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 0.75rem;
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
  white-space: nowrap;
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
  border: 1px solid #e2e8f0;
}

.btn-secondary:hover {
  background: #e2e8f0;
}

.btn-success {
  background: #10b981;
  color: white;
}

.btn-success:hover {
  background: #059669;
}

.btn-danger {
  background: #ef4444;
  color: white;
}

.btn-danger:hover {
  background: #dc2626;
}

.btn-large {
  padding: 0.875rem 1.75rem;
  font-size: 1rem;
}

.btn-small {
  padding: 0.375rem 0.75rem;
  font-size: 0.8125rem;
}

/* Filters */
.filters-section {
  background: white;
  border-radius: 12px;
  padding: 1.25rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
}

.filters-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  align-items: end;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.filter-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #475569;
}

.filter-select,
.filter-input {
  padding: 0.625rem 0.875rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.875rem;
  background: white;
  color: #1e293b;
  transition: border-color 0.2s;
}

.filter-select:focus,
.filter-input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.filter-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
}

/* Loading and Empty States */
.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 1rem;
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
  max-width: 400px;
}

.empty-actions {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
  justify-content: center;
}

/* Transactions Summary */
.transactions-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.summary-card {
  background: white;
  border-radius: 12px;
  padding: 1.25rem;
  border: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  gap: 1rem;
  transition: transform 0.2s;
}

.summary-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.summary-icon {
  width: 48px;
  height: 48px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.summary-icon.income {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.summary-icon.expense {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.summary-icon.balance.positive {
  background: rgba(22, 163, 74, 0.1);
  color: #16a34a;
}

.summary-icon.balance.negative {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.summary-icon.balance.neutral {
  background: rgba(100, 116, 139, 0.1);
  color: #64748b;
}

.summary-content {
  flex: 1;
}

.summary-label {
  font-size: 0.875rem;
  color: #64748b;
  margin-bottom: 0.25rem;
}

.summary-amount {
  font-size: 1.25rem;
  font-weight: 700;
}

.summary-amount.income {
  color: #10b981;
}

.summary-amount.expense {
  color: #ef4444;
}

.summary-amount.positive {
  color: #16a34a;
}

.summary-amount.negative {
  color: #dc2626;
}

.summary-amount.neutral {
  color: #64748b;
}

/* Transactions List */
.transactions-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.transaction-card {
  background: white;
  border-radius: 12px;
  padding: 1.25rem;
  border: 1px solid #e2e8f0;
  cursor: pointer;
  transition: all 0.2s;
}

.transaction-card:hover {
  border-color: #3b82f6;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}

.transaction-content {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.transaction-icon {
  flex-shrink: 0;
}

.category-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.category-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}

.transaction-info {
  flex: 1;
  min-width: 0;
}

.transaction-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 0.5rem;
}

.transaction-description {
  font-size: 1rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
  line-height: 1.4;
  word-break: break-word;
}

.transaction-type {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  white-space: nowrap;
}

.transaction-type.income {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.transaction-type.expense {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.transaction-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  font-size: 0.875rem;
  color: #64748b;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 0.375rem;
}

.meta-item svg {
  flex-shrink: 0;
}

.transaction-amount-container {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.5rem;
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

.transaction-actions {
  display: flex;
  gap: 0.5rem;
}

.action-btn {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
  background: transparent;
  color: #64748b;
}

.action-btn:hover {
  background: #f1f5f9;
}

.edit-btn:hover {
  color: #3b82f6;
}

.delete-btn:hover {
  color: #ef4444;
}

/* Pagination */
.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1rem;
  margin-top: 2rem;
  padding: 1rem;
  background: white;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.pagination-btn {
  padding: 0.5rem 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: white;
  color: #475569;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.pagination-btn:hover:not(:disabled) {
  background: #f1f5f9;
  border-color: #94a3b8;
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pagination-info {
  font-size: 0.875rem;
  color: #64748b;
}

/* Responsive Design */
@media (max-width: 768px) {
  .transactions-page {
    padding: 0 1rem 1.5rem;
  }

  .hero-section {
    padding: 1.5rem 1rem;
    border-radius: 16px;
  }

  .hero-title {
    font-size: 1.5rem;
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
    gap: 1rem;
  }

  .header-content {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }

  .header-actions {
    width: 100%;
    flex-wrap: wrap;
  }

  .filters-grid {
    grid-template-columns: 1fr;
  }

  .transactions-summary {
    grid-template-columns: 1fr;
  }

  .transaction-content {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }

  .transaction-header {
    width: 100%;
  }

  .transaction-amount-container {
    width: 100%;
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
  }

  .transaction-meta {
    flex-direction: column;
    gap: 0.5rem;
  }

  .pagination {
    flex-direction: column;
    gap: 0.75rem;
  }

  .btn {
    width: 100%;
    justify-content: center;
  }

  .empty-actions {
    flex-direction: column;
    width: 100%;
  }
}
</style>