import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'

// События для синхронизации
const USER_UPDATED_EVENT = 'user-updated'
const USER_LOGOUT_EVENT = 'user-logout'

export function useAuth() {
    const router = useRouter()

    // Состояние
    const isAuthenticated = ref(!!localStorage.getItem('user'))
    const userCacheVersion = ref(0)

    // Вычисляемые свойства
    const userData = computed(() => {
        userCacheVersion.value // Триггер для реактивности
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

    const userName = computed(() => userData.value?.name || 'Пользователь')
    const userEmail = computed(() => userData.value?.email || '')
    const hasPremiumPlan = computed(() => userData.value?.plan_code === 'premium')

    // Методы
    const bumpUserFromStorage = () => {
        const user = localStorage.getItem('user')
        isAuthenticated.value = !!user
        userCacheVersion.value++
    }

    const syncWithApi = async () => {
        try {
            const response = await fetch('/auth/user', { credentials: 'include' })
            const data = await response.json()

            if (data.authenticated && data.user) {
                localStorage.setItem('user', JSON.stringify(data.user))
                isAuthenticated.value = true
                userCacheVersion.value++
            } else if (data.authenticated === false) {
                localStorage.removeItem('user')
                isAuthenticated.value = false
                userCacheVersion.value++
            }
        } catch (e) {
            // Сетевые ошибки — не трогаем localStorage
        }
    }

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

            localStorage.removeItem('user')
            isAuthenticated.value = false

            window.dispatchEvent(new CustomEvent(USER_LOGOUT_EVENT))

            const message = response.ok ? 'Вы успешно вышли из системы' : 'Вы вышли из системы'
            router.push('/login')
            return { success: true, message }
        } catch (error) {
            console.error('Ошибка при выходе:', error)
            localStorage.removeItem('user')
            isAuthenticated.value = false
            window.dispatchEvent(new CustomEvent(USER_LOGOUT_EVENT))
            router.push('/login')
            return { success: true, message: 'Вы вышли из системы' }
        }
    }

    // Обновление пользователя (вызывается после логина)
    const updateUser = (user) => {
        localStorage.setItem('user', JSON.stringify(user))
        isAuthenticated.value = true
        userCacheVersion.value++
        window.dispatchEvent(new CustomEvent(USER_UPDATED_EVENT))
    }

    // Обработчики событий
    const handleUserUpdated = () => {
        bumpUserFromStorage()
    }

    const handleUserLogout = () => {
        isAuthenticated.value = false
        localStorage.removeItem('user')
        userCacheVersion.value++
    }

    // Регистрация слушателей
    const registerEventListeners = () => {
        window.addEventListener(USER_UPDATED_EVENT, handleUserUpdated)
        window.addEventListener(USER_LOGOUT_EVENT, handleUserLogout)
    }

    const unregisterEventListeners = () => {
        window.removeEventListener(USER_UPDATED_EVENT, handleUserUpdated)
        window.removeEventListener(USER_LOGOUT_EVENT, handleUserLogout)
    }

    return {
        // Состояние
        isAuthenticated,
        userData,
        userInitials,
        userName,
        userEmail,
        hasPremiumPlan,

        // Методы
        bumpUserFromStorage,
        syncWithApi,
        logout,
        updateUser,
        registerEventListeners,
        unregisterEventListeners
    }
}