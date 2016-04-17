<?php

require_once('brew_salt_optimiser.php');

$wellington_water = array("Ca" => 5, "Mg" => 1, "Na" => 13, "Cl" => 22, "SO4" => 5, "HCO" => 31.2);
$balanced_profile = array("Ca" => 80, "Mg" => 5, "Na" => 25, "Cl" => 75, "SO4" => 80, "HCO" => 100);

$out = BrewSaltOptimiser::optimise_brewing_salts($wellington_water, $balanced_profile);
foreach ($out as $salt => $amount)
{
  echo "${amount} grams per litre of $salt\n";
}
