<?php
/**
 * Get images data.
 */

namespace Dan\UploadImage;


class UploadImageGet
{
    /**
     * Image name.
     */
    public $imageName;

    /**
     * Image URL.
     */
    public $imageUrl;

    /**
     * Image path to disk.
     */
    public $imagePath;

    /**
     * UploadImageGet constructor.
     *
     * @param $imageName string image name
     * @param $imageUrl string url to image (/image/upload/image.jpg)
     * @param $imagePath string path image on the disk
     */
    function __construct($imageName, $imageUrl, $imagePath)
    {
        $this->imageName = $imageName;
        $this->imageUrl = $imageUrl;
        $this->imagePath = $imagePath;
    }

    /**
     * Get image name
     *
     * @return string
     */
    public function getImageName()
    {
        return $this->imageName;
    }

    /**
     * Get image Url to image (/image/upload/image.jpg)
     *
     * @return string
     */
    public function getImageUrl()
    {
        return $this->imageUrl;
    }

    /**
     * Get image path on the disk with image name
     *
     * @return string
     */
    public function getImagePath()
    {
        return $this->imagePath;
    }
}