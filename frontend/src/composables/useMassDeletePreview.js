import { ref, computed } from 'vue'
import axios from 'axios'

export function useMassDeletePreview() {
    const previewTransactions = ref([])
    const previewLoaded = ref(false)
    const loading = ref(false)
    const error = ref('')
    const showConfirmDialog = ref(false)
    const deleting = ref(false)

    const previewStats = computed(() => {
        let income = 0
        let expenses = 0
        previewTransactions.value.forEach(t => {
            const amount = parseFloat(t.amount) || 0
            if (t.type === 'income') income += amount
            else expenses += amount
        })
        return { income, expenses, balance: income - expenses }
    })

    const buildTransactionsQueryString = (opts) => {
        const sp = new URLSearchParams()
        if (opts.fetch_all) sp.set('fetch_all', '1')
        if (opts.type) sp.set('type', opts.type)
        if (opts.date_from) sp.set('date_from', opts.date_from)
        if (opts.date_to) sp.set('date_to', opts.date_to)
        if (opts.year != null && opts.year !== '') sp.set('year', String(opts.year))
        if (opts.month != null && opts.month !== '') sp.set('month', String(opts.month))
        if (opts.category_ids?.length) {
            for (const id of opts.category_ids) {
                sp.append('category_ids[]', String(id))
            }
        }
        return sp.toString()
    }

    const fetchPreview = async (params) => {
        try {
            loading.value = true
            error.value = ''
            const qs = buildTransactionsQueryString(params)
            const response = await axios.get(`/api/transactions?${qs}`)
            previewTransactions.value = response.data.data || []
            previewLoaded.value = true
            if (previewTransactions.value.length === 0) {
                error.value = 'По вашему запросу транзакции не найдены'
                setTimeout(() => { error.value = '' }, 3000)
            }
        } catch (err) {
            console.error('Error previewing transactions:', err)
            error.value = 'Ошибка при поиске транзакций'
        } finally {
            loading.value = false
        }
    }

    const executeDelete = async (transactionIds, router, showNotification) => {
        try {
            deleting.value = true
            error.value = ''
            await axios.post('/api/transactions/mass-delete', { transaction_ids: transactionIds })
            showConfirmDialog.value = false
            previewTransactions.value = []
            previewLoaded.value = false
            if (showNotification) {
                showNotification('success', `Удалено ${transactionIds.length} транзакций`)
            }
            router.push('/transactions')
        } catch (err) {
            console.error('Error deleting transactions:', err)
            error.value = err.response?.data?.message || 'Ошибка при удалении транзакций'
        } finally {
            deleting.value = false
        }
    }

    const resetPreview = () => {
        previewTransactions.value = []
        previewLoaded.value = false
        showConfirmDialog.value = false
    }

    return {
        previewTransactions,
        previewLoaded,
        loading,
        error,
        showConfirmDialog,
        deleting,
        previewStats,
        fetchPreview,
        executeDelete,
        resetPreview
    }
}