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
          <div class="avatar-large">{{ userInitials }}</div>
        </div>
        <div class="profile-info">
          <h2 class="profile-name">{{ form.name || user.name }}</h2>
          <p class="profile-email">{{ form.email || user.email }}</p>
          <span class="profile-status" :class="{ active: isActive }">{{ isActive ? 'Активен' : 'Не активен' }}</span>
        </div>
      </div>

      <div class="profile-tabs">
        <button type="button" class="tab-btn" :class="{ active: activeTab === 'profile' }" @click="setTab('profile')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
          </svg>
          Профиль
        </button>
        <button type="button" class="tab-btn" :class="{ active: activeTab === 'security' }" @click="setTab('security')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          Безопасность
        </button>
        <button type="button" class="tab-btn" :class="{ active: activeTab === 'subscription' }" @click="setTab('subscription')">
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

          <div class="form-group">
            <label class="form-label">📅 День зарплаты</label>
            <div class="salary-day-input">
              <input v-model.number="form.salary_day" type="number" class="form-input salary-day-field" placeholder="25" min="1" max="28">
              <span class="salary-day-hint">(1-28)</span>
            </div>
            <div class="field-hint">Используется для расчёта ликвидности (хватит ли денег до зарплаты)</div>
          </div>

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
            <input v-model="passwordForm.password" type="password" class="form-input" placeholder="Введите новый пароль" required minlength="6">
          </div>

          <div class="form-group">
            <label class="form-label">Подтверждение пароля</label>
            <input v-model="passwordForm.password_confirmation" type="password" class="form-input" placeholder="Подтвердите новый пароль" required>
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
          <section v-for="section in planGroupSections" :key="section.code" class="plan-code-group">
            <div class="plan-group-header">
              <h3 class="plan-group-title">{{ formatGroupTitle(section.code) }}</h3>
            </div>
            <div class="plans-grid">
              <div v-for="plan in section.plans" :key="plan.id" class="plan-offer-card" :class="{ current: isCurrentPlan(plan) }">
                <div class="plan-offer-head">
                  <h4 class="plan-offer-title">{{ plan.name }}</h4>
                  <div class="plan-offer-price">{{ formatPlanPrice(plan) }}</div>
                  <div class="plan-offer-period">{{ formatPlanPeriod(plan.type) }}</div>
                </div>
                <p class="plan-offer-desc">{{ plan.description }}</p>
                <button type="button" class="btn btn-primary plan-select-btn" :disabled="isCurrentPlan(plan) || settingPlanId !== null" @click="handleSelectPlan(plan)">
                  <span v-if="settingPlanId === plan.id" class="spinner"></span>
                  {{ isCurrentPlan(plan) ? 'Текущий тариф' : settingPlanId === plan.id ? 'Сохранение…' : 'Выбрать' }}
                </button>
              </div>
            </div>
          </section>
        </div>

        <div v-else-if="plans.length" class="plans-grid">
          <div v-for="plan in plans" :key="plan.id" class="plan-offer-card" :class="{ current: isCurrentPlan(plan) }">
            <div class="plan-offer-head">
              <h4 class="plan-offer-title">{{ plan.name }}</h4>
              <div class="plan-offer-price">{{ formatPlanPrice(plan) }}</div>
              <div class="plan-offer-period">{{ formatPlanPeriod(plan.type) }}</div>
            </div>
            <p class="plan-offer-desc">{{ plan.description }}</p>
            <button type="button" class="btn btn-primary plan-select-btn" :disabled="isCurrentPlan(plan) || settingPlanId !== null" @click="handleSelectPlan(plan)">
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
import { useProfile } from '../composables/useProfile.js'

export default {
  name: 'Profile',
  setup() {
    const profile = useProfile()
    profile.init(true)
    return profile
  }
}
</script>

<style scoped>
@import '../css/profile.css';
</style>