<?php
include_once("AccessBDD.php");

/**
 * Classe de construction des requêtes SQL
 * Hérite de AccessBDD qui contient les requêtes de base
 * Pour ajouter une requête :
 * - créer la fonction qui crée une requête (prendre modèle sur les fonctions 
 *   existantes qui ne commencent pas par 'traitement')
 * - ajouter un 'case' dans un des switch des fonctions redéfinies 
 * - appeler la nouvelle fonction dans ce 'case'
 */
class MyAccessBDD extends AccessBDD {
	    
    /**
     * constructeur qui appelle celui de `AccessBDD`.
     */
    public function __construct(){
        try{
            parent::__construct();
        }catch(\Exception $e){
            throw $e;
        }
    }

    /**
     * Demande de recherche (select)
     * @param string $table
     * @param array|null $champs nom et valeur de chaque champ
     * @return array|null tuples du résultat de la requête ou null si erreur
     * @override
     */	
    protected function traitementSelect(string $table, ?array $champs) : ?array{
        switch($table){  
            case "livre" :
                return $this->selectAllLivres();
            case "dvd" :
                return $this->selectAllDvd();
            case "revue" :
                return $this->selectAllRevues();
            case "exemplaire" :
                return $this->selectExemplairesRevue($champs);
            case "genre" :
            case "public" :
            case "rayon" :
            case "etat" :
                // select portant sur une table contenant juste id et libelle
                return $this->selectTableSimple($table);
            case "commande" :
                // toutes les commandes livres et DVD
                return $this->selectAllCommandes();
            case "commandeparid" :
                // toutes les commandes liées à un id livres ou DVD
                return $this->selectAllCommandesByIdLivreDvd($champs);
			case "abonnement" :
				return $this->selectAllAbonnements();
            default:
                // cas général
                return $this->selectTuplesOneTable($table, $champs);
        }	
    }

    /**
     * Demande d'ajout (insert)
     * @param string $table
     * @param array|null $champs nom et valeur de chaque champ
     * @return int|null nombre de tuples ajoutés ou null si erreur
     * @override
     */	
    protected function traitementInsert(string $table, ?array $champs) : ?int{
        switch($table){
            case "commandetotale":
                return $this->insertCommande($champs);
			case "abonnement":
				return $this->insertAbonnement($champs);
            default:                    
                // cas général
                return $this->insertOneTupleOneTable($table, $champs);	
        }
    }
    
    /**
     * Demande de modification (update)
     * @param string $table
     * @param string|null $id
     * @param array|null $champs nom et valeur de chaque champ
     * @return int|null nombre de tuples modifiés ou null si erreur
     * @override
     */	
    protected function traitementUpdate(string $table, ?string $id, ?array $champs) : ?int{
        switch($table){
            case "" :
                // return $this->uneFonction(parametres);
            default:                    
                // cas général
                return $this->updateOneTupleOneTable($table, $id, $champs);
        }	
    }  
    
    /**
     * Demande de suppression (delete)
     * @param string $table
     * @param array|null $champs nom et valeur de chaque champ
     * @return int|null nombre de tuples supprimés ou null si erreur
     * @override
     */	
    protected function traitementDelete(string $table, ?array $champs) : ?int{
        switch($table){
            case "abonnement" :
                return $this->deleteAbonnementByID($champs);
            default:                    
                // cas général
                return $this->deleteTuplesOneTable($table, $champs);	
        }
    }	    

    /**
     * Demande de suppression (DELETE) d'un abonnement (et des données associées) via une procédure stockée.
     *
     * Cette méthode exécute la procédure stockée "supprimerAbonnement" qui supprime un abonnement
     * ainsi que ses données associées dans la base de données.
     *
     *Exemple du format final de la requête :
     *  CALL supprimerAbonnement(:id);
     *
     * @param array $champs Tableau associatif contenant la clé "id" et sa valeur, par exemple : ["id" => "00001"].
     * @return int|null Renvoie 1 en cas de succès, ou `null` si la clé "id" n'est pas présente ou en cas d'erreur.
     */
    private function deleteAbonnementByID(array $champs): ?int {
        if (!isset($champs["id"])) {
            return null;
        }

        try {
            $requete = "CALL supprimerAbonnement(:id)";
            return $this->conn->updateBDD($requete, ["id" => $champs["id"]]);
        } catch (Exception $e) {
            return null;
        }
    }
 
    /**
     * Demande de suppression (DELETE) d'un ou plusieurs tuples dans une table.
     *
     * Cette méthode construit dynamiquement une requête SQL pour supprimer des enregistrements
     * dans la table spécifiée.
     *
     * Le paramètre `$champs` est un tableau associatif qui définit les critères de suppression,
     * chaque élément du tableau correspondant à une condition de type "colonne = valeur".
     *
     * Si le tableau `$champs` est vide ou null, la méthode retourne `null` pour éviter toute suppression 
     * non contrôlée.
     *
     * * Exemple du format final de la requête :
     *   DELETE FROM nom_table WHERE key = :value[AND key2 = :value2[...]];
     *
     * @param string $table Le nom de la table dans laquelle les tuples doivent être supprimés.
     * @param array|null $champs Un tableau associatif définissant les critères de suppression.
     *                           Si ce paramètre est null ou vide, aucun tuple n'est supprimé.
     * @return int|null Le nombre de tuples supprimés, ou `null` en cas d'erreur ou si aucun critère
     *                  n'a été fourni.
     */
    private function deleteTuplesOneTable(string $table, ?array $champs) : ?int{
        if(empty($champs)){
            return null;
        }
        // Construction de la requête.
        $requete = "delete from $table where ";
        foreach ($champs as $key => $value){
            $requete .= "$key=:$key and ";
        }
        // Retire le dernier "AND " ajouté à la fin de la requête.
        $requete = substr($requete, 0, strlen($requete)-5);   

        return $this->conn->updateBDD($requete, $champs);	        
    }

    /**
     * Génère un nouvel ID au format texte (zéro-rempli) pour une table donnée.
     *
     * Cette méthode constitue la base de l'incrémentation des identifiants. Elle est
     * appelée par toutes les fonctions responsables de la création d'un nouvel enregistrement.
     *
     * Processus :
     *   1. Récupère l'ID existant au format varchar(5).
     *   2. Convertit l'ID en entier (int) pour permettre l'opération mathématique.
     *   3. Incrémente l'ID de 1.
     *   4. Reconvertion de l'ID incrémenté en varchar(5) afin de conserver le format standard.
     *
     * @param string $nomTable Nom de la table (ex: "commande")
     * @param int $taille Nombre total de caractères attendus (par défaut 5)
     * @return string|null Le nouvel ID incrémenté au format varchar(5) (ex: "00015") ou null si erreur
     */
    private function genererNouvelId(string $nomTable, int $taille = 5) : ?string {
        try {
            $requete = "SELECT MAX(CAST(id AS UNSIGNED)) AS maxId FROM $nomTable";
            $resultat = $this->conn->queryBDD($requete);
            $nouvelID = (int)$resultat[0]['maxId'] + 1;
            return str_pad($nouvelID, $taille, "0", STR_PAD_LEFT);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
    * Crée un abonnement complet via la procédure stockée `creerAbonnement`.
    *
    * @param array $champs Tableau contenant les champs suivants :
    *   - idRevue
    *   - dateCommande
    *   - dateFinAbonnement
    *   - montant
    *   - nbExemplaire
    * @return int|null Retourne 1 si succès, null sinon
    */
    private function insertAbonnement(array $champs): ?int {

        $idCommande = $this->genererNouvelId("commande");

        $params = [
            "p_idCommande" => $idCommande,
            "p_idRevue" => $champs["idRevue"],
            "p_dateCommande" => $champs["dateCommande"],
            "p_dateFinAbonnement" => $champs["dateFinAbonnement"],
            "p_montant" => $champs["montant"],
            "p_nbExemplaire" => $champs["nbExemplaire"]
        ];

        try {
            $requete = "CALL creerAbonnement(:p_idCommande, :p_idRevue, :p_dateCommande, :p_dateFinAbonnement, :p_montant, :p_nbExemplaire)";
            return $this->conn->updateBDD($requete, $params);
        } catch (Exception $e) {
            return null;
        }
   }

    /**
     * Insère une commande complète dans la base de données.
     *
     * Cette méthode réalise l'insertion d'une commande dans deux tables distinctes : 
     * - La table "commande", pour enregistrer les informations principales (ID, date de commande, montant).
     * - La table "commandedocument", pour enregistrer les détails complémentaires (nombre d'exemplaires, 
     *   identifiant du livre/DVD, et un identifiant de suivi fixé à "00001").
     *
     * Processus :
     *   1. Génération d'un nouvel ID pour la commande via la méthode genererNouvelId.
     *   2. Insertion des lignes dans les tables distinctes. Par défaut l'ID de suivi est préféfini sur ("00001")
     *   3. Si les deux insertions réussissent (retour de 1 pour chacune), la méthode retourne 1.
     *      Sinon, ou en cas d'exception, la méthode retourne null.
     *
     * @param array $champs Tableau associatif contenant les champs nécessaires :
     *                      - 'dateCommande' : Date de la commande.
     *                      - 'montant' : Montant total de la commande.
     *                      - 'nbExemplaire' : Nombre d'exemplaires commandés.
     *                      - 'idLivreDvd' : Identifiant du livre ou DVD concerné.
     * @return int|null Retourne 1 en cas de succès ou null si une des insertions échoue ou si une exception est levée.
     */
        private function insertCommande(array $champs) : ?int {
        try {
            // Générer nouvel ID de commande
            $nouvelId = $this->genererNouvelId("commande");

            // Insertion dans la table commande
            $requeteCommande = $this->insertOneTupleOneTable("commande", [
                "id" => $nouvelId,
                "dateCommande" => $champs["dateCommande"],
                "montant" => $champs["montant"]
            ]);

            // Insertion dans la table commandedocument
            $requeteCommandeDocument = $this->insertOneTupleOneTable("commandedocument", [
                "id" => $nouvelId,
                "nbExemplaire" => $champs["nbExemplaire"],
                "idLivreDvd" => $champs["idLivreDvd"],
                "idSuivi" => "00001"
            ]);

            // Vérifie que les deux commandes soient un succès
            if ($requeteCommande === 1 && $requeteCommandeDocument === 1) {
                return 1;
            } else {
                return null;
            }

        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Demande d'insertion (INSERT) d'un tuple dans une table.
     *
     * Cette méthode construit dynamiquement une requête SQL pour insérer un enregistrement
     * dans la table spécifiée. Le tableau associatif `$champs` contient les colonnes et leurs valeurs
     * respectives.  
     *
     * - Si le paramètre `$champs` est vide, la méthode retourne `null` car il n'y a aucune donnée à insérer.
     * - Sinon, elle construit une requête `INSERT INTO` en listant les colonnes et en associant des marqueurs nommés aux valeurs.
     *
     * @param string $table Le nom de la table dans laquelle insérer le tuple.
     * @param array|null $champs Un tableau associatif où chaque clé représente le nom d'une colonne et
     *                           la valeur correspondante à insérer.
     * @return int|null Retourne le nombre de tuples ajoutés (généralement 0 ou 1) ou `null` en cas d'erreur.
     */
    private function insertOneTupleOneTable(string $table, ?array $champs) : ?int{
        if(empty($champs)){
            return null;
        }
        // Construction de la requête et ajout des colonnes.
        $requete = "insert into $table (";
        foreach ($champs as $key => $value){
            $requete .= "$key,";
        }
        // Supprime la dernière virgule.
        $requete = substr($requete, 0, strlen($requete)-1);

        // Ajoute les valeurs.
        $requete .= ") values (";
        foreach ($champs as $key => $value){
            $requete .= ":$key,";
        }
        // Supprime la dernière virgule.
        $requete = substr($requete, 0, strlen($requete)-1);
        $requete .= ");";

        return $this->conn->updateBDD($requete, $champs);
    }

    /**
     * Récupère les tuples de la table des abonnements et des tables associées.
     *
     * @return array|null Un tableau contenant les enregistrements complets représentant les Abonnements, ou `null` en cas d'erreur.
     */
    private function selectAllAbonnements() : ?array {
        $requete = "SELECT a.id AS idAbonnement, a.idRevue, d.titre AS titreRevue, ";
        $requete .= "c.dateCommande, a.dateFinAbonnement, c.montant ";
        $requete .= "FROM abonnement a ";
        $requete .= "JOIN commande c ON a.id = c.id ";
        $requete .= "JOIN commandedocument cd ON c.id = cd.id ";
        $requete .= "JOIN revue r ON a.idRevue = r.id ";
        $requete .= "JOIN document d ON r.id = d.id ";
        $requete .= "ORDER BY c.dateCommande DESC;";		

        return $this->conn->queryBDD($requete);
    }

    /**
     * Récupère les tuples de la table des commandes et des tables associées.
     *
     * @return array|null Un tableau contenant les enregistrements complets représentant les Commandes, ou `null` en cas d'erreur.
     */
    private function selectAllCommandes() : ?array {
        $requete = "SELECT c.id AS idCommande, cd.idLivreDvd, d.titre, c.dateCommande, ";
        $requete .= "c.montant, cd.nbExemplaire, s.libelle AS statut ";
        $requete .= "FROM commande c ";
        $requete .= "JOIN commandedocument cd ON c.id = cd.id ";
        $requete .= "JOIN document d ON cd.idLivreDvd = d.id ";
        $requete .= "JOIN suivi s ON cd.idSuivi = s.id ";
        $requete .= "ORDER BY c.dateCommande DESC;";

        return $this->conn->queryBDD($requete);
    }

    /**
     * Récupère les tuples de la table des commandes et des tables associées en fonction de l'identifiant "idLivreDvd".
     *
     * @param array $champs Un tableau associatif contenant au minimum la clé "idLivreDvd". Exemple : ["idLivreDvd" => "00001"].
     * @return array|null Un tableau contenant les enregistrements correspondant aux commandes filtrées, ou null en cas d'erreur.
     * @throws Exception Si le paramètre "idLivreDvd" est invalide.
     */
    private function selectAllCommandesByIdLivreDvd(array $champs) : ?array {
        if(!array_key_exists('idLivreDvd', $champs)){
            throw new Exception("Paramètre invalide : la clé 'idLivreDvd' est requise.");
        }

        $requete  = "SELECT c.id AS idCommande, c.dateCommande, c.montant, cd.nbExemplaire, s.libelle AS statut ";
        $requete .= "FROM commande c ";
        $requete .= "JOIN commandedocument cd ON c.id = cd.id ";
        $requete .= "JOIN suivi s ON cd.idSuivi = s.id ";
        $requete .= "WHERE cd.idLivreDvd = :idLivreDvd ";
        $requete .= "ORDER BY c.dateCommande DESC;";

        return $this->conn->queryBDD($requete, ['idLivreDvd' => $champs['idLivreDvd']]);
    }

    /**
     * Récupère les tuples de la table DVD et des tables associées.
     *
     * @return array|null Un tableau contenant les enregistrements complets représentant les DVD, ou `null` en cas d'erreur.
     */
    private function selectAllDvd() : ?array{
        $requete = "Select dvd.id, dvd.duree, dvd.realisateur, d.titre, d.image, dvd.synopsis, ";
        $requete .= "d.idrayon, d.idpublic, d.idgenre, g.libelle as genre, p.libelle as lePublic, r.libelle as rayon ";
        $requete .= "from dvd join document d on dvd.id=d.id ";
        $requete .= "join genre g on g.id=d.idGenre ";
        $requete .= "join public p on p.id=d.idPublic ";
        $requete .= "join rayon r on r.id=d.idRayon ";
        $requete .= "order by titre ";	

        return $this->conn->queryBDD($requete);
    }	

    /**
     * Récupère les tuples de la table Livre et des tables associées.
     *
     * @return array|null Un tableau contenant les enregistrements complets représentant les Livres, ou `null` en cas d'erreur.
     */
    private function selectAllLivres() : ?array{
        $requete = "Select l.id, l.ISBN, l.auteur, d.titre, d.image, l.collection, ";
        $requete .= "d.idrayon, d.idpublic, d.idgenre, g.libelle as genre, p.libelle as lePublic, r.libelle as rayon ";
        $requete .= "from livre l join document d on l.id=d.id ";
        $requete .= "join genre g on g.id=d.idGenre ";
        $requete .= "join public p on p.id=d.idPublic ";
        $requete .= "join rayon r on r.id=d.idRayon ";
        $requete .= "order by titre ";		

        return $this->conn->queryBDD($requete);
    }	

    /**
     * Récupère les tuples de la table Revue et des tables associées.
     *
     * @return array|null Un tableau contenant les enregistrements complets représentant les Revues, ou `null` en cas d'erreur.
     */
    private function selectAllRevues() : ?array{
        $requete = "Select l.id, l.periodicite, d.titre, d.image, l.delaiMiseADispo, ";
        $requete .= "d.idrayon, d.idpublic, d.idgenre, g.libelle as genre, p.libelle as lePublic, r.libelle as rayon ";
        $requete .= "from revue l join document d on l.id=d.id ";
        $requete .= "join genre g on g.id=d.idGenre ";
        $requete .= "join public p on p.id=d.idPublic ";
        $requete .= "join rayon r on r.id=d.idRayon ";
        $requete .= "order by titre ";

        return $this->conn->queryBDD($requete);
    }	

    /**
     * Récupère tous les exemplaires d'une revue via son id.
     *
     * Le filtrage s'effectue grâce à la clé "id" fournie dans le tableau des paramètres ($champs), 
     * qui identifie l'exemplaire ou la revue recherchée.
     *
     * @param array|null $champs Un tableau associatif contenant au minimum la clé "id".
     * @return array|null Un tableau contenant les exemplaires correspondants ou `null` en cas d'erreur interne.
     * @throws Exception Si aucun paramètre n'est fourni, ou si le paramètre "id" est absent.
     */
    private function selectExemplairesRevue(?array $champs) : ?array{
        if(empty($champs)){
            throw new Exception("Paramètres manquants : Aucun paramètre fourni. Veuillez fournir le paramètre 'id'.");
        }
        if(!array_key_exists('id', $champs)){
            throw new Exception("Paramètre invalide : la clé 'id' est requise.");
        }
        $champNecessaire['id'] = $champs['id'];
        $requete = "Select e.id, e.numero, e.dateAchat, e.photo, e.idEtat ";
        $requete .= "from exemplaire e join document d on e.id=d.id ";
        $requete .= "where e.id = :id ";
        $requete .= "order by e.dateAchat DESC";

        return $this->conn->queryBDD($requete, $champNecessaire);
    }		    

    /**
     * Récupère toutes les lignes d'une table simple.
     *
     * Cette méthode exécute une requête SQL pour récupérer tous les enregistrements d'une table
     * qui contient uniquement les colonnes "id" et "libelle". Les résultats sont triés par la colonne "libelle".
     *
     * * Exemple du format final de la requête :
     *     SELECT * FROM nom_table ORDER BY libelle;
     *
     * @param string $table Le nom de la table à interroger.
     * @return array|null Un tableau contenant toutes les lignes de la table, ou `null` en cas d'erreur.
     */
    private function selectTableSimple(string $table) : ?array{
        $requete = "select * from $table order by libelle;";		
        return $this->conn->queryBDD($requete);	    
    }
 
    /**
     * Récupère les tuples de la table spécifiée en fonction des critères.
     *
     * Deux cas de figure sont gérés :
     * - Si le paramètre $champs est vide ou nul, tous les tuples de la table
     *   seront sélectionnés.
     * - Si $champs contient des critères, la méthode construit une clause WHERE
     *   en associant chaque clé du tableau aux paramètres correspondants.
     *
     * @param string $table Le nom de la table sur laquelle exécuter la requête.
     * @param array|null $champs Un tableau associatif des critères de filtrage où la clé
     *                           représente le nom d'une colonne et la valeur le critère correspondant.
     * @return array|null Un tableau contenant les tuples de la table, ou null en cas d'erreur.
     */
    private function selectTuplesOneTable(string $table, ?array $champs) : ?array{
        if(empty($champs)){
            // Récupération de tous les tuples de la table. 
            $requete = "select * from $table;";
            return $this->conn->queryBDD($requete);  
        }else{
            // Construction dynamique de la requête pour filtrer les tuples selon les critères spécifiés.
            $requete = "select * from $table where ";
            foreach ($champs as $key => $value){
                $requete .= "$key=:$key and ";
            }
            // Suppression du dernier 'AND ' ajouté à la fin de la requête.
            $requete = substr($requete, 0, strlen($requete)-5);	          
            return $this->conn->queryBDD($requete, $champs);
        }
    }	

    /**
     * Sélectionne les informations d'un utilisateur en fonction de son login.
     *
     * @param string $login Le login de l'utilisateur.
     * 
     * @return array|null Un tableau associatif contenant les données de l'utilisateur et du service,
     *                    ou null si aucun utilisateur n'a été trouvé.
     */
    public function selectUtilisateurByLogin(string $login): ?array {
    $requete = "SELECT u.id, u.nom, u.login, u.motdepasse, s.libelle AS service ";
    $requete .= "FROM utilisateur u ";
    $requete .= "JOIN service s ON u.idService = s.id ";
    $requete .= "WHERE u.login = :login";

    return $this->conn->queryBDD($requete, ["login" => $login]);
    }

    /**
     * Demande de modification (UPDATE) d'un tuple dans une table.
     *
     * Cette méthode construit dynamiquement une requête SQL pour mettre à jour les valeurs d'un tuple
     * dans la table spécifiée. Les colonnes à modifier et leurs nouvelles valeurs sont fournies via le tableau
     * associatif `$champs`, et le tuple ciblé est identifié grâce à l'identifiant `$id`.
     *
     * * Exemple du format final de la requête :
     *     UPDATE nom_table SET colonne1 = :colonne1, colonne2 = :colonne2[,...] WHERE id = :id;
     *
     * @param string $table Le nom de la table dans laquelle le tuple doit être mis à jour.
     * @param string|null $id L'identifiant du tuple à mettre à jour.
     * @param array|null $champs Un tableau associatif contenant les colonnes à mettre à jour et les nouvelles valeurs.
     *                           Exemple : ["colonne1" => "nouvelleValeur1", "colonne2" => "nouvelleValeur2"].
     * @return int|null Le nombre de tuples modifiés (0 ou 1) ou `null` en cas d'erreur ou de paramètres manquants.
     */	
    private function updateOneTupleOneTable(string $table, ?string $id, ?array $champs) : ?int {
        if(empty($champs)){
            return null;
        }

        if(is_null($id)){
            return null;
        }

        // Construction de la requête.
        $requete = "update $table set ";
        foreach ($champs as $key => $value){
            $requete .= "$key=:$key,";
        }

        // Supprime la dernière virgule.
        $requete = substr($requete, 0, strlen($requete)-1);				

        // Ajoute la condition sur l'identifiant.
        $champs["id"] = $id;
        $requete .= " where id=:id;";		

        return $this->conn->updateBDD($requete, $champs);	        
    }
   
}
