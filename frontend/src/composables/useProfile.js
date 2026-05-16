import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { useSubscription } from './useSubscription'

export function useProfile() {
    const route = useRoute()
    const router = useRouter()

    const activeTab = ref('profile')

    const syncTabFromRoute = () => {
        const t = route.query.tab
        if (t === 'subscription' || t === 'security' || t === 'profile') {
            activeTab.value = t
        }
    }

    const setTab = (tab) => {
        activeTab.value = tab
    }

    watch(() => route.query.tab, () => {
        syncTabFromRoute()
    })

    watch(() => activeTab.value, (tab) => {
        if (tab === 'subscription') {
            subscription.fetchPlans()
        }
    })

    const user = ref({})
    const form = ref({
        name: '',
        email: '',
        salary_day: 25
    })

    const profileLoading = ref(false)
    const profileSuccess = ref('')
    const profileError = ref('')

    const needsVerification = ref(false)
    const verificationCode = ref('')
    const pendingEmail = ref('')

    const userInitials = computed(() => {
        const name = form.value.name || user.value.name || 'Пользователь'
        return name.charAt(0).toUpperCase()
    })

    const isActive = computed(() => true)

    const passwordForm = ref({
        current_password: '',
        password: '',
        password_confirmation: ''
    })

    const passwordLoading = ref(false)
    const passwordSuccess = ref('')
    const passwordError = ref('')

    const subscription = useSubscription(user)
    const loadUserFromStorage = () => {
        try {
            const userStr = localStorage.getItem('user')
            if (userStr) {
                const userData = JSON.parse(userStr)
                user.value = userData
                form.value.name = userData.name || ''
                form.value.email = userData.email || ''
                form.value.salary_day = userData.salary_day || 25
            }
        } catch (err) {
            console.error('Error loading user from storage:', err)
        }
    }

    const fetchUser = async () => {
        try {
            const response = await axios.get('/auth/user')
            if (response.data && response.data.user) {
                user.value = response.data.user
                form.value.name = response.data.user.name || ''
                form.value.email = response.data.user.email || ''
                form.value.salary_day = response.data.user.salary_day || 25
                localStorage.setItem('user', JSON.stringify(response.data.user))
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
                email: form.value.email,
                salary_day: form.value.salary_day
            })

            if (response.data.status === 'success') {
                profileSuccess.value = 'Профиль успешно обновлен'
                user.value = response.data.user
                localStorage.setItem('user', JSON.stringify(response.data.user))
                window.dispatchEvent(new CustomEvent('user-updated'))
                setTimeout(() => { profileSuccess.value = '' }, 3000)
            } else if (response.data.status === 'needs_verification') {
                needsVerification.value = true
                pendingEmail.value = response.data.email
                profileSuccess.value = 'Код подтверждения отправлен на новый email'
                setTimeout(() => { profileSuccess.value = '' }, 5000)
            }
        } catch (err) {
            if (err.response?.data?.errors) {
                profileError.value = Object.values(err.response.data.errors).flat().join(', ')
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
            const response = await axios.post('/auth/verify-email', { code: verificationCode.value })
            if (response.data.status === 'success') {
                needsVerification.value = false
                verificationCode.value = ''
                profileSuccess.value = 'Email успешно изменен'
                user.value = response.data.user
                localStorage.setItem('user', JSON.stringify(response.data.user))
                window.dispatchEvent(new CustomEvent('user-updated'))
                setTimeout(() => { profileSuccess.value = '' }, 3000)
            }
        } catch (err) {
            profileError.value = err.response?.data?.message || 'Неверный код подтверждения'
        } finally {
            profileLoading.value = false
        }
    }

    const changePassword = async () => {
        if (!passwordForm.value.current_password) {
            passwordError.value = 'Введите текущий пароль'
            return
        }

        if (!passwordForm.value.password) {
            passwordError.value = 'Введите новый пароль'
            return
        }

        if (passwordForm.value.password !== passwordForm.value.password_confirmation) {
            passwordError.value = 'Пароли не совпадают'
            return
        }

        if (passwordForm.value.password.length < 6) {
            passwordError.value = 'Новый пароль должен содержать минимум 6 символов'
            return
        }

        passwordLoading.value = true
        passwordError.value = ''
        passwordSuccess.value = ''

        try {
            const response = await axios.put('/auth/password', {
                current_password: passwordForm.value.current_password,
                new_password: passwordForm.value.password,
                new_password_confirmation: passwordForm.value.password_confirmation
            })

            if (response.data.status === 'success') {
                passwordSuccess.value = 'Пароль успешно изменен'
                passwordForm.value = {
                    current_password: '',
                    password: '',
                    password_confirmation: ''
                }
                setTimeout(() => { passwordSuccess.value = '' }, 3000)
            }
        } catch (err) {
            if (err.response?.status === 422 && err.response?.data?.errors) {
                const errors = err.response.data.errors
                if (errors.current_password) {
                    passwordError.value = errors.current_password[0] || 'Неверный текущий пароль'
                } else {
                    passwordError.value = Object.values(errors).flat().join(', ')
                }
            } else if (err.response?.data?.message === 'Current password is incorrect') {
                passwordError.value = 'Неверный текущий пароль'
            } else {
                passwordError.value = err.response?.data?.message || 'Ошибка при смене пароля'
            }
        } finally {
            passwordLoading.value = false
        }
    }

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
        } catch (error) {
            console.error('Ошибка при выходе:', error)
        } finally {
            localStorage.removeItem('auth_token')
            localStorage.removeItem('user')
            window.dispatchEvent(new CustomEvent('user-logout'))
            router.push('/login')
        }
    }

    const confirmLogout = () => {
        logout()
    }

    const handleSelectPlan = (plan) => {
        subscription.selectPlan(plan, fetchUser)
    }

    const init = async (skipFetch = true) => {
        if (skipFetch) {
            loadUserFromStorage()
        } else {
            await fetchUser()
        }
        syncTabFromRoute()
    }

    return {
        activeTab,
        setTab,
        user,
        form,
        userInitials,
        isActive,
        profileLoading,
        profileSuccess,
        profileError,
        needsVerification,
        verificationCode,
        updateProfile,
        verifyEmail,
        passwordForm,
        passwordLoading,
        passwordSuccess,
        passwordError,
        changePassword,
        plans: subscription.plans,
        planGroupSections: subscription.planGroupSections,
        plansLoading: subscription.plansLoading,
        plansError: subscription.plansError,
        settingPlanId: subscription.settingPlanId,
        subscriptionSuccess: subscription.subscriptionSuccess,
        subscriptionError: subscription.subscriptionError,
        currentPlanBlock: subscription.currentPlanBlock,
        isPlanExpired: subscription.isPlanExpired,
        formatPlanPeriod: subscription.formatPlanPeriod,
        formatPlanPrice: subscription.formatPlanPrice,
        formatExpiryDate: subscription.formatExpiryDate,
        formatGroupTitle: subscription.formatGroupTitle,
        isCurrentPlan: subscription.isCurrentPlan,
        fetchPlans: subscription.fetchPlans,
        handleSelectPlan,
        confirmLogout,
        init
    }
}