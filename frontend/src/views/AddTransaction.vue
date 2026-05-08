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
                @click="form.currency_id = currency.id"
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
    const currencies = ref([])

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
      payment_method: 'card',
    })

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

    // Форматирование курса (4 знака после запятой)
    const formatRate = (rate) => {
      if (!rate && rate !== 0) return '0.0000'
      return Number(rate).toFixed(4)
    }

    const filteredCategories = computed(() => {
      return categories.value
          .filter(cat => cat.type === form.value.type)
          .sort((a, b) => a.name.localeCompare(b.name))
    })

    const isFormValid = computed(() => {
      return form.value.amount > 0 &&
          form.value.category_ids.length > 0 &&
          form.value.currency_id !== null &&
          form.value.date
    })

    const fetchCategories = async () => {
      try {
        const response = await axios.get('/api/categories')
        categories.value = response.data.data || []
      } catch (error) {
        console.error('Error fetching categories:', error)
      }
    }

    const fetchCurrencies = async () => {
      try {
        const response = await axios.get('/api/currencies')
        currencies.value = response.data.data || []
        // Выбираем BYN по умолчанию
        const defaultCurrency = currencies.value.find(c => c.code === 'BYN')
        if (defaultCurrency) {
          form.value.currency_id = defaultCurrency.id
        } else if (currencies.value.length) {
          form.value.currency_id = currencies.value[0].id
        }
      } catch (error) {
        console.error('Error fetching currencies:', error)
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

    const toggleCategory = (categoryId) => {
      const index = form.value.category_ids.indexOf(categoryId)
      if (index === -1) {
        form.value.category_ids.push(categoryId)
      } else {
        form.value.category_ids.splice(index, 1)
      }
    }

    const submitTransaction = async () => {
      if (!isFormValid.value) return

      try {
        loading.value = true
        error.value = ''

        const transactionData = {
          amount: parseFloat(form.value.amount),
          type: form.value.type,
          category_ids: form.value.category_ids,
          currency_id: form.value.currency_id,
          description: form.value.description,
          date: form.value.date,
          payment_method: form.value.payment_method
        }

        await axios.post('/api/transactions', transactionData)

        if (window.showNotification) {
          window.showNotification('success', 'Транзакция успешно добавлена')
        }

        router.push('/transactions')
      } catch (err) {
        console.error('Error creating transaction:', err)
        error.value = err.response?.data?.message ||
            err.response?.data?.errors?.category_ids?.[0] ||
            'Ошибка при создании транзакции'

        window.scrollTo({ top: 0, behavior: 'smooth' })
      } finally {
        loading.value = false
      }
    }

    onMounted(() => {
      fetchCategories()
      fetchCurrencies()
    })

    watch(() => form.value.type, () => {
      form.value.category_ids = []
    })

    return {
      form,
      loading,
      error,
      amountError,
      categories,
      currencies,
      filteredCategories,
      paymentMethods,
      isFormValid,
      currentCurrencySymbol,
      getCurrencyFlag,
      formatRate,
      validateAmount,
      toggleCategory,
      submitTransaction
    }
  }
}
</script>

<style scoped>
@import '../css/add_transaction.css';
</style>