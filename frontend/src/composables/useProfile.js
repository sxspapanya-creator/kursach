import { onMounted, watch } from 'vue'
import { useProfileForm } from './useProfileForm'
import { usePasswordChange } from './usePasswordChange'
import { useSubscription } from './useSubscription'
import { useProfileLogout } from './useProfileLogout'
import { useProfileTabs } from './useProfileTabs'

export function useProfile() {
    const {
        user,
        form,
        userInitials,
        isActive,
        profileLoading,
        profileSuccess,
        profileError,
        needsVerification,
        verificationCode,
        fetchUser,
        updateProfile,
        verifyEmail
    } = useProfileForm()

    const {
        passwordForm,
        passwordLoading,
        passwordSuccess,
        passwordError,
        changePassword
    } = usePasswordChange()

    const {
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
        selectPlan
    } = useSubscription(user)

    const { confirmLogout } = useProfileLogout()
    const { activeTab, setTab, syncTabFromRoute } = useProfileTabs()

    const handleSelectPlan = (plan) => {
        selectPlan(plan, fetchUser)
    }

    onMounted(async () => {
        await fetchUser()
        syncTabFromRoute()
    })

    watch(activeTab, (tab) => {
        if (tab === 'subscription') {
            fetchPlans()
        }
    })

    return {
        activeTab,
        setTab,
        user,
        form,
        userInitials,
        isActive,
        profileLoading,
        profileSuccess,
        profileError,
        needsVerification,
        verificationCode,
        updateProfile,
        verifyEmail,
        passwordForm,
        passwordLoading,
        passwordSuccess,
        passwordError,
        changePassword,
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
        handleSelectPlan,
        confirmLogout
    }
}