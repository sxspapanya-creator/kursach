/**
 * Форматирование даты
 */
export const formatDate = (dateString, options = {}) => {
    if (!dateString) return 'Дата не указана'
    try {
        const date = new Date(dateString)
        const defaultOptions = {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        }
        return date.toLocaleDateString('ru-RU', { ...defaultOptions, ...options })
    } catch {
        return 'Неверная дата'
    }
}

/**
 * Форматирование дня (число)
 */
export const formatDay = (dateStr) => {
    const date = new Date(dateStr)
    return date.getDate()
}

/**
 * Форматирование месяца
 */
export const formatMonth = (monthString) => {
    if (!monthString) return ''
    const [year, month] = monthString.split('-')
    const date = new Date(year, parseInt(month) - 1, 1)
    return date.toLocaleDateString('ru-RU', { month: 'long', year: 'numeric' })
}

/**
 * Относительная дата (сегодня, вчера, и т.д.)
 */
export const formatRelativeDate = (dateString) => {
    if (!dateString) return 'Дата не указана'
    try {
        const date = new Date(dateString)
        const now = new Date()
        const diffTime = Math.abs(now - date)
        const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24))

        if (date.toDateString() === now.toDateString()) return 'Сегодня'

        const yesterday = new Date(now)
        yesterday.setDate(yesterday.getDate() - 1)
        if (date.toDateString() === yesterday.toDateString()) return 'Вчера'

        if (diffDays <= 7) {
            return date.toLocaleDateString('ru-RU', { weekday: 'short', day: 'numeric' })
        }

        return formatDate(dateString)
    } catch {
        return 'Неверная дата'
    }
}

/**
 * Получить короткое название дня недели
 */
export const getShortDay = (dayOfWeek) => {
    const short = {
        'Понедельник': 'Пн',
        'Вторник': 'Вт',
        'Среда': 'Ср',
        'Четверг': 'Чт',
        'Пятница': 'Пт',
        'Суббота': 'Сб',
        'Воскресенье': 'Вс'
    }
    return short[dayOfWeek] || dayOfWeek?.slice(0, 2)
}