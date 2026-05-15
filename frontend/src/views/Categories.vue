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
            @click="setTab('income')"
            :class="['tab-btn', { 'active': activeTab === 'income' }]"
        >
          <span class="tab-icon">💰</span>
          <span class="tab-text">Доходы</span>
          <span class="tab-count">{{ incomeCategories.length }}</span>
        </button>
        <button
            @click="setTab('expense')"
            :class="['tab-btn', { 'active': activeTab === 'expense' }]"
        >
          <span class="tab-icon">💸</span>
          <span class="tab-text">Расходы</span>
          <span class="tab-count">{{ expenseCategories.length }}</span>
        </button>
        <button
            @click="setTab('all')"
            :class="['tab-btn', { 'active': activeTab === 'all' }]"
        >
          <span class="tab-icon">📊</span>
          <span class="tab-text">Все</span>
          <span class="tab-count">{{ totalCategories }}</span>
        </button>
      </div>
    </div>

    <!-- Модальное окно -->
    <transition name="modal">
      <div v-if="showModal" class="modal-overlay" @click="closeModal">
        <div class="modal-content" @click.stop>
          <div class="modal-header">
            <h3 class="modal-title">
              <span class="modal-icon">{{ isEditing ? '✏️' : '➕' }}</span>
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
            </div>

            <div class="form-actions">
              <div class="actions-left">
                <button
                    v-if="isEditing"
                    type="button"
                    @click="promptDelete"
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
                <button type="button" @click="closeModal" class="btn btn-secondary">Отмена</button>
                <button type="submit" :disabled="loading || !formData.name.trim()" class="btn btn-primary">
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
    <div v-else-if="displayedCategoriesWithMonthData.length === 0" class="empty-state">
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
              <div class="category-color" :style="{ backgroundColor: category.color }"></div>
              <span class="category-type" :class="category.type">{{ category.type === 'income' ? 'Доход' : 'Расход' }}</span>
            </div>
            <div class="category-actions">
              <button @click.stop="openEditForm(category)" class="action-btn edit-btn" title="Редактировать">
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
                <div class="stat-value" :class="category.type">{{ formatMoney(category.total_amount || 0) }}</div>
              </div>

              <div v-if="category.currency_stats && category.currency_stats.length > 0" class="stat-item currency-breakdown">
                <div class="stat-label">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 8v8M8 12h8"/>
                  </svg>
                  По валютам
                </div>
                <div class="currency-stats">
                  <div v-for="stat in category.currency_stats" :key="stat.currency_code" class="currency-stat-item">
                    <span class="currency-symbol">{{ stat.currency_symbol }}</span>
                    <span class="currency-amount">{{ formatMoneyAmount(stat.total_amount) }}</span>
                    <span class="currency-count">({{ stat.transaction_count }} шт.)</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="category-footer">
            <div class="category-footer-left">
              <div v-if="category.last_transaction_date" class="transaction-info">
                <span class="transaction-date">Последняя транзакция: {{ formatDate(category.last_transaction_date) }}</span>
              </div>
            </div>
            <div class="category-footer-right">
              <button
                  v-if="(category.all_time_count || 0) === 0"
                  @click.stop="promptDelete"
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
import { ref, onMounted } from 'vue'  // ← ДОБАВИТЬ ref в импорт!
import { useCategories } from '../composables/useCategories'
import { useCategoryForm } from '../composables/useCategoryForm'
import { useCategoryFilters } from '../composables/useCategoryFilters'
import { useCategoryStats } from '../composables/useCategoryStats'

export default {
  name: 'CategoriesPage',
  setup() {
    const {
      categories,
      loading,
      error,
      colorOptions,
      incomeCategories,
      expenseCategories,
      totalCategories,
      fetchCategoriesWithStats,
      createCategory,
      updateCategory,
      deleteCategory
    } = useCategories()

    const {
      showModal,
      isEditing,
      editingId,
      hasTransactions,
      formErrors,
      formData,
      openAddForm,
      openEditForm,
      closeModal,
      validateForm
    } = useCategoryForm(colorOptions)

    const {
      activeTab,
      displayedCategories,
      setTab
    } = useCategoryFilters(incomeCategories, expenseCategories)

    const {
      displayedCategoriesWithMonthData,
      formatMoney,
      formatMoneyAmount,
      formatDate
    } = useCategoryStats(displayedCategories)

    // Эта переменная не используется, можно удалить
    // const currentEditingCategory = ref(null)

    const saveCategory = async () => {
      if (!validateForm()) return

      try {
        loading.value = true
        error.value = ''

        const payload = { ...formData.value, name: formData.value.name.trim() }

        if (isEditing.value) {
          await updateCategory(editingId.value, payload)
        } else {
          await createCategory(payload)
        }

        await fetchCategoriesWithStats()
        closeModal()
      } catch (err) {
        console.error('Ошибка сохранения категории:', err)
        error.value = err.response?.data?.message || 'Ошибка при сохранении категории'
      } finally {
        loading.value = false
      }
    }

    const promptDelete = async () => {
      if (!isEditing.value) return

      if (hasTransactions.value) {
        error.value = 'Нельзя удалить категорию с транзакциями'
        setTimeout(() => { error.value = '' }, 3000)
        return
      }

      try {
        await deleteCategory(editingId.value)
        await fetchCategoriesWithStats()
        closeModal()
      } catch (err) {
        error.value = err.response?.data?.message || 'Ошибка при удалении категории'
        setTimeout(() => { error.value = '' }, 3000)
      }
    }

    onMounted(() => {
      fetchCategoriesWithStats()
    })

    return {
      // Данные
      loading,
      error,
      colorOptions,
      incomeCategories,
      expenseCategories,
      totalCategories,

      // Фильтры
      activeTab,
      displayedCategoriesWithMonthData,
      setTab,

      // Модальное окно
      showModal,
      isEditing,
      formData,
      formErrors,
      hasTransactions,
      openAddForm,
      openEditForm,
      closeModal,
      saveCategory,
      promptDelete,

      // Форматирование
      formatMoney,
      formatMoneyAmount,
      formatDate
    }
  }
}
</script>

<style scoped>
@import '../css/categories.css';
</style>