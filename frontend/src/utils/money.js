import { CURRENCY_SYMBOLS, CURRENCY_CODES } from './constants'

/**
 * Получить символ валюты
 */
export const getCurrencySymbol = (currencyCode) => {
    return CURRENCY_SYMBOLS[currencyCode] || 'Br'
}

/**
 * Получить код валюты по ID
 */
export const getCurrencyCode = (currencyId) => {
    return CURRENCY_CODES[currencyId] || 'BYN'
}

/**
 * Форматирование суммы с валютой
 */
export const formatMoneyWithCurrency = (amount, currencyCode = 'BYN') => {
    if (amount === null || amount === undefined || isNaN(amount)) {
        return `0 ${getCurrencySymbol(currencyCode)}`
    }
    const symbol = getCurrencySymbol(currencyCode)
    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount) + ' ' + symbol
}

/**
 * Форматирование суммы без валюты
 */
export const formatMoneyAmount = (amount) => {
    if (amount === null || amount === undefined || isNaN(amount)) return '0'
    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount)
}

/**
 * Форматирование суммы для транзакции
 */
export const formatTransactionMoney = (transaction) => {
    if (!transaction) return '0 Br'
    const amount = transaction.amount || 0
    const currencyCode = transaction.currency?.code || getCurrencyCode(transaction.currency_id)
    const currencySymbol = getCurrencySymbol(currencyCode)

    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount) + ' ' + currencySymbol
}

/**
 * Форматирование курса (4 знака после запятой)
 */
export const formatRate = (rate) => {
    if (!rate && rate !== 0) return '0.0000'
    return Number(rate).toFixed(4)
}

/**
 * Получить сумму в BYN из транзакции
 */
export const getAmountInByn = (transaction) => {
    if (!transaction) return 0
    if (transaction.amount_in_byn !== null && transaction.amount_in_byn !== undefined) {
        return parseFloat(transaction.amount_in_byn) || 0
    }
    if (transaction.exchange_rate) {
        return (parseFloat(transaction.amount) || 0) * parseFloat(transaction.exchange_rate)
    }
    return parseFloat(transaction.amount) || 0
}