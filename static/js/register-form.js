(function () {
    var form = document.getElementById('registerForm');
    if (!form) {
        return;
    }

    var firstName = document.getElementById('registerFirstName');
    var lastName = document.getElementById('registerLastName');
    var email = document.getElementById('registerEmail');
    var password = document.getElementById('registerPassword');
    var section = document.getElementById('registerSection');
    var privacyConsent = document.getElementById('privacyConsent');

    if (!firstName || !lastName || !email || !password || !section || !privacyConsent) {
        return;
    }

    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    var touched = {
        firstName: false,
        lastName: false,
        email: false,
        password: false,
        section: false,
        privacyConsent: false
    };

    function ensureErrorElement(element) {
        var container = element.parentElement;
        var error = container ? container.querySelector('[data-inline-error]') : null;

        if (!error) {
            error = document.createElement('p');
            error.setAttribute('data-inline-error', '1');
            error.className = 'text-red-500 text-xs mt-1 hidden';
            if (container) {
                container.appendChild(error);
            }
        }

        return error;
    }

    function markInvalid(input, message) {
        var error = ensureErrorElement(input);
        input.classList.remove('ring-2', 'ring-emerald-400');
        input.classList.add('ring-2', 'ring-red-400');
        error.textContent = message;
        error.classList.remove('hidden');
        return false;
    }

    function markValid(input) {
        var error = ensureErrorElement(input);
        input.classList.remove('ring-2', 'ring-red-400');
        input.classList.add('ring-2', 'ring-emerald-400');
        error.classList.add('hidden');
        error.textContent = '';
        return true;
    }

    function validateFirstName() {
        var value = firstName.value.trim();
        if (value.length < 2) {
            return markInvalid(firstName, 'First name must be at least 2 characters.');
        }
        return markValid(firstName);
    }

    function validateLastName() {
        var value = lastName.value.trim();
        if (value.length < 2) {
            return markInvalid(lastName, 'Last name must be at least 2 characters.');
        }
        return markValid(lastName);
    }

    function validateEmail() {
        var value = email.value.trim();
        if (value === '') {
            return markInvalid(email, 'Email is required.');
        }
        if (!emailRegex.test(value)) {
            return markInvalid(email, 'Enter a valid student email format.');
        }
        return markValid(email);
    }

    function getPasswordStrength(value) {
        var score = 0;
        if (value.length >= 8) {
            score += 1;
        }
        if (/\d/.test(value)) {
            score += 1;
        }
        if (/[a-z]/.test(value) && /[A-Z]/.test(value)) {
            score += 1;
        }

        if (score <= 1) {
            return 'weak';
        }
        if (score === 2) {
            return 'medium';
        }
        return 'strong';
    }

    function validatePassword() {
        var value = password.value;
        if (value.length < 8) {
            return markInvalid(password, 'Password must be at least 8 characters.');
        }
        if (!/\d/.test(value)) {
            return markInvalid(password, 'Password must contain at least one number.');
        }
        return markValid(password);
    }

    function validateSection() {
        var value = section.value.trim();
        if (value === '') {
            return markInvalid(section, 'Section is required.');
        }
        return markValid(section);
    }

    function validatePrivacyConsent() {
        if (!privacyConsent.checked) {
            return markInvalid(privacyConsent, 'You must agree to the terms and conditions.');
        }
        return markValid(privacyConsent);
    }

    var passwordWrapper = password.parentElement;
    var strengthWrap = document.createElement('div');
    strengthWrap.className = 'mt-2';
    strengthWrap.innerHTML = '<div class="h-1.5 rounded bg-slate-200 overflow-hidden"><span id="registerPasswordStrengthFill" class="block h-full bg-red-400 transition-all" style="width: 0%"></span></div><p id="registerPasswordStrengthText" class="mt-1 text-xs text-slate-600">Strength: weak</p>';
    passwordWrapper.parentNode.insertBefore(strengthWrap, passwordWrapper.nextSibling);

    var strengthFill = document.getElementById('registerPasswordStrengthFill');
    var strengthText = document.getElementById('registerPasswordStrengthText');

    function updatePasswordStrength() {
        var strength = getPasswordStrength(password.value);
        if (!strengthFill || !strengthText) {
            return;
        }

        if (strength === 'weak') {
            strengthFill.className = 'block h-full bg-red-400 transition-all';
            strengthFill.style.width = '33%';
            strengthText.textContent = 'Strength: weak';
        } else if (strength === 'medium') {
            strengthFill.className = 'block h-full bg-amber-400 transition-all';
            strengthFill.style.width = '66%';
            strengthText.textContent = 'Strength: medium';
        } else {
            strengthFill.className = 'block h-full bg-emerald-500 transition-all';
            strengthFill.style.width = '100%';
            strengthText.textContent = 'Strength: strong';
        }
    }

    firstName.addEventListener('blur', function () {
        touched.firstName = true;
        validateFirstName();
    });
    firstName.addEventListener('input', function () {
        if (touched.firstName) {
            validateFirstName();
        }
    });

    lastName.addEventListener('blur', function () {
        touched.lastName = true;
        validateLastName();
    });
    lastName.addEventListener('input', function () {
        if (touched.lastName) {
            validateLastName();
        }
    });

    email.addEventListener('blur', function () {
        touched.email = true;
        validateEmail();
    });
    email.addEventListener('input', function () {
        if (touched.email) {
            validateEmail();
        }
    });

    password.addEventListener('blur', function () {
        touched.password = true;
        validatePassword();
    });
    password.addEventListener('input', function () {
        updatePasswordStrength();
        if (touched.password) {
            validatePassword();
        }
    });

    section.addEventListener('blur', function () {
        touched.section = true;
        validateSection();
    });
    section.addEventListener('input', function () {
        if (touched.section) {
            validateSection();
        }
    });

    privacyConsent.addEventListener('blur', function () {
        touched.privacyConsent = true;
        validatePrivacyConsent();
    });
    privacyConsent.addEventListener('change', function () {
        if (touched.privacyConsent) {
            validatePrivacyConsent();
        }
    });

    form.addEventListener('submit', function (event) {
        touched.firstName = true;
        touched.lastName = true;
        touched.email = true;
        touched.password = true;
        touched.section = true;
        touched.privacyConsent = true;

        var checks = [
            { input: firstName, valid: validateFirstName() },
            { input: lastName, valid: validateLastName() },
            { input: email, valid: validateEmail() },
            { input: password, valid: validatePassword() },
            { input: section, valid: validateSection() },
            { input: privacyConsent, valid: validatePrivacyConsent() }
        ];

        var firstInvalid = checks.find(function (item) {
            return !item.valid;
        });

        if (firstInvalid) {
            event.preventDefault();
            firstInvalid.input.focus();
        }
    });

    updatePasswordStrength();
})();
