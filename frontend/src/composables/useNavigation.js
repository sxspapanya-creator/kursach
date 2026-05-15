import { computed } from 'vue'
import { useRoute } from 'vue-router'

export function useNavigation() {
    const route = useRoute()

    const isGuestRoute = computed(() => {
        const path = route.path
        return path === '/login' || path === '/register'
    })

    const showNavbar = (isAuthenticated) => {
        return isAuthenticated && !isGuestRoute.value
    }

    const navLinks = [
        {
            path: '/',
            name: 'Обзор',
            icon: 'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z M9 22 9 12 15 12 15 22',
            requiresPremium: false
        },
        {
            path: '/transactions',
            name: 'Транзакции',
            icon: 'M1 4h22v16H1z M1 10h22',
            requiresPremium: false
        },
        {
            path: '/categories',
            name: 'Категории',
            icon: 'M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z M7 7h.01',
            requiresPremium: false
        },
        {
            path: '/analytics',
            name: 'Аналитика',
            icon: 'M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z M3.27 6.96L12 12.01 20.73 6.96 M12 22.08 12 12',
            requiresPremium: true
        }
    ]

    const getFilteredNavLinks = (hasPremium) => {
        return navLinks.filter(link => !link.requiresPremium || hasPremium)
    }

    return {
        isGuestRoute,
        showNavbar,
        navLinks,
        getFilteredNavLinks
    }
}