<template>
  <div class="mass-delete-page">
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
      <!-- Вкладки -->
      <div class="delete-tabs">
        <button type="button" class="tab-btn" :class="{ active: activeTab === 'date' }" @click="switchTab('date')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
            <line x1="16" y1="2" x2="16" y2="6"/>
            <line x1="8" y1="2" x2="8" y2="6"/>
            <line x1="3" y1="10" x2="21" y2="10"/>
          </svg>
          По датам
        </button>
        <button type="button" class="tab-btn" :class="{ active: activeTab === 'category' }" @click="switchTab('category')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
            <line x1="7" y1="7" x2="7.01" y2="7"/>
          </svg>
          По категориям
        </button>
        <button type="button" class="tab-btn" :class="{ active: activeTab === 'type' }" @click="switchTab('type')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <path d="M12 8v8M8 12h8"/>
          </svg>
          По типу
        </button>
      </div>

      <!-- Панель по датам -->
      <div v-if="activeTab === 'date'" class="delete-panel">
        <div class="panel-header">
          <h3>Удаление транзакций по датам</h3>
          <p>Выберите диапазон дат или конкретную дату</p>
        </div>
        <div class="delete-form">
          <div class="form-group">
            <label class="form-label">Тип выбора</label>
            <div class="radio-group">
              <label class="radio-label"><input type="radio" v-model="dateSelectionType" value="range"><span>Диапазон дат</span></label>
              <label class="radio-label"><input type="radio" v-model="dateSelectionType" value="single"><span>Конкретная дата</span></label>
            </div>
          </div>

          <div v-if="dateSelectionType === 'range'" class="date-range-group">
            <div class="form-group">
              <label class="form-label">Дата от</label>
              <input type="date" v-model="dateRange.from" class="form-input" :class="{ 'date-disabled': dateRange.from && !isDateAvailable(dateRange.from) }" :min="minDate" :max="maxDate" @change="validateDateFrom">
              <div v-if="dateFromError" class="field-hint error">{{ dateFromError }}</div>
            </div>
            <div class="form-group">
              <label class="form-label">Дата до</label>
              <input type="date" v-model="dateRange.to" class="form-input" :class="{ 'date-disabled': dateRange.to && !isDateAvailable(dateRange.to) }" :min="minDate" :max="maxDate" @change="validateDateTo">
              <div v-if="dateToError" class="field-hint error">{{ dateToError }}</div>
            </div>
          </div>

          <div v-else class="single-date-group">
            <div class="form-group">
              <label class="form-label">Дата</label>
              <input type="date" v-model="singleDate" class="form-input" :class="{ 'date-disabled': singleDate && !isDateAvailable(singleDate) }" :min="minDate" :max="maxDate" @change="validateSingleDate">
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

          <div class="form-group">
            <label class="form-label">Тип транзакции (опционально)</label>
            <select v-model="deleteFilters.type" class="form-select">
              <option value="">Все типы</option>
              <option value="income">Доходы</option>
              <option value="expense">Расходы</option>
            </select>
          </div>

          <div class="form-actions">
            <button type="button" @click="previewByDate" :disabled="!isDateSelectionValid || isDateSelectionUnavailable" class="btn btn-secondary">Предпросмотр</button>
          </div>
        </div>
      </div>

      <!-- Панель по категориям -->
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
                <span class="category-badge" :class="category.type">{{ category.type === 'income' ? 'Доход' : 'Расход' }}</span>
              </label>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Период (опционально)</label>
            <input type="month" v-model="categoryPeriod.month" class="form-input">
            <p class="field-hint muted">Если месяц выбран, ищутся только транзакции за этот месяц. Очистите поле, чтобы взять все даты.</p>
          </div>

          <div class="form-actions">
            <button type="button" @click="previewByCategory" :disabled="selectedCategories.length === 0" class="btn btn-secondary">Предпросмотр</button>
          </div>
        </div>
      </div>

      <!-- Панель по типу -->
      <div v-if="activeTab === 'type'" class="delete-panel">
        <div class="panel-header">
          <h3>Удаление транзакций по типу</h3>
          <p>Удалить все доходы или все расходы</p>
        </div>
        <div class="delete-form">
          <div class="form-group">
            <label class="form-label">Тип транзакций для удаления</label>
            <div class="radio-group">
              <label class="radio-label danger"><input type="radio" v-model="deleteType" value="income"><span>Все доходы</span></label>
              <label class="radio-label danger"><input type="radio" v-model="deleteType" value="expense"><span>Все расходы</span></label>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Период (опционально)</label>
            <input type="month" v-model="typePeriod.month" class="form-input">
          </div>

          <div class="form-actions">
            <button type="button" @click="previewByType" :disabled="!deleteType" class="btn btn-secondary">Предпросмотр</button>
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
          <div v-for="transaction in previewTransactions" :key="transaction.id" class="preview-item">
            <div class="preview-date">{{ formatDate(transaction.date) }}</div>
            <div class="preview-description">{{ transaction.description || 'Без описания' }}</div>
            <div class="preview-categories">
              <span v-for="cat in transaction.categories" :key="cat.id" class="preview-category">{{ cat.name }}</span>
            </div>
            <div class="preview-amount" :class="transaction.type">{{ transaction.type === 'income' ? '+' : '-' }} {{ formatTransactionMoney(transaction) }}</div>
          </div>
        </div>

        <div class="preview-actions">
          <button @click="showConfirmDialog = true" class="btn btn-danger btn-large">Удалить {{ previewTransactions.length }} транзакций</button>
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
            <h3 class="modal-title"><span class="modal-icon">⚠️</span> Подтверждение удаления</h3>
            <button @click="showConfirmDialog = false" class="modal-close">✕</button>
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
            <button @click="handleDelete" :disabled="deleting" class="btn btn-danger">
              <span v-if="deleting" class="spinner"></span>
              {{ deleting ? 'Удаление...' : 'Да, удалить' }}
            </button>
          </div>
        </div>
      </div>
    </transition>

    <transition name="slide-fade">
      <div v-if="error" class="error-toast">
        <div class="error-icon">❌</div>
        <div class="error-content">
          <div class="error-title">Ошибка</div>
          <div class="error-message">{{ error }}</div>
        </div>
        <button @click="error = ''" class="error-close">✕</button>
      </div>
    </transition>
  </div>
</template>

<script>
import { useMassDelete } from '../composables/useMassDelete'

export default {
  name: 'MassDeleteTransactions',
  setup() {
    return useMassDelete()
  }
}
</script>

<style scoped>
@import '../css/mass_delete_transaction.css';
</style>