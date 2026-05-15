import { ref, computed } from 'vue'
import axios from 'axios'

export function useProfileForm() {
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

    const fetchUser = async () => {
        try {
            console.log('📌 Запрос /auth/user')  // Отладка
            const response = await axios.get('/auth/user')
            console.log('✅ Ответ /auth/user:', response.data)
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

    return {
        user,
        form,
        userInitials,
        isActive,
        profileLoading,
        profileSuccess,
        profileError,
        needsVerification,
        verificationCode,
        pendingEmail,
        fetchUser,
        updateProfile,
        verifyEmail
    }
}