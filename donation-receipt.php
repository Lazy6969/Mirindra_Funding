<?php
$pageTitle = "Reçu de Don";
require_once 'includes/header.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    $_SESSION['error_message'] = "Jeton de reçu invalide.";
    redirect('projects.php');
}

$database = new Database();
$pdo = $database->getConnection();

// Récupérer les informations du don, du projet associé et le numéro de téléphone
$stmt = $pdo->prepare("
    SELECT d.*, p.title as project_title, p.school_name, p.image_url as project_image_url, u.first_name as project_owner_first_name, u.last_name as project_owner_last_name
    FROM donations d
    JOIN projects p ON d.project_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE d.receipt_token = :token AND d.payment_status = 'completed'
");
$stmt->execute([':token' => $token]);
$donation = $stmt->fetch();

if (!$donation) {
    $_SESSION['error_message'] = "Reçu introuvable ou non valide.";
    redirect('projects.php');
}

// Vérifier si l'utilisateur connecté est le donateur ou un admin
$isAuthorized = false;
if (isLoggedIn()) {
    if ($_SESSION['user_id'] == $donation['user_id'] || isAdmin()) {
        $isAuthorized = true;
    }
}

// Si le donateur est anonyme et non connecté, ou si l'utilisateur n'est pas autorisé
if (!$isAuthorized && $donation['is_anonymous']) {
    $_SESSION['error_message'] = "Vous n'êtes pas autorisé à voir ce reçu.";
    redirect('projects.php');
}

// Informations du donateur
$donor_display_name = $donation['is_anonymous'] ? 'Donateur Anonyme' : htmlspecialchars($donation['donor_name']);
$donor_display_email = $donation['is_anonymous'] ? 'Anonyme' : htmlspecialchars($donation['donor_email']);
?>

<section style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 4rem 2rem 3rem;">
    <div style="max-width: 800px; margin: 0 auto; text-align: center; color: white;">
        <h1 style="font-size: 2.5rem; margin-bottom: 1rem;">Reçu de Don</h1>
        <p style="font-size: 1.1rem; opacity: 0.9;">Merci pour votre générosité !</p>
    </div>
</section>

<div id="receipt-card" style="width: 750px; max-width: 100%; margin: 3rem auto; padding: 2rem; background: white; border-radius: 12px; box-shadow: var(--shadow-lg); overflow: hidden;">
    <div style="text-align: center; margin-bottom: 2rem; border-bottom: 1px solid #eee; padding-bottom: 1.5rem;">
        <img src="<?php echo SITE_URL; ?>/assets/images/logo4.png" alt="Logo Mirindra Funding" style="width: 80px; margin-bottom: 1rem;">
        <h2 style="color: var(--primary-color); margin: 0;">Reçu de Paiement</h2>
        <p style="font-size: 0.9rem; color: #666;">Date et heure : <?php echo date('d/m/Y à H:i', strtotime($donation['created_at'])); ?></p>
    </div>

    <div style="margin-bottom: 2rem; text-align: center;">
        <h3 style="color: var(--dark-color); margin-bottom: 1rem;">Détails du Don</h3>
        <p><strong>Numéro de transaction:</strong> #<?php echo $donation['id']; ?></p>
        <p><strong>Montant du don:</strong> <span style="font-weight: bold; color: var(--success-color);"><?php echo formatAmount($donation['amount']); ?></span></p>
        <p><strong>Statut du paiement:</strong> <span style="color: <?php echo $donation['payment_status'] === 'completed' ? 'var(--success-color)' : 'var(--danger-color)'; ?>; font-weight: bold;"><?php echo ucfirst($donation['payment_status']); ?></span></p>
    </div>

    <div style="margin-bottom: 2rem; text-align: center;">
        <h3 style="color: var(--dark-color); margin-bottom: 1rem;">Informations du Donateur</h3>
        <p><strong>Nom:</strong> <?php echo $donor_display_name; ?></p>
        <p><strong>Email:</strong> <?php echo $donor_display_email; ?></p>
        <?php if (!empty($donation['phone_number'])): ?>
            <p><strong>Téléphone :</strong> <?php echo htmlspecialchars($donation['phone_number']); ?></p>
        <?php endif; ?>
        <?php if (!empty($donation['message'])): ?>
            <p><strong>Message:</strong> <?php echo htmlspecialchars($donation['message']); ?></p>
        <?php endif; ?>
    </div>

    <div style="margin-bottom: 2rem;">
        <h3 style="color: var(--dark-color); margin-bottom: 1rem; border-bottom: 1px solid #f0f0f0; padding-bottom: 0.5rem;">Projet Soutenu</h3>
        <div style="display: flex; gap: 20px; align-items: flex-start;">
            <div style="flex: 1;"> <!-- Laisser de la place pour l'image -->
                <p style="margin-bottom: 0.75rem;"><strong>Titre du projet:</strong> <a href="<?php echo SITE_URL; ?>/project-detail.php?id=<?php echo $donation['project_id']; ?>" style="color: var(--primary-color); text-decoration: none; font-weight: bold;"><?php echo htmlspecialchars($donation['project_title']); ?></a></p>
                <p style="margin-bottom: 0.75rem;"><strong>École:</strong> <?php echo htmlspecialchars($donation['school_name']); ?></p>
                <p style="margin-bottom: 0.75rem;"><strong>Porteur de projet:</strong> <?php echo htmlspecialchars($donation['project_owner_first_name'] . ' ' . $donation['project_owner_last_name']); ?></p>
            </div>
            <?php if (!empty($donation['project_image_url'])): ?>
                <div style="flex-shrink: 0; width: 200px; height: 120px; overflow: hidden; border-radius: 12px; border: 1px solid #eee; box-shadow: var(--shadow-sm); margin-left: auto;">
                    <img src="<?php echo SITE_URL . '/' . $donation['project_image_url']; ?>" 
                         alt="Image du projet" 
                         style="width: 100%; height: 100%; object-fit: cover;">
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="receipt-actions" style="text-align: center; margin-top: 3rem; display: flex; justify-content: center; gap: 1rem;">
        <button onclick="window.print()" class="btn btn-secondary" style="padding: 0.8rem 2rem; font-size: 1rem; min-width: auto;">
            <i class="fas fa-print"></i> Imprimer
        </button>
        <button id="download-pdf" class="btn btn-primary" style="padding: 0.8rem 2rem; font-size: 1rem; min-width: auto;">
            <i class="fas fa-file-pdf"></i> Télécharger PDF
        </button>
    </div>
    <p style="text-align: center; font-size: 0.8rem; color: #999; margin-top: 1rem;">
        Ceci est un reçu généré automatiquement.
    </p>
</div>

<style>
    @media print {
        /* Masquer les éléments non pertinents pour l'impression/PDF */
        .navbar, .footer, section:first-of-type, .receipt-actions {
            display: none !important; 
        }
        /* Assurer que le contenu ne déborde pas */
        #receipt-card {
            margin: 0 !important;
            box-shadow: none !important;
            width: 100% !important;
            padding: 10mm !important; /* Utiliser des unités physiques pour les marges */
        }
        body { background: none; }
    }
</style>

<!-- Bibliothèque HTML2PDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
document.getElementById('download-pdf').addEventListener('click', function() {
    const element = document.getElementById('receipt-card');
    const actions = document.querySelector('.receipt-actions');
    
    // Masquer temporairement les boutons pour le PDF
    actions.style.visibility = 'hidden';

    const options = {
        margin:       [15, 15, 15, 15], // Marges haut, droite, bas, gauche en mm (A4 est ~210x297mm)
        filename:     'Recu_Don_Mirindra_Funding_<?php echo $donation['id']; ?>.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { 
            scale: 2, // Garder une bonne résolution sans surcharger
            useCORS: true, 
            windowWidth: 800, // Une largeur virtuelle suffisante pour le contenu de 650px
            letterRendering: true
        },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    html2pdf().set(options).from(element).save().then(() => {
        // Réafficher les boutons après la génération
        actions.style.visibility = 'visible';
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>