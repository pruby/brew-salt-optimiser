<?php

function debug_print_simplex_matrix($goal, $matrix, $row_values, $goal_value)
{
  foreach($goal as $v)
  {
    printf("%+04.2f  ", $v);
  }
  printf(" | %+04.2f\n\n", $goal_value);
  for ($i = 0; $i < count($matrix); $i++)
  {
    $row = $matrix[$i];
    foreach($row as $v)
    {
      printf("%+04.2f  ", $v);
    }
    $r = $row_values[$i];
    printf(" | %+04.2f\n", $r);
  }
}

/*
 * Given a goal vector and matrix of constraints, minimise the goal vector using the Simplex algorithm.
*/
function _simplex_phase_internal($goal, $constraint_coefficients, $row_values, $goal_value = 0, $basis_columns = array())
{
  /* Validate input array sizes */
  $variable_count = count($goal);
  $constraint_count = count($constraint_coefficients);
  assert(count($row_values) === $constraint_count, "Invalid goal vector length in simplex_minimise");
  foreach ($constraint_coefficients as $row)
  {
    assert(count($row) === $variable_count, "Invalid constraint row length in simplex_minimise");
  }
  
  //echo "Start phase\n";
  
  $status = "Feasible";
  
  $iter = 0;
  while(true)
  {
    //echo "Start iteration\n";
    //debug_print_simplex_matrix($goal, $constraint_coefficients, $row_values, $goal_value);
  
    /* Select entering variable */
    $entering_variable = -1;
    $max_slope = 0;
    for ($i = 0; $i < $variable_count; $i++)
    {
      if ($goal[$i] > $max_slope)
      {
        $entering_variable = $i;
        $max_slope = $goal[$i];
      }
    }
    
    if ($entering_variable < 0)
    {
      /* No further optimisation possible. */
      break;
    }
    
    //echo "$entering_variable entering with goal value $max_slope\n";
    
    /* Select variable to leave the basis */
    $leaving_row = -1;
    $seen_zero = false;
    $max_slope = 0;
    for ($i = 0; $i < $constraint_count; $i++)
    {
      if ($constraint_coefficients[$i][$entering_variable] > 0)
      {
        if ($row_values[$i] >= 0)
        {
          $gradient = $row_values[$i] / $constraint_coefficients[$i][$entering_variable];
          if ($leaving_row < 0 || $gradient < $max_slope)
          {
            $leaving_row = $i;
            $max_slope = $gradient;
          }
        }
      }
    }
    
    if ($leaving_row < 0 && $seen_zero)
    {
      //echo "Infinite solutions - return feasible\n";
      break;
    }
    elseif ($leaving_row < 0)
    {
      //echo "No leaving row - unbounded\n";
      $status = "Unbounded";
      break;
    }
    
    //echo "$leaving_row leaving with gradient $max_slope\n";
    
    /* Multiply the row by a constant, so that it has a one in the entering column */
    $row_scale = $constraint_coefficients[$leaving_row][$entering_variable];
    $row_values[$leaving_row] /= $row_scale;
    for ($i = 0; $i < $variable_count; $i++)
    {
      $constraint_coefficients[$leaving_row][$i] /= $row_scale;
    }
    
    /* Subtract a multiple of this row from each other to zero the other entries in the entering column */
    for ($i = 0; $i < $constraint_count; $i++)
    {
      if ($i === $leaving_row)
      {
        continue;
      }
      
      $subtract_ratio = $constraint_coefficients[$i][$entering_variable];
      for ($j = 0; $j < $variable_count; $j++)
      {
        $constraint_coefficients[$i][$j] -= $subtract_ratio * $constraint_coefficients[$leaving_row][$j];
      }
      $row_values[$i] -= $subtract_ratio * $row_values[$leaving_row];
    }
    
    /* Subtract out the leaving row from the goal */
    $subtract_ratio = $goal[$entering_variable];
    for ($j = 0; $j < $variable_count; $j++)
    {
      $goal[$j] -= $subtract_ratio * $constraint_coefficients[$leaving_row][$j];
    }
    $goal_value -= $subtract_ratio * $row_values[$leaving_row];
    
    $basis_columns[$leaving_row] = $entering_variable;
  }
  
  /* Record values of basis variables */
  $variable_values = array();
  for ($i = 0; $i < $constraint_count; $i++)
  {
    if (array_key_exists($i, $basis_columns))
    {
      $variable_values[$basis_columns[$i]] = $row_values[$i];
    }
  }
  
  //echo "Simplex phase done\n";
  //debug_print_simplex_matrix($goal, $constraint_coefficients, $row_values, $goal_value);
  
  $result = array();
  $result["status"] = $status;
  $result["basis"] = $basis_columns;
  $result["goal"] = $goal;
  $result["matrix"] = $constraint_coefficients;
  $result["row_values"] = $row_values;
  $result["variable_values"] = $variable_values;
  $result["goal_value"] = $goal_value;
  return $result;
}

/*
* Find a feasible set of values for the provided Simplex matrix, then optimise for a goal.
*/
function simplex_solve_minimise($goal, $constraint_coefficients, $row_values, $initial_goal = 0)
{
  /* Validate input array sizes */
  $variable_count = count($goal);
  $constraint_count = count($constraint_coefficients);
  assert(count($row_values) === $constraint_count, "Invalid goal vector length in simplex_minimise");
  foreach ($constraint_coefficients as $row)
  {
    assert(count($row) === $variable_count, "Invalid constraint row length in simplex_minimise");
  }
  
  /* Invert negative rows by multiplying by -1 */
  for ($i = 0; $i < $constraint_count; $i++)
  {
    if ($row_values[$i] < 0)
    {
      $row_values[$i] *= -1;
      for ($j = 0; $j < $variable_count; $j++)
      {
        $constraint_coefficients[$i][$j] *= -1;
      }
    }
  }
  
  /*
   *  Populate the feasibility goal with the sum of each column
   *  Simulates pricing out an artificial variable for each constraint, produces the goal function to
   *  remove the artificials from the basis.
   */
  $feasibility_goal = array();
  for ($i = 0; $i < $variable_count; $i++)
  {
    $column_total = 0;
    foreach($constraint_coefficients as $row)
    {
      $column_total += $row[$i];
    }
    $feasibility_goal[] = $column_total;
  }
  $infeasibility = array_sum($row_values);
  
  // Add the initial goal in to the constraints
  $constraint_coefficients[] = $goal;
  $row_values[] = $initial_goal;
  
  $feasible_solution = _simplex_phase_internal($feasibility_goal, $constraint_coefficients, $row_values, $infeasibility, array());
  
  assert($feasible_solution["status"] === "Feasible");
  
  if ($feasible_solution["goal_value"] > 0)
  {
    /* Failed to find a feasible solution */
    //return "Infeasible";
  }
  
  /* pop off the new goal function */
  $constraint_coefficients = $feasible_solution["matrix"];
  $row_values = $feasible_solution["row_values"];
  $goal = array_pop($constraint_coefficients);
  $goal_value = array_pop($row_values);
  
  $optimal_solution = _simplex_phase_internal($goal, $constraint_coefficients, $row_values, $goal_value, $feasible_solution["basis"]);
  
  return $optimal_solution;
}

function optimise_brewing_salts($initial_values, $target_values, $available_salts)
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
  $result = simplex_solve_minimise($goal, $matrix, $row_values);
  
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

/*
  Grabbed values from the calculator for a 30L batch - will need to calculate these in actuality
*/
$SALTS = array(
  "Gypsum" => array("Ca" => 7.8, "SO4" => 18.6),
  "Epsom Salt" => array("Mg" => 3.3, "SO4" => 13),
  "Table Salt" => array("Na" => 13.1, "Cl" => 20.2),
  "Calcium Chloride" => array("Ca" => 9.1, "Cl" => 16.1),
  "Magnesium Chloride" => array("Mg" => 4, "Cl" => 11.6),
  "Chalk" => array("Ca" => 6.7),
  "Baking Soda" => array("Na" => 9.1),
  "Slaked Lime" => array("Ca" => 18.0),
  "Lye" => array("Na" => 19.2)
);

$wellington_water = array("Ca" => 38, "Mg" => 9, "Na" => 12, "Cl" => 15, "SO4" => 4);
$balanced_profile = array("Ca" => 80, "Mg" => 5, "Na" => 25, "Cl" => 75, "SO4" => 80);

$out = optimise_brewing_salts($wellington_water, $balanced_profile, $SALTS);
foreach ($out as $salt => $amount)
{
  echo "${amount}g of $salt\n";
}
