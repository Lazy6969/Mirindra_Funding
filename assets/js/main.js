// ============================================
// FONCTIONNALITÉS PRINCIPALES
// ============================================

// Gestion des formulaires
class FormHandler {
    static validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    static showError(input, message) {
        const formGroup = input.closest('.form-group');
        let errorDiv = formGroup.querySelector('.error-message');
        
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.style.color = '#dc3545';
            errorDiv.style.fontSize = '0.875rem';
            errorDiv.style.marginTop = '0.25rem';
            formGroup.appendChild(errorDiv);
        }
        
        errorDiv.textContent = message;
        input.style.borderColor = '#dc3545';
    }
    
    static clearError(input) {
        const formGroup = input.closest('.form-group');
        const errorDiv = formGroup.querySelector('.error-message');
        if (errorDiv) {
            errorDiv.remove();
        }
        input.style.borderColor = '#e0e0e0';
    }
    
    static showGlobalSuccess(message) {
        this.showAlert(message, 'success');
    }
    
    static showGlobalError(message) {
        this.showAlert(message, 'error');
    }
    
    static showAlert(message, type = 'success') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.textContent = message;
        
        const container = document.querySelector('.form-container') || document.body;
        container.insertBefore(alertDiv, container.firstChild);
        
        setTimeout(() => alertDiv.remove(), 5000);
    }
}

// Gestion des projets
class ProjectManager {
    static async createProject(projectData) {
        try {
            const response = await fetch('includes/create-project.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(projectData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                FormHandler.showGlobalSuccess('Projet créé avec succès!');
                setTimeout(() => {
                    window.location.href = 'project-detail.php?id=' + result.project_id;
                }, 1500);
            } else {
                FormHandler.showGlobalError(result.message);
            }
            
            return result;
        } catch (error) {
            console.error('Error:', error);
            FormHandler.showGlobalError('Une erreur est survenue');
        }
    }
    
    static async donate(projectId, donationData) {
        try {
            const response = await fetch('includes/donate.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    project_id: projectId,
                    ...donationData
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                FormHandler.showGlobalSuccess('Merci pour votre don!');
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                FormHandler.showGlobalError(result.message);
            }
            
            return result;
        } catch (error) {
            console.error('Error:', error);
            FormHandler.showGlobalError('Une erreur est survenue');
        }
    }
    
    static calculateProgress(current, target) {
        return Math.min((current / target) * 100, 100);
    }
    
    static formatAmount(amount) {
        return new Intl.NumberFormat('fr-MG', {
            style: 'decimal',
            minimumFractionDigits: 0
        }).format(amount) + ' Ar';
    }
    
    static formatDate(dateString) {
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        return new Date(dateString).toLocaleDateString('fr-FR', options);
    }
    
    static getDaysRemaining(endDate) {
        const end = new Date(endDate);
        const now = new Date();
        const diffTime = end - now;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        return diffDays > 0 ? diffDays : 0;
    }
}

// Filtrage et recherche de projets
class ProjectFilter {
    constructor() {
        this.init();
    }
    
    init() {
        const categoryFilter = document.getElementById('category-filter');
        const searchInput = document.getElementById('search-projects');
        
        if (categoryFilter) {
            categoryFilter.addEventListener('change', (e) => this.filterByCategory(e.target.value));
        }
        
        if (searchInput) {
            searchInput.addEventListener('input', (e) => this.searchProjects(e.target.value));
        }
    }
    
    filterByCategory(category) {
        const projects = document.querySelectorAll('.project-card');
        
        projects.forEach(project => {
            if (!category || category === 'all' || project.dataset.category === category) {
                project.style.display = 'block';
                project.style.animation = 'fadeInUp 0.5s ease';
            } else {
                project.style.display = 'none';
            }
        });
    }
    
    searchProjects(query) {
        const projects = document.querySelectorAll('.project-card');
        const searchTerm = query.toLowerCase();
        
        projects.forEach(project => {
            const title = project.querySelector('.project-title').textContent.toLowerCase();
            const description = project.querySelector('.project-description').textContent.toLowerCase();
            
            if (title.includes(searchTerm) || description.includes(searchTerm)) {
                project.style.display = 'block';
            } else {
                project.style.display = 'none';
            }
        });
    }
}

// Initialisation globale
document.addEventListener('DOMContentLoaded', () => {
    new ProjectFilter();
    
    // Validation des formulaires en temps réel
    document.querySelectorAll('.form-control').forEach(input => {
        // Gestion de l'effacement du placeholder au focus (clic)
        const originalPlaceholder = input.placeholder;
        input.addEventListener('focus', function() {
            this.placeholder = '';
        });
        
        // Restauration du placeholder si le champ est laissé vide
        input.addEventListener('blur', function() {
            if (this.value === '') {
                this.placeholder = originalPlaceholder;
            }
        });

        input.addEventListener('blur', function() {
            if (this.type === 'email' && !FormHandler.validateEmail(this.value)) {
                FormHandler.showError(this, 'Veuillez entrer une adresse email valide');
            } else {
                FormHandler.clearError(this);
            }
        });
    });
    
    // Animation des barres de progression
    document.querySelectorAll('.progress-fill').forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0';
        setTimeout(() => {
            bar.style.width = width;
        }, 300);
    });
    
    // Gestion du bouton de don
    document.querySelectorAll('.donate-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const modal = document.getElementById('payment-modal');
            if (modal) {
                modal.style.display = 'flex';
                modal.dataset.activeProjectId = this.dataset.projectId;
            }
        });
    });

    // Fermeture du modal
    document.querySelector('.close-modal')?.addEventListener('click', () => {
        document.getElementById('payment-modal').style.display = 'none';
    });

    // Gestion de la sélection du paiement
    document.querySelectorAll('.payment-method-card').forEach(card => {
        card.addEventListener('click', async function() {
            const modal = document.getElementById('payment-modal');
            const projectId = modal.dataset.activeProjectId;
            const amount = document.getElementById('modal-amount').value;
            const phone = document.getElementById('modal-phone').value;
            const method = this.dataset.method;
            const statusMsg = document.getElementById('payment-status-msg');

            if (!amount || amount < 100) {
                FormHandler.showAlert('Veuillez entrer un montant valide (min. 100 Ar)', 'error');
                return;
            }
            if (!phone || phone.length < 10) {
                FormHandler.showAlert('Veuillez entrer un numéro de téléphone valide', 'error');
                return;
            }

            // Afficher un état de chargement
            statusMsg.style.display = 'block';
            statusMsg.innerHTML = `<span class="spinner-small"></span> Initialisation de la transaction ${method}...`;
            this.style.opacity = '0.5';
            this.style.pointerEvents = 'none';

            const result = await ProjectManager.donate(projectId, {
                amount: parseFloat(amount),
                donor_name: 'Donateur Mirindra', // Idéalement récupéré d'un champ ou de la session
                donor_email: 'contributeur@mirindra.mg',
                payment_method: method,
                phone_number: phone
            });

            if (result && result.success) {
                statusMsg.style.color = 'green';
                statusMsg.textContent = 'Paiement réussi ! Redirection...';
                setTimeout(() => { modal.style.display = 'none'; window.location.reload(); }, 2000);
            } else {
                statusMsg.style.color = 'red';
                statusMsg.textContent = 'Erreur : ' + (result?.message || 'Transaction échouée');
                this.style.opacity = '1';
                this.style.pointerEvents = 'auto';
            }
        });
    });
});

// Export pour utilisation globale
window.FormHandler = FormHandler;
window.ProjectManager = ProjectManager;
window.ProjectFilter = ProjectFilter;
