<?php
$pageTitle = "Tableau de Bord";
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Sécurité : l'utilisateur doit être connecté pour accéder à cette page
requireLogin();

$database = new Database();
$pdo = $database->getConnection();

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Récupérer les informations complètes de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch() ?: [];

// Si c'est un enseignant ou un étudiant, on récupère ses projets créés
$myProjects = [];
if ($user_type === 'teacher' || $user_type === 'student') {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE user_id = :user_id ORDER BY created_at DESC");
    $stmt->execute([':user_id' => $user_id]);
    $myProjects = $stmt->fetchAll();
}

// Si c'est un donateur, on récupère l'historique de ses contributions
$myDonations = [];
if ($user_type === 'donor') {
    $stmt = $pdo->prepare("
        SELECT d.*, p.title as project_title 
        FROM donations d 
        JOIN projects p ON d.project_id = p.id 
        WHERE d.user_id = :user_id 
        ORDER BY d.created_at DESC
    ");
    $stmt->execute([':user_id' => $user_id]);
    $myDonations = $stmt->fetchAll();
}

// --- SECTION ADMIN ---
$allUsers = [];
$allProjects = [];
$globalStats = [];
$establishmentStats = [];
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] === 'true'; // Détecter les requêtes AJAX
$searchDonationQuery = isset($_GET['search_don']) ? sanitizeInput($_GET['search_don']) : '';
$searchQuery = isset($_GET['search_proj']) ? sanitizeInput($_GET['search_proj']) : '';
$searchUserQuery = isset($_GET['search_user']) ? sanitizeInput($_GET['search_user']) : '';
$searchSchoolQuery = isset($_GET['search_school']) ? sanitizeInput($_GET['search_school']) : '';

if ($user_type === 'admin') {
    // Récupérer les statistiques globales
    $rawGlobalStats = getProjectStats($pdo) ?: [];
    // Assurez-vous que $globalStats est toujours un tableau avec des valeurs par défaut
    $globalStats = [
        'total_projects' => $rawGlobalStats['total_projects'] ?? 0,
        'active_projects' => $rawGlobalStats['active_projects'] ?? 0,
        'total_raised' => $rawGlobalStats['total_raised'] ?? 0,
        'total_donors' => $rawGlobalStats['total_donors'] ?? 0,
    ];

    // Récupérer tous les utilisateurs sauf soi-même avec recherche optionnelle
    $sqlUsers = "SELECT u.*, (SELECT COUNT(*) FROM projects WHERE user_id = u.id) as projects_count 
                FROM users u WHERE u.id != :user_id";
    if (!empty($searchUserQuery)) {
        $sqlUsers .= " AND (first_name LIKE :search OR last_name LIKE :search OR email LIKE :search OR school_name LIKE :search)";
        $stmtUsers = $pdo->prepare($sqlUsers . " ORDER BY created_at DESC");
        $stmtUsers->execute([':user_id' => $user_id, ':search' => "%$searchUserQuery%"]);
        $allUsers = $stmtUsers->fetchAll();
    } else {
        $stmtUsers = $pdo->prepare($sqlUsers . " ORDER BY created_at DESC");
        $stmtUsers->execute([':user_id' => $user_id]);
        $allUsers = $stmtUsers->fetchAll();
    }

    // Récupérer TOUS les projets avec recherche optionnelle
    $sqlProj = "
        SELECT p.*, u.first_name, u.last_name FROM projects p 
        JOIN users u ON p.user_id = u.id";
    
    if (!empty($searchQuery)) {
        $sqlProj .= " WHERE p.title LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search";
        $stmtProj = $pdo->prepare($sqlProj . " ORDER BY p.created_at DESC");
        $stmtProj->execute([':search' => "%$searchQuery%"]);
        $allProjects = $stmtProj->fetchAll();
    } else {
        $allProjects = $pdo->query($sqlProj . " ORDER BY p.created_at DESC")->fetchAll();
    }

    // Récupérer les statistiques par établissement
    $establishmentStats = getEstablishmentStats($pdo, $searchSchoolQuery) ?: [];
}
?>

<?php
// Get friends count for the current user
$friendsCount = 0;
if (isLoggedIn()) { // Only fetch if user is logged in
    try {
        $stmtFriends = $pdo->prepare("SELECT COUNT(*) FROM user_connections WHERE (sender_id = :user_id OR receiver_id = :user_id) AND status = 'accepted'");
        $stmtFriends->execute([':user_id' => $user_id]);
        $friendsCount = $stmtFriends->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error fetching friends count: " . $e->getMessage());
    }
}
?>

<?php
// --- AJAX RESPONSE BLOCK ---
// Si c'est une requête AJAX, on ne renvoie que le contenu du tableau/liste demandé et on arrête l'exécution.
if ($isAjax):
    // Détecter quelle section est demandée par AJAX
    if (isset($_GET['search_don'])):
        // Re-fetch donations for AJAX request
        $searchDonationQuery = isset($_GET['search_don']) ? sanitizeInput($_GET['search_don']) : '';
        $sqlDon = "SELECT d.*, p.title as project_title, u.first_name, u.last_name
                  FROM donations d
                  JOIN projects p ON d.project_id = p.id
                  LEFT JOIN users u ON d.user_id = u.id
                  WHERE d.payment_status = 'completed'";
        
        if (!empty($searchDonationQuery)) {
            $sqlDon .= " AND (p.title LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search OR d.donor_name LIKE :search)";
            $stmtRecentDonations = $pdo->prepare($sqlDon . " ORDER BY d.created_at DESC LIMIT 20");
            $stmtRecentDonations->execute([':search' => "%$searchDonationQuery%"]);
        } else {
            $stmtRecentDonations = $pdo->prepare($sqlDon . " ORDER BY d.created_at DESC LIMIT 20");
            $stmtRecentDonations->execute();
        }
        $recentDonations = $stmtRecentDonations->fetchAll();

        if (count($recentDonations) > 0):
            foreach ($recentDonations as $donation): ?>
                <tr style="border-bottom: 1px solid #f2f2f2; transition: background 0.2s;" onmouseover="this.style.background='#fcfcfc'" onmouseout="this.style.background='transparent'">
                    <td style="padding: 15px 10px; font-weight: 600;"><a href="project-detail.php?id=<?php echo $donation['project_id']; ?>"><?php echo htmlspecialchars($donation['project_title']); ?></a></td>
                    <td style="padding: 15px 10px;">
                        <?php echo $donation['is_anonymous'] ? '<i class="fas fa-user-secret"></i> Anonyme' : htmlspecialchars(($donation['first_name'] ?? '') . ' ' . ($donation['last_name'] ?? $donation['donor_name'])); ?>
                    </td>
                    <td style="padding: 15px 10px; color: var(--success-color); font-weight: 700;"><?php echo formatAmount($donation['amount']); ?></td>
                    <td style="padding: 15px 10px; color: #666;"><?php echo formatDate($donation['created_at']); ?></td>
                    <td style="padding: 15px 10px; text-align: center;">
                        <?php if ($donation['receipt_token']): ?>
                            <a href="donation-receipt.php?token=<?php echo $donation['receipt_token']; ?>" title="Voir le reçu" style="color: var(--primary-color); font-size: 1.1rem;"><i class="fas fa-file-invoice"></i></a>
                        <?php else: ?>
                            <span style="color: #ccc;">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach;
        else: ?>
            <tr><td colspan="5" style="padding: 2rem; text-align: center; color: #666;">Aucun don trouvé.</td></tr>
        <?php endif; // closes if (count($recentDonations) > 0)
    elseif (isset($_GET['search_user'])):
        // Contenu du tableau des utilisateurs
        // $allUsers est déjà récupéré en haut, mais si search_user est défini, il doit être récupéré à nouveau avec la requête de recherche
        $searchUserQuery = isset($_GET['search_user']) ? sanitizeInput($_GET['search_user']) : '';
        $sqlUsers = "SELECT u.*, (SELECT COUNT(*) FROM projects WHERE user_id = u.id) as projects_count 
                    FROM users u WHERE u.id != :user_id";
        if (!empty($searchUserQuery)) {
            $sqlUsers .= " AND (first_name LIKE :search OR last_name LIKE :search OR email LIKE :search OR school_name LIKE :search)";
            $stmtUsers = $pdo->prepare($sqlUsers . " ORDER BY created_at DESC");
            $stmtUsers->execute([':user_id' => $user_id, ':search' => "%$searchUserQuery%"]);
            $allUsers = $stmtUsers->fetchAll();
        } else {
            $stmtUsers = $pdo->prepare($sqlUsers . " ORDER BY created_at DESC");
            $stmtUsers->execute([':user_id' => $user_id]);
            $allUsers = $stmtUsers->fetchAll();
        }

        if (count($allUsers) > 0):
            foreach ($allUsers as $u): ?>
                <tr style="border-bottom: 1px solid #f2f2f2; transition: background 0.2s;" onmouseover="this.style.background='#fcfcfc'" onmouseout="this.style.background='transparent'">
                    <td style="padding: 15px 10px;">
                        <div style="font-weight: 600; color: var(--dark-color);">
                            <a href="user-profile.php?id=<?php echo $u['id']; ?>" style="text-decoration: none; color: inherit; border-bottom: 1px dashed transparent;" onmouseover="this.style.borderBottomColor='var(--primary-color)'" onmouseout="this.style.borderBottomColor='transparent'">
                                <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>
                            </a>
                        </div>
                        <small style="color: #7f8c8d;"><?php echo htmlspecialchars($u['email']); ?></small>
                    </td>
                    <td style="padding: 15px 10px;"><span style="font-size: 0.75rem; font-weight: 700; padding: 4px 10px; background: #ebf0ff; color: #5a67d8; border-radius: 20px; text-transform: uppercase;"><?php echo $u['user_type']; ?></span></td>
                    <td style="padding: 15px 10px;">
                        <?php $info = password_get_info($u['password']); ?>
                        <div style="display: flex; flex-direction: column; gap: 4px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 0.65rem; color: #888; width: 40px; font-weight: 700;">CLAIR:</span>
                                <?php if ($info['algo'] === 0): ?>
                                    <strong style="color: #e53e3e; font-family: monospace; font-size: 0.9rem;"><?php echo htmlspecialchars($u['password']); ?></strong>
                                <?php else: ?>
                                    <span style="color: #a0aec0; font-size: 0.7rem; font-style: italic;">Indisponible (Haché)</span>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 0.65rem; color: #888; width: 40px; font-weight: 700;">HASH:</span>
                                <?php if ($info['algo'] !== 0): ?>
                                    <code title="<?php echo htmlspecialchars($u['password']); ?>" style="font-size: 0.65rem; background: #f0fff4; border: 1px solid #c6f6d5; padding: 2px 4px; border-radius: 4px; color: #2f855a; max-width: 130px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo htmlspecialchars($u['password']); ?>
                                    </code>
                                <?php else: ?>
                                    <span style="color: #a0aec0; font-size: 0.7rem; font-style: italic;">Non haché</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td style="padding: 15px 10px;"><?php echo $u['is_active'] ? '<span style="display:flex; align-items:center; gap:5px; color:#38a169;"><i class="fas fa-check-circle"></i> Actif</span>' : '<span style="display:flex; align-items:center; gap:5px; color:#e53e3e;"><i class="fas fa-ban"></i> Banni</span>'; ?></td>
                    <td style="padding: 15px 10px; font-weight: 600; color: #4a5568;"><?php echo $u['projects_count']; ?></td>
                    <td style="padding: 15px 10px; text-align: right;">
                        <div style="display: flex; gap: 15px; justify-content: flex-end; align-items: center;">
                            <a href="projects.php?user_id=<?php echo $u['id']; ?>&status=all" title="Voir les projets" style="color: var(--primary-color); font-size: 1.1rem;"><i class="fas fa-folder-open"></i></a>
                            <a href="edit-user.php?id=<?php echo $u['id']; ?>" title="Modifier" style="color: #3182ce; font-size: 1.1rem;"><i class="fas fa-edit"></i></a>
                            <a href="admin-actions.php?action=toggle_user&id=<?php echo $u['id']; ?>" title="Changer statut" style="color: #718096; font-size: 1.1rem;"><i class="fas fa-power-off"></i></a>
                            <a href="admin-actions.php?action=delete_user&id=<?php echo $u['id']; ?>" onclick="return confirm('Supprimer définitivement cet utilisateur ?')" title="Supprimer" style="color: var(--danger-color); font-size: 1.1rem;"><i class="fas fa-trash-alt"></i></a>
                        </div>
                    </td>
                </tr>
            <?php endforeach;
        else: ?>
            <tr><td colspan="6" style="padding: 2rem; text-align: center; color: #666;">Aucun utilisateur trouvé.</td></tr>
        <?php endif; // closes if (count($allUsers) > 0)
    elseif (isset($_GET['search_school'])):
        // Contenu du tableau des établissements
        // $establishmentStats est déjà récupéré en haut, mais si search_school est défini, il doit être récupéré à nouveau avec la requête de recherche
        $searchSchoolQuery = isset($_GET['search_school']) ? sanitizeInput($_GET['search_school']) : '';
        $establishmentStats = getEstablishmentStats($pdo, $searchSchoolQuery) ?: [];

        if (count($establishmentStats) > 0):
            foreach ($establishmentStats as $stat): 
                $school_perc = ($stat['total_target'] > 0) ? ($stat['total_raised'] / $stat['total_target']) * 100 : 0;
            ?>
                <tr style="border-bottom: 1px solid #f2f2f2; transition: background 0.2s;" onmouseover="this.style.background='#fcfcfc'" onmouseout="this.style.background='transparent'">
                    <td style="padding: 15px 10px; font-weight: 600;"><?php echo htmlspecialchars($stat['school_name']); ?></td>
                    <td style="padding: 15px 10px;"><?php echo $stat['total_projects']; ?> <span style="color:#999">(<?php echo $stat['active_projects']; ?>)</span></td>
                    <td style="padding: 15px 10px;"><?php echo formatAmount($stat['total_raised']); ?> / <?php echo formatAmount($stat['total_target']); ?></td>
                    <td style="padding: 15px 10px;">
                        <span style="font-weight: 800; color: var(--primary-color);"><?php echo round($school_perc, 1); ?>%</span>
                    </td>
                    <td style="padding: 15px 10px;"><?php echo $stat['total_donors']; ?></td>
                    <td style="padding: 15px 10px;"><span style="color: var(--success-color); font-weight: 800;"><?php echo $stat['funded_projects']; ?></span></td>
                </tr>
            <?php endforeach;
        else: ?>
            <tr><td colspan="6" style="padding: 2rem; text-align: center; color: #666;">Aucun établissement trouvé.</td></tr>
        <?php endif; // closes if (count($establishmentStats) > 0)
    elseif (isset($_GET['search_proj'])):
        // Contenu de la liste des projets
        // $allProjects est déjà récupéré en haut, mais si search_proj est défini, il doit être récupéré à nouveau avec la requête de recherche
        $searchQuery = isset($_GET['search_proj']) ? sanitizeInput($_GET['search_proj']) : '';
        $sqlProj = "
            SELECT p.*, u.first_name, u.last_name FROM projects p 
            JOIN users u ON p.user_id = u.id";
        
        if (!empty($searchQuery)) {
            $sqlProj .= " WHERE p.title LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search";
            $stmtProj = $pdo->prepare($sqlProj . " ORDER BY p.created_at DESC");
            $stmtProj->execute([':search' => "%$searchQuery%"]);
            $allProjects = $stmtProj->fetchAll();
        } else {
            $allProjects = $pdo->query($sqlProj . " ORDER BY p.created_at DESC")->fetchAll();
        }

        if (count($allProjects) > 0):
            foreach ($allProjects as $proj): ?>
                <?php 
                    $statusColor = $proj['status'] === 'active' ? 'var(--success-color)' : ($proj['status'] === 'pending' ? '#f39c12' : '#e74c3c');
                    $isPending = ($proj['status'] === 'pending');
                ?>
                <div style="padding: 1.2rem; border: 1px solid #eee; background: <?php echo $isPending ? '#fffcf0' : '#fff'; ?>; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; border-left: 5px solid <?php echo $statusColor; ?>;">
                    <div>
                        <h4 style="margin: 0; color: var(--dark-color);">
                            <?php echo htmlspecialchars($proj['title']); ?>
                            <?php if($isPending): ?>
                                <span style="font-size: 0.7rem; background: #ffeeba; color: #856404; padding: 2px 6px; border-radius: 4px; margin-left: 5px;">EN ATTENTE</span>
                            <?php endif; ?>
                        </h4>
                        <small style="color: #666;">Par : <strong><?php echo htmlspecialchars($proj['first_name'] . ' ' . $proj['last_name']); ?></strong></small>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="project-detail.php?id=<?php echo $proj['id']; ?>" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; min-width: auto;">Voir</a>
                        
                        <?php if ($isPending): ?>
                            <a href="admin-actions.php?action=approve_project&id=<?php echo $proj['id']; ?>" class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; min-width: auto; background: var(--success-color);">Approuver</a>
                            <a href="admin-actions.php?action=refuse_project&id=<?php echo $proj['id']; ?>" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; min-width: auto; color: var(--danger-color); border-color: var(--danger-color);">Refuser</a>
                        <?php endif; ?>

                        <a href="admin-actions.php?action=delete_project&id=<?php echo $proj['id']; ?>" 
                           onclick="return confirm('Supprimer ce projet ?')"
                           style="color: var(--danger-color); display: flex; align-items: center; padding: 0 10px;">
                           <i class="fas fa-trash-alt"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach;
        else: ?>
            <p style="color: #666; margin-bottom: 3rem;">Aucun projet trouvé.</p>
        <?php endif; // closes if (count($allProjects) > 0)
    endif; // Closes the if (isset($_GET['search_don'])) / elseif chain
    exit; // Stop execution after sending AJAX fragment
endif;
// Maintenant qu'on a vérifié l'AJAX, on peut inclure le header pour l'affichage normal
require_once 'includes/header.php';
// --- END AJAX RESPONSE BLOCK ---
?>

<style>
    /* Style pour le tableau des dons */
    .donations-table th, .donations-table td {
        white-space: nowrap; /* Empêche le texte de s'enrouler dans les cellules */
        padding: 12px 10px;
        border-bottom: 1px solid #f2f2f2;
    }

</style>

<section style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 4rem 2rem 3rem;">
    <div style="max-width: 1200px; margin: 0 auto; color: white; display: flex; align-items: center; gap: 2rem;">
        <div style="width: 100px; height: 100px; border-radius: 50%; overflow: hidden; border: 4px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.1);">
            <img src="<?php echo !empty($user['profile_image']) ? SITE_URL . '/' . $user['profile_image'] : 'https://ui-avatars.com/api/?name='.urlencode($user['first_name'].'+'.$user['last_name']).'&background=fff&color=667eea'; ?>" 
                 style="width: 100%; height: 100%; object-fit: cover;">
        </div>
        <div>
            <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem;">Bienvenue, <?php echo htmlspecialchars($user['first_name']); ?> !</h1>
            <p style="font-size: 1.1rem; opacity: 0.9;">Espace personnel - <?php echo ucfirst($user_type); ?></p>
        </div>
    </div>
</section>




<div style="max-width: 1200px; margin: 3rem auto; padding: 0 2rem;">

    <style>
        /* Style personnalisé pour les barres de défilement dans le dashboard */
        #admin-projects-content::-webkit-scrollbar,
        #admin-schools-content::-webkit-scrollbar,
        #admin-donations-content::-webkit-scrollbar,
        #admin-users-content::-webkit-scrollbar {
            width: 8px;
        }
        #admin-projects-content::-webkit-scrollbar-track,
        #admin-schools-content::-webkit-scrollbar-track,
        #admin-donations-content::-webkit-scrollbar-track,
        #admin-users-content::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        #admin-projects-content::-webkit-scrollbar-thumb,
        #admin-schools-content::-webkit-scrollbar-thumb,
        #admin-donations-content::-webkit-scrollbar-thumb,
        #admin-users-content::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        #admin-projects-content::-webkit-scrollbar-thumb:hover,
        #admin-schools-content::-webkit-scrollbar-thumb:hover,
        #admin-donations-content::-webkit-scrollbar-thumb:hover,
        #admin-users-content::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success" style="margin-bottom: 2rem;">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error" style="margin-bottom: 2rem;">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <?php if ($user_type === 'admin'): ?>
        <!-- Statistiques Globales de la Plateforme (Admin uniquement) -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
            <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow-sm); border-left: 5px solid var(--primary-color);">
                <div style="color: #666; font-size: 0.8rem; text-transform: uppercase; font-weight: 700; margin-bottom: 5px;">Projets</div>
                <div style="font-size: 1.8rem; font-weight: 800; color: var(--dark-color);"><?php echo $globalStats['total_projects']; ?></div>
                <div style="font-size: 0.85rem; color: #2ecc71;"><i class="fas fa-check-circle"></i> <?php echo $globalStats['active_projects']; ?> actifs</div>
            </div>
            <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow-sm); border-left: 5px solid var(--success-color);">
                <div style="color: #666; font-size: 0.8rem; text-transform: uppercase; font-weight: 700; margin-bottom: 5px;">Total Collecté</div>
                <div style="font-size: 1.8rem; font-weight: 800; color: var(--dark-color);"><?php echo number_format($globalStats['total_raised'], 0, ',', ' '); ?> <small style="font-size: 0.9rem;">Ar</small></div>
                <div style="font-size: 0.85rem; color: #999;">Impact éducation</div>
            </div>
            <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow-sm); border-left: 5px solid #f39c12;">
                <div style="color: #666; font-size: 0.8rem; text-transform: uppercase; font-weight: 700; margin-bottom: 5px;">Donateurs</div>
                <div style="font-size: 1.8rem; font-weight: 800; color: var(--dark-color);"><?php echo $globalStats['total_donors']; ?></div>
                <div style="font-size: 0.85rem; color: #999;">Générosité partagée</div>
            </div>
            <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow-sm); border-left: 5px solid #9b59b6;">
                <div style="color: #666; font-size: 0.8rem; text-transform: uppercase; font-weight: 700; margin-bottom: 5px;">Établissements</div>
                <div style="font-size: 1.8rem; font-weight: 800; color: var(--dark-color);"><?php echo count($establishmentStats); ?></div>
                <div style="font-size: 0.85rem; color: #999;">Écoles partenaires</div>
            </div>
        </div>
    <?php endif; ?>

    <?php // Cette partie du code s'exécute uniquement si ce n'est PAS une requête AJAX (car le script aurait déjà exit() plus haut)
          // Elle affiche la page complète.
    ?>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
        
        <!-- Colonne Latérale : Profil et Actions -->
        <div style="display: flex; flex-direction: column; gap: 2rem;">
            <div style="background: white; padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-md);">
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <?php $unreadDash = getUnreadMessagesCount($pdo, $_SESSION['user_id']); ?>
                    <a href="conversations.php" class="btn btn-secondary" style="width: 100%; display: flex; justify-content: center; position: relative;">
                        <i class="fas fa-envelope" style="margin-right: 8px;"></i> Messagerie
                        <?php if($unreadDash > 0): ?>
                            <span style="position: absolute; top: -5px; right: -5px; background: var(--danger-color); color: white; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; border: 2px solid white;"><?php echo $unreadDash; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="projects.php" class="btn btn-secondary" style="width: 100%; display: flex; justify-content: center;">
                        <i class="fas fa-search" style="margin-right: 8px;"></i> Explorer les projets
                    </a>
                    <?php if ($user_type === 'teacher' || $user_type === 'student'): ?>
                        <a href="create-project.php" class="btn btn-primary" style="width: 100%; display: flex; justify-content: center;">
                            <i class="fas fa-plus" style="margin-right: 8px;"></i> Lancer un projet
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($user_type === 'admin'): ?>
                <!-- GESTION GLOBALE DES PROJETS -->
                <div style="background: white; padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-md);">
                    <h3 style="margin-bottom: 1.5rem; color: var(--dark-color); display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #f8f9fa; padding-bottom: 1rem;">
                        <span style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-tasks" style="color: var(--primary-color);"></i> Gestion des projets
                        </span>
                    </h3>

                    <!-- Barre de recherche interne au Dashboard -->
                    <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; align-items: stretch; max-width: 50rem; margin-left: auto; margin-right: auto;">
                        <input type="text" name="search_proj" id="search-projects-input" placeholder="Chercher un projet ou un auteur..." 
                               value="<?php echo htmlspecialchars($searchQuery); ?>"
                               class="form-control" style="flex: 1;">
                        <button type="button" class="btn btn-secondary" style="min-width: auto; padding: 0.5rem 1rem;" onclick="resetLiveSearch('search-projects-input')">
                            <i class="fas fa-undo"></i> Tout
                        </button>
                    </div>
                    <div id="admin-projects-list" style="max-height: 31.25rem; overflow-y: auto; padding-right: 0.625rem;">
                    <?php if (count($allProjects) > 0): ?>
                        <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 3rem;">
                            <?php foreach ($allProjects as $proj): ?>
                                <?php 
                                    $statusColor = $proj['status'] === 'active' ? 'var(--success-color)' : ($proj['status'] === 'pending' ? '#f39c12' : '#e74c3c');
                                    $isPending = ($proj['status'] === 'pending');
                                ?>
                                <div style="padding: 1.2rem; border: 1px solid #eee; background: <?php echo $isPending ? '#fffcf0' : '#fff'; ?>; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; border-left: 5px solid <?php echo $statusColor; ?>;">
                                    <div>
                                        <h4 style="margin: 0; color: var(--dark-color);">
                                            <?php echo htmlspecialchars($proj['title']); ?>
                                            <?php if($isPending): ?>
                                                <span style="font-size: 0.7rem; background: #ffeeba; color: #856404; padding: 2px 6px; border-radius: 4px; margin-left: 5px;">EN ATTENTE</span>
                                            <?php endif; ?>
                                        </h4>
                                        <small style="color: #666;">Par : <strong><?php echo htmlspecialchars($proj['first_name'] . ' ' . $proj['last_name']); ?></strong></small>
                                    </div>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <a href="project-detail.php?id=<?php echo $proj['id']; ?>" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; min-width: auto;">Voir</a>
                                        
                                        <?php if ($isPending): ?>
                                            <a href="admin-actions.php?action=approve_project&id=<?php echo $proj['id']; ?>" class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; min-width: auto; background: var(--success-color);">Approuver</a>
                                            <a href="admin-actions.php?action=refuse_project&id=<?php echo $proj['id']; ?>" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; min-width: auto; color: var(--danger-color); border-color: var(--danger-color);">Refuser</a>
                                        <?php endif; ?>

                                        <a href="admin-actions.php?action=delete_project&id=<?php echo $proj['id']; ?>" 
                                           onclick="return confirm('Supprimer ce projet ?')"
                                           style="color: var(--danger-color); display: flex; align-items: center; padding: 0 10px;">
                                           <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #666; margin-bottom: 3rem;">Aucun projet trouvé.</p>
                    <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Colonne Centrale : Activité -->
        <div style="background: white; padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-md);">
            <!-- SECTION PROFIL (Commune à tous les utilisateurs) -->
            <h3 style="margin-bottom: 1.5rem; color: var(--dark-color); display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-id-card" style="color: var(--primary-color);"></i> Mes Informations
            </h3>
            
            <div style="display: flex; align-items: center; gap: 1.5rem; margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid #f0f0f0; flex-wrap: wrap;">
                <div style="width: 100px; height: 100px; border-radius: 50%; overflow: hidden; border: 3px solid var(--primary-color); flex-shrink: 0; box-shadow: var(--shadow-sm);">
                    <img src="<?php echo !empty($user['profile_image']) ? SITE_URL . '/' . $user['profile_image'] : 'https://ui-avatars.com/api/?name='.urlencode($user['first_name'].'+'.$user['last_name']).'&background=667eea&color=fff'; ?>" 
                         alt="Profil" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
                <div style="flex-grow: 1;">
                    <h2 style="margin: 0 0 5px; color: var(--dark-color);"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                    <p style="margin: 0; font-size: 0.95rem; color: #666;">
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?> | 
                        <i class="fas fa-user-tag"></i> <strong><?php echo ucfirst($user_type); ?></strong>
                    </p>
                    <?php if (!empty($user['phone'])): ?>
                        <p style="margin: 5px 0 0; font-size: 0.9rem; color: #666;"><i class="fas fa-phone"></i> Tel: <?php echo htmlspecialchars($user['phone']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($user['school_name'])): ?>
                        <p style="margin: 5px 0 0; font-size: 0.9rem; color: #666;"><i class="fas fa-school"></i> <?php echo htmlspecialchars($user['school_name']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistiques et Informations Complémentaires -->
            <div style="display: flex; justify-content: space-around; margin-bottom: 2rem; background: #f8f9fa; padding: 1.5rem; border-radius: 12px; text-align: center;">
                <div>
                    <p style="margin: 0; font-weight: 700; font-size: 1.3rem; color: var(--primary-color);"><?php echo $friendsCount; ?></p>
                    <small style="color: #7f8c8d; font-weight: 600; text-transform: uppercase; font-size: 0.7rem;">Mes Amis</small>
                </div>
                <div style="border-left: 1px solid #ddd; border-right: 1px solid #ddd; padding: 0 1rem;">
                    <p style="margin: 0; font-weight: 700; font-size: 1.3rem; color: var(--success-color);">
                        <?php 
                            if ($user_type === 'admin') echo $globalStats['total_projects'];
                            elseif ($user_type === 'donor') echo count($myDonations);
                            else echo count($myProjects);
                        ?>
                    </p>
                    <small style="color: #7f8c8d; font-weight: 600; text-transform: uppercase; font-size: 0.7rem;">
                        <?php echo ($user_type === 'donor') ? 'Dons' : 'Projets'; ?>
                    </small>
                </div>
                <div>
                    <p style="margin: 0; font-weight: 700; font-size: 1.3rem; color: #34495e;"><?php echo date('M Y', strtotime($user['created_at'])); ?></p>
                    <small style="color: #7f8c8d; font-weight: 600; text-transform: uppercase; font-size: 0.7rem;">Inscrit en</small>
                </div>
            </div>

            <div style="text-align: center; margin-bottom: 2.5rem;">
                <a href="profile-settings.php" class="btn btn-secondary" style="padding: 0.6rem 2rem; border-radius: 50px; font-size: 0.9rem;">
                    <i class="fas fa-user-edit" style="margin-right: 8px;"></i> Modifier mon profil
                </a>
                <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px dashed #eee; color: #999; font-size: 0.85rem;">
                    <p><i class="fas fa-shield-alt"></i> Espace personnel sécurisé • Mirindra Funding <?php echo date('Y'); ?></p>
                    <p style="margin-top: 5px;">Dernière activité : <?php echo date('d/m/Y à H:i'); ?></p>
                </div>
            </div>

            <!-- SECTION ACTIVITÉ SPÉCIFIQUE (Affiche la suite selon le type) -->
            <?php if ($user_type === 'teacher' || $user_type === 'student'): ?>
                <h3 style="margin-bottom: 1.5rem; color: var(--dark-color); border-top: 1px solid #eee; padding-top: 2rem;">Mes Projets</h3>
                <?php if (count($myProjects) > 0): ?>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($myProjects as $proj): ?>
                            <div style="padding: 1rem; border: 1px solid #eee; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <h4 style="margin: 0; color: var(--dark-color);"><?php echo htmlspecialchars($proj['title']); ?></h4>
                                    <small style="color: #666;">Statut : <span style="color: var(--primary-color);"><?php echo ucfirst($proj['status']); ?></span></small>
                                </div>
                                <a href="project-detail.php?id=<?php echo $proj['id']; ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem; min-width: auto;">Détails</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #666; text-align: center; padding: 2rem;">Vous n'avez aucun projet pour le moment.</p>
                <?php endif; ?>
            <?php else: ?>
                <h3 style="margin-bottom: 1.5rem; color: var(--dark-color);">Mes Dons</h3>
                <?php if (count($myDonations) > 0): ?>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($myDonations as $don): ?>
                            <div style="padding: 1rem; border: 1px solid #eee; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <h4 style="margin: 0; color: var(--dark-color);"><?php echo htmlspecialchars($don['project_title']); ?></h4>
                                    <small style="color: #666;"><?php echo formatDate($don['created_at']); ?></small>
                                </div>
                                <div style="font-weight: bold; color: var(--success-color);">
                                    <?php echo formatAmount($don['amount']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #666; text-align: center; padding: 2rem;">Vous n'avez pas encore effectué de don.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>

    <?php if ($user_type === 'admin'): ?>
        <!-- STATISTIQUES PAR ÉTABLISSEMENT - Affichage Pleine Largeur -->
        <div style="background: white; padding: 2.5rem; border-radius: 12px; box-shadow: var(--shadow-md); margin-top: 2.5rem;">
            <h3 style="margin-bottom: 2rem; color: var(--dark-color); display: flex; align-items: center; gap: 12px; font-size: 1.5rem; border-bottom: 2px solid #f8f9fa; padding-bottom: 1rem;">
                <span style="display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-graduation-cap" style="color: var(--primary-color);"></i> Statistiques par Établissement
                </span>
            </h3>
            
            <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; align-items: stretch; max-width: 50rem; margin-left: auto; margin-right: auto;">
                <input type="text" name="search_school" id="search-schools-input" placeholder="Chercher un établissement..." 
                       value="<?php echo htmlspecialchars($searchSchoolQuery); ?>"
                       class="form-control" style="flex: 1;">
                <button type="button" class="btn btn-secondary" style="min-width: auto; padding: 0.5rem 1rem;" onclick="resetLiveSearch('search-schools-input')">
                    <i class="fas fa-undo"></i> Tout afficher
                </button>
            </div>

            <div id="admin-schools-content" style="max-height: 25rem; overflow-y: auto;">
                <table class="admin-table">
                    <thead>
                        <tr style="text-align: left; border-bottom: 2px solid #eee; color: #888;">
                            <th style="padding: 12px 10px; position: sticky; top: 0; background: white; z-index: 10;">Établissement</th>
                            <th style="padding: 12px 10px; position: sticky; top: 0; background: white; z-index: 10;">Projets (Actifs)</th>
                            <th style="padding: 12px 10px; position: sticky; top: 0; background: white; z-index: 10;">Collecté / Objectif</th>
                            <th style="padding: 12px 10px; position: sticky; top: 0; background: white; z-index: 10;">%</th>
                            <th style="padding: 12px 10px; position: sticky; top: 0; background: white; z-index: 10;">Donateurs</th>
                            <th style="padding: 12px 10px; position: sticky; top: 0; background: white; z-index: 10;">Financés</th> 
                        </tr>
                    </thead>
                    <tbody id="admin-schools-table-body">
                        <?php foreach ($establishmentStats as $stat): 
                            $school_perc = ($stat['total_target'] > 0) ? ($stat['total_raised'] / $stat['total_target']) * 100 : 0;
                        ?>
                            <tr style="border-bottom: 1px solid #f2f2f2; transition: background 0.2s;" onmouseover="this.style.background='#fcfcfc'" onmouseout="this.style.background='transparent'">
                                <td style="padding: 15px 10px; font-weight: 600;"><?php echo htmlspecialchars($stat['school_name']); ?></td>
                                <td style="padding: 15px 10px;"><?php echo $stat['total_projects']; ?> <span style="color:#999">(<?php echo $stat['active_projects']; ?>)</span></td>
                                <td style="padding: 15px 10px;"><?php echo formatAmount($stat['total_raised']); ?> / <?php echo formatAmount($stat['total_target']); ?></td>
                                <td style="padding: 15px 10px;">
                                    <span style="font-weight: 800; color: var(--primary-color);"><?php echo round($school_perc, 1); ?>%</span>
                                </td>
                                <td style="padding: 15px 10px;"><?php echo $stat['total_donors']; ?></td>
                                <td style="padding: 15px 10px;"><span style="color: var(--success-color); font-weight: 800;"><?php echo $stat['funded_projects']; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- HISTORIQUE DES DONS - Affichage Pleine Largeur -->
        <?php // Cette section est toujours affichée dans la page complète, son contenu est mis à jour par AJAX ?>
        <div style="background: white; padding: 2.5rem; border-radius: 12px; box-shadow: var(--shadow-md); margin-top: 2.5rem;">
            <h3 style="margin-bottom: 2rem; color: var(--dark-color); display: flex; align-items: center; gap: 12px; font-size: 1.5rem; border-bottom: 2px solid #f8f9fa; padding-bottom: 1rem;">
                <span style="display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-hand-holding-usd" style="color: var(--success-color);"></i> Historique des dons récents
                </span>
            </h3>
            
            <!-- Barre de recherche pour les dons -->
            <div style="display: flex; gap: 10px; margin-bottom: 1.5rem; align-items: stretch; max-width: 800px; margin-left: auto; margin-right: auto;">
                <input type="text" name="search_don" id="search-donations-input" placeholder="Chercher par projet ou donateur..." 
                       value="<?php echo htmlspecialchars($searchDonationQuery); ?>"
                       class="form-control" style="flex: 1;">
                <button type="button" class="btn btn-secondary" style="min-width: auto; padding: 0.5rem 1rem;" onclick="resetLiveSearch('search-donations-input')">
                    <i class="fas fa-undo"></i> Tout afficher
                </button>
            </div>

            <?php
            $sqlDon = "SELECT d.*, p.title as project_title, u.first_name, u.last_name
                      FROM donations d
                      JOIN projects p ON d.project_id = p.id
                      LEFT JOIN users u ON d.user_id = u.id
                      WHERE d.payment_status = 'completed'";
            
            if (!empty($searchDonationQuery)) {
                $sqlDon .= " AND (p.title LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search OR d.donor_name LIKE :search)";
                $stmtRecentDonations = $pdo->prepare($sqlDon . " ORDER BY d.created_at DESC LIMIT 20");
                $stmtRecentDonations->execute([':search' => "%$searchDonationQuery%"]);
            } else {
                $stmtRecentDonations = $pdo->prepare($sqlDon . " ORDER BY d.created_at DESC LIMIT 20");
                $stmtRecentDonations->execute();
            }
            $recentDonations = $stmtRecentDonations->fetchAll();
            ?>
            <div id="admin-donations-content" style="max-height: 25rem; overflow-y: auto;">
                <?php if (count($recentDonations) > 0): ?>
                    <table class="donations-table admin-table">
                        <thead>
                            <tr style="text-align: left; border-bottom: 2px solid #eee; color: #888;">
                                <th style="padding: 12px 10px; position: sticky; top: 0; background: white; z-index: 10;">Projet</th>
                                <th style="padding: 12px 10px; position: sticky; top: 0; background: white; z-index: 10;">Donateur</th>
                                <th style="padding: 12px 10px; position: sticky; top: 0; background: white; z-index: 10;">Montant</th>
                                <th style="padding: 12px 10px; position: sticky; top: 0; background: white; z-index: 10;">Date</th>
                                <th style="padding: 12px 10px; position: sticky; top: 0; background: white; z-index: 10; text-align: center;">Reçu</th>
                            </tr> 
                        </thead>
                        <tbody id="admin-donations-table-body">
                            <?php foreach ($recentDonations as $donation): ?>
                                <tr style="border-bottom: 1px solid #f2f2f2; transition: background 0.2s;" onmouseover="this.style.background='#fcfcfc'" onmouseout="this.style.background='transparent'">
                                    <td style="padding: 15px 10px; font-weight: 600;"><a href="project-detail.php?id=<?php echo $donation['project_id']; ?>"><?php echo htmlspecialchars($donation['project_title']); ?></a></td>
                                    <td style="padding: 15px 10px;">
                                        <?php echo $donation['is_anonymous'] ? '<i class="fas fa-user-secret"></i> Anonyme' : htmlspecialchars(($donation['first_name'] ?? '') . ' ' . ($donation['last_name'] ?? $donation['donor_name'])); ?>
                                    </td>
                                    <td style="padding: 15px 10px; color: var(--success-color); font-weight: 700;"><?php echo formatAmount($donation['amount']); ?></td>
                                    <td style="padding: 15px 10px; color: #666;"><?php echo formatDate($donation['created_at']); ?></td>
                                    <td style="padding: 15px 10px; text-align: center;">
                                        <?php if ($donation['receipt_token']): ?>
                                            <a href="donation-receipt.php?token=<?php echo $donation['receipt_token']; ?>" title="Voir le reçu" style="color: var(--primary-color); font-size: 1.1rem;"><i class="fas fa-file-invoice"></i></a>
                                        <?php else: ?>
                                            <span style="color: #ccc;">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #666; padding: 2rem; text-align: center;">Aucun don trouvé.</p>
                <?php endif; ?>
            </div> 
        </div>

        <!-- GESTION DES UTILISATEURS - Affichage Pleine Largeur en bas -->
        <div style="background: white; padding: 2.5rem; border-radius: 12px; box-shadow: var(--shadow-md); margin-top: 2.5rem;">
            <h3 style="margin-bottom: 2rem; color: var(--dark-color); display: flex; align-items: center; gap: 12px; font-size: 1.5rem; border-bottom: 2px solid #f8f9fa; padding-bottom: 1rem;">
                <span style="display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-users" style="color: var(--primary-color);"></i> Gestion globale des utilisateurs
                </span>
            </h3>
            <!-- Barre de recherche pour les utilisateurs -->
            <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; align-items: stretch; max-width: 50rem; margin-left: auto; margin-right: auto;">
                <input type="text" name="search_user" id="search-users-input" placeholder="Chercher un utilisateur (nom, email, école)..." 
                       value="<?php echo htmlspecialchars($searchUserQuery); ?>"
                       class="form-control" style="flex: 1;">
                <button type="button" class="btn btn-secondary" style="min-width: auto; padding: 0.5rem 1rem;" onclick="resetLiveSearch('search-users-input')">
                    <i class="fas fa-undo"></i> Tout afficher
                </button>
            </div>
            
            <div id="admin-users-content" style="max-height: 37.5rem; overflow-y: auto;">
            <div style="overflow-x: auto; width: 100%;">
                <table class="admin-table">
                    <thead>
                        <tr style="text-align: left; border-bottom: 2px solid #eee; color: #888;">
                            <th style="padding: 12px 10px; position: sticky; top: 0; background: white; z-index: 10;">Utilisateur</th>
                            <th style="padding: 12px 10px; position: sticky; top: 0; background: white; z-index: 10;">Rôle</th>
                            <th style="padding: 12px 10px; position: sticky; top: 0; background: white; z-index: 10;">Mot de passe / Hash</th>
                            <th style="padding: 12px 10px; position: sticky; top: 0; background: white; z-index: 10;">Statut</th>
                            <th style="padding: 12px 10px; position: sticky; top: 0; background: white; z-index: 10;">Projets</th>
                            <th style="padding: 12px 10px; text-align: right; position: sticky; top: 0; background: white; z-index: 10;">Actions</th> 
                        </tr>
                    </thead>
                    <tbody id="admin-users-table-body">
                        <?php foreach ($allUsers as $u): ?>
                            <tr style="border-bottom: 1px solid #f2f2f2; transition: background 0.2s;" onmouseover="this.style.background='#fcfcfc'" onmouseout="this.style.background='transparent'">
                                <td style="padding: 15px 10px;">
                                    <div style="font-weight: 600; color: var(--dark-color);">
                                        <a href="user-profile.php?id=<?php echo $u['id']; ?>" style="text-decoration: none; color: inherit; border-bottom: 1px dashed transparent;" onmouseover="this.style.borderBottomColor='var(--primary-color)'" onmouseout="this.style.borderBottomColor='transparent'">
                                            <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>
                                        </a>
                                    </div>
                                    <small style="color: #7f8c8d;"><?php echo htmlspecialchars($u['email']); ?></small>
                                </td>
                                <td style="padding: 15px 10px;"><span style="font-size: 0.75rem; font-weight: 700; padding: 4px 10px; background: #ebf0ff; color: #5a67d8; border-radius: 20px; text-transform: uppercase;"><?php echo $u['user_type']; ?></span></td>
                                <td style="padding: 15px 10px;">
                                    <?php $info = password_get_info($u['password']); ?>
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span style="font-size: 0.65rem; color: #888; width: 40px; font-weight: 700;">CLAIR:</span>
                                            <?php if ($info['algo'] === 0): ?>
                                                <strong style="color: #e53e3e; font-family: monospace; font-size: 0.9rem;"><?php echo htmlspecialchars($u['password']); ?></strong>
                                            <?php else: ?>
                                                <span style="color: #a0aec0; font-size: 0.7rem; font-style: italic;">Indisponible (Haché)</span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span style="font-size: 0.65rem; color: #888; width: 40px; font-weight: 700;">HASH:</span>
                                            <?php if ($info['algo'] !== 0): ?>
                                                <code title="<?php echo htmlspecialchars($u['password']); ?>" style="font-size: 0.65rem; background: #f0fff4; border: 1px solid #c6f6d5; padding: 2px 4px; border-radius: 4px; color: #2f855a; max-width: 130px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                    <?php echo htmlspecialchars($u['password']); ?>
                                                </code>
                                            <?php else: ?>
                                                <span style="color: #a0aec0; font-size: 0.7rem; font-style: italic;">Non haché</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 15px 10px;"><?php echo $u['is_active'] ? '<span style="display:flex; align-items:center; gap:5px; color:#38a169;"><i class="fas fa-check-circle"></i> Actif</span>' : '<span style="display:flex; align-items:center; gap:5px; color:#e53e3e;"><i class="fas fa-ban"></i> Banni</span>'; ?></td>
                                <td style="padding: 15px 10px; font-weight: 600; color: #4a5568;"><?php echo $u['projects_count']; ?></td>
                                <td style="padding: 15px 10px; text-align: right;">
                                    <div style="display: flex; gap: 15px; justify-content: flex-end; align-items: center;">
                                        <a href="projects.php?user_id=<?php echo $u['id']; ?>&status=all" title="Voir les projets" style="color: var(--primary-color); font-size: 1.1rem;"><i class="fas fa-folder-open"></i></a>
                                        <a href="edit-user.php?id=<?php echo $u['id']; ?>" title="Modifier" style="color: #3182ce; font-size: 1.1rem;"><i class="fas fa-edit"></i></a>
                                        <a href="admin-actions.php?action=toggle_user&id=<?php echo $u['id']; ?>" title="Changer statut" style="color: #718096; font-size: 1.1rem;"><i class="fas fa-power-off"></i></a>
                                        <a href="admin-actions.php?action=delete_user&id=<?php echo $u['id']; ?>" onclick="return confirm('Supprimer définitivement cet utilisateur ?')" title="Supprimer" style="color: var(--danger-color); font-size: 1.1rem;"><i class="fas fa-trash-alt"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    <?php endif; ?>
</div>
<script>
    // Fonction de debounce pour limiter la fréquence des appels AJAX
    function debounce(func, delay) {
        let timeout;
        return function(...args) {
            const context = this;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), delay);
        };
    }

    // Fonction pour réinitialiser une recherche
    function resetLiveSearch(inputId) {
        const input = document.getElementById(inputId);
        if (input) {
            input.value = '';
            input.dispatchEvent(new Event('input'));
        }
    }

    // Fonction générique pour effectuer la recherche AJAX
    async function performLiveSearch(inputElementId, targetContainerId, searchParamName) {
        const input = document.getElementById(inputElementId);
        const container = document.getElementById(targetContainerId);
        if (!input || !container) return;

        input.addEventListener('input', debounce(async () => {
            const query = input.value;
            // Afficher un indicateur de chargement
            container.innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin fa-2x" style="color: var(--primary-color);"></i></div>';

            try {
                const response = await fetch(`dashboard.php?ajax=true&${searchParamName}=${encodeURIComponent(query)}`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const html = await response.text();
                container.innerHTML = html;
            } catch (error) {
                console.error("Erreur lors de la recherche AJAX:", error);
                container.innerHTML = '<div class="alert alert-error">Erreur lors du chargement des résultats.</div>';
            }
        }, 300)); // Délai de 300ms
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Initialiser la recherche en direct pour les dons
        performLiveSearch('search-donations-input', 'admin-donations-table-body', 'search_don');
        
        // Initialiser la recherche en direct pour les utilisateurs
        performLiveSearch('search-users-input', 'admin-users-table-body', 'search_user'); // Cible le tbody de la table des utilisateurs

        // Initialiser la recherche en direct pour les projets
        performLiveSearch('search-projects-input', 'admin-projects-list', 'search_proj'); // Cible le conteneur de liste des projets

        // Initialiser la recherche en direct pour les établissements
        performLiveSearch('search-schools-input', 'admin-schools-table-body', 'search_school'); // Cible le tbody de la table des établissements
    });
</script>

<?php require_once 'includes/footer.php'; ?>