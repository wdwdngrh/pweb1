// js/auth-dynamic.js - Dynamic authentication handling
document.addEventListener('DOMContentLoaded', function() {
    checkAuthStatus();
    
    // Login Form Handler
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'login');
            
            try {
                const response = await fetch('api/auth.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('success', data.message);
                    const modal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
                    if (modal) modal.hide();
                    
                    // Reload page after short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showAlert('danger', data.message);
                }
            } catch (error) {
                showAlert('danger', 'Terjadi kesalahan. Silakan coba lagi.');
                console.error('Login error:', error);
            }
        });
    }

    // Register Form Handler
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'register');
            
            try {
                const response = await fetch('api/auth.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('success', data.message);
                    const modal = bootstrap.Modal.getInstance(document.getElementById('registerModal'));
                    if (modal) modal.hide();
                    
                    // Show login modal
                    setTimeout(() => {
                        const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
                        loginModal.show();
                    }, 1500);
                } else {
                    showAlert('danger', data.message);
                }
            } catch (error) {
                showAlert('danger', 'Terjadi kesalahan. Silakan coba lagi.');
                console.error('Register error:', error);
            }
        });
    }
    
    // Logout handler
    const logoutButtons = document.querySelectorAll('[data-action="logout"]');
    logoutButtons.forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            
            try {
                const response = await fetch('api/auth.php?action=logout');
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = 'index.php';
                }
            } catch (error) {
                console.error('Logout error:', error);
            }
        });
    });
});

// Check authentication status and update UI
async function checkAuthStatus() {
    try {
        const response = await fetch('api/auth.php?action=check');
        const data = await response.json();
        
        if (data.success) {
            updateUIForLoggedInUser(data.user);
        } else {
            updateUIForGuest();
        }
    } catch (error) {
        console.error('Auth check error:', error);
    }
}

// Update UI for logged-in user
function updateUIForLoggedInUser(user) {
    // const navbar = document.querySelector('.navbar-nav');
    // if (!navbar) return;
    
    // // Remove login/register buttons
    // const loginBtn = navbar.querySelector('[data-bs-target="#loginModal"]');
    // const registerBtn = navbar.querySelector('[data-bs-target="#registerModal"]');
    
    // if (loginBtn) loginBtn.parentElement.remove();
    // if (registerBtn) registerBtn.parentElement.remove();
    
    // // Add user menu
    // const userMenu = document.createElement('li');
    // userMenu.className = 'nav-item dropdown';
    // userMenu.innerHTML = `
    //     <a class="nav-link dropdown-toggle btn btn-light text-primary ms-2 px-3 rounded-pill" href="#" role="button" data-bs-toggle="dropdown">
    //         <i class="bi bi-person-circle"></i> ${user.username}
    //     </a>
    //     <ul class="dropdown-menu">
    //         <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> Profil</a></li>
    //         ${user.role === 'admin' ? '<li><a class="dropdown-item" href="admin/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard Admin</a></li>' : ''}
    //         <li><a class="dropdown-item" href="my-topics.php"><i class="bi bi-chat-dots"></i> Topik Saya</a></li>
    //         <li><hr class="dropdown-divider"></li>
    //         <li><a class="dropdown-item" href="#" data-action="logout"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
    //     </ul>
    // `;
    
    // navbar.appendChild(userMenu);
}

// Update UI for guest
function updateUIForGuest() {
    // UI already shows login/register buttons by default
}

// Show alert message
function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
    alertDiv.style.zIndex = '9999';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}
