# Brewing Salt Optimiser

Designed to work with the Brewer's Friend calculator at http://www.brewersfriend.com/mash-chemistry-and-brewing-water-calculator/ and written in PHP so they can integrate it if they want. Feature request is in their forum!

This takes a source water profile and a target water profile and generates optimal salt additions to minimise the total error across all the different ions (Calcium, Magnesium, Sodium, Chlorides, Sulphates, HCO alkalinity). These are very precise, optimal measures for reducing that target. At the result given, increasing or decreasing or trading off any salts will increase the total error.

An example of use is in test_brewing_salt_additions.php and calculates salts to change from Wellington water (as well as I can read the report) to Brewer's Friend's "Balanced Profile I".

Note that:
* This is not integrated with a user interface, and is useful for programmers only in this form.
* Inputs are in mg/L (= ppm) targets, g/L to add as outputs. Imperial weight conversion is not included in the calculator itself.
* At the moment all ions are treated as equally important to get right. There's no variable scale of how bad a deviation over or under the target is for any given ion. If someone wants to provide sensible defaults, those could be incorporated.
* Heavily rounded outputs (e.g. to closest teaspoon) of this are not necessarily optimal - rounding may introduce more error for one solution than another. They should, however, be close to optimal for any reasonable rounding. The problem of finding an optimal solution after rounding is called integer programming, and is quite feasible for this problem but I would go about it a different way.
