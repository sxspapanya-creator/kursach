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
                  v-model="form.amount"
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
                    :class="{ 'date-disabled': isDateUnavailable }"
                    :min="minDate"
                    :max="maxDate"
                    @input="validateDate"
                />
                <button
                    type="button"
                    @click="setTodayDate"
                    class="date-today"
                >
                  Сегодня
                </button>
              </div>
              <div v-if="dateError" class="error-message">{{ dateError }}</div>
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
import { onMounted } from 'vue'
import { useTransactionForm } from '../composables/useTransactionForm'
import { useCurrencies } from '../composables/useCurrencies'

export default {
  name: 'AddTransaction',
  setup() {
    const {
      form,
      loading,
      error,
      amountError,
      dateError,
      currencies,
      paymentMethods,
      minDate,
      maxDate,
      currentCurrencySymbol,
      filteredCategories,
      isFormValid,
      isDateUnavailable,
      validateAmount,
      toggleCategory,
      submitTransaction,
      selectCurrency,
      setTodayDate,
      validateDate,
      loadInitialData
    } = useTransactionForm()

    const { getCurrencyFlag, formatRate } = useCurrencies()

    onMounted(() => {
      loadInitialData()
    })

    return {
      // Состояние формы
      form,
      loading,
      error,
      amountError,
      dateError,

      // Данные
      currencies,
      paymentMethods,
      minDate,
      maxDate,

      // Вычисляемые свойства
      currentCurrencySymbol,
      filteredCategories,
      isFormValid,
      isDateUnavailable,

      // Методы
      getCurrencyFlag,
      formatRate,
      validateAmount,
      toggleCategory,
      submitTransaction,
      selectCurrency,
      setTodayDate,
      validateDate
    }
  }
}
</script>

<style scoped>
@import '../css/add_transaction.css';
</style>