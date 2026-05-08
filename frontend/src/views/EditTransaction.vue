<template>
  <div class="edit-transaction">
    <!-- Заголовок -->
    <div class="page-header">
      <div class="header-left">
        <router-link to="/transactions" class="back-link">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 12H5M12 19l-7-7 7-7"/>
          </svg>
          Назад
        </router-link>
        <h1 class="page-title">Редактировать транзакцию</h1>
        <p class="page-subtitle">Измените данные о доходе или расходе</p>
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
      <form @submit.prevent="updateTransaction" class="transaction-form">
        <div v-if="loading" class="loading-state">
          <div class="loading-spinner"></div>
          <p>Загрузка данных транзакции...</p>
        </div>

        <div v-else-if="error" class="error-card">
          <div class="error-icon">⚠️</div>
          <div class="error-content">
            <h4>Ошибка</h4>
            <p>{{ error }}</p>
          </div>
          <button @click="fetchTransaction" class="btn btn-secondary btn-small">
            Попробовать снова
          </button>
        </div>

        <div v-else>
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

          <!-- Категории (множественный выбор) -->
          <div class="form-section">
            <div class="form-header">
              <h3 class="section-title">Категории</h3>
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
                  :class="{ selected: form.category_ids.includes(category.id) }"
                  @click="toggleCategory(category.id)"
              >
                <div class="category-info">
                  <div class="category-color" :style="{ backgroundColor: category.color || '#95a5a6' }"></div>
                  <div class="category-name">{{ category.name }}</div>
                </div>
                <div class="category-budget" v-if="category.budget_limit">
                  Лимит: {{ formatMoney(category.budget_limit) }}
                </div>
                <div v-if="form.category_ids.includes(category.id)" class="category-check">
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
                :disabled="submitting || !isFormValid"
                class="btn btn-primary btn-large"
            >
              <span v-if="submitting" class="spinner"></span>
              {{ submitting ? 'Сохранение...' : 'Сохранить изменения' }}
            </button>
            <router-link to="/transactions" class="btn btn-secondary">
              Отмена
            </router-link>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'

export default {
  name: 'EditTransaction',
  props: {
    id: {
      type: [String, Number],
      required: true
    }
  },
  setup(props) {
    const router = useRouter()
    const route = useRoute()

    const loading = ref(true)
    const submitting = ref(false)
    const error = ref('')
    const amountError = ref('')
    const categories = ref([])
    const budgetWarning = ref(null)
    const isDataLoaded = ref(false)  // ← добавить флаг

    const paymentMethods = [
      { value: 'card', name: 'Карта', icon: '💳' },
      { value: 'cash', name: 'Наличные', icon: '💵' },
      { value: 'transfer', name: 'Перевод', icon: '🏦' },
    ]

    const form = ref({
      amount: '',
      type: 'expense',
      category_ids: [],
      description: '',
      date: new Date().toISOString().split('T')[0],
      payment_method: 'card'
    })

    const filteredCategories = computed(() => {
      if (!categories.value || !Array.isArray(categories.value)) {
        return []
      }
      return categories.value
          .filter(cat => cat && cat.type === form.value.type)
          .sort((a, b) => (a.name || '').localeCompare(b.name || ''))
    })

    const isFormValid = computed(() => {
      return form.value.amount > 0 &&
          form.value.category_ids &&
          form.value.category_ids.length > 0 &&
          form.value.date
    })

    const fetchTransaction = async () => {
      try {
        loading.value = true
        error.value = false
        isDataLoaded.value = false

        const transactionId = props.id || route.params.id

        // Загружаем оба ресурса
        const [transactionRes, categoriesRes] = await Promise.all([
          axios.get(`/api/transactions/${transactionId}`),
          axios.get('/api/categories')
        ])

        const transactionData = transactionRes.data.data || transactionRes.data
        const categoriesData = categoriesRes.data.data || categoriesRes.data

        if (!transactionData) {
          throw new Error('Транзакция не найдена')
        }

        // Сохраняем категории
        categories.value = categoriesData

        // Форматируем дату
        let formattedDate = transactionData.date
        if (formattedDate) {
          formattedDate = formattedDate.split('T')[0]
        }

        // Получаем ID категорий
        let categoryIds = []
        if (transactionData.categories && Array.isArray(transactionData.categories)) {
          categoryIds = transactionData.categories.map(cat => cat.id)
        } else if (transactionData.category_id) {
          categoryIds = [transactionData.category_id]
        } else if (transactionData.category && transactionData.category.id) {
          categoryIds = [transactionData.category.id]
        }

        // Обновляем форму
        form.value.amount = transactionData.amount || ''
        form.value.type = transactionData.type || 'expense'
        form.value.category_ids = categoryIds
        form.value.description = transactionData.description || ''
        form.value.date = formattedDate
        form.value.payment_method = transactionData.payment_method || 'card'

        isDataLoaded.value = true

        console.log('Data loaded. Category IDs:', form.value.category_ids)

      } catch (err) {
        console.error('Error fetching transaction:', err)
        error.value = err.response?.status === 404
            ? 'Транзакция не найдена'
            : (err.response?.data?.message || err.message || 'Не удалось загрузить транзакцию')
      } finally {
        loading.value = false
      }
    }

    const toggleCategory = (categoryId) => {
      const index = form.value.category_ids.indexOf(categoryId)
      if (index === -1) {
        form.value.category_ids.push(categoryId)
      } else {
        form.value.category_ids.splice(index, 1)
      }
      console.log('Toggled category. New IDs:', form.value.category_ids)
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

    const updateTransaction = async () => {
      if (!isFormValid.value) return

      try {
        submitting.value = true
        error.value = ''

        const transactionId = props.id || route.params.id

        const transactionData = {
          amount: parseFloat(form.value.amount),
          type: form.value.type,
          category_ids: form.value.category_ids,
          description: form.value.description,
          date: form.value.date,
          payment_method: form.value.payment_method
        }

        await axios.put(`/api/transactions/${transactionId}`, transactionData)

        if (window.showNotification) {
          window.showNotification('success', 'Транзакция успешно обновлена')
        }

        router.push('/transactions')
      } catch (err) {
        console.error('Error updating transaction:', err)
        error.value = err.response?.data?.message ||
            err.response?.data?.errors?.category_ids?.[0] ||
            'Ошибка при обновлении транзакции'
        window.scrollTo({ top: 0, behavior: 'smooth' })
      } finally {
        submitting.value = false
      }
    }

    onMounted(() => {
      fetchTransaction()
    })

    watch(() => form.value.type, () => {
      form.value.category_ids = []
    })

    return {
      form,
      loading,
      submitting,
      error,
      amountError,
      categories,
      filteredCategories,
      paymentMethods,
      budgetWarning,
      isFormValid,
      validateAmount,
      toggleCategory,
      formatMoney,
      fetchTransaction,
      updateTransaction
    }
  }
}
</script>

<style scoped>
@import '../css/edit_transaction.css';
</style>