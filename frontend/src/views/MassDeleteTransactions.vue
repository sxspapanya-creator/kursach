<template>
  <div class="mass-delete-page">
    <!-- Заголовок -->
    <div class="page-header">
      <div class="header-left">
        <router-link to="/transactions" class="back-link">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 12H5M12 19l-7-7 7-7"/>
          </svg>
          Назад
        </router-link>
        <h1 class="page-title">Массовое удаление транзакций</h1>
        <p class="page-subtitle">Выберите транзакции для удаления по датам или категориям</p>
      </div>
    </div>

    <div class="delete-container">
      <!-- Вкладки выбора способа удаления -->
      <div class="delete-tabs">
        <button
            type="button"
            class="tab-btn"
            :class="{ active: activeTab === 'date' }"
            @click="activeTab = 'date'"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
            <line x1="16" y1="2" x2="16" y2="6"/>
            <line x1="8" y1="2" x2="8" y2="6"/>
            <line x1="3" y1="10" x2="21" y2="10"/>
          </svg>
          По датам
        </button>
        <button
            type="button"
            class="tab-btn"
            :class="{ active: activeTab === 'category' }"
            @click="activeTab = 'category'"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
            <line x1="7" y1="7" x2="7.01" y2="7"/>
          </svg>
          По категориям
        </button>
        <button
            type="button"
            class="tab-btn"
            :class="{ active: activeTab === 'type' }"
            @click="activeTab = 'type'"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <path d="M12 8v8M8 12h8"/>
          </svg>
          По типу
        </button>
      </div>

      <!-- Панель удаления по датам -->
      <div v-if="activeTab === 'date'" class="delete-panel">
        <div class="panel-header">
          <h3>Удаление транзакций по датам</h3>
          <p>Выберите диапазон дат или конкретную дату</p>
        </div>

        <div class="delete-form">
          <!-- Тип выбора -->
          <div class="form-group">
            <label class="form-label">Тип выбора</label>
            <div class="radio-group">
              <label class="radio-label">
                <input type="radio" v-model="dateSelectionType" value="range">
                <span>Диапазон дат</span>
              </label>
              <label class="radio-label">
                <input type="radio" v-model="dateSelectionType" value="single">
                <span>Конкретная дата</span>
              </label>
            </div>
          </div>

          <!-- Диапазон дат -->
          <div v-if="dateSelectionType === 'range'" class="date-range-group">
            <div class="form-group">
              <label class="form-label">Дата от</label>
              <input
                  type="date"
                  v-model="dateRange.from"
                  class="form-input"
                  :class="{ 'date-disabled': dateRange.from && !isDateAvailable(dateRange.from) }"
                  :min="minDate"
                  :max="maxDate"
                  @change="validateDateFrom"
              >
              <div v-if="dateFromError" class="field-hint error">{{ dateFromError }}</div>
            </div>
            <div class="form-group">
              <label class="form-label">Дата до</label>
              <input
                  type="date"
                  v-model="dateRange.to"
                  class="form-input"
                  :class="{ 'date-disabled': dateRange.to && !isDateAvailable(dateRange.to) }"
                  :min="minDate"
                  :max="maxDate"
                  @change="validateDateTo"
              >
              <div v-if="dateToError" class="field-hint error">{{ dateToError }}</div>
            </div>
          </div>

          <!-- Конкретная дата -->
          <div v-else class="single-date-group">
            <div class="form-group">
              <label class="form-label">Дата</label>
              <input
                  type="date"
                  v-model="singleDate"
                  class="form-input"
                  :class="{ 'date-disabled': singleDate && !isDateAvailable(singleDate) }"
                  :min="minDate"
                  :max="maxDate"
                  @change="validateSingleDate"
              >
              <div v-if="singleDateError" class="field-hint error">{{ singleDateError }}</div>
            </div>
            <div v-if="availableDatesHint" class="available-dates-hint">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
              </svg>
              <span>Доступные даты: {{ availableDatesHint }}</span>
            </div>
          </div>

          <!-- Тип транзакции -->
          <div class="form-group">
            <label class="form-label">Тип транзакции (опционально)</label>
            <select v-model="deleteFilters.type" class="form-select">
              <option value="">Все типы</option>
              <option value="income">Доходы</option>
              <option value="expense">Расходы</option>
            </select>
          </div>

          <div class="form-actions">
            <button
                type="button"
                @click="previewByDate"
                :disabled="!isDateSelectionValid || isDateSelectionUnavailable"
                class="btn btn-secondary"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 8v4l3 3"/>
              </svg>
              Предпросмотр
            </button>
          </div>
        </div>
      </div>

      <!-- Панель удаления по категориям -->
      <div v-if="activeTab === 'category'" class="delete-panel">
        <div class="panel-header">
          <h3>Удаление транзакций по категориям</h3>
          <p>Выберите категории для удаления всех транзакций в них</p>
        </div>

        <div class="delete-form">
          <div class="form-group full-width">
            <label class="form-label">Категории</label>
            <div class="categories-checkbox-list">
              <label v-for="category in allCategories" :key="category.id" class="checkbox-label">
                <input type="checkbox" :value="category.id" v-model="selectedCategories">
                <span class="category-color-dot" :style="{ backgroundColor: category.color }"></span>
                <span class="category-name">{{ category.name }}</span>
                <span class="category-badge" :class="category.type">
                  {{ category.type === 'income' ? 'Доход' : 'Расход' }}
                </span>
              </label>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Период (опционально)</label>
            <input type="month" v-model="categoryPeriod.month" class="form-input">
            <p class="field-hint muted">
              Если месяц выбран, ищутся только транзакции за этот месяц. Очистите поле, чтобы взять все даты.
            </p>
          </div>

          <div class="form-actions">
            <button
                type="button"
                @click="previewByCategory"
                :disabled="selectedCategories.length === 0"
                class="btn btn-secondary"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 8v4l3 3"/>
              </svg>
              Предпросмотр
            </button>
          </div>
        </div>
      </div>

      <!-- Панель удаления по типу -->
      <div v-if="activeTab === 'type'" class="delete-panel">
        <div class="panel-header">
          <h3>Удаление транзакций по типу</h3>
          <p>Удалить все доходы или все расходы</p>
        </div>

        <div class="delete-form">
          <div class="form-group">
            <label class="form-label">Тип транзакций для удаления</label>
            <div class="radio-group">
              <label class="radio-label danger">
                <input type="radio" v-model="deleteType" value="income">
                <span>Все доходы</span>
              </label>
              <label class="radio-label danger">
                <input type="radio" v-model="deleteType" value="expense">
                <span>Все расходы</span>
              </label>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Период (опционально)</label>
            <input type="month" v-model="typePeriod.month" class="form-input">
          </div>

          <div class="form-actions">
            <button
                type="button"
                @click="previewByType"
                :disabled="!deleteType"
                class="btn btn-secondary"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 8v4l3 3"/>
              </svg>
              Предпросмотр
            </button>
          </div>
        </div>
      </div>

      <!-- Результаты предпросмотра -->
      <div v-if="previewTransactions.length > 0" class="preview-section">
        <div class="preview-header">
          <h3>Найдено транзакций: {{ previewTransactions.length }}</h3>
          <div class="preview-summary">
            <span class="income-summary">Доходы: {{ formatMoney(previewStats.income) }}</span>
            <span class="expense-summary">Расходы: {{ formatMoney(previewStats.expenses) }}</span>
            <span class="balance-summary">Баланс: {{ formatMoney(previewStats.balance) }}</span>
          </div>
        </div>

        <div class="preview-list">
          <div
              v-for="transaction in previewTransactions"
              :key="transaction.id"
              class="preview-item"
          >
            <div class="preview-date">{{ formatDate(transaction.date) }}</div>
            <div class="preview-description">{{ transaction.description || 'Без описания' }}</div>
            <div class="preview-categories">
              <span v-for="cat in transaction.categories" :key="cat.id" class="preview-category">
                {{ cat.name }}
              </span>
            </div>
            <div class="preview-amount" :class="transaction.type">
              {{ transaction.type === 'income' ? '+' : '-' }} {{ formatTransactionMoney(transaction) }}
            </div>
          </div>
        </div>

        <div class="preview-actions">
          <button @click="showConfirmDialog = true" class="btn btn-danger btn-large">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
            </svg>
            Удалить {{ previewTransactions.length }} транзакций
          </button>
        </div>
      </div>

      <div v-else-if="previewLoaded && previewTransactions.length === 0" class="empty-preview">
        <div class="empty-icon">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
        </div>
        <h3>Транзакции не найдены</h3>
        <p>По вашему запросу ничего не найдено</p>
      </div>
    </div>

    <!-- Модальное окно подтверждения -->
    <transition name="modal">
      <div v-if="showConfirmDialog" class="modal-overlay" @click="showConfirmDialog = false">
        <div class="modal-content" @click.stop>
          <div class="modal-header">
            <h3 class="modal-title">
              <span class="modal-icon">⚠️</span>
              Подтверждение удаления
            </h3>
            <button @click="showConfirmDialog = false" class="modal-close">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="modal-body">
            <p>Вы действительно хотите удалить <strong>{{ previewTransactions.length }}</strong> транзакций?</p>
            <p class="warning-text">Это действие нельзя отменить!</p>

            <div class="delete-summary">
              <div>Доходы: {{ formatMoney(previewStats.income) }}</div>
              <div>Расходы: {{ formatMoney(previewStats.expenses) }}</div>
              <div>Баланс: {{ formatMoney(previewStats.balance) }}</div>
            </div>
          </div>

          <div class="modal-footer">
            <button @click="showConfirmDialog = false" class="btn btn-secondary">Отмена</button>
            <button @click="executeDelete" :disabled="deleting" class="btn btn-danger">
              <span v-if="deleting" class="spinner"></span>
              {{ deleting ? 'Удаление...' : 'Да, удалить' }}
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- Сообщение об ошибке -->
    <transition name="slide-fade">
      <div v-if="error" class="error-toast">
        <div class="error-icon">❌</div>
        <div class="error-content">
          <div class="error-title">Ошибка</div>
          <div class="error-message">{{ error }}</div>
        </div>
        <button @click="error = ''" class="error-close">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 6L6 18M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </transition>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

export default {
  name: 'MassDeleteTransactions',
  setup() {
    const router = useRouter()

    // Состояние
    const activeTab = ref('date')
    const loading = ref(false)
    const deleting = ref(false)
    const error = ref('')
    const previewTransactions = ref([])
    const previewLoaded = ref(false)
    const showConfirmDialog = ref(false)

    // Фильтры
    const deleteFilters = ref({ type: '' })

    // Удаление по датам
    const dateSelectionType = ref('range')
    const dateRange = ref({ from: '', to: '' })
    const singleDate = ref('')
    const dateFromError = ref('')
    const dateToError = ref('')
    const singleDateError = ref('')
    const minDate = ref('')
    const maxDate = ref('')

    // Доступные даты для валют
    const availableDates = ref([])
    const allDatesAllowed = ref(false)
    const currencies = ref([])
    const bynCurrency = ref(null)

    // Удаление по категориям
    const allCategories = ref([])
    const selectedCategories = ref([])
    const categoryPeriod = ref({ month: '' })

    // Удаление по типу
    const deleteType = ref('')
    const typePeriod = ref({ month: '' })

    // Установка минимальной и максимальной даты
    const setDateRange = () => {
      if (availableDates.value.length > 0) {
        minDate.value = availableDates.value[0]
        maxDate.value = availableDates.value[availableDates.value.length - 1]
      } else {
        const today = new Date()
        const sixMonthsAgo = new Date()
        sixMonthsAgo.setMonth(today.getMonth() - 6)
        maxDate.value = today.toISOString().split('T')[0]
        minDate.value = sixMonthsAgo.toISOString().split('T')[0]
      }
    }

    // Загрузка валют (как в AddTransaction)
    const fetchCurrencies = async () => {
      try {
        const response = await axios.get('/api/currencies')
        currencies.value = response.data.data || []
        const byn = currencies.value.find(c => c.code === 'BYN')
        if (byn) {
          bynCurrency.value = byn
          await fetchAvailableDates(byn.id)
        }
      } catch (err) {
        console.error('Error fetching currencies:', err)
      }
    }

    // Загрузка доступных дат
    const fetchAvailableDates = async (currencyId) => {
      try {
        const response = await axios.get('/api/currencies/available-dates', {
          params: { currency_id: currencyId }
        })
        availableDates.value = response.data.data.available_dates || []
        allDatesAllowed.value = response.data.data.all_dates_allowed || false
        setDateRange()
      } catch (err) {
        console.error('Error fetching available dates:', err)
      }
    }

    // Проверка доступности даты
    const isDateAvailable = (date) => {
      if (!date) return true
      if (allDatesAllowed.value) return true
      return availableDates.value.includes(date)
    }

    // Валидация дат
    const validateDateFrom = () => {
      dateFromError.value = ''
      if (dateRange.value.from && !isDateAvailable(dateRange.value.from)) {
        dateFromError.value = 'На эту дату нет курса валют'
      }
    }

    const validateDateTo = () => {
      dateToError.value = ''
      if (dateRange.value.to && !isDateAvailable(dateRange.value.to)) {
        dateToError.value = 'На эту дату нет курса валют'
      }
    }

    const validateSingleDate = () => {
      singleDateError.value = ''
      if (singleDate.value && !isDateAvailable(singleDate.value)) {
        singleDateError.value = 'На эту дату нет курса валют'
      }
    }

    // Подсказка с доступными датами
    const availableDatesHint = computed(() => {
      if (allDatesAllowed.value) return ''
      if (availableDates.value.length === 0) return 'Нет доступных дат'
      if (availableDates.value.length > 10) {
        return `${availableDates.value[0]} ... ${availableDates.value[availableDates.value.length - 1]} (${availableDates.value.length} дат)`
      }
      return availableDates.value.join(', ')
    })

    // Символы валют
    const getCurrencySymbol = (currencyCode) => {
      const symbols = { 'BYN': 'Br', 'RUB': '₽', 'USD': '$', 'EUR': '€', 'CNY': '¥' }
      return symbols[currencyCode] || 'Br'
    }

    const formatMoney = (amount) => {
      if (amount === null || isNaN(amount)) return '0 Br'
      return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }).format(amount) + ' Br'
    }

    const formatTransactionMoney = (transaction) => {
      const currencyCode = transaction.currency?.code || 'BYN'
      const symbol = getCurrencySymbol(currencyCode)
      return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }).format(transaction.amount) + ' ' + symbol
    }

    const formatDate = (dateString) => {
      if (!dateString) return ''
      const date = new Date(dateString)
      return date.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' })
    }

    // Валидация дат для кнопки
    const isDateSelectionValid = computed(() => {
      if (dateSelectionType.value === 'range') {
        return dateRange.value.from && dateRange.value.to
      }
      return singleDate.value
    })

    const isDateSelectionUnavailable = computed(() => {
      if (dateSelectionType.value === 'range') {
        return (dateRange.value.from && !isDateAvailable(dateRange.value.from)) ||
            (dateRange.value.to && !isDateAvailable(dateRange.value.to))
      }
      return singleDate.value && !isDateAvailable(singleDate.value)
    })

    // Статистика предпросмотра
    const previewStats = computed(() => {
      let income = 0
      let expenses = 0

      previewTransactions.value.forEach(t => {
        const amount = parseFloat(t.amount) || 0
        if (t.type === 'income') income += amount
        else expenses += amount
      })

      return { income, expenses, balance: income - expenses }
    })

    // Загрузка категорий
    const fetchCategories = async () => {
      try {
        const response = await axios.get('/api/categories')
        allCategories.value = response.data.data || []
      } catch (err) {
        console.error('Error fetching categories:', err)
      }
    }

    /** Laravel ожидает category_ids[] в query; стандартная сериализация axios для GET часто ломает массив. */
    const buildTransactionsQueryString = (opts) => {
      const sp = new URLSearchParams()
      if (opts.fetch_all) sp.set('fetch_all', '1')
      if (opts.type) sp.set('type', opts.type)
      if (opts.date_from) sp.set('date_from', opts.date_from)
      if (opts.date_to) sp.set('date_to', opts.date_to)
      if (opts.year != null && opts.year !== '') sp.set('year', String(opts.year))
      if (opts.month != null && opts.month !== '') sp.set('month', String(opts.month))
      if (opts.category_ids?.length) {
        for (const id of opts.category_ids) {
          sp.append('category_ids[]', String(id))
        }
      }
      return sp.toString()
    }

    // Предпросмотр по датам
    const previewByDate = async () => {
      if (isDateSelectionUnavailable.value) {
        error.value = 'Выбраны даты без курса валют'
        setTimeout(() => { error.value = '' }, 3000)
        return
      }

      try {
        loading.value = true
        error.value = ''

        let params = { fetch_all: true }

        if (dateSelectionType.value === 'range') {
          params.date_from = dateRange.value.from
          params.date_to = dateRange.value.to
        } else {
          params.date_from = singleDate.value
          params.date_to = singleDate.value
        }

        if (deleteFilters.value.type) {
          params.type = deleteFilters.value.type
        }

        const response = await axios.get('/api/transactions', { params })
        previewTransactions.value = response.data.data || []
        previewLoaded.value = true

        if (previewTransactions.value.length === 0) {
          error.value = 'По вашему запросу транзакции не найдены'
          setTimeout(() => { error.value = '' }, 3000)
        }
      } catch (err) {
        console.error('Error previewing transactions:', err)
        error.value = 'Ошибка при поиске транзакций'
      } finally {
        loading.value = false
      }
    }

    // Предпросмотр по категориям
    const previewByCategory = async () => {
      try {
        loading.value = true
        error.value = ''

        const opts = {
          fetch_all: true,
          category_ids: selectedCategories.value
        }
        if (categoryPeriod.value.month) {
          const [year, month] = categoryPeriod.value.month.split('-')
          opts.year = parseInt(year, 10)
          opts.month = parseInt(month, 10)
        }

        const qs = buildTransactionsQueryString(opts)
        const response = await axios.get(`/api/transactions?${qs}`)
        previewTransactions.value = response.data.data || []
        previewLoaded.value = true

        if (previewTransactions.value.length === 0) {
          error.value = 'По вашему запросу транзакции не найдены'
          setTimeout(() => { error.value = '' }, 3000)
        }
      } catch (err) {
        console.error('Error previewing transactions:', err)
        error.value = 'Ошибка при поиске транзакций'
      } finally {
        loading.value = false
      }
    }

    // Предпросмотр по типу
    const previewByType = async () => {
      try {
        loading.value = true
        error.value = ''

        let params = { fetch_all: true, type: deleteType.value }

        if (typePeriod.value.month) {
          const [year, month] = typePeriod.value.month.split('-')
          params.year = parseInt(year)
          params.month = parseInt(month)
        }

        const response = await axios.get('/api/transactions', { params })
        previewTransactions.value = response.data.data || []
        previewLoaded.value = true

        if (previewTransactions.value.length === 0) {
          error.value = 'По вашему запросу транзакции не найдены'
          setTimeout(() => { error.value = '' }, 3000)
        }
      } catch (err) {
        console.error('Error previewing transactions:', err)
        error.value = 'Ошибка при поиске транзакций'
      } finally {
        loading.value = false
      }
    }

    // Выполнение удаления
    const executeDelete = async () => {
      try {
        deleting.value = true
        error.value = ''

        const transactionIds = previewTransactions.value.map(t => t.id)

        await axios.post('/api/transactions/mass-delete', {
          transaction_ids: transactionIds
        })

        showConfirmDialog.value = false
        previewTransactions.value = []
        previewLoaded.value = false

        if (window.showNotification) {
          window.showNotification('success', `Удалено ${transactionIds.length} транзакций`)
        }

        router.push('/transactions')
      } catch (err) {
        console.error('Error deleting transactions:', err)
        error.value = err.response?.data?.message || 'Ошибка при удалении транзакций'
      } finally {
        deleting.value = false
      }
    }

    onMounted(() => {
      fetchCategories()
      fetchCurrencies()
    })

    return {
      activeTab,
      loading,
      deleting,
      error,
      previewTransactions,
      previewLoaded,
      showConfirmDialog,
      deleteFilters,
      dateSelectionType,
      dateRange,
      singleDate,
      dateFromError,
      dateToError,
      singleDateError,
      minDate,
      maxDate,
      allCategories,
      selectedCategories,
      categoryPeriod,
      deleteType,
      typePeriod,
      isDateSelectionValid,
      isDateSelectionUnavailable,
      previewStats,
      availableDatesHint,
      isDateAvailable,
      validateDateFrom,
      validateDateTo,
      validateSingleDate,
      formatMoney,
      formatTransactionMoney,
      formatDate,
      previewByDate,
      previewByCategory,
      previewByType,
      executeDelete
    }
  }
}
</script>

<style scoped>
.mass-delete-page {
  max-width: 1000px;
  margin: 0 auto;
  padding: 0 1rem 2rem;
  width: 100%;
  box-sizing: border-box;
}

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

.delete-container {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  width: 100%;
  box-sizing: border-box;
}

.delete-tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 2rem;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 1rem;
  flex-wrap: wrap;
}

.tab-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  background: none;
  border: none;
  border-radius: 8px;
  font-size: 0.95rem;
  font-weight: 500;
  color: #64748b;
  cursor: pointer;
  transition: all 0.2s;
}

.tab-btn:hover {
  background: #f1f5f9;
  color: #1e293b;
}

.tab-btn.active {
  background: #3b82f6;
  color: white;
}

.delete-panel {
  animation: fadeIn 0.3s ease;
}

.panel-header {
  margin-bottom: 1.5rem;
}

.panel-header h3 {
  font-size: 1.25rem;
  margin: 0 0 0.25rem 0;
  color: #1e293b;
}

.panel-header p {
  color: #64748b;
  font-size: 0.875rem;
  margin: 0;
}

.delete-form {
  background: #f8fafc;
  padding: 1.5rem;
  border-radius: 12px;
  margin-bottom: 1.5rem;
  width: 100%;
  box-sizing: border-box;
}

.form-group {
  margin-bottom: 1.25rem;
  width: 100%;
  box-sizing: border-box;
}

.form-group.full-width {
  width: 100%;
}

.form-label {
  display: block;
  font-size: 0.875rem;
  font-weight: 600;
  color: #475569;
  margin-bottom: 0.5rem;
}

.form-input, .form-select {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.95rem;
  transition: all 0.2s;
  box-sizing: border-box;
}

.form-input:focus, .form-select:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-input.date-disabled {
  border-color: #ef4444;
  background-color: #fef2f2;
}

.radio-group {
  display: flex;
  gap: 1.5rem;
  flex-wrap: wrap;
}

.radio-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}

.radio-label.danger {
  color: #dc2626;
}

.date-range-group {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.date-range-group .form-group {
  flex: 1;
  min-width: 150px;
}

.single-date-group {
  max-width: 100%;
}

.available-dates-hint {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.75rem;
  color: #f59e0b;
  margin-top: 0.5rem;
  padding: 0.5rem;
  background: #fffbeb;
  border-radius: 6px;
}

.field-hint {
  font-size: 0.75rem;
  margin-top: 0.25rem;
}

.field-hint.error {
  color: #ef4444;
}

.field-hint.muted {
  font-size: 0.75rem;
  color: #64748b;
  margin: 0.375rem 0 0;
  line-height: 1.4;
}

.form-actions {
  margin-top: 1rem;
  display: flex;
  justify-content: flex-end;
}

.categories-checkbox-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 0.75rem;
  max-height: 300px;
  overflow-y: auto;
  padding: 0.5rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: white;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.2s;
}

.checkbox-label:hover {
  background: #f1f5f9;
}

.category-color-dot {
  width: 12px;
  height: 12px;
  border-radius: 4px;
  flex-shrink: 0;
}

.category-name {
  flex: 1;
  font-size: 0.875rem;
}

.category-badge {
  font-size: 0.7rem;
  padding: 0.125rem 0.375rem;
  border-radius: 4px;
}

.category-badge.income {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.category-badge.expense {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  font-size: 0.95rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
}

.btn-secondary {
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
}

.btn-secondary:hover:not(:disabled) {
  background: #e2e8f0;
}

.btn-secondary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-danger {
  background: #ef4444;
  color: white;
}

.btn-danger:hover:not(:disabled) {
  background: #dc2626;
}

.btn-large {
  padding: 0.875rem 2rem;
  font-size: 1rem;
}

.preview-section {
  margin-top: 2rem;
  border-top: 1px solid #e2e8f0;
  padding-top: 1.5rem;
}

.preview-header {
  margin-bottom: 1rem;
}

.preview-header h3 {
  font-size: 1.125rem;
  margin: 0 0 0.5rem 0;
}

.preview-summary {
  display: flex;
  gap: 1rem;
  font-size: 0.875rem;
  flex-wrap: wrap;
}

.income-summary { color: #10b981; }
.expense-summary { color: #ef4444; }
.balance-summary { color: #3b82f6; }

.preview-list {
  max-height: 400px;
  overflow-y: auto;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  margin-bottom: 1rem;
}

.preview-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #e2e8f0;
  gap: 1rem;
  flex-wrap: wrap;
}

.preview-item:last-child {
  border-bottom: none;
}

.preview-date {
  font-size: 0.75rem;
  color: #64748b;
  min-width: 80px;
}

.preview-description {
  flex: 2;
  min-width: 150px;
  font-size: 0.875rem;
  color: #1e293b;
}

.preview-categories {
  display: flex;
  gap: 0.25rem;
  flex-wrap: wrap;
}

.preview-category {
  font-size: 0.7rem;
  padding: 0.125rem 0.375rem;
  background: #f1f5f9;
  border-radius: 4px;
  color: #475569;
}

.preview-amount {
  font-weight: 600;
  font-size: 0.875rem;
  min-width: 100px;
  text-align: right;
}

.preview-amount.income { color: #10b981; }
.preview-amount.expense { color: #ef4444; }

.preview-actions {
  text-align: center;
  margin-top: 1rem;
}

.empty-preview {
  text-align: center;
  padding: 3rem;
}

.empty-icon {
  color: #94a3b8;
  margin-bottom: 1rem;
}

/* Модальное окно */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 16px;
  width: 90%;
  max-width: 500px;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #e2e8f0;
}

.modal-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
}

.modal-close {
  background: none;
  border: none;
  cursor: pointer;
  color: #64748b;
}

.modal-body {
  padding: 1.5rem;
}

.warning-text {
  color: #ef4444;
  font-weight: 500;
}

.delete-summary {
  background: #f8fafc;
  padding: 1rem;
  border-radius: 8px;
  margin-top: 1rem;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding: 1.25rem 1.5rem;
  border-top: 1px solid #e2e8f0;
}

/* Error toast */
.error-toast {
  position: fixed;
  bottom: 20px;
  right: 20px;
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 12px;
  z-index: 1000;
}

/* Анимации */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

.spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  border-top-color: white;
  animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.slide-fade-enter-active,
.slide-fade-leave-active {
  transition: all 0.3s ease;
}

.slide-fade-enter-from,
.slide-fade-leave-to {
  transform: translateX(100%);
  opacity: 0;
}

.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

@media (max-width: 768px) {
  .mass-delete-page {
    padding: 0 0.75rem 1rem;
  }

  .delete-container {
    padding: 1rem;
  }

  .delete-form {
    padding: 1rem;
  }

  .date-range-group {
    flex-direction: column;
  }

  .date-range-group .form-group {
    width: 100%;
  }

  .preview-item {
    flex-direction: column;
    align-items: flex-start;
  }

  .preview-amount {
    text-align: left;
  }

  .form-actions {
    justify-content: stretch;
  }

  .form-actions .btn {
    width: 100%;
    justify-content: center;
  }

  .radio-group {
    flex-direction: column;
    gap: 0.75rem;
  }
}
</style>