import { ref, reactive } from 'vue'

export function useRegisterForm() {
    const form = reactive({
        name: '',
        email: '',
        password: '',
        password_confirmation: ''
    })

    const errors = reactive({
        name: '',
        email: '',
        password: '',
        password_confirmation: ''
    })

    const validateForm = () => {
        let isValid = true

        Object.keys(errors).forEach(key => errors[key] = '')

        if (!form.name) {
            errors.name = 'Имя обязательно'
            isValid = false
        } else if (form.name.length < 2) {
            errors.name = 'Имя должно быть не менее 2 символов'
            isValid = false
        }

        if (!form.email) {
            errors.email = 'Email обязателен'
            isValid = false
        } else if (!/\S+@\S+\.\S+/.test(form.email)) {
            errors.email = 'Введите корректный email'
            isValid = false
        }

        if (!form.password) {
            errors.password = 'Пароль обязателен'
            isValid = false
        } else if (form.password.length < 6) {
            errors.password = 'Пароль должен быть не менее 6 символов'
            isValid = false
        }

        if (!form.password_confirmation) {
            errors.password_confirmation = 'Подтверждение пароля обязательно'
            isValid = false
        } else if (form.password !== form.password_confirmation) {
            errors.password_confirmation = 'Пароли не совпадают'
            isValid = false
        }

        return isValid
    }

    const resetForm = () => {
        form.name = ''
        form.email = ''
        form.password = ''
        form.password_confirmation = ''
        Object.keys(errors).forEach(key => errors[key] = '')
    }

    return {
        form,
        errors,
        validateForm,
        resetForm
    }
}