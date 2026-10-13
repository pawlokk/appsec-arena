<?php
class Serializer {
    public $data;
    public $format;

    public function __toString() {
        if ($this->format === 'raw') {
            return (string)$this->data;
        }
        return serialize($this->data);
    }
}
