<?php

require_once('brew_salt_optimiser.php');

$wellington_water = array("Ca" => 5, "Mg" => 1, "Na" => 13, "Cl" => 22, "SO4" => 5, "HCO" => 31.2);
$balanced_profile = array("Ca" => 80, "Mg" => 5, "Na" => 25, "Cl" => 75, "SO4" => 80, "HCO" => 100);
$litres = 30;

$available_salts = array("Table Salt", "Gypsum", "Chalk", "Calcium Chloride", "Epsom Salt");
$salts = array();
foreach ($available_salts as $salt)
{
  $salts[$salt] = BrewSaltOptimiser::DEFAULT_SALTS[$salt];
}

$out = BrewSaltOptimiser::optimise_brewing_salts($wellington_water, $balanced_profile, $salts);

echo "Optimal salt additions to convert between profiles for $litres litres of water:\n";
foreach ($out as $salt => $amount)
{
  $amount = $amount * $litres;
  echo "${amount} grams of $salt\n";
}
