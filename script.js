document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector(".login-card form");
    if (!form) return;

    const usernameInput = document.getElementById("username");
    const emailInput = document.getElementById("email");
    const passwordInput = document.getElementById("password");
    const confirmPasswordInput = document.getElementById("confirm_password");

    form.addEventListener("submit", (e) => {
        let errors = [];

        // Clear existing errors
        removeErrorContainer();

        // 1. If it's registration form (username exists)
        if (usernameInput) {
            const username = usernameInput.value.trim();
            if (username.length < 3) {
                errors.push("Username must be at least 3 characters long.");
            }
            if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                errors.push("Username can only contain letters, numbers, and underscores.");
            }
        }

        // 2. Email validation (both login and register)
        if (emailInput) {
            const email = emailInput.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                errors.push("Please enter a valid email address.");
            }
        }

        // 3. Password validation (both login and register)
        if (passwordInput) {
            const password = passwordInput.value;
            if (password.length < 8) {
                errors.push("Password must be at least 8 characters long.");
            }
        }

        // 4. Confirm Password validation (registration only)
        if (confirmPasswordInput) {
            const confirmPassword = confirmPasswordInput.value;
            if (passwordInput && passwordInput.value !== confirmPassword) {
                errors.push("Passwords do not match.");
            }
        }

        // If errors, prevent submit and show them
        if (errors.length > 0) {
            e.preventDefault();
            showErrors(errors);
        }
    });

    function showErrors(errorsList) {
        let errorDiv = document.querySelector(".login-card .error");
        if (!errorDiv) {
            errorDiv = document.createElement("div");
            errorDiv.className = "error";
            const formTitle = document.querySelector(".login-card h2");
            if (formTitle) {
                formTitle.parentNode.insertBefore(errorDiv, formTitle.nextSibling);
            } else {
                form.parentNode.insertBefore(errorDiv, form);
            }
        }
        
        errorDiv.innerHTML = errorsList.join("<br>");
        errorDiv.style.display = "block";
    }

    function removeErrorContainer() {
        const errorDiv = document.querySelector(".login-card .error");
        if (errorDiv) {
            errorDiv.innerHTML = "";
            errorDiv.style.display = "none";
        }
    }
});