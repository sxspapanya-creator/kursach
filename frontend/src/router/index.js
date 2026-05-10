import { createRouter, createWebHistory } from 'vue-router'
import Home from '../views/Home.vue'
import TransactionList from '../views/TransactionList.vue'
import AddTransaction from '../views/AddTransaction.vue'
import EditTransaction from '../views/EditTransaction.vue'
import Categories from '../views/Categories.vue'
import Analytics from '../views/Analytics.vue'
import Login from '../views/Login.vue'
import Register from '../views/Register.vue'
import MassDeleteTransactions from '../views/MassDeleteTransactions.vue'
import Profile from '../views/Profile.vue'

const routes = [
    {
        path: '/',
        name: 'Home',
        component: Home,
        meta: {
            requiresAuth: true,
            title: 'Финансы'
        }
    },
    {
        path: '/login',
        name: 'Login',
        component: Login,
        meta: {
            requiresGuest: true,
            title: 'Вход'
        }
    },
    {
        path: '/register',
        name: 'Register',
        component: Register,
        meta: {
            requiresGuest: true,
            title: 'Регистрация'
        }
    },
    {
        path: '/transactions',
        name: 'TransactionList',
        component: TransactionList,
        meta: {
            requiresAuth: true,
            title: 'Транзакции'
        }
    },
    {
        path: '/transactions/create',
        name: 'AddTransaction',
        component: AddTransaction,
        meta: {
            requiresAuth: true,
            title: 'Добавить транзакцию'
        }
    },
    {
        path: '/transactions/edit/:id',
        name: 'EditTransaction',
        component: EditTransaction,
        props: true,
        meta: {
            requiresAuth: true,
            title: 'Изменить транзакцию'
        }
    },
    {
        path: '/categories',
        name: 'Categories',
        component: Categories,
        meta: {
            requiresAuth: true,
            title: 'Категории'
        }
    },
    {
        path: '/analytics',
        name: 'Analytics',
        component: Analytics,
        meta: {
            requiresAuth: true,
            requiresPremium: true,
            title: 'Аналитика'
        }
    },
    {
        path: '/profile',
        name: 'Profile',
        component: Profile,
        meta: {
            requiresAuth: true,
            title: 'Личный кабинет'
        }
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/'
    },
    {
        path: '/transactions/mass-delete',
        name: 'MassDeleteTransactions',
        component: MassDeleteTransactions,
        meta: {
            requiresAuth: true,
            title: 'Массовое удаление транзакций'
        }
    }
]

const router = createRouter({
    history: createWebHistory(),
    routes
})

// Функция для проверки авторизации через API
const checkAuth = async () => {
    try {
        // Пробуем получить данные пользователя через API
        const response = await fetch('/auth/user', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'include' // Важно для отправки куков
        })

        if (response.ok) {
            const data = await response.json()
            console.log('Проверка авторизации через API:', data)
            return data.authenticated || false
        }

        return false
    } catch (error) {
        console.error('Ошибка проверки авторизации:', error)
        return false
    }
}

// Доступ к аналитике только при тарифе premium (plan_code в ответе /auth/user)
async function userHasPremiumPlan() {
    try {
        const response = await fetch('/auth/user', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'include'
        })
        if (response.ok) {
            const data = await response.json()
            if (data.user) {
                localStorage.setItem('user', JSON.stringify(data.user))
                return data.user.plan_code === 'premium'
            }
        }
    } catch {
        /* ignore */
    }
    try {
        const u = JSON.parse(localStorage.getItem('user') || '{}')
        return u.plan_code === 'premium'
    } catch {
        return false
    }
}

// Навигационный гард
router.beforeEach(async (to, from, next) => {
    console.log('=== НАВИГАЦИЯ ===')
    console.log('Откуда:', from.path)
    console.log('Куда:', to.path)
    console.log('Требует авторизации:', to.meta.requiresAuth)
    console.log('Только для гостей:', to.meta.requiresGuest)

    // Проверяем авторизацию через API
    const isAuthenticated = await checkAuth()
    console.log('Пользователь авторизован (через API):', isAuthenticated)

    // Если маршрут требует авторизации и пользователь не авторизован
    if (to.meta.requiresAuth && !isAuthenticated) {
        console.log('❌ Редирект на /login - требуется авторизация')
        next('/login')
        return
    }

    // Если маршрут только для гостей и пользователь авторизован
    if (to.meta.requiresGuest && isAuthenticated) {
        console.log('❌ Редирект на / - уже авторизован')
        next('/')
        return
    }

    if (to.meta.requiresPremium && isAuthenticated) {
        const premium = await userHasPremiumPlan()
        if (!premium) {
            next({ path: '/profile', query: { tab: 'subscription' } })
            return
        }
    }

    // Во всех остальных случаях разрешаем переход
    console.log('✅ Разрешаю переход на:', to.path)
    next()
})

export default router