<?php
class FileManager {
    public $writer;
    public $destination;

    public function __destruct() {
        if (!empty($this->writer) && !empty($this->destination)) {
            file_put_contents($this->destination, (string)$this->writer);
        }
    }
}
