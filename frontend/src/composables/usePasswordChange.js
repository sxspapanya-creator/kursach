import { ref } from 'vue'
import axios from 'axios'

export function usePasswordChange() {
    const passwordLoading = ref(false)
    const passwordSuccess = ref('')
    const passwordError = ref('')

    const passwordForm = ref({
        current_password: '',
        new_password: '',
        new_password_confirmation: ''
    })

    const changePassword = async () => {
        if (passwordForm.value.new_password !== passwordForm.value.new_password_confirmation) {
            passwordError.value = 'Пароли не совпадают'
            return
        }

        if (passwordForm.value.new_password.length < 6) {
            passwordError.value = 'Новый пароль должен содержать минимум 6 символов'
            return
        }

        passwordLoading.value = true
        passwordSuccess.value = ''
        passwordError.value = ''

        try {
            const response = await axios.put('/auth/password', {
                current_password: passwordForm.value.current_password,
                new_password: passwordForm.value.new_password,
                new_password_confirmation: passwordForm.value.new_password_confirmation
            })

            if (response.data.status === 'success') {
                passwordSuccess.value = 'Пароль успешно изменен'
                passwordForm.value = {
                    current_password: '',
                    new_password: '',
                    new_password_confirmation: ''
                }
                setTimeout(() => { passwordSuccess.value = '' }, 3000)
            }
        } catch (err) {
            if (err.response?.data?.errors) {
                passwordError.value = Object.values(err.response.data.errors).flat().join(', ')
            } else {
                passwordError.value = err.response?.data?.message || 'Ошибка при смене пароля'
            }
        } finally {
            passwordLoading.value = false
        }
    }

    const resetPasswordForm = () => {
        passwordForm.value = {
            current_password: '',
            new_password: '',
            new_password_confirmation: ''
        }
        passwordSuccess.value = ''
        passwordError.value = ''
    }

    return {
        passwordForm,
        passwordLoading,
        passwordSuccess,
        passwordError,
        changePassword,
        resetPasswordForm
    }
}