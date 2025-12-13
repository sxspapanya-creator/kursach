<template>
  <div class="categories-page">
    <div class="header">
      <h1>Категории</h1>
      <button @click="openAddForm" class="btn btn-primary">
        + Добавить категорию
      </button>
    </div>

    <!-- Модальное окно для добавления/редактирования категории -->
    <div v-if="showModal" class="category-form-modal">
      <div class="modal-content">
        <h3>{{ isEditing ? 'Редактирование категории' : 'Новая категория' }}</h3>
        <form @submit.prevent="saveCategory" class="category-form">
          <div class="form-group">
            <label>Название *</label>
            <input
                v-model="formData.name"
                type="text"
                placeholder="Название категории"
                required
            >
          </div>

          <div class="form-group">
            <label>Тип *</label>
            <select v-model="formData.type" required :disabled="isEditing && hasTransactions">
              <option value="expense">Расход</option>
              <option value="income">Доход</option>
            </select>
            <small v-if="isEditing && hasTransactions" class="field-hint">
              Нельзя изменить тип категории, так как она используется в транзакциях
            </small>
          </div>

          <div class="form-group">
            <label>Цвет</label>
            <div class="color-picker">
              <input v-model="formData.color" type="color">
              <div class="color-preview" :style="{ backgroundColor: formData.color }"></div>
            </div>
          </div>

          <div class="form-group">
            <label>Лимит бюджета (опционально)</label>
            <div class="budget-input-wrapper">
              <input
                  v-model.number="formData.budget_limit"
                  type="number"
                  min="0"
                  step="0.01"
                  placeholder="0.00"
                  class="budget-input"
              >
              <span class="currency">₽</span>
            </div>
            <small class="field-hint">Оставьте пустым, если лимит не нужен</small>
          </div>

          <div class="form-actions">
            <button
                type="submit"
                :disabled="loading || !formData.name.trim()"
                class="btn btn-primary"
            >
              {{ loading ? 'Сохранение...' : (isEditing ? 'Сохранить изменения' : 'Создать категорию') }}
            </button>
            <button type="button" @click="closeModal" class="btn btn-secondary">
              Отмена
            </button>
            <button
                v-if="isEditing && !hasTransactions"
                type="button"
                @click="confirmDelete"
                class="btn btn-danger"
            >
              Удалить
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Список категорий -->
    <div class="categories-sections">
      <!-- Категории доходов -->
      <div class="category-section">
        <h2>💰 Категории доходов ({{ incomeCategories.length }})</h2>
        <div v-if="loading" class="loading">Загрузка категорий...</div>
        <div v-else-if="incomeCategories.length === 0" class="no-categories">
          <p>Нет категорий доходов</p>
          <button @click="openAddForm('income')" class="btn btn-primary btn-small">
            + Добавить категорию доходов
          </button>
        </div>
        <div v-else class="categories-grid">
          <div
              v-for="category in incomeCategories"
              :key="category.id"
              class="category-card"
              :style="{ borderLeftColor: category.color }"
          >
            <div class="category-header">
              <div class="category-color" :style="{ backgroundColor: category.color }"></div>
              <h4>{{ category.name }}</h4>
              <span class="transaction-count" v-if="category.transaction_count > 0">
                {{ category.transaction_count }} {{ pluralizeTransaction(category.transaction_count) }}
              </span>
            </div>

            <div class="category-details">
              <div v-if="category.budget_limit" class="budget-limit">
                <span class="label">Лимит:</span>
                <span class="value">{{ formatMoney(category.budget_limit) }}</span>
              </div>
              <div v-else class="no-limit">Лимит не установлен</div>

              <div v-if="category.current_month_total !== undefined" class="month-total">
                <span class="label">В этом месяце:</span>
                <span class="value" :class="{ 'over-limit': isOverLimit(category) }">
                  {{ formatMoney(category.current_month_total) }}
                </span>
              </div>
            </div>

            <div class="category-actions">
              <button
                  @click="openEditForm(category)"
                  class="btn btn-small btn-edit"
                  title="Редактировать"
              >
                ✏️
              </button>
              <button
                  @click="promptDelete(category)"
                  :disabled="category.transaction_count > 0"
                  :title="category.transaction_count > 0 ? 'Нельзя удалить категорию с транзакциями' : 'Удалить'"
                  class="btn btn-small btn-delete"
                  :class="{ 'disabled': category.transaction_count > 0 }"
              >
                🗑️
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Категории расходов -->
      <div class="category-section">
        <h2>💸 Категории расходов ({{ expenseCategories.length }})</h2>
        <div v-if="loading" class="loading">Загрузка категорий...</div>
        <div v-else-if="expenseCategories.length === 0" class="no-categories">
          <p>Нет категорий расходов</p>
          <button @click="openAddForm('expense')" class="btn btn-primary btn-small">
            + Добавить категорию расходов
          </button>
        </div>
        <div v-else class="categories-grid">
          <div
              v-for="category in expenseCategories"
              :key="category.id"
              class="category-card"
              :style="{ borderLeftColor: category.color }"
          >
            <div class="category-header">
              <div class="category-color" :style="{ backgroundColor: category.color }"></div>
              <h4>{{ category.name }}</h4>
              <span class="transaction-count" v-if="category.transaction_count > 0">
                {{ category.transaction_count }} {{ pluralizeTransaction(category.transaction_count) }}
              </span>
            </div>

            <div class="category-details">
              <div v-if="category.budget_limit" class="budget-limit">
                <span class="label">Лимит:</span>
                <span class="value">{{ formatMoney(category.budget_limit) }}</span>
              </div>
              <div v-else class="no-limit">Лимит не установлен</div>

              <div v-if="category.current_month_total !== undefined" class="month-total">
                <span class="label">В этом месяце:</span>
                <span class="value" :class="{ 'over-limit': isOverLimit(category) }">
                  {{ formatMoney(category.current_month_total) }}
                </span>
                <div v-if="isOverLimit(category)" class="limit-warning">
                  ⚠️ Превышение на {{ formatMoney(Math.abs(category.budget_limit - category.current_month_total)) }}
                </div>
                <div v-else-if="category.budget_limit && category.current_month_total > 0" class="limit-progress">
                  <div class="progress-bar">
                    <div
                        class="progress-fill"
                        :style="{
                        width: `${Math.min((category.current_month_total / category.budget_limit) * 100, 100)}%`,
                        backgroundColor: getProgressColor(category)
                      }"
                    ></div>
                  </div>
                  <span class="progress-text">
                    {{ Math.round((category.current_month_total / category.budget_limit) * 100) }}%
                  </span>
                </div>
              </div>
            </div>

            <div class="category-actions">
              <button
                  @click="openEditForm(category)"
                  class="btn btn-small btn-edit"
                  title="Редактировать"
              >
                ✏️
              </button>
              <button
                  @click="promptDelete(category)"
                  :disabled="category.transaction_count > 0"
                  :title="category.transaction_count > 0 ? 'Нельзя удалить категорию с транзакциями' : 'Удалить'"
                  class="btn btn-small btn-delete"
                  :class="{ 'disabled': category.transaction_count > 0 }"
              >
                🗑️
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Сообщение об ошибке -->
    <div v-if="error" class="error-message">
      {{ error }}
    </div>
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

    const formData = ref({
      name: '',
      type: 'expense',
      color: '#3498db',
      budget_limit: null
    })

    const incomeCategories = computed(() => {
      if (!categories.value || !Array.isArray(categories.value)) {
        return []
      }
      return categories.value
          .filter(c => c.type === 'income')
          .sort((a, b) => a.name.localeCompare(b.name))
    })

    const expenseCategories = computed(() => {
      if (!categories.value || !Array.isArray(categories.value)) {
        return []
      }
      return categories.value
          .filter(c => c.type === 'expense')
          .sort((a, b) => a.name.localeCompare(b.name))
    })

    const fetchCategories = async () => {
      try {
        loading.value = true
        error.value = ''
        console.log('Starting to fetch categories...')

        // Запрашиваем категории вместе со статистикой
        const response = await axios.get('/api/categories', {
          params: {
            with_stats: true
          }
        })

        console.log('Categories API response:', response.data)

        if (response.data && response.data.data) {
          categories.value = response.data.data.map(category => ({
            ...category,
            transaction_count: category.transaction_count || 0,
            current_month_total: category.current_month_total || 0
          }))
          console.log('Categories set to:', categories.value)
        } else {
          categories.value = []
        }
      } catch (err) {
        console.error('Error fetching categories:', err)
        error.value = 'Ошибка при загрузке категорий: ' + (err.response?.data?.message || err.message)
        categories.value = []
      } finally {
        loading.value = false
      }
    }

    const openAddForm = (type = 'expense') => {
      isEditing.value = false
      editingId.value = null
      hasTransactions.value = false
      formData.value = {
        name: '',
        type: type,
        color: '#3498db',
        budget_limit: null
      }
      showModal.value = true
    }

    const openEditForm = (category) => {
      isEditing.value = true
      editingId.value = category.id
      hasTransactions.value = category.transaction_count > 0
      formData.value = {
        name: category.name,
        type: category.type,
        color: category.color || '#3498db',
        budget_limit: category.budget_limit
      }
      showModal.value = true
    }

    const saveCategory = async () => {
      try {
        loading.value = true
        error.value = ''

        const categoryData = { ...formData.value }

        // Преобразуем пустую строку в null для budget_limit
        if (categoryData.budget_limit === '' || categoryData.budget_limit === null) {
          categoryData.budget_limit = null
        }

        if (isEditing.value) {
          await axios.put(`/api/categories/${editingId.value}`, categoryData)
        } else {
          await axios.post('/api/categories', categoryData)
        }

        await fetchCategories()
        closeModal()
      } catch (err) {
        console.error('Error saving category:', err)
        error.value = err.response?.data?.message || `Ошибка при ${isEditing.value ? 'сохранении' : 'создании'} категории`

        // Проверяем специфические ошибки
        if (err.response?.status === 422) {
          const errors = err.response.data.errors
          if (errors && errors.name) {
            error.value = errors.name[0]
          }
        }
      } finally {
        loading.value = false
      }
    }

    const promptDelete = (category) => {
      if (category.transaction_count > 0) {
        alert('Нельзя удалить категорию, которая используется в транзакциях')
        return
      }

      if (confirm(`Удалить категорию "${category.name}"?`)) {
        deleteCategory(category.id)
      }
    }

    const confirmDelete = () => {
      if (confirm(`Удалить эту категорию?`)) {
        deleteCategory(editingId.value)
        closeModal()
      }
    }

    const deleteCategory = async (id) => {
      try {
        await axios.delete(`/api/categories/${id}`)
        await fetchCategories()
      } catch (err) {
        console.error('Error deleting category:', err)
        const message = err.response?.data?.message || 'Ошибка при удалении категории'

        if (err.response?.status === 422) {
          alert('Нельзя удалить категорию, которая используется в транзакциях')
        } else {
          error.value = message
        }
      }
    }

    const closeModal = () => {
      showModal.value = false
      isEditing.value = false
      editingId.value = null
      formData.value = {
        name: '',
        type: 'expense',
        color: '#3498db',
        budget_limit: null
      }
    }

    const formatMoney = (amount) => {
      if (amount === null || amount === undefined) return '0 ₽'
      return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }).format(amount) + ' ₽'
    }

    const pluralizeTransaction = (count) => {
      const lastDigit = count % 10
      const lastTwoDigits = count % 100

      if (lastTwoDigits >= 11 && lastTwoDigits <= 19) return 'транзакций'
      if (lastDigit === 1) return 'транзакция'
      if (lastDigit >= 2 && lastDigit <= 4) return 'транзакции'
      return 'транзакций'
    }

    const isOverLimit = (category) => {
      if (!category.budget_limit || !category.current_month_total) return false
      return category.current_month_total > category.budget_limit
    }

    const getProgressColor = (category) => {
      if (!category.budget_limit || category.budget_limit === 0) return '#3498db'

      const percentage = (category.current_month_total / category.budget_limit) * 100

      if (percentage >= 100) return '#e74c3c'
      if (percentage >= 80) return '#f39c12'
      return '#2ecc71'
    }

    onMounted(() => {
      fetchCategories()
    })

    return {
      categories,
      incomeCategories,
      expenseCategories,
      loading,
      error,
      showModal,
      isEditing,
      formData,
      hasTransactions,
      openAddForm,
      openEditForm,
      saveCategory,
      promptDelete,
      confirmDelete,
      closeModal,
      formatMoney,
      pluralizeTransaction,
      isOverLimit,
      getProgressColor
    }
  }
}
</script>

<style scoped>
.categories-page {
  max-width: 1200px;
  margin: 0 auto;
  padding: 2rem;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.category-form-modal {
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
  padding: 2rem;
  border-radius: 12px;
  width: 90%;
  max-width: 500px;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-content h3 {
  margin-bottom: 1.5rem;
  color: #2c3e50;
  text-align: center;
}

.category-form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 600;
  color: #2c3e50;
}

.field-hint {
  display: block;
  margin-top: 0.25rem;
  color: #7f8c8d;
  font-size: 0.85rem;
}

.form-group input,
.form-group select {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #bdc3c7;
  border-radius: 6px;
  font-size: 1rem;
  transition: border-color 0.2s;
}

.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: #3498db;
  box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
}

.budget-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.budget-input {
  padding-right: 2.5rem;
}

.currency {
  position: absolute;
  right: 0.75rem;
  color: #7f8c8d;
  pointer-events: none;
}

.color-picker {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.color-picker input[type="color"] {
  width: 60px;
  height: 40px;
  padding: 0;
  border: none;
  border-radius: 6px;
  cursor: pointer;
}

.color-preview {
  width: 40px;
  height: 40px;
  border-radius: 6px;
  border: 2px solid #bdc3c7;
}

.form-actions {
  display: flex;
  gap: 0.75rem;
  margin-top: 1rem;
  flex-wrap: wrap;
}

.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 1rem;
  font-weight: 600;
  transition: all 0.2s;
  flex: 1;
  text-align: center;
  min-width: 120px;
}

.btn-primary {
  background: #3498db;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #2980b9;
}

.btn-primary:disabled {
  background: #bdc3c7;
  cursor: not-allowed;
}

.btn-secondary {
  background: #95a5a6;
  color: white;
}

.btn-secondary:hover {
  background: #7f8c8d;
}

.btn-danger {
  background: #e74c3c;
  color: white;
}

.btn-danger:hover {
  background: #c0392b;
}

.categories-sections {
  display: flex;
  flex-direction: column;
  gap: 3rem;
}

.category-section h2 {
  margin-bottom: 1.5rem;
  color: #2c3e50;
  padding-bottom: 0.75rem;
  border-bottom: 2px solid #ecf0f1;
  font-size: 1.5rem;
}

.categories-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 1.5rem;
}

.category-card {
  background: white;
  padding: 1.5rem;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
  border-left: 4px solid;
  transition: transform 0.2s, box-shadow 0.2s;
  display: flex;
  flex-direction: column;
}

.category-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 15px rgba(0,0,0,0.15);
}

.category-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

.category-color {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  flex-shrink: 0;
}

.category-header h4 {
  margin: 0;
  color: #2c3e50;
  font-size: 1.1rem;
  flex: 1;
}

.transaction-count {
  background: #f8f9fa;
  color: #7f8c8d;
  padding: 0.25rem 0.5rem;
  border-radius: 12px;
  font-size: 0.85rem;
}

.category-details {
  margin-bottom: 1rem;
  flex: 1;
}

.budget-limit,
.month-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: #f8f9fa;
  border-radius: 6px;
  margin-bottom: 0.5rem;
}

.budget-limit .label,
.month-total .label {
  color: #7f8c8d;
  font-size: 0.9rem;
}

.budget-limit .value,
.month-total .value {
  font-weight: 600;
  color: #2c3e50;
}

.month-total .value.over-limit {
  color: #e74c3c;
}

.limit-warning {
  background: #ffeaa7;
  color: #d63031;
  padding: 0.5rem;
  border-radius: 4px;
  margin-top: 0.5rem;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.limit-progress {
  margin-top: 0.75rem;
}

.progress-bar {
  height: 6px;
  background: #ecf0f1;
  border-radius: 3px;
  overflow: hidden;
  margin-bottom: 0.25rem;
}

.progress-fill {
  height: 100%;
  transition: width 0.3s ease;
}

.progress-text {
  display: block;
  text-align: right;
  font-size: 0.85rem;
  color: #7f8c8d;
}

.no-limit {
  color: #95a5a6;
  font-size: 0.9rem;
  font-style: italic;
  text-align: center;
  padding: 0.75rem;
  background: #f8f9fa;
  border-radius: 6px;
}

.category-actions {
  display: flex;
  gap: 0.5rem;
  justify-content: flex-end;
  margin-top: auto;
}

.btn-small {
  padding: 0.5rem 0.75rem;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.9rem;
  transition: background-color 0.2s;
  min-width: 40px;
}

.btn-edit {
  background: #3498db;
  color: white;
}

.btn-edit:hover {
  background: #2980b9;
}

.btn-delete {
  background: #e74c3c;
  color: white;
}

.btn-delete:hover:not(.disabled) {
  background: #c0392b;
}

.btn-delete.disabled {
  background: #bdc3c7;
  cursor: not-allowed;
  opacity: 0.6;
}

.loading {
  text-align: center;
  padding: 3rem;
  color: #7f8c8d;
  font-style: italic;
  font-size: 1.1rem;
}

.no-categories {
  text-align: center;
  padding: 3rem;
  color: #7f8c8d;
  background: #f8f9fa;
  border-radius: 12px;
  border: 2px dashed #dee2e6;
}

.no-categories p {
  margin-bottom: 1.5rem;
  font-size: 1.1rem;
}

.error-message {
  background: #e74c3c;
  color: white;
  padding: 1rem;
  border-radius: 6px;
  text-align: center;
  margin-top: 1rem;
  animation: slideIn 0.3s ease;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (max-width: 768px) {
  .categories-page {
    padding: 1rem;
  }

  .header {
    flex-direction: column;
    gap: 1rem;
    text-align: center;
  }

  .categories-grid {
    grid-template-columns: 1fr;
  }

  .form-actions {
    flex-direction: column;
  }

  .btn {
    min-width: 100%;
  }
}
</style>