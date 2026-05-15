export const authApi = {
    async getUser() {
        const response = await fetch('/auth/user', { credentials: 'include' })
        return response.json()
    },

    async logout() {
        const response = await fetch('/auth/logout', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'include'
        })
        return response
    }
}