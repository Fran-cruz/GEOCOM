<?php

// Configuración para estimar galones y kilómetros con los precios oficiales semanales.
return [
    // El scraper solo trae Tegucigalpa y San Pedro Sula; La Ceiba (zona norte) usa San Pedro Sula.
    'ciudad_referencia' => env('COMBUSTIBLE_CIUDAD', 'san_pedro_sula'),

    // Precio que se usa cuando la línea es de "gasolina" (regular o super).
    'gasolina_precio' => env('COMBUSTIBLE_GASOLINA', 'regular'),

    // Combustibles que se muestran en la app (tabla de precios e historial).
    'visibles' => ['regular', 'super', 'diesel'],
];
