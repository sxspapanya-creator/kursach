import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'

export function useRegister() {
    const router = useRouter()

    const form = reactive({
        name: '',
        email: '',
        password: '',
        password_confirmation: ''
    })

    const errors = reactive({
        name: '',
        email: '',
        password: '',
        password_confirmation: ''
    })

    const loading = ref(false)
    const verificationCode = ref('')
    const verificationError = ref('')
    const needsVerification = ref(false)
    const pendingUserId = ref(null)
    const pendingEmail = ref('')
    const pendingName = ref('')

    const validateForm = () => {
        let isValid = true

        Object.keys(errors).forEach(key => errors[key] = '')

        if (!form.name) {
            errors.name = 'Имя обязательно'
            isValid = false
        } else if (form.name.length < 2) {
            errors.name = 'Имя должно быть не менее 2 символов'
            isValid = false
        }

        if (!form.email) {
            errors.email = 'Email обязателен'
            isValid = false
        } else if (!/\S+@\S+\.\S+/.test(form.email)) {
            errors.email = 'Введите корректный email'
            isValid = false
        }

        if (!form.password) {
            errors.password = 'Пароль обязателен'
            isValid = false
        } else if (form.password.length < 6) {
            errors.password = 'Пароль должен быть не менее 6 символов'
            isValid = false
        }

        if (!form.password_confirmation) {
            errors.password_confirmation = 'Подтверждение пароля обязательно'
            isValid = false
        } else if (form.password !== form.password_confirmation) {
            errors.password_confirmation = 'Пароли не совпадают'
            isValid = false
        }

        return isValid
    }

    const register = async () => {
        loading.value = true

        try {
            const response = await fetch('/auth/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    name: form.name,
                    email: form.email,
                    password: form.password
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
                if (data.errors.email) errors.email = data.errors.email[0] || data.errors.email
                if (data.errors.name) errors.name = data.errors.name[0] || data.errors.name
                if (data.errors.password) errors.password = data.errors.password[0] || data.errors.password
                return { success: false, errors: data.errors }
            } else {
                throw new Error(data.message || 'Ошибка регистрации')
            }
        } catch (error) {
            console.error('Registration error:', error)
            return { success: false, error: error.message || 'Ошибка регистрации' }
        } finally {
            loading.value = false
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

    const resetForm = () => {
        form.name = ''
        form.email = ''
        form.password = ''
        form.password_confirmation = ''
        Object.keys(errors).forEach(key => errors[key] = '')
        needsVerification.value = false
        verificationCode.value = ''
        verificationError.value = ''
        pendingUserId.value = null
        pendingEmail.value = ''
    }

    const handleSubmit = async (e) => {
        e.preventDefault()

        if (needsVerification.value) {
            await verifyEmail()
        } else {
            if (!validateForm()) return
            await register()
        }
    }

    return {
        form,
        errors,
        loading,
        verificationCode,
        verificationError,
        needsVerification,
        pendingEmail,
        handleSubmit,
        resendCode,
        backToRegistration,
        resetForm
    }
}