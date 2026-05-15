import { ref } from 'vue'

export function useCategoryForm(colorOptions) {
    const showModal = ref(false)
    const isEditing = ref(false)
    const editingId = ref(null)
    const hasTransactions = ref(false)
    const formErrors = ref({})
    const formData = ref({
        name: '',
        type: 'expense',
        color: colorOptions[3] // #ef4444
    })

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

    return {
        showModal,
        isEditing,
        editingId,
        hasTransactions,
        formErrors,
        formData,
        openAddForm,
        openEditForm,
        closeModal,
        validateForm
    }
}