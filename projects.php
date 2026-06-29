<?php
$pageTitle = "Tous les Projets";
require_once 'includes/header.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

$database = new Database();
$pdo = $database->getConnection();

// Filtrage
$category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'active';
$user_id_filter = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Requête SQL
$sql = "
    SELECT p.*, u.first_name, u.last_name, u.school_name,
           (SELECT COUNT(*) FROM donations WHERE project_id = p.id AND payment_status = 'completed') as donation_count
    FROM projects p
    JOIN users u ON p.user_id = u.id
";

$where = [];
$params = [];

if ($status !== 'all' && !isAdmin()) {
    $where[] = "p.status = 'active'";
} elseif ($status !== 'all') {
    $where[] = "p.status = :status";
    $params[':status'] = $status;
}

if (!empty($category)) {
    $where[] = "p.category = :category";
    $params[':category'] = $category;
}

if (!empty($search)) {
    $where[] = "(p.title LIKE :search OR p.description LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($user_id_filter > 0) {
    $where[] = "p.user_id = :user_id";
    $params[':user_id'] = $user_id_filter;
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$projects = $stmt->fetchAll();
?>

<section style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 4rem 2rem 3rem;">
    <div style="max-width: 1200px; margin: 0 auto; text-align: center; color: white;">
        <h1 style="font-size: 3rem; margin-bottom: 1rem;">Tous les Projets</h1>
        <p style="font-size: 1.2rem; opacity: 0.9;">Découvrez et soutenez les projets éducatifs de Madagascar</p>
    </div>
</section>

<section class="projects" style="padding-top: 3rem;">
    <!-- Filtres -->
    <div style="max-width: 1200px; margin: 0 auto 3rem; padding: 0 2rem;">
        <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
            <div style="flex: 1; min-width: 250px;">
                <input type="text" 
                       name="search" 
                       id="search-projects"
                       placeholder="Rechercher un projet..." 
                       value="<?php echo htmlspecialchars($search); ?>"
                       class="form-control">
            </div>
            
            <select name="category" id="category-filter" class="form-control" style="width: auto;">
                <option value="">Toutes les catégories</option>
                <option value="arts" <?php echo $category === 'arts' ? 'selected' : ''; ?>>Arts & Culture</option>
                <option value="science" <?php echo $category === 'science' ? 'selected' : ''; ?>>Sciences & Technologie</option>
                <option value="sports" <?php echo $category === 'sports' ? 'selected' : ''; ?>>Sports & Santé</option>
                <option value="environment" <?php echo $category === 'environment' ? 'selected' : ''; ?>>Environnement</option>
                <option value="culture" <?php echo $category === 'culture' ? 'selected' : ''; ?>>Voyages & Échanges</option>
                <option value="technology" <?php echo $category === 'technology' ? 'selected' : ''; ?>>Technologie</option>
            </select>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Rechercher
            </button>
        </form>
    </div>
    
    <!-- Résultats -->
    <?php if (count($projects) > 0): ?>
        <div class="projects-grid" style="max-width: 1400px; margin: 0 auto;">
            <?php foreach ($projects as $project): ?>
                <?php
                $progress = ($project['current_amount'] / $project['target_amount']) * 100;
$daysRemaining = getDaysRemaining($project['end_date']);
                $categoryColors = [
                    'arts' => '#e74c3c',
                    'science' => '#3498db',
                    'sports' => '#2ecc71',
                    'environment' => '#27ae60',
                    'culture' => '#f39c12',
                    'technology' => '#9b59b6',
                    'other' => '#95a5a6'
                ];
                $color = $categoryColors[$project['category']] ?? '#667eea';
                ?>
                
                <div class="project-card animate-on-scroll" data-category="<?php echo $project['category']; ?>">
                    <div style="overflow: hidden;">
                        <img src="<?php echo !empty($project['image_url']) ? SITE_URL . '/' . $project['image_url'] : 'https://via.placeholder.com/600x400/667eea/ffffff?text=Mirindra+Funding'; ?>" 
                             alt="<?php echo htmlspecialchars($project['title']); ?>" 
                             class="project-image">
                    </div>
                    
                    <div class="project-content">
                        <a href="projects.php?category=<?php echo $project['category']; ?>" class="project-category" style="background: <?php echo $color; ?>">
                            <?php echo ucfirst($project['category']); ?>
                        </a>
                        
                        <h3 class="project-title"><?php echo htmlspecialchars($project['title']); ?></h3>
                        <p class="project-description"><?php echo htmlspecialchars(substr($project['description'], 0, 120)) . '...'; ?></p>
                        
                        <div class="project-stats">
                            <div>
                                <div class="project-raised">
                                    <?php echo number_format($project['current_amount'], 0, ',', ' '); ?> Ar
                                </div>
                                <div class="project-target">
                                    sur <?php echo number_format($project['target_amount'], 0, ',', ' '); ?> Ar
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-weight: 600; color: var(--primary-color);">
                                    <?php echo $project['donation_count']; ?> dons
                                </div>
                            </div>
                        </div>
                        
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                        </div>
                        
                        <div class="project-footer">
                            <div class="project-school">
                                <i class="fas fa-school"></i> <?php echo htmlspecialchars($project['school_name']); ?>
                            </div>
                            <div class="project-days">
                                <i class="fas fa-clock"></i> <?php echo $daysRemaining; ?> jours
                            </div>
                        </div>
                        
                        <a href="project-detail.php?id=<?php echo $project['id']; ?>" 
                           class="btn btn-primary" 
                           style="width: 100%; margin-top: 1rem; text-align: center;">
                            <i class="fas fa-heart"></i> Soutenir ce projet
                        </a>

                    <?php if (isAdmin()): ?>
                        <a href="admin-actions.php?action=delete_project&id=<?php echo $project['id']; ?>" 
                           class="btn btn-secondary" 
                           onclick="return confirm('Voulez-vous vraiment supprimer ce projet ?')"
                           style="width: 100%; margin-top: 0.5rem; color: var(--danger-color); border-color: var(--danger-color); text-align: center;">
                            <i class="fas fa-trash-alt"></i> Supprimer (Admin)
                        </a>
                    <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 4rem 2rem;">
            <i class="fas fa-search" style="font-size: 4rem; color: #bdc3c7; margin-bottom: 1rem;"></i>
            <h3 style="color: #7f8c8d; margin-bottom: 1rem;">Aucun projet trouvé</h3>
            <p style="color: #95a5a6;">Essayez de modifier vos critères de recherche</p>
        </div>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>