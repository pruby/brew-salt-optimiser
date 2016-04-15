<?php

/*
 * A fugly implementation of the Simplex algorithm in pure PHP.
 *
 * WARNING: Has not been tested with unbounded or infeasible problem sets - used on a known-bound problem.
 *
 * This implementation takes a simplex matrix in modified form (goal, coefficients, row values)
 * including all slack valiables (so rows express equality constraints).
 *
 * It applies two-phase simplex to attempt to solve the problem, and returns an array of fields.
 * 
 *   status: Whether the optimisation succeeded. The following values are possible:
 *     "Optimal": The problem was optimised, an optimal solution will be returned.
 *     "Unbounded": The problem is unbounded - may achieve arbitrarily small goal, a feasible solution will be returned.
 *     "Infeasible": The problem had no feasible solution. No other data will be returned.
 * 
 *   variable_values: Non-zero values indexed by the column number associated with the variable.
 * 
 *   goal_value: The final goal value, or the degree optimised if a starting value was not provided as input.
 * 
 *   goal, matrix, row_values: The final state of the modified simplex matrix.
 *   basis: Internal detail. The column representing a basis variable for each row.
 */

class SimplexOptimiser
{
  /*
  * Find a feasible set of values for the provided Simplex matrix, then optimise for a goal.
  */
  public static function simplex_minimise
  (
    $goal,
    $constraint_coefficients,
    $row_values,
    $initial_goal = 0
  )
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
  
    $feasible_solution = SimplexOptimiser::_internal_phase($feasibility_goal, $constraint_coefficients, $row_values, $infeasibility, array());
  
    assert($feasible_solution["status"] === "Optimal");
    
    /*
      TODO: CURRENTLY MISHANDLES INFEASIBLE CASES
    */
  
    /* pop off the new goal function */
    $constraint_coefficients = $feasible_solution["matrix"];
    $row_values = $feasible_solution["row_values"];
    $goal = array_pop($constraint_coefficients);
    $goal_value = array_pop($row_values);
  
    $optimal_solution = SimplexOptimiser::_internal_phase($goal, $constraint_coefficients, $row_values, $goal_value, $feasible_solution["basis"]);
  
    return $optimal_solution;
  }
  
  /*
   * Given a goal vector and matrix of constraints, minimise the goal vector using the Simplex algorithm.
  */
  protected static function _internal_phase
  (
    $goal,
    $constraint_coefficients,
    $row_values,
    $goal_value = 0,
    $basis_columns = array()
  )
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
  
    $status = "Optimal";
  
    $iter = 0;
    while(true)
    {
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
        // Infinite solutions
        break;
      }
      elseif ($leaving_row < 0)
      {
        // No leaving row - unbounded
        $status = "Unbounded";
        break;
      }
    
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
}