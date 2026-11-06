<?php

/*
 * The list "What do you need?" on the Inquire page, in the order it shows them.
 *
 * @var list<array{slug: string, name: string, needs_furniture?: bool}>
 */
return [
    ['slug' => 'full-package', 'name' => 'Stone supply, fabrication and installation'],
    ['slug' => 'fabrication-supply', 'name' => 'Stone supply and fabrication'],
    ['slug' => 'installation-supply', 'name' => 'Stone supply and installation'],
    ['slug' => 'supply-only', 'name' => 'Stone supply only'],
    ['slug' => 'furniture-only', 'name' => 'Customized furniture', 'needs_furniture' => true],
    ['slug' => 'full-kitchen-fit-out', 'name' => 'Full kitchen fit-out'],
    ['slug' => 'cabinetry-countertop-installation', 'name' => 'Cabinetry with stone countertop and installation'],
    ['slug' => 'cabinetry-countertop-supply', 'name' => 'Cabinetry with stone countertop'],
];
