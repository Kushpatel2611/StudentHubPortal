const form = document.getElementById('studentRegistrationForm');
const strengthMeter = document.getElementById('passwordStrength');
const strengthText = document.getElementById('strengthText');
const formSuccess = document.getElementById('formSuccess');

if (form) {
    // Name: allows letters, spaces, dots, apostrophes, and hyphens only.
    const namePattern = /^[A-Za-z .'-]{2,50}$/;

    // Email: standard username@domain.com pattern.
    const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

    // Mobile: 10 digit Indian number starting with 6, 7, 8, or 9.
    const mobilePattern = /^[6-9]\d{9}$/;

    // Password: at least 8 characters with one uppercase, one lowercase, one digit, and one special character.
    const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/;

    function updateStrengthMeter(passwordValue) {
        const password = passwordValue.trim();

        if (!password) {
            strengthMeter.className = 'password-strength weak';
            strengthText.textContent = 'Weak';
            return;
        }

        const strengthScore = [
            /[a-z]/.test(password),
            /[A-Z]/.test(password),
            /\d/.test(password),
            /[^A-Za-z0-9]/.test(password)
        ].filter(Boolean).length;

        if (password.length >= 8 && strengthScore >= 4) {
            strengthMeter.className = 'password-strength strong';
            strengthText.textContent = 'Strong';
            return;
        }

        if (password.length >= 6 && strengthScore >= 2) {
            strengthMeter.className = 'password-strength medium';
            strengthText.textContent = 'Medium';
            return;
        }

        strengthMeter.className = 'password-strength weak';
        strengthText.textContent = 'Weak';
    }

    function setFieldState(field, isValid) {
        const hasField = field && field.id;

        if (!hasField) {
            return;
        }

        const errorElement = document.getElementById(`${field.id}Error`);

        if (field.type === 'radio') {
            const radioButtons = document.querySelectorAll('input[name="gender"]');
            radioButtons.forEach((radio) => {
                radio.setAttribute('aria-invalid', String(!isValid));
            });
            return;
        }

        if (errorElement) {
            errorElement.textContent = isValid ? '' : errorElement.textContent;
        }

        field.setAttribute('aria-invalid', String(!isValid));
        field.classList.toggle('invalid', !isValid);
    }

    function clearFieldError(field) {
        const errorElement = document.getElementById(`${field.id}Error`);
        field.classList.remove('invalid');
        field.setAttribute('aria-invalid', 'false');

        if (errorElement) {
            errorElement.textContent = '';
        }
    }

    function validateName(field) {
        const value = field.value.trim();

        if (!value) {
            return { valid: false, message: 'Full name is required.' };
        }

        if (!namePattern.test(value)) {
            return { valid: false, message: 'Name must be 2 to 50 characters using letters, spaces, dots, apostrophes, or hyphens.' };
        }

        return { valid: true, message: '' };
    }

    function validateEmail(field) {
        const value = field.value.trim();

        if (!value) {
            return { valid: false, message: 'Email is required.' };
        }

        if (!emailPattern.test(value)) {
            return { valid: false, message: 'Enter a valid email like name@example.com.' };
        }

        return { valid: true, message: '' };
    }

    function validateMobile(field) {
        const value = field.value.trim();

        if (!value) {
            return { valid: false, message: 'Mobile number is required.' };
        }

        if (!mobilePattern.test(value)) {
            return { valid: false, message: 'Mobile number must be 10 digits and start with 6, 7, 8, or 9.' };
        }

        return { valid: true, message: '' };
    }

    function validatePassword(field) {
        const value = field.value.trim();

        if (!value) {
            return { valid: false, message: 'Password is required.' };
        }

        if (!passwordPattern.test(value)) {
            return { valid: false, message: 'Password must be at least 8 characters with one uppercase, one lowercase, one number, and one special character.' };
        }

        return { valid: true, message: '' };
    }

    function validateConfirmPassword(field) {
        const value = field.value.trim();
        const password = document.getElementById('password').value.trim();

        if (!value) {
            return { valid: false, message: 'Confirm password is required.' };
        }

        if (value !== password) {
            return { valid: false, message: 'Passwords do not match.' };
        }

        return { valid: true, message: '' };
    }

    function validateSelect(field) {
        if (!field.value) {
            return { valid: false, message: 'Please select an option.' };
        }

        return { valid: true, message: '' };
    }

    function validateGenderGroup() {
        const radios = document.querySelectorAll('input[name="gender"]');
        const selected = Array.from(radios).some((radio) => radio.checked);

        radios.forEach((radio) => {
            radio.setAttribute('aria-invalid', String(!selected));
        });

        const errorElement = document.getElementById('genderError');
        if (!selected) {
            if (errorElement) {
                errorElement.textContent = 'Please select your gender.';
            }
            return false;
        }

        if (errorElement) {
            errorElement.textContent = '';
        }
        return true;
    }

    function validateTerms(field) {
        if (!field.checked) {
            return { valid: false, message: 'You must accept the terms and conditions.' };
        }

        return { valid: true, message: '' };
    }

    function showError(field, message) {
        const errorElement = document.getElementById(`${field.id}Error`);

        if (field.type === 'radio') {
            const radioButtons = document.querySelectorAll('input[name="gender"]');
            radioButtons.forEach((radio) => {
                radio.setAttribute('aria-invalid', 'true');
            });
            if (errorElement) {
                errorElement.textContent = message;
            }
            return;
        }

        field.setAttribute('aria-invalid', 'true');
        field.classList.add('invalid');

        if (errorElement) {
            errorElement.textContent = message;
        }
    }

    function validateField(field) {
        let result = { valid: true, message: '' };

        if (field.name === 'gender') {
            result = validateGenderGroup() ? { valid: true, message: '' } : { valid: false, message: 'Please select your gender.' };
        } else if (field.id === 'fullName') {
            result = validateName(field);
        } else if (field.id === 'email') {
            result = validateEmail(field);
        } else if (field.id === 'mobile') {
            result = validateMobile(field);
        } else if (field.id === 'password') {
            result = validatePassword(field);
            if (field.value.trim()) {
                updateStrengthMeter(field.value);
            }
            const confirmPassword = document.getElementById('confirmPassword');
            if (confirmPassword && confirmPassword.value.trim()) {
                validateField(confirmPassword);
            }
        } else if (field.id === 'confirmPassword') {
            result = validateConfirmPassword(field);
        } else if (field.id === 'course' || field.id === 'year' || field.id === 'country' || field.id === 'state' || field.id === 'city') {
            result = validateSelect(field);
        } else if (field.id === 'terms') {
            result = validateTerms(field);
        }

        if (result.valid) {
            if (field.type === 'radio') {
                const radioButtons = document.querySelectorAll('input[name="gender"]');
                radioButtons.forEach((radio) => radio.setAttribute('aria-invalid', 'false'));
            } else {
                clearFieldError(field);
            }
            return true;
        }

        if (field.type === 'radio') {
            showError(field, result.message);
        } else {
            showError(field, result.message);
        }

        return false;
    }

    function markTouched(field) {
        field.dataset.touched = 'true';
    }

    function addValidationListeners() {
        const fields = form.querySelectorAll('input, select');

        fields.forEach((field) => {
            field.addEventListener('blur', () => {
                markTouched(field);
                if (field.type === 'radio') {
                    validateGenderGroup();
                    return;
                }
                validateField(field);
            });

            field.addEventListener('input', () => {
                if (field.dataset.touched === 'true' || field.type === 'checkbox' || field.type === 'radio') {
                    if (field.type === 'radio') {
                        validateGenderGroup();
                        return;
                    }
                    validateField(field);
                }
            });

            field.addEventListener('change', () => {
                markTouched(field);
                if (field.type === 'checkbox' || field.type === 'radio' || field.tagName === 'SELECT') {
                    validateField(field);
                }
            });
        });
    }

    function findFirstInvalidField() {
        const fields = form.querySelectorAll('input, select');

        for (const field of fields) {
            if (field.type === 'radio') {
                if (!validateGenderGroup()) {
                    return document.querySelector('input[name="gender"]');
                }
                continue;
            }

            if (field.getAttribute('aria-invalid') === 'true') {
                return field;
            }
        }

        return null;
    }

    function clearAllErrors() {
        const fields = form.querySelectorAll('input, select');
        fields.forEach((field) => {
            if (field.type === 'radio') {
                const radioButtons = document.querySelectorAll('input[name="gender"]');
                radioButtons.forEach((radio) => {
                    radio.setAttribute('aria-invalid', 'false');
                });
                const genderError = document.getElementById('genderError');
                if (genderError) {
                    genderError.textContent = '';
                }
                return;
            }
            clearFieldError(field);
        });
        updateStrengthMeter('');
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        const fieldsToCheck = Array.from(form.querySelectorAll('input, select'));
        let isValidForm = true;

        fieldsToCheck.forEach((field) => {
            field.dataset.touched = 'true';
            if (field.type === 'radio') {
                if (!validateGenderGroup()) {
                    isValidForm = false;
                }
                return;
            }

            if (!validateField(field)) {
                isValidForm = false;
            }
        });

        if (!isValidForm) {
            const firstInvalidField = findFirstInvalidField();
            if (firstInvalidField) {
                firstInvalidField.focus();
            }
            return;
        }

        // Server-side validation will be added in a later practical.
        formSuccess.hidden = false;
        formSuccess.textContent = 'Registration successful. Your details were accepted.';
        form.reset();
        clearAllErrors();
        updateStrengthMeter('');
    });

    form.addEventListener('reset', () => {
        setTimeout(() => {
            formSuccess.hidden = true;
            formSuccess.textContent = '';
            clearAllErrors();
        }, 0);
    });

    addValidationListeners();
    updateStrengthMeter('');
}
