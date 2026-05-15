import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'

export function useProfileTabs() {
    const route = useRoute()
    const activeTab = ref('profile')

    const syncTabFromRoute = () => {
        const t = route.query.tab
        if (t === 'subscription' || t === 'security' || t === 'profile') {
            activeTab.value = t
        }
    }

    const setTab = (tab) => {
        activeTab.value = tab
    }

    watch(() => route.query.tab, () => {
        syncTabFromRoute()
    })

    return {
        activeTab,
        setTab,
        syncTabFromRoute
    }
}