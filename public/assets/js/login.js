document.addEventListener('DOMContentLoaded', function () {
    var passwordInput = document.getElementById('password');
    var toggleButton = document.getElementById('passwordToggle');
    var submitButton = document.getElementById('loginSubmitBtn');
    var form = document.querySelector('form[method="post"]');

    if (passwordInput && toggleButton) {
        toggleButton.addEventListener('click', function () {
            var isHidden = passwordInput.type === 'password';
            passwordInput.type = isHidden ? 'text' : 'password';
            toggleButton.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
            toggleButton.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            toggleButton.innerHTML = isHidden
                ? '<i class="fa-regular fa-eye-slash"></i>'
                : '<i class="fa-regular fa-eye"></i>';
        });
    }

    if (form && submitButton) {
        form.addEventListener('submit', function () {
            submitButton.disabled = true;
            submitButton.classList.add('is-loading');
            submitButton.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i><span>Signing In...</span>';
        });
    }
});
