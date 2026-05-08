<template>
  <div class="categories-page">
    <!-- Hero Section -->
    <div class="hero-section">
      <div class="hero-content">
        <h1 class="hero-title">
          <span class="hero-icon">📂</span>
          Категории
        </h1>
        <p class="hero-subtitle">Управление категориями доходов и расходов</p>
      </div>
    </div>

    <!-- Header with Actions -->
    <div class="section-header">
      <div class="header-content">
        <h2 class="section-title">Управление категориями</h2>
        <div class="header-actions">
          <button @click="openAddForm('income')" class="btn btn-success">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 5v14M5 12h14"/>
            </svg>
            Категория доходов
          </button>
          <button @click="openAddForm('expense')" class="btn btn-danger">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 5v14M5 12h14"/>
            </svg>
            Категория расходов
          </button>
        </div>
      </div>
    </div>

    <!-- Category Tabs -->
    <div class="category-tabs">
      <div class="tabs-header">
        <button
            @click="activeTab = 'income'"
            :class="['tab-btn', { 'active': activeTab === 'income' }]"
        >
          <span class="tab-icon">💰</span>
          <span class="tab-text">Доходы</span>
          <span class="tab-count">{{ incomeCategories.length }}</span>
        </button>
        <button
            @click="activeTab = 'expense'"
            :class="['tab-btn', { 'active': activeTab === 'expense' }]"
        >
          <span class="tab-icon">💸</span>
          <span class="tab-text">Расходы</span>
          <span class="tab-count">{{ expenseCategories.length }}</span>
        </button>
        <button
            @click="activeTab = 'all'"
            :class="['tab-btn', { 'active': activeTab === 'all' }]"
        >
          <span class="tab-icon">📊</span>
          <span class="tab-text">Все</span>
          <span class="tab-count">{{ totalCategories }}</span>
        </button>
      </div>
    </div>

    <!-- Модальное окно для добавления/редактирования категории -->
    <transition name="modal">
      <div v-if="showModal" class="modal-overlay" @click="closeModal">
        <div class="modal-content" @click.stop>
          <div class="modal-header">
            <h3 class="modal-title">
              <span class="modal-icon">
                {{ isEditing ? '✏️' : '➕' }}
              </span>
              {{ isEditing ? 'Редактирование категории' : 'Новая категория' }}
            </h3>
            <button @click="closeModal" class="modal-close">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <form @submit.prevent="saveCategory" class="category-form">
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">
                  <span class="label-text">Название категории</span>
                  <span class="label-required">*</span>
                </label>
                <div class="input-with-icon">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                    <line x1="7" y1="7" x2="7.01" y2="7"/>
                  </svg>
                  <input
                      v-model="formData.name"
                      type="text"
                      placeholder="Например: Зарплата, Продукты..."
                      required
                      class="form-input"
                      :class="{ 'has-error': formErrors.name }"
                  >
                </div>
                <div v-if="formErrors.name" class="error-message">{{ formErrors.name }}</div>
              </div>

              <div class="form-group">
                <label class="form-label">
                  <span class="label-text">Тип категории</span>
                  <span class="label-required">*</span>
                </label>
                <div class="type-buttons">
                  <button
                      type="button"
                      @click="formData.type = 'income'"
                      :class="['type-btn', { 'active': formData.type === 'income' }]"
                      :disabled="isEditing && hasTransactions"
                  >
                    <span class="type-icon">💰</span>
                    <span class="type-text">Доход</span>
                  </button>
                  <button
                      type="button"
                      @click="formData.type = 'expense'"
                      :class="['type-btn', { 'active': formData.type === 'expense' }]"
                      :disabled="isEditing && hasTransactions"
                  >
                    <span class="type-icon">💸</span>
                    <span class="type-text">Расход</span>
                  </button>
                </div>
                <div v-if="isEditing && hasTransactions" class="field-hint">
                  ⚠️ Нельзя изменить тип категории с транзакциями
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">
                  <span class="label-text">Цвет категории</span>
                </label>
                <div class="color-picker-section">
                  <div class="color-grid">
                    <div
                        v-for="color in colorOptions"
                        :key="color"
                        :class="['color-option', { 'selected': formData.color === color }]"
                        :style="{ backgroundColor: color }"
                        @click="formData.color = color"
                    >
                      <div v-if="formData.color === color" class="color-check">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3">
                          <path d="M20 6L9 17l-5-5"/>
                        </svg>
                      </div>
                    </div>
                  </div>
                  <div class="color-custom">
                    <input
                        v-model="formData.color"
                        type="color"
                        class="color-input"
                    >
                    <div class="color-preview" :style="{ backgroundColor: formData.color }"></div>
                    <span class="color-value">{{ formData.color }}</span>
                  </div>
                </div>
              </div>

              <!-- Лимит бюджета (только для расходов) -->
              <div class="form-group" v-if="formData.type === 'expense'">
                <label class="form-label">
                  <span class="label-text">Лимит бюджета</span>
                  <span class="label-required">*</span>
                  <span class="label-hint">(обязательно)</span>
                </label>
                <div class="input-with-icon">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="6" width="20" height="12" rx="2"/>
                    <path d="M16 16v-4M8 16v-4"/>
                  </svg>
                  <input
                      v-model.number="formData.budget_limit"
                      type="number"
                      min="1"
                      step="0.01"
                      placeholder="0.00"
                      required
                      class="form-input"
                      :class="{ 'has-error': formData.type === 'expense' && !formData.budget_limit }"
                  >
                  <span class="currency">Br</span>
                </div>
                <div v-if="formData.type === 'expense' && !formData.budget_limit" class="error-message">
                  Для категории расходов необходимо установить лимит бюджета
                </div>
                <div class="field-hint">
                  Установите месячный лимит для контроля расходов
                </div>
              </div>
            </div>

            <div class="form-actions">
              <div class="actions-left">
                <button
                    v-if="isEditing"
                    type="button"
                    @click="confirmDelete"
                    class="btn btn-danger"
                    :disabled="hasTransactions"
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    <line x1="10" y1="11" x2="10" y2="17"/>
                    <line x1="14" y1="11" x2="14" y2="17"/>
                  </svg>
                  Удалить
                </button>
              </div>
              <div class="actions-right">
                <button
                    type="button"
                    @click="closeModal"
                    class="btn btn-secondary"
                >
                  Отмена
                </button>
                <button
                    type="submit"
                    :disabled="loading || !formData.name.trim() || (formData.type === 'expense' && !formData.budget_limit)"
                    class="btn btn-primary"
                >
                  <span v-if="loading" class="btn-loading">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                    </svg>
                  </span>
                  <span v-else>{{ isEditing ? 'Сохранить' : 'Создать' }}</span>
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </transition>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="loading-spinner"></div>
      <p>Загрузка категорий...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="displayedCategories.length === 0" class="empty-state">
      <div class="empty-icon">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
          <line x1="7" y1="7" x2="7.01" y2="7"/>
        </svg>
      </div>
      <h3>Категории не найдены</h3>
      <p>
        <span v-if="activeTab === 'income'">У вас еще нет категорий доходов</span>
        <span v-else-if="activeTab === 'expense'">У вас еще нет категорий расходов</span>
        <span v-else>У вас еще нет категорий</span>
      </p>
      <button @click="openAddForm(activeTab === 'income' ? 'income' : 'expense')" class="btn btn-primary btn-large">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 5v14M5 12h14"/>
        </svg>
        Добавить первую категорию
      </button>
    </div>

    <!-- Categories Grid -->
    <div v-else class="categories-section">
      <div class="categories-grid">
        <div
            v-for="category in displayedCategoriesWithMonthData"
            :key="category.id"
            class="category-card"
            @click="openEditForm(category)"
        >
          <div class="category-header">
            <div class="category-badge" :style="{ backgroundColor: category.color + '20' }">
              <div
                  class="category-color"
                  :style="{ backgroundColor: category.color }"
              ></div>
              <span class="category-type" :class="category.type">
                {{ category.type === 'income' ? 'Доход' : 'Расход' }}
              </span>
            </div>
            <div class="category-actions">
              <button
                  @click.stop="openEditForm(category)"
                  class="action-btn edit-btn"
                  title="Редактировать"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
              </button>
            </div>
          </div>

          <div class="category-content">
            <h4 class="category-name">{{ category.name }}</h4>

            <div class="category-stats">
              <div class="stat-item">
                <div class="stat-label">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="1" y="4" width="22" height="16" rx="2"/>
                    <line x1="1" y1="10" x2="23" y2="10"/>
                  </svg>
                  Всего транзакций
                </div>
                <div class="stat-value">{{ category.transaction_count || 0 }}</div>
              </div>

              <!-- Статистика за текущий месяц -->
              <div class="stat-item">
                <div class="stat-label">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                  </svg>
                  Текущий месяц
                </div>
                <div class="stat-value" :class="category.type">
                  {{ formatMoney(category.current_month_total || 0) }}
                </div>
              </div>

              <!-- Количество транзакций за месяц -->
              <div class="stat-item" v-if="category.current_month_count > 0">
                <div class="stat-label">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 8v8M8 12h8"/>
                  </svg>
                  Транзакций за месяц
                </div>
                <div class="stat-value">{{ category.current_month_count }}</div>
              </div>
            </div>

            <!-- Бюджетная информация (только для расходов) -->
            <div v-if="category.type === 'expense' && category.budget_limit" class="budget-section">
              <div class="budget-header">
                <span class="budget-label">Месячный лимит</span>
                <span class="budget-limit">{{ formatMoney(category.budget_limit) }}</span>
              </div>

              <div v-if="category.current_month_total !== undefined" class="budget-progress">
                <div class="progress-bar">
                  <div
                      class="progress-fill"
                      :style="{
                      width: `${Math.min((category.current_month_total / category.budget_limit) * 100, 100)}%`,
                      backgroundColor: getProgressColor(category)
                    }"
                  ></div>
                </div>
                <div class="progress-info">
                  <span class="progress-text">
                    {{ Math.round((category.current_month_total / category.budget_limit) * 100) }}%
                  </span>
                  <span v-if="isOverLimit(category)" class="progress-warning">
                    ⚠️ Превышение
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div class="category-footer">
            <div class="transaction-info">
              <span class="transaction-date">
                Последняя транзакция : {{ formatDate(category.last_transaction_date) }}
              </span>

            </div>
            <button
                v-if="(category.transaction_count || 0) === 0"
                @click.stop="promptDelete(category)"
                class="delete-btn"
                title="Удалить"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Error Message -->
    <transition name="slide-fade">
      <div v-if="error" class="error-toast">
        <div class="error-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
        </div>
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
import axios from 'axios'

export default {
  name: 'CategoriesPage',
  setup() {
    const categories = ref([])
    const loading = ref(true)
    const error = ref('')
    const showModal = ref(false)
    const isEditing = ref(false)
    const editingId = ref(null)
    const hasTransactions = ref(false)
    const activeTab = ref('all')
    const formErrors = ref({})
    const currentMonth = new Date().getMonth() + 1
    const currentYear = new Date().getFullYear()

    const colorOptions = [
      '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
      '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#64748b'
    ]

    const formData = ref({
      name: '',
      type: 'expense',
      color: '#3b82f6',
      budget_limit: null
    })

    // --- Computed ---
    const incomeCategories = computed(() =>
        categories.value.filter(c => c.type === 'income').sort((a, b) => a.name.localeCompare(b.name))
    )

    const expenseCategories = computed(() =>
        categories.value.filter(c => c.type === 'expense').sort((a, b) => a.name.localeCompare(b.name))
    )

    const totalCategories = computed(() => categories.value.length)

    const displayedCategories = computed(() => {
      if (activeTab.value === 'income') return incomeCategories.value
      if (activeTab.value === 'expense') return expenseCategories.value
      return [...incomeCategories.value, ...expenseCategories.value]
    })

    const displayedCategoriesWithMonthData = computed(() =>
        displayedCategories.value.map(category => {
          // Все данные уже пришли с сервера
          return {
            ...category,
            current_month_total: category.current_month_total || 0,
            current_month_count: category.current_month_count || 0,
            last_transaction_date: category.last_transaction_date // Используем дату с сервера
          }
        })
    )

    // --- API ---
    const loadCategories = async () => {
      try {
        loading.value = true
        error.value = ''

        const response = await axios.get('/api/categories/with-stats', {
          params: { month: currentMonth, year: currentYear }
        })

        let data = Array.isArray(response.data) ? response.data : response.data?.data || []

        categories.value = data.map(c => ({
          id: c.id,
          name: c.name,
          type: c.type,
          color: c.color || colorOptions[c.type === 'income' ? 1 : 3],
          budget_limit: c.budget_limit || null,
          transaction_count: c.transaction_count || 0,
          current_month_total: Math.abs(c.current_month_total || 0),
          current_month_count: c.current_month_count || 0,
          last_transaction_date: c.last_transaction_date,
          created_at: c.created_at,
          updated_at: c.updated_at
        }))
      } catch (err) {
        console.error('Ошибка загрузки категорий:', err)
        error.value = 'Ошибка при загрузке категорий: ' + (err.response?.data?.message || err.message)

        // fallback для разработки
        categories.value = []
      } finally {
        loading.value = false
      }
    }

    // --- Modal / Form ---
    const openAddForm = (type = 'expense') => {
      isEditing.value = false
      editingId.value = null
      hasTransactions.value = false
      formErrors.value = {}
      formData.value = {
        name: '',
        type,
        color: colorOptions[type === 'income' ? 1 : 3],
        budget_limit: type === 'expense' ? 1000 : null
      }
      showModal.value = true
    }

    const openEditForm = (category) => {
      isEditing.value = true
      editingId.value = category.id
      hasTransactions.value = (category.transaction_count || 0) > 0
      formErrors.value = {}
      formData.value = { ...category }
      showModal.value = true
    }

    const closeModal = () => {
      showModal.value = false
      isEditing.value = false
      editingId.value = null
      formData.value = { name: '', type: 'expense', color: '#3b82f6', budget_limit: null }
      formErrors.value = {}
    }

    const validateForm = () => {
      formErrors.value = {}

      if (!formData.value.name.trim()) {
        formErrors.value.name = 'Введите название категории'
      } else if (formData.value.name.length > 50) {
        formErrors.value.name = 'Название не должно превышать 50 символов'
      }

      if (formData.value.type === 'expense' && (!formData.value.budget_limit || formData.value.budget_limit <= 0)) {
        formErrors.value.budget_limit = 'Установите лимит бюджета для расходов'
      }

      return Object.keys(formErrors.value).length === 0
    }

    const saveCategory = async () => {
      if (!validateForm()) return

      try {
        loading.value = true
        error.value = ''

        const payload = { ...formData.value, name: formData.value.name.trim() }
        if (payload.type === 'income') payload.budget_limit = null

        if (isEditing.value) {
          await axios.put(`/api/categories/${editingId.value}`, payload)
        } else {
          await axios.post('/api/categories', payload)
        }

        await loadCategories()
        closeModal()
      } catch (err) {
        console.error('Ошибка сохранения категории:', err)
        error.value = err.response?.data?.message || 'Ошибка при сохранении категории'
      } finally {
        loading.value = false
      }
    }

    const deleteCategory = async (id) => {
      try {
        await axios.delete(`/api/categories/${id}`)
        await loadCategories()
      } catch (err) {
        console.error('Ошибка удаления категории:', err)
        error.value = err.response?.data?.message || 'Ошибка при удалении категории'
      } finally {
        setTimeout(() => { error.value = '' }, 3000)
      }
    }

    const promptDelete = (category) => {
      if ((category.transaction_count || 0) > 0) {
        error.value = 'Нельзя удалить категорию с транзакциями'
        setTimeout(() => { error.value = '' }, 3000)
        return
      }
      deleteCategory(category.id)
    }

    // --- Helpers ---
    const formatMoney = amount => {
      if (amount == null || isNaN(amount)) return '0 Br'
      return new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount) + ' Br'
    }

    const formatDate = dateString => {
      if (!dateString) return 'Нет транзакций'
      const date = new Date(dateString)
      return date.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' })
    }

    const isOverLimit = category => category.type === 'expense' && category.budget_limit && category.current_month_total > category.budget_limit

    const getProgressColor = category => {
      if (category.type !== 'expense' || !category.budget_limit) return '#3b82f6'
      const percent = (category.current_month_total / category.budget_limit) * 100
      if (percent >= 100) return '#ef4444'
      if (percent >= 80) return '#f59e0b'
      return '#10b981'
    }

    onMounted(loadCategories)

    return {
      categories,
      incomeCategories,
      expenseCategories,
      totalCategories,
      displayedCategories,
      displayedCategoriesWithMonthData,
      loading,
      error,
      showModal,
      isEditing,
      formData,
      formErrors,
      hasTransactions,
      activeTab,
      colorOptions,
      openAddForm,
      openEditForm,
      saveCategory,
      closeModal,
      promptDelete,
      formatMoney,
      formatDate,
      isOverLimit,
      getProgressColor
    }
  }
}
</script>

<style scoped>
@import '../css/categories.css';
</style>