<?php

namespace Test\Models;

class DogEntityComplex
{
    public $animalId;
    public $animalName;
    public $animalType;
    public $weightKg;

    // Custom method to convert weight to pounds
    public function getWeightInPounds()
    {
        return $this->weightKg * 2.20462;
    }

    // Custom method to get a formatted string
    public function getDescription()
    {
        return sprintf("%s is a %s with ID #%d weighing %.1f kg",
            $this->animalName,
            $this->animalType,
            $this->animalId,
            $this->weightKg
        );
    }
}
