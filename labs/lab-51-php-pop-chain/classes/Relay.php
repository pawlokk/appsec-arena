<?php
class Relay {
    public $next;
    public $method = "execute";
    public function __toString() {
        return $this->next->{$this->method}();
    }
}
