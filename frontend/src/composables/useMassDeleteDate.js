import { ref, computed } from 'vue'

export function useMassDeleteDate() {
    const dateSelectionType = ref('range')
    const dateRange = ref({ from: '', to: '' })
    const singleDate = ref('')
    const dateFromError = ref('')
    const dateToError = ref('')
    const singleDateError = ref('')
    const minDate = ref('')
    const maxDate = ref('')
    const availableDates = ref([])
    const allDatesAllowed = ref(false)

    const setDateRange = (dates) => {
        if (dates && dates.length > 0) {
            minDate.value = dates[0]
            maxDate.value = dates[dates.length - 1]
        } else {
            const today = new Date()
            const sixMonthsAgo = new Date()
            sixMonthsAgo.setMonth(today.getMonth() - 6)
            maxDate.value = today.toISOString().split('T')[0]
            minDate.value = sixMonthsAgo.toISOString().split('T')[0]
        }
    }

    const isDateAvailable = (date) => {
        if (!date) return true
        if (allDatesAllowed.value) return true
        return availableDates.value.includes(date)
    }

    const validateDateFrom = () => {
        dateFromError.value = ''
        if (dateRange.value.from && !isDateAvailable(dateRange.value.from)) {
            dateFromError.value = 'На эту дату нет курса валют'
        }
    }

    const validateDateTo = () => {
        dateToError.value = ''
        if (dateRange.value.to && !isDateAvailable(dateRange.value.to)) {
            dateToError.value = 'На эту дату нет курса валют'
        }
    }

    const validateSingleDate = () => {
        singleDateError.value = ''
        if (singleDate.value && !isDateAvailable(singleDate.value)) {
            singleDateError.value = 'На эту дату нет курса валют'
        }
    }

    const availableDatesHint = computed(() => {
        if (allDatesAllowed.value) return ''
        if (availableDates.value.length === 0) return 'Нет доступных дат'
        if (availableDates.value.length > 10) {
            return `${availableDates.value[0]} ... ${availableDates.value[availableDates.value.length - 1]} (${availableDates.value.length} дат)`
        }
        return availableDates.value.join(', ')
    })

    const isDateSelectionValid = computed(() => {
        if (dateSelectionType.value === 'range') {
            return dateRange.value.from && dateRange.value.to
        }
        return singleDate.value
    })

    const isDateSelectionUnavailable = computed(() => {
        if (dateSelectionType.value === 'range') {
            return (dateRange.value.from && !isDateAvailable(dateRange.value.from)) ||
                (dateRange.value.to && !isDateAvailable(dateRange.value.to))
        }
        return singleDate.value && !isDateAvailable(singleDate.value)
    })

    const resetDateFilters = () => {
        dateSelectionType.value = 'range'
        dateRange.value = { from: '', to: '' }
        singleDate.value = ''
        dateFromError.value = ''
        dateToError.value = ''
        singleDateError.value = ''
    }

    return {
        dateSelectionType,
        dateRange,
        singleDate,
        dateFromError,
        dateToError,
        singleDateError,
        minDate,
        maxDate,
        availableDates,
        allDatesAllowed,
        setDateRange,
        isDateAvailable,
        validateDateFrom,
        validateDateTo,
        validateSingleDate,
        availableDatesHint,
        isDateSelectionValid,
        isDateSelectionUnavailable,
        resetDateFilters
    }
}