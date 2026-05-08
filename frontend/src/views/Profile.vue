<template>
  <div class="profile-page">
    <div class="page-header">
      <div class="header-left">
        <router-link to="/" class="back-link">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 12H5M12 19l-7-7 7-7"/>
          </svg>
          Назад
        </router-link>
        <h1 class="page-title">Личный кабинет</h1>
        <p class="page-subtitle">Управление профилем и настройками</p>
      </div>
    </div>

    <div class="profile-container">
      <div class="profile-header">
        <div class="avatar-section">
          <div class="avatar-large">
            {{ userInitials }}
          </div>
        </div>
        <div class="profile-info">
          <h2 class="profile-name">{{ form.name || user.name }}</h2>
          <p class="profile-email">{{ form.email || user.email }}</p>
          <span class="profile-status" :class="{ active: isActive }">
            {{ isActive ? 'Активен' : 'Не активен' }}
          </span>
        </div>
      </div>

      <div class="profile-tabs">
        <button type="button" class="tab-btn" :class="{ active: activeTab === 'profile' }" @click="activeTab = 'profile'">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
          </svg>
          Профиль
        </button>
        <button type="button" class="tab-btn" :class="{ active: activeTab === 'security' }" @click="activeTab = 'security'">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          Безопасность
        </button>
        <button type="button" class="tab-btn" :class="{ active: activeTab === 'subscription' }" @click="activeTab = 'subscription'">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="5" width="20" height="14" rx="2"/>
            <line x1="2" y1="10" x2="22" y2="10"/>
          </svg>
          Подписка
        </button>
      </div>

      <!-- Панель профиля -->
      <div v-if="activeTab === 'profile'" class="profile-panel">
        <div class="panel-header">
          <h3>Редактирование профиля</h3>
          <p>Измените личную информацию</p>
        </div>

        <form @submit.prevent="updateProfile" class="profile-form">
          <div class="form-group">
            <label class="form-label">Имя</label>
            <input v-model="form.name" type="text" class="form-input" placeholder="Ваше имя" required>
          </div>

          <div class="form-group">
            <label class="form-label">Email</label>
            <input v-model="form.email" type="email" class="form-input" placeholder="email@example.com" required>
            <div class="field-hint">После смены email потребуется подтверждение</div>
          </div>

          <!-- Поле для кода подтверждения (показывается только если ожидается подтверждение) -->
          <div v-if="needsVerification" class="form-group">
            <label class="form-label">Код подтверждения</label>
            <div class="verification-group">
              <input v-model="verificationCode" type="text" maxlength="6" class="form-input" placeholder="Введите код из письма" @input="verificationCode = verificationCode.replace(/[^0-9]/g, '')">
              <button type="button" @click="verifyEmail" :disabled="verificationCode.length !== 6" class="btn btn-sm btn-primary">Подтвердить</button>
            </div>
            <div class="field-hint">Код отправлен на новый email. Проверьте почту.</div>
          </div>

          <div class="form-actions">
            <button type="submit" :disabled="profileLoading" class="btn btn-primary">
              <span v-if="profileLoading" class="spinner"></span>
              {{ profileLoading ? 'Сохранение...' : 'Сохранить изменения' }}
            </button>
          </div>

          <div v-if="profileSuccess" class="success-message">✅ {{ profileSuccess }}</div>
          <div v-if="profileError" class="error-message">❌ {{ profileError }}</div>
        </form>
      </div>

      <!-- Панель безопасности -->
      <div v-if="activeTab === 'security'" class="profile-panel">
        <div class="panel-header">
          <h3>Смена пароля</h3>
          <p>Измените пароль для входа в аккаунт</p>
        </div>

        <form @submit.prevent="changePassword" class="profile-form">
          <div class="form-group">
            <label class="form-label">Текущий пароль</label>
            <input v-model="passwordForm.current_password" type="password" class="form-input" placeholder="Введите текущий пароль" required>
          </div>

          <div class="form-group">
            <label class="form-label">Новый пароль</label>
            <input v-model="passwordForm.new_password" type="password" class="form-input" placeholder="Введите новый пароль" required minlength="6">
          </div>

          <div class="form-group">
            <label class="form-label">Подтверждение пароля</label>
            <input v-model="passwordForm.new_password_confirmation" type="password" class="form-input" placeholder="Подтвердите новый пароль" required>
          </div>

          <div class="form-actions">
            <button type="submit" :disabled="passwordLoading" class="btn btn-primary">
              <span v-if="passwordLoading" class="spinner"></span>
              {{ passwordLoading ? 'Смена пароля...' : 'Сменить пароль' }}
            </button>
          </div>

          <div v-if="passwordSuccess" class="success-message">✅ {{ passwordSuccess }}</div>
          <div v-if="passwordError" class="error-message">❌ {{ passwordError }}</div>
        </form>
      </div>

      <!-- Панель подписки -->
      <div v-if="activeTab === 'subscription'" class="profile-panel">
        <div class="panel-header">
          <h3>Управление подпиской</h3>
          <p>Информация о вашем тарифе</p>
        </div>

        <div class="subscription-info">
          <div class="subscription-card">
            <div class="subscription-plan">
              <div class="plan-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="2" y="5" width="20" height="14" rx="2"/>
                  <line x1="2" y1="10" x2="22" y2="10"/>
                </svg>
              </div>
              <div class="plan-details">
                <div class="plan-name">Бесплатный тариф</div>
                <div class="plan-price">0 Br / месяц</div>
              </div>
            </div>
            <div class="plan-features">
              <div class="feature">✅ Неограниченное количество транзакций</div>
              <div class="feature">✅ Управление категориями</div>
              <div class="feature">✅ Аналитика расходов</div>
              <div class="feature">✅ 5 валют</div>
            </div>
            <button type="button" @click="manageSubscription" class="btn btn-secondary subscription-btn">
              Управление подпиской
            </button>
          </div>
        </div>
      </div>

      <!-- Кнопка выхода -->
      <div class="logout-section">
        <button @click="confirmLogout" class="btn btn-danger btn-large">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16 17 21 12 16 7"/>
            <line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
          Выйти из аккаунта
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

export default {
  name: 'Profile',
  setup() {
    const router = useRouter()

    const activeTab = ref('profile')
    const user = ref({})
    const showLogoutConfirm = ref(false)

    const profileLoading = ref(false)
    const passwordLoading = ref(false)

    const profileSuccess = ref('')
    const profileError = ref('')
    const passwordSuccess = ref('')
    const passwordError = ref('')

    // Для подтверждения email
    const needsVerification = ref(false)
    const verificationCode = ref('')
    const pendingEmail = ref('')

    const form = ref({
      name: '',
      email: ''
    })

    const passwordForm = ref({
      current_password: '',
      new_password: '',
      new_password_confirmation: ''
    })

    const userInitials = computed(() => {
      const name = form.value.name || user.value.name || 'Пользователь'
      return name.charAt(0).toUpperCase()
    })

    const isActive = computed(() => true)

    const fetchUser = async () => {
      try {
        const response = await axios.get('/auth/user')
        if (response.data && response.data.user) {
          user.value = response.data.user
          form.value.name = response.data.user.name || ''
          form.value.email = response.data.user.email || ''
        }
      } catch (err) {
        console.error('Error fetching user:', err)
      }
    }

    const updateProfile = async () => {
      profileLoading.value = true
      profileSuccess.value = ''
      profileError.value = ''
      needsVerification.value = false

      try {
        const response = await axios.put('/auth/profile', {
          name: form.value.name,
          email: form.value.email
        })

        if (response.data.status === 'success') {
          profileSuccess.value = 'Профиль успешно обновлен'
          user.value = response.data.user
          localStorage.setItem('user', JSON.stringify(response.data.user))
          window.dispatchEvent(new CustomEvent('user-updated'))

          setTimeout(() => {
            profileSuccess.value = ''
          }, 3000)
        } else if (response.data.status === 'needs_verification') {
          // Требуется подтверждение email
          needsVerification.value = true
          pendingEmail.value = response.data.email
          profileError.value = ''
          profileSuccess.value = 'Код подтверждения отправлен на новый email'
          setTimeout(() => {
            profileSuccess.value = ''
          }, 5000)
        }
      } catch (err) {
        if (err.response?.data?.errors) {
          const errors = err.response.data.errors
          profileError.value = Object.values(errors).flat().join(', ')
        } else {
          profileError.value = err.response?.data?.message || 'Ошибка при обновлении профиля'
        }
      } finally {
        profileLoading.value = false
      }
    }

    const verifyEmail = async () => {
      profileLoading.value = true
      profileError.value = ''

      try {
        const response = await axios.post('/auth/verify-email', {
          code: verificationCode.value
        })

        if (response.data.status === 'success') {
          needsVerification.value = false
          verificationCode.value = ''
          profileSuccess.value = 'Email успешно изменен'
          user.value = response.data.user
          localStorage.setItem('user', JSON.stringify(response.data.user))
          window.dispatchEvent(new CustomEvent('user-updated'))

          setTimeout(() => {
            profileSuccess.value = ''
          }, 3000)
        }
      } catch (err) {
        profileError.value = err.response?.data?.message || 'Неверный код подтверждения'
      } finally {
        profileLoading.value = false
      }
    }

    const changePassword = async () => {
      if (passwordForm.value.new_password !== passwordForm.value.new_password_confirmation) {
        passwordError.value = 'Пароли не совпадают'
        return
      }

      if (passwordForm.value.new_password.length < 6) {
        passwordError.value = 'Новый пароль должен содержать минимум 6 символов'
        return
      }

      passwordLoading.value = true
      passwordSuccess.value = ''
      passwordError.value = ''

      try {
        const response = await axios.put('/auth/password', {
          current_password: passwordForm.value.current_password,
          new_password: passwordForm.value.new_password,
          new_password_confirmation: passwordForm.value.new_password_confirmation
        })

        if (response.data.status === 'success') {
          passwordSuccess.value = 'Пароль успешно изменен'
          passwordForm.value = {
            current_password: '',
            new_password: '',
            new_password_confirmation: ''
          }

          setTimeout(() => {
            passwordSuccess.value = ''
          }, 3000)
        }
      } catch (err) {
        if (err.response?.data?.errors) {
          const errors = err.response.data.errors
          passwordError.value = Object.values(errors).flat().join(', ')
        } else {
          passwordError.value = err.response?.data?.message || 'Ошибка при смене пароля'
        }
      } finally {
        passwordLoading.value = false
      }
    }

    const manageSubscription = () => {
      alert('Функция в разработке')
    }

    const confirmLogout = () => {
        logout()
    }

    // Выход из личного кабинета
    const logout = async () => {
      try {
        await fetch('/auth/logout', {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          credentials: 'include'
        })

        localStorage.removeItem('auth_token')
        localStorage.removeItem('user')
        window.dispatchEvent(new CustomEvent('user-logout'))
        router.push('/login')
      } catch (error) {
        console.error('Ошибка при выходе:', error)
        localStorage.removeItem('auth_token')
        localStorage.removeItem('user')
        window.dispatchEvent(new CustomEvent('user-logout'))
        router.push('/login')
      }
    }

    onMounted(() => {
      fetchUser()
    })

    return {
      activeTab,
      user,
      form,
      passwordForm,
      userInitials,
      isActive,
      profileLoading,
      passwordLoading,
      profileSuccess,
      profileError,
      passwordSuccess,
      passwordError,
      needsVerification,
      verificationCode,
      showLogoutConfirm,
      updateProfile,
      verifyEmail,
      changePassword,
      manageSubscription,
      confirmLogout
    }
  }
}
</script>

<style scoped>
@import '../css/profile.css';
</style>