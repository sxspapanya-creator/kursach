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
          <h3>Тарифы</h3>
          <p>Выберите подходящий план и срок списания</p>
        </div>

        <div v-if="subscriptionSuccess" class="success-message">✅ {{ subscriptionSuccess }}</div>
        <div v-if="subscriptionError" class="error-message">❌ {{ subscriptionError }}</div>

        <div v-if="currentPlanBlock" class="subscription-info">
          <div class="subscription-card current-plan-summary">
            <div class="subscription-plan">
              <div class="plan-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="2" y="5" width="20" height="14" rx="2"/>
                  <line x1="2" y1="10" x2="22" y2="10"/>
                </svg>
              </div>
              <div class="plan-details">
                <div class="plan-name">{{ currentPlanBlock.name }}</div>
                <div class="plan-price">{{ formatPlanPrice(currentPlanBlock) }} · {{ formatPlanPeriod(currentPlanBlock.type) }}</div>
              </div>
            </div>
            <p v-if="user.plan_id != null && user.plan_expires_at" class="plan-expiry" :class="{ expired: isPlanExpired }">
              <template v-if="isPlanExpired">Срок действия истёк: {{ user.plan_expires_at }}</template>
              <template v-else>Действует до {{ formatExpiryDate(user.plan_expires_at) }}</template>
            </p>
            <p v-else class="plan-expiry muted">Срок действия не назначен</p>
          </div>
        </div>

        <div v-if="plansLoading" class="plans-loading">
          <span class="spinner-dark"></span>
          <span>Загрузка тарифов…</span>
        </div>

        <div v-else-if="plansError" class="error-message">{{ plansError }}</div>

        <div v-else-if="planGroupSections.length" class="plans-by-code">
          <section
            v-for="section in planGroupSections"
            :key="section.code"
            class="plan-code-group"
          >
            <div class="plan-group-header">
              <h3 class="plan-group-title">{{ formatGroupTitle(section.code) }}</h3>
            </div>
            <div class="plans-grid">
              <div
                v-for="plan in section.plans"
                :key="plan.id"
                class="plan-offer-card"
                :class="{ current: isCurrentPlan(plan) }"
              >
                <div class="plan-offer-head">
                  <h4 class="plan-offer-title">{{ plan.name }}</h4>
                  <div class="plan-offer-price">{{ formatPlanPrice(plan) }}</div>
                  <div class="plan-offer-period">{{ formatPlanPeriod(plan.type) }}</div>
                </div>
                <p class="plan-offer-desc">{{ plan.description }}</p>
                <button
                  type="button"
                  class="btn btn-primary plan-select-btn"
                  :disabled="isCurrentPlan(plan) || settingPlanId !== null"
                  @click="selectPlan(plan)"
                >
                  <span v-if="settingPlanId === plan.id" class="spinner"></span>
                  {{ isCurrentPlan(plan) ? 'Текущий тариф' : settingPlanId === plan.id ? 'Сохранение…' : 'Выбрать' }}
                </button>
              </div>
            </div>
          </section>
        </div>

        <div v-else-if="plans.length" class="plans-grid">
          <div
            v-for="plan in plans"
            :key="plan.id"
            class="plan-offer-card"
            :class="{ current: isCurrentPlan(plan) }"
          >
            <div class="plan-offer-head">
              <h4 class="plan-offer-title">{{ plan.name }}</h4>
              <div class="plan-offer-price">{{ formatPlanPrice(plan) }}</div>
              <div class="plan-offer-period">{{ formatPlanPeriod(plan.type) }}</div>
            </div>
            <p class="plan-offer-desc">{{ plan.description }}</p>
            <button
              type="button"
              class="btn btn-primary plan-select-btn"
              :disabled="isCurrentPlan(plan) || settingPlanId !== null"
              @click="selectPlan(plan)"
            >
              <span v-if="settingPlanId === plan.id" class="spinner"></span>
              {{ isCurrentPlan(plan) ? 'Текущий тариф' : settingPlanId === plan.id ? 'Сохранение…' : 'Выбрать' }}
            </button>
          </div>
        </div>

        <p v-else-if="!plansLoading" class="plans-empty-hint">Нет доступных тарифов</p>
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
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'

export default {
  name: 'Profile',
  setup() {
    const router = useRouter()
    const route = useRoute()

    const activeTab = ref('profile')
    const user = ref({})
    const showLogoutConfirm = ref(false)

    const profileLoading = ref(false)
    const passwordLoading = ref(false)

    const profileSuccess = ref('')
    const profileError = ref('')
    const passwordSuccess = ref('')
    const passwordError = ref('')

    const plans = ref([])
    const plansByCode = ref(null)
    const plansLoading = ref(false)
    const plansError = ref('')
    const settingPlanId = ref(null)
    const subscriptionSuccess = ref('')
    const subscriptionError = ref('')

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

    const currentPlanBlock = computed(() => {
      if (!plans.value.length) return null
      if (user.value.plan_id != null) {
        return plans.value.find((p) => p.id === user.value.plan_id) || null
      }
      return plans.value.find((p) => p.code === 'free') || null
    })

    const isCurrentPlan = (plan) => {
      if (!plan?.id) return false
      if (user.value.plan_id != null) {
        return user.value.plan_id === plan.id
      }
      return plan.code === 'free'
    }

    const isPlanExpired = computed(() => {
      const d = user.value.plan_expires_at
      if (!d) return false
      const end = new Date(d)
      end.setHours(23, 59, 59, 999)
      return end < new Date()
    })

    const formatPlanPeriod = (type) => {
      if (type === null || type === undefined || type === '') return 'бессрочно'
      if (type === 'yearly') return 'год'
      if (type === 'monthly') return 'месяц'
      return type || ''
    }

    const formatPlanPrice = (plan) => {
      if (!plan) return ''
      const sym = plan.currency?.symbol || plan.currency?.code || ''
      const price = plan.price != null ? Number(plan.price).toLocaleString('ru-RU', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) : '0'
      return sym ? `${price} ${sym}` : price
    }

    const formatExpiryDate = (isoDate) => {
      if (!isoDate) return ''
      const d = new Date(isoDate)
      return d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' })
    }

    const GROUP_CODE_ORDER = ['free', 'premium']

    const planGroupSections = computed(() => {
      const g = plansByCode.value
      if (!g || typeof g !== 'object') return []
      const keys = Object.keys(g)
      keys.sort((a, b) => {
        const ai = GROUP_CODE_ORDER.indexOf(a)
        const bi = GROUP_CODE_ORDER.indexOf(b)
        if (ai >= 0 && bi >= 0) return ai - bi
        if (ai >= 0) return -1
        if (bi >= 0) return 1
        return a.localeCompare(b)
      })
      return keys.map((code) => ({
        code,
        plans: Array.isArray(g[code]) ? g[code] : []
      }))
    })

    const formatGroupTitle = (code) => {
      const labels = { free: 'Бесплатный', premium: 'Премиум' }
      return labels[code] ?? code
    }

    const fetchUser = async () => {
      try {
        const response = await axios.get('/auth/user')
        if (response.data && response.data.user) {
          user.value = response.data.user
          form.value.name = response.data.user.name || ''
          form.value.email = response.data.user.email || ''
          localStorage.setItem('user', JSON.stringify(response.data.user))
        }
      } catch (err) {
        console.error('Error fetching user:', err)
      }
    }

    const fetchPlans = async () => {
      plansLoading.value = true
      plansError.value = ''
      plansByCode.value = null
      try {
        const response = await axios.get('/api/plans', { params: { group_by: 'code' } })
        if (response.data?.status !== 'success') {
          plans.value = []
          plansError.value = 'Не удалось разобрать список тарифов'
          return
        }
        const raw = response.data.data
        if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
          plansByCode.value = raw
          plans.value = Object.values(raw).flat()
        } else if (Array.isArray(raw)) {
          plans.value = raw
        } else {
          plans.value = []
          plansError.value = 'Не удалось разобрать список тарифов'
        }
      } catch (err) {
        plansError.value = err.response?.data?.message || err.response?.data?.error || 'Не удалось загрузить тарифы'
        plans.value = []
      } finally {
        plansLoading.value = false
      }
    }

    const selectPlan = async (plan) => {
      if (!plan?.id || isCurrentPlan(plan)) return

      subscriptionSuccess.value = ''
      subscriptionError.value = ''
      settingPlanId.value = plan.id

      try {
        const response = await axios.post('/api/plans/set-plan', { plan_id: plan.id })
        if (response.data?.status === 'success') {
          subscriptionSuccess.value = 'Тариф обновлён'
          await fetchUser()
          window.dispatchEvent(new CustomEvent('user-updated'))
          setTimeout(() => {
            subscriptionSuccess.value = ''
          }, 4000)
        }
      } catch (err) {
        const msg = err.response?.data?.message
        const errs = err.response?.data?.errors
        subscriptionError.value = errs ? Object.values(errs).flat().join(', ') : msg || 'Не удалось сменить тариф'
      } finally {
        settingPlanId.value = null
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
      syncTabFromRoute()
    })

    watch(activeTab, (tab) => {
      if (tab === 'subscription') {
        fetchPlans()
      }
    })

    function syncTabFromRoute() {
      const t = route.query.tab
      if (t === 'subscription' || t === 'security' || t === 'profile') {
        activeTab.value = t
      }
    }

    watch(() => route.query.tab, () => {
      syncTabFromRoute()
      if (route.query.tab === 'subscription') {
        fetchPlans()
      }
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
      plans,
      planGroupSections,
      formatGroupTitle,
      plansLoading,
      plansError,
      settingPlanId,
      subscriptionSuccess,
      subscriptionError,
      currentPlanBlock,
      isPlanExpired,
      formatPlanPeriod,
      formatPlanPrice,
      formatExpiryDate,
      selectPlan,
      isCurrentPlan,
      confirmLogout
    }
  }
}
</script>

<style scoped>
@import '../css/profile.css';
</style>