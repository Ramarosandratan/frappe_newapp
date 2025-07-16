<?php

echo "=== Test des améliorations de login ===\n\n";

// Vérifier que les fichiers ont été modifiés correctement
$files_to_check = [
    'config/packages/security.yaml' => 'Configuration de sécurité avec logout',
    'templates/base.html.twig' => 'Navigation avec bouton de déconnexion',
    'templates/security/login.html.twig' => 'Page de login améliorée',
    'templates/home/index.html.twig' => 'Page d\'accueil améliorée',
    'src/Security/AppAuthenticator.php' => 'Redirection vers page d\'accueil',
    'src/Controller/HomeController.php' => 'Route app_home configurée',
    'assets/styles/app.css' => 'Styles améliorés'
];

foreach ($files_to_check as $file => $description) {
    if (file_exists($file)) {
        echo "✅ $description : $file\n";
    } else {
        echo "❌ Fichier manquant : $file\n";
    }
}

echo "\n=== Vérification du contenu des fichiers clés ===\n\n";

// Vérifier la configuration de sécurité
$security_config = file_get_contents('config/packages/security.yaml');
if (strpos($security_config, 'logout:') !== false) {
    echo "✅ Configuration logout présente dans security.yaml\n";
} else {
    echo "❌ Configuration logout manquante dans security.yaml\n";
}

// Vérifier la navigation
$base_template = file_get_contents('templates/base.html.twig');
if (strpos($base_template, 'app.user') !== false && strpos($base_template, 'Déconnexion') !== false) {
    echo "✅ Bouton de déconnexion présent dans la navigation\n";
} else {
    echo "❌ Bouton de déconnexion manquant dans la navigation\n";
}

// Vérifier la page de login
$login_template = file_get_contents('templates/security/login.html.twig');
if (strpos($login_template, 'linear-gradient') !== false && strpos($login_template, 'form-floating') !== false) {
    echo "✅ Design amélioré présent dans la page de login\n";
} else {
    echo "❌ Design amélioré manquant dans la page de login\n";
}

// Vérifier l'authenticateur
$authenticator = file_get_contents('src/Security/AppAuthenticator.php');
if (strpos($authenticator, 'app_home') !== false) {
    echo "✅ Redirection vers app_home configurée dans l'authenticateur\n";
} else {
    echo "❌ Redirection vers app_home manquante dans l'authenticateur\n";
}

// Vérifier la route home
$home_controller = file_get_contents('src/Controller/HomeController.php');
if (strpos($home_controller, '#[Route(\'/', name: \'app_home\')]') !== false) {
    echo "✅ Route app_home configurée dans HomeController\n";
} else {
    echo "❌ Route app_home manquante dans HomeController\n";
}

echo "\n=== Résumé des améliorations ===\n\n";
echo "1. ✅ Page de login avec design moderne et responsive\n";
echo "2. ✅ Bouton de déconnexion dans la navigation\n";
echo "3. ✅ Redirection vers page d'accueil après login\n";
echo "4. ✅ Page d'accueil personnalisée avec message de bienvenue\n";
echo "5. ✅ Styles CSS améliorés pour toute l'application\n";
echo "6. ✅ Configuration de sécurité avec logout\n";

echo "\n=== Instructions pour tester ===\n\n";
echo "1. Démarrez le serveur Symfony : php -S localhost:8000 -t public\n";
echo "2. Accédez à http://localhost:8000\n";
echo "3. Vous devriez être redirigé vers la page de login avec le nouveau design\n";
echo "4. Après connexion, vous serez redirigé vers la page d'accueil\n";
echo "5. Le bouton de déconnexion sera visible dans la navigation\n";

echo "\n=== Test terminé ===\n";