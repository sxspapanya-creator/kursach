// Валюты
export const CURRENCIES = {
    BYN: { code: 'BYN', symbol: 'Br', name: 'Белорусский рубль' },
    RUB: { code: 'RUB', symbol: '₽', name: 'Российский рубль' },
    USD: { code: 'USD', symbol: '$', name: 'Доллар США' },
    EUR: { code: 'EUR', symbol: '€', name: 'Евро' },
    CNY: { code: 'CNY', symbol: '¥', name: 'Китайский юань' }
}

export const CURRENCY_SYMBOLS = {
    BYN: 'Br',
    RUB: '₽',
    USD: '$',
    EUR: '€',
    CNY: '¥'
}

export const CURRENCY_CODES = {
    1: 'BYN',
    2: 'RUB',
    3: 'USD',
    4: 'EUR',
    5: 'CNY'
}

// Способы оплаты
export const PAYMENT_METHODS = {
    cash: { value: 'cash', label: 'Наличные', icon: '💰' },
    card: { value: 'card', label: 'Карта', icon: '💳' },
    transfer: { value: 'transfer', label: 'Перевод', icon: '🏦' }
}

// Типы транзакций
export const TRANSACTION_TYPES = {
    income: { value: 'income', label: 'Доход', sign: '+', color: 'green' },
    expense: { value: 'expense', label: 'Расход', sign: '-', color: 'red' }
}

// Периоды
export const PERIODS = {
    month: 'month',
    year: 'year'
}

// Уровни надежности CV
export const CV_LEVELS = {
    high: { min: 0, max: 15, label: 'Стабильные расходы', color: '#4caf50' },
    medium: { min: 15, max: 30, label: 'Умеренные колебания', color: '#ff9800' },
    low: { min: 30, max: 50, label: 'Нестабильные расходы', color: '#f44336' },
    veryLow: { min: 50, max: 100, label: 'Очень нестабильные расходы', color: '#d32f2f' }
}

// Методы прогнозирования
export const FORECAST_METHODS = {
    SimpleExtrapolation: { name: 'SimpleExtrapolation', label: 'Простая экстраполяция', months: '3-6' },
    LinearRegression: { name: 'LinearRegression', label: 'Линейная регрессия', months: '7-14' },
    DoubleExponentialSmoothing: { name: 'DoubleExponentialSmoothing', label: 'Двойное сглаживание', months: '15-23' },
    HoltWinters: { name: 'HoltWinters', label: 'Хольта-Уинтерса', months: '24+' }
}