<template>
  <div class="auth-container">
    <div class="auth-card">
      <div class="auth-header">
        <h1 class="auth-title">Регистрация</h1>
        <p class="auth-subtitle">Создайте новый аккаунт</p>
      </div>

      <form @submit.prevent="handleSubmit" class="auth-form">
        <!-- Поля для регистрации (скрываются после отправки кода) -->
        <div v-if="!needsVerification">
          <div class="form-group">
            <label for="name" class="form-label">Имя</label>
            <input
                type="text"
                id="name"
                v-model="form.name"
                placeholder="Введите ваше имя"
                required
                class="form-input"
                :class="{ 'error': errors.name }"
            />
            <div v-if="errors.name" class="error-message">{{ errors.name }}</div>
          </div>

          <div class="form-group">
            <label for="email" class="form-label">Email</label>
            <input
                type="email"
                id="email"
                v-model="form.email"
                placeholder="Введите ваш email"
                required
                class="form-input"
                :class="{ 'error': errors.email }"
            />
            <div v-if="errors.email" class="error-message">{{ errors.email }}</div>
          </div>

          <div class="form-group">
            <label for="password" class="form-label">Пароль</label>
            <input
                type="password"
                id="password"
                v-model="form.password"
                placeholder="Создайте пароль"
                required
                class="form-input"
                :class="{ 'error': errors.password }"
            />
            <div v-if="errors.password" class="error-message">{{ errors.password }}</div>
            <div class="password-hint">Пароль должен содержать не менее 6 символов</div>
          </div>

          <div class="form-group">
            <label for="password_confirmation" class="form-label">Подтверждение пароля</label>
            <input
                type="password"
                id="password_confirmation"
                v-model="form.password_confirmation"
                placeholder="Повторите пароль"
                required
                class="form-input"
                :class="{ 'error': errors.password_confirmation }"
            />
            <div v-if="errors.password_confirmation" class="error-message">{{ errors.password_confirmation }}</div>
          </div>
        </div>

        <!-- Поле для кода подтверждения (показывается после регистрации) -->
        <div v-if="needsVerification" class="form-group">
          <label class="form-label">Код подтверждения</label>
          <div class="verification-group">
            <input
                v-model="verificationCode"
                type="text"
                maxlength="6"
                class="form-input"
                :class="{ 'error': verificationError }"
                @input="verificationCode = verificationCode.replace(/[^0-9]/g, '')"
            />
            <button
                type="button"
                @click="verifyEmail"
                :disabled="verificationCode.length !== 6 || loading"
                class="btn-verify"
            >
              Подтвердить
            </button>
          </div>
          <div v-if="verificationError" class="error-message">{{ verificationError }}</div>
          <div class="field-hint">Код отправлен на почту {{ pendingEmail }}. Проверьте ящик.</div>
          <div class="field-hint-small">Не пришло письмо? <button type="button" @click="resendCode" class="resend-link">Отправить снова</button></div>
        </div>

        <button type="submit" class="auth-button" :disabled="loading">
          <span v-if="!loading">{{ needsVerification ? 'Подтвердить регистрацию' : 'Зарегистрироваться' }}</span>
          <span v-else>{{ needsVerification ? 'Подтверждение...' : 'Регистрация...' }}</span>
        </button>

        <div class="auth-footer" v-if="!needsVerification">
          <p>
            Уже есть аккаунт?
            <router-link to="/login" class="auth-link">Войти</router-link>
          </p>
        </div>

        <div class="auth-footer" v-else>
          <button type="button" @click="backToRegistration" class="back-link">← Вернуться к регистрации</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { useRegisterSubmit } from '../composables/useRegisterSubmit'

export default {
  name: 'Register',
  setup() {
    return useRegisterSubmit()
  }
}
</script>

<style scoped>
@import '../css/register.css';
</style>