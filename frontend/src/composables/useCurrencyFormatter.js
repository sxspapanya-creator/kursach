export function useCurrencyFormatter() {
    // Символы валют для 5 основных валют
    const getCurrencySymbol = (currencyId, currencyCode) => {
        if (currencyCode) {
            const symbols = {
                'BYN': 'Br',
                'RUB': '₽',
                'USD': '$',
                'EUR': '€',
                'CNY': '¥'
            }
            return symbols[currencyCode] || 'Br'
        }

        const symbols = {
            1: 'Br',   // BYN
            2: '₽',    // RUB
            3: '$',    // USD
            4: '€',    // EUR
            5: '¥'     // CNY
        }
        return symbols[currencyId] || 'Br'
    }

    // Код валюты по ID
    const getCurrencyCode = (currencyId) => {
        const codes = {
            1: 'BYN',
            2: 'RUB',
            3: 'USD',
            4: 'EUR',
            5: 'CNY'
        }
        return codes[currencyId] || 'BYN'
    }

    // Форматирование курса (4 знака после запятой)
    const formatRate = (rate) => {
        if (!rate && rate !== 0) return '0.0000'
        return Number(rate).toFixed(4)
    }

    // Форматирование суммы для транзакции (с валютой)
    const formatTransactionMoney = (transaction) => {
        if (!transaction) return '0 Br'
        const amount = transaction.amount || 0
        const currencySymbol = transaction.currency?.symbol || getCurrencySymbol(transaction.currency_id, transaction.currency?.code)

        return new Intl.NumberFormat('ru-RU', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount) + ' ' + currencySymbol
    }

    // Форматирование просто суммы (для сводки)
    const formatMoneyAmount = (amount) => {
        if (amount === null || amount === undefined || isNaN(amount)) return '0'
        return new Intl.NumberFormat('ru-RU', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount)
    }

    return {
        getCurrencySymbol,
        getCurrencyCode,
        formatRate,
        formatTransactionMoney,
        formatMoneyAmount
    }
}