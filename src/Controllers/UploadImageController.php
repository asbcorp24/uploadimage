<?php

/**
 * Controller for upload images from WYSIWYG and Delete image from WYSIWYG.
 */

namespace Dan\UploadImage\Controllers;

use Dan\UploadImage\UploadImageFacade as UploadImage;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Input;
use Illuminate\Http\Request;

class UploadImageController extends Controller
{
    /**
     * Folder name for upload images from WYSIWYG editor.
     */
    protected $editor_folder;

    /**
     * Storage image which uploaded from WYSIWYG editor into the DB in the Base64 (default storage on the disk).
     */
    protected $base64_storage;

    // Get settings from config file.
    public function __construct()
    {
        $config = \Config::get('upload-image.image-settings');

        $this->editor_folder = $config['editor_folder'];
        //$this->base64_storage = $config['base64_storage'];
        $this->base64_storage = false;
    }

    /**
     * Upload file to server.
     */
    public function upload(Request $request)
    {
        // Check exist file (files or link).
        if ($request->file('files') || $request->get('image')) {
            // If array with files.
            if ($request->file('files')) {
                $file = Input::file('files');
            } // If link to file.
            elseif ($request->get('image')) {
                // Get file from url.
                $file = $request->get('image');
            }

            // If file is array with many files.
            if (is_array($file)) {
                $files = $file;
                unset($file);

                $image = [];

                // Get every file and upload it.
                foreach ($files as $file) {
                    // Upload and save image.
                    $savedImage = UploadImage::upload($file, $this->editor_folder);

                    // Get only image url.
                    $image[] = $savedImage['url'];
                }

                // Some errors in form.
                if (empty($image)) {
                    $respond = [
                        "error" => "Error, can't upload file! Please check file or URL."
                    ];

                    return response()->json($respond);
                } else {
                    $respond = [
                        "url" => $image,
                    ];
                }

                return response()->json($respond);
            } // If file once file or link.
            else {
                // Upload and save image.
                $image = UploadImage::upload($file, $this->editor_folder);

                // Some errors in form.
                if (empty($image)) {
                    $respond = [
                        "error" => "Error, can't upload file! Please check file or URL."
                    ];

                    return response()->json($respond);
                }

                // Get URL to file.
                $image_path_name = $image['url'];

                // Get path to file in file system.
                $image_path_file = $image['file_path'];

                // Get image name.
                $image_name = $image['name'];

                // Check status. If true then to convert file in base64 format.
                if ($this->base64_storage) {
                    // Convert image to base64 file.
                    $image_path_name = UploadImage::convertToBase64($image_path_file);

                    // Delete saved image (use if file need convert to base64 file).
                    UploadImage::delete($image_name, $this->editor_folder);
                }

                if (!empty($image_name)) {
                    $respond = [
                        "uploaded" => 1,
                        "fileName" => $image_name,
                        "url" => $image_path_name,
                    ];
                } else {
                    $respond = [
                        "uploaded" => 0,
                        "fileName" => $image_name,
                        "url" => $image_path_name,
                        "error" => [
                            "message" => "Error, can't upload file!"
                        ]
                    ];
                }

                return response()->json($respond);
            }
        }
    }

    /**
     * Delete file from server.
     */
    public function delete(Request $request)
    {
        // Check exist file.
        if ($request->get('file')) {
            $image_name = explode('/', $request->get('file'));

            // Delete image from server.
            UploadImage::delete(last($image_name), $this->editor_folder);
        }

        //return response()->json(['status' => 200]);
    }

    /**
     * Create preview image.
     */
    public function preview(Request $request)
    {
        // Check exist file (file or link).
        if ($request->file('file')) {
            $file = Input::file('file');

            // Upload and save image.
            $image = UploadImage::preview($file, $this->editor_folder);

            // Some errors in form.
            if (isset($image['error'])) {
                $respond = [
                    "error" => $image['error']
                ];

                return response()->json($respond);
            }

            // Get URL to file.
            $image_path_name = $image['url'];

            $respond = [
                "url" => $image_path_name
            ];

            return response()->json($respond);
        }
    }
}
