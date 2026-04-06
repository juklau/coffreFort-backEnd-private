<?php
// src/Security/StorageWriter.php

namespace App\Helpers;


final class StorageWriter
{
    /**
     * S'assure qu'un répertoire existe
     * static => on peut appeler cette méthode sans instancier la classe, 
     * ex: StorageWriter::ensureDir('/path/to/dir')
     * sans utiliser le "new"
     * garantit que le répertoire de destination existe avant l’écriture
     */
    public static function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new \RuntimeException("Impossible de créer le répertoire: $dir");
            }
        }
    }

    /**
     * Écrit des données binaires dans un fichier (par stream)
     * @param string $path Chemin du fichier à écrire
     * @param string $data Données binaires à écrire
     * @return int Nombre total d'octets écrits
     */
    public static function writeBinary(string $path, string $data): int
    {

        $handle = @fopen($path, 'wb'); //w => crée le fichier ou écrase s'il existe, b=> mode binaire
        if ($handle === false) {
            throw new \RuntimeException("Impossible de créer le fichier de destination: $path");
        }

        $chunkSize = 8192;      // 8 Ko par bloc pour éviter de charger tout en mémoire
        $offset = 0;            //position actuelle dans les données à écrire
        $length = strlen($data);
        $totalWritten = 0;

        while ($offset < $length) {
            $chunk = substr($data, $offset, $chunkSize);
            $written = fwrite($handle, $chunk); //nbre d'octets écrits pour ce bloc
            
            if ($written === false) {
                fclose($handle);
                throw new \RuntimeException("Erreur lors de l\'écriture du fichier");
            }
            
            $totalWritten += $written;
            $offset += $written;
        }

        fclose($handle);  //fermer le fichier après l'écriture
        return $totalWritten;
    }


     /**
     * Lit un fichier binaire par stream (pour éviter de charger tout en mémoire)
     * 
     * @param string $path Chemin du fichier à lire
     * @return string Contenu du fichier
     * @throws \RuntimeException Si impossible de lire le fichier
     */
    public static function readBinary(string $path): string
    {
        $fileHandle = @fopen($path, 'rb');
        if ($fileHandle === false) {
            throw new \RuntimeException("Impossible de lire le fichier chiffré: $path");
        }

        $content = '';
        while (!feof($fileHandle)) {
            $chunk = fread($fileHandle, 8192); // 8 Ko par bloc
            if ($chunk === false) {
                fclose($fileHandle);
                throw new \RuntimeException("Erreur lors de la lecture du fichier: $path");
            }
            $content .= $chunk;
        }
        
        fclose($fileHandle);
        return $content;
    }

}
?>