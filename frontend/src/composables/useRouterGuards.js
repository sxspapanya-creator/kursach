import { useAuth } from './useAuth'

export function setupRouterGuards(router) {
    router.beforeEach(async (to, from, next) => {
        const { isAuthenticated, syncWithApi } = useAuth()

        // Синхронизируем состояние перед проверкой
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