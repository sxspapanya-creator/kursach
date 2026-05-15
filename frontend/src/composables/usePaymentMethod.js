export function usePaymentMethod() {
    const getPaymentMethodLabel = (method) => {
        const methods = {
            cash: 'Наличные',
            card: 'Карта',
            transfer: 'Перевод'
        }
        return methods[method] || method
    }

    return { getPaymentMethodLabel }
}