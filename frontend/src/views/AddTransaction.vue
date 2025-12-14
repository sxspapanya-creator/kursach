<template>
  <div class="add-transaction">
    <!-- Заголовок -->
    <div class="page-header">
      <div class="header-left">
        <router-link to="/transactions" class="back-link">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 12H5M12 19l-7-7 7-7"/>
          </svg>
          Назад
        </router-link>
        <h1 class="page-title">Новая транзакция</h1>
        <p class="page-subtitle">Добавьте новый доход или расход</p>
      </div>
    </div>

    <div class="form-container">
      <!-- Переключатель типа транзакции -->
      <div class="type-selector">
        <button
            type="button"
            class="type-option income"
            :class="{ active: form.type === 'income' }"
            @click="form.type = 'income'"
        >
          <div class="type-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 2V22" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M17 5L12 10L7 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="type-content">
            <h4>Доход</h4>
            <p>Поступление средств</p>
          </div>
          <div class="type-check" v-if="form.type === 'income'">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 6L9 17l-5-5"/>
            </svg>
          </div>
        </button>

        <button
            type="button"
            class="type-option expense"
            :class="{ active: form.type === 'expense' }"
            @click="form.type = 'expense'"
        >
          <div class="type-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 22V2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M7 19L12 14L17 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="type-content">
            <h4>Расход</h4>
            <p>Трата средств</p>
          </div>
          <div class="type-check" v-if="form.type === 'expense'">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 6L9 17l-5-5"/>
            </svg>
          </div>
        </button>
      </div>

      <!-- Форма -->
      <form @submit.prevent="submitTransaction" class="transaction-form">
        <!-- Сумма -->
        <div class="form-section">
          <div class="form-header">
            <h3 class="section-title">Сумма</h3>
          </div>
          <div class="amount-input-wrapper">
            <div class="amount-input">
              <input
                  v-model.number="form.amount"
                  type="number"
                  step="0.01"
                  min="0.01"
                  placeholder="0.00"
                  required
                  class="amount-field"
                  :class="{ 'error': amountError }"
                  @input="validateAmount"
              />
              <span class="currency-symbol">Br</span>
            </div>
            <div v-if="amountError" class="error-message">{{ amountError }}</div>
          </div>
        </div>

        <!-- Категория -->
        <div class="form-section">
          <div class="form-header">
            <h3 class="section-title">Категория</h3>
            <router-link to="/categories" class="category-manage">
              Управление
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M5 12h14M12 5l7 7-7 7"/>
              </svg>
            </router-link>
          </div>

          <div v-if="filteredCategories.length === 0" class="no-categories">
            <div class="no-categories-icon">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                <line x1="7" y1="7" x2="7.01" y2="7"/>
              </svg>
            </div>
            <p>Нет категорий для {{ form.type === 'income' ? 'доходов' : 'расходов' }}</p>
            <router-link to="/categories" class="btn btn-secondary">
              Создать категорию
            </router-link>
          </div>

          <div v-else class="categories-grid">
            <div
                v-for="category in filteredCategories"
                :key="category.id"
                class="category-option"
                :class="{ selected: form.category_id === category.id }"
                @click="form.category_id = category.id"
            >
              <div class="category-info">
                <div class="category-color" :style="{ backgroundColor: category.color }"></div>
                <div class="category-name">{{ category.name }}</div>
              </div>
              <div class="category-budget" v-if="category.budget_limit">
                Лимит: {{ formatMoney(category.budget_limit) }}
              </div>
              <div v-if="form.category_id === category.id" class="category-check">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20 6L9 17l-5-5"/>
                </svg>
              </div>
            </div>
          </div>
        </div>

        <!-- Детали -->
        <div class="form-section">
          <div class="form-header">
            <h3 class="section-title">Детали транзакции</h3>
          </div>

          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Описание</label>
              <input
                  v-model="form.description"
                  type="text"
                  placeholder="Например: Зарплата, Продукты, Кафе..."
                  class="form-input"
              />
              <div class="input-hint">Необязательно</div>
            </div>

            <div class="form-group">
              <label class="form-label">Дата</label>
              <div class="date-input-wrapper">
                <input
                    v-model="form.date"
                    type="date"
                    required
                    class="form-input"
                />
                <button
                    type="button"
                    @click="form.date = new Date().toISOString().split('T')[0]"
                    class="date-today"
                >
                  Сегодня
                </button>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Способ оплаты</label>
              <div class="payment-methods">
                <div
                    v-for="method in paymentMethods"
                    :key="method.value"
                    class="payment-method"
                    :class="{ selected: form.payment_method === method.value }"
                    @click="form.payment_method = method.value"
                >
                  <div class="method-icon">{{ method.icon }}</div>
                  <div class="method-name">{{ method.name }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Рекомендации -->
        <div v-if="budgetWarning" class="warning-card">
          <div class="warning-icon">⚠️</div>
          <div class="warning-content">
            <h4>Превышение бюджета</h4>
            <p>Эта трата превысит лимит категории на {{ budgetWarning.overspend }}</p>
          </div>
        </div>

        <!-- Действия -->
        <div class="form-actions">
          <button
              type="submit"
              :disabled="loading || !isFormValid"
              class="btn btn-primary btn-large"
          >
            <span v-if="loading" class="spinner"></span>
            {{ loading ? 'Добавление...' : 'Добавить транзакцию' }}
          </button>
          <router-link to="/transactions" class="btn btn-secondary">
            Отмена
          </router-link>
        </div>

        <!-- Общая ошибка -->
        <div v-if="error" class="error-card">
          <div class="error-icon">❌</div>
          <div class="error-content">
            <h4>Ошибка</h4>
            <p>{{ error }}</p>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

export default {
  name: 'AddTransaction',
  setup() {
    const router = useRouter()
    const loading = ref(false)
    const error = ref('')
    const amountError = ref('')
    const categories = ref([])
    const budgetWarning = ref(null)

    const paymentMethods = [
      { value: 'card', name: 'Карта', icon: '💳' },
      { value: 'cash', name: 'Наличные', icon: '💵' },
      { value: 'transfer', name: 'Перевод', icon: '🏦' },
    ]

    const form = ref({
      amount: '',
      type: 'expense',
      category_id: '',
      description: '',
      date: new Date().toISOString().split('T')[0],
      payment_method: 'card',
      priority: 'normal'
    })

    const filteredCategories = computed(() => {
      return categories.value
          .filter(cat => cat.type === form.value.type)
          .sort((a, b) => a.name.localeCompare(b.name))
    })

    const isFormValid = computed(() => {
      return form.value.amount > 0 && form.value.category_id && form.value.date
    })

    const fetchCategories = async () => {
      try {
        const response = await axios.get('/api/categories', {
          params: { with_stats: true }
        })
        categories.value = response.data.data || []
      } catch (error) {
        console.error('Error fetching categories:', error)
      }
    }

    const validateAmount = () => {
      const amount = parseFloat(form.value.amount)
      if (amount < 0.01) {
        amountError.value = 'Сумма должна быть больше 0'
      } else if (amount > 10000000) {
        amountError.value = 'Слишком большая сумма'
      } else {
        amountError.value = ''
      }
    }

    const formatMoney = (amount) => {
      if (amount === null || amount === undefined) return '0 Br'
      return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }).format(amount) + ' Br'
    }

    const submitTransaction = async () => {
      if (!isFormValid.value) return

      try {
        loading.value = true
        error.value = ''

        const transactionData = {
          ...form.value,
          amount: parseFloat(form.value.amount)
        }

        await axios.post('/api/transactions', transactionData)

        // Показать уведомление об успехе
        if (window.showNotification) {
          window.showNotification('success', 'Транзакция успешно добавлена')
        }

        router.push('/transactions')
      } catch (err) {
        console.error('Error creating transaction:', err)
        error.value = err.response?.data?.message ||
            err.response?.data?.errors?.amount?.[0] ||
            'Ошибка при создании транзакции'

        // Прокрутить к ошибке
        window.scrollTo({ top: 0, behavior: 'smooth' })
      } finally {
        loading.value = false
      }
    }

    onMounted(() => {
      fetchCategories()
    })

    watch(() => form.value.type, () => {
      form.value.category_id = ''
    })

    return {
      form,
      loading,
      error,
      amountError,
      categories,
      filteredCategories,
      paymentMethods,
      budgetWarning,
      isFormValid,
      validateAmount,
      formatMoney,
      submitTransaction
    }
  }
}
</script>

<style scoped>
.add-transaction {
  max-width: 600px;
  margin: 0 auto;
  padding: 0 1rem 2rem;
}

/* Заголовок */
.page-header {
  margin-bottom: 2rem;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #64748b;
  text-decoration: none;
  margin-bottom: 1rem;
  transition: color 0.2s;
}

.back-link:hover {
  color: #3b82f6;
}

.page-title {
  font-size: 2rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 0.5rem 0;
}

.page-subtitle {
  font-size: 1rem;
  color: #64748b;
  margin: 0;
}

/* Переключатель типа */
.type-selector {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-bottom: 2rem;
}

.type-option {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.25rem;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  background: white;
  cursor: pointer;
  transition: all 0.2s;
  text-align: left;
  width: 100%;
}

.type-option:hover {
  border-color: #cbd5e1;
}

.type-option.active {
  border-color: transparent;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.type-option.income.active {
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%);
  border-color: #10b981;
}

.type-option.expense.active {
  background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%);
  border-color: #ef4444;
}

.type-icon {
  width: 48px;
  height: 48px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.type-option.income .type-icon {
  background: rgba(16, 185, 129, 0.15);
  color: #10b981;
}

.type-option.expense .type-icon {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
}

.type-option.active .type-icon {
  background: white;
}

.type-content {
  flex: 1;
}

.type-content h4 {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 0.25rem 0;
}

.type-content p {
  font-size: 0.875rem;
  color: #64748b;
  margin: 0;
}

.type-check {
  color: #10b981;
}

.type-option.expense.active .type-check {
  color: #ef4444;
}

/* Форма */
.form-container {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
}

.form-section {
  margin-bottom: 2rem;
  padding-bottom: 2rem;
  border-bottom: 1px solid #f1f5f9;
}

.form-section:last-child {
  margin-bottom: 0;
  padding-bottom: 0;
  border-bottom: none;
}

.form-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.section-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.category-manage {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #3b82f6;
  text-decoration: none;
}

.category-manage:hover {
  color: #2563eb;
}

/* Ввод суммы */
.amount-input-wrapper {
  max-width: 300px;
}

.amount-input {
  position: relative;
}

.amount-field {
  width: 100%;
  padding: 1rem 3rem 1rem 1.5rem;
  font-size: 2rem;
  font-weight: 700;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  text-align: right;
  transition: all 0.2s;
}

.amount-field:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.amount-field.error {
  border-color: #ef4444;
}

.currency-symbol {
  position: absolute;
  right: 1.5rem;
  top: 50%;
  transform: translateY(-50%);
  font-size: 2rem;
  font-weight: 600;
  color: #64748b;
  pointer-events: none;
}

.error-message {
  color: #ef4444;
  font-size: 0.875rem;
  margin-top: 0.5rem;
  font-weight: 500;
}

/* Категории */
.no-categories {
  text-align: center;
  padding: 2rem;
  background: #f8fafc;
  border-radius: 12px;
  border: 2px dashed #e2e8f0;
}

.no-categories-icon {
  width: 64px;
  height: 64px;
  margin: 0 auto 1rem;
  color: #94a3b8;
}

.no-categories p {
  color: #64748b;
  margin-bottom: 1rem;
}

.categories-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 0.75rem;
}

.category-option {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 1rem;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
  min-height: 80px;
}

.category-option:hover {
  border-color: #cbd5e1;
  transform: translateY(-2px);
}

.category-option.selected {
  border-color: #3b82f6;
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(59, 130, 246, 0.02) 100%);
}

.category-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.category-color {
  width: 16px;
  height: 16px;
  border-radius: 4px;
  flex-shrink: 0;
}

.category-name {
  font-weight: 600;
  color: #1e293b;
  font-size: 0.875rem;
}

.category-budget {
  font-size: 0.75rem;
  color: #64748b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.category-check {
  position: absolute;
  top: 0.5rem;
  right: 0.5rem;
  color: #3b82f6;
}

/* Детали формы */
.form-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.5rem;
}

@media (min-width: 640px) {
  .form-grid {
    grid-template-columns: 1fr 1fr;
  }
}

.form-group {
  margin-bottom: 0;
}

.form-label {
  display: block;
  font-size: 0.875rem;
  font-weight: 600;
  color: #475569;
  margin-bottom: 0.5rem;
}

.form-input {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.95rem;
  transition: all 0.2s;
  background: white;
}

.form-input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.input-hint {
  font-size: 0.75rem;
  color: #94a3b8;
  margin-top: 0.25rem;
}

.date-input-wrapper {
  display: flex;
  gap: 0.5rem;
}

.date-today {
  padding: 0.75rem 1rem;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 500;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.date-today:hover {
  background: #e2e8f0;
}

.payment-methods {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
  gap: 0.5rem;
}

.payment-method {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
  padding: 0.75rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
  text-align: center;
}

.payment-method:hover {
  border-color: #cbd5e1;
}

.payment-method.selected {
  border-color: #3b82f6;
  background: rgba(59, 130, 246, 0.05);
}

.method-icon {
  font-size: 1.25rem;
  margin-bottom: 0.25rem;
}

.method-name {
  font-size: 0.75rem;
  font-weight: 500;
  color: #475569;
}

.priority-select {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.priority-option {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}

.priority-option:hover {
  border-color: #cbd5e1;
}

.priority-option.selected {
  border-color: #3b82f6;
  background: rgba(59, 130, 246, 0.05);
}

.priority-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}

.priority-option.essential .priority-dot {
  background: #ef4444;
}

.priority-option.important .priority-dot {
  background: #f59e0b;
}

.priority-option.normal .priority-dot {
  background: #3b82f6;
}

.priority-option.optional .priority-dot {
  background: #94a3b8;
}

.priority-name {
  font-size: 0.875rem;
  color: #475569;
  font-weight: 500;
}

.priority-option.selected .priority-name {
  color: #1e293b;
  font-weight: 600;
}

/* Предупреждения */
.warning-card {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding: 1.25rem;
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(245, 158, 11, 0.05) 100%);
  border: 1px solid rgba(245, 158, 11, 0.3);
  border-radius: 12px;
  margin-bottom: 1.5rem;
}

.warning-icon {
  font-size: 1.5rem;
  flex-shrink: 0;
}

.warning-content h4 {
  font-size: 1rem;
  font-weight: 600;
  color: #d97706;
  margin: 0 0 0.25rem 0;
}

.warning-content p {
  font-size: 0.875rem;
  color: #92400e;
  margin: 0;
  line-height: 1.4;
}

/* Действия формы */
.form-actions {
  display: flex;
  gap: 1rem;
  margin-top: 2rem;
  flex-wrap: wrap;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  font-size: 0.95rem;
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

.btn-primary:hover:not(:disabled) {
  background: #2563eb;
}

.btn-primary:disabled {
  background: #94a3b8;
  cursor: not-allowed;
}

.btn-secondary {
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
}

.btn-secondary:hover {
  background: #e2e8f0;
}

.btn-large {
  padding: 0.875rem 2rem;
  font-size: 1rem;
}

.spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  border-top-color: white;
  animation: spin 1s ease-in-out infinite;
  margin-right: 0.5rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Карточка ошибки */
.error-card {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding: 1.25rem;
  background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%);
  border: 1px solid rgba(239, 68, 68, 0.3);
  border-radius: 12px;
  margin-top: 1.5rem;
}

.error-icon {
  font-size: 1.5rem;
  flex-shrink: 0;
}

.error-content h4 {
  font-size: 1rem;
  font-weight: 600;
  color: #dc2626;
  margin: 0 0 0.25rem 0;
}

.error-content p {
  font-size: 0.875rem;
  color: #991b1b;
  margin: 0;
  line-height: 1.4;
}

/* Адаптивность */
@media (max-width: 640px) {
  .add-transaction {
    padding: 0 0.75rem 1.5rem;
  }

  .form-container {
    padding: 1.5rem;
  }

  .type-selector {
    grid-template-columns: 1fr;
  }

  .amount-field {
    font-size: 1.75rem;
    padding: 0.875rem 2.5rem 0.875rem 1.25rem;
  }

  .currency-symbol {
    font-size: 1.75rem;
    right: 1.25rem;
  }

  .categories-grid {
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  }

  .payment-methods {
    grid-template-columns: repeat(2, 1fr);
  }

  .form-actions {
    flex-direction: column;
  }

  .btn {
    width: 100%;
  }
}
</style>