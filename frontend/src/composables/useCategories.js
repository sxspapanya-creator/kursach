import { ref, computed } from 'vue'
import axios from 'axios'

export function useCategories() {
    const categories = ref([])
    const loading = ref(false)
    const error = ref('')

    const currentMonth = new Date().getMonth() + 1
    const currentYear = new Date().getFullYear()

    const colorOptions = [
        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
        '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#64748b'
    ]

    // Базовые методы (для других компонентов)
    const fetchCategories = async () => {
        try {
            loading.value = true
            const response = await axios.get('/api/categories')
            categories.value = response.data.data || []
            return categories.value
        } catch (error) {
            console.error('Error fetching categories:', error)
            return []
        } finally {
            loading.value = false
        }
    }

    // Загрузка категорий со статистикой (для страницы Categories)
    const fetchCategoriesWithStats = async (month = currentMonth, year = currentYear) => {
        try {
            loading.value = true
            error.value = ''

            const response = await axios.get('/api/categories/with-stats', {
                params: { month, year }
            })

            // Обработка ответа с бэка
            let data = response.data.data || response.data || []

            // Если data не массив, а объект, пробуем взять data.data
            if (!Array.isArray(data) && data.data) {
                data = data.data
            }

            categories.value = data.map(c => ({
                id: c.id,
                name: c.name,
                type: c.type,
                color: c.color || colorOptions[c.type === 'income' ? 1 : 3],
                transaction_count: c.transaction_count || 0,
                total_amount: c.total_amount || 0,
                all_time_count: c.all_time_count ?? 0,
                all_time_total_byn: c.all_time_total_byn || 0,
                currency_stats: c.currency_stats || [],
                last_transaction_date: c.last_transaction_date,
                created_at: c.created_at,
                updated_at: c.updated_at
            }))

            return categories.value
        } catch (err) {
            console.error('Ошибка загрузки категорий:', err)
            error.value = 'Ошибка при загрузке категорий: ' + (err.response?.data?.message || err.message)
            categories.value = []
            return []
        } finally {
            loading.value = false
        }
    }

    const getFilteredCategories = (type) => {
        return categories.value
            .filter(cat => cat.type === type)
            .sort((a, b) => a.name.localeCompare(b.name))
    }

    const getCategoryById = (id) => {
        return categories.value.find(c => c.id === id)
    }

    const incomeCategories = computed(() => getFilteredCategories('income'))
    const expenseCategories = computed(() => getFilteredCategories('expense'))
    const totalCategories = computed(() => categories.value.length)

    // CRUD операции
    const createCategory = async (data) => {
        const response = await axios.post('/api/categories', data)
        return response.data
    }

    const updateCategory = async (id, data) => {
        const response = await axios.put(`/api/categories/${id}`, data)
        return response.data
    }

    const deleteCategory = async (id) => {
        await axios.delete(`/api/categories/${id}`)
    }

    return {
        // Состояние
        categories,
        loading,
        error,
        colorOptions,

        // Вычисляемые
        incomeCategories,
        expenseCategories,
        totalCategories,

        // Методы
        fetchCategories,
        fetchCategoriesWithStats,
        getFilteredCategories,
        getCategoryById,
        createCategory,
        updateCategory,
        deleteCategory
    }
}