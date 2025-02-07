<?php

class ControllerImage extends Controller
{

    private $imageHelper;

    public function __construct($DisableRender = false)
    {
        parent::__construct($DisableRender);
        include_once(ROOT . '/Controllers/Helpers/imageHelper.php');
        $this->imageHelper = new imageHelper();
    }

    /**
     * Display the image in full resolution to the browser. Does not display Nav or Footer
     * @return void
     */
    public function raw()
    {
        // Load Image or fail
        $Image = $this->imageHelper->loadImage(ArgumentList: $this->ArgumentList, minimal: false);
        if ($Image == false) {
            $this->imageHelper->displayDeniedImage();
            die();
        }

        // Permission check (always allow for now)
        if (!$this->imageHelper->permissionCheck(Registry::get('User')->id, $Image)) {
            $this->imageHelper->displayDeniedImage();
            die();
        }

        // Display the image if permission is granted and the image is loaded
        $this->imageHelper->displayImage($Image);
    }


    /**
     * Display the image in thumbnail resolution to the browser. Does not display Nav or Footer
     * @return void
     */
    public function thumbnail()
    {

        // Load Image or fail (also handles permission check)
        $Image = $this->imageHelper->loadImage($this->ArgumentList, minimal: true);
        if ($Image == false) {
            $this->imageHelper->displayDeniedImage();
            die();
        }

        // Permission check (always allow for now)
        if (!$this->imageHelper->permissionCheck(Registry::get('User')->id, $Image)) {
            $this->imageHelper->displayDeniedImage();
            die();
        }

        // Display the image if permission is granted and the image is loaded
        $this->imageHelper->displayThumbnail($Image);
    }
}