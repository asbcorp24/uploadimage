<?php

/**
 * Class for work with images.
 */

namespace Dan\UploadImage;

use File;
use GlideImage;

class UploadImage
{
    /**
     * Use thumbnails or not.
     */
    protected $thumbnail_status;

    /**
     * Base store for images.
     */
    protected $baseStore;

    /**
     * Original folder for images.
     */
    protected $original;

    /**
     * Original image will be resizing to 800px.
     */
    protected $originalResize;

    /**
     * Image quality for save image in percent.
     */
    protected $quality;

    /**
     * Width thumbnails for images.
     */
    protected $thumbnails;

    /**
     * Watermark image.
     */
    protected $watermark_path;

    /**
     * Watermark image for video.
     */
    protected $watermark_video_path;

    /**
     * Watermark text.
     */
    protected $watermark_text;

    /**
     * Minimal width for image.
     */
    protected $min_width;

    /**
     * Width for preview image.
     */
    protected $previewWidth;

    /**
     * Folder name for upload images from WYSIWYG editor.
     */
    protected $editor_folder;

    /**
     *  Get settings from config file.
     */
    public function __construct()
    {
        $config = \Config::get('upload-image.image-settings');

        $this->thumbnail_status = $config['thumbnail_status'];
        $this->baseStore = $config['baseStore'];
        $this->original = $config['original'];
        $this->originalResize = $config['originalResize'];
        $this->quality = $config['quality'];
        $this->thumbnails = $config['thumbnails'];
        $this->watermark_path = $config['watermark_path'];
        $this->watermark_video_path = $config['watermark_video_path'];
        $this->watermark_text = $config['watermark_text'];
        $this->min_width = $config['min_width'];
        $this->previewWidth = $config['previewWidth'];
        $this->editor_folder = $config['editor_folder'];
    }

    /**
     * Upload image to disk.
     *
     * @param $file object instance image or image string
     * @param $contentName string content name (use for create and named folder)
     * @param bool $video if true then add watermark with video player image to an image
     *
     * @return string new image name
     */
    public function upload($file, $contentName, $video = false)
    {
        $thumbnails = $this->thumbnail_status;

        // Create path for storage and full path to image.
        $imageStorage = $this->baseStore . $contentName . 's/';
        $imagePath = public_path() . $imageStorage;

        $newName = '';
        $newImage = '';

        // If file URL string.
        if (is_string($file) && !empty($file)) {
            // Path to file in file system.
            $originalPath = $this->saveLinkImage($file, $contentName);

        } // If file from form.
        elseif (is_object($file)) {
            if ($file->isValid()) {
                // File name.
                //$imageName = $file->getClientOriginalName();

                // Get extension file.
                $ext = strtolower($file->getClientOriginalExtension());

                // Get real path to file.
                $pathToFile = $file->getPathname();

                // Get image size.
                $imageSize = getimagesize($pathToFile);

                // If width image < $this->min_width (default 500px).
                if ($imageSize[0] < $this->min_width) {
                    return '';
                }

                $ind = time() . '_' . mb_strtolower(str_random(8));

                // New file name.
                $newName = $contentName . '_' . $ind . '.' . $ext;

                // Save image to disk.
                $file->move($imagePath . $this->original, $newName);

                // Path to file in file system.
                $originalPath = $imagePath . $this->original . $newName;
            }
        }

        // If file was uploaded then make resize and add watermark.
        if (isset($originalPath)) {
            // Get image width.
            $image_width = getimagesize($originalPath)[0];

            // If video content then cover image the video player watermark.
            if ($video) {
                $watermark = $this->watermark_video_path;
                $markPos = 'center';
                $markPad = 0;
            } else {
                $watermark = $this->watermark_path;
                $markPos = 'bottom-right';
                $markPad = 5;
            }

            // If image width more then originalResize - make resize it.
            if ($image_width > $this->originalResize) {
                // Resize saved image and save to original folder
                // (help about attributes http://glide.thephpleague.com/1.0/api/quick-reference/).
                GlideImage::create($originalPath)
                    ->modify([
                        'w' => $this->originalResize,
                        'q' => $this->quality,
                        'mark' => public_path() . $watermark,
                        'markpad' => $markPad,
                        'markpos' => $markPos
                    ])
                    ->save($originalPath);
            } // Add only watermark and change quality for image.
            else {
                GlideImage::create($originalPath)
                    ->modify([
                        'q' => $this->quality,
                        'mark' => public_path() . $watermark,
                        'markpad' => $markPad,
                        'markpos' => $markPos
                    ])
                    ->save($originalPath);
            }

            // If need make thumbnails.
            if ($thumbnails) {
                // Get all thumbnails and save it.
                foreach ($this->thumbnails as $width) {
                    // Path to folder where will be save image.
                    $savedImagePath = $imagePath . 'w' . $width . '/';

                    // File with path to save image.
                    $savedImagePathFile = $savedImagePath . $newName;

                    // Create new folder.
                    File::makeDirectory($savedImagePath, $mode = 0755, true, true);

                    // Resize saved image and save to thumbnail folder
                    // (help about attributes http://glide.thephpleague.com/1.0/api/quick-reference/).
                    GlideImage::create($originalPath)
                        ->modify(['w' => $width])
                        ->save($savedImagePathFile);
                }
            }

            $newImage = [
                'name' => $newName,
                'url' => $imageStorage . $this->original . $newName,
                'file_path' => $originalPath
            ];
        }

        return $newImage;
    }

    /**
     *  Save image from link.
     *
     * @param $file string link to file
     * @param $contentName string content name (folder name for save)
     *
     * @return string path to file
     */
    public function saveLinkImage($file, $contentName)
    {
        // Create path for storage and full path to image.
        $imageStorage = $this->baseStore . $contentName . 's/';
        $imagePath = public_path() . $imageStorage;

        $file = trim($file);

        // Check image.
        if (!getimagesize($file)) {
            // If not image
            return '';
        }

        // If width image < 500px.
        if (getimagesize($file)[0] < $this->min_width) {
            return '';
        }

        // Get extension file.
        $ext = strtolower(last(explode('.', $file)));

        // Get file from URL.
        $file = curl_init($file);

        $ind = time() . '_' . mb_strtolower(str_random(8));

        // New file name.
        $newName = $contentName . '_' . $ind . '.' . $ext;

        // Path to file in file system.
        $originalPath = $imagePath . $this->original . $newName;

        // Save file to disk.
        $fp = fopen($originalPath, 'wb');
        curl_setopt($file, CURLOPT_FILE, $fp);
        curl_setopt($file, CURLOPT_HEADER, 0);
        curl_setopt($file, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($file);
        curl_close($file);
        fclose($fp);

        return $originalPath;
    }

    /**
     * Delete image from disk.
     *
     * @param $imageName string image name or array with images
     * @param $contentName string content name (use for folder and name)
     *
     */
    public function delete($imageName, $contentName)
    {
        $thumbnails = $this->thumbnail_status;

        // Delete old image if exist.
        if (is_string($imageName)) {
            // Get file name.
            $oldFileName = $imageName;

            // Create path for storage and full path to image.
            $imageStorage = $this->baseStore . $contentName . 's/';
            $imagePath = public_path() . $imageStorage;

            // Delete old original image from disk.
            File::delete($imagePath . $this->original . $oldFileName);

            // Delete all thumbnails if exist.
            if ($thumbnails) {
                // Get all thumbnails and delete it.
                foreach ($this->thumbnails as $width) {
                    // Delete old image from disk.
                    File::delete($imagePath . 'w' . $width . '/' . $oldFileName);
                }
            }
        } elseif (is_array($imageName)) {
            // If need delete array images.

            // Delete each image.
            foreach ($imageName as $image) {
                // Get file name.
                $oldFileName = $image;

                // Create path for storage and full path to image.
                $imageStorage = $this->baseStore . $contentName . 's/';
                $imagePath = public_path() . $imageStorage;

                // Delete old original image from disk.
                File::delete($imagePath . $this->original . $oldFileName);

                // Delete all thumbnails if exist.
                if ($thumbnails) {
                    // Get all thumbnails and delete it.
                    foreach ($this->thumbnails as $width) {
                        // Delete old image from disk.
                        File::delete($imagePath . 'w' . $width . '/' . $oldFileName);
                    }
                }
            }
        }
    }

    /**
     * Delete body images from disk.
     *
     * @param $textBody string with text where there images
     *
     */
    public function deleteBody($textBody)
    {
        // Get all images from post body.
        $images_body = $this->getImagesFromBody($textBody);

        // Delete body images from disk.
        if(count($images_body) > 0) {
            $this->delete($images_body, $this->editor_folder);
        }
    }

    /**
     * Create path to image.
     *
     * @param $contentName string content name (use for folder and name)
     * @param null $size integer width for image (use one of thumbnail array)
     *
     * @return mixed
     */
    public function load($contentName, $size = null)
    {
        // If get size for image.
        if ($size) {
            // Get all thumbnails and compare with size.
            foreach ($this->thumbnails as $width) {
                if ($width == $size) {
                    $imagePath = $this->baseStore . $contentName . 's/w' . $width . '/';

                    return $imagePath;
                }
            }
        } else {
            $imagePath = $this->baseStore . $contentName . 's/' . $this->original;

            return $imagePath;
        }

        return '';
    }

    /**
     * Convert image to base64 format.
     *
     * @param $image_path_file string path to file in file system
     *
     * @return string base64 file format
     */
    public function convertToBase64($image_path_file)
    {
        // Create Base64 image.
        $type = pathinfo($image_path_file, PATHINFO_EXTENSION);
        $data = file_get_contents($image_path_file, FILE_USE_INCLUDE_PATH);
        $dataUri = 'data:image/' . $type . ';base64,' . base64_encode($data);

        return $dataUri;
    }

    /**
     * Preview image for form.
     *
     * @param $file object instance image
     * @param $contentName string content name (use for folder and name)
     * @param bool $real_width width for image preview
     * (if true - use real image width, if false (default) - use preview width from settings)
     * @param bool $watermark add watermark to image (by default - disable)
     *
     * @return array new image stream Base64
     */
    public function preview($file, $contentName, $real_width = false, $watermark = false)
    {
        // Create path for storage and full path to image.
        $imageStorage = $this->baseStore . $contentName . 's/';
        $imagePath = public_path() . $imageStorage;

        $newName = ['error' => 'File not valid!'];

        // If file URL string.
        if (is_string($file) && !empty($file)) {
            // Path to file in file system.
            $originalPath = $this->saveLinkImage($file, $contentName);

            // Get image size.
            $imageSize = getimagesize($originalPath);

        } // If file from form.
        elseif (is_object($file)) {
            if ($file->isValid()) {
                // File name.
                //$imageName = $file->getClientOriginalName();

                // Get extension file.
                $ext = strtolower($file->getClientOriginalExtension());

                // Get real path to file.
                $pathToFile = $file->getPathname();

                // Get image size.
                $imageSize = getimagesize($pathToFile);

                // If not image.
                if (!$imageSize) {
                    return ['error' => 'Not image file!'];
                }

                // If width image < $this->min_width (default 500px).
                if ($imageSize[0] < $this->min_width) {
                    return ['error' => 'Image must be more then ' . $this->min_width . 'px!'];
                }

                $ind = time() . '_' . mb_strtolower(str_random(8));

                // New file name.
                $newName = $contentName . '_' . $ind . '.' . $ext;

                // Save image to disk.
                $file->move($imagePath . $this->original, $newName);

                // Path to file in file system.
                $originalPath = $imagePath . $this->original . $newName;

            }
        }

        // If file was uploaded.
        if (isset($originalPath) && !empty($originalPath)) {
            // If real width = true.
            if ($real_width) {
                $previewWidth = $imageSize[0] > $this->originalResize ? $this->originalResize : $imageSize[0];
            } else {
                $previewWidth = $this->previewWidth;
            }

            // Check nisessary in a watermark cover.
            if ($watermark) {
                // Resize saved image and save
                // (help about attributes http://glide.thephpleague.com/1.0/api/quick-reference/).
                GlideImage::create($originalPath)
                    ->modify([
                        'w' => $previewWidth,
                        'q' => $this->quality,
                        'mark' => public_path() . $this->watermark_path,
                        'markpad' => 5
                    ])
                    ->save($originalPath);
            } else {
                // Resize saved image and save
                // (help about attributes http://glide.thephpleague.com/1.0/api/quick-reference/).
                GlideImage::create($originalPath)
                    ->modify([
                        'w' => $previewWidth,
                        'q' => $this->quality,
                    ])
                    ->save($originalPath);
            }

            // Get new image size.
            $imageNewSize = getimagesize($originalPath);

            // Convert image to base64 file.
            $image_path_name = UploadImage::convertToBase64($originalPath);

            // Delete original image from disk.
            File::delete($originalPath);

            $newName = [
                'url' => $image_path_name,
                'size' => $imageNewSize
            ];
        }

        return $newName;
    }

    /**
     * Get all images from body which keeping on the our server.
     *
     * @param $html string text with relative images links
     *
     * @return array with images
     */
    public function getImagesFromBody($html)
    {
        // Get all images from body.
        $doc = new \DOMDocument();
        @$doc->loadHTML($html);

        $get_body_images = $doc->getElementsByTagName('img');

        $body_images = [];

        foreach ($get_body_images as $body_image) {
            // If this is internal link.
            if (mb_strpos($body_image->getAttribute('src'), 'http://') === false &&
                mb_strpos($body_image->getAttribute('src'), 'https://') === false
            ) {
                $body_images[] = last(explode('/', $body_image->getAttribute('src')));
            }
        }

        return $body_images;
    }
}