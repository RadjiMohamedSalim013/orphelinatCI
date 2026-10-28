# Système de Backup et Audit de Sécurité

## 📦 Backup Automatique

### Vue d'ensemble
Le système de backup automatique permet de sauvegarder régulièrement :
- **Base de données** (format SQL compressé)
- **Fichiers uploadés** (photos, documents PDF)

### Utilisation

#### 1. Commandes disponibles

```bash
# Créer une sauvegarde manuelle
php bin/console app:backup:database

# Créer une sauvegarde avec nettoyage automatique
php bin/console app:backup:database
```

#### 2. Interface Admin
Accédez à `/admin/backups` pour :
- Voir l'historique des sauvegardes
- Créer une nouvelle sauvegarde
- Nettoyer les anciennes sauvegardes
- Télécharger les sauvegardes

#### 3. Configuration automatique (Cron)

Ajoutez une tâche cron pour sauvegardes automatiques quotidiennes :

```bash
# Éditer le crontab
crontab -e

# Ajouter cette ligne (sauvegarde quotidienne à 2h du matin)
0 2 * * * cd /chemin/vers/projet && php bin/console app:backup:database >> var/logs/backup.log 2>&1
```

#### 4. Configuration du nettoyage

Les sauvegardes de plus de 30 jours sont automatiquement supprimées. Pour modifier cette durée, éditez le fichier `src/Service/BackupService.php` :

```php
public function cleanOldBackups(int $daysToKeep = 30): int
```

### Emplacement des sauvegardes
```
var/backups/
├── db_backup_2026-07-01_14-30-00.sql.gz
├── files_backup_2026-07-01_14-30-00.tar.gz
└── ...
```

---

## 🔍 Audit de Sécurité

### Vue d'ensemble
Le système d'audit de sécurité vérifie automatiquement :

1. **Permissions des fichiers** - Vérifie que les fichiers sensibles ont les bonnes permissions
2. **Fichiers uploadés** - Détecte les fichiers exécutables dans le dossier uploads
3. **Configuration de sécurité** - Vérifie APP_DEBUG, APP_SECRET, etc.
4. **Sauvegardes** - Vérifie la régularité des sauvegardes
5. **Utilisateurs inactifs** - Identifie les comptes inutilisés

### Utilisation

#### 1. Commandes disponibles

```bash
# Exécuter un audit basique
php bin/console app:security:audit

# Audit avec tentative de correction automatique
php bin/console app:security:audit --fix

# Audit avec rapport détaillé
php bin/console app:security:audit --report
```

#### 2. Interface Admin
Accédez à `/admin/security` pour :
- Voir les statistiques de sécurité
- Lancer un audit manuel
- Consulter l'historique des audits
- Marquer les problèmes comme résolus

#### 3. Configuration automatique (Cron)

Ajoutez une tâche cron pour audits automatiques hebdomadaires :

```bash
# Éditer le crontab
crontab -e

# Ajouter cette ligne (audit hebdomadaire le lundi à 3h)
0 3 * * 1 cd /chemin/vers/projet && php bin/console app:security:audit >> var/logs/audit.log 2>&1
```

### Niveaux de sévérité

- 🔴 **CRITICAL** - Problème nécessitant une action immédiate
- 🟠 **HIGH** - Problème important à résoudre rapidement
- 🟡 **MEDIUM** - Problème moyen, à planifier
- 🔵 **LOW** - Information ou amélioration suggérée

---

## 📊 Monitoring et Alertes

### Logs
Les logs sont disponibles dans :
- `var/logs/backup.log` - Logs des sauvegardes
- `var/logs/audit.log` - Logs des audits
- `var/logs/prod.log` - Logs généraux de l'application

### Base de données
Les résultats d'audit sont stockés dans la table `security_audit` :
- Consulter via l'interface admin
- Requêter directement pour des analyses personnalisées

---

## 🔧 Dépannage

### Problème de permissions
```bash
# Corriger les permissions des dossiers sensibles
chmod 640 .env
chmod 750 config/
chmod 750 src/
chmod 770 var/
```

### Problème de sauvegarde
Vérifier que `mysqldump` et `tar` sont installés :
```bash
# Ubuntu/Debian
sudo apt-get install mysql-client tar

# Vérifier la version
mysqldump --version
tar --version
```

### Problème d'audit
Si l'audit détecte des faux positifs, vous pouvez :
1. Marquer le problème comme résolu dans l'interface admin
2. Ajuster les règles de détection dans `SecurityAuditService.php`

---

## 📝 Bonnes Pratiques

1. **Sauvegardes**
   - Effectuez une sauvegarde avant chaque mise à jour
   - Stockez les sauvegardes sur un support externe
   - Testez régulièrement la restauration

2. **Audit de sécurité**
   - Lancez un audit au moins une fois par semaine
   - Résolvez les problèmes critiques immédiatement
   - Consultez les logs régulièrement

3. **Sécurité générale**
   - Ne jamais commiter le fichier `.env`
   - Utilisez des mots de passe forts
   - Maintenez Symfony à jour
   - Limitez les permissions des fichiers

---

## 🚀 Évolutions futures possibles

- [ ] Notification par email lors d'audits critiques
- [ ] Intégration avec des services de monitoring (Sentry, New Relic)
- [ ] Backup automatique vers le cloud (AWS S3, Google Drive)
- [ ] Scan de vulnérabilités avec des outils externes
- [ ] Génération de rapports PDF automatiques
- [ ] Alertes SMS pour les problèmes critiques

---

## 📞 Support

Pour toute question ou problème :
1. Consultez les logs dans `var/logs/`
2. Vérifiez la documentation Symfony
3. Contactez l'équipe de développement