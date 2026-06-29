<?php
$pageTitle = "Créer un Projet";
require_once 'includes/header.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $category = sanitizeInput($_POST['category']);
    $target_amount = floatval($_POST['target_amount']);
    $start_date = sanitizeInput($_POST['start_date']);
    $end_date = sanitizeInput($_POST['end_date']);
    $school_name = sanitizeInput($_POST['school_name']);
    $class_level = sanitizeInput($_POST['class_level']);
    $number_of_students = intval($_POST['number_of_students']);
    
    // Validation
    $errors = [];
    
    if (empty($title)) $errors[] = "Le titre est requis";
    if (empty($description)) $errors[] = "La description est requise";
    if ($target_amount <= 0) $errors[] = "Le montant objectif doit être supérieur à 0";
    if (empty($start_date) || empty($end_date)) $errors[] = "Les dates sont requises";
    if (strtotime($end_date) <= strtotime($start_date)) $errors[] = "La date de fin doit être après la date de début";
    
    // Upload d'image
    $image_url = null;
    if (isset($_FILES['project_image']) && $_FILES['project_image']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadImage($_FILES['project_image'], $title);
        if ($uploadResult['success']) {
            $image_url = $uploadResult['filepath'];
        } else {
            $errors[] = $uploadResult['message'];
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO projects 
                (user_id, title, description, category, target_amount, start_date, end_date, 
                 image_url, school_name, class_level, number_of_students, status)
                VALUES 
                (:user_id, :title, :description, :category, :target_amount, :start_date, :end_date,
                 :image_url, :school_name, :class_level, :number_of_students, 'pending')
            ");
            
            $stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':title' => $title,
                ':description' => $description,
                ':category' => $category,
                ':target_amount' => $target_amount,
                ':start_date' => $start_date,
                ':end_date' => $end_date,
                ':image_url' => $image_url,
                ':school_name' => $school_name,
                ':class_level' => $class_level,
                ':number_of_students' => $number_of_students
            ]);
            
            $projectId = $pdo->lastInsertId();
            
            // Notification par email (optionnel)
            // sendEmail($_SESSION['user_email'], "Projet créé", "Votre projet a été créé avec succès et est en attente de validation.");
            
            $_SESSION['success_message'] = "Projet créé avec succès! Il sera publié après validation.";
            redirect("project-detail.php?id=$projectId");
            
        } catch (PDOException $e) {
            $errors[] = "Erreur lors de la création du projet: " . $e->getMessage();
        }
    }
}
?>

<section style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 4rem 2rem 3rem;">
    <div style="max-width: 1200px; margin: 0 auto; text-align: center; color: white;">
        <h1 style="font-size: 3rem; margin-bottom: 1rem;">Créer un Projet</h1>
        <p style="font-size: 1.2rem; opacity: 0.9;">Partagez votre projet éducatif et trouvez des soutiens</p>
    </div>
</section>

<div class="form-container">
    <?php if (isset($errors) && count($errors) > 0): ?>
        <div class="alert alert-error">
            <ul style="margin: 0; padding-left: 1.5rem;">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="title">Titre du projet *</label>
            <input type="text" id="title" name="title" class="form-control" required 
                   placeholder="Ex: Voyage linguistique à Londres"
                   value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
        </div>
        
        <div class="form-group">
            <label for="description">Description détaillée *</label>
            <textarea id="description" name="description" class="form-control" rows="6" required
                      placeholder="Décrivez votre projet, ses objectifs, son impact sur les élèves..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label for="category">Catégorie *</label>
                <select id="category" name="category" class="form-control" required>
                    <option value="">Sélectionnez...</option>
                    <option value="arts" <?php echo (isset($_POST['category']) && $_POST['category'] === 'arts') ? 'selected' : ''; ?>>Arts & Culture</option>
                    <option value="science" <?php echo (isset($_POST['category']) && $_POST['category'] === 'science') ? 'selected' : ''; ?>>Sciences & Technologie</option>
                    <option value="sports" <?php echo (isset($_POST['category']) && $_POST['category'] === 'sports') ? 'selected' : ''; ?>>Sports & Santé</option>
                    <option value="environment" <?php echo (isset($_POST['category']) && $_POST['category'] === 'environment') ? 'selected' : ''; ?>>Environnement</option>
                    <option value="culture" <?php echo (isset($_POST['category']) && $_POST['category'] === 'culture') ? 'selected' : ''; ?>>Voyages & Échanges</option>
                    <option value="technology" <?php echo (isset($_POST['category']) && $_POST['category'] === 'technology') ? 'selected' : ''; ?>>Technologie</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="target_amount">Montant objectif (Ariary) *</label>
                <input type="number" id="target_amount" name="target_amount" class="form-control" required 
                       min="1000" step="1000"
                       placeholder="500000"
                       value="<?php echo isset($_POST['target_amount']) ? htmlspecialchars($_POST['target_amount']) : ''; ?>">
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label for="start_date">Date de début *</label>
                <input type="date" id="start_date" name="start_date" class="form-control" required
                       value="<?php echo isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : date('Y-m-d'); ?>">
            </div>
            
            <div class="form-group">
                <label for="end_date">Date de fin *</label>
                <input type="date" id="end_date" name="end_date" class="form-control" required
                       value="<?php echo isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : date('Y-m-d', strtotime('+30 days')); ?>">
            </div>
        </div>
        
        <div class="form-group">
            <label for="school_name">Nom de l'établissement *</label>
            <input type="text" id="school_name" name="school_name" class="form-control" required
                   placeholder="Ex: École Primaire Centrale"
                   value="<?php echo isset($_POST['school_name']) ? htmlspecialchars($_POST['school_name']) : ''; ?>">
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label for="class_level">Niveau de classe</label>
                <input type="text" id="class_level" name="class_level" class="form-control"
                       placeholder="Ex: CE2-CM1"
                       value="<?php echo isset($_POST['class_level']) ? htmlspecialchars($_POST['class_level']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="number_of_students">Nombre d'élèves</label>
                <input type="number" id="number_of_students" name="number_of_students" class="form-control"
                       min="1"
                       placeholder="25"
                       value="<?php echo isset($_POST['number_of_students']) ? htmlspecialchars($_POST['number_of_students']) : ''; ?>">
            </div>
        </div>
        
        <div class="form-group">
            <label for="project_image">Image du projet</label>
            <input type="file" id="project_image" name="project_image" class="form-control" accept="image/*">
            <small style="color: #666; display: block; margin-top: 0.5rem;">
                Formats acceptés: JPG, PNG, GIF, WebP (max 5MB)
            </small>
        </div>
        
        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
            <a href="dashboard.php" class="btn btn-secondary" style="flex: 1;">Annuler</a>
            <button type="submit" class="btn btn-primary" style="flex: 2;">
                <i class="fas fa-paper-plane"></i> Soumettre le projet
            </button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>