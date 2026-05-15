import { useRegisterForm } from './useRegisterForm'
import { useVerification } from './useVerification'

export function useRegisterSubmit() {
    const { form, errors, validateForm, resetForm } = useRegisterForm()
    const {
        loading,
        verificationCode,
        verificationError,
        needsVerification,
        pendingEmail,
        register,
        verifyEmail,
        resendCode,
        backToRegistration
    } = useVerification()

    const handleSubmit = async (e) => {
        e.preventDefault()

        if (needsVerification.value) {
            await verifyEmail()
        } else {
            if (!validateForm()) return

            const result = await register(form)

            if (result && result.errors) {
                if (result.errors.email) errors.email = result.errors.email[0] || result.errors.email
                if (result.errors.name) errors.name = result.errors.name[0] || result.errors.name
                if (result.errors.password) errors.password = result.errors.password[0] || result.errors.password
            }
        }
    }

    return {
        // Состояние формы
        form,
        errors,

        // Состояние верификации
        loading,
        verificationCode,
        verificationError,
        needsVerification,
        pendingEmail,

        // Методы
        handleSubmit,
        resendCode,
        backToRegistration
    }
}