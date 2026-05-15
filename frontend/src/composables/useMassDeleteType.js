import { ref } from 'vue'

export function useMassDeleteType() {
    const deleteType = ref('')
    const typePeriod = ref({ month: '' })

    const resetTypeFilters = () => {
        deleteType.value = ''
        typePeriod.value = { month: '' }
    }

    return {
        deleteType,
        typePeriod,
        resetTypeFilters
    }
}