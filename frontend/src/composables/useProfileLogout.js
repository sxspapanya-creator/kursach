import { useRouter } from 'vue-router'

export function useProfileLogout() {
    const router = useRouter()

    const logout = async () => {
        try {
            await fetch('/auth/logout', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'include'
            })
            localStorage.removeItem('auth_token')
            localStorage.removeItem('user')
            window.dispatchEvent(new CustomEvent('user-logout'))
            router.push('/login')
        } catch (error) {
            console.error('Ошибка при выходе:', error)
            localStorage.removeItem('auth_token')
            localStorage.removeItem('user')
            window.dispatchEvent(new CustomEvent('user-logout'))
            router.push('/login')
        }
    }

    const confirmLogout = () => {
        logout()
    }

    return {
        logout,
        confirmLogout
    }
}