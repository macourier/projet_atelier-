<?php
declare(strict_types=1);

namespace App\Service;

class CompanyProfileService
{
    private $pdo;
    private static $cachedProfile = null;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Récupère le profil de l'entreprise (id=1)
     * Utilise un cache pour éviter les requêtes multiples
     */
    public function getProfile(): array
    {
        // Retourner le cache si disponible
        if (self::$cachedProfile !== null) {
            return self::$cachedProfile;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT * FROM company_profile WHERE id = 1");
            $stmt->execute();
            $profile = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$profile) {
                // Profil par défaut si la table est vide
                $profile = [
                    'id' => 1,
                    'name' => "L'atelier vélo",
                    'address_line1' => '10 avenue Willy Brandt',
                    'address_line2' => '',
                    'postcode' => '59000',
                    'city' => 'Lille',
                    'phone' => '03 20 78 80 63',
                    'email' => '',
                    'logo_path' => '',
                    'updated_at' => date('Y-m-d H:i:s')
                ];
            }
        } catch (\PDOException $e) {
            // Table n'existe pas encore ou erreur SQL, utiliser le profil par défaut
            $profile = [
                'id' => 1,
                'name' => "L'atelier vélo",
                'address_line1' => '10 avenue Willy Brandt',
                'address_line2' => '',
                'postcode' => '59000',
                'city' => 'Lille',
                'phone' => '03 20 78 80 63',
                'email' => '',
                'logo_path' => '',
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }

        // Pour le PDF, convertir le chemin relatif en chemin absolu du système de fichiers
        if (!empty($profile['logo_path'])) {
            // Le chemin stocké est relatif (/uploads/company/logo.png)
            // Pour Dompdf avec chroot, nous avons besoin du chemin relatif depuis public
            // mais Dompdf peut avoir besoin d'un chemin de fichier complet
            $publicDir = realpath(dirname(__DIR__, 2) . '/public');
            $relativePath = ltrim($profile['logo_path'], '/');
            $absolutePath = $publicDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            
            // Vérifier si le fichier existe
            if (file_exists($absolutePath)) {
                // Pour Dompdf, utiliser le chemin relatif depuis le chroot (public)
                $profile['logo_path_for_pdf'] = $relativePath;
            } else {
                $profile['logo_path_for_pdf'] = '';
            }
        } else {
            $profile['logo_path_for_pdf'] = '';
        }

        // Mettre en cache
        self::$cachedProfile = $profile;

        return $profile;
    }

    /**
     * Met à jour le profil de l'entreprise
     */
    public function updateProfile(array $data): bool
    {
        try {
            $sql = "UPDATE company_profile SET 
                    name = :name,
                    address_line1 = :address_line1,
                    address_line2 = :address_line2,
                    postcode = :postcode,
                    city = :city,
                    phone = :phone,
                    email = :email,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = 1";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                ':name' => $data['name'] ?? '',
                ':address_line1' => $data['address_line1'] ?? '',
                ':address_line2' => $data['address_line2'] ?? '',
                ':postcode' => $data['postcode'] ?? '',
                ':city' => $data['city'] ?? '',
                ':phone' => $data['phone'] ?? '',
                ':email' => $data['email'] ?? ''
            ]);

            // Invalider le cache
            if ($result) {
                self::$cachedProfile = null;
            }

            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Met à jour uniquement le chemin du logo
     */
    public function updateLogo(string $logoPath): bool
    {
        try {
            $sql = "UPDATE company_profile SET 
                    logo_path = :logo_path,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = 1";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                ':logo_path' => $logoPath
            ]);

            // Invalider le cache
            if ($result) {
                self::$cachedProfile = null;
            }

            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Gère l'upload du logo
     * @return string|null Chemin relatif du logo ou null en cas d'erreur
     * @throws \Exception En cas d'erreur de validation
     */
    public function uploadLogo(array $file): ?string
    {
        // Vérifier que le fichier existe
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \Exception('Aucun fichier uploadé');
        }

        // Vérifier le type MIME
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            throw new \Exception('Type de fichier non autorisé. Seuls PNG et JPEG sont acceptés.');
        }

        // Vérifier la taille (max 2 Mo)
        $maxSize = 2 * 1024 * 1024; // 2 Mo
        if ($file['size'] > $maxSize) {
            throw new \Exception('Le fichier est trop volumineux. Maximum 2 Mo.');
        }

        // Créer le dossier de destination
        $uploadDir = dirname(__DIR__, 2) . '/public/uploads/company';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Déterminer l'extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['png', 'jpg', 'jpeg'])) {
            $extension = $mimeType === 'image/png' ? 'png' : 'jpg';
        }

        // Nom du fichier fixe (logo.png ou logo.jpg)
        $fileName = 'logo.' . $extension;
        $destination = $uploadDir . '/' . $fileName;

        // Supprimer l'ancien logo s'il existe
        if (file_exists($destination)) {
            unlink($destination);
        }

        // Déplacer le fichier
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \Exception('Erreur lors du déplacement du fichier');
        }

        // Retourner le chemin relatif pour l'utilisation dans les templates
        return '/uploads/company/' . $fileName;
    }

    /**
     * Invalide le cache du profil
     */
    public function invalidateCache(): void
    {
        self::$cachedProfile = null;
    }
}
