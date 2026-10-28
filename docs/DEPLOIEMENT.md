# Mise en ligne gratuite : Render + Aiven MySQL

Durée : 20 à 30 minutes. Deux comptes gratuits sont nécessaires, sans carte bancaire.

| Service | Rôle | Offre gratuite |
|---|---|---|
| [Aiven](https://aiven.io) | Base MySQL 8 | 1 Go, connexion chiffrée |
| [Render](https://render.com) | Application (Docker) | Mise en veille après 15 min d’inactivité |

## 1. Base de données (Aiven)

1. Créez un compte sur aiven.io, puis **Create service → MySQL → Free plan**, région Europe.
2. Une fois le service démarré, ouvrez l’onglet **Overview** et notez : *Host*, *Port*, *User* (`avnadmin`), *Password*, *Database name* (`defaultdb`).
3. Téléchargez le **CA certificate** (bouton « Download » à côté de *CA certificate*).
4. Composez l’adresse de connexion (remplacez les valeurs ; encodez les caractères spéciaux du mot de passe, ex. `@` → `%40`) :

   ```
   mysql://avnadmin:MOT_DE_PASSE@HOTE:PORT/defaultdb?serverVersion=8.0.35&charset=utf8mb4
   ```

## 2. Application (Render)

1. Créez un compte sur render.com en vous connectant avec GitHub.
2. **New → Blueprint**, choisissez le dépôt `orphelinatCI` : Render lit `render.yaml` et prépare le service `orphamap-ci`.
3. Renseignez les variables demandées :
   - `DATABASE_URL` : l’adresse composée à l’étape 1.4 ;
   - `DATABASE_SSL_CA_PEM` : **le contenu complet** du certificat téléchargé (de `-----BEGIN CERTIFICATE-----` à `-----END CERTIFICATE-----`) ;
   - `DEFAULT_URI` : `https://orphamap-ci.onrender.com` (ou le nom attribué par Render).
4. Lancez le déploiement. La première construction prend 5 à 10 minutes. Au démarrage, le conteneur applique les migrations et ajoute les villes et communes de Côte d’Ivoire.

## 3. Premier administrateur

L’offre gratuite de Render ne donne pas accès au terminal : l’administrateur est créé au démarrage.

1. Dans Render → service → **Environment**, renseignez `ADMIN_EMAIL` et `ADMIN_PASSWORD` (12 caractères minimum, mot de passe unique).
2. Enregistrez : le service redémarre et crée le compte.
3. Connectez-vous sur `/login`, puis **supprimez la variable `ADMIN_PASSWORD`** (sinon le mot de passe serait réinitialisé à chaque redémarrage).

## 4. Référencement

1. [Google Search Console](https://search.google.com/search-console) → ajoutez la propriété avec l’adresse du site, validez par balise HTML ou fichier.
2. Soumettez le plan du site : `https://VOTRE-ADRESSE/sitemap.xml`.
3. Faites de même sur [Bing Webmaster Tools](https://www.bing.com/webmasters) (import possible depuis Google).

## Ce qu’il faut savoir sur l’offre gratuite

- **Mise en veille** : sans visite pendant 15 minutes, le site s’endort ; la visite suivante attend environ 30 à 50 secondes. Un service de surveillance gratuit (ex. UptimeRobot, appel de `/robots.txt` toutes les 10 minutes) limite ce délai.
- **Fichiers envoyés** : le disque de Render est effacé à chaque redémarrage. `FICHIERS_EN_BASE=1` copie chaque photo et document dans MySQL ; ils sont restaurés automatiquement à la première consultation.
- **Sessions** : un redémarrage déconnecte les administrateurs.
- **E-mails** : désactivés (`MAILER_DSN=null://null`). Pour notifier les proposants, renseignez un SMTP (ex. Brevo, offre gratuite) : `MAILER_DSN=smtp://identifiant:clé@smtp-relay.brevo.com:587`.

## Mises à jour

Chaque envoi sur la branche `master` du dépôt GitHub redéploie automatiquement le site (`autoDeploy`). Les migrations s’appliquent au démarrage.
