<template>
  <div class="transaction-list">
    <div class="header">
      <h1>Транзакции</h1>
      <router-link to="/transactions/create" class="btn btn-primary">
        + Добавить транзакцию
      </router-link>
    </div>

    <div class="filters">
      <select v-model="filters.type" @change="fetchTransactions">
        <option value="">Все типы</option>
        <option value="income">Доходы</option>
        <option value="expense">Расходы</option>
      </select>

      <select v-model="filters.category_id" @change="fetchTransactions">
        <option value="">Все категории</option>
        <option v-for="category in categories" :value="category.id" :key="category.id">
          {{ category.name }}
        </option>
      </select>

      <input v-model="filters.month" type="month" @change="fetchTransactions">
    </div>

    <div v-if="loading" class="loading">Загрузка транзакций...</div>

    <div v-else-if="transactions.length === 0" class="empty-state">
      <p>Транзакций не найдено</p>
      <router-link to="/transactions/create" class="btn btn-primary">
        Добавить первую транзакцию
      </router-link>
    </div>

    <div v-else class="transactions">
      <div v-for="transaction in transactions" :key="transaction.id" class="transaction-item">
        <div class="transaction-main">
          <div class="category-color" :style="{ backgroundColor: transaction.category.color }"></div>
          <div class="transaction-info">
            <div class="description">{{ transaction.description || 'Без описания' }}</div>
            <div class="meta">
              <span class="category">{{ transaction.category.name }}</span>
              <span class="date">{{ formatDate(transaction.date) }}</span>
              <span class="payment-method">{{ getPaymentMethodLabel(transaction.payment_method) }}</span>
            </div>
          </div>
        </div>
        <div class="amount" :class="transaction.type">
          {{ transaction.type === 'income' ? '+' : '-' }}{{ formatMoney(transaction.amount) }}
        </div>
        <div class="actions">
          <button @click="editTransaction(transaction)" class="btn btn-small">✏️</button>
          <button @click="deleteTransaction(transaction.id)" class="btn btn-small btn-danger">🗑️</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
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

    const fetchTransactions = async () => {
      try {
        loading.value = true
        const params = {}
        if (filters.value.type) params.type = filters.value.type
        if (filters.value.category_id) params.category_id = filters.value.category_id
        if (filters.value.month) params.month = filters.value.month.split('-')[1]

        const response = await axios.get('/api/transactions', { params })
        transactions.value = response.data.data
      } catch (error) {
        console.error('Error fetching transactions:', error)
      } finally {
        loading.value = false
      }
    }

    const fetchCategories = async () => {
      try {
        const response = await axios.get('/api/categories')
        categories.value = response.data.data
      } catch (error) {
        console.error('Error fetching categories:', error)
      }
    }

    // ДОБАВИТЬ ФУНКЦИЮ РЕДАКТИРОВАНИЯ
    const editTransaction = (transaction) => {
      router.push(`/transactions/edit/${transaction.id}`)
    }

    const deleteTransaction = async (id) => {
      if (!confirm('Удалить эту транзакцию?')) return

      try {
        await axios.delete(`/api/transactions/${id}`)
        await fetchTransactions()
      } catch (error) {
        console.error('Error deleting transaction:', error)
        alert('Ошибка при удалении транзакции')
      }
    }

    const formatMoney = (amount) => {
      return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB'
      }).format(amount)
    }

    const formatDate = (dateString) => {
      return new Date(dateString).toLocaleDateString('ru-RU')
    }

    const getPaymentMethodLabel = (method) => {
      const methods = {
        cash: 'Наличные',
        card: 'Карта',
        transfer: 'Перевод'
      }
      return methods[method] || method
    }

    onMounted(async () => {
      await Promise.all([fetchTransactions(), fetchCategories()])
    })

    return {
      transactions,
      categories,
      loading,
      filters,
      fetchTransactions,
      editTransaction, // <-- ДОБАВИТЬ В RETURN
      deleteTransaction,
      formatMoney,
      formatDate,
      getPaymentMethodLabel
    }
  }
}
</script>

<style scoped>
.transaction-list {
  max-width: 800px;
  margin: 0 auto;
  padding: 2rem;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.filters {
  display: flex;
  gap: 1rem;
  margin-bottom: 2rem;
  padding: 1rem;
  background: #f8f9fa;
  border-radius: 8px;
}

.filters select,
.filters input {
  padding: 0.5rem;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.transactions {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.transaction-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.transaction-main {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex: 1;
}

.category-color {
  width: 16px;
  height: 16px;
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
  color: #666;
}

.amount {
  font-weight: 700;
  font-size: 1.1rem;
  margin: 0 1rem;
}

.amount.income {
  color: #27ae60;
}

.amount.expense {
  color: #e74c3c;
}

.actions {
  display: flex;
  gap: 0.5rem;
}

.loading, .empty-state {
  text-align: center;
  padding: 3rem;
  color: #7f8c8d;
}

.empty-state p {
  margin-bottom: 1rem;
}
</style>