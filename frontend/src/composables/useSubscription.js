import { ref, computed } from 'vue'
import axios from 'axios'

export function useSubscription(user) {
    const plans = ref([])
    const plansByCode = ref(null)
    const plansLoading = ref(false)
    const plansError = ref('')
    const settingPlanId = ref(null)
    const subscriptionSuccess = ref('')
    const subscriptionError = ref('')

    const GROUP_CODE_ORDER = ['free', 'premium']

    const currentPlanBlock = computed(() => {
        if (!plans.value.length) return null
        if (user.value?.plan_id != null) {
            return plans.value.find((p) => p.id === user.value.plan_id) || null
        }
        return plans.value.find((p) => p.code === 'free') || null
    })

    const isPlanExpired = computed(() => {
        const d = user.value?.plan_expires_at
        if (!d) return false
        const end = new Date(d)
        end.setHours(23, 59, 59, 999)
        return end < new Date()
    })

    const planGroupSections = computed(() => {
        const g = plansByCode.value
        if (!g || typeof g !== 'object') return []
        const keys = Object.keys(g)
        keys.sort((a, b) => {
            const ai = GROUP_CODE_ORDER.indexOf(a)
            const bi = GROUP_CODE_ORDER.indexOf(b)
            if (ai >= 0 && bi >= 0) return ai - bi
            if (ai >= 0) return -1
            if (bi >= 0) return 1
            return a.localeCompare(b)
        })
        return keys.map((code) => ({
            code,
            plans: Array.isArray(g[code]) ? g[code] : []
        }))
    })

    const formatPlanPeriod = (type) => {
        if (type === null || type === undefined || type === '') return 'бессрочно'
        if (type === 'yearly') return 'год'
        if (type === 'monthly') return 'месяц'
        return type || ''
    }

    const formatPlanPrice = (plan) => {
        if (!plan) return ''
        const sym = plan.currency?.symbol || plan.currency?.code || ''
        const price = plan.price != null ? Number(plan.price).toLocaleString('ru-RU', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) : '0'
        return sym ? `${price} ${sym}` : price
    }

    const formatExpiryDate = (isoDate) => {
        if (!isoDate) return ''
        const d = new Date(isoDate)
        return d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' })
    }

    const formatGroupTitle = (code) => {
        const labels = { free: 'Бесплатный', premium: 'Премиум' }
        return labels[code] ?? code
    }

    const isCurrentPlan = (plan) => {
        if (!plan?.id) return false
        if (user.value?.plan_id != null) {
            return user.value.plan_id === plan.id
        }
        return plan.code === 'free'
    }

    const fetchPlans = async () => {
        plansLoading.value = true
        plansError.value = ''
        plansByCode.value = null
        try {
            const response = await axios.get('/api/plans', { params: { group_by: 'code' } })
            if (response.data?.status !== 'success') {
                plans.value = []
                plansError.value = 'Не удалось разобрать список тарифов'
                return
            }
            const raw = response.data.data
            if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
                plansByCode.value = raw
                plans.value = Object.values(raw).flat()
            } else if (Array.isArray(raw)) {
                plans.value = raw
            } else {
                plans.value = []
                plansError.value = 'Не удалось разобрать список тарифов'
            }
        } catch (err) {
            plansError.value = err.response?.data?.message || err.response?.data?.error || 'Не удалось загрузить тарифы'
            plans.value = []
        } finally {
            plansLoading.value = false
        }
    }

    const selectPlan = async (plan, fetchUserCallback) => {
        if (!plan?.id || isCurrentPlan(plan)) return

        subscriptionSuccess.value = ''
        subscriptionError.value = ''
        settingPlanId.value = plan.id

        try {
            const response = await axios.post('/api/plans/set-plan', { plan_id: plan.id })
            if (response.data?.status === 'success') {
                subscriptionSuccess.value = 'Тариф обновлён'
                if (fetchUserCallback) {
                    await fetchUserCallback()
                }
                window.dispatchEvent(new CustomEvent('user-updated'))
                setTimeout(() => { subscriptionSuccess.value = '' }, 4000)
            }
        } catch (err) {
            const msg = err.response?.data?.message
            const errs = err.response?.data?.errors
            subscriptionError.value = errs ? Object.values(errs).flat().join(', ') : msg || 'Не удалось сменить тариф'
        } finally {
            settingPlanId.value = null
        }
    }

    const resetSubscriptionMessages = () => {
        subscriptionSuccess.value = ''
        subscriptionError.value = ''
    }

    return {
        plans,
        planGroupSections,
        plansLoading,
        plansError,
        settingPlanId,
        subscriptionSuccess,
        subscriptionError,
        currentPlanBlock,
        isPlanExpired,
        formatPlanPeriod,
        formatPlanPrice,
        formatExpiryDate,
        formatGroupTitle,
        isCurrentPlan,
        fetchPlans,
        selectPlan,
        resetSubscriptionMessages
    }
}