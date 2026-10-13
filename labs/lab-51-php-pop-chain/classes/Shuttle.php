<?php
class Shuttle {
    public $transport;
    public function __wakeup() {
        $s = (string)$this->transport;
        echo $s;
    }
}
