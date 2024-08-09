<?php

use Duplicator\Models\Storages\AbstractStorageEntity;

defined("ABSPATH") or die("");
abstract class DUP_PRO_Upload_Status
{
    const Pending   = 0;
    const Running   = 1;
    const Succeeded = 2;
    const Failed    = 3;
    const Cancelled = 4;
}

// Tracks the progress of the package with relation to a specific storage provider
// Used to track a specific upload as well as later report on its' progress
class DUP_PRO_Package_Upload_Info
{
    /** @var int<-1,max> */
    protected $storage_id = -1;
    /** @var int */
    public $archive_offset = 0;
    /** @var bool Next byte of archive to copy */
    public $copied_installer = false;
    /** @var bool Whether installer has been copied */
    public $copied_archive = false;
    /** @var float Whether archive has been copied */
    public $progress = 0;
    /** @var int 0-100 where this particular storage is at */
    public $num_failures = 0;
    /** @var bool */
    public $failed = false;
    /** @var bool If catastrophic failure has been experienced or num_failures exceeded threshold */
    public $cancelled = false;
    /** @var scalar */
    public $upload_id = null;
    /** @var int */
    public $failure_count = 0;
    /** @var mixed */
    public $data = '';
    /** @var mixed */
    public $data2 = '';
    // Storage specific data
    // Log related properties - these all SHOULD be public but since we need to json_encode them they have to be public. Ugh.
    /** @var bool */
    public $has_started = false;
    /** @var string */
    public $status_message_details = '';
    // Details about the storage run (success or failure)
    /** @var int */
    public $started_timestamp = 0;
    /** @var int */
    public $stopped_timestamp = 0;
    /** @var mixed[] chunk iterator data */
    public $chunkPosition = [];

    /**
     * Class constructor
     *
     * @param int $storage_id The storage id
     */
    public function __construct($storage_id)
    {
        $this->setStorageId($storage_id);
    }

    /**
     * Set the storage id
     *
     * @param int $storage_id The storage id
     *
     * @return void
     */
    public function setStorageId($storage_id)
    {
        if ($storage_id < 0) {
            $this->storage_id = -1;
        }
        $this->storage_id = (int) $storage_id;
    }

    /**
     * Get storage id
     *
     * @return int
     */
    public function getStorageId()
    {
        // For old pacakges, some storage ids are strings
        return (int) $this->storage_id;
    }

    /**
     * Return true if the upload has started
     *
     * @return bool
     */
    public function has_started()
    {
        return $this->has_started;
    }

    /**
     * Start the upload
     *
     * @return void
     */
    public function start()
    {
        $this->has_started       = true;
        $this->started_timestamp = time();
    }

    /**
     * Stop the upload
     *
     * @return void
     */
    public function stop()
    {
        $this->stopped_timestamp = time();
    }

    /**
     * Get started timestamp
     *
     * @return int
     */
    public function get_started_timestamp()
    {
        return $this->started_timestamp;
    }

    /**
     * Get stopped timestamp
     *
     * @return int
     */
    public function get_stopped_timestamp()
    {
        return $this->stopped_timestamp;
    }

    /**
     * Get the status text
     *
     * @return string
     */
    public function get_status_text()
    {
        $status      = $this->get_status();
        $status_text = DUP_PRO_U::__('Unknown');
        if ($status == DUP_PRO_Upload_Status::Pending) {
            $status_text = DUP_PRO_U::__('Pending');
        } elseif ($status == DUP_PRO_Upload_Status::Running) {
            $status_text = DUP_PRO_U::__('Running');
        } elseif ($status == DUP_PRO_Upload_Status::Succeeded) {
            $status_text = DUP_PRO_U::__('Succeeded');
        } elseif ($status == DUP_PRO_Upload_Status::Failed) {
            $status_text = DUP_PRO_U::__('Failed');
        } elseif ($status == DUP_PRO_Upload_Status::Cancelled) {
            $status_text = DUP_PRO_U::__('Cancelled');
        }

        return $status_text;
    }

    /**
     * Get the status
     *
     * @return int
     */
    public function get_status()
    {
        if ($this->cancelled) {
            $status = DUP_PRO_Upload_Status::Cancelled;
        } elseif ($this->failed) {
            $status = DUP_PRO_Upload_Status::Failed;
        } elseif ($this->has_started() === false) {
            $status = DUP_PRO_Upload_Status::Pending;
        } elseif ($this->has_completed(true)) {
            $status = DUP_PRO_Upload_Status::Succeeded;
        } else {
            $status = DUP_PRO_Upload_Status::Running;
        }

        return $status;
    }

    /**
     * Set the status message details
     *
     * @param string $status_message_details The status message details
     *
     * @return void
     */
    public function set_status_message_details($status_message_details)
    {
        $this->status_message_details = $status_message_details;
    }

    /**
     * Get the status message
     *
     * @return string
     */
    public function get_status_message()
    {
        $message = '';
        $status  = $this->get_status();
        $storage = AbstractStorageEntity::getById($this->storage_id);
        if ($storage !== false) {
            if ($status == DUP_PRO_Upload_Status::Pending) {
                $message = $storage->getPendingText();
            } elseif ($status == DUP_PRO_Upload_Status::Failed) {
                $message = $storage->getFailedText();
            } elseif ($status == DUP_PRO_Upload_Status::Cancelled) {
                $message = $storage->getCancelledText();
            } elseif ($status == DUP_PRO_Upload_Status::Succeeded) {
                $message = $storage->getSuccessText();
            } else {
                $message = $storage->getActionText();
            }
        } else {
            $message = "Error. Unknown storage id {$this->storage_id}";
            DUP_PRO_Log::trace($message);
        }

        $message_details = $this->status_message_details == '' ? '' : " ($this->status_message_details)";
        $message         = "$message$message_details";
        return $message;
    }

    /**
     * Return true if the upload has completed
     *
     * @param bool $count_only_success If true then only return true if the upload has completed successfully
     *
     * @return bool
     */
    public function has_completed($count_only_success = false)
    {
        $retval = false;
        if ($count_only_success) {
            $retval = (($this->failed == false) && ($this->cancelled == false) && ($this->copied_installer && $this->copied_archive));
        } else {
            $retval = $this->failed || ($this->copied_installer && $this->copied_archive) || $this->cancelled;
        }

        if ($retval && ($this->stopped_timestamp == null)) {
            // Having to set stopped this way because we aren't OO and allow everyone to set failed/other flags so impossible to know exactly when its done
            $this->stop();
        }

        return $retval;
    }

    /**
     * Increase the failure count
     *
     * @return void
     */
    public function increase_failure_count()
    {
        $global = DUP_PRO_Global_Entity::getInstance();
        $this->failure_count++;
        DUP_PRO_Log::infoTrace("Failure count increasing to $this->failure_count [Storage Id: $this->storage_id]");
        if ($this->failure_count > $global->max_storage_retries) {
            DUP_PRO_Log::infoTrace("* Failure count reached to max level, Storage Status updated to failed [Storage Id: $this->storage_id]");
            $this->failed = true;
        }
    }
}
