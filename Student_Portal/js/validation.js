const form = document.getElementById('studentRegistrationForm');
const strengthMeter = document.getElementById('passwordStrength');
const strengthText = document.getElementById('strengthText');
const formSuccess = document.getElementById('formSuccess');

if (form) {
    const namePattern = /^[A-Za-z .'-]{2,50}$/;
    const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    const mobilePattern = /^[6-9]\d{9}$/;
    const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/;

    function updateStrengthMeter(passwordValue) {
        const password = passwordValue.trim();

        if (!password) {
            if (strengthMeter) {
                strengthMeter.className = 'password-strength weak';
            }
            if (strengthText) {
                strengthText.textContent = 'Weak';
            }
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
        } else if (password.length >= 6 && strengthScore >= 2) {
            strengthMeter.className = 'password-strength medium';
            strengthText.textContent = 'Medium';
        } else {
            strengthMeter.className = 'password-strength weak';
            strengthText.textContent = 'Weak';
        }
    }

    function clearFieldError(field) {
        const errorElement = document.getElementById(field.id + 'Error');

        field.classList.remove('invalid');
        field.setAttribute('aria-invalid', 'false');

        if (errorElement) {
            errorElement.textContent = '';
        }
    }

    function showError(field, message) {
        const errorElement = document.getElementById(field.id + 'Error');

        field.classList.add('invalid');
        field.setAttribute('aria-invalid', 'true');

        if (errorElement) {
            errorElement.textContent = message;
        }
    }

    function validateName(field) {
        const value = field.value.trim();

        if (!value) {
            return 'Full name is required.';
        }

        if (!namePattern.test(value)) {
            return 'Enter a valid name using 2–50 letters, spaces, dots, apostrophes or hyphens.';
        }

        return '';
    }

    function validateEmail(field) {
        const value = field.value.trim();

        if (!value) {
            return 'Email is required.';
        }

        if (!emailPattern.test(value)) {
            return 'Enter a valid email like name@example.com.';
        }

        return '';
    }

    function validateMobile(field) {
        const value = field.value.trim();

        if (!value) {
            return 'Mobile number is required.';
        }

        if (!mobilePattern.test(value)) {
            return 'Enter a 10-digit Indian mobile number starting with 6, 7, 8 or 9.';
        }

        return '';
    }

    function validatePassword(field) {
        const value = field.value;

        if (!value) {
            return 'Password is required.';
        }

        if (!passwordPattern.test(value)) {
            return 'Use at least 8 characters with uppercase, lowercase, a number and a special character.';
        }

        return '';
    }

    function validateConfirmPassword(field) {
        const password = document.getElementById('password').value;
        const value = field.value;

        if (!value) {
            return 'Confirm password is required.';
        }

        if (value !== password) {
            return 'Passwords do not match.';
        }

        return '';
    }

    function validateSelect(field) {
        return field.value ? '' : 'Please select an option.';
    }

    function validateGender() {
        const radios = document.querySelectorAll('input[name="gender"]');
        const selected = Array.from(radios).some(radio => radio.checked);
        const errorElement = document.getElementById('genderError');

        radios.forEach(radio => {
            radio.setAttribute('aria-invalid', String(!selected));
        });

        if (errorElement) {
            errorElement.textContent = selected ? '' : 'Please select your gender.';
        }

        return selected;
    }

    function validateTerms(field) {
        return field.checked ? '' : 'You must accept the terms and conditions.';
    }

    function validateField(field) {
        let message = '';

        switch (field.id) {
            case 'fullName':
                message = validateName(field);
                break;

            case 'email':
                message = validateEmail(field);
                break;

            case 'mobile':
                message = validateMobile(field);
                break;

            case 'password':
                message = validatePassword(field);
                updateStrengthMeter(field.value);

                const confirmField = document.getElementById('confirmPassword');

                if (confirmField && confirmField.dataset.touched === 'true') {
                    validateField(confirmField);
                }
                break;

            case 'confirmPassword':
                message = validateConfirmPassword(field);
                break;

            case 'course':
            case 'year':
            case 'country':
            case 'state':
            case 'city':
                message = validateSelect(field);
                break;

            case 'terms':
                message = validateTerms(field);
                break;
        }

        if (message) {
            showError(field, message);
            return false;
        }

        clearFieldError(field);
        return true;
    }

    function clearAllErrors() {
        form.querySelectorAll('input, select').forEach(field => {
            clearFieldError(field);
            delete field.dataset.touched;
        });

        const genderError = document.getElementById('genderError');

        if (genderError) {
            genderError.textContent = '';
        }

        updateStrengthMeter('');
    }

    function addValidationListeners() {
        form.querySelectorAll('input, select').forEach(field => {
            field.addEventListener('blur', function() {
                field.dataset.touched = 'true';

                if (field.name === 'gender') {
                    validateGender();
                } else {
                    validateField(field);
                }
            });

            field.addEventListener('input', function() {
                if (field.dataset.touched === 'true') {
                    validateField(field);
                }
            });

            field.addEventListener('change', function() {
                field.dataset.touched = 'true';

                if (field.name === 'gender') {
                    validateGender();
                } else {
                    validateField(field);
                }
            });
        });
    }

    form.addEventListener('submit', function(event) {
        event.preventDefault();

        let isValidForm = true;

        form.querySelectorAll('input, select').forEach(field => {
            field.dataset.touched = 'true';

            if (field.name === 'gender') {
                return;
            }

            if (!validateField(field)) {
                isValidForm = false;
            }
        });

        if (!validateGender()) {
            isValidForm = false;
        }

        if (!isValidForm) {
            const firstInvalidField = form.querySelector('[aria-invalid="true"]');

            if (firstInvalidField) {
                firstInvalidField.focus();
            }

            if (formSuccess) {
                formSuccess.hidden = true;
            }

            return;
        }

        if (formSuccess) {
            formSuccess.hidden = false;
            formSuccess.textContent =
                'Registration successful. Your details were accepted.';
        }

        alert('Registration Successful!\n\nYour details were validated successfully.');

        form.reset();
        clearAllErrors();

        if (formSuccess) {
            formSuccess.hidden = false;
            formSuccess.textContent =
                'Registration successful. Your details were accepted.';
        }
    });

    form.addEventListener('reset', function() {
        setTimeout(function() {
            clearAllErrors();

            if (formSuccess) {
                formSuccess.hidden = true;
                formSuccess.textContent = '';
            }
        }, 0);
    });

    addValidationListeners();
    updateStrengthMeter('');
}