<?php
include_once("AccessBDD.php");

/**
 * Classe de construction des requêtes SQL
 * hérite de AccessBDD qui contient les requêtes de base
 * Pour ajouter une requête :
 * - créer la fonction qui crée une requête (prendre modèle sur les fonctions 
 *   existantes qui ne commencent pas par 'traitement')
 * - ajouter un 'case' dans un des switch des fonctions redéfinies 
 * - appeler la nouvelle fonction dans ce 'case'
 */
class MyAccessBDD extends AccessBDD {
	    
    /**
     * constructeur qui appelle celui de la classe mère
     */
    public function __construct(){
        try{
            parent::__construct();
        }catch(\Exception $e){
            throw $e;
        }
    }

    /**
     * demande de recherche
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
            case "commandes" :
                // toutes les commandes livres et DVD
                return $this->selectAllCommandes();
            case "commandesparid" :
                // toutes les commandes liées à un id livres ou DVD
                return $this->selectCommandesByIdLivreDvd($champs);
            case "" :
                // return $this->uneFonction(parametres);
            default:
                // cas général
                return $this->selectTuplesOneTable($table, $champs);
        }	
    }

    /**
     * demande d'ajout (insert)
     * @param string $table
     * @param array|null $champs nom et valeur de chaque champ
     * @return int|null nombre de tuples ajoutés ou null si erreur
     * @override
     */	
    protected function traitementInsert(string $table, ?array $champs) : ?int{
        switch($table){
            case "commandetotale":
                // return $this->uneFonction(parametres);
                return $this->insertCommandeComplete($champs);
            default:                    
                // cas général
                return $this->insertOneTupleOneTable($table, $champs);	
        }
    }
    
    /**
     * demande de modification (update)
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
     * demande de suppression (delete)
     * @param string $table
     * @param array|null $champs nom et valeur de chaque champ
     * @return int|null nombre de tuples supprimés ou null si erreur
     * @override
     */	
    protected function traitementDelete(string $table, ?array $champs) : ?int{
        switch($table){
            case "" :
                // return $this->uneFonction(parametres);
            default:                    
                // cas général
                return $this->deleteTuplesOneTable($table, $champs);	
        }
    }	    
        
    /**
     * récupère les tuples d'une seule table
     * @param string $table
     * @param array|null $champs
     * @return array|null 
     */
    private function selectTuplesOneTable(string $table, ?array $champs) : ?array{
        if(empty($champs)){
            // tous les tuples d'une table
            $requete = "select * from $table;";
            return $this->conn->queryBDD($requete);  
        }else{
            // tuples spécifiques d'une table
            $requete = "select * from $table where ";
            foreach ($champs as $key => $value){
                $requete .= "$key=:$key and ";
            }
            // (enlève le dernier and)
            $requete = substr($requete, 0, strlen($requete)-5);	          
            return $this->conn->queryBDD($requete, $champs);
        }
    }	

    /**
     * demande d'ajout (insert) d'un tuple dans une table
     * @param string $table
     * @param array|null $champs
     * @return int|null nombre de tuples ajoutés (0 ou 1) ou null si erreur
     */	
    private function insertOneTupleOneTable(string $table, ?array $champs) : ?int{
        if(empty($champs)){
            return null;
        }
        // construction de la requête
        $requete = "insert into $table (";
        foreach ($champs as $key => $value){
            $requete .= "$key,";
        }
        // (enlève la dernière virgule)
        $requete = substr($requete, 0, strlen($requete)-1);
        $requete .= ") values (";
        foreach ($champs as $key => $value){
            $requete .= ":$key,";
        }
        // (enlève la dernière virgule)
        $requete = substr($requete, 0, strlen($requete)-1);
        $requete .= ");";
        return $this->conn->updateBDD($requete, $champs);
    }

    /**
     * demande de modification (update) d'un tuple dans une table
     * @param string $table
     * @param string\null $id
     * @param array|null $champs 
     * @return int|null nombre de tuples modifiés (0 ou 1) ou null si erreur
     */	
    private function updateOneTupleOneTable(string $table, ?string $id, ?array $champs) : ?int {
        if(empty($champs)){
            return null;
        }
        if(is_null($id)){
            return null;
        }
        // construction de la requête
        $requete = "update $table set ";
        foreach ($champs as $key => $value){
            $requete .= "$key=:$key,";
        }
        // (enlève la dernière virgule)
        $requete = substr($requete, 0, strlen($requete)-1);				
        $champs["id"] = $id;
        $requete .= " where id=:id;";		
        return $this->conn->updateBDD($requete, $champs);	        
    }
    
    /**
     * demande de suppression (delete) d'un ou plusieurs tuples dans une table
     * @param string $table
     * @param array|null $champs
     * @return int|null nombre de tuples supprimés ou null si erreur
     */
    private function deleteTuplesOneTable(string $table, ?array $champs) : ?int{
        if(empty($champs)){
            return null;
        }
        // construction de la requête
        $requete = "delete from $table where ";
        foreach ($champs as $key => $value){
            $requete .= "$key=:$key and ";
        }
        // (enlève le dernier and)
        $requete = substr($requete, 0, strlen($requete)-5);   
        return $this->conn->updateBDD($requete, $champs);	        
    }
 
    /**
     * récupère toutes les lignes d'une table simple (qui contient juste id et libelle)
     * @param string $table
     * @return array|null
     */
    private function selectTableSimple(string $table) : ?array{
        $requete = "select * from $table order by libelle;";		
        return $this->conn->queryBDD($requete);	    
    }
    
    /**
     * récupère toutes les lignes de la table Livre et les tables associées
     * @return array|null
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
     * récupère toutes les lignes de la table DVD et les tables associées
     * @return array|null
     */
    private function selectAllDvd() : ?array{
        $requete = "Select l.id, l.duree, l.realisateur, d.titre, d.image, l.synopsis, ";
        $requete .= "d.idrayon, d.idpublic, d.idgenre, g.libelle as genre, p.libelle as lePublic, r.libelle as rayon ";
        $requete .= "from dvd l join document d on l.id=d.id ";
        $requete .= "join genre g on g.id=d.idGenre ";
        $requete .= "join public p on p.id=d.idPublic ";
        $requete .= "join rayon r on r.id=d.idRayon ";
        $requete .= "order by titre ";	
        return $this->conn->queryBDD($requete);
    }	

    /**
     * récupère toutes les lignes de la table Revue et les tables associées
     * @return array|null
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
     * récupère tous les exemplaires d'une revue
     * @param array|null $champs 
     * @return array|null
     */
    private function selectExemplairesRevue(?array $champs) : ?array{
        if(empty($champs)){
            return null;
        }
        if(!array_key_exists('id', $champs)){
            return null;
        }
        $champNecessaire['id'] = $champs['id'];
        $requete = "Select e.id, e.numero, e.dateAchat, e.photo, e.idEtat ";
        $requete .= "from exemplaire e join document d on e.id=d.id ";
        $requete .= "where e.id = :id ";
        $requete .= "order by e.dateAchat DESC";
        return $this->conn->queryBDD($requete, $champNecessaire);
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
        private function insertCommandeComplete(array $champs) : ?int {
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
     * Récupère toutes les commandes enregistrées.
     *
     * Cette méthode exécute une requête SQL qui joint les tables "commande", "commandedocument", "document"
     * et "suivi" pour obtenir toutes les informations relatives aux commandes. Les données retournées
     * incluent l'identifiant de la commande, l'identifiant du document (livre ou DVD), le titre du document,
     * la date de la commande, le montant, le nombre d'exemplaires commandés ainsi que le libellé du statut.
     *
     * Les résultats sont triés par date de commande dans l'ordre décroissant pour afficher en premier
     * les commandes les plus récentes.
     *
     * @return array|null Un tableau contenant les commandes ou null.
     */
    private function selectAllCommandes() : ?array {
        $requete = "
            SELECT c.id AS idCommande, cd.idLivreDvd, d.titre, c.dateCommande, c.montant, cd.nbExemplaire, s.libelle AS statut
            FROM commande c
            JOIN commandedocument cd ON c.id = cd.id
            JOIN document d ON cd.idLivreDvd = d.id
            JOIN suivi s ON cd.idSuivi = s.id
            ORDER BY c.dateCommande DESC;
        ";
        return $this->conn->queryBDD($requete);
    }

    /**
     * Récupère les commandes pour un document spécifique (livre ou DVD).
     *
     * Cette méthode nécessite un tableau associatif contenant l'ID du document sous la clé "idLivreDvd".
     * Si cette clé n'est pas présente, la méthode retourne null. Sinon, elle exécute une requête SQL qui
     * joint les tables "commande", "commandedocument" et "suivi" afin d'obtenir les informations relatives aux commandes
     * du document spécifié. Les données récupérées incluent l'identifiant de la commande, la date de la commande,
     * le montant, le nombre d'exemplaires commandés ainsi que le libellé du statut. Les résultats sont triés par
     * date de commande dans l'ordre décroissant.
     *
     * @param array $champs Un tableau contenant au minimum la clé "idLivreDvd".
     * @return array|null Un tableau de commandes spécifiques ou null.
     */
    private function selectCommandesByIdLivreDvd(array $champs) : ?array {
        if (!isset($champs['idLivreDvd'])) return null;

        $requete = "
            SELECT c.id AS idCommande, c.dateCommande, c.montant, cd.nbExemplaire, s.libelle AS statut
            FROM commande c
            JOIN commandedocument cd ON c.id = cd.id
            JOIN suivi s ON cd.idSuivi = s.id
            WHERE cd.idLivreDvd = :idLivreDvd
            ORDER BY c.dateCommande DESC;
        ";
        return $this->conn->queryBDD($requete, ['idLivreDvd' => $champs['idLivreDvd']]);
    }
}
