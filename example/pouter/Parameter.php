<?php

class Parameter
{
    public string $name;
    public $value;

    public function __toString()
    {
        return $this->value;
    }

    public function __construct($name, $value) {
        $this->name = $name;
        $this->value = $value;
    }
}

class RouteParam extends Parameter {}
class GetParam extends Parameter {}
class PostParam extends Parameter {}