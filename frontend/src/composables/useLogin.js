import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from './useAuth'

export function useLogin() {
    const router = useRouter()
    const { updateUser, syncWithApi } = useAuth()

    const loading = ref(false)
    const generalError = ref('')

    const form = reactive({
        email: '',
        password: ''
    })

    const errors = reactive({
        email: '',
        password: ''
    })

    const clearError = (field) => {
        if (errors[field]) errors[field] = ''
        if (generalError.value) generalError.value = ''
    }

    const validate = () => {
        let valid = true

        errors.email = ''
        errors.password = ''

        if (!form.email.trim()) {
            errors.email = 'Email обязателен'
            valid = false
        } else if (!/\S+@\S+\.\S+/.test(form.email)) {
            errors.email = 'Введите корректный email'
            valid = false
        }

        if (!form.password) {
            errors.password = 'Пароль обязателен'
            valid = false
        } else if (form.password.length < 6) {
            errors.password = 'Пароль должен быть не менее 6 символов'
            valid = false
        }

        return valid
    }

    const handleLogin = async () => {
        if (!validate()) return

        loading.value = true
        generalError.value = ''

        try {
            const response = await fetch('/auth/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    email: form.email,
                    password: form.password
                }),
                credentials: 'include',
                redirect: 'follow'
            })

            if (response.status === 200 || response.status === 302 || response.redirected) {
                // Ждем немного, чтобы сессия точно установилась
                await new Promise(resolve => setTimeout(resolve, 100))

                // Получаем данные пользователя
                const userResponse = await fetch('/auth/user', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'include'
                })

                if (userResponse.ok) {
                    const userData = await userResponse.json()
                    if (userData.authenticated && userData.user) {
                        updateUser(userData.user)
                        router.push('/')
                        return
                    }
                }

                // Если не получили данные пользователя, все равно редиректим
                router.push('/')
            } else {
                const errorData = await response.json().catch(() => ({}))
                throw new Error(errorData.message || 'Ошибка авторизации')
            }

        } catch (error) {
            console.error('Ошибка при логине:', error)
            generalError.value = error.message || 'Ошибка при входе в систему'
            localStorage.removeItem('user')
        } finally {
            loading.value = false
        }
    }

    const loginWithGoogle = () => {
        window.location.href = '/auth/google'
    }

    const checkAuthOnMount = async () => {
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
                    updateUser(data.user)
                    router.push('/')
                }
            }
        } catch (error) {
            console.warn('Не удалось проверить сессию при загрузке Login:', error)
        }
    }

    onMounted(() => {
        checkAuthOnMount()
    })

    return {
        form,
        errors,
        generalError,
        loading,
        handleLogin,
        loginWithGoogle,
        clearError
    }
}