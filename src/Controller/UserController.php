<?php
// coffre-fort/src/Controller/UserController.php

namespace App\Controller;

use App\Model\UserRepository;
use App\Security\AuthService;
use Medoo\Medoo;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class UserController
{
    private UserRepository $users;
    private string $jwtSecret;
    private AuthService $auth;

    public function __construct(Medoo $db)
    {
        $this->users = new UserRepository($db);
        //$this->jwtSecret = getenv('JWT_SECRET') ?: 'default-secret'; //=> à mettre dans env!!!

        // Init du secret JWT (env ou param)
        $this->jwtSecret = $jwtSecret ?? ($_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?? '');
        $this->auth = new AuthService($db, $this->jwtSecret);

        if ($this->jwtSecret === '') {
            // possible de faire throw ici, mais je préfère debug clair
            error_log("JWT_SECRET manquant dans les variables d'environnement.");
        }
    }

    private function json(Response $response, array $data, int $status): Response{
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    

    // POST /auth/register - Inscription d'un nouvel utilisateur ********************************************************** OK
    public function register(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        // Validation des champs requis
        if (!isset($body['email']) || !isset($body['password'])) {
            return $this->json($response, ['error' => 'Email et un mot de passe sont requis'], 400);
        }

        // Validation et nettoyage de l'email
        $email = trim($body['email']);

        //longeur maximal pour éviter les attaques par déni de service
        if (strlen($email) > 255) {
            return $this->json($response, ['error' => 'Email trop long (maximum 255 caractères)'], 400);
        }

        // format email valide (RFC 5322 via FILTER_VALIDATE_EMAIL)
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json($response, ['error' => "Format d'e-mail invalide"], 400);
        }

        // normalisation  => lowercase pour éviter les doublons
        $email = strtolower($email);

        // Validation du mot de passe 
        $password = $body['password'];

        // min. 12 caractères
        if (strlen($password) < 12) {
            return $this->json($response, ['error' => 'Le mot de passe doit comporter au moins 12 caractères'], 400);
        }

        // longueur maximale => éviter les attaques bcrypt avec mots de passe géants
        if (strlen($password) > 128) {
            return $this->json($response, ['error' => 'Le mot de passe ne peut pas dépasser 128 caractères'], 400);
        }

        // au moins une lettre majuscule
        if (!preg_match('/[A-Z]/', $password)) {
            return $this->json($response, ['error' => 'Le mot de passe doit contenir au moins une lettre majuscule'], 400);
        }

        // au moins une lettre minuscule
        if (!preg_match('/[a-z]/', $password)) {
            return $this->json($response, ['error' => 'Le mot de passe doit contenir au moins une lettre minuscule'], 400);
        }

        // au moins un chiffre
        if (!preg_match('/[0-9]/', $password)) {
            return $this->json($response, ['error' => 'Le mot de passe doit contenir au moins un chiffre'], 400);
        }

        // au moins un caractère spécial
        if (!preg_match('/[\W_]/', $password)) {
            return $this->json($response, ['error' => 'Le mot de passe doit contenir au moins un caractère spécial (!@#$%^&*...)'], 400);
        }


        // Vérifier si l'email existe déjà
        if ($this->users->findByEmail($email)) {
            return $this->json($response, ['error' => 'Email existe deja'], 409);
        }

        $isFirstUser = ($this->users->countUsers() === 0);
        $isAdmin = $isFirstUser; //=> true pour le premier utilisateur

        // Créer l'utilisateur
        $userData = [
            'email'         => $email,
            // 'pass_hash'     => password_hash($password, PASSWORD_DEFAULT),
            'pass_hash'     => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), // bcrypt avec un coût de 12 (plus sécurisé que le défaut de 10)
            'quota_used'    => 0,
            'quota_total'   => isset($body['quota_total']) ? (int)$body['quota_total'] : 1073741824, // 1GB par défaut
            // 'quota_total'   => isset($body['quota_total']) ? (int)$body['quota_total'] : 31457280, // 30 Mo par défaut pour tests
            'is_admin'      => $isAdmin,
            'created_at'    => date('Y-m-d')
        ];

        $id = $this->users->create($userData);

        $response->getBody()->write(json_encode([
            'message'   => 'Utilisateur créé',
            'id'        => $id,
            'email'     => $email,
            'is_admin'  => $isAdmin
        ], JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }


    // POST /auth/login - Authentifie un utilisateur et retourne un JWT ***************************************************** OK
    public function login(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        // Vérification des champs requis
        if (!isset($body['email']) || !isset($body['password'])) {
            return $this->json($response, ['error' => 'Email et un mot de passe sont requis'], 400);
        }

        //validation et nettoyage de l'email
        $email = trim($body['email'] ?? '');

        if (strlen($email) > 255) {
            return $this->json($response, ['error' => 'Email trop long'], 400);
        }

        // Validation basique pour éviter les caractères comme < ou >
        $email = filter_var($email, FILTER_VALIDATE_EMAIL);
        if (!$email) {
            return $this->json($response, ['error' => "Format d'email invalide"], 400);
        }

        // normalisation : lowercase (cohérent avec le register)
        $email = strtolower($email);

        // validation basique du mot de passe 
        $password = $body['password'] ?? '';

        // longueur minimale — évite des requêtes DB inutiles avec un mot de passe vide
        if (strlen($password) < 12) {
            return $this->json($response, ['error' => 'Mot de passe invalide'], 401);
        }

        // longueur maximale — protection contre les attaques bcrypt
        if (strlen($password) > 128) {
            return $this->json($response, ['error' => 'Mot de passe invalide'], 401);
        }

         // Recherche de l'utilisateur par email
        $user = $this->users->findByEmail($email);
        if (!$user) {
            return $this->json($response, ['error' => 'Utilisateur avec cet email n\'existe pas'], 401);
        }
        error_log("body['password']: " . $body['password']);
        error_log("user['pass_hash']: " . $user['pass_hash']);

        // Vérification du mot de passe
        if (!password_verify($body['password'], $user['pass_hash'])) {
            return $this->json($response, ['error' => 'Mot de passe incorrect'], 401);
        }

        //call pour le procédure stocké de BDD à mettre ici pour faire des logs pour toutes les connexions???

        // Génération du JWT
        $payload = [
            'iss'       => 'coffre-fort',          // émetteur
            'aud'       => 'coffre-fort-users',    // audience
            'iat'       => time(),                 // date d’émission
            'exp'       => time() + 900,          // expiration (15min)
            // 'exp'       => time() + 3600,          // expiration (1h)
            //'exp'       => time() + 300,          // expiration (5min pour tests)
            'user_id'   => $user['id'],            // identifiant utilisateur
            'email'     => $user['email'],
            'is_admin'  => $user['is_admin']
        ];

        // format de token: opaque signé (HMAC SHA‑256 sur payload)
        $jwt = JWT::encode($payload, $this->jwtSecret, 'HS256');

        // Réponse
        return $this->json($response, ['jwt' => $jwt], 200);
    }


    // GET /users - Liste tous les utilisateurs que pour admin ********************************************************************** OK
    public function list(Request $request, Response $response): Response
    {
        //vérif authentification d'admin
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $isAdmin = (int)$user['is_admin'];

            if($isAdmin === 0){
                return $this->json($response, ['error' => "Accès interdit"], 403);
            }

        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }

        $data = $this->users->listUsers();

        return $this->json($response, $data, 200);
    }


    // GET /users/{id} - Affiche un utilisateur que pour admin ********************************************************************** OK
    public function show(Request $request, Response $response, array $args): Response
    {
        //vérif authentification d'admin
        try {
            $authUser = $this->auth->getAuthenticatedUserFromToken($request);
            $isAdmin = (int)$authUser['is_admin'];

            if($isAdmin === 0){
                return $this->json($response, ['error' => "Accès interdit"], 403);
            }

        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }

        $id = (int)($args['id'] ?? 0);
        if ($id <= 0) {
            return $this->json($response, ['error' => 'ID invalide'], 400);
        }

        $targetUser = $this->users->find($id);

        if (!$targetUser) {
            return $this->json($response, ['error' => 'Utilisateur introuvable'], 404);
        }

        return $this->json($response, $targetUser, 200);
    }


    // ROUTE DASHBOARD (protégée)
    /**
     * vérifie que user est authentifié via un JWT valide (dans Header ou GET)
     */
    public function dashboard(Request $request, Response $response)
    {
        // Essayer de récupérer via Header
        // Authorization: Bearer eyJhbGciOiJIUzI1Ni...
        $authHeader = $request->getHeaderLine('Authorization');
        $jwt = null;

        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {

            // $matches[1] contient le token après "Bearer "
            $jwt = $matches[1];
        }

        // Si pas trouvé → essayer GET
        if (!$jwt) {

            //dashboard?jwt=abc123
            $params = $request->getQueryParams();
            $jwt = $params['jwt'] ?? null;
        }

        // Si toujours rien → erreur
        if (!$jwt) {
            return $response->withStatus(401);
        }

        // Décodage JWT
        try {

            // decode va vérifier la signature et l'expiration et décode le token
            // HS256 => algorithme de signature HMAC SHA‑256 (symétrique, même secret pour signer et vérifier)
            $jwt = JWT::decode($jwt, new Key($this->jwtSecret, 'HS256'));
        } catch (\Exception $e) {
            return $response->withStatus(403);
        }

        // OK → retourne les données nécessaires
        $response->getBody()->write(json_encode([
            "success"   => true,
            "message"   => "Bienvenue sur la page Main",
            "email"     => $jwt->email
        ]));
        
        return $response->withHeader("Content-Type", "application/json");
    }



}

?>
