# Présentation de l'API

Cette API, écrite en PHP, est basée sur la structure de l'API présentée dans le dépôt suivant :<br>
https://github.com/CNED-SLAM/rest_chocolatein<br>
Le readme de ce dépôt présente la structure de la base de l'API (rôle de chaque fichier) et comment l'exploiter.<br>
Les ajouts faits dans cette API ne concernent que les fichiers '.env' (qui contient les données sensibles d'authentification et d'accès à la BDD) et 'MyAccessBDD.php' (dans lequel de nouvelles fonctions ont été ajoutées pour répondre aux demandes de l'application).<br>

Cette API permet d'exécuter des requêtes SQL sur la BDD `mediatek86` créée avec le SGBD MySQL.  
Elle est sécurisée par une authentification HTTP "Basic Auth" avec :  
**login** = `admin`  
**mot de passe** = `adminpwd`

Elle est utilisée pour l'application de gestion documentaire **MediaTekDocuments** (C#) disponible ici :
https://github.com/stband/mediatekdocuments-app

---

# Installation de l'API en local

Pour installer et tester l'API sur votre poste local :

1. Installez les outils suivants (ou équivalent) :
   - Serveur local (par exemple WampServer)
   - IDE comme NetBeans, VS Code, ou Neovim (pour ceux qui savent utiliser `:q!`).
   - [Postman](https://www.postman.com/) pour tester les requêtes, ou simplement via `curl` dans un terminal.

2. Clonez ce dépôt ou téléchargez-le, puis placez le dossier dans `www` de WampServer.
   Renommez-le en `rest_mediatekdocuments`.

3. Si **Composer** n’est pas installé :
   - Téléchargez-le ici : https://getcomposer.org/Composer-Setup.exe
   - Puis lancez `composer install` dans le dossier de l’API pour générer le dossier `vendor`.

4. Importez le fichier `mediatek86.sql` dans phpMyAdmin :
   - Créez une base `mediatek86`
   - Exécutez le script pour insérer la structure et les données (dont quelques utilisateurs).

5. Dans **Postman**, configurez l'authentification dans chaque requête :
   - Onglet **Authorization**
   - **Type** : Basic Auth
   - **Username** : `admin`
   - **Password** : `adminpwd`

---

# Utilisation de l'API

Adresse locale de base :  
`http://localhost/rest_mediatekdocuments/`

Voici les différentes possibilités de sollicitation de l'API, afin d'agir sur la BDD, en ajoutant des informations directement dans l'URL (visible) et éventuellement dans le body (invisible) suivant les besoins : 

<h2>Récupérer un contenu (select)</h2>
Méthode HTTP : <strong>GET</strong><br>
http://localhost/rest_mediatekdocuments/table/champs (champs optionnel)
<ul>
   <li>'table' doit être remplacé par un nom de table (caractères acceptés : alphanumériques et '_')</li>
   <li>'champs' (optionnel) doit être remplacé par la liste des champs (nom/valeur) qui serviront à la recherche (au format json)</li>
</ul>

<h2>Insérer (insert)</h2>
Méthode HTTP : <strong>POST</strong><br>
http://localhost/rest_mediatekdocuments/table <br>
'table' doit être remplacé par un nom de table (caractères acceptés : alphanumériques et '_')<br>
Dans le body (Dans Postman, onglet 'Body', cocher 'x-www-form-urlencoded'), ajouter :<br>
<ul>
   <li>Key : 'champs'</li>
   <li>Value : liste des champs (nom/valeur) qui serviront à l'insertion (au format json)</li>
</ul>

<h2>Modifier (update)</h2>
Méthode HTTP : <strong>PUT</strong><br>
http://localhost/rest_mediatekdocuments/table/id (id optionnel)<br>
<ul>
   <li>'table' doit être remplacé par un nom de table (caractères acceptés : alphanumériques et '_')</li>
   <li>'id' (optionnel) doit être remplacé par l'identifiant de la ligne à modifier (caractères acceptés : alphanumériques)</li>
</ul>
Dans le body (Dans Postman, onglet 'Body', cocher 'x-www-form-urlencoded'), ajouter :<br>
<ul>
   <li>Key : 'champs'</li>
   <li>Value : liste des champs (nom/valeur) qui serviront à la modification (au format json)</li>
</ul>

<h2>Supprimer (delete)</h2>
Méthode HTTP : <strong>DELETE</strong><br>
http://localhost/rest_mediatekdocuments/table/champs (champs optionnel)<br>
<ul>
   <li>'table' doit être remplacé par un nom de table (caractères acceptés : alphanumériques et '_')</li>
   <li> 'champs' (optionnel) doit être remplacé par la liste des champs (nom/valeur) qui serviront déterminer les lignes à supprimer (au format json</li>
</ul>

---

# Comptes utilisateurs de démonstration

Voici les comptes disponibles par défaut pour tester l’application :

| Identifiant     | Mot de passe     | Service         | Accès autorisé |
|------------------|------------------|------------------|----------------|
| `admin`          | `admin`          | Administrateur   | Oui         |
| `pret`           | `pret`           | Prêts            | Oui         |
| `administratif`  | `administratif`  | Administratif    | Oui         |
| `culture`        | `culture`        | Culture          | Non         |

*Ces utilisateurs sont insérés par défaut via le script `mediatek86.sql`.  
Ils sont là uniquement pour tester l'application.*

## Ajouter vos propres utilisateurs

Il n'existe pas encore d’interface de création de comptes.
Pour le moment, l’ajout d’un utilisateur doit se faire manuellement dans la base de données.

Pour créer manuellement un utilisateur, insérez une nouvelle ligne dans la table `utilisateur`.  
Le mot de passe doit être **haché** avec l'algorithme `bcrypt`.

### Exemple de génération du hash :

En ligne de commande (si PHP installé) :

```bash
php -r "echo password_hash('votreMotDePasse', PASSWORD_BCRYPT) . PHP_EOL;"
```

En ligne :

- https://bcrypt-generator.com/
