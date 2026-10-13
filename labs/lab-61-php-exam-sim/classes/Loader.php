<?php
class Loader {
    public $path;

    public function __wakeup() {
        if (!empty($this->path)) {
            include($this->path);
        }
    }
}
