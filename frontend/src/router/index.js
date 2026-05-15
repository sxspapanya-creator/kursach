import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from '../composables/useAuth'
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
        path: '/transactions/mass-delete',
        name: 'MassDeleteTransactions',
        component: MassDeleteTransactions,
        meta: {
            requiresAuth: true,
            title: 'Массовое удаление транзакций'
        }
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/'
    }
]

const router = createRouter({
    history: createWebHistory(),
    routes
})

const { setupRouterGuards } = useAuth()
setupRouterGuards(router)

export default router