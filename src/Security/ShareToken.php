<?php
namespace App\Security;

use Medoo\Medoo;

//Utilitaires pour la gestion des tokens
//cette classe fournit des méthodes pour 
//générer des tokens sécurisés, les signer et les comparer de manière sécurisée
final class ShareToken{

    // encoder des données binaires en Base64 compatible URL (remplace + par - et / par _, et supprime les = de padding)
    public static function base64url(string $data): string{

        //rtrim => supprime  = à la fin
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    // génère un token aléatoire sécurisé de la longueur spécifiée (en octets)
    // 32 bytes => 256 bits de sécurité
    public static function randomToken(int $bytes = 32): string{

        //base64url encode un token binaire aléatoire
        return self::base64url(random_bytes($bytes));  //=> environ 43 caractères en base64url pour 32 bytes d'entrée
    }

    //créer une signature HMAC-SHA256 du token et de l'ID de partage en utilisant un secret partagé
    //HMAC = Hash Message Authentication Code
    /**
     * La signature HMAC garantit que le token n’a pas été modifié et qu’il a été généré par le serveur. 
     * L’utilisation d’un secret côté serveur empêche un attaquant de fabriquer une signature valide.
     */
    public static function sign(string $secret, string $token, int $shareId): string{

        //HMAC-SHA256 signature => algo, message, clé secrète
        // inclure $shareId => Même token + shareId différent
        return hash_hmac('sha256', $token . $shareId, $secret);

    }

    // comparer deux signatures de manière sécurisée pour éviter les attaques de timing
    public static function equals(string $a, string $b): bool {
        return hash_equals($a, $b);
    }
}