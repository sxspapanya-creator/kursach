export function useDateFormatter() {
    const formatDate = (dateString) => {
        if (!dateString) return 'Дата не указана'
        try {
            const date = new Date(dateString)
            return date.toLocaleDateString('ru-RU', {
                day: 'numeric',
                month: 'short',
                year: 'numeric'
            })
        } catch {
            return 'Неверная дата'
        }
    }

    return { formatDate }
}