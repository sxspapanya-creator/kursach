<template>
  <div id="app">
    <nav v-if="isAuthenticated" class="navbar">
      <div class="nav-container">
        <div class="nav-left">
          <div class="nav-brand">
            <router-link to="/" class="brand-link">
              💰 Финансы
            </router-link>
          </div>
        </div>

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
            <li v-if="hasPremiumPlan">
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

        <div class="nav-right">
          <div class="nav-user">
            <div class="user-info" @click="goToProfile">
              <div class="user-avatar">
                {{ userInitials }}
              </div>
              <div class="user-details">
                <div class="user-name">{{ userName }}</div>
                <div class="user-email">{{ userEmail }}</div>
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

    <main class="main-content">
      <router-view/>
    </main>

    <div v-if="notification.show" :class="['notification', notification.type]">
      <div class="notification-content">
        <div class="notification-message">{{ notification.message }}</div>
        <button @click="hideNotification" class="notification-close">
          &times;
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'

export default {
  name: 'App',
  setup() {
    const router = useRouter()
    const route = useRoute()

    const notification = ref({
      show: false,
      type: 'info',
      message: ''
    })

    const isAuthenticatedState = ref(false)
    const refreshTrigger = ref(0)

    const isAuthenticated = computed(() => {
      const user = localStorage.getItem('user')
      return isAuthenticatedState.value || !!user
    })

    const userData = computed(() => {
      refreshTrigger.value
      try {
        const userStr = localStorage.getItem('user')
        return userStr ? JSON.parse(userStr) : null
      } catch {
        return null
      }
    })

    const userInitials = computed(() => {
      if (!userData.value?.name) return '?'
      return userData.value.name.charAt(0).toUpperCase()
    })

    const userName = computed(() => {
      return userData.value?.name || 'Пользователь'
    })

    const userEmail = computed(() => {
      return userData.value?.email || ''
    })

    const hasPremiumPlan = computed(() => userData.value?.plan_code === 'premium')

    const showNotification = (type, message) => {
      notification.value = { show: true, type, message }
      setTimeout(hideNotification, 5000)
    }

    const hideNotification = () => {
      notification.value.show = false
    }

    const goToProfile = () => {
      router.push('/profile')
    }

    // Выход из навигации
    const logout = async () => {
      try {
        const response = await fetch('/auth/logout', {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          credentials: 'include'
        })

        localStorage.removeItem('auth_token')
        localStorage.removeItem('user')
        isAuthenticatedState.value = false
        refreshTrigger.value++

        window.dispatchEvent(new CustomEvent('user-logout'))

        if (response.ok) {
          showNotification('success', 'Вы успешно вышли из системы')
        } else {
          showNotification('success', 'Вы вышли из системы')
        }

        router.push('/login')
      } catch (error) {
        console.error('Ошибка при выходе:', error)
        localStorage.removeItem('auth_token')
        localStorage.removeItem('user')
        isAuthenticatedState.value = false
        refreshTrigger.value++
        window.dispatchEvent(new CustomEvent('user-logout'))
        showNotification('success', 'Вы вышли из системы')
        router.push('/login')
      }
    }

    const checkAuthStatus = async () => {
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
            isAuthenticatedState.value = true
            localStorage.setItem('user', JSON.stringify(data.user))
            refreshTrigger.value++
          } else {
            isAuthenticatedState.value = false
            localStorage.removeItem('user')
            refreshTrigger.value++
          }
        } else {
          isAuthenticatedState.value = false
          localStorage.removeItem('user')
          refreshTrigger.value++
        }
      } catch (error) {
        console.warn('Не удалось проверить авторизацию:', error)
        isAuthenticatedState.value = !!localStorage.getItem('user')
        refreshTrigger.value++
      }
    }

    const handleUserUpdated = () => {
      console.log('User updated event received, refreshing user data...')
      checkAuthStatus()
    }

    const handleUserLogout = () => {
      console.log('User logout event received')
      isAuthenticatedState.value = false
      localStorage.removeItem('user')
      refreshTrigger.value++
    }

    onMounted(async () => {
      console.log('App mounted, проверка авторизации...')
      await checkAuthStatus()
      window.addEventListener('user-updated', handleUserUpdated)
      window.addEventListener('user-logout', handleUserLogout)
    })

    onUnmounted(() => {
      window.removeEventListener('user-updated', handleUserUpdated)
      window.removeEventListener('user-logout', handleUserLogout)
    })

    watch(() => route.path, async (newPath) => {
      if (newPath !== '/login' && newPath !== '/register') {
        await checkAuthStatus()
      }
    })

    return {
      isAuthenticated,
      userInitials,
      userName,
      userEmail,
      hasPremiumPlan,
      notification,
      logout,
      showNotification,
      hideNotification,
      checkAuthStatus,
      goToProfile
    }
  }
}
</script>

<style scoped>
@import './css/app.css';
</style>