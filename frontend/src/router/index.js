import { createRouter, createWebHistory } from 'vue-router'
import Home from '../views/Home.vue'
import TransactionList from '../views/TransactionList.vue'
import AddTransaction from '../views/AddTransaction.vue'
import EditTransaction from '../views/EditTransaction.vue'
import Categories from '../views/Categories.vue'
import Analytics from '../views/Analytics.vue'

const routes = [
    {
        path: '/',
        name: 'Home',
        component: Home
    },
    {
        path: '/transactions',
        name: 'TransactionList',
        component: TransactionList
    },
    {
        path: '/transactions/create',
        name: 'AddTransaction',
        component: AddTransaction
    },
    {
        path: '/transactions/edit/:id',
        name: 'EditTransaction',
        component: EditTransaction,
        props: true
    },
    {
        path: '/categories',
        name: 'Categories',
        component: Categories
    },
    {
        path: '/analytics',
        name: 'Analytics',
        component: Analytics
    }
]

const router = createRouter({
    history: createWebHistory(),
    routes
})

export default router