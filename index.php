<?php
$pageTitle = "Accueil";
require_once 'includes/header.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

$database = new Database();
$pdo = $database->getConnection();

// Récupérer les statistiques
$stats = getProjectStats($pdo);

// Récupérer les projets récents
$stmt = $pdo->query("
    SELECT p.*, u.first_name, u.last_name, u.school_name,
           (SELECT COUNT(*) FROM donations WHERE project_id = p.id AND payment_status = 'completed') as donation_count
    FROM projects p
    JOIN users u ON p.user_id = u.id
    WHERE p.status = 'active'
    ORDER BY p.created_at DESC
    LIMIT 6
");
$recentProjects = $stmt->fetchAll();

// Récupérer les nombres de projets réels par catégorie
$categoryCounts = [];
$countsStmt = $pdo->query("SELECT category, COUNT(*) as count FROM projects WHERE status = 'active' GROUP BY category");
while ($row = $countsStmt->fetch()) {
    $categoryCounts[$row['category']] = $row['count'];
}
?>

<!-- Hero Section -->
<section class="hero">
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>
    
    <div class="hero-content">
    <!-- Conteneur Logo Circulaire -->
    <div class="hero-logo-container animate-on-scroll">
        <!-- 🔹 Remplacez le chemin par votre logo réel -->
        <img src="<?php echo SITE_URL; ?>/assets/images/logo.png" alt="Mirindra Funding Logo" 
             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
        <div class="hero-logo-fallback" style="display: none;">
            <i class="fas fa-hand-holding-heart"></i>
        </div>
    </div>

    <h1 class="animate-on-scroll">Soutenez l'Éducation de Demain</h1>
    <p class="animate-on-scroll">Mirindra Funding est la plateforme de crowdfunding dédiée aux projets éducatifs. Ensemble, finançons l'avenir de nos enfants.</p>
    
    <div class="hero-buttons animate-on-scroll">
        <a href="projects.php" class="btn btn-primary btn-large magnetic-btn">
            <i class="fas fa-search"></i> Découvrir les projets
        </a>
        <a href="create-project.php" class="btn btn-secondary btn-large magnetic-btn">
            <i class="fas fa-plus"></i> Créer un projet
        </a>
    </div>
</div>
</section>

<!-- Section Vidéo -->
<div style="max-width: 1200px; margin: 3rem auto 0; padding: 0 2rem;">
    <div style="background: white; border-radius: 12px; box-shadow: var(--shadow-md); overflow: hidden;">
        <video width="100%" height="auto" autoplay loop muted playsinline style="display: block; border-radius: 12px;">
            <source src="<?php echo SITE_URL; ?>/assets/videos/video.mp4" type="video/mp4">
            Votre navigateur ne supporte pas la lecture de vidéos.
        </video>
        <div style="padding: 1.5rem; text-align: center; background: #f8f9fa; border-top: 1px solid #eee;">
            <p style="margin: 0; color: var(--dark-color); font-weight: 600;">Découvrez l'impact de vos dons en action !</p>
        </div>
    </div>
</div>

<!-- Stats Section -->
<section class="stats">
    <div class="stats-container">
        <div class="stat-card animate-on-scroll">
            <div class="stat-icon">📚</div>
            <div class="stat-number" data-target="<?php echo $stats['total_projects']; ?>">0</div>
            <div class="stat-label">Projets financés</div>
        </div>
        
        <div class="stat-card animate-on-scroll">
            <div class="stat-icon">💰</div>
            <div class="stat-number" data-target="<?php echo number_format($stats['total_raised'], 0, '.', ''); ?>">0</div>
            <div class="stat-label">Ariary collectés</div>
        </div>
        
        <div class="stat-card animate-on-scroll">
            <div class="stat-icon">👥</div>
            <div class="stat-number" data-target="<?php echo $stats['total_donors']; ?>">0</div>
            <div class="stat-label">Donateurs</div>
        </div>
        
        <div class="stat-card animate-on-scroll">
            <div class="stat-icon">🎯</div>
            <div class="stat-number" data-target="<?php echo $stats['active_projects']; ?>">0</div>
            <div class="stat-label">Projets actifs</div>
        </div>
    </div>
</section>

<!-- Categories Section -->
<section class="categories">
    <div class="section-header">
        <h2>Catégories de Projets</h2>
        <p>Explorez les différents types de projets éducatifs que vous pouvez soutenir</p>
    </div>
    
    <div class="categories-grid">
        <a href="projects.php?category=arts" class="category-card animate-on-scroll">
            <div class="category-icon">🎨</div>
            <div class="category-name">Arts & Culture</div>
            <div class="category-count"><?php echo $categoryCounts['arts'] ?? 0; ?> projets</div>
        </a>
        
        <a href="projects.php?category=science" class="category-card animate-on-scroll">
            <div class="category-icon">🔬</div>
            <div class="category-name">Sciences & Tech</div>
            <div class="category-count"><?php echo $categoryCounts['science'] ?? 0; ?> projets</div>
        </a>
        
        <a href="projects.php?category=sports" class="category-card animate-on-scroll">
            <div class="category-icon">⚽</div>
            <div class="category-name">Sports & Santé</div>
            <div class="category-count"><?php echo $categoryCounts['sports'] ?? 0; ?> projets</div>
        </a>
        
        <a href="projects.php?category=environment" class="category-card animate-on-scroll">
            <div class="category-icon">🌱</div>
            <div class="category-name">Environnement</div>
            <div class="category-count"><?php echo $categoryCounts['environment'] ?? 0; ?> projets</div>
        </a>
        
        <a href="projects.php?category=culture" class="category-card animate-on-scroll">
            <div class="category-icon">✈️</div>
            <div class="category-name">Voyages</div>
            <div class="category-count"><?php echo $categoryCounts['culture'] ?? 0; ?> projets</div>
        </a>
        
        <a href="projects.php?category=other" class="category-card animate-on-scroll">
            <div class="category-icon">🤝</div>
            <div class="category-name">Citoyenneté</div>
            <div class="category-count"><?php echo $categoryCounts['other'] ?? 0; ?> projets</div>
        </a>
    </div>
</section>

<!-- Recent Projects -->
<section class="projects">
    <div class="section-header">
        <h2>Projets Récents</h2>
        <p>Découvrez les derniers projets lancés par nos enseignants et élèves</p>
    </div>
    
    <div class="projects-grid">
        <?php foreach ($recentProjects as $project): ?>
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
                            <i class="fas fa-clock"></i> <?php echo $daysRemaining; ?> jours restants
                        </div>
                    </div>
                    
                    <a href="project-detail.php?id=<?php echo $project['id']; ?>" 
                       class="btn btn-primary" 
                       style="width: 100%; margin-top: 1rem; text-align: center;">
                        <i class="fas fa-heart"></i> Soutenir ce projet
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div style="text-align: center; margin-top: 3rem;">
        <a href="projects.php" class="btn btn-secondary btn-large">
            Voir tous les projets <i class="fas fa-arrow-right"></i>
        </a>
    </div>
</section>

<!-- How It Works -->
<section class="how-it-works" id="how-it-works">
    <div class="section-header">
        <h2>Comment Ça Marche?</h2>
        <p>En 4 étapes simples, soutenez ou créez un projet éducatif</p>
    </div>
    
    <div class="steps">
        <div class="step animate-on-scroll">
            <div class="step-number">1</div>
            <h3 class="step-title">Découvrez</h3>
            <p class="step-description">Explorez les projets éducatifs proposés par les enseignants et élèves de Madagascar</p>
        </div>
        
        <div class="step animate-on-scroll">
            <div class="step-number">2</div>
            <h3 class="step-title">Choisissez</h3>
            <p class="step-description">Sélectionnez le projet qui vous touche et qui correspond à vos valeurs</p>
        </div>
        
        <div class="step animate-on-scroll">
            <div class="step-number">3</div>
            <h3 class="step-title">Contribuez</h3>
            <p class="step-description">Faites un don sécurisé, même modeste. Chaque contribution compte!</p>
        </div>
        
        <div class="step animate-on-scroll">
            <div class="step-number">4</div>
            <h3 class="step-title">Suivez</h3>
            <p class="step-description">Recevez des nouvelles du projet et voyez l'impact de votre générosité</p>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>