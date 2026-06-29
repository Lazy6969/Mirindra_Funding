# Installation de Mirindra Funding

## Prérequis
- PHP 7.4 ou supérieur
- MySQL 5.7 ou supérieur
- Apache/Nginx
- Composer (optionnel)

## Étapes d'installation

### 1. Cloner/copier les fichiers
Placez tous les fichiers dans votre dossier web (htdocs/www)

### 2. Créer la base de données
- Ouvrez phpMyAdmin ou votre client MySQL
- Créez une base de données nommée `mirindra_funding`
- Importez le fichier `database.sql`

### 3. Configurer la connexion
Modifiez `includes/database.php`:
```php


private $db_name = "mirindra_funding";
private $username = "root";
private $password = "votre_mot_de_passe";
```

### 4. Identifiants Administrateur (par défaut)
**Nom :** LAZY  
**Email :** mirindra@gmail.com  
**Mot de passe :** mirindra123
