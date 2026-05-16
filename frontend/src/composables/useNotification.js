import { ref } from 'vue'

export function useNotification() {
    const notification = ref({
        show: false,
        type: 'info',
        message: ''
    })

    let timeoutId = null

    const showNotification = (type, message, duration = 5000) => {
        if (timeoutId) {
            clearTimeout(timeoutId)
        }

        notification.value = { show: true, type, message }

        timeoutId = setTimeout(() => {
            hideNotification()
        }, duration)
    }

    const hideNotification = () => {
        notification.value.show = false
        if (timeoutId) {
            clearTimeout(timeoutId)
            timeoutId = null
        }
    }

    // Удобные методы для разных типов
    const success = (message, duration = 5000) => showNotification('success', message, duration)
    const error = (message, duration = 5000) => showNotification('error', message, duration)
    const info = (message, duration = 5000) => showNotification('info', message, duration)
    const warning = (message, duration = 5000) => showNotification('warning', message, duration)

    return {
        notification,
        showNotification,
        hideNotification,
        success,
        error,
        info,
        warning
    }
}