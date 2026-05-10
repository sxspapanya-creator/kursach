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
        <h1 class="page-title">Изменить транзакцию</h1>
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
                    v-model="form.amount"
                    type="number"
                    step="0.01"
                    min="0.01"
                    placeholder="0.00"
                    required
                    class="amount-field"
                    :class="{ 'error': amountError }"
                    @input="validateAmount"
                    @blur="roundAmount"
                />
                <span class="currency-symbol">{{ currentCurrencySymbol }}</span>
              </div>
              <div v-if="amountError" class="error-message">{{ amountError }}</div>
            </div>
          </div>

          <!-- Валюта -->
          <div class="form-section">
            <div class="form-header">
              <h3 class="section-title">Валюта</h3>
            </div>

            <div v-if="currencies.length === 0" class="no-currencies">
              <div class="no-currencies-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"/>
                  <line x1="12" y1="8" x2="12" y2="12"/>
                  <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
              </div>
              <p>Загрузка валют...</p>
            </div>

            <div v-else class="currencies-grid">
              <div
                  v-for="currency in currencies"
                  :key="currency.id"
                  class="currency-option"
                  :class="{ selected: form.currency_id === currency.id }"
                  @click="selectCurrency(currency)"
              >
                <div class="currency-flag">{{ getCurrencyFlag(currency.code) }}</div>
                <div class="currency-name">{{ currency.name }}</div>
                <div class="currency-rate">{{ formatRate(currency.rate) }} Br</div>
                <div v-if="form.currency_id === currency.id" class="currency-check">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 6L9 17l-5-5"/>
                  </svg>
                </div>
              </div>
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
                      :class="{ 'date-disabled': isDateUnavailable }"
                      :min="minDate"
                      :max="maxDate"
                      @input="validateDate"
                  />
                  <button
                      type="button"
                      @click="setTodayDate"
                      class="date-today"
                      :disabled="isTodayUnavailable"
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

          <!-- Действия -->
          <div class="form-actions">
            <button
                type="submit"
                :disabled="submitting || !isFormValid || isDateUnavailable"
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
    const dateError = ref('')
    const categories = ref([])
    const currencies = ref([])
    const availableDates = ref([])
    const allDatesAllowed = ref(false)
    const minDate = ref('')
    const maxDate = ref('')

    const paymentMethods = [
      { value: 'card', name: 'Карта', icon: '💳' },
      { value: 'cash', name: 'Наличные', icon: '💵' },
      { value: 'transfer', name: 'Перевод', icon: '🏦' },
    ]

    const form = ref({
      amount: '',
      type: 'expense',
      category_ids: [],
      currency_id: null,
      description: '',
      date: new Date().toISOString().split('T')[0],
      payment_method: 'card'
    })

    // Округление до 2 знаков после запятой
    const roundToTwoDecimals = (value) => {
      const num = parseFloat(value)
      if (isNaN(num)) return ''
      return num.toFixed(2)
    }

    // Метод для округления при потере фокуса
    const roundAmount = () => {
      if (form.value.amount) {
        form.value.amount = roundToTwoDecimals(form.value.amount)
      }
    }

    // Установка минимальной и максимальной даты (последние 6 месяцев)
    const setDateRange = () => {
      const today = new Date()
      const sixMonthsAgo = new Date()
      sixMonthsAgo.setMonth(today.getMonth() - 6)
      maxDate.value = today.toISOString().split('T')[0]
      minDate.value = sixMonthsAgo.toISOString().split('T')[0]
    }

    // Получение доступных дат для выбранной валюты
    const fetchAvailableDates = async (currencyId) => {
      if (!currencyId) {
        console.warn('fetchAvailableDates called without currencyId')
        return
      }

      try {
        const response = await axios.get('/api/currencies/available-dates', {
          params: { currency_id: currencyId }
        })

        availableDates.value = response.data.data.available_dates || []
        allDatesAllowed.value = response.data.data.all_dates_allowed || false

        if (form.value.date) {
          validateDate()
        }
      } catch (err) {
        console.error('Error fetching available dates:', err)
      }
    }

    // Проверка доступности даты
    const isDateAvailable = (date) => {
      if (allDatesAllowed.value) return true
      if (!date) return false
      return availableDates.value.includes(date)
    }

    // Проверка валидности даты
    const validateDate = () => {
      if (!form.value.date) {
        dateError.value = ''
        return
      }

      const today = new Date().toISOString().split('T')[0]

      if (form.value.date > today) {
        dateError.value = 'Нельзя выбрать дату в будущем'
        return
      }

      if (allDatesAllowed.value) {
        dateError.value = ''
        return
      }

      if (form.value.date === today && !isDateAvailable(today)) {
        dateError.value = ''
        return
      }

      if (!isDateAvailable(form.value.date)) {
        dateError.value = `На дату ${form.value.date} нет курса для выбранной валюты.`
      } else {
        dateError.value = ''
      }
    }

    // Доступна ли сегодняшняя дата
    const isTodayUnavailable = computed(() => {
      return false
    })

    // Установка сегодняшней даты
    const setTodayDate = () => {
      const today = new Date().toISOString().split('T')[0]
      form.value.date = today
      dateError.value = ''
      validateDate()
    }

    // Подсказка по доступным датам
    const availableDatesHint = computed(() => {
      if (allDatesAllowed.value) return ''
      if (availableDates.value.length === 0) return 'Нет доступных дат'
      if (availableDates.value.length > 10) {
        return `${availableDates.value[0]} ... ${availableDates.value[availableDates.value.length - 1]} (${availableDates.value.length} дат)`
      }
      return availableDates.value.join(', ')
    })

    // Блокировка даты в календаре через атрибут
    const isDateUnavailable = computed(() => {
      const today = new Date().toISOString().split('T')[0]
      if (form.value.date === today) return false
      if (allDatesAllowed.value) return false
      return !isDateAvailable(form.value.date) && form.value.date !== ''
    })

    // Выбор валюты
    const selectCurrency = (currency) => {
      form.value.currency_id = currency.id
    }

    // Текущая выбранная валюта
    const currentCurrency = computed(() => {
      return currencies.value.find(c => c.id === form.value.currency_id)
    })

    // Символ текущей валюты
    const currentCurrencySymbol = computed(() => {
      if (!currentCurrency.value) return 'Br'
      const symbols = {
        'BYN': 'Br',
        'RUB': '₽',
        'USD': '$',
        'EUR': '€',
        'CNY': '¥',
      }
      return symbols[currentCurrency.value.code] || currentCurrency.value.code
    })

    // Флаги для валют
    const getCurrencyFlag = (code) => {
      const flags = {
        'BYN': '🇧🇾',
        'RUB': '🇷🇺',
        'USD': '🇺🇸',
        'EUR': '🇪🇺',
        'CNY': '🇨🇳',
      }
      return flags[code] || '💰'
    }

    // Форматирование курса
    const formatRate = (rate) => {
      if (!rate && rate !== 0) return '0.0000'
      return Number(rate).toFixed(4)
    }

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
          form.value.currency_id !== null &&
          form.value.date &&
          !dateError.value
    })

    const fetchTransaction = async () => {
      try {
        loading.value = true
        error.value = false

        const transactionId = props.id || route.params.id

        const [transactionRes, categoriesRes, currenciesRes] = await Promise.all([
          axios.get(`/api/transactions/${transactionId}`),
          axios.get('/api/categories'),
          axios.get('/api/currencies')
        ])

        const transactionData = transactionRes.data.data || transactionRes.data
        const categoriesData = categoriesRes.data.data || categoriesRes.data
        const currenciesData = currenciesRes.data.data || currenciesRes.data

        if (!transactionData) {
          throw new Error('Транзакция не найдена')
        }

        categories.value = categoriesData
        currencies.value = currenciesData

        let formattedDate = transactionData.date
        if (formattedDate) {
          formattedDate = formattedDate.split('T')[0]
        }

        let categoryIds = []
        if (transactionData.categories && Array.isArray(transactionData.categories)) {
          categoryIds = transactionData.categories.map(cat => cat.id)
        }

        // Округляем сумму до 2 знаков при загрузке
        const roundedAmount = transactionData.amount
            ? parseFloat(transactionData.amount).toFixed(2)
            : ''

        form.value.amount = roundedAmount
        form.value.type = transactionData.type || 'expense'
        form.value.category_ids = categoryIds
        form.value.currency_id = transactionData.currency_id || null
        form.value.description = transactionData.description || ''
        form.value.date = formattedDate
        form.value.payment_method = transactionData.payment_method || 'card'

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
    }

    const validateAmount = () => {
      const amount = parseFloat(form.value.amount)
      if (isNaN(amount) || amount < 0.01) {
        amountError.value = 'Сумма должна быть больше 0'
      } else if (amount > 10000000) {
        amountError.value = 'Слишком большая сумма'
      } else {
        amountError.value = ''
      }
    }

    const updateTransaction = async () => {
      if (!isFormValid.value) return

      const today = new Date().toISOString().split('T')[0]
      if (form.value.date > today) {
        error.value = 'Нельзя создать транзакцию на будущую дату'
        window.scrollTo({ top: 0, behavior: 'smooth' })
        return
      }

      try {
        submitting.value = true
        error.value = ''

        const transactionId = props.id || route.params.id

        // Округляем сумму перед отправкой
        const roundedAmount = parseFloat(form.value.amount).toFixed(2)

        const transactionData = {
          amount: roundedAmount,
          type: form.value.type,
          category_ids: form.value.category_ids,
          currency_id: form.value.currency_id,
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
      setDateRange()
      fetchTransaction()
    })

    watch(() => form.value.type, () => {
      form.value.category_ids = []
    })

    watch(() => form.value.date, () => {
      validateDate()
    })

    watch(() => form.value.currency_id, (newVal, oldVal) => {
      if (newVal && newVal !== oldVal) {
        fetchAvailableDates(newVal)
      }
    }, { immediate: true })

    return {
      form,
      loading,
      submitting,
      error,
      amountError,
      dateError,
      categories,
      currencies,
      filteredCategories,
      paymentMethods,
      isFormValid,
      currentCurrencySymbol,
      getCurrencyFlag,
      formatRate,
      validateAmount,
      roundAmount,
      toggleCategory,
      fetchTransaction,
      updateTransaction,
      selectCurrency,
      minDate,
      maxDate,
      isDateUnavailable,
      isTodayUnavailable,
      setTodayDate,
      availableDatesHint
    }
  }
}
</script>

<style scoped>
@import '../css/edit_transaction.css';

.add-transaction {
  max-width: 600px;
  margin: 0 auto;
  padding: 0 1rem 2rem;
  width: 100%;
  box-sizing: border-box;
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
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
}

.category-manage:hover {
  color: #2563eb;
}

/* Ввод суммы */
.amount-input-wrapper {
  max-width: 300px;
  width: 100%;
  box-sizing: border-box;
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
  box-sizing: border-box;
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

/* Валюты - сетка как у категорий */
.currencies-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 0.75rem;
}

.currency-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
}

.currency-option:hover {
  border-color: #cbd5e1;
  transform: translateY(-2px);
}

.currency-option.selected {
  border-color: #3b82f6;
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(59, 130, 246, 0.02) 100%);
}

.currency-flag {
  font-size: 1.5rem;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.currency-name {
  flex: 1;
  font-weight: 500;
  color: #1e293b;
  font-size: 0.875rem;
  margin-left: 0.5rem;
}

.currency-rate {
  font-size: 0.875rem;
  font-weight: 600;
  color: #3b82f6;
}

.currency-check {
  color: #3b82f6;
  flex-shrink: 0;
}

.no-currencies {
  text-align: center;
  padding: 2rem;
  background: #f8fafc;
  border-radius: 12px;
  border: 2px dashed #e2e8f0;
}

.no-currencies-icon {
  width: 64px;
  height: 64px;
  margin: 0 auto 1rem;
  color: #94a3b8;
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
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.75rem;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
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
  gap: 0.5rem;
  flex: 1;
}

.category-color {
  width: 12px;
  height: 12px;
  border-radius: 3px;
  flex-shrink: 0;
}

.category-name {
  font-weight: 500;
  color: #1e293b;
  font-size: 0.875rem;
}

.category-check {
  color: #3b82f6;
  flex-shrink: 0;
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
  box-sizing: border-box;
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

/* Стили для недоступной даты */
.date-input-wrapper input.date-disabled {
  border-color: #ef4444;
  background-color: #fef2f2;
}

.date-today:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.field-hint {
  font-size: 0.75rem;
  color: #f59e0b;
  margin-top: 0.25rem;
}

/* Адаптивность */
@media (max-width: 768px) {
  .edit-transaction {
    padding: 0 1rem 1.5rem;
  }

  .page-header {
    margin-bottom: 1.5rem;
  }

  .page-title {
    font-size: 1.5rem;
  }

  .form-container {
    padding: 1.5rem;
  }

  .type-selector {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  .type-option {
    padding: 1.25rem;
  }

  .amount-field {
    font-size: 1.75rem;
    padding: 0.875rem 2.5rem 0.875rem 1.25rem;
  }

  .currency-symbol {
    font-size: 1.75rem;
    right: 1.25rem;
  }

  .currencies-grid {
    grid-template-columns: 1fr;
    gap: 0.75rem;
  }

  .currency-option {
    padding: 0.875rem;
  }

  .categories-grid {
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 0.75rem;
  }

  .payment-methods {
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
  }

  .form-actions {
    flex-direction: column;
    gap: 0.75rem;
  }

  .btn {
    width: 100%;
    justify-content: center;
  }

  .section-title {
    font-size: 1.125rem;
  }
}

@media (max-width: 480px) {
  .edit-transaction {
    padding: 0 0.75rem 1rem;
  }

  .form-container {
    padding: 1.25rem;
  }

  .amount-field {
    font-size: 1.5rem;
  }

  .currency-option {
    padding: 0.75rem;
  }

  .currency-name {
    font-size: 0.75rem;
  }

  .currency-rate {
    font-size: 0.75rem;
  }

  .categories-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .payment-methods {
    grid-template-columns: 1fr;
  }
}
</style>