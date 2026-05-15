import { ref } from 'vue'
import axios from 'axios'

export function useMassDeleteCategories() {
    const allCategories = ref([])
    const selectedCategories = ref([])
    const categoryPeriod = ref({ month: '' })

    const fetchCategories = async () => {
        try {
            const response = await axios.get('/api/categories')
            allCategories.value = response.data.data || []
        } catch (err) {
            console.error('Error fetching categories:', err)
        }
    }

    const resetCategoryFilters = () => {
        selectedCategories.value = []
        categoryPeriod.value = { month: '' }
    }

    return {
        allCategories,
        selectedCategories,
        categoryPeriod,
        fetchCategories,
        resetCategoryFilters
    }
}