<?php

/**
 * File Class
 *
 * This class acts as a wrapper around the file data, providing methods to interact with file metadata, content, and storage drivers.
 * It is responsible for loading the correct driver for file storage, handling file operations like saving, retrieving, deleting, and more.
 */
class file
{
    public $FileID;
    public $FileName;
    public $FileSize;
    public $FileType;
    public $PublicFile = 0;  // 0 = Private, 1 = Public (Default is private)
    public $FilePath;
    public $Owner;
    public $Encoded;
    public $Decoded;
    public $FileHash;
    public $Driver;
    public $UniqueID;

    /**
     * The raw content of the file.
     *
     * @var string
     */
    public $Content;

    public $Created;
    public $Modified;

    /**
     * Constructor initializes the database connections.
     */
    public function __construct()
    {

    }

    /**
     * Retrieves file metadata from the database using a unique ID.
     *
     * @param string $id The unique ID of the file (hash and ID combined).
     * @return bool True if metadata is retrieved successfully, false otherwise.
     */
    public function getFileMetadata($id)
    {
        if (!is_numeric($id) && strpos($id, "_") !== false) {
            mlog("Extracting ID from: " . $id);
            $imageID = explode("_", $id)[1];
            $imageID = Registry::get('SqlSlaves')->safe($imageID);
            $hash = explode("_", $id)[0];
            $hash = Registry::get('SqlSlaves')->safe($hash);
            mlog("Extracted ID: " . $imageID);
            mlog("Extracted Hash: " . $hash);
        } else {
            mlog("Invalid ID: " . $id);
            return false;
        }

        $data = Registry::get('SqlSlaves')->query("SELECT * FROM `files-metadata` WHERE `id` = '$imageID' AND `hash` = '$hash'", true);
        if ($data == false) {
            mlog("No file found with ID: " . $id);
            return false;
        }

        $data = $data[0];
        $this->Created = $data['created'];
        $this->Modified = $data['modified'];
        $this->FileSize = $data['size'];
        $this->FileName = $data['name'];
        $this->Owner = $data['owner'];
        $this->FileID = $data['id'];
        $this->FileHash = $data['hash'];
        $this->FileType = $data['filetype'];
        $this->Driver = $data['driver'];
        $this->PublicFile = $data['public'];
        $this->UniqueID = $data['hash'] . "_" . $data['id'];
        return true;
    }

    /**
     * Deletes the file, either permanently or softly.
     *
     * @param bool $softDelete If true, the file is only marked as deleted rather than being permanently removed.
     */
    public function delete($softDelete = false)
    {
        mlog("❌ Deleting file: " . $this->UniqueID);
        $id = Registry::get('Sql')->safe($this->FileID);
        Registry::get('Sql')->insert("DELETE FROM `files-metadata` WHERE `id` = '$id'");

        $this->_deleteFileFromDriver();
    }

    /**
     * Saves the file metadata and content using the appropriate driver.
     */
    public function set()
    {
        mlog("✅ Saving file: " . $this->FileID);

        if ($this->Driver === null) {
            $this->Driver = Registry::get('settings')['fileDriver']; #$settings['fileDriver'];
        }

        mlog("🔴 The PublicFile is set to: " . $this->PublicFile);

        Registry::get('Sql')->insert("
            INSERT INTO `files-metadata` 
            (`filetype`, `created`, `modified`, `size`, `name`, `owner`, `hash`, `driver`, `public`)
            VALUES
            ('$this->FileType', '$this->Created', '$this->Modified', '$this->FileSize', '$this->FileName', '$this->Owner', '$this->FileHash', '$this->Driver', '$this->PublicFile')
        ");

        mlog("File Metadata Saved, Getting the ID of the file...");
        $this->FileID = Registry::get('Sql')->insert_id();
        mlog("Done! - File ID: " . $this->FileID);
        $this->UniqueID = $this->FileHash . "_" . $this->FileID;

        $this->_saveFileToDriver();
    }

    /**
     * Deletes the file from the storage driver.
     *
     * @return bool True if the file was deleted successfully, false otherwise.
     */
    private function _deleteFileFromDriver()
    {
        if (isset($this->Driver) && file_exists(ROOT . '/system/classes/drivers/file/' . $this->Driver . ".php")) {
            require_once ROOT . '/system/classes/drivers/file/' . $this->Driver . ".php";
        } else {
            mlog("Error Finding Driver: " . $this->Driver);
        }

        $file = new ("file_driver_" . $this->Driver);
        return $file->delete($this->UniqueID);
    }

    /**
     * Saves the file to the appropriate storage driver.
     *
     * @return bool True if the file was saved successfully, false otherwise.
     */
    private function _saveFileToDriver()
    {
        if (isset($this->Driver) && file_exists(ROOT . '/system/classes/drivers/file/' . $this->Driver . ".php")) {
            require_once ROOT . '/system/classes/drivers/file/' . $this->Driver . ".php";
        } else {
            mlog("Error Finding Driver: " . $this->Driver);
        }

        $file = new ("file_driver_" . $this->Driver);
        return $file->set($this->UniqueID, $this->Content);
    }

    /**
     * Retrieves the file content and metadata based on the unique ID.
     *
     * @param string $id The unique ID of the file.
     * @return mixed The file object if retrieved successfully, false otherwise.
     */
    public function get($id, $minimal=false)
    {
        if ($this->getFileMetadata($id) == false) {
            mlog("Failed to get file metadata for: " . $id);
            return false;
        }

        mlog("🗃 getting file: " . $this->UniqueID);
        if($minimal == false){
            $File = $this->_getFileFromDriver($this->UniqueID);
            mlog("✅ File loaded: " . $this->UniqueID);
            mlog("Setting File Content");
            $this->Content = $File;
            mlog("File Set");
            mlog("✅ File Size: " . strlen($File));            
        }else{
            mlog("Skipping file content load as minimal is set to true");
            return true;
        }
        


        return $this;
    }

    /**
     * Updates the file metadata.
     */
    public function update()
    {
        mlog("✅ Updating file: " . $this->UniqueID);
        $this->Modified = date("Y-m-d H:i:s");

        Registry::get('Sql')->insert("
            UPDATE `files-metadata` 
            SET `filetype` = '$this->FileType', `created` = '$this->Created', `modified` = '$this->Modified', `size` = '$this->FileSize', `name` = '$this->FileName', `owner` = '$this->Owner', `hash` = '$this->FileHash', `driver` = '$this->Driver'
            WHERE `id` = '$this->FileID'
        ");

        // TODO: Implement file content updates
    }

    /**
     * Finds files based on the owner, type, order, and limit.
     *
     * @param int|null $userID The owner of the file or NULL for all users.
     * @param mixed|null $fileType The type of files to find. Supply an array for multiple types.
     * @param string $fileSort The order to return the files in.
     * @param int $limit The number of files to return.
     * @return array An array of file objects.
     */
    public function Find($userID = null, $fileType = null, $fileSort = "`id` DESC", $limit = 1)
    {
        $id = Registry::get('SqlSlaves')->safe($userID);
        
        #$FileType = Registry::get('SqlSlaves')->safe($fileType); # This is not needed any more, moved to the query
        $limitString = "LIMIT $limit";

        # Build the query based on the fileType
        # Sample: SELECT * FROM `files-metadata` WHERE `owner` = '$id' AND `filetype` LIKE '%$FileType%' ORDER BY $fileSort $limitString
        if (is_array($fileType)) {
            $Query = "SELECT * FROM `files-metadata` WHERE `owner` = '$id' AND";
            foreach ($fileType as $key => $value) {
                $FileType = Registry::get('SqlSlaves')->safe($value);
                $Query .= " `filetype` LIKE '%$FileType%' OR";
            }
            $Query = rtrim($Query, "OR");

        } else {
            $FileType = Registry::get('SqlSlaves')->safe($fileType);
            $Query = "SELECT * FROM `files-metadata` WHERE `owner` = '$id' AND `filetype` LIKE '%$FileType%'";
        }

        if ($userID == null) {
            $data = Registry::get('SqlSlaves')->query("SELECT * FROM `files-metadata` WHERE `filetype` LIKE '%$FileType%' ORDER BY $fileSort $limitString", true, 5);
        } else {
            $data = Registry::get('SqlSlaves')->query("$Query ORDER BY $fileSort $limitString", true, 5);
        }

        $files = array();
        foreach ($data as $key => $value) {
            $files[] = $value;
        }

        return $files;
    }

    /**
     * Retrieves the file from the appropriate storage driver.
     *
     * @param string $id The unique ID of the file.
     * @return mixed The file content if retrieved successfully, false otherwise.
     */
    private function _getFileFromDriver($id)
    {
        if (isset($this->Driver) && file_exists(ROOT . '/system/classes/drivers/file/' . $this->Driver . ".php")) {
            mlog("Loading Driver: " . $this->Driver);
            require_once ROOT . '/system/classes/drivers/file/' . $this->Driver . ".php";
            mlog("Driver Loaded: " . $this->Driver);
        } else {
            mlog("Error Finding Driver: " . $this->Driver);
        }

        $file = new ("file_driver_" . $this->Driver);
        return $file->get($this->UniqueID);
    }

    /**
     * Counts the total number of files a user owns.
     *
     * @param int $userID The user ID.
     * @param string $FileType The type of file to count.
     * @return int The number of files.
     */
    public function Count($userID, $FileType = "image")
    {
        $id = Registry::get('SqlSlaves')->safe($userID);
        $FileType = Registry::get('SqlSlaves')->safe($FileType);
        $data = Registry::get('SqlSlaves')->query("SELECT COUNT(*) as count FROM `files-metadata` WHERE `owner` = '$id' AND `filetype` LIKE '%$FileType%'", true, 5);
        return $data[0]['count'];
    }

    /**
     * Sets file properties from an uploaded file object.
     *
     * @param array $UploadObject The uploaded file object.
     */
    public function setFromUpload($UploadObject)
    {
        $this->Owner = Registry::get('User')->id;#$GLOBALS['User']->id;
        # Check if the user has a quota set and if they have reached it before uploading
        $quota = Registry::get('User')->quota;
        logger("User Quota: " . $quota);
        $spaceUsed = floor(Registry::get('User')->getSpaceUsed() / 1024 / 1024);

        if($spaceUsed >= $quota){
            mlog("🔴 User has no quota left - denying upload");
            # set header to 507 to indicate the user has reached their storage limit
            #http_response_code(507);
            #die("You have reached your storage limit");
        }

        $this->FileType = $this->getObjectType($UploadObject['tmp_name']);
        mlog("Saving as type: " . $this->FileType);
        $this->FileName = $UploadObject['name'];
        $this->FileSize = strlen(file_get_contents($UploadObject['tmp_name']));
        $this->Created = date("Y-m-d H:i:s");
        $this->Modified = date("Y-m-d H:i:s");
        $this->FileHash = md5(file_get_contents($UploadObject['tmp_name']));
        $this->FilePath = $UploadObject['tmp_name'];

        $this->Content = file_get_contents($UploadObject['tmp_name']);
    }

    /**
     * Determines the MIME type of a file.
     *
     * @param string $path The path to the file.
     * @return string The MIME type of the file.
     */
    public function getObjectType($path)
    {
        $mime = mime_content_type($path);

        // Conversion table for certain MIME types
        $conversionTable = array(
            "image/x-ms-bmp" => "image/bmp",
            "image/x-windows-bmp" => "image/bmp",
            "image/jpeg" => "image/jpg",
            "image/pjpeg" => "image/jpg",
            "image/png" => "image/png",
            "image/gif" => "image/gif",
            "image/tiff" => "image/tiff",
            "image/x-tiff" => "image/tiff",
            "image/x-targa" => "image/tga",
            "image/x-tga" => "image/tga",
            "image/x-bmp" => "image/bmp"
        );

        if (array_key_exists($mime, $conversionTable)) {
            $mime = $conversionTable[$mime];
        }

        return $mime;
    }

    private function shouldCompress($fileType, $fileSize)
    {
        $compressedTypes = ['video/mp4', 'video/mkv', 'audio/mp3', 'image/jpeg']; // Add other types as needed
        $maxSizeForCompression = 100 * 1024 * 1024; // 100MB, adjust as needed
    
        if (in_array($fileType, $compressedTypes) || $fileSize > $maxSizeForCompression) {
            return false;
        }
        return true;
    }
}
