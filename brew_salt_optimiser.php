<?php

require_once('simplex_optimiser.php');

/*
 * Brew Salt Optimising Calculator.
 *
 * Given a source water profile and a target water profile, calculates the optimum
 * additions of a set of available salts. Custom salt configuration may be provided
 * to use alternate and/or non-metric additions.
 *
 * Fairly primitive for now - treats all ion deviations as equally bad and salts as zero cost.
 */

class BrewSaltOptimiser
{
  /*
    Impact in mg/L of 1g of salt in 1L of water
  */
  const DEFAULT_SALTS = array(
    "Gypsum" => array("Ca" => 232.8, "SO4" => 557.9),
    "Epsom Salt" => array("Mg" => 98.6, "SO4" => 389.7),
    "Table Salt" => array("Na" => 393.4, "Cl" => 606.7),
    "Calcium Chloride" => array("Ca" => 272.6, "Cl" => 482.3),
    "Magnesium Chloride" => array("Mg" => 119.5, "Cl" => 348.7),
    "Chalk" => array("Ca" => 200.2, "HCO" => 606.8),
    "Baking Soda" => array("Na" => 273.7, "HCO" => 710.0)
  );
  
  public static function optimise_brewing_salts($initial_values, $target_values, $available_salts = BrewSaltOptimiser::DEFAULT_SALTS)
  {
    $ions = array_keys($initial_values);
  
    $row_values = array();
    $goal = array();
    $basis_columns = array();
    $initial_difference = 0;
  
    foreach ($ions as $ion)
    {
      $excess_constraint = array();
      $lack_constraint = array();
    
      /* Columns for the salt addition variables */
      foreach($available_salts as $salt => $ion_content)
      {
        $amount = 0;
        if (array_key_exists($ion, $ion_content))
        {
          $amount = $ion_content[$ion];
        }
        $excess_constraint[] = $amount;
        $lack_constraint[] = $amount;
      }
    
      /* Generate the pattern of columns for the excess and lack variables */
      foreach($ions as $ei)
      {
        if ($ion === $ei)
        {
          /* Excess variable and surplus variable on the excess. */
          $excess_constraint[] = 1;
          $excess_constraint[] = -1;
          $excess_constraint[] = 0;
          $excess_constraint[] = 0;
          
          /* Lack variable and slack variable on the lack. */
          $lack_constraint[] = 0;
          $lack_constraint[] = 0;
          $lack_constraint[] = 1;
          $lack_constraint[] = 1;
        }
        else
        {
          /* Excess/lack on a different ion - zero columns in our rows */
          for ($j = 0; $j < 4; $j++)
          {
            $excess_constraint[] = 0;
            $lack_constraint[] = 0;
          }
        }
      }
    
      $matrix[] = $excess_constraint;
      $matrix[] = $lack_constraint;
    }
  
    /* Set up goal values, target values */
    foreach ($available_salts as $salt => $ion_content)
    {
      /* Actual salt amounts don't affect the goal */
      $goal[] = 0;
    }
  
    /* The excess and lack columns all contribute to the minimisation goal */
    foreach ($ions as $ion)
    {
      $initial_difference += abs($target_values[$ion] - $initial_values[$ion]);

      $row_values[] = $target_values[$ion] - $initial_values[$ion];
      $goal[] = 1;
      $goal[] = 0; // Surplus

      $row_values[] = $target_values[$ion] - $initial_values[$ion];
      $goal[] = 1;
      $goal[] = 0; // Slack
    }
  
    /* Optimise */
    $result = SimplexOptimiser::simplex_minimise($goal, $matrix, $row_values);
  
    $salt_additions = array();
    $i = -1;
    foreach ($available_salts as $salt => $ion_content)
    {
      $i++;
      if (array_key_exists($i, $result["variable_values"]))
      {
        $salt_additions[$salt] = $result["variable_values"][$i];
      }
      else
      {
        $salt_additions[$salt] = 0;
      }
    }
  
    return $salt_additions;
  }
}
