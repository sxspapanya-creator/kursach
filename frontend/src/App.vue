<template>
  <div id="app">
    <!-- Навигация -->
    <nav class="navbar">
      <div class="nav-container">
        <!-- Логотип слева -->
        <div class="nav-left">
          <div class="nav-brand">
            <router-link to="/" class="brand-link">
              💰 Финансы
            </router-link>
          </div>
        </div>

        <!-- Меню по центру -->
        <div class="nav-center">
          <ul class="nav-menu">
            <li>
              <router-link to="/" class="nav-link" exact-active-class="active">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                  <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                <span>Обзор</span>
              </router-link>
            </li>
            <li>
              <router-link to="/transactions" class="nav-link" active-class="active">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                  <line x1="1" y1="10" x2="23" y2="10"/>
                </svg>
                <span>Транзакции</span>
              </router-link>
            </li>
            <li>
              <router-link to="/categories" class="nav-link" active-class="active">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                  <line x1="7" y1="7" x2="7.01" y2="7"/>
                </svg>
                <span>Категории</span>
              </router-link>
            </li>
            <li>
              <router-link to="/analytics" class="nav-link" active-class="active">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                  <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                  <line x1="12" y1="22.08" x2="12" y2="12"/>
                </svg>
                <span>Аналитика</span>
              </router-link>
            </li>
          </ul>
        </div>

        <!-- Пользователь справа -->
        <div class="nav-right" v-if="user">
          <div class="nav-user">
            <div class="user-info">
              <div class="user-avatar">
                {{ user.name.charAt(0).toUpperCase() }}
              </div>
              <div class="user-details">
                <div class="user-name">{{ user.name }}</div>
                <div class="user-email">{{ user.email }}</div>
              </div>
            </div>
            <button @click="logout" class="logout-btn" title="Выйти">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
              </svg>
            </button>
          </div>
        </div>
      </div>
    </nav>

    <!-- Основное содержимое -->
    <main class="main-content">
      <router-view/>
    </main>

    <!-- Уведомления -->
    <div v-if="notification.show" :class="['notification', notification.type]">
      <div class="notification-content">
        <div class="notification-message">{{ notification.message }}</div>
        <button @click="hideNotification" class="notification-close">
          &times;
        </button>
      </div>
    </div>

    <!-- Загрузка -->
    <div v-if="globalLoading" class="global-loading">
      <div class="loading-spinner"></div>
      <div class="loading-text">Загрузка...</div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

export default {
  name: 'App',
  setup() {
    const router = useRouter()
    const user = ref(null)
    const globalLoading = ref(false)
    const notification = ref({
      show: false,
      type: 'info',
      message: ''
    })

    // Проверяем авторизацию при загрузке
    const checkAuth = async () => {
      try {
        globalLoading.value = true
        const token = localStorage.getItem('auth_token')

        if (token) {
          axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
          axios.defaults.withCredentials = true

          const response = await axios.get('/api/auth/user')
          if (response.data.authenticated) {
            user.value = response.data.user
            localStorage.setItem('user', JSON.stringify(response.data.user))
          } else {
            clearAuth()
          }
        }
      } catch (error) {
        console.error('Auth check failed:', error)
        clearAuth()
      } finally {
        globalLoading.value = false
      }
    }

    // Выход
    const logout = async () => {
      try {
        globalLoading.value = true
        await axios.post('/api/auth/logout')
        clearAuth()
        showNotification('success', 'Вы успешно вышли')
        await router.push('/')
      } catch (error) {
        console.error('Logout failed:', error)
        showNotification('error', 'Ошибка при выходе')
      } finally {
        globalLoading.value = false
      }
    }

    // Очистка данных авторизации
    const clearAuth = () => {
      user.value = null
      localStorage.removeItem('auth_token')
      localStorage.removeItem('user')
      localStorage.removeItem('session_id')
      delete axios.defaults.headers.common['Authorization']
    }

    // Показать уведомление
    const showNotification = (type, message) => {
      notification.value = {
        show: true,
        type,
        message
      }

      // Автоматическое скрытие
      setTimeout(() => {
        hideNotification()
      }, 5000)
    }

    // Скрыть уведомление
    const hideNotification = () => {
      notification.value.show = false
    }

    // Проверка активного маршрута
    const isActiveRoute = computed(() => {
      return (route) => {
        return router.currentRoute.value.path === route
      }
    })

    onMounted(() => {
      checkAuth()

      // Загружаем пользователя из localStorage если есть
      const savedUser = localStorage.getItem('user')
      if (savedUser) {
        try {
          user.value = JSON.parse(savedUser)
        } catch (e) {
          console.error('Failed to parse user data:', e)
        }
      }
    })

    return {
      user,
      globalLoading,
      notification,
      logout,
      showNotification,
      hideNotification,
      isActiveRoute
    }
  }
}
</script>

<style>
/* Глобальные стили */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
  background-color: #f8fafc;
  color: #1e293b;
  line-height: 1.5;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

/* Навигация */
.navbar {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  position: sticky;
  top: 0;
  z-index: 1000;
}

.nav-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 64px;
  position: relative;
}

/* Левая часть (логотип) */
.nav-left {
  flex: 0 0 auto;
  position: absolute;
  left: 1.5rem;
}

.nav-brand {
  margin-right: 2rem;
}

.brand-link {
  color: white;
  text-decoration: none;
  font-size: 1.5rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  white-space: nowrap;
}

/* Центральная часть (меню) */
.nav-center {
  flex: 1;
  display: flex;
  justify-content: center;
  width: 100%;
}

.nav-menu {
  display: flex;
  list-style: none;
  gap: 1rem;
  margin: 0;
  padding: 0;
  justify-content: center;
}

.nav-menu li {
  margin: 0;
}

.nav-link {
  color: rgba(255, 255, 255, 0.9);
  text-decoration: none;
  padding: 0.75rem 1rem;
  border-radius: 8px;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.95rem;
  font-weight: 500;
  transition: all 0.2s;
  border: 2px solid transparent;
  white-space: nowrap;
}

.nav-link:hover {
  background: rgba(255, 255, 255, 0.1);
  color: white;
}

.nav-link.active {
  background: rgba(255, 255, 255, 0.15);
  color: white;
  border-color: rgba(255, 255, 255, 0.3);
}

.nav-link svg {
  flex-shrink: 0;
}

/* Правая часть (пользователь) */
.nav-right {
  flex: 0 0 auto;
  position: absolute;
  right: 1.5rem;
}

.nav-user {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.user-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 0.875rem;
  color: white;
  flex-shrink: 0;
}

.user-details {
  display: none;
}

.user-name {
  font-weight: 600;
  font-size: 0.875rem;
  white-space: nowrap;
}

.user-email {
  font-size: 0.75rem;
  opacity: 0.8;
  white-space: nowrap;
}

.logout-btn {
  background: transparent;
  border: none;
  color: white;
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background-color 0.2s;
  flex-shrink: 0;
}

.logout-btn:hover {
  background: rgba(255, 255, 255, 0.1);
}

/* Основное содержимое */
.main-content {
  max-width: 1200px;
  margin: 0 auto;
  padding: 1.5rem;
  min-height: calc(100vh - 64px);
}

/* Уведомления */
.notification {
  position: fixed;
  top: 80px;
  right: 20px;
  max-width: 400px;
  width: 100%;
  border-radius: 8px;
  padding: 1rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  z-index: 2000;
  animation: slideInRight 0.3s ease;
}

@keyframes slideInRight {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

.notification.success {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
}

.notification.error {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
}

.notification.info {
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
  color: white;
}

.notification.warning {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: white;
}

.notification-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
}

.notification-message {
  flex: 1;
  font-weight: 500;
}

.notification-close {
  background: transparent;
  border: none;
  color: white;
  font-size: 1.5rem;
  line-height: 1;
  cursor: pointer;
  padding: 0;
  opacity: 0.8;
  transition: opacity 0.2s;
}

.notification-close:hover {
  opacity: 1;
}

/* Глобальная загрузка */
.global-loading {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.9);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  z-index: 3000;
  backdrop-filter: blur(4px);
}

.loading-spinner {
  width: 50px;
  height: 50px;
  border: 3px solid #e2e8f0;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

.loading-text {
  margin-top: 1rem;
  color: #64748b;
  font-weight: 500;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Адаптивность */
@media (min-width: 768px) {
  .user-details {
    display: block;
  }

  .nav-link span {
    display: inline;
  }
}

@media (max-width: 767px) {
  .nav-container {
    padding: 0 1rem;
  }

  .nav-left {
    left: 1rem;
  }

  .nav-right {
    right: 1rem;
  }

  .nav-menu {
    gap: 0.5rem;
  }

  .nav-link span {
    display: none;
  }

  .nav-link {
    padding: 0.5rem;
  }

  .brand-link span {
    display: none;
  }

  .user-details {
    display: none;
  }

  .user-avatar {
    width: 32px;
    height: 32px;
    font-size: 0.75rem;
  }

  .logout-btn svg {
    width: 16px;
    height: 16px;
  }

  .main-content {
    padding: 1rem;
  }

  .notification {
    left: 1rem;
    right: 1rem;
    top: 1rem;
    max-width: none;
  }
}

@media (max-width: 480px) {
  .nav-link {
    padding: 0.4rem;
  }

  .nav-link svg {
    width: 16px;
    height: 16px;
  }

  .brand-link {
    font-size: 1.25rem;
  }

  .user-avatar {
    width: 28px;
    height: 28px;
    font-size: 0.7rem;
  }
}
</style>