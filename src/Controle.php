<?php
header('Content-Type: application/json');

include_once("MyAccessBDD.php");

/**
 * Contrôleur : reçoit et traite les demandes du point d'entrée
 */
class Controle{
	
    /**
     * 
     * @var MyAccessBDD
     */
    private $myAaccessBDD;

    /**
     * constructeur : récupère l'instance d'accès à la BDD
     */
    public function __construct(){
        try{
            $this->myAaccessBDD = new MyAccessBDD();
        }catch(Exception $e){
            $this->reponse(500, "erreur serveur");
            die();
        }
    }

    /**
     * réception d'une demande de requête
     * demande de traiter la requête puis demande d'afficher la réponse
     * @param string $methodeHTTP
     * @param string $table
     * @param string|null $id
     * @param array|null $champs
     */
    public function demande(string $methodeHTTP, string $table, ?string $id, ?array $champs){
        try {
            $result = $this->myAaccessBDD->demande($methodeHTTP, $table, $id, $champs);
            $this->controleResult($result);
        } catch (Exception $e) {
            $this->reponse(500, "Erreur serveur", ($_ENV['APP_ENV'] ?? 'prod') === 'dev' ? $e->getMessage() : null);
        }
    }

    /**
     * réponse renvoyée (affichée) au client au format json
     * @param int $code code standard HTTP (200, 500, ...)
     * @param string $message message correspondant au code
     * @param array|int|string|null $result
     */
    private function reponse(int $code, string $message, array|int|string|null $result=""){
        $retour = array(
            'code' => $code,
            'message' => $message,
            'result' => $result
        );
        echo json_encode($retour, JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * contrôle si le résultat n'est pas null
     * demande l'affichage de la réponse adéquate
     * @param array|int|null $result résultat de la requête
     */
    private function controleResult(array|int|null $result) {
        if (!is_null($result)){
            $this->reponse(200, "OK", $result);
        }else{	
            $this->reponse(400, "requete invalide");
        }        
    }
	
    /**
     * authentification incorrecte
     * demande d'afficher un messaage d'erreur
     */
    public function unauthorized(){
        $this->reponse(401, "authentification incorrecte");
    }

    /**
     * Vérifie la connexion de l'utilisateur.
     *
     * La méthode selectUtilisateurByLogin pour récupérer les informations de l'utilisateur
     * correspondant au login fourni. La vérification du mot de passe est effectuée à l'aide de password_verify().
     *
     * Si l'utilisateur n'est pas trouvé ou si le mot de passe ne correspond pas, la méthode envoie une réponse non autorisée.
     * 
     * En cas de succès, le hash du mot de passe est supprimé de la réponse et une réponse avec le code HTTP 200,
     * accompagnée d'un message de confirmation et des informations de l'utilisateur (sans le mot de passe), est renvoyée.
     *
     * Ce qui permet à l'application de stocker des informations sur la session actuelle et l'utilisateur.
     *
     * @param array|null $champs Un tableau contenant les champs de connexion ("login" et "mdp").
     */
        public function verifierConnexion(?array $champs) {
        if (!isset($champs["login"]) || !isset($champs["mdp"])) {
            $this->reponse(400, "Données d'authentification manquantes");
            return;
        }

        // On apppel la méthode selectUtilisateurByLogin qui va s'occuper de faire la requête select.
        $utilisateur = $this->myAaccessBDD->selectUtilisateurByLogin($champs["login"]);

        if (!$utilisateur || !password_verify($champs["mdp"], $utilisateur[0]["motdepasse"])) {
            $this->unauthorized();
            return;
        }

        // Supprimer le hash du mot de passe de la réponse.
        unset($utilisateur[0]["motdepasse"]);

        $this->reponse(200, "Connexion réussie", $utilisateur[0]);
    }
    
}
