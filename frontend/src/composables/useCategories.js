import { ref, computed } from 'vue'
import axios from 'axios'

export function useCategories() {
    const categories = ref([])
    const loading = ref(false)
    const error = ref('')

    const activeTab = ref('all') // all, income, expense

    const showModal = ref(false)
    const isEditing = ref(false)
    const editingId = ref(null)
    const hasTransactions = ref(false)
    const formErrors = ref({})

    const colorOptions = [
        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
        '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#64748b'
    ]

    const formData = ref({
        name: '',
        type: 'expense',
        color: colorOptions[3]
    })

    const currentMonth = new Date().getMonth() + 1
    const currentYear = new Date().getFullYear()

    const incomeCategories = computed(() =>
        categories.value.filter(cat => cat.type === 'income')
            .sort((a, b) => a.name.localeCompare(b.name))
    )

    const expenseCategories = computed(() =>
        categories.value.filter(cat => cat.type === 'expense')
            .sort((a, b) => a.name.localeCompare(b.name))
    )

    const totalCategories = computed(() => categories.value.length)

    const displayedCategories = computed(() => {
        if (activeTab.value === 'income') return incomeCategories.value
        if (activeTab.value === 'expense') return expenseCategories.value
        return [...incomeCategories.value, ...expenseCategories.value]
    })

    const displayedCategoriesWithMonthData = computed(() =>
        displayedCategories.value.map(category => ({
            ...category,
            total_amount: category.total_amount || 0,
            transaction_count: category.transaction_count || 0,
            all_time_count: category.all_time_count ?? 0,
            currency_stats: category.currency_stats || [],
            last_transaction_date: category.last_transaction_date
        }))
    )

    const setTab = (tab) => {
        activeTab.value = tab
    }

    const getFilteredCategories = (type) => {
        return categories.value
            .filter(cat => cat.type === type)
            .sort((a, b) => a.name.localeCompare(b.name))
    }

    const getCategoryById = (id) => {
        return categories.value.find(c => c.id === id)
    }

    const formatMoney = (amount) => {
        if (amount == null || isNaN(amount)) return '0 Br'
        return new Intl.NumberFormat('ru-RU', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount) + ' Br'
    }

    const formatMoneyAmount = (amount) => {
        if (amount == null || isNaN(amount)) return '0'
        return new Intl.NumberFormat('ru-RU', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount)
    }

    const formatDate = (dateString) => {
        if (!dateString) return 'Нет транзакций'
        const date = new Date(dateString)
        return date.toLocaleDateString('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        })
    }

    const openAddForm = (type = 'expense') => {
        isEditing.value = false
        editingId.value = null
        hasTransactions.value = false
        formErrors.value = {}
        formData.value = {
            name: '',
            type,
            color: colorOptions[type === 'income' ? 1 : 3]
        }
        showModal.value = true
    }

    const openEditForm = (category) => {
        isEditing.value = true
        editingId.value = category.id
        hasTransactions.value = (category.all_time_count || 0) > 0
        formErrors.value = {}
        formData.value = { ...category }
        showModal.value = true
    }

    const closeModal = () => {
        showModal.value = false
        isEditing.value = false
        editingId.value = null
        formData.value = { name: '', type: 'expense', color: colorOptions[3] }
        formErrors.value = {}
    }

    const validateForm = () => {
        formErrors.value = {}

        if (!formData.value.name.trim()) {
            formErrors.value.name = 'Введите название категории'
        } else if (formData.value.name.length > 50) {
            formErrors.value.name = 'Название не должно превышать 50 символов'
        }

        return Object.keys(formErrors.value).length === 0
    }

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

    const fetchCategoriesWithStats = async (month = currentMonth, year = currentYear) => {
        try {
            loading.value = true
            error.value = ''

            const response = await axios.get('/api/categories/with-stats', {
                params: { month, year }
            })

            let data = response.data.data || response.data || []

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
        // Состояния
        categories,
        loading,
        error,
        activeTab,
        showModal,
        isEditing,
        editingId,
        hasTransactions,
        formErrors,
        formData,
        colorOptions,

        // Вычисляемые
        incomeCategories,
        expenseCategories,
        totalCategories,
        displayedCategories,
        displayedCategoriesWithMonthData,

        // Методы фильтрации
        setTab,
        getFilteredCategories,
        getCategoryById,

        // Методы форматирования
        formatMoney,
        formatMoneyAmount,
        formatDate,

        // Методы формы
        openAddForm,
        openEditForm,
        closeModal,
        validateForm,

        // API методы
        fetchCategories,
        fetchCategoriesWithStats,
        createCategory,
        updateCategory,
        deleteCategory
    }
}