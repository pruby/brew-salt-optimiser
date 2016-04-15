<?php

require_once('brew_salt_optimiser.php');

$wellington_water = array("Ca" => 38, "Mg" => 9, "Na" => 12, "Cl" => 15, "SO4" => 4);
$balanced_profile = array("Ca" => 80, "Mg" => 5, "Na" => 25, "Cl" => 75, "SO4" => 80);

$out = BrewSaltOptimiser::optimise_brewing_salts($wellington_water, $balanced_profile);
foreach ($out as $salt => $amount)
{
  echo "${amount} grams per litre of $salt\n";
}
