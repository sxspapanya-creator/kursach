<template>
  <div class="add-transaction">
    <div class="header">
      <h1>Добавить транзакцию</h1>
      <router-link to="/transactions" class="btn btn-secondary">← Назад</router-link>
    </div>

    <form @submit.prevent="submitTransaction" class="transaction-form">
      <div class="type-toggle">
        <button
            type="button"
            :class="{ active: form.type === 'income' }"
            @click="form.type = 'income'"
        >
          💰 Доход
        </button>
        <button
            type="button"
            :class="{ active: form.type === 'expense' }"
            @click="form.type = 'expense'"
        >
          💸 Расход
        </button>
      </div>

      <div class="form-group">
        <label>Сумма *</label>
        <input
            v-model.number="form.amount"
            type="number"
            step="0.01"
            min="0.01"
            placeholder="0.00"
            required
        >
      </div>

      <div class="form-group">
        <label>Категория *</label>
        <select v-model="form.category_id" required>
          <option value="">Выберите категорию</option>
          <option
              v-for="category in filteredCategories"
              :value="category.id"
              :key="category.id"
              :style="{ color: category.color }"
          >
            {{ category.name }}
          </option>
        </select>
      </div>

      <div class="form-group">
        <label>Описание</label>
        <input
            v-model="form.description"
            type="text"
            placeholder="Краткое описание транзакции"
        >
      </div>

      <div class="form-group">
        <label>Дата *</label>
        <input v-model="form.date" type="date" required>
      </div>

      <div class="form-group">
        <label>Способ оплаты</label>
        <select v-model="form.payment_method">
          <option value="card">💳 Карта</option>
          <option value="cash">💵 Наличные</option>
          <option value="transfer">🏦 Перевод</option>
        </select>
      </div>

      <div class="form-actions">
        <button type="submit" :disabled="loading" class="btn btn-primary">
          {{ loading ? 'Добавление...' : 'Добавить транзакцию' }}
        </button>
        <router-link to="/transactions" class="btn btn-secondary">Отмена</router-link>
      </div>

      <div v-if="error" class="error-message">
        {{ error }}
      </div>
    </form>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

export default {
  name: 'AddTransaction',
  setup() {
    const router = useRouter()
    const loading = ref(false)
    const error = ref('')
    const categories = ref([])

    const form = ref({
      amount: '',
      type: 'expense',
      category_id: '',
      description: '',
      date: new Date().toISOString().split('T')[0],
      payment_method: 'card'
    })

    const filteredCategories = computed(() => {
      return categories.value.filter(cat => cat.type === form.value.type)
    })

    const fetchCategories = async () => {
      try {
        const response = await axios.get('/api/categories')
        categories.value = response.data.data
      } catch (error) {
        console.error('Error fetching categories:', error)
      }
    }

    const submitTransaction = async () => {
      try {
        loading.value = true
        error.value = ''

        await axios.post('/api/transactions', form.value)

        router.push('/transactions')
      } catch (err) {
        console.error('Error creating transaction:', err)
        error.value = err.response?.data?.message || 'Ошибка при создании транзакции'
      } finally {
        loading.value = false
      }
    }

    onMounted(() => {
      fetchCategories()
    })

    return {
      form,
      loading,
      error,
      categories,
      filteredCategories,
      submitTransaction
    }
  }
}
</script>

<style scoped>
.add-transaction {
  max-width: 500px;
  margin: 0 auto;
  padding: 2rem;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.transaction-form {
  background: white;
  padding: 2rem;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.type-toggle {
  display: flex;
  gap: 1rem;
  margin-bottom: 2rem;
}

.type-toggle button {
  flex: 1;
  padding: 1rem;
  border: 2px solid #ddd;
  background: white;
  border-radius: 8px;
  cursor: pointer;
  font-size: 1rem;
  transition: all 0.3s;
}

.type-toggle button.active {
  border-color: #3498db;
  background: #3498db;
  color: white;
}

.form-group {
  margin-bottom: 1.5rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 600;
  color: #2c3e50;
}

.form-group input,
.form-group select {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #bdc3c7;
  border-radius: 4px;
  font-size: 1rem;
}

.form-actions {
  display: flex;
  gap: 1rem;
  margin-top: 2rem;
}

.error-message {
  background-color: #e74c3c;
  color: white;
  padding: 1rem;
  border-radius: 4px;
  margin-top: 1rem;
  text-align: center;
}
</style>