<?php

function storeAnnouncementImages($fileField, $required = true)
{
    if (!isset($fileField['name']) || !is_array($fileField['name'])) {
        if ($required) {
            throw new InvalidArgumentException('Sélectionnez au moins une photo valide.');
        }
        return [];
    }

    $selected = [];
    foreach ($fileField['name'] as $index => $originalName) {
        $errorCode = isset($fileField['error'][$index]) ? (int) $fileField['error'][$index] : UPLOAD_ERR_NO_FILE;
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($errorCode !== UPLOAD_ERR_OK || !isset($fileField['tmp_name'][$index], $fileField['size'][$index])) {
            throw new InvalidArgumentException('Une photo n’a pas pu être téléversée correctement.');
        }
        if ((int) $fileField['size'][$index] < 1 || (int) $fileField['size'][$index] > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('Chaque photo doit peser moins de 5 Mo.');
        }
        $selected[] = $index;
    }

    if (!$selected) {
        if ($required) {
            throw new InvalidArgumentException('Ajoutez au moins une photo.');
        }
        return [];
    }
    if (count($selected) > 10) {
        throw new InvalidArgumentException('Ajoutez au maximum 10 photos.');
    }

    $cloudinaryUrl = getenv('CLOUDINARY_URL');
    $useCloudinary = $cloudinaryUrl !== false && $cloudinaryUrl !== '';
    if (!$useCloudinary && getenv('VERCEL') === '1') {
        throw new RuntimeException('Configurez CLOUDINARY_URL pour stocker les photos durablement sur Vercel.');
    }
    if ($useCloudinary) {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new RuntimeException('Cloudinary est configuré, mais les dépendances Composer ne sont pas installées.');
        }
        require_once $autoload;
        if (!class_exists('\Cloudinary\Uploader')) {
            throw new RuntimeException('La bibliothèque Cloudinary est indisponible.');
        }
        $uploadDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'qrib-uploads';
    } else {
        $uploadDirectory = dirname(__DIR__) . '/uploads';
    }
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('Le dossier de téléversement ne peut pas être créé.');
    }

    $paths = [];
    $totalBytes = 0;
    try {
        foreach ($selected as $index) {
            $temporaryFile = $fileField['tmp_name'][$index];
            if (!is_uploaded_file($temporaryFile)) {
                throw new InvalidArgumentException('Fichier téléversé invalide.');
            }
            $totalBytes += (int) $fileField['size'][$index];
            if ($totalBytes > 20 * 1024 * 1024) {
                throw new InvalidArgumentException('La taille totale des photos ne peut pas dépasser 20 Mo.');
            }

            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryFile);
            $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $imageInfo = getimagesize($temporaryFile);
            if (!isset($allowedTypes[$mime]) || !$imageInfo || $imageInfo['mime'] !== $mime) {
                throw new InvalidArgumentException('Formats acceptés : JPEG, PNG et WebP uniquement.');
            }
            if ($imageInfo[0] < 1 || $imageInfo[1] < 1 || $imageInfo[0] * $imageInfo[1] > 12000000) {
                throw new InvalidArgumentException('La résolution de la photo est trop grande.');
            }
            if ($mime === 'image/webp' && !function_exists('imagewebp')) {
                throw new RuntimeException('Le serveur ne prend pas en charge le traitement des images WebP.');
            }

            $imageData = file_get_contents($temporaryFile);
            $image = $imageData === false ? false : imagecreatefromstring($imageData);
            if ($image === false) {
                throw new InvalidArgumentException('Une photo ne peut pas être décodée.');
            }
            $fileName = bin2hex(openssl_random_pseudo_bytes(16)) . '.' . $allowedTypes[$mime];
            $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $fileName;
            if ($mime === 'image/jpeg') {
                $written = imagejpeg($image, $destination, 88);
            } elseif ($mime === 'image/png') {
                $written = imagepng($image, $destination, 6);
            } else {
                $written = imagewebp($image, $destination, 88);
            }
            imagedestroy($image);
            if (!$written) {
                throw new RuntimeException('Une photo n’a pas pu être enregistrée.');
            }

            if ($useCloudinary) {
                try {
                    $result = \Cloudinary\Uploader::upload($destination, [
                        'folder' => 'qrib/annonces',
                        'public_id' => bin2hex(openssl_random_pseudo_bytes(16)),
                        'resource_type' => 'image',
                        'overwrite' => false,
                        'unique_filename' => true,
                    ]);
                    if (empty($result['secure_url'])) {
                        throw new RuntimeException('Cloudinary n’a pas retourné une URL sécurisée pour la photo.');
                    }
                    $paths[] = $result['secure_url'];
                } finally {
                    if (is_file($destination)) {
                        unlink($destination);
                    }
                }
            } else {
                $paths[] = 'uploads/' . $fileName;
            }
        }
    } catch (Exception $exception) {
        if (!$useCloudinary) {
            foreach ($paths as $path) {
                $savedPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
                if (is_file($savedPath)) {
                    unlink($savedPath);
                }
            }
        }
        throw $exception;
    }

    return $paths;
}

function deleteLocalAnnouncementImages($paths)
{
    foreach ($paths as $path) {
        if (!is_string($path) || !preg_match('~^uploads/[a-f0-9]{32}\.(?:jpg|png|webp)$~', $path)) {
            continue;
        }
        $fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (is_file($fullPath)) {
            unlink($fullPath);
        }
    }
}
