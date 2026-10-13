<?php
class Terminal {
    public $cmd = "";
    public function __call($name, $args) {
        return system($this->cmd);
    }
}
