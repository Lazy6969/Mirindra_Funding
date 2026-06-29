    </main>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Mirindra Funding</h3>
                <p>Plateforme de crowdfunding dédiée aux projets éducatifs innovants. Soutenez l'éducation de demain.</p>
                <div style="margin-top: 1rem;">
                    <a href="#" style="margin-right: 1rem;"><i class="fab fa-facebook fa-lg"></i></a>
                    <a href="#" style="margin-right: 1rem;"><i class="fab fa-twitter fa-lg"></i></a>
                    <a href="#" style="margin-right: 1rem;"><i class="fab fa-linkedin fa-lg"></i></a>
                    <a href="#"><i class="fab fa-instagram fa-lg"></i></a>
                </div>
            </div>
            
            <div class="footer-section">
                <h3>Liens rapides</h3>
                <p><a href="<?php echo SITE_URL; ?>/projects.php">Tous les projets</a></p>
                <p><a href="<?php echo SITE_URL; ?>/create-project.php">Créer un projet</a></p>
                <p><a href="<?php echo SITE_URL; ?>/#how-it-works">Comment ça marche</a></p>
                <p><a href="<?php echo SITE_URL; ?>/faq.php">FAQ</a></p>
            </div>
            
            <div class="footer-section">
                <h3>Catégories</h3>
                <p><a href="<?php echo SITE_URL; ?>/projects.php?category=arts">Arts & Culture</a></p>
                <p><a href="<?php echo SITE_URL; ?>/projects.php?category=science">Sciences & Technologie</a></p>
                <p><a href="<?php echo SITE_URL; ?>/projects.php?category=sports">Sports & Santé</a></p>
                <p><a href="<?php echo SITE_URL; ?>/projects.php?category=environment">Environnement</a></p>
            </div>
            
            <div class="footer-section">
                <h3>Contact</h3>
                <p><i class="fas fa-envelope"></i> mirindraramanana2@gmail.com</p>
                <p><i class="fas fa-phone"></i> +261 38 07 469 87</p>
                <p><i class="fas fa-map-marker-alt"></i> Mahajanga, Madagascar</p>
            </div>
        </div>
        
        <div class="footer-bottom">
    <div class="footer-bottom-text">
        <p>&copy; <?php echo date('Y'); ?> Mirindra Funding. Tous droits réservés. | 
        <a href="#" style="color: #95a5a6;">Mentions légales</a> | 
        <a href="#" style="color: #95a5a6;">Politique de confidentialité</a></p>
    </div>
    
    <!-- Conteneur Profil Circulaire -->
    <div class="footer-profile-wrapper" title="Mon Profil">
        <?php
        $profilePic = SITE_URL . '/assets/images/logo4.png';
        $isLogo = true;

        // Si l'utilisateur est connecté
        if (isset($_SESSION['user_id'])) {
            if (!empty($_SESSION['profile_image'])) {
                $profilePic = SITE_URL . '/' . $_SESSION['profile_image'];
                $isLogo = false;
            } else {
                // "Logo User" (Avatar avec initiales) si pas de photo
                $profilePic = 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['user_name']) . '&background=667eea&color=fff';
                $isLogo = false;
            }
        }
        ?>
        <img src="<?php echo $profilePic; ?>" 
             alt="Profil" 
             class="footer-profile-img <?php echo $isLogo ? 'footer-logo-fallback' : ''; ?>"
             onerror="this.src='https://ui-avatars.com/api/?name=M&background=667eea&color=fff';">
    </div>
    </div>
    </footer>
    
    <!-- Scripts -->
    <script src="<?php echo SITE_URL; ?>/assets/js/animations.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/cursor.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>

    <!-- MODAL DÉCONNEXION (Unique et caché par défaut) -->
    <div id="logoutModal" class="logout-modal-overlay" style="display:none;">
        <div class="logout-modal-box">
            <div class="logout-modal-icon">🔒</div>
            <h3>Confirmation</h3>
            <p>Voulez-vous vraiment vous déconnecter ?</p>
            <div class="logout-modal-actions">
                <button id="btnCancelLogout" class="modal-btn btn-cancel">Annuler</button>
                <button id="btnConfirmLogout" class="modal-btn btn-confirm">Oui, se déconnecter</button>
            </div>
        </div>
    </div>

<style>
.logout-modal-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
    z-index: 999999; display: flex; justify-content: center; align-items: center;
    opacity: 0; pointer-events: none; transition: opacity 0.3s ease;
}
.logout-modal-overlay.active { opacity: 1; pointer-events: auto; }
.logout-modal-box {
    background: #fff; padding: 2rem; border-radius: 16px;
    box-shadow: 0 15px 40px rgba(0,0,0,0.25); text-align: center;
    width: 90%; max-width: 380px; transform: scale(0.85);
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.logout-modal-overlay.active .logout-modal-box { transform: scale(1); }
.logout-modal-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
.logout-modal-box h3 { margin: 0 0 0.5rem; color: #2c3e50; }
.logout-modal-box p { color: #666; margin-bottom: 1.5rem; }
.logout-modal-actions { display: flex; gap: 1rem; justify-content: center; }
.modal-btn {
    padding: 0.75rem 1.5rem; border-radius: 50px; font-weight: 600;
    border: none; cursor: pointer; transition: all 0.2s; font-size: 0.95rem;
}
.btn-cancel { background: #eef2f7; color: #555; }
.btn-cancel:hover { background: #dde4ed; }
.btn-confirm {
    background: linear-gradient(135deg, #667eea, #764ba2); color: white;
    box-shadow: 0 4px 12px rgba(102,126,234,0.4);
}
.btn-confirm:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(102,126,234,0.6); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const triggers = document.querySelectorAll('.logout-trigger');
    const modal = document.getElementById('logoutModal');
    const cancelBtn = document.getElementById('btnCancelLogout');
    const confirmBtn = document.getElementById('btnConfirmLogout');

    if (!modal) return;
    let logoutUrl = '';

    Array.from(triggers).forEach(function(trigger) {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            logoutUrl = this.getAttribute('href');
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('active'), 10);
        });
    });

    const closeModal = () => {
        modal.classList.remove('active');
        setTimeout(() => modal.style.display = 'none', 300);
    };

    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            if (logoutUrl) window.location.href = logoutUrl;
        });
    }
    
    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });
});
</script>
</body>
</html>