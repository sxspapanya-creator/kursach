import { ref } from 'vue'
import { useRouter } from 'vue-router'

export function useVerification() {
    const router = useRouter()
    const loading = ref(false)
    const verificationCode = ref('')
    const verificationError = ref('')
    const needsVerification = ref(false)
    const pendingUserId = ref(null)
    const pendingEmail = ref('')
    const pendingName = ref('')

    const register = async (formData) => {
        try {
            const response = await fetch('/auth/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    name: formData.name,
                    email: formData.email,
                    password: formData.password
                }),
                credentials: 'include'
            })

            const data = await response.json()

            if (response.ok && data.status === 'needs_verification') {
                needsVerification.value = true
                pendingUserId.value = data.user_id
                pendingEmail.value = data.email
                pendingName.value = data.name
                verificationError.value = ''
                return { success: true, needsVerification: true }
            } else if (response.status === 422 && data.errors) {
                return { success: false, errors: data.errors }
            } else {
                throw new Error(data.message || 'Ошибка регистрации')
            }
        } catch (error) {
            console.error('Registration error:', error)
            return { success: false, error: error.message || 'Ошибка регистрации' }
        }
    }

    const verifyEmail = async () => {
        if (verificationCode.value.length !== 6) return { success: false }

        loading.value = true
        verificationError.value = ''

        try {
            const response = await fetch('/auth/verify-registration', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    code: verificationCode.value,
                    user_id: pendingUserId.value
                }),
                credentials: 'include'
            })

            const data = await response.json()

            if (response.ok && data.status === 'success') {
                localStorage.setItem('user', JSON.stringify(data.user))
                window.dispatchEvent(new CustomEvent('user-updated'))
                router.push('/')
                return { success: true }
            } else {
                verificationError.value = data.message || 'Неверный код подтверждения'
                return { success: false, error: verificationError.value }
            }
        } catch (error) {
            verificationError.value = 'Ошибка при подтверждении. Попробуйте позже.'
            return { success: false, error: verificationError.value }
        } finally {
            loading.value = false
        }
    }

    const resendCode = async () => {
        loading.value = true
        verificationError.value = ''

        try {
            const response = await fetch('/auth/resend-verification', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    user_id: pendingUserId.value,
                    email: pendingEmail.value
                }),
                credentials: 'include'
            })

            const data = await response.json()

            if (response.ok) {
                verificationError.value = ''
                alert('Новый код отправлен на почту')
                return { success: true }
            } else {
                verificationError.value = data.message || 'Не удалось отправить код'
                return { success: false, error: verificationError.value }
            }
        } catch (error) {
            verificationError.value = 'Ошибка при отправке кода'
            return { success: false, error: verificationError.value }
        } finally {
            loading.value = false
        }
    }

    const backToRegistration = () => {
        needsVerification.value = false
        verificationCode.value = ''
        verificationError.value = ''
        pendingUserId.value = null
        pendingEmail.value = ''
    }

    return {
        loading,
        verificationCode,
        verificationError,
        needsVerification,
        pendingEmail,
        register,
        verifyEmail,
        resendCode,
        backToRegistration
    }
}