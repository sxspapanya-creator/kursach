import { ref, computed } from 'vue'

const USER_UPDATED_EVENT = 'user-updated'
const USER_LOGOUT_EVENT = 'user-logout'

export function useAuth(router = null) {
    let routerInstance = router

    const isAuthenticated = ref(!!localStorage.getItem('user'))
    const userCacheVersion = ref(0)

    const userData = computed(() => {
        userCacheVersion.value
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
        } catch (e) {}
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
            localStorage.removeItem('user')
            isAuthenticated.value = false
            window.dispatchEvent(new CustomEvent(USER_LOGOUT_EVENT))
            if (routerInstance) {
                routerInstance.push('/login')
            }
        } catch (error) {
            localStorage.removeItem('user')
            isAuthenticated.value = false
            window.dispatchEvent(new CustomEvent(USER_LOGOUT_EVENT))
            if (routerInstance) {
                routerInstance.push('/login')
            }
        }
    }

    const updateUser = (user) => {
        localStorage.setItem('user', JSON.stringify(user))
        isAuthenticated.value = true
        userCacheVersion.value++
        window.dispatchEvent(new CustomEvent(USER_UPDATED_EVENT))
    }

    const handleUserUpdated = () => {
        bumpUserFromStorage()
    }

    const handleUserLogout = () => {
        isAuthenticated.value = false
        localStorage.removeItem('user')
        userCacheVersion.value++
    }

    const registerEventListeners = () => {
        window.addEventListener(USER_UPDATED_EVENT, handleUserUpdated)
        window.addEventListener(USER_LOGOUT_EVENT, handleUserLogout)
    }

    const unregisterEventListeners = () => {
        window.removeEventListener(USER_UPDATED_EVENT, handleUserUpdated)
        window.removeEventListener(USER_LOGOUT_EVENT, handleUserLogout)
    }

    const setupRouterGuards = (routerInstance) => {
        routerInstance.beforeEach(async (to, from, next) => {
            await syncWithApi()

            const requiresAuth = to.matched.some(record => record.meta.requiresAuth)
            const requiresPremium = to.matched.some(record => record.meta.requiresPremium)

            if (requiresAuth && !isAuthenticated.value) {
                next('/login')
                return
            }

            if (requiresPremium && !hasPremiumPlan.value) {
                next('/')
                return
            }

            next()
        })
    }

    return {
        isAuthenticated,
        userData,
        userInitials,
        userName,
        userEmail,
        hasPremiumPlan,
        bumpUserFromStorage,
        syncWithApi,
        logout,
        updateUser,
        registerEventListeners,
        unregisterEventListeners,
        setupRouterGuards
    }
}