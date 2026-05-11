<template>
  <div class="auth-container">
    <div class="auth-card">
      <div class="auth-header">
        <h1 class="auth-title">Вход в систему</h1>
        <p class="auth-subtitle">Войдите в свой аккаунт</p>
      </div>

      <form @submit.prevent="handleLogin" class="auth-form">
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
              @input="clearError('email')"
              :disabled="loading"
          />
          <div v-if="errors.email" class="error-message">{{ errors.email }}</div>
        </div>

        <div class="form-group">
          <label for="password" class="form-label">Пароль</label>
          <input
              type="password"
              id="password"
              v-model="form.password"
              placeholder="Введите ваш пароль"
              required
              class="form-input"
              :class="{ 'error': errors.password }"
              @input="clearError('password')"
              :disabled="loading"
          />
          <div v-if="errors.password" class="error-message">{{ errors.password }}</div>
        </div>

        <div v-if="generalError" class="general-error">
          {{ generalError }}
        </div>

        <button type="submit" class="auth-button" :disabled="loading">
          <span v-if="!loading">Войти</span>
          <span v-else class="loading-text">
            <span class="loading-dot">.</span>
            <span class="loading-dot">.</span>
            <span class="loading-dot">.</span>
          </span>
        </button>

        <div class="auth-divider">
          <span>или</span>
        </div>

        <button
            type="button"
            @click="loginWithGoogle"
            class="google-button"
            :disabled="loading"
        >
          <svg class="google-icon" viewBox="0 0 24 24">
            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
          </svg>
          Войти через Google
        </button>

        <div class="auth-footer">
          <p>
            Нет аккаунта?
            <router-link to="/register" class="auth-link">Зарегистрироваться</router-link>
          </p>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'

export default {
  name: 'Login',
  setup() {
    const router = useRouter()

    const loading = ref(false)
    const generalError = ref('')

    const form = reactive({
      email: '',
      password: ''
    })

    const errors = reactive({
      email: '',
      password: ''
    })

    // Очистка ошибок
    const clearError = (field) => {
      if (errors[field]) errors[field] = ''
      if (generalError.value) generalError.value = ''
    }

    // Валидация
    const validate = () => {
      let valid = true

      errors.email = ''
      errors.password = ''

      if (!form.email.trim()) {
        errors.email = 'Email обязателен'
        valid = false
      } else if (!/\S+@\S+\.\S+/.test(form.email)) {
        errors.email = 'Введите корректный email'
        valid = false
      }

      if (!form.password) {
        errors.password = 'Пароль обязателен'
        valid = false
      } else if (form.password.length < 6) {
        errors.password = 'Пароль должен быть не менее 6 символов'
        valid = false
      }

      return valid
    }

    // Логин
    const handleLogin = async () => {
      if (!validate()) return

      loading.value = true
      generalError.value = ''

      try {
        console.log('Отправка запроса на логин...')

        const response = await fetch('/auth/login', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: JSON.stringify({
            email: form.email,
            password: form.password
          }),
          credentials: 'include',
          redirect: 'follow' // Следуем за редиректом
        })

        console.log('Статус ответа:', response.status)

        // Бэкенд возвращает редирект, но для AJAX это будет 200 или 302
        // После редиректа сессия уже установлена в куках
        // Проверяем авторизацию через /auth/user
        if (response.status === 200 || response.status === 302 || response.redirected) {
          console.log('Логин успешен, проверяю авторизацию через /auth/user')
          
          // Ждем немного, чтобы сессия точно установилась
          await new Promise(resolve => setTimeout(resolve, 100))
          
          // Проверяем авторизацию через сессию
          const userResponse = await fetch('/auth/user', {
            headers: {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'include'
          })

          if (userResponse.ok) {
            const userData = await userResponse.json()
            console.log('🔵 userData получен:', userData)  // ДОБАВЬТЕ ЭТУ СТРОЧКУ
            if (userData.authenticated && userData.user) {
              localStorage.setItem('user', JSON.stringify(userData.user))
              console.log('✅ Пользователь сохранен в localStorage')  // ДОБАВЬТЕ ЭТУ СТРОЧКУ
              console.log('🔍 Проверка:', localStorage.getItem('user'))  // ДОБАВЬТЕ ЭТУ СТРОЧКУ
              
              // Редирект на главную
              console.log('Логин успешен, выполняю редирект на /')
              router.push('/')
              return
            }
          }
          
          // Если не получилось получить данные пользователя, все равно редиректим
          // Навигационный гард проверит авторизацию
          router.push('/')
        } else {
          // Обрабатываем ошибку
          const errorData = await response.json().catch(() => ({}))
          throw new Error(errorData.message || 'Ошибка авторизации')
        }

      } catch (error) {
        console.error('Ошибка при логине:', error)
        generalError.value = error.message || 'Ошибка при входе в систему'

        // Очищаем на всякий случай
        localStorage.removeItem('user')
      } finally {
        loading.value = false
      }
    }

    // Google OAuth
    const loginWithGoogle = () => {
      window.location.href = '/auth/google'
    }

    // Проверка авторизации при загрузке компонента
    onMounted(async () => {
      // Проверяем сессию через API (сессия хранится в куках)
      try {
        const response = await fetch('/auth/user', {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          credentials: 'include'
        })

        if (response.ok) {
          const data = await response.json()
          if (data.authenticated && data.user) {
            // Пользователь уже авторизован через сессию
            localStorage.setItem('user', JSON.stringify(data.user))
            router.push('/')
          }
        }
      } catch (error) {
        console.warn('Не удалось проверить сессию при загрузке Login:', error)
      }
    })

    return {
      form,
      errors,
      generalError,
      loading,
      handleLogin,
      loginWithGoogle,
      clearError
    }
  }
}
</script>

<style scoped>
@import '../css/login.css';
</style>