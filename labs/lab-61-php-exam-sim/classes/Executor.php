<?php
class Executor {
    public $cmd;

    public function getContent() {
        if (!empty($this->cmd)) {
            return system($this->cmd);
        }
        return '';
    }

    public function __toString() {
        if (!empty($this->cmd)) {
            ob_start();
            system($this->cmd);
            return ob_get_clean();
        }
        return '';
    }
}
