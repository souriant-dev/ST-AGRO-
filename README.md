# ST-AGRO — Plateforme agro-intelligente

Application web PHP / MySQL / HTML / CSS / JS générée à partir du diagramme de cas d'utilisation fourni.

## Installation (XAMPP / WAMP / LAMP)

1. Copiez le dossier `st-agro` dans votre serveur web (`htdocs`, `www`, etc.).
2. Créez la base de données en important `database.sql` (phpMyAdmin ou `mysql -u root -p < database.sql`).
3. Ouvrez `config/database.php` et vérifiez les identifiants MySQL (`DB_USER`, `DB_PASS`).
4. (Optionnel) Créez un compte gratuit sur [OpenWeatherMap](https://openweathermap.org/api) et collez votre clé dans `METEO_API_KEY` pour une météo en temps réel — sans clé, l'application affiche une estimation de repli, l'interface reste toujours fonctionnelle.
5. Lancez Apache + MySQL et ouvrez `http://localhost/st-agro/`.

**Comptes de démonstration** (mot de passe `Password123`) :
- `admin@st-agro.cm` — Administrateur
- `agriculteur@st-agro.cm` — Agriculteur
- `agronome@st-agro.cm` — Agronome

## Ce qui est implémenté

D'après votre diagramme de cas d'utilisation :
- **Visiteur** : consulter la plateforme, s'inscrire, s'authentifier.
- **Utilisateur (abstrait)** : gérer son profil, consulter notifications.
- **Agriculteur** : gérer ses exploitations agricoles (CRUD), consulter les données de ses capteurs, consulter la météo, gérer les analyses phytosanitaires (envoi de photo), consulter ses alertes, demander conseil et discuter (chat) avec un agronome.
- **Agronome** : consulter les données des exploitations, consulter/répondre aux demandes de conseil (chat), valider/compléter les diagnostics phytosanitaires.
- **Administrateur** : gérer les comptes utilisateurs (rôles, suspension), consulter les exploitations, consulter les statistiques globales.

## Ce qui est simulé ou à connecter (roadmap)

Ces cas d'utilisation dépendent de services externes réels ou de matériel physique qui ne peuvent pas être « inventés » de façon crédible ; l'architecture est prête à les recevoir :

| Cas d'utilisation | État actuel | Pour aller plus loin |
|---|---|---|
| Consulter météo | **Fonctionnel** via l'API OpenWeatherMap (avec repli local) | Ajouter la géolocalisation automatique |
| Transmettre paramètre / Mesurer paramètres du terrain (Capteur IoT) | Table `releves_capteurs` prête, données de démo insérées | Créer un endpoint `api/ingestion_capteur.php` que vos capteurs réels appelleront (POST + clé API par capteur) |
| Gérer analyse phytosanitaire (IA) | Le flux d'envoi de photo + validation humaine par un agronome est fonctionnel | Brancher un vrai modèle de vision (ex. API tierce) qui pré-remplit le diagnostic avant validation |
| Gérer prédiction (IA rendement) | Table `predictions` prête | Brancher un modèle de prédiction (ex. script Python exposé en API) qui écrit dans cette table |
| ChatBot IA | Non inclus (le chat agriculteur ↔ agronome, lui, est fonctionnel) | Intégrer un service de chatbot IA sur le modèle du module conseils |
| Générer/Envoyer alerte automatique | Table `alertes` prête, alertes de démo visibles | Ajouter une tâche planifiée (cron) qui compare les relevés de capteurs à des seuils et insère des alertes + notifications |
| API géolocalisation / API notification push | Colonnes `latitude`/`longitude` prêtes | Brancher un fournisseur SMS/push pour convertir les notifications internes en envois réels |

## Structure du projet

```
st-agro/
├── config/database.php        Connexion PDO + clé API météo
├── includes/                  auth.php, functions.php, layout partagé
├── assets/css/style.css       Système de design (thème bleu clair)
├── assets/js/main.js          Interactions (menus, modales, notifications)
├── index.php / inscription.php / connexion.php / deconnexion.php / profil.php
├── agriculteur/                Espace Agriculteur
├── agronome/                   Espace Agronome
├── admin/                      Espace Administrateur
├── uploads/phytosanitaire/     Photos envoyées pour diagnostic
└── database.sql                Schéma + données de démonstration
```

## Sécurité déjà en place

- Mots de passe hachés avec `password_hash` (bcrypt).
- Requêtes SQL préparées (PDO) partout.
- Jeton CSRF sur tous les formulaires POST.
- Contrôle d'accès par rôle (`exigerRole`) sur chaque page de l'espace applicatif.
- Le dossier `uploads/` interdit l'exécution de scripts PHP.
