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
            >
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
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'

export default {
  name: 'Register',
  setup() {
    const router = useRouter()
    const loading = ref(false)

    const form = reactive({
      name: '',
      email: '',
      password: '',
      password_confirmation: ''
    })

    const errors = reactive({
      name: '',
      email: '',
      password: '',
      password_confirmation: ''
    })

    // Для подтверждения email
    const needsVerification = ref(false)
    const verificationCode = ref('')
    const verificationError = ref('')
    const pendingUserId = ref(null)
    const pendingEmail = ref('')
    const pendingName = ref('')

    const validateForm = () => {
      let isValid = true

      Object.keys(errors).forEach(key => errors[key] = '')

      if (!form.name) {
        errors.name = 'Имя обязательно'
        isValid = false
      } else if (form.name.length < 2) {
        errors.name = 'Имя должно быть не менее 2 символов'
        isValid = false
      }

      if (!form.email) {
        errors.email = 'Email обязателен'
        isValid = false
      } else if (!/\S+@\S+\.\S+/.test(form.email)) {
        errors.email = 'Введите корректный email'
        isValid = false
      }

      if (!form.password) {
        errors.password = 'Пароль обязателен'
        isValid = false
      } else if (form.password.length < 6) {
        errors.password = 'Пароль должен быть не менее 6 символов'
        isValid = false
      }

      if (!form.password_confirmation) {
        errors.password_confirmation = 'Подтверждение пароля обязательно'
        isValid = false
      } else if (form.password !== form.password_confirmation) {
        errors.password_confirmation = 'Пароли не совпадают'
        isValid = false
      }

      return isValid
    }

    const register = async () => {
      try {
        const response = await fetch('/auth/register', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: JSON.stringify({
            name: form.name,
            email: form.email,
            password: form.password
          }),
          credentials: 'include'
        })

        const data = await response.json()

        if (response.ok && data.status === 'needs_verification') {
          needsVerification.value = true
          pendingUserId.value = data.user_id
          pendingEmail.value = data.email
          pendingName.value = data.name
          verificationError.value = ''
        } else if (response.status === 422 && data.errors) {
          if (data.errors.email) errors.email = data.errors.email[0] || data.errors.email
          if (data.errors.name) errors.name = data.errors.name[0] || data.errors.name
          if (data.errors.password) errors.password = data.errors.password[0] || data.errors.password
        } else {
          throw new Error(data.message || 'Ошибка регистрации')
        }
      } catch (error) {
        console.error('Registration error:', error)
        if (error.message?.includes('already exists')) {
          errors.email = 'Пользователь с таким email уже существует'
        } else if (!errors.email && !errors.name && !errors.password) {
          errors.email = error.message || 'Ошибка регистрации'
        }
      }
    }

    const verifyEmail = async () => {
      if (verificationCode.value.length !== 6) return

      loading.value = true
      verificationError.value = ''

      try {
        const response = await fetch('/auth/verify-registration', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: JSON.stringify({
            code: verificationCode.value,
            user_id: pendingUserId.value
          }),
          credentials: 'include'
        })

        const data = await response.json()

        if (response.ok && data.status === 'success') {
          localStorage.setItem('user', JSON.stringify(data.user))
          router.push('/')
        } else {
          verificationError.value = data.message || 'Неверный код подтверждения'
        }
      } catch (error) {
        verificationError.value = 'Ошибка при подтверждении. Попробуйте позже.'
      } finally {
        loading.value = false
      }
    }

    const resendCode = async () => {
      loading.value = true
      verificationError.value = ''

      try {
        const response = await fetch('/auth/resend-verification', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: JSON.stringify({
            user_id: pendingUserId.value,
            email: pendingEmail.value
          }),
          credentials: 'include'
        })

        const data = await response.json()

        if (response.ok) {
          verificationError.value = ''
          alert('Новый код отправлен на почту')
        } else {
          verificationError.value = data.message || 'Не удалось отправить код'
        }
      } catch (error) {
        verificationError.value = 'Ошибка при отправке кода'
      } finally {
        loading.value = false
      }
    }

    const backToRegistration = () => {
      needsVerification.value = false
      verificationCode.value = ''
      verificationError.value = ''
      pendingUserId.value = null
      pendingEmail.value = ''
    }

    const handleSubmit = async (e) => {
      e.preventDefault()

      if (needsVerification.value) {
        await verifyEmail()
      } else {
        if (!validateForm()) return
        loading.value = true
        await register()
        loading.value = false
      }
    }

    return {
      form,
      errors,
      loading,
      needsVerification,
      verificationCode,
      verificationError,
      pendingEmail,
      handleSubmit,
      verifyEmail,
      resendCode,
      backToRegistration
    }
  }
}
</script>

<style scoped>
@import '../css/register.css';
/* Блок подтверждения регистрации */
.verification-section {
  animation: fadeIn 0.3s ease;
}

.verification-header {
  text-align: center;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #e2e8f0;
}

.verification-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 0.5rem;
}

.verification-subtitle {
  font-size: 0.875rem;
  color: #64748b;
}

/* Группа ввода кода */
.verification-group {
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

.verification-group .form-input {
  flex: 1;
  text-align: center;
  font-size: 1.125rem;
  letter-spacing: 0.25rem;
}

/* Кнопка подтверждения */
.btn-verify {
  padding: 0.75rem 1.5rem;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.btn-verify:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-verify:disabled {
  background: #94a3b8;
  cursor: not-allowed;
  transform: none;
}

/* Подсказки */
.field-hint {
  font-size: 0.75rem;
  color: #64748b;
  margin-top: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.375rem;
}

.field-hint svg {
  flex-shrink: 0;
}

.field-hint-small {
  font-size: 0.7rem;
  color: #64748b;
  margin-top: 0.5rem;
  text-align: center;
}

/* Ссылка повторной отправки */
.resend-link {
  background: none;
  border: none;
  color: #3b82f6;
  cursor: pointer;
  font-size: 0.7rem;
  text-decoration: underline;
  padding: 0;
  margin-left: 0.25rem;
}

.resend-link:hover {
  color: #2563eb;
}

/* Кнопка возврата */
.back-link {
  background: none;
  border: none;
  color: #64748b;
  cursor: pointer;
  font-size: 0.875rem;
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  transition: color 0.2s;
}

.back-link:hover {
  color: #3b82f6;
}

/* Анимации */
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Стили для конверта (иконка письма) */
.mail-icon {
  width: 48px;
  height: 48px;
  margin: 0 auto 1rem;
  background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
}

/* Инфо об email */
.email-info {
  background: #f8fafc;
  padding: 0.75rem 1rem;
  border-radius: 8px;
  margin: 1rem 0;
  text-align: center;
  font-size: 0.875rem;
  color: #1e293b;
}

.email-info strong {
  color: #3b82f6;
  font-weight: 600;
}

/* Статусные сообщения */
.verification-status {
  margin-top: 1rem;
  padding: 0.75rem;
  border-radius: 8px;
  font-size: 0.875rem;
  text-align: center;
}

.verification-status.success {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.verification-status.error {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

/* Разделитель */
.divider {
  display: flex;
  align-items: center;
  text-align: center;
  margin: 1rem 0;
}

.divider::before,
.divider::after {
  content: '';
  flex: 1;
  border-bottom: 1px solid #e2e8f0;
}

.divider span {
  padding: 0 0.5rem;
  color: #94a3b8;
  font-size: 0.75rem;
}

/* Адаптивность */
@media (max-width: 480px) {
  .verification-group {
    flex-direction: column;
  }

  .btn-verify {
    width: 100%;
    justify-content: center;
  }

  .verification-group .form-input {
    text-align: center;
  }
}

</style>