<template>
  <div class="auth-container">
    <div class="auth-card">
      <div class="auth-header">
        <h1 class="auth-title">Регистрация</h1>
        <p class="auth-subtitle">Создайте новый аккаунт</p>
      </div>

      <form @submit.prevent="register" class="auth-form">
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

        <button type="submit" class="auth-button" :disabled="loading">
          <span v-if="!loading">Зарегистрироваться</span>
          <span v-else>Регистрация...</span>
        </button>

        <div class="auth-footer">
          <p>
            Уже есть аккаунт?
            <router-link to="/login" class="auth-link">Войти</router-link>
          </p>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

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

    const validateForm = () => {
      let isValid = true

      // Reset errors
      Object.keys(errors).forEach(key => errors[key] = '')

      // Name validation
      if (!form.name) {
        errors.name = 'Имя обязательно'
        isValid = false
      } else if (form.name.length < 2) {
        errors.name = 'Имя должно быть не менее 2 символов'
        isValid = false
      }

      // Email validation
      if (!form.email) {
        errors.email = 'Email обязателен'
        isValid = false
      } else if (!/\S+@\S+\.\S+/.test(form.email)) {
        errors.email = 'Введите корректный email'
        isValid = false
      }

      // Password validation
      if (!form.password) {
        errors.password = 'Пароль обязателен'
        isValid = false
      } else if (form.password.length < 6) {
        errors.password = 'Пароль должен быть не менее 6 символов'
        isValid = false
      }

      // Password confirmation validation
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
      if (!validateForm()) return

      loading.value = true

      try {
        const response = await axios.post('/api/auth/register', {
          name: form.name,
          email: form.email,
          password: form.password,
          password_confirmation: form.password_confirmation
        })

        // Получаем токен
        const token = response.data.token || localStorage.getItem('auth_token')

        if (token) {
          localStorage.setItem('auth_token', token)
          axios.defaults.headers.common['Authorization'] = `Bearer ${token}`

          // Получаем информацию о пользователе
          const userResponse = await axios.get('/api/auth/user')
          localStorage.setItem('user', JSON.stringify(userResponse.data.user))

          // Перенаправляем на главную
          await router.push('/')
        }
      } catch (error) {
        console.error('Registration error:', error)

        if (error.response) {
          if (error.response.status === 400) {
            if (error.response.data.message?.includes('already exists')) {
              errors.email = 'Пользователь с таким email уже существует'
            } else {
              errors.email = error.response.data.message || 'Ошибка регистрации'
            }
          } else {
            errors.email = 'Ошибка сервера. Попробуйте позже.'
          }
        } else {
          errors.email = 'Ошибка сети. Проверьте соединение.'
        }
      } finally {
        loading.value = false
      }
    }

    return {
      form,
      errors,
      loading,
      register
    }
  }
}
</script>

<style scoped>
.auth-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
}

.auth-card {
  width: 100%;
  max-width: 400px;
  background: white;
  border-radius: 16px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
  padding: 2.5rem;
}

.auth-header {
  text-align: center;
  margin-bottom: 2rem;
}

.auth-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 0.5rem;
}

.auth-subtitle {
  color: #64748b;
  font-size: 0.95rem;
}

.auth-form {
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
  font-weight: 500;
  color: #475569;
  font-size: 0.875rem;
}

.form-input {
  padding: 0.875rem 1rem;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.95rem;
  transition: all 0.2s;
  color: #1e293b;
}

.form-input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-input.error {
  border-color: #ef4444;
}

.error-message {
  color: #ef4444;
  font-size: 0.75rem;
  margin-top: 0.25rem;
}

.password-hint {
  color: #94a3b8;
  font-size: 0.75rem;
  margin-top: 0.25rem;
}

.auth-button {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  border: none;
  padding: 1rem;
  border-radius: 8px;
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
  transition: all 0.2s;
}

.auth-button:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.auth-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.auth-footer {
  text-align: center;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #e2e8f0;
  color: #64748b;
  font-size: 0.875rem;
}

.auth-link {
  color: #3b82f6;
  text-decoration: none;
  font-weight: 500;
  margin-left: 0.25rem;
}

.auth-link:hover {
  text-decoration: underline;
}

@media (max-width: 480px) {
  .auth-card {
    padding: 2rem 1.5rem;
  }

  .auth-title {
    font-size: 1.5rem;
  }
}
</style>