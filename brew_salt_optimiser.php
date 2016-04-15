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
    "Gypsum" => array("Ca" => 234, "SO4" => 558),
    "Epsom Salt" => array("Mg" => 99, "SO4" => 390),
    "Table Salt" => array("Na" => 393, "Cl" => 606),
    "Calcium Chloride" => array("Ca" => 273, "Cl" => 483),
    "Magnesium Chloride" => array("Mg" => 120, "Cl" => 348),
    "Chalk" => array("Ca" => 201)
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
        $excess_constraint[] = 0 - $amount;
        $lack_constraint[] = $amount;
      }
    
      /* Columns for the excess variables */
      foreach($ions as $ei)
      {
        if ($ion === $ei)
        {
          $excess_constraint[] = 1;
        }
        else
        {
          $excess_constraint[] = 0;
        }
        $lack_constraint[] = 0;
      }
    
      /* Columns for the lack variables */
      foreach($ions as $ui)
      {
        if ($ion === $ei)
        {
          $lack_constraint[] = 1;
        }
        else
        {
          $lack_constraint[] = 0;
        }
        $excess_constraint[] = 0;
      }
    
      /* Slack on the excess */
      foreach($ions as $ei)
      {
        if ($ion === $ei)
        {
          $excess_constraint[] = 1;
        }
        else
        {
          $excess_constraint[] = 0;
        }
        $lack_constraint[] = 0;
      }
    
      /* Slack on the lack */
      foreach($ions as $ui)
      {
        /* Identity matrix at the start */
        if ($ion === $ei)
        {
          $lack_constraint[] = 1;
        }
        else
        {
          $lack_constraint[] = 0;
        }
        $excess_constraint[] = 0;
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

      $row_values[] = $initial_values[$ion] - $target_values[$ion];
      $goal[] = 1;
    
      $row_values[] = $target_values[$ion] - $initial_values[$ion];
      $goal[] = 1;
    }
    
    /* Zero slack variable impact on minimisation goal */
    foreach ($ions as $ion)
    {
      $goal[] = 0;
      $goal[] = 0;
    }
  
    /* Optimise */
    $result = SimplexOptimiser::simplex_minimise($goal, $matrix, $row_values);
  
    //echo "Variable values:\n";
    //var_dump($result['variable_values']);
  
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
