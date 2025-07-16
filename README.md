# Frappe NewApp - Système de Gestion de Paie

Une application web Symfony moderne intégrée à ERPNext pour la gestion des employés, le traitement de la paie et la génération de bulletins de salaire.

## 🚀 Fonctionnalités

- **Gestion des employés** : Interface complète pour la gestion des données employés
- **Génération de paie** : Système automatisé de génération des bulletins de salaire
- **Intégration ERPNext** : Synchronisation bidirectionnelle avec ERPNext
- **Statistiques avancées** : Tableaux de bord et rapports détaillés
- **Import CSV** : Import en masse d'employés et structures salariales
- **Historique des modifications** : Suivi complet des changements
- **Génération PDF** : Bulletins de paie au format PDF
- **Interface responsive** : Design moderne et adaptatif

## 🛠️ Technologies

- **Backend** : PHP 8.2+ avec Symfony 7.3
- **Base de données** : MySQL/MariaDB avec Doctrine ORM
- **Frontend** : Twig, Stimulus, Bootstrap
- **API** : Intégration REST avec ERPNext
- **PDF** : KnpSnappyBundle (wkhtmltopdf)
- **Tests** : PHPUnit 12.2
- **Build** : Webpack Encore

## 📋 Prérequis

- PHP 8.2 ou supérieur
- Composer
- Node.js et npm
- MySQL/MariaDB
- Instance ERPNext configurée
- wkhtmltopdf (pour la génération PDF)

## 🔧 Installation

### 1. Cloner le projet
```bash
git clone <repository-url>
cd frappe_newapp
```

### 2. Installer les dépendances PHP
```bash
composer install
```

### 3. Installer les dépendances JavaScript
```bash
npm install
```

### 4. Configuration de l'environnement
Copiez le fichier `.env` et configurez vos paramètres :
```bash
cp .env .env.local
```

Configurez les variables suivantes dans `.env.local` :
```env
# Base de données
DATABASE_URL="mysql://user:password@127.0.0.1:3306/database_name?serverVersion=10.11.13-MariaDB&charset=utf8mb4"

# ERPNext API
API_BASE=http://your-erpnext-instance:8000
API_KEY=your-api-key
API_SECRET=your-api-secret

# Symfony
APP_SECRET=your-app-secret
```

### 5. Configuration de la base de données
```bash
# Créer la base de données
php bin/console doctrine:database:create

# Exécuter les migrations
php bin/console doctrine:migrations:migrate
```

### 6. Compiler les assets
```bash
# Pour le développement
npm run dev

# Pour la production
npm run build

# Mode watch pour le développement
npm run watch
```

### 7. Configuration Docker (optionnel)
Si vous utilisez Docker pour la base de données :
```bash
docker-compose up -d
```

## 🚀 Utilisation

### Démarrer le serveur de développement
```bash
symfony server:start
```
ou
```bash
php -S localhost:8000 -t public/
```

### Accès à l'application
- URL : `http://localhost:8000`
- Connexion via ERPNext (authentification déléguée)

## 📁 Structure du projet

```
frappe_newapp/
├── assets/                 # Sources JavaScript/CSS
├── config/                 # Configuration Symfony
├── migrations/             # Migrations de base de données
├── public/                 # Point d'entrée web
├── src/
│   ├── Command/           # Commandes console
│   ├── Controller/        # Contrôleurs web
│   ├── Entity/           # Entités Doctrine
│   ├── Form/             # Types de formulaires
│   ├── Repository/       # Repositories Doctrine
│   ├── Security/         # Authentification
│   ├── Service/          # Services métier
│   └── Twig/            # Extensions Twig
├── templates/             # Templates Twig
├── tests/                # Tests automatisés
└── translations/         # Fichiers de traduction
```

## 🧪 Tests

### Exécuter tous les tests
```bash
php bin/phpunit
```

### Tests par catégorie
```bash
# Tests unitaires
php bin/phpunit tests/Service/

# Tests d'intégration
php bin/phpunit tests/Integration/

# Tests fonctionnels
php bin/phpunit tests/Functional/

# Tests E2E
php bin/phpunit tests/E2E/
```

### Coverage des tests
```bash
php bin/phpunit --coverage-html coverage/
```

## 📊 Fonctionnalités principales

### 1. Gestion des employés
- Liste et détail des employés
- Synchronisation avec ERPNext
- Gestion des informations personnelles et professionnelles

### 2. Génération de paie
- Calcul automatique des salaires
- Support des formules personnalisées
- Gestion des composants de salaire (base, primes, déductions)
- Génération en lot ou individuelle

### 3. Import de données
- Import CSV d'employés
- Import de structures salariales
- Validation et gestion des erreurs
- Import avec dépendances automatiques

### 4. Statistiques et rapports
- Tableaux de bord interactifs
- Graphiques avec Chart.js
- Rapports mensuels et annuels
- Historique des modifications

### 5. Intégration ERPNext
- Authentification SSO
- Synchronisation des données
- Gestion des documents ERPNext
- API REST complète

## 🔧 Commandes utiles

### Import de données
```bash
# Import d'employés depuis CSV
php bin/console app:import-employees employees.csv

# Import de structures salariales
php bin/console app:import-salary-structures structures.csv

# Import avec dépendances
php bin/console app:import-with-dependencies
```

### Maintenance
```bash
# Nettoyer l'historique ancien
php bin/console app:clean-history

# Générer des données de démonstration
php bin/console app:demo-history
```

## 🐛 Dépannage

### Problèmes courants

1. **Erreur de connexion ERPNext**
   - Vérifiez les paramètres API dans `.env.local`
   - Assurez-vous que l'instance ERPNext est accessible

2. **Erreur de génération PDF**
   - Installez wkhtmltopdf : `sudo apt-get install wkhtmltopdf`
   - Vérifiez la configuration dans `config/packages/knp_snappy.yaml`

3. **Problèmes d'assets**
   - Relancez `npm run build`
   - Videz le cache : `php bin/console cache:clear`

### Logs
Les logs sont disponibles dans `var/log/` :
- `dev.log` : Logs de développement
- `prod.log` : Logs de production

## 🤝 Contribution

1. Fork le projet
2. Créez une branche feature (`git checkout -b feature/nouvelle-fonctionnalite`)
3. Committez vos changements (`git commit -am 'Ajout nouvelle fonctionnalité'`)
4. Poussez vers la branche (`git push origin feature/nouvelle-fonctionnalite`)
5. Créez une Pull Request

### Standards de code
- Suivre les standards PSR-12
- Utiliser PHPStan pour l'analyse statique
- Écrire des tests pour les nouvelles fonctionnalités
- Documenter les API publiques

## 📝 Licence

Ce projet est sous licence propriétaire. Voir le fichier `composer.json` pour plus de détails.

## 🆘 Support

Pour obtenir de l'aide :
1. Consultez la documentation dans le dossier `docs/`
2. Vérifiez les issues GitHub existantes
3. Créez une nouvelle issue si nécessaire

## 🔄 Changelog

Voir `docs/HISTORIQUE_MODIFICATIONS.md` pour l'historique détaillé des modifications.

---

**Développé avec ❤️ en utilisant Symfony et ERPNext**