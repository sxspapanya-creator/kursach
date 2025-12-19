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
/* Стили остаются без изменений, добавляем только новые */
.categories-page {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1.5rem 2rem;
  width: 100%;
  box-sizing: border-box;
}

/* Hero Section */
.hero-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 2rem 1rem;
  margin-bottom: 1.5rem;
  background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
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

/* Category Tabs */
.category-tabs {
  margin-bottom: 2rem;
}

.tabs-header {
  display: flex;
  gap: 0.5rem;
  padding: 0.5rem;
  background: white;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.tab-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.875rem 1rem;
  border: none;
  background: transparent;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 600;
  color: #64748b;
  cursor: pointer;
  transition: all 0.2s;
}

.tab-btn:hover:not(.active) {
  background: #f1f5f9;
  color: #475569;
}

.tab-btn.active {
  background: #3b82f6;
  color: white;
  box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
}

.tab-icon {
  font-size: 1.125rem;
}

.tab-text {
  flex: 1;
  text-align: left;
}

.tab-count {
  background: rgba(255, 255, 255, 0.2);
  padding: 0.125rem 0.5rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
}

.tab-btn.active .tab-count {
  background: rgba(255, 255, 255, 0.3);
}

/* Modal */
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
  padding: 1rem;
}

.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-content {
  background: white;
  border-radius: 16px;
  width: 100%;
  max-width: 500px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem 1.5rem 0.5rem;
  border-bottom: 1px solid #e2e8f0;
}

.modal-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.modal-icon {
  font-size: 1.5rem;
}

.modal-close {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  border: none;
  background: transparent;
  color: #64748b;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.modal-close:hover {
  background: #f1f5f9;
  color: #475569;
}

/* Form */
.category-form {
  padding: 1.5rem;
}

.form-grid {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-label {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: #475569;
}

.label-text {
  flex: 1;
}

.label-required {
  color: #ef4444;
}

.label-optional {
  color: #94a3b8;
  font-weight: 400;
}

.label-hint {
  font-size: 0.75rem;
  color: #64748b;
  font-weight: 400;
  margin-left: 0.5rem;
}

.input-with-icon {
  position: relative;
  display: flex;
  align-items: center;
}

.input-with-icon svg {
  position: absolute;
  left: 0.875rem;
  color: #94a3b8;
  pointer-events: none;
}

.input-with-icon .currency {
  position: absolute;
  right: 0.875rem;
  color: #64748b;
  font-weight: 600;
  pointer-events: none;
}

.form-input {
  width: 100%;
  padding: 0.625rem 2.5rem 0.625rem 2.5rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.875rem;
  color: #1e293b;
  transition: all 0.2s;
  background: white;
  box-sizing: border-box;
}

.form-input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-input.has-error {
  border-color: #ef4444;
}

.form-input.has-error:focus {
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

.error-message {
  font-size: 0.75rem;
  color: #ef4444;
  margin-top: 0.25rem;
}

/* Type Buttons */
.type-buttons {
  display: flex;
  gap: 0.75rem;
}

.type-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  background: white;
  color: #64748b;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.type-btn:hover:not(.active):not(:disabled) {
  border-color: #94a3b8;
  color: #475569;
}

.type-btn.active {
  border-color: #3b82f6;
  background: rgba(59, 130, 246, 0.05);
  color: #3b82f6;
}

.type-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.type-icon {
  font-size: 1.125rem;
}

/* Color Picker */
.color-picker-section {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.color-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 0.75rem;
}

.color-option {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  cursor: pointer;
  transition: transform 0.2s;
  position: relative;
  border: 2px solid transparent;
}

.color-option:hover {
  transform: scale(1.1);
}

.color-option.selected {
  border-color: #1e293b;
  transform: scale(1.1);
}

.color-check {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
}

.color-custom {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.color-input {
  width: 36px;
  height: 36px;
  padding: 0;
  border: none;
  border-radius: 8px;
  cursor: pointer;
}

.color-preview {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  border: 2px solid #e2e8f0;
}

.color-value {
  font-size: 0.875rem;
  color: #64748b;
  font-family: monospace;
  flex: 1;
}

/* No Budget Info (для доходов) */
.no-budget-info {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 1rem;
  background: rgba(16, 185, 129, 0.05);
  border-radius: 8px;
  border: 1px solid rgba(16, 185, 129, 0.2);
}

.no-budget-info svg {
  color: #10b981;
  flex-shrink: 0;
  margin-top: 0.125rem;
}

.no-budget-content h4 {
  font-size: 0.875rem;
  font-weight: 600;
  color: #10b981;
  margin: 0 0 0.25rem 0;
}

.no-budget-content p {
  font-size: 0.75rem;
  color: #047857;
  margin: 0;
  line-height: 1.4;
}

/* Form Actions */
.form-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  margin-top: 2rem;
  padding-top: 1.5rem;
  border-top: 1px solid #e2e8f0;
}

.actions-left,
.actions-right {
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

.btn-danger:hover:not(:disabled) {
  background: #dc2626;
}

.btn-danger:disabled {
  background: #fca5a5;
  cursor: not-allowed;
}

.btn-large {
  padding: 0.875rem 1.75rem;
  font-size: 1rem;
}

.btn-loading svg {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Loading State */
.loading-state {
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

/* Empty State */
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
}

/* Categories Grid */
.categories-section {
  margin-bottom: 2rem;
}

.categories-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1.5rem;
}

.category-card {
  background: white;
  border-radius: 12px;
  padding: 1.25rem;
  border: 1px solid #e2e8f0;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.category-card:hover {
  border-color: #3b82f6;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}

.category-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.category-badge {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0.75rem;
  border-radius: 20px;
}

.category-color {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}

.category-type {
  font-size: 0.75rem;
  font-weight: 600;
}

.category-type.income {
  color: #10b981;
}

.category-type.expense {
  color: #ef4444;
}

.category-actions {
  display: flex;
  gap: 0.25rem;
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

.edit-btn:hover {
  background: #f1f5f9;
  color: #3b82f6;
}

.category-content {
  flex: 1;
}

.category-name {
  font-size: 1rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 1rem 0;
  line-height: 1.3;
}

.category-stats {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.stat-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.stat-label {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.875rem;
  color: #64748b;
}

.stat-value {
  font-size: 0.875rem;
  font-weight: 600;
}

.stat-value.income {
  color: #10b981;
}

.stat-value.expense {
  color: #ef4444;
}

/* Бюджетная секция */
.budget-section {
  margin-bottom: 1rem;
  padding: 0.75rem;
  background: #f8fafc;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.budget-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.budget-label {
  font-size: 0.875rem;
  color: #64748b;
}

.budget-limit {
  font-size: 0.875rem;
  font-weight: 600;
  color: #475569;
}

.budget-progress {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.progress-bar {
  height: 6px;
  background: #e2e8f0;
  border-radius: 3px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  transition: width 0.3s ease;
}

.progress-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.progress-text {
  font-size: 0.75rem;
  color: #64748b;
}

.progress-warning {
  font-size: 0.75rem;
  color: #ef4444;
  font-weight: 600;
}

/* Информация о доходах */
.income-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem;
  background: rgba(16, 185, 129, 0.05);
  border-radius: 6px;
  margin-bottom: 1rem;
}

.income-icon {
  font-size: 1rem;
}

.income-text {
  font-size: 0.875rem;
  color: #10b981;
  font-weight: 500;
}

/* Предупреждение о отсутствии лимита */
.no-budget-warning {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem;
  background: rgba(245, 158, 11, 0.05);
  border-radius: 6px;
  margin-bottom: 1rem;
  border: 1px solid rgba(245, 158, 11, 0.2);
}

.no-budget-warning svg {
  color: #f59e0b;
}

.warning-text {
  font-size: 0.875rem;
  color: #d97706;
  font-weight: 500;
}

/* Category Footer */
.category-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 0.75rem;
  border-top: 1px solid #e2e8f0;
}

.transaction-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.transaction-date {
  font-size: 0.75rem;
  color: #94a3b8;
  font-style: italic;
}

.delete-btn {
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
  color: #ef4444;
}

.delete-btn:hover {
  background: #fee2e2;
}

/* Error Toast */
.error-toast {
  position: fixed;
  bottom: 2rem;
  right: 2rem;
  background: #ef4444;
  color: white;
  padding: 1rem;
  border-radius: 8px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  max-width: 400px;
  z-index: 9999;
}

.slide-fade-enter-active {
  transition: all 0.3s ease;
}

.slide-fade-leave-active {
  transition: all 0.3s cubic-bezier(1, 0.5, 0.8, 1);
}

.slide-fade-enter-from,
.slide-fade-leave-to {
  transform: translateY(20px);
  opacity: 0;
}

.error-icon {
  flex-shrink: 0;
  margin-top: 0.125rem;
}

.error-content {
  flex: 1;
}

.error-title {
  font-size: 0.875rem;
  font-weight: 600;
  margin-bottom: 0.25rem;
}

.error-message {
  font-size: 0.875rem;
  opacity: 0.9;
}

.error-close {
  width: 24px;
  height: 24px;
  border-radius: 6px;
  border: none;
  background: transparent;
  color: white;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: background-color 0.2s;
}

.error-close:hover {
  background: rgba(255, 255, 255, 0.2);
}

/* Field Hint */
.field-hint {
  font-size: 0.75rem;
  color: #64748b;
  margin-top: 0.25rem;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

/* Responsive Design */
@media (max-width: 768px) {
  .categories-page {
    padding: 0 1rem 1.5rem;
  }

  .hero-section {
    padding: 1.5rem 1rem;
    border-radius: 16px;
    margin-bottom: 1.5rem;
  }

  .header-content {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }

  .header-actions {
    width: 100%;
    flex-wrap: wrap;
    gap: 0.75rem;
  }

  .header-actions .btn {
    flex: 1;
    min-width: 140px;
  }

  .category-tabs {
    margin-bottom: 1.5rem;
  }

  .tabs-header {
    flex-wrap: wrap;
    gap: 0.5rem;
  }

  .tab-btn {
    flex: 1;
    min-width: 100px;
  }

  .categories-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  .category-card {
    padding: 1.25rem;
  }

  .modal-content {
    width: 95%;
    max-width: 95%;
    margin: 1rem;
    max-height: 90vh;
    overflow-y: auto;
  }

  .form-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
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

  .header-content {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }

  .header-actions {
    width: 100%;
    flex-direction: column;
  }

  .tabs-header {
    flex-direction: column;
  }

  .categories-grid {
    grid-template-columns: 1fr;
  }

  .modal-overlay {
    padding: 0.5rem;
  }

  .modal-content {
    max-height: 95vh;
  }

  .form-grid {
    gap: 1rem;
  }

  .color-grid {
    grid-template-columns: repeat(3, 1fr);
  }

  .form-actions {
    flex-direction: column;
    gap: 0.75rem;
  }

  .actions-left,
  .actions-right {
    width: 100%;
  }

  .actions-right {
    order: -1;
  }

  .btn {
    width: 100%;
    justify-content: center;
  }

  .error-toast {
    bottom: 1rem;
    right: 1rem;
    left: 1rem;
    max-width: none;
  }
}
</style>