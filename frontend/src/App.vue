<template>
  <div id="app">
    <nav v-if="showNavbar" class="navbar">
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
            <li v-for="link in filteredNavLinks" :key="link.path">
              <router-link
                  :to="link.path"
                  class="nav-link"
                  exact-active-class="active"
                  :active-class="'active'"
              >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path :d="link.icon"/>
                </svg>
                <span>{{ link.name }}</span>
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
            <button @click="handleLogout" class="logout-btn" title="Выйти">
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
      <router-view />
    </main>

    <div v-if="notification.show" :class="['notification', notification.type]">
      <div class="notification-content">
        <div class="notification-message">{{ notification.message }}</div>
        <button @click="hideNotification" class="notification-close">&times;</button>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuth } from './composables/useAuth'
import { useNotification } from './composables/useNotifications'
import { useNavigation } from './composables/useNavigation'

export default {
  name: 'App',
  setup() {
    const router = useRouter()
    const route = useRoute()

    const {
      isAuthenticated,
      userInitials,
      userName,
      userEmail,
      hasPremiumPlan,
      syncWithApi,
      logout,
      registerEventListeners,
      unregisterEventListeners
    } = useAuth()

    const { notification, hideNotification, success, error: notifyError } = useNotification()
    const { showNavbar: getShowNavbar, getFilteredNavLinks } = useNavigation()

    // Вычисляемые свойства
    const showNavbar = computed(() => getShowNavbar(isAuthenticated.value))
    const filteredNavLinks = computed(() => getFilteredNavLinks(hasPremiumPlan.value))

    // Методы
    const goToProfile = () => {
      router.push('/profile')
    }

    const handleLogout = async () => {
      const result = await logout()
      if (result.message) {
        success(result.message)
      }
    }

    // Обновление при смене маршрута
    watch(() => route.fullPath, () => {
      if (route.path !== '/login' && route.path !== '/register') {
        // перечитываем пользователя при смене маршрута (если нужно)
      }
    })

    onMounted(async () => {
      registerEventListeners()
      await syncWithApi()
    })

    onUnmounted(() => {
      unregisterEventListeners()
    })

    return {
      showNavbar,
      filteredNavLinks,
      userInitials,
      userName,
      userEmail,
      notification,
      goToProfile,
      handleLogout,
      hideNotification
    }
  }
}
</script>

<style scoped>
@import './css/app.css';
</style>